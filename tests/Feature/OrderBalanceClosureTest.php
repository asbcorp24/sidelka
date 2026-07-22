<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\User;
use App\Services\OrderBalanceClosureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderBalanceClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_unused_held_balance_is_returned_after_partial_shift_resolution(): void
    {
        $client = User::factory()->create(['role' => 'client', 'email_verified_at' => now()]);
        $caregiver = User::factory()->create(['role' => 'caregiver', 'email_verified_at' => now()]);
        $client->forceFill(['wallet_balance' => 0])->save();

        $order = Order::create([
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
            'title' => 'Частично подтвержденная смена',
            'description' => 'Тест возврата остатка.',
            'city' => 'Казань',
            'address' => 'ул. Тестовая, 3',
            'schedule_type' => 'hourly',
            'status' => 'in_progress',
            'payment_status' => 'partially_released',
            'hourly_budget' => 600,
            'custom_services' => [],
            'starts_at' => now()->subHours(4),
            'ends_at' => now(),
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
            'kind' => 'base_order',
            'amount' => 2400,
            'currency' => 'RUB',
            'status' => 'partially_released',
            'provider' => 'internal_wallet',
            'description' => 'Тестовое удержание',
            'paid_at' => now(),
            'held_at' => now(),
        ]);

        Payout::create([
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'caregiver_id' => $caregiver->id,
            'gross_amount' => 1200,
            'commission_percent' => 10,
            'commission_amount' => 120,
            'amount' => 1080,
            'currency' => 'RUB',
            'status' => 'pending',
        ]);

        $refunded = app(OrderBalanceClosureService::class)->refundUnusedBasePayment($order);

        $this->assertSame(1200, $refunded);
        $this->assertSame(1200, (int) $client->fresh()->wallet_balance);
        $this->assertDatabaseHas('refunds', [
            'payment_id' => $payment->id,
            'amount' => 1200,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'partially_refunded',
        ]);
    }
}
