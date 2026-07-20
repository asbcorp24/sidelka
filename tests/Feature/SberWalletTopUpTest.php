<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WalletTopUp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SberWalletTopUpTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_is_redirected_to_sber_and_balance_is_credited_once_after_verified_status(): void
    {
        config([
            'sber.enabled' => true,
            'sber.base_url' => 'https://ecomtest.sberbank.ru/ecomm/gw/partner/api/v1',
            'sber.username' => 'test-user',
            'sber.password' => 'test-password',
        ]);

        Http::fake([
            '*register.do' => Http::response([
                'errorCode' => '0',
                'orderId' => 'a67b0ced-c9a4-4cfb-bce3-b9595afaafc1',
                'formUrl' => 'https://ecomtest.sberbank.ru/pp/pay_ru?orderId=test',
            ]),
            '*getOrderStatusExtended.do' => function ($request) {
                $topUp = WalletTopUp::query()->firstOrFail();

                return Http::response([
                    'errorCode' => '0',
                    'orderNumber' => $topUp->order_number,
                    'orderStatus' => 2,
                    'amount' => 100000,
                    'currency' => '643',
                    'paymentAmountInfo' => [
                        'depositedAmount' => 100000,
                        'paymentState' => 'DEPOSITED',
                    ],
                ]);
            },
        ]);

        $user = User::factory()->create([
            'role' => 'client',
            'wallet_balance' => 0,
        ]);

        $response = $this->actingAs($user)->post(route('client.wallet.topup'), [
            'amount' => 1000,
        ]);

        $response->assertRedirect('https://ecomtest.sberbank.ru/pp/pay_ru?orderId=test');

        $topUp = WalletTopUp::query()->firstOrFail();
        $this->assertSame('awaiting_payment', $topUp->status);

        $callback = [
            'mdOrder' => $topUp->provider_order_id,
            'orderNumber' => $topUp->order_number,
            'operation' => 'deposited',
            'status' => 1,
        ];

        $this->postJson(route('payments.sber.callback'), $callback)->assertOk();
        $this->postJson(route('payments.sber.callback'), $callback)->assertOk();

        $this->assertSame(1000, (int) $user->fresh()->wallet_balance);
        $this->assertSame('paid', $topUp->fresh()->status);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }
}
