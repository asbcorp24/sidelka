<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;

class OrderBalanceClosureService
{
    public function refundUnusedBasePayment(Order $order, string $reason = 'Окончательный расчет по завершенному заказу'): int
    {
        $refunded = 0;

        foreach ($order->payments()
            ->where('kind', 'base_order')
            ->whereIn('status', ['held', 'partially_released', 'partially_refunded'])
            ->get() as $payment) {
            $releasedGross = (int) $payment->payouts()
                ->where('status', '!=', 'cancelled')
                ->sum('gross_amount');
            $alreadyRefunded = (int) $payment->refunds()->where('status', 'completed')->sum('amount');
            $amount = max(0, (int) $payment->amount - $releasedGross - $alreadyRefunded);

            if ($amount <= 0) {
                if ($releasedGross >= (int) $payment->amount) {
                    $payment->update(['status' => 'released', 'released_at' => $payment->released_at ?: now()]);
                }
                continue;
            }

            DB::transaction(function () use ($payment, $amount, $reason) {
                $lockedPayment = $payment->newQuery()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
                $client = $lockedPayment->client()->lockForUpdate()->firstOrFail();

                $releasedGross = (int) $lockedPayment->payouts()
                    ->where('status', '!=', 'cancelled')
                    ->sum('gross_amount');
                $alreadyRefunded = (int) $lockedPayment->refunds()->where('status', 'completed')->sum('amount');
                $actual = max(0, (int) $lockedPayment->amount - $releasedGross - $alreadyRefunded);

                if ($actual <= 0) {
                    return;
                }

                $client->increment('wallet_balance', $actual);
                $client->refresh();

                $lockedPayment->update([
                    'status' => $releasedGross > 0 ? 'partially_refunded' : 'refunded',
                    'refunded_at' => now(),
                ]);

                Refund::create([
                    'order_id' => $lockedPayment->order_id,
                    'payment_id' => $lockedPayment->id,
                    'client_id' => $client->id,
                    'amount' => $actual,
                    'currency' => $lockedPayment->currency,
                    'status' => 'completed',
                    'reason' => $reason,
                    'processed_at' => now(),
                ]);

                $client->walletTransactions()->create([
                    'order_id' => $lockedPayment->order_id,
                    'payment_id' => $lockedPayment->id,
                    'type' => 'refund',
                    'amount' => $actual,
                    'balance_after' => $client->wallet_balance,
                    'description' => 'Возврат остатка после актов и решений по сменам заказа #' . $lockedPayment->order_id,
                ]);
            });

            $refunded += $amount;
        }

        return $refunded;
    }
}
