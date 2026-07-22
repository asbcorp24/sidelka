<?php

namespace App\Services;

use App\Models\LegalContract;
use App\Models\Order;
use App\Models\OrderScheduleSlot;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LegalContractService
{
    public function createFramework(User $user, User $actor): LegalContract
    {
        abort_unless(in_array($user->role, ['client', 'caregiver'], true), 422);

        $this->assertCompanyConfigured();
        $this->assertProfileComplete($user);

        $type = $user->isClient()
            ? LegalContract::TYPE_CLIENT_AGENCY
            : LegalContract::TYPE_CAREGIVER_AGENCY;

        LegalContract::query()
            ->where('type', $type)
            ->where('status', LegalContract::STATUS_AWAITING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->whereHas('parties', fn ($query) => $query->where('user_id', $user->id))
            ->update(['status' => LegalContract::STATUS_SUPERSEDED]);

        $existing = LegalContract::query()
            ->where('type', $type)
            ->whereHas('parties', fn ($query) => $query->where('user_id', $user->id))
            ->where(function ($query) {
                $query->where('status', LegalContract::STATUS_SIGNED)
                    ->orWhere(function ($active) {
                        $active->where('status', LegalContract::STATUS_AWAITING)
                            ->where(function ($notExpired) {
                                $notExpired->whereNull('expires_at')
                                    ->orWhere('expires_at', '>', now());
                            });
                    });
            })
            ->latest('id')
            ->first();

        if ($existing) {
            return $existing->load('parties.signature');
        }

        $version = (int) LegalContract::query()
            ->where('type', $type)
            ->whereHas('parties', fn ($query) => $query->where('user_id', $user->id))
            ->max('version') + 1;

        $prefix = $user->isClient() ? 'AG-CL' : 'AG-CG';
        $number = sprintf('%s-%d-%s-V%d', $prefix, $user->id, now()->format('Ymd'), $version);
        $title = $user->isClient()
            ? 'Агентский договор с заказчиком'
            : 'Партнёрский агентский договор с сиделкой';

        $data = [
            'company' => config('legal.company'),
            'user' => $user->loadMissing('contractProfile'),
            'profile' => $user->contractProfile,
            'number' => $number,
            'date' => now(),
            'commissionPercent' => (float) config('legal.agent_commission_percent'),
        ];

        $view = $user->isClient()
            ? 'contracts.templates.client-agency'
            : 'contracts.templates.caregiver-agency';

        return $this->persistContract(
            type: $type,
            number: $number,
            version: $version,
            title: $title,
            bodyHtml: View::make($view, $data)->render(),
            actor: $actor,
            order: null,
            parties: [
                $this->platformParty(),
                $this->userParty($user, $user->isClient() ? 'client' : 'caregiver'),
            ],
            meta: [
                'user_id' => $user->id,
                'model' => 'platform_agent',
                'commission_percent' => (float) config('legal.agent_commission_percent'),
            ],
        );
    }

    public function createOrderContracts(Order $order, User $actor): Collection
    {
        $this->assertCompanyConfigured();

        $order->loadMissing([
            'client.contractProfile',
            'caregiver.contractProfile',
            'scheduleSlots',
            'caregiverAssignments.caregiver.contractProfile',
            'caregiverAssignments.scheduleSlot',
            'services',
            'expenses',
            'clinicPartnerServices',
        ]);

        if (! $order->client) {
            throw ValidationException::withMessages(['contract' => 'У заказа отсутствует заказчик.']);
        }

        $this->assertProfileComplete($order->client);

        $caregivers = $order->caregiverAssignments
            ->whereIn('status', ['invited', 'accepted', 'completed'])
            ->pluck('caregiver')
            ->filter()
            ->unique('id')
            ->values();

        if ($order->caregiver && $caregivers->doesntContain('id', $order->caregiver->id)) {
            $caregivers->push($order->caregiver);
        }

        if ($caregivers->isEmpty()) {
            throw ValidationException::withMessages([
                'contract' => 'Для договора по заказу сначала выберите хотя бы одну сиделку.',
            ]);
        }

        return $caregivers->map(function (User $caregiver) use ($order, $actor) {
            $this->assertProfileComplete($caregiver);

            LegalContract::query()
                ->where('type', LegalContract::TYPE_ORDER_SERVICE)
                ->where('order_id', $order->id)
                ->where('meta->caregiver_id', $caregiver->id)
                ->where('status', LegalContract::STATUS_AWAITING)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->update(['status' => LegalContract::STATUS_SUPERSEDED]);

            $existing = LegalContract::query()
                ->where('type', LegalContract::TYPE_ORDER_SERVICE)
                ->where('order_id', $order->id)
                ->where('meta->caregiver_id', $caregiver->id)
                ->where(function ($query) {
                    $query->where('status', LegalContract::STATUS_SIGNED)
                        ->orWhere(function ($active) {
                            $active->where('status', LegalContract::STATUS_AWAITING)
                                ->where(function ($notExpired) {
                                    $notExpired->whereNull('expires_at')
                                        ->orWhere('expires_at', '>', now());
                                });
                        });
                })
                ->latest('id')
                ->first();

            if ($existing) {
                return $existing->load('parties.signature');
            }

            $version = (int) LegalContract::query()
                ->where('type', LegalContract::TYPE_ORDER_SERVICE)
                ->where('order_id', $order->id)
                ->where('meta->caregiver_id', $caregiver->id)
                ->max('version') + 1;

            $number = sprintf('ORD-%d-%d-%s-V%d', $order->id, $caregiver->id, now()->format('Ymd'), $version);
            $contractSlots = $this->caregiverSlots($order, $caregiver);
            $minutes = $this->minutesForSlots($order, $contractSlots);
            $serviceAmount = (int) ceil($minutes / 60) * (int) $order->hourly_budget;
            $commissionPercent = (float) config('legal.agent_commission_percent');
            $commissionAmount = (int) round($serviceAmount * $commissionPercent / 100);
            $caregiverAmount = max(0, $serviceAmount - $commissionAmount);

            $body = View::make('contracts.templates.order-service-agent', [
                'company' => config('legal.company'),
                'order' => $order,
                'client' => $order->client,
                'clientProfile' => $order->client->contractProfile,
                'caregiver' => $caregiver,
                'caregiverProfile' => $caregiver->contractProfile,
                'number' => $number,
                'date' => now(),
                'serviceAmount' => $serviceAmount,
                'commissionPercent' => $commissionPercent,
                'commissionAmount' => $commissionAmount,
                'caregiverAmount' => $caregiverAmount,
                'minutes' => $minutes,
                'contractSlots' => $contractSlots,
            ])->render();

            return $this->persistContract(
                type: LegalContract::TYPE_ORDER_SERVICE,
                number: $number,
                version: $version,
                title: 'Договор оказания услуг по заказу #' . $order->id,
                bodyHtml: $body,
                actor: $actor,
                order: $order,
                parties: [
                    $this->platformParty(),
                    $this->userParty($order->client, 'client'),
                    $this->userParty($caregiver, 'caregiver'),
                ],
                meta: [
                    'model' => 'platform_agent',
                    'client_id' => $order->client_id,
                    'caregiver_id' => $caregiver->id,
                    'schedule_slot_ids' => $contractSlots->pluck('id')->values()->all(),
                    'service_amount' => $serviceAmount,
                    'commission_percent' => $commissionPercent,
                    'commission_amount' => $commissionAmount,
                    'caregiver_amount' => $caregiverAmount,
                ],
            );
        });
    }

    private function persistContract(
        string $type,
        string $number,
        int $version,
        string $title,
        string $bodyHtml,
        User $actor,
        ?Order $order,
        array $parties,
        array $meta,
    ): LegalContract {
        $hash = hash('sha256', $bodyHtml);

        return DB::transaction(function () use ($type, $number, $version, $title, $bodyHtml, $hash, $actor, $order, $parties, $meta) {
            $contract = LegalContract::create([
                'public_id' => (string) Str::uuid(),
                'type' => $type,
                'order_id' => $order?->id,
                'created_by_id' => $actor->id,
                'number' => $number,
                'version' => $version,
                'title' => $title,
                'status' => LegalContract::STATUS_AWAITING,
                'body_html' => $bodyHtml,
                'document_hash' => $hash,
                'meta' => $meta,
                'sent_at' => now(),
                'expires_at' => now()->addDays((int) config('legal.contract_lifetime_days', 30)),
            ]);

            foreach ($parties as $partyData) {
                $party = $contract->parties()->create([
                    ...$partyData,
                    'public_token' => (string) Str::uuid(),
                ]);

                if ($party->role === 'platform') {
                    $party->update(['status' => 'signed', 'signed_at' => now()]);
                    $party->signature()->create([
                        'legal_contract_id' => $contract->id,
                        'user_id' => null,
                        'method' => 'platform_offer',
                        'channel' => 'system',
                        'destination' => config('legal.company.email'),
                        'document_hash' => $hash,
                        'signed_at' => now(),
                        'evidence' => [
                            'company' => config('legal.company'),
                            'offer_published_by' => $actor->id,
                        ],
                    ]);
                }
            }

            $contract->events()->create([
                'actor_user_id' => $actor->id,
                'event' => 'created',
                'data' => ['number' => $number, 'document_hash' => $hash],
            ]);

            return $contract->load('parties.signature');
        });
    }

    private function platformParty(): array
    {
        $company = config('legal.company');

        return [
            'user_id' => null,
            'role' => 'platform',
            'name' => (string) $company['name'],
            'email' => $company['email'] ?: null,
            'phone' => $company['phone'] ?: null,
            'is_required' => false,
            'status' => 'pending',
        ];
    }

    private function userParty(User $user, string $role): array
    {
        return [
            'user_id' => $user->id,
            'role' => $role,
            'name' => $user->contractProfile?->legal_full_name ?: $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'is_required' => true,
            'status' => 'pending',
        ];
    }

    private function assertCompanyConfigured(): void
    {
        $company = config('legal.company');
        $required = [
            'name' => 'LEGAL_COMPANY_NAME',
            'inn' => 'LEGAL_COMPANY_INN',
            'ogrn' => 'LEGAL_COMPANY_OGRN',
            'address' => 'LEGAL_COMPANY_ADDRESS',
            'signatory_name' => 'LEGAL_COMPANY_SIGNATORY_NAME',
        ];

        $missing = collect($required)
            ->filter(fn ($env, $field) => empty($company[$field]))
            ->values()
            ->all();

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'contract' => 'Не заполнены реквизиты площадки в .env: ' . implode(', ', $missing) . '.',
            ]);
        }
    }

    private function assertProfileComplete(User $user): void
    {
        $profile = $user->contractProfile;

        if (! $profile) {
            throw ValidationException::withMessages([
                'contract' => 'Сначала заполните договорные данные пользователя ' . $user->name . '.',
            ]);
        }

        $required = [
            'legal_full_name' => 'ФИО',
            'passport_series' => 'серия паспорта',
            'passport_number' => 'номер паспорта',
            'registration_address' => 'адрес регистрации',
        ];

        if ($user->isCaregiver()) {
            $required['inn'] = 'ИНН';
            $required['tax_status'] = 'налоговый статус';
        }

        $missing = collect($required)
            ->filter(fn ($label, $field) => empty($profile->{$field}))
            ->values()
            ->all();

        if (empty($user->phone) && (empty($user->email) || Str::endsWith($user->email, '@sidelka.local'))) {
            $missing[] = 'телефон или реальный email';
        }

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'contract' => 'Для ' . $user->name . ' не заполнены: ' . implode(', ', $missing) . '.',
            ]);
        }
    }

    private function caregiverSlots(Order $order, User $caregiver): Collection
    {
        $assignedSlots = $order->caregiverAssignments
            ->where('caregiver_id', $caregiver->id)
            ->whereIn('status', ['invited', 'accepted', 'completed'])
            ->pluck('scheduleSlot')
            ->filter()
            ->unique('id')
            ->values();

        return $assignedSlots->isNotEmpty()
            ? $assignedSlots
            : $order->scheduleSlots->values();
    }

    private function minutesForSlots(Order $order, Collection $slots): int
    {
        if ($slots->isNotEmpty()) {
            return max(60, (int) $slots->sum(function (OrderScheduleSlot $slot) {
                $start = Carbon::parse($slot->scheduled_date->format('Y-m-d') . ' ' . $slot->starts_at);
                $end = Carbon::parse($slot->scheduled_date->format('Y-m-d') . ' ' . $slot->ends_at);

                return max(0, $start->diffInMinutes($end));
            }));
        }

        if ($order->starts_at && $order->ends_at) {
            return max(60, $order->starts_at->diffInMinutes($order->ends_at));
        }

        return 60;
    }
}
