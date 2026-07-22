<?php

namespace App\Services;

use App\Models\AgentCommission;
use App\Models\OrderCaregiverAssignment;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\ShiftAct;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShiftSettlementService
{
    public function __construct(private OrderFinanceService $finance)
    {
    }

    public function settle(OrderCaregiverAssignment $assignment, ?int $approvedGrossAmount = null): Payout
    {
        return DB::transaction(function () use ($assignment, $approvedGrossAmount) {
            $locked = OrderCaregiverAssignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $locked->loadMissing(['order', 'caregiver', 'act']);

            $existing = Payout::query()->where('order_caregiver_assignment_id', $locked->id)->first();
            if ($existing) {
                return $existing;
            }

            $act = $locked->act;
            if (! $act || $act->status !== ShiftAct::STATUS_SIGNED) {
                throw ValidationException::withMessages(['act' => 'Для выплаты нужен подписанный акт этой смены.']);
            }

            if ($locked->disputes()->whereIn('status', ['open', 'in_review'])->exists()) {
                throw ValidationException::withMessages(['dispute' => 'По смене открыт спор. Выплата заморожена до решения.']);
            }

            $payment = Payment::query()
                ->where('order_id', $locked->order_id)
                ->where('kind', 'base_order')
                ->whereIn('status', ['held', 'partially_released', 'released'])
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                throw ValidationException::withMessages(['payout' => 'Основная оплата заказа не удержана.']);
            }

            $alreadyReleased = (int) Payout::query()
                ->where('payment_id', $payment->id)
                ->where('status', '!=', 'cancelled')
                ->sum('gross_amount');
            $remaining = max(0, (int) $payment->amount - $alreadyReleased);
            $gross = min($approvedGrossAmount ?? (int) $act->gross_amount, $remaining);

            if ($gross < 0) {
                $gross = 0;
            }

            $percent = (float) (($act->meta ?? [])['commission_percent'] ?? 0);
            $commission = (int) round($gross * $percent / 100);
            $net = max(0, $gross - $commission);

            $payout = Payout::create([
                'order_id' => $locked->order_id,
                'order_caregiver_assignment_id' => $locked->id,
                'payment_id' => $payment->id,
                'caregiver_id' => $locked->caregiver_id,
                'gross_amount' => $gross,
                'commission_percent' => $percent,
                'commission_amount' => $commission,
                'amount' => $net,
                'currency' => $payment->currency,
                'status' => $gross > 0 ? 'pending' : 'cancelled',
                'destination' => 'Банковские реквизиты сиделки',
            ]);

            if ($commission > 0) {
                AgentCommission::firstOrCreate([
                    'payment_id' => $payment->id,
                    'caregiver_id' => $locked->caregiver_id,
                    'order_caregiver_assignment_id' => $locked->id,
                ], [
                    'order_id' => $locked->order_id,
                    'payout_id' => $payout->id,
                    'gross_amount' => $gross,
                    'percent' => $percent,
                    'amount' => $commission,
                    'currency' => $payment->currency,
                    'status' => 'recognized',
                    'recognized_at' => now(),
                ]);
            }

            $locked->update([
                'status' => 'completed',
                'client_confirmed_at' => $locked->client_confirmed_at ?: now(),
                'completed_at' => $locked->completed_at ?: now(),
                'payout_generated_at' => now(),
            ]);

            $releasedGross = (int) $payment->payouts()->where('status', '!=', 'cancelled')->sum('gross_amount');
            $payment->update([
                'status' => $releasedGross >= (int) $payment->amount ? 'released' : ($releasedGross > 0 ? 'partially_released' : 'held'),
                'released_at' => $releasedGross >= (int) $payment->amount ? now() : null,
            ]);

            $this->finance->syncOrderPaymentStatus($locked->order);

            if ($gross > 0) {
                $this->finance->notify(
                    $locked->caregiver,
                    'shift.payout_created',
                    'Выплата за смену сформирована',
                    'По подтверждённому акту ' . $act->number . ' к выплате сформировано '
                        . number_format($net, 0, ',', ' ') . ' ₽.',
                    ['assignment_id' => $locked->id, 'payout_id' => $payout->id],
                );
            }

            return $payout;
        });
    }
}
