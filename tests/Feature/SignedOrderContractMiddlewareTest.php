<?php

namespace Tests\Feature;

use App\Models\LegalContract;
use App\Models\Order;
use App\Models\OrderCaregiverAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SignedOrderContractMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_cannot_start_without_fully_signed_contract(): void
    {
        [$client, $caregiver, $order] = $this->makeConfirmedOrder();

        $this->actingAs($client)
            ->from(route('client.orders.show', $order))
            ->post(route('client.orders.start', $order))
            ->assertRedirect(route('client.orders.show', $order))
            ->assertSessionHasErrors('contract');

        $this->assertSame('in_chat', $order->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_order_can_start_after_contract_is_signed(): void
    {
        [$client, $caregiver, $order] = $this->makeConfirmedOrder();
        $client->forceFill(['wallet_balance' => 2400])->save();

        LegalContract::create([
            'public_id' => (string) Str::uuid(),
            'type' => LegalContract::TYPE_ORDER_SERVICE,
            'order_id' => $order->id,
            'number' => 'ORD-SIGNED-1',
            'version' => 1,
            'title' => 'Подписанный договор заказа',
            'status' => LegalContract::STATUS_SIGNED,
            'body_html' => '<p>Подписано</p>',
            'document_hash' => hash('sha256', '<p>Подписано</p>'),
            'meta' => [
                'caregiver_id' => $caregiver->id,
                'commission_percent' => 10,
            ],
            'signed_at' => now(),
        ]);

        $this->actingAs($client)
            ->post(route('client.orders.start', $order))
            ->assertRedirect(route('client.orders.show', $order));

        $this->assertSame('in_progress', $order->fresh()->status);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'amount' => 2400,
            'status' => 'held',
        ]);
    }

    private function makeConfirmedOrder(): array
    {
        $client = User::factory()->create([
            'role' => 'client',
            'email_verified_at' => now(),
            'city' => 'Казань',
        ]);

        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'email_verified_at' => now(),
            'city' => 'Казань',
        ]);

        $start = now()->addDay()->setTime(9, 0);
        $end = now()->addDay()->setTime(13, 0);

        $order = Order::create([
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
            'title' => 'Согласованный заказ',
            'description' => 'Уход в течение четырех часов.',
            'city' => 'Казань',
            'address' => 'ул. Тестовая, 5',
            'schedule_type' => 'hourly',
            'status' => 'in_chat',
            'payment_status' => 'pending',
            'hourly_budget' => 600,
            'custom_services' => [],
            'starts_at' => $start,
            'ends_at' => $end,
        ]);

        $slot = $order->scheduleSlots()->create([
            'scheduled_date' => $start->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '13:00:00',
        ]);

        OrderCaregiverAssignment::create([
            'order_id' => $order->id,
            'order_schedule_slot_id' => $slot->id,
            'caregiver_id' => $caregiver->id,
            'status' => 'accepted',
            'confirmed_at' => now(),
        ]);

        return [$client, $caregiver, $order];
    }
}
