<?php

namespace Tests\Feature;

use App\Models\LegalContract;
use App\Models\Order;
use App\Models\OrderCaregiverAssignment;
use App\Models\Payout;
use App\Models\User;
use App\Services\OrderFinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AgentCommissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_payout_uses_signed_commission_and_requires_crm_confirmation(): void
    {
        config(['legal.agent_commission_percent' => 5]);

        $client = User::factory()->create([
            'role' => 'client',
            'email_verified_at' => now(),
            'city' => 'Казань',
        ]);
        $client->forceFill(['wallet_balance' => 2400])->save();

        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'email_verified_at' => now(),
            'city' => 'Казань',
        ]);

        $start = now()->subHours(5);
        $end = now()->subHour();

        $order = Order::create([
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
            'title' => 'Тестовая услуга ухода',
            'description' => 'Услуга продолжительностью четыре часа.',
            'city' => 'Казань',
            'address' => 'ул. Тестовая, 1',
            'schedule_type' => 'hourly',
            'status' => 'in_progress',
            'payment_status' => 'pending',
            'hourly_budget' => 600,
            'custom_services' => [],
            'starts_at' => $start,
            'ends_at' => $end,
        ]);

        $slot = $order->scheduleSlots()->create([
            'scheduled_date' => now()->subDay()->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '13:00:00',
        ]);

        $assignment = OrderCaregiverAssignment::create([
            'order_id' => $order->id,
            'order_schedule_slot_id' => $slot->id,
            'caregiver_id' => $caregiver->id,
            'status' => 'completed',
            'confirmed_at' => now()->subDays(2),
            'client_confirmed_at' => now(),
            'completed_at' => now(),
        ]);

        LegalContract::create([
            'public_id' => (string) Str::uuid(),
            'type' => LegalContract::TYPE_ORDER_SERVICE,
            'order_id' => $order->id,
            'number' => 'ORD-TEST-1',
            'version' => 1,
            'title' => 'Тестовый подписанный договор',
            'status' => LegalContract::STATUS_SIGNED,
            'body_html' => '<p>Договор</p>',
            'document_hash' => hash('sha256', '<p>Договор</p>'),
            'meta' => [
                'caregiver_id' => $caregiver->id,
                'commission_percent' => 12.5,
            ],
            'signed_at' => now(),
        ]);

        $finance = app(OrderFinanceService::class);
        $finance->holdBaseOrderPayment($order->fresh(['client', 'scheduleSlots']));
        $finance->releaseHeldPayments($order->fresh([
            'payments',
            'caregiver',
            'caregiverAssignments.caregiver',
            'caregiverAssignments.scheduleSlot',
            'scheduleSlots',
        ]));

        $this->assertDatabaseHas('payouts', [
            'order_id' => $order->id,
            'order_caregiver_assignment_id' => $assignment->id,
            'caregiver_id' => $caregiver->id,
            'gross_amount' => 2400,
            'commission_amount' => 300,
            'amount' => 2100,
            'status' => 'pending',
            'paid_at' => null,
        ]);

        $this->assertSame('payout_pending', $order->fresh()->payment_status);

        $this->assertDatabaseHas('agent_commissions', [
            'order_id' => $order->id,
            'order_caregiver_assignment_id' => $assignment->id,
            'caregiver_id' => $caregiver->id,
            'gross_amount' => 2400,
            'amount' => 300,
            'status' => 'recognized',
        ]);

        $payout = Payout::firstOrFail();
        $crm = User::factory()->create([
            'role' => 'crm',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($crm)
            ->patch(route('crm.payouts.paid', $payout), [
                'destination' => 'СБП +7 999 000-00-00',
                'external_reference' => 'BANK-TEST-123',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('payouts', [
            'id' => $payout->id,
            'status' => 'paid',
            'external_reference' => 'BANK-TEST-123',
        ]);
        $this->assertNotNull($payout->fresh()->paid_at);
        $this->assertSame('released', $order->fresh()->payment_status);
    }
}
