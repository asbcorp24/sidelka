<?php

namespace App\Services;

use App\Models\User;
use App\Models\WalletTopUp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class WalletTopUpService
{
    public function __construct(
        private SberAcquiringService $sber,
        private OrderFinanceService $financeService
    ) {
    }

    public function start(User $user, int $amount): WalletTopUp
    {
        $publicId = (string) Str::uuid();

        $topUp = WalletTopUp::create([
            'public_id' => $publicId,
            'user_id' => $user->id,
            'provider' => 'sber',
            'order_number' => $publicId,
            'amount' => $amount,
            'amount_minor' => $amount * 100,
            'currency' => 'RUB',
            'status' => 'pending',
            'expires_at' => now()->addHour(),
        ]);

        try {
            $payload = $this->sber->register($topUp);

            if (
                (string) ($payload['errorCode'] ?? '') !== '0'
                || empty($payload['orderId'])
                || empty($payload['formUrl'])
            ) {
                throw new RuntimeException(
                    (string) ($payload['errorMessage'] ?? 'Сбер не создал платёж.')
                );
            }

            $topUp->update([
                'provider_order_id' => (string) $payload['orderId'],
                'payment_url' => (string) $payload['formUrl'],
                'status' => 'awaiting_payment',
                'provider_payload' => $payload,
                'error_code' => null,
                'error_message' => null,
            ]);

            return $topUp->fresh();
        } catch (Throwable $exception) {
            $topUp->update([
                'status' => 'failed',
                'error_code' => (string) $exception->getCode(),
                'error_message' => $exception->getMessage(),
                'failed_at' => now(),
            ]);

            throw $exception;
        }
    }

    public function sync(WalletTopUp $topUp): WalletTopUp
    {
        if ($topUp->status === 'paid') {
            return $topUp;
        }

        $payload = $this->sber->status($topUp);

        $topUp->update([
            'provider_status_payload' => $payload,
            'error_code' => isset($payload['errorCode']) ? (string) $payload['errorCode'] : null,
            'error_message' => $payload['errorMessage'] ?? null,
        ]);

        if ($this->sber->isDeposited($topUp, $payload)) {
            $this->credit($topUp);

            return $topUp->fresh();
        }

        $orderStatus = (int) ($payload['orderStatus'] ?? -1);

        if (in_array($orderStatus, [0, 1, 5], true)) {
            $topUp->update(['status' => 'awaiting_payment']);

            return $topUp->fresh();
        }

        if ($orderStatus === 6) {
            $topUp->update([
                'status' => 'failed',
                'failed_at' => now(),
            ]);

            return $topUp->fresh();
        }

        if (in_array($orderStatus, [3, 4], true)) {
            $topUp->update([
                'status' => 'cancelled',
                'failed_at' => now(),
            ]);
        }

        return $topUp->fresh();
    }

    private function credit(WalletTopUp $topUp): void
    {
        DB::transaction(function () use ($topUp) {
            $lockedTopUp = WalletTopUp::query()
                ->whereKey($topUp->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedTopUp->status === 'paid') {
                return;
            }

            $user = User::query()
                ->whereKey($lockedTopUp->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->financeService->topUpWallet(
                $user,
                (int) $lockedTopUp->amount,
                'Пополнение через Сбер, платёж ' . $lockedTopUp->order_number
            );

            $lockedTopUp->update([
                'status' => 'paid',
                'paid_at' => now(),
                'failed_at' => null,
                'error_code' => null,
                'error_message' => null,
            ]);
        });
    }
}
