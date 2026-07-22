<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderCaregiverAssignment;
use App\Models\ShiftAct;
use App\Models\ShiftDispute;
use App\Models\User;
use App\Services\OrderFinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftDisputeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_can_resolve_one_shift_partially_without_signing_for_client(): void
    {
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
        $supervisor = User::factory()->create([
            'role' => 'crm',
            'staff_role' => 'supervisor',
            'staff_active' => true,
            'email_verified_at' => now(),
        ]);

        $order = Order::create([
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
            'title' => 'Уход на дому',
            'description' => 'Четырёхчасовая смена.',
            'city' => 'Казань',
            'address' => 'ул. Тестовая, 2',
            'schedule_type' => 'hourly',
            'status' => 'in_progress',
            'payment_status' => 'pending',
            'hourly_budget' => 600,
            'custom_services' => [],
            'starts_at' => now()->subHours(5),
            'ends_at' => now()->subHour(),
        ]);

        $slot = $order->scheduleSlots()->create([
            'scheduled_date' => today()->subDay()->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '13:00:00',
        ]);
        $assignment = OrderCaregiverAssignment::create([
            'order_id' => $order->id,
            'order_schedule_slot_id' => $slot->id,
            'caregiver_id' => $caregiver->id,
            'status' => 'accepted',
            'confirmed_at' => now()->subDays(2),
        ]);

        app(OrderFinanceService::class)->holdBaseOrderPayment($order->fresh(['client', 'scheduleSlots']));
        $order->update(['payment_status' => 'held']);

        $this->actingAs($caregiver)
            ->post(route('caregiver.assignments.complete-request', [$order, $assignment]), [
                'completion_note' => 'Смена завершена.',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($client)
            ->post(route('shift-disputes.store', [$order, $assignment]), [
                'reason' => 'time',
                'description' => 'Фактически отработано меньше согласованного времени.',
                'requested_action' => 'partial_payment',
            ])
            ->assertSessionHasNoErrors();

        $dispute = ShiftDispute::firstOrFail();

        $this->actingAs($supervisor)
            ->patch(route('crm.shift-disputes.resolve', $dispute), [
                'decision' => 'approve_partial',
                'approved_gross_amount' => 1200,
                'resolution' => 'Подтверждены два часа работы из четырёх.',
            ])
            ->assertSessionHasNoErrors();

        $act = ShiftAct::firstOrFail()->fresh();
        $this->assertSame(ShiftAct::STATUS_RESOLVED, $act->status);
        $this->assertNull($act->client_confirmed_at);
        $this->assertNull($act->signed_at);
        $this->assertSame($supervisor->id, (int) data_get($act->meta, 'resolved_by_id'));

        $this->assertDatabaseHas('shift_disputes', [
            'id' => $dispute->id,
            'status' => 'resolved',
            'decision' => 'approve_partial',
            'approved_gross_amount' => 1200,
        ]);
        $this->assertDatabaseHas('payouts', [
            'order_caregiver_assignment_id' => $assignment->id,
            'gross_amount' => 1200,
            'status' => 'pending',
        ]);
    }
}
