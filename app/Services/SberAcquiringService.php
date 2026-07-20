<?php

namespace App\Services;

use App\Models\WalletTopUp;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SberAcquiringService
{
    public function register(WalletTopUp $topUp): array
    {
        $this->assertConfigured();

        $response = $this->client()->post($this->endpoint('register.do'), [
            'userName' => config('sber.username'),
            'password' => config('sber.password'),
            'orderNumber' => $topUp->order_number,
            'amount' => $topUp->amount_minor,
            'currency' => '643',
            'returnUrl' => route('payments.sber.return', $topUp),
            'failUrl' => route('payments.sber.fail', $topUp),
            'dynamicCallbackUrl' => route('payments.sber.callback'),
            'description' => config('sber.description_prefix') . ' #' . $topUp->order_number,
            'language' => 'ru',
        ]);

        $response->throw();

        return $response->json();
    }

    public function status(WalletTopUp $topUp): array
    {
        $this->assertConfigured();

        $identity = $topUp->provider_order_id
            ? ['orderId' => $topUp->provider_order_id]
            : ['orderNumber' => $topUp->order_number];

        $response = $this->client()->post(
            $this->endpoint('getOrderStatusExtended.do'),
            array_merge([
                'userName' => config('sber.username'),
                'password' => config('sber.password'),
                'language' => 'ru',
            ], $identity)
        );

        $response->throw();

        return $response->json();
    }

    public function isDeposited(WalletTopUp $topUp, array $payload): bool
    {
        $paymentInfo = $payload['paymentAmountInfo'] ?? [];

        return (string) ($payload['errorCode'] ?? '') === '0'
            && (int) ($payload['orderStatus'] ?? -1) === 2
            && (string) ($paymentInfo['paymentState'] ?? '') === 'DEPOSITED'
            && (int) ($paymentInfo['depositedAmount'] ?? -1) === $topUp->amount_minor
            && (string) ($payload['orderNumber'] ?? '') === $topUp->order_number;
    }

    private function client(): PendingRequest
    {
        return Http::asJson()
            ->acceptJson()
            ->timeout((int) config('sber.timeout', 20));
    }

    private function endpoint(string $method): string
    {
        return rtrim((string) config('sber.base_url'), '/') . '/' . ltrim($method, '/');
    }

    private function assertConfigured(): void
    {
        if (! config('sber.enabled')) {
            throw new RuntimeException('Интернет-эквайринг Сбера отключён.');
        }

        if (! config('sber.username') || ! config('sber.password')) {
            throw new RuntimeException('Не заданы тестовые реквизиты интернет-эквайринга Сбера.');
        }
    }
}
