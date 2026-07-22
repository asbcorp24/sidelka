<?php

namespace Tests\Feature;

use App\Models\CarePlan;
use App\Models\Order;
use App\Models\OrderCaregiverAssignment;
use App\Models\SafetyIncident;
use App\Models\ShiftAct;
use App\Models\User;
use App\Models\UserDocument;
use App\Services\CaregiverDocumentService;
use App\Services\OrderFinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CareOperationsModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_shift_journal_act_and_individual_payout_work_end_to_end(): void
    {
        [$client, $caregiver, $order, $assignment] = $this->createEndedAssignment();

        CarePlan::create([
            'order_id' => $order->id,
            'created_by_id' => $client->id,
            'status' => 'active',
            'patient_name' => 'Анна Петрова',
            'medications' => 'По назначению заказчика',
            'risks' => 'Риск падения',
            'effective_from' => now()->subDay(),
        ]);

        $client->forceFill(['wallet_balance' => 2400])->save();
        app(OrderFinanceService::class)->holdBaseOrderPayment($order->fresh(['client', 'scheduleSlots']));
        $order->update(['payment_status' => 'held']);

        $this->actingAs($caregiver)
            ->post(route('caregiver.journals.save', [$order, $assignment]), [
                'arrived_at' => now()->subHours(5)->format('Y-m-d H:i:s'),
                'left_at' => now()->subHour()->format('Y-m-d H:i:s'),
                'summary' => 'Смена выполнена полностью.',
                'observations' => 'Состояние стабильное.',
                'vitals_text' => 'Давление 130/80',
                'meals_text' => 'Завтрак и обед',
                'medications_text' => 'Напоминания выполнены',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($caregiver)
            ->post(route('caregiver.journals.submit', [$order, $assignment]))
            ->assertSessionHasNoErrors();

        $this->actingAs($caregiver)
            ->post(route('caregiver.assignments.complete-request', [$order, $assignment]), [
                'completion_note' => 'Работы по плану выполнены.',
            ])
            ->assertSessionHasNoErrors();

        $act = ShiftAct::firstOrFail();
        $this->assertSame(ShiftAct::STATUS_AWAITING_CLIENT, $act->status);
        $this->assertSame(64, strlen($act->document_hash));

        $this->actingAs($client)
            ->post(route('client.assignments.confirm', [$order, $assignment]), [
                'client_comment' => 'Замечаний нет.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('shift_acts', [
            'id' => $act->id,
            'status' => ShiftAct::STATUS_SIGNED,
        ]);
        $this->assertDatabaseHas('shift_journals', [
            'order_caregiver_assignment_id' => $assignment->id,
            'status' => 'accepted',
        ]);
        $this->assertDatabaseHas('payouts', [
            'order_caregiver_assignment_id' => $assignment->id,
            'caregiver_id' => $caregiver->id,
            'status' => 'pending',
        ]);
        $this->assertSame('completed', $assignment->fresh()->status);
    }

    public function test_expiring_document_creates_task_and_expired_blocking_document_denies_new_shift(): void
    {
        $coordinator = User::factory()->create([
            'role' => 'crm',
            'staff_role' => 'coordinator',
            'staff_active' => true,
            'email_verified_at' => now(),
        ]);
        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'email_verified_at' => now(),
        ]);

        UserDocument::create([
            'user_id' => $caregiver->id,
            'document_type' => 'medical_certificate',
            'title' => 'Медицинская справка',
            'verification_status' => 'verified',
            'is_required' => true,
            'blocks_assignments' => true,
            'expires_at' => today()->addDays(10),
        ]);

        $created = app(CaregiverDocumentService::class)->createExpiryTasks();
        $this->assertSame(1, $created);
        $this->assertDatabaseHas('crm_tasks', [
            'person_user_id' => $caregiver->id,
            'assigned_to_id' => $coordinator->id,
            'category' => 'caregiver_document',
            'status' => 'open',
            'priority' => 'high',
        ]);

        UserDocument::where('user_id', $caregiver->id)->update(['expires_at' => today()->subDay()]);

        $this->expectException(ValidationException::class);
        app(CaregiverDocumentService::class)->assertEligible($caregiver->fresh());
    }

    public function test_critical_incident_creates_urgent_supervisor_task(): void
    {
        [$client, $caregiver, $order, $assignment] = $this->createEndedAssignment();
        $supervisor = User::factory()->create([
            'role' => 'crm',
            'staff_role' => 'supervisor',
            'staff_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($caregiver)
            ->post(route('safety-incidents.store', $order), [
                'order_caregiver_assignment_id' => $assignment->id,
                'incident_type' => 'fall',
                'severity' => 'critical',
                'occurred_at' => now()->format('Y-m-d H:i:s'),
                'description' => 'Подопечный упал при попытке самостоятельно встать.',
                'actions_taken' => 'Оказана первая помощь, вызвана скорая помощь.',
                'emergency_called' => 1,
                'emergency_service_reference' => '112-TEST',
            ])
            ->assertSessionHasNoErrors();

        $incident = SafetyIncident::firstOrFail();
        $this->assertSame($supervisor->id, $incident->assigned_to_id);
        $this->assertDatabaseHas('crm_tasks', [
            'source_type' => SafetyIncident::class,
            'source_id' => $incident->id,
            'assigned_to_id' => $supervisor->id,
            'priority' => 'urgent',
            'status' => 'open',
        ]);
    }

    private function createEndedAssignment(): array
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

        $order = Order::create([
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
            'title' => 'Уход на дому',
            'description' => 'Помощь с питанием, гигиеной и перемещением.',
            'city' => 'Казань',
            'address' => 'ул. Тестовая, 1',
            'schedule_type' => 'hourly',
            'status' => 'in_progress',
            'payment_status' => 'pending',
            'hourly_budget' => 600,
            'patient_name' => 'Анна Петрова',
            'patient_age' => 79,
            'custom_services' => [],
            'starts_at' => now()->subHours(5),
            'ends_at' => now()->subHour(),
        ]);

        $slot = $order->scheduleSlots()->create([
            'scheduled_date' => today()->subDay()->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '13:00:00',
            'label' => 'Дневная смена',
        ]);

        $assignment = OrderCaregiverAssignment::create([
            'order_id' => $order->id,
            'order_schedule_slot_id' => $slot->id,
            'caregiver_id' => $caregiver->id,
            'status' => 'accepted',
            'confirmed_at' => now()->subDays(2),
        ]);

        return [$client, $caregiver, $order, $assignment];
    }
}
