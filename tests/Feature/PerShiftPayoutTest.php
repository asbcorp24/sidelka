<?php

namespace Tests\Feature;

use App\Models\LegalContract;
use App\Models\Order;
use App\Models\OrderCaregiverAssignment;
use App\Models\User;
use App\Services\OrderFinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PerShiftPayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_caregiver_gets_payout_without_closing_multi_caregiver_order(): void
    {
        config(['legal.agent_commission_percent' => 10]);

        $client = User::factory()->create([
            'role' => 'client',
            'email_verified_at' => now(),
            'city' => 'Казань',
        ]);
        $client->forceFill(['wallet_balance' => 2400])->save();

        $firstCaregiver = User::factory()->create([
            'role' => 'caregiver',
            'email_verified_at' => now(),
            'city' => 'Казань',
        ]);
        $secondCaregiver = User::factory()->create([
            'role' => 'caregiver',
            'email_verified_at' => now(),
            'city' => 'Казань',
        ]);

        $order = Order::create([
            'client_id' => $client->id,
            'title' => 'Две последовательные смены',
            'description' => 'Первую смену выполняет одна сиделка, вторую — другая.',
            'city' => 'Казань',
            'address' => 'ул. Тестовая, 10',
            'schedule_type' => 'calendar',
            'status' => 'in_progress',
            'payment_status' => 'pending',
            'hourly_budget' => 600,
            'allows_multiple_caregivers' => true,
            'custom_services' => [],
            'starts_at' => now()->subHours(5),
            'ends_at' => now()->subHour(),
        ]);

        $firstSlot = $order->scheduleSlots()->create([
            'scheduled_date' => now()->subDay()->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '11:00:00',
            'label' => 'Первая смена',
        ]);
        $secondSlot = $order->scheduleSlots()->create([
            'scheduled_date' => now()->subDay()->toDateString(),
            'starts_at' => '11:00:00',
            'ends_at' => '13:00:00',
            'label' => 'Вторая смена',
        ]);

        $firstAssignment = OrderCaregiverAssignment::create([
            'order_id' => $order->id,
            'order_schedule_slot_id' => $firstSlot->id,
            'caregiver_id' => $firstCaregiver->id,
            'status' => 'accepted',
            'confirmed_at' => now()->subDays(2),
        ]);
        $secondAssignment = OrderCaregiverAssignment::create([
            'order_id' => $order->id,
            'order_schedule_slot_id' => $secondSlot->id,
            'caregiver_id' => $secondCaregiver->id,
            'status' => 'accepted',
            'confirmed_at' => now()->subDays(2),
        ]);

        $this->signedContract($order, $firstCaregiver, 'ORD-FIRST');
        $this->signedContract($order, $secondCaregiver, 'ORD-SECOND');

        $finance = app(OrderFinanceService::class);
        $finance->holdBaseOrderPayment($order->fresh(['client', 'scheduleSlots']));
        $order->update(['payment_status' => 'held']);

        $this->actingAs($firstCaregiver)
            ->post(route('caregiver.assignments.complete-request', [$order, $firstAssignment]), [
                'completion_note' => 'Смена выполнена полностью.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('order_caregiver_assignments', [
            'id' => $firstAssignment->id,
            'status' => 'completion_requested',
        ]);
        $this->assertDatabaseCount('payouts', 0);

        $this->actingAs($client)
            ->post(route('client.assignments.confirm', [$order, $firstAssignment]))
            ->assertRedirect();

        $this->assertDatabaseHas('payouts', [
            'order_id' => $order->id,
            'order_caregiver_assignment_id' => $firstAssignment->id,
            'caregiver_id' => $firstCaregiver->id,
            'gross_amount' => 1200,
            'commission_amount' => 120,
            'amount' => 1080,
            'status' => 'pending',
        ]);
        $this->assertDatabaseMissing('payouts', [
            'order_caregiver_assignment_id' => $secondAssignment->id,
        ]);

        $this->assertSame('completed', $firstAssignment->fresh()->status);
        $this->assertSame('accepted', $secondAssignment->fresh()->status);
        $this->assertSame('in_progress', $order->fresh()->status);
        $this->assertSame('payout_pending', $order->fresh()->payment_status);
        $this->assertSame('partially_released', $order->payments()->where('kind', 'base_order')->firstOrFail()->status);
    }

    private function signedContract(Order $order, User $caregiver, string $number): void
    {
        LegalContract::create([
            'public_id' => (string) Str::uuid(),
            'type' => LegalContract::TYPE_ORDER_SERVICE,
            'order_id' => $order->id,
            'number' => $number,
            'version' => 1,
            'title' => 'Подписанный договор смен',
            'status' => LegalContract::STATUS_SIGNED,
            'body_html' => '<p>Договор</p>',
            'document_hash' => hash('sha256', '<p>Договор</p>'),
            'meta' => [
                'caregiver_id' => $caregiver->id,
                'commission_percent' => 10,
            ],
            'signed_at' => now(),
        ]);
    }
}
