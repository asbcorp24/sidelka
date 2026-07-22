<?php

namespace App\Services;

use App\Models\AgentCommission;
use App\Models\LegalContract;
use App\Models\MarketplaceNotification;
use App\Models\Order;
use App\Models\OrderCaregiverAssignment;
use App\Models\OrderExpense;
use App\Models\OrderScheduleSlot;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderFinanceService
{
    public function topUpWallet(User $user, int $amount, string $description = 'Пополнение баланса'): void
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Сумма пополнения должна быть больше нуля.',
            ]);
        }

        $user->increment('wallet_balance', $amount);
        $user->refresh();

        $user->walletTransactions()->create([
            'type' => 'top_up',
            'amount' => $amount,
            'balance_after' => $user->wallet_balance,
            'description' => $description,
        ]);

        $this->notify($user, 'wallet.top_up', 'Баланс пополнен', "На ваш счет зачислено {$amount} ₽.");
    }

    public function holdBaseOrderPayment(Order $order): Payment
    {
        $existing = $order->payments()->where('kind', 'base_order')->first();
        if ($existing) {
            return $existing;
        }

        return $this->holdFromWallet(
            $order->client,
            $order,
            $order->base_amount,
            'base_order',
            'Удержание средств по основному счету заказа',
            $order->caregiver_id,
        );
    }

    public function holdExpensePayment(Order $order, OrderExpense $expense): Payment
    {
        $existing = $order->payments()
            ->where('kind', 'expense')
            ->where('description', 'like', '%' . $expense->id . '%')
            ->first();

        if ($existing) {
            return $existing;
        }

        $payment = $this->holdFromWallet(
            $order->client,
            $order,
            $expense->line_total,
            'expense',
            "Дополнительный счет по расходу #{$expense->id}: {$expense->title}",
            $expense->caregiver_id ?: $order->caregiver_id,
        );

        $expense->update([
            'status' => 'billed',
            'billed_at' => now(),
        ]);

        return $payment;
    }

    public function releaseAssignmentPayout(OrderCaregiverAssignment $assignment, ?User $actor = null): Payout
    {
        return DB::transaction(function () use ($assignment, $actor) {
            $lockedAssignment = OrderCaregiverAssignment::query()
                ->whereKey($assignment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedAssignment->loadMissing(['order.scheduleSlots', 'order.payments', 'scheduleSlot', 'caregiver']);
            $order = $lockedAssignment->order;

            abort_unless(in_array($order->status, ['in_progress', 'completed'], true), 422);
            abort_unless(in_array($lockedAssignment->status, ['accepted', 'completion_requested', 'completed'], true), 422);

            $existing = Payout::query()
                ->where('order_caregiver_assignment_id', $lockedAssignment->id)
                ->first();

            if ($existing) {
                return $existing;
            }

            $this->assertSignedOrderContract($order, (int) $lockedAssignment->caregiver_id);

            $payment = Payment::query()
                ->where('order_id', $order->id)
                ->where('kind', 'base_order')
                ->whereIn('status', ['held', 'partially_released', 'released'])
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                throw ValidationException::withMessages([
                    'payout' => 'Основная оплата заказа не была удержана.',
                ]);
            }

            $grossAmount = $this->assignmentGrossAmount($lockedAssignment, $payment);
            $alreadyReleased = (int) Payout::query()
                ->where('payment_id', $payment->id)
                ->where('status', '!=', 'cancelled')
                ->sum('gross_amount');
            $remaining = max(0, (int) $payment->amount - $alreadyReleased);
            $grossAmount = min($grossAmount, $remaining);

            if ($grossAmount <= 0) {
                throw ValidationException::withMessages([
                    'payout' => 'По основной оплате заказа не осталось средств для этой смены.',
                ]);
            }

            $payout = $this->createPayout(
                order: $order,
                payment: $payment,
                caregiverId: (int) $lockedAssignment->caregiver_id,
                grossAmount: $grossAmount,
                applyCommission: true,
                assignmentId: $lockedAssignment->id,
            );

            $lockedAssignment->update([
                'status' => 'completed',
                'client_confirmed_at' => $lockedAssignment->client_confirmed_at ?: now(),
                'completed_at' => $lockedAssignment->completed_at ?: now(),
                'payout_generated_at' => now(),
            ]);

            $this->syncBasePaymentStatus($payment);
            $this->releaseCaregiverExpensePayments($order, (int) $lockedAssignment->caregiver_id);
            $this->syncOrderPaymentStatus($order);

            $this->notify(
                $lockedAssignment->caregiver,
                'shift.payout_created',
                'Выплата за смену сформирована',
                'Смена по заказу «' . $order->title . '» подтверждена. К выплате: '
                    . number_format($payout->amount, 0, ',', ' ') . ' ₽. Перевод ожидает обработки.',
                [
                    'order_id' => $order->id,
                    'assignment_id' => $lockedAssignment->id,
                    'payout_id' => $payout->id,
                    'actor_id' => $actor?->id,
                ],
            );

            return $payout;
        });
    }

    public function releaseHeldPayments(Order $order): void
    {
        $order->loadMissing([
            'payments',
            'caregiver',
            'caregiverAssignments.caregiver',
            'caregiverAssignments.scheduleSlot',
            'scheduleSlots',
        ]);

        $basePayment = $order->payments->firstWhere('kind', 'base_order');

        if ($basePayment && $order->caregiverAssignments->isNotEmpty()) {
            foreach ($order->caregiverAssignments->where('status', 'completed') as $assignment) {
                $this->releaseAssignmentPayout($assignment);
            }

            $this->syncBasePaymentStatus($basePayment->fresh());
        } elseif ($basePayment && in_array($basePayment->status, ['held', 'partially_released'], true)) {
            $caregiverId = $basePayment->caregiver_id ?: $order->caregiver_id;

            if (! $caregiverId) {
                throw ValidationException::withMessages([
                    'payout' => 'Невозможно сформировать выплату: у платежа не определена сиделка.',
                ]);
            }

            $this->createPayout(
                order: $order,
                payment: $basePayment,
                caregiverId: (int) $caregiverId,
                grossAmount: (int) $basePayment->amount,
                applyCommission: true,
            );

            $basePayment->update(['status' => 'released', 'released_at' => now()]);
        }

        foreach ($order->payments->where('kind', '!=', 'base_order')->whereIn('status', ['held', 'partially_released']) as $payment) {
            $caregiverId = $payment->caregiver_id ?: $order->caregiver_id;
            if (! $caregiverId) {
                continue;
            }

            $this->createPayout(
                order: $order,
                payment: $payment,
                caregiverId: (int) $caregiverId,
                grossAmount: (int) $payment->amount,
                applyCommission: false,
            );

            $payment->update(['status' => 'released', 'released_at' => now()]);
        }

        $this->syncOrderPaymentStatus($order);
    }

    public function syncOrderPaymentStatus(Order $order): void
    {
        $hasPendingPayouts = $order->payouts()->whereIn('status', ['pending', 'processing'])->exists();
        $hasPartiallyReleased = $order->payments()->where('status', 'partially_released')->exists();
        $hasHeld = $order->payments()->where('status', 'held')->exists();
        $hasPayments = $order->payments()->exists();
        $allPayoutsPaid = ! $order->payouts()->whereNotIn('status', ['paid', 'cancelled'])->exists();

        $status = match (true) {
            $hasPendingPayouts => 'payout_pending',
            $hasPartiallyReleased => 'partially_released',
            $hasHeld => 'held',
            $hasPayments && $allPayoutsPaid => 'released',
            default => $order->payment_status,
        };

        if ($order->payment_status !== $status) {
            $order->update(['payment_status' => $status]);
        }
    }

    public function cancelOrder(Order $order, User $actor, string $reason, ?string $details = null): void
    {
        $stage = match ($order->status) {
            'published', 'matched' => 'before_confirmation',
            'in_chat' => 'after_confirmation',
            'in_progress' => 'during_work',
            default => 'other',
        };

        $refundAmount = 0;
        $preservedPayoutAmount = (int) $order->payouts()
            ->whereIn('status', ['pending', 'processing', 'paid'])
            ->sum('amount');

        foreach ($order->payments()->whereIn('status', ['held', 'partially_released'])->get() as $payment) {
            $releasedGross = (int) $payment->payouts()
                ->where('status', '!=', 'cancelled')
                ->sum('gross_amount');
            $refundable = max(0, (int) $payment->amount - $releasedGross);

            if ($refundable > 0) {
                $refundAmount += $refundable;
                $this->refundPayment($payment, $reason, $refundable, $releasedGross > 0);
            } elseif ($releasedGross > 0) {
                $payment->update(['status' => 'released', 'released_at' => now()]);
            }
        }

        $order->cancellations()->create([
            'cancelled_by_id' => $actor->id,
            'stage' => $stage,
            'reason' => $reason,
            'details' => $details,
            'refund_amount' => $refundAmount,
            'payout_amount' => $preservedPayoutAmount,
        ]);

        $paymentStatus = match (true) {
            $refundAmount > 0 && $preservedPayoutAmount > 0 => 'partially_refunded',
            $refundAmount > 0 => 'refunded',
            $preservedPayoutAmount > 0 => 'payout_pending',
            default => 'cancelled',
        };

        $order->update([
            'status' => 'cancelled',
            'payment_status' => $paymentStatus,
            'cancelled_at' => now(),
        ]);

        $this->notify(
            $order->client,
            'order.cancelled',
            'Заказ отменен',
            "Заказ «{$order->title}» отменен. Возврат неиспользованного остатка: {$refundAmount} ₽."
        );
    }

    public function notify(?User $user, string $type, string $title, string $body, array $data = []): void
    {
        if (! $user) {
            return;
        }

        MarketplaceNotification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);
    }

    private function holdFromWallet(
        User $client,
        Order $order,
        int $amount,
        string $kind,
        string $description,
        ?int $caregiverId = null,
    ): Payment {
        if ($client->wallet_balance < $amount) {
            throw ValidationException::withMessages([
                'wallet' => 'Недостаточно средств на балансе. Нужно еще ' . number_format($amount - $client->wallet_balance, 0, ',', ' ') . ' ₽.',
            ]);
        }

        return DB::transaction(function () use ($client, $order, $amount, $kind, $description, $caregiverId) {
            $client->decrement('wallet_balance', $amount);
            $client->refresh();

            $payment = $order->payments()->create([
                'client_id' => $client->id,
                'caregiver_id' => $caregiverId,
                'kind' => $kind,
                'amount' => $amount,
                'currency' => 'RUB',
                'status' => 'held',
                'provider' => 'internal_wallet',
                'description' => $description,
                'paid_at' => now(),
                'held_at' => now(),
            ]);

            $client->walletTransactions()->create([
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'type' => 'hold',
                'amount' => -$amount,
                'balance_after' => $client->wallet_balance,
                'description' => $description,
            ]);

            return $payment;
        });
    }

    private function assignmentGrossAmount(OrderCaregiverAssignment $assignment, Payment $payment): int
    {
        $order = $assignment->order;
        $slots = $order->scheduleSlots
            ->sortBy(fn (OrderScheduleSlot $slot) => $slot->scheduled_date->format('Y-m-d') . ' ' . $slot->starts_at . ' ' . $slot->id)
            ->values();

        if (! $assignment->scheduleSlot || $slots->isEmpty()) {
            return max(0, (int) $payment->amount);
        }

        $minutesBySlot = $slots->mapWithKeys(function (OrderScheduleSlot $slot) {
            $start = Carbon::parse($slot->scheduled_date->format('Y-m-d') . ' ' . $slot->starts_at);
            $end = Carbon::parse($slot->scheduled_date->format('Y-m-d') . ' ' . $slot->ends_at);

            return [$slot->id => max(1, $start->diffInMinutes($end))];
        });

        $totalMinutes = max(1, (int) $minutesBySlot->sum());
        $distributed = 0;
        $amounts = [];

        foreach ($slots as $index => $slot) {
            $amount = $index === $slots->count() - 1
                ? (int) $payment->amount - $distributed
                : (int) floor((int) $payment->amount * ((int) $minutesBySlot[$slot->id] / $totalMinutes));

            $amounts[$slot->id] = max(0, $amount);
            $distributed += $amounts[$slot->id];
        }

        return (int) ($amounts[$assignment->order_schedule_slot_id] ?? 0);
    }

    private function releaseCaregiverExpensePayments(Order $order, int $caregiverId): void
    {
        $payments = Payment::query()
            ->where('order_id', $order->id)
            ->where('kind', '!=', 'base_order')
            ->where('caregiver_id', $caregiverId)
            ->whereIn('status', ['held', 'partially_released'])
            ->lockForUpdate()
            ->get();

        foreach ($payments as $payment) {
            $this->createPayout(
                order: $order,
                payment: $payment,
                caregiverId: $caregiverId,
                grossAmount: (int) $payment->amount,
                applyCommission: false,
            );

            $payment->update(['status' => 'released', 'released_at' => now()]);
        }
    }

    private function syncBasePaymentStatus(Payment $payment): void
    {
        $releasedGross = (int) $payment->payouts()
            ->where('status', '!=', 'cancelled')
            ->sum('gross_amount');

        $status = match (true) {
            $releasedGross <= 0 => 'held',
            $releasedGross >= (int) $payment->amount => 'released',
            default => 'partially_released',
        };

        $payment->update([
            'status' => $status,
            'released_at' => $status === 'released' ? now() : null,
        ]);
    }

    private function createPayout(
        Order $order,
        Payment $payment,
        int $caregiverId,
        int $grossAmount,
        bool $applyCommission,
        ?int $assignmentId = null,
    ): Payout {
        $commissionPercent = $applyCommission
            ? $this->commissionPercentFor($order, $caregiverId)
            : 0.0;
        $commissionAmount = $applyCommission
            ? (int) round($grossAmount * $commissionPercent / 100)
            : 0;
        $netAmount = max(0, $grossAmount - $commissionAmount);

        $identity = $assignmentId
            ? ['order_caregiver_assignment_id' => $assignmentId]
            : [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'caregiver_id' => $caregiverId,
            ];

        $payout = Payout::firstOrCreate(
            $identity,
            [
                'order_id' => $order->id,
                'order_caregiver_assignment_id' => $assignmentId,
                'payment_id' => $payment->id,
                'caregiver_id' => $caregiverId,
                'gross_amount' => $grossAmount,
                'commission_percent' => $commissionPercent,
                'commission_amount' => $commissionAmount,
                'amount' => $netAmount,
                'currency' => $payment->currency,
                'status' => 'pending',
                'destination' => 'Банковские реквизиты сиделки',
                'paid_at' => null,
            ]
        );

        if ($commissionAmount > 0) {
            AgentCommission::firstOrCreate(
                [
                    'payment_id' => $payment->id,
                    'caregiver_id' => $caregiverId,
                    'order_caregiver_assignment_id' => $assignmentId,
                ],
                [
                    'order_id' => $order->id,
                    'payout_id' => $payout->id,
                    'gross_amount' => $grossAmount,
                    'percent' => $commissionPercent,
                    'amount' => $commissionAmount,
                    'currency' => $payment->currency,
                    'status' => 'recognized',
                    'recognized_at' => now(),
                ]
            );
        }

        return $payout;
    }

    private function assertSignedOrderContract(Order $order, int $caregiverId): void
    {
        $signed = LegalContract::query()
            ->where('type', LegalContract::TYPE_ORDER_SERVICE)
            ->where('order_id', $order->id)
            ->where('meta->caregiver_id', $caregiverId)
            ->where('status', LegalContract::STATUS_SIGNED)
            ->exists();

        if (! $signed) {
            throw ValidationException::withMessages([
                'contract' => 'Нельзя сформировать выплату: договор с этой сиделкой ещё не подписан обеими сторонами.',
            ]);
        }
    }

    private function commissionPercentFor(Order $order, int $caregiverId): float
    {
        $signedContract = LegalContract::query()
            ->where('type', LegalContract::TYPE_ORDER_SERVICE)
            ->where('order_id', $order->id)
            ->where('meta->caregiver_id', $caregiverId)
            ->where('status', LegalContract::STATUS_SIGNED)
            ->latest('id')
            ->first();

        $contractMeta = $signedContract?->meta ?? [];
        $percent = $contractMeta['commission_percent']
            ?? config('legal.agent_commission_percent', 0);

        return max(0, min(100, (float) $percent));
    }

    private function refundPayment(Payment $payment, string $reason, int $amount, bool $partial): void
    {
        $client = $payment->client;

        DB::transaction(function () use ($payment, $client, $reason, $amount, $partial) {
            $client->increment('wallet_balance', $amount);
            $client->refresh();

            $payment->update([
                'status' => $partial ? 'partially_refunded' : 'refunded',
                'refunded_at' => now(),
            ]);

            Refund::create([
                'order_id' => $payment->order_id,
                'payment_id' => $payment->id,
                'client_id' => $client->id,
                'amount' => $amount,
                'currency' => $payment->currency,
                'status' => 'completed',
                'reason' => $reason,
                'processed_at' => now(),
            ]);

            $client->walletTransactions()->create([
                'order_id' => $payment->order_id,
                'payment_id' => $payment->id,
                'type' => 'refund',
                'amount' => $amount,
                'balance_after' => $client->wallet_balance,
                'description' => "Возврат неиспользованного остатка по заказу #{$payment->order_id}: {$reason}",
            ]);
        });
    }
}
