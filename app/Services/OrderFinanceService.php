<?php

namespace App\Services;

use App\Models\MarketplaceNotification;
use App\Models\Order;
use App\Models\OrderExpense;
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
            'Удержание средств по основному счету заказа'
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
            "Дополнительный счет по расходу #{$expense->id}: {$expense->title}"
        );

        $expense->update([
            'status' => 'billed',
            'billed_at' => now(),
        ]);

        return $payment;
    }

    public function releaseHeldPayments(Order $order): void
    {
        $order->loadMissing(['payments', 'caregiver', 'caregiverAssignments.caregiver', 'caregiverAssignments.scheduleSlot']);

        foreach ($order->payments->where('status', 'held') as $payment) {
            $payment->update([
                'status' => 'released',
                'released_at' => now(),
            ]);

            if ($payment->kind === 'base_order' && $order->allows_multiple_caregivers) {
                $this->releaseMultiCaregiverPayouts($order, $payment);
            } else {
                Payout::firstOrCreate(
                    [
                        'order_id' => $order->id,
                        'payment_id' => $payment->id,
                        'caregiver_id' => $order->caregiver_id,
                    ],
                    [
                        'amount' => $payment->amount,
                        'currency' => $payment->currency,
                        'status' => 'paid',
                        'destination' => 'Банковские реквизиты сиделки',
                        'paid_at' => now(),
                    ]
                );
            }
        }

        if ($order->allows_multiple_caregivers) {
            foreach ($order->caregiverAssignments->pluck('caregiver')->filter()->unique('id') as $caregiver) {
                $this->notify(
                    $caregiver,
                    'payout.released',
                    'Выплата переведена',
                    "По заказу «{$order->title}» выплата переведена на ваш счет."
                );
            }

            return;
        }

        $this->notify(
            $order->caregiver,
            'payout.released',
            'Выплата переведена',
            "По заказу «{$order->title}» выплата переведена на ваш счет."
        );
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
        $payoutAmount = 0;

        foreach ($order->payments()->where('status', 'held')->get() as $payment) {
            $refundAmount += $payment->amount;
            $this->refundPayment($payment, $reason);
        }

        foreach ($order->payouts()->whereIn('status', ['pending', 'processing'])->get() as $payout) {
            $payoutAmount += $payout->amount;
            $payout->update(['status' => 'cancelled']);
        }

        $order->cancellations()->create([
            'cancelled_by_id' => $actor->id,
            'stage' => $stage,
            'reason' => $reason,
            'details' => $details,
            'refund_amount' => $refundAmount,
            'payout_amount' => $payoutAmount,
        ]);

        $order->update([
            'status' => 'cancelled',
            'payment_status' => $refundAmount > 0 ? 'refunded' : 'cancelled',
            'cancelled_at' => now(),
        ]);

        $this->notify(
            $order->client,
            'order.cancelled',
            'Заказ отменен',
            "Заказ «{$order->title}» отменен. Возврат: {$refundAmount} ₽."
        );

        if ($order->caregiver) {
            $this->notify(
                $order->caregiver,
                'order.cancelled',
                'Заказ отменен',
                "Заказ «{$order->title}» отменен."
            );
        }
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

    private function holdFromWallet(User $client, Order $order, int $amount, string $kind, string $description): Payment
    {
        if ($client->wallet_balance < $amount) {
            throw ValidationException::withMessages([
                'wallet' => 'Недостаточно средств на балансе. Нужно еще ' . number_format($amount - $client->wallet_balance, 0, ',', ' ') . ' ₽.',
            ]);
        }

        return DB::transaction(function () use ($client, $order, $amount, $kind, $description) {
            $client->decrement('wallet_balance', $amount);
            $client->refresh();

            $payment = $order->payments()->create([
                'client_id' => $client->id,
                'caregiver_id' => $order->caregiver_id,
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

    private function releaseMultiCaregiverPayouts(Order $order, Payment $payment): void
    {
        $acceptedAssignments = $order->caregiverAssignments
            ->whereIn('status', ['accepted', 'completed'])
            ->filter(fn ($assignment) => $assignment->caregiver && $assignment->scheduleSlot)
            ->values();

        if ($acceptedAssignments->isEmpty()) {
            return;
        }

        $minutesByCaregiver = $acceptedAssignments
            ->groupBy('caregiver_id')
            ->map(function ($assignments) {
                return $assignments->sum(function ($assignment) {
                    $slot = $assignment->scheduleSlot;
                    $start = Carbon::parse($slot->scheduled_date->format('Y-m-d') . ' ' . $slot->starts_at);
                    $end = Carbon::parse($slot->scheduled_date->format('Y-m-d') . ' ' . $slot->ends_at);

                    return max(1, $start->diffInMinutes($end));
                });
            });

        $totalMinutes = max(1, (int) $minutesByCaregiver->sum());
        $distributed = 0;
        $caregiverIds = $minutesByCaregiver->keys()->values();

        foreach ($caregiverIds as $index => $caregiverId) {
            $minutes = (int) $minutesByCaregiver[$caregiverId];
            $amount = $index === $caregiverIds->count() - 1
                ? $payment->amount - $distributed
                : (int) floor($payment->amount * ($minutes / $totalMinutes));

            $distributed += $amount;

            Payout::firstOrCreate(
                [
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                    'caregiver_id' => $caregiverId,
                ],
                [
                    'amount' => max(0, $amount),
                    'currency' => $payment->currency,
                    'status' => 'paid',
                    'destination' => 'Банковские реквизиты сиделки',
                    'paid_at' => now(),
                ]
            );
        }
    }

    private function refundPayment(Payment $payment, string $reason): void
    {
        $client = $payment->client;

        DB::transaction(function () use ($payment, $client, $reason) {
            $client->increment('wallet_balance', $payment->amount);
            $client->refresh();

            $payment->update([
                'status' => 'refunded',
                'refunded_at' => now(),
            ]);

            Refund::create([
                'order_id' => $payment->order_id,
                'payment_id' => $payment->id,
                'client_id' => $client->id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'status' => 'completed',
                'reason' => $reason,
                'processed_at' => now(),
            ]);

            $client->walletTransactions()->create([
                'order_id' => $payment->order_id,
                'payment_id' => $payment->id,
                'type' => 'refund',
                'amount' => $payment->amount,
                'balance_after' => $client->wallet_balance,
                'description' => "Возврат по заказу #{$payment->order_id}: {$reason}",
            ]);
        });
    }
}
