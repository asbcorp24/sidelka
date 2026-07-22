<?php

namespace Tests\Feature;

use App\Models\CaregiverProfile;
use App\Models\CrmRequest;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_crm_employee_can_create_phone_request(): void
    {
        $employee = User::factory()->create([
            'role' => 'crm',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($employee)->post(route('crm.requests.store'), [
            'caller_name' => 'Иван Петров',
            'caller_phone' => '+79990000001',
            'patient_name' => 'Анна Петрова',
            'patient_age' => 79,
            'city' => 'Казань',
            'address' => 'ул. Примерная, 1',
            'service_text' => 'Нужна помощь после операции и контроль лекарств.',
            'schedule_text' => 'Будни с 09:00 до 18:00',
            'budget_per_hour' => 500,
            'priority' => 'high',
        ]);

        $crmRequest = CrmRequest::firstOrFail();

        $response->assertRedirect(route('crm.requests.show', $crmRequest));
        $this->assertDatabaseHas('crm_requests', [
            'caller_phone' => '+79990000001',
            'responsible_user_id' => $employee->id,
            'status' => 'new',
        ]);
        $this->assertDatabaseHas('crm_interactions', [
            'crm_request_id' => $crmRequest->id,
            'type' => 'call_in',
        ]);
    }

    public function test_crm_employee_can_record_caregiver_availability(): void
    {
        $employee = User::factory()->create([
            'role' => 'crm',
            'email_verified_at' => now(),
        ]);
        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'email_verified_at' => now(),
        ]);
        CaregiverProfile::create([
            'user_id' => $caregiver->id,
            'experience_years' => 3,
            'hourly_rate_from' => 450,
            'employment_format' => 'hourly',
        ]);

        $response = $this->actingAs($employee)->post(
            route('crm.caregivers.availability.store', $caregiver),
            [
                'weekday' => 1,
                'starts_at' => '09:00',
                'ends_at' => '18:00',
                'is_recurring' => 1,
                'notes' => 'Сообщено по телефону',
            ]
        );

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('availability_slots', [
            'caregiver_profile_id' => $caregiver->caregiverProfile->id,
            'weekday' => 1,
            'is_recurring' => 1,
        ]);
    }

    public function test_phone_request_can_be_converted_to_platform_order_once(): void
    {
        $employee = User::factory()->create([
            'role' => 'crm',
            'email_verified_at' => now(),
        ]);
        $client = User::factory()->create([
            'role' => 'client',
            'city' => 'Казань',
            'email_verified_at' => now(),
        ]);
        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'city' => 'Казань',
            'email_verified_at' => now(),
        ]);
        CaregiverProfile::create([
            'user_id' => $caregiver->id,
            'experience_years' => 5,
            'hourly_rate_from' => 500,
            'employment_format' => 'hourly',
        ]);

        $crmRequest = CrmRequest::create([
            'public_id' => '6cfd7427-e66f-4c55-98c7-b27acb1f81cf',
            'source' => 'phone',
            'status' => 'searching',
            'priority' => 'normal',
            'responsible_user_id' => $employee->id,
            'created_by_id' => $employee->id,
            'caller_name' => 'Иван Петров',
            'caller_phone' => '+79990000001',
            'patient_name' => 'Анна Петрова',
            'patient_age' => 79,
            'city' => 'Казань',
            'address' => 'ул. Примерная, 1',
            'service_text' => 'Уход после операции',
            'schedule_text' => 'Одна дневная смена',
        ]);

        $response = $this->actingAs($employee)->post(route('crm.requests.convert', $crmRequest), [
            'client_user_id' => $client->id,
            'caregiver_user_id' => $caregiver->id,
            'starts_at' => now()->addDay()->setTime(9, 0)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDay()->setTime(18, 0)->format('Y-m-d H:i:s'),
            'hourly_budget' => 600,
        ]);

        $response->assertSessionHasNoErrors();
        $crmRequest->refresh();

        $this->assertNotNull($crmRequest->order_id);
        $this->assertSame('booked', $crmRequest->status);
        $this->assertDatabaseHas('orders', [
            'id' => $crmRequest->order_id,
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
            'status' => 'matched',
        ]);
        $this->assertDatabaseCount('orders', 1);
        $this->assertInstanceOf(Order::class, $crmRequest->order);
    }
}
