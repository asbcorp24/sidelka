<?php

namespace App\Services;

use App\Models\LegalContract;
use App\Models\LegalContractParty;
use App\Models\LegalContractSignature;
use App\Notifications\ContractSigningCodeNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class LegalSignatureService
{
    public function sendCode(LegalContractParty $party, Request $request): array
    {
        $party->loadMissing('contract');
        $this->assertCanSign($party);

        $ttl = (int) config('legal.signature.code_ttl_minutes', 10);
        $maxAttempts = (int) config('legal.signature.max_attempts', 5);
        $code = (string) random_int(100000, 999999);
        [$channel, $destination] = $this->resolveChannel($party);

        $party->challenges()
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $challenge = $party->challenges()->create([
            'code_hash' => Hash::make($code),
            'channel' => $channel,
            'destination' => $destination,
            'attempts' => 0,
            'max_attempts' => $maxAttempts,
            'request_ip' => $request->ip(),
            'sent_at' => now(),
            'expires_at' => now()->addMinutes($ttl),
        ]);

        try {
            $providerMessageId = $this->deliver($channel, $destination, $code, $party, $ttl, $request);
            $challenge->update(['provider_message_id' => $providerMessageId]);
        } catch (Throwable $exception) {
            $challenge->delete();
            throw $exception;
        }

        $party->contract->events()->create([
            'actor_user_id' => $request->user()?->id,
            'event' => 'signature_code_sent',
            'data' => [
                'party_id' => $party->id,
                'role' => $party->role,
                'channel' => $channel,
                'destination' => $this->maskDestination($channel, $destination),
            ],
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
        ]);

        return [
            'channel' => $channel,
            'destination' => $this->maskDestination($channel, $destination),
            'expires_at' => $challenge->expires_at,
        ];
    }

    public function sign(LegalContractParty $party, string $code, Request $request): LegalContract
    {
        $party->loadMissing('contract');
        $this->assertCanSign($party);

        $challenge = $party->challenges()
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $challenge) {
            throw ValidationException::withMessages([
                'code' => 'Код отсутствует или истёк. Запросите новый код.',
            ]);
        }

        if ($challenge->attempts >= $challenge->max_attempts) {
            $challenge->update(['consumed_at' => now()]);
            throw ValidationException::withMessages([
                'code' => 'Превышено количество попыток. Запросите новый код.',
            ]);
        }

        $challenge->increment('attempts');
        $challenge->refresh();

        if (! Hash::check(trim($code), $challenge->code_hash)) {
            if ($challenge->attempts >= $challenge->max_attempts) {
                $challenge->update(['consumed_at' => now()]);
            }

            throw ValidationException::withMessages([
                'code' => 'Неверный код электронной подписи.',
            ]);
        }

        return DB::transaction(function () use ($party, $challenge, $request) {
            $lockedParty = LegalContractParty::query()
                ->whereKey($party->id)
                ->lockForUpdate()
                ->firstOrFail();

            $contract = LegalContract::query()
                ->whereKey($lockedParty->legal_contract_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedParty->status === 'signed') {
                return $contract->load('parties.signature');
            }

            if ($contract->status !== LegalContract::STATUS_AWAITING) {
                throw ValidationException::withMessages([
                    'code' => 'Этот договор больше не доступен для подписания.',
                ]);
            }

            if ($contract->expires_at && $contract->expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'code' => 'Срок подписания договора истёк.',
                ]);
            }

            LegalContractSignature::firstOrCreate(
                ['legal_contract_party_id' => $lockedParty->id],
                [
                    'legal_contract_id' => $contract->id,
                    'user_id' => $lockedParty->user_id,
                    'method' => 'simple_code',
                    'channel' => $challenge->channel,
                    'destination' => $challenge->destination,
                    'document_hash' => $contract->document_hash,
                    'signed_at' => now(),
                    'ip_address' => $request->ip(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 4000, ''),
                    'evidence' => [
                        'challenge_id' => $challenge->id,
                        'party_name' => $lockedParty->name,
                        'party_role' => $lockedParty->role,
                        'contract_number' => $contract->number,
                        'agreement_text' => 'Подтверждаю, что прочитал документ, согласен с его условиями и использую полученный одноразовый код как простую электронную подпись.',
                    ],
                ]
            );

            $lockedParty->update([
                'status' => 'signed',
                'signed_at' => now(),
            ]);

            $challenge->update(['consumed_at' => now()]);

            $contract->events()->create([
                'actor_user_id' => $request->user()?->id,
                'event' => 'party_signed',
                'data' => [
                    'party_id' => $lockedParty->id,
                    'role' => $lockedParty->role,
                    'document_hash' => $contract->document_hash,
                    'channel' => $challenge->channel,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
            ]);

            $contract->load('parties');

            if ($contract->isFullySigned()) {
                $contract->update([
                    'status' => LegalContract::STATUS_SIGNED,
                    'signed_at' => now(),
                ]);

                $contract->events()->create([
                    'actor_user_id' => $request->user()?->id,
                    'event' => 'fully_signed',
                    'data' => ['document_hash' => $contract->document_hash],
                    'ip_address' => $request->ip(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
                ]);
            }

            return $contract->fresh()->load('parties.signature');
        });
    }

    private function assertCanSign(LegalContractParty $party): void
    {
        $contract = $party->contract;

        if ($party->role === 'platform' || ! $party->is_required) {
            throw ValidationException::withMessages(['code' => 'Подпись этой стороны не требуется.']);
        }

        if ($party->status === 'signed') {
            throw ValidationException::withMessages(['code' => 'Договор уже подписан этой стороной.']);
        }

        if ($contract->status !== LegalContract::STATUS_AWAITING) {
            throw ValidationException::withMessages(['code' => 'Договор недоступен для подписания.']);
        }

        if ($contract->expires_at && $contract->expires_at->isPast()) {
            throw ValidationException::withMessages(['code' => 'Срок подписания договора истёк.']);
        }
    }

    private function resolveChannel(LegalContractParty $party): array
    {
        $requested = (string) config('legal.signature.channel', 'auto');
        $phone = $this->normalizePhone((string) $party->phone);
        $email = trim((string) $party->email);
        $hasRealEmail = $email !== '' && ! Str::endsWith(Str::lower($email), '@sidelka.local');
        $smsConfigured = ! empty(config('legal.sms_ru.api_id'));

        if ($requested === 'sms' || ($requested === 'auto' && $smsConfigured && $phone !== '')) {
            if (! $smsConfigured) {
                throw ValidationException::withMessages(['code' => 'Для SMS не задан SMS_RU_API_ID.']);
            }
            if ($phone === '') {
                throw ValidationException::withMessages(['code' => 'У стороны договора отсутствует корректный номер телефона.']);
            }

            return ['sms', $phone];
        }

        if ($requested === 'mail' || ($requested === 'auto' && $hasRealEmail)) {
            if (! $hasRealEmail) {
                throw ValidationException::withMessages(['code' => 'У стороны договора отсутствует реальный email.']);
            }

            return ['mail', $email];
        }

        if ($requested === 'log') {
            if (! app()->environment(['local', 'testing'])) {
                throw ValidationException::withMessages([
                    'code' => 'Канал log запрещён на рабочем сервере. Настройте SMS.RU или email.',
                ]);
            }

            return ['log', $phone ?: $email ?: 'application-log'];
        }

        if (app()->environment(['local', 'testing'])) {
            return ['log', $phone ?: $email ?: 'application-log'];
        }

        throw ValidationException::withMessages([
            'code' => 'Невозможно отправить код: настройте SMS.RU или укажите реальный email стороны.',
        ]);
    }

    private function deliver(
        string $channel,
        string $destination,
        string $code,
        LegalContractParty $party,
        int $ttl,
        Request $request,
    ): ?string {
        if ($channel === 'mail') {
            Notification::route('mail', $destination)
                ->notify(new ContractSigningCodeNotification($party, $code, $ttl));

            return null;
        }

        if ($channel === 'log') {
            Log::info('Код простой электронной подписи', [
                'contract' => $party->contract->number,
                'party' => $party->id,
                'code' => $code,
                'expires_in_minutes' => $ttl,
            ]);

            return 'log';
        }

        $message = sprintf(
            'Сиделка24: код подписи договора %s: %s. Действует %d мин. Никому не сообщайте код.',
            $party->contract->number,
            $code,
            $ttl
        );

        $payload = [
            'api_id' => config('legal.sms_ru.api_id'),
            'to' => $destination,
            'msg' => $message,
            'json' => 1,
            'ip' => $request->ip(),
        ];

        if (config('legal.sms_ru.from')) {
            $payload['from'] = config('legal.sms_ru.from');
        }

        if (config('legal.sms_ru.test')) {
            $payload['test'] = 1;
        }

        $response = Http::asForm()
            ->timeout(20)
            ->post((string) config('legal.sms_ru.url'), $payload);

        $response->throw();
        $data = $response->json();
        $sms = $data['sms'][$destination] ?? null;

        if (($data['status'] ?? null) !== 'OK' || ! is_array($sms) || ($sms['status'] ?? null) !== 'OK') {
            throw new RuntimeException(
                (string) ($sms['status_text'] ?? $data['status_text'] ?? 'SMS.RU не принял сообщение.')
            );
        }

        return isset($sms['sms_id']) ? (string) $sms['sms_id'] : null;
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';

        if (strlen($digits) === 11 && Str::startsWith($digits, '8')) {
            $digits = '7' . substr($digits, 1);
        }

        return strlen($digits) === 11 && Str::startsWith($digits, '7') ? $digits : '';
    }

    private function maskDestination(string $channel, string $destination): string
    {
        if ($channel === 'sms' && strlen($destination) >= 4) {
            return '+7 *** ***-' . substr($destination, -4);
        }

        if ($channel === 'mail' && str_contains($destination, '@')) {
            [$name, $domain] = explode('@', $destination, 2);
            return substr($name, 0, 1) . '***@' . $domain;
        }

        return $destination;
    }
}
