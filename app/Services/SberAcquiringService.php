<?php

namespace App\Services;

use App\Models\WalletTopUp;
use App\Support\PlatformSettings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SberAcquiringService
{
    public function __construct(private PlatformSettings $platformSettings)
    {
    }

    public function register(WalletTopUp $topUp): array
    {
        $settings = $this->bankSettings();

        $response = $this->client()->post($this->endpoint('register.do'), [
            'userName' => $settings['username'],
            'password' => $settings['password'],
            'orderNumber' => $topUp->order_number,
            'amount' => $topUp->amount_minor,
            'currency' => '643',
            'returnUrl' => route('payments.sber.return', $topUp),
            'failUrl' => route('payments.sber.fail', $topUp),
            'dynamicCallbackUrl' => route('payments.sber.callback'),
            'description' => $settings['description_prefix'] . ' #' . $topUp->order_number,
            'language' => 'ru',
        ]);

        $response->throw();

        return $response->json();
    }

    public function status(WalletTopUp $topUp): array
    {
        $settings = $this->bankSettings();

        $identity = $topUp->provider_order_id
            ? ['orderId' => $topUp->provider_order_id]
            : ['orderNumber' => $topUp->order_number];

        $response = $this->client()->post(
            $this->endpoint('getOrderStatusExtended.do'),
            array_merge([
                'userName' => $settings['username'],
                'password' => $settings['password'],
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
        $settings = $this->bankSettings();

        return Http::asJson()
            ->acceptJson()
            ->timeout((int) $settings['timeout']);
    }

    private function endpoint(string $method): string
    {
        $settings = $this->bankSettings();

        return rtrim((string) $settings['base_url'], '/') . '/' . ltrim($method, '/');
    }

    private function bankSettings(): array
    {
        $settings = $this->platformSettings->bankPayload();

        if (! $settings['enabled']) {
            throw new RuntimeException('Интернет-эквайринг Сбера отключен.');
        }

        if (! $settings['username'] || ! $settings['password']) {
            throw new RuntimeException('Не заданы реквизиты интернет-эквайринга Сбера.');
        }

        return $settings;
    }
}
