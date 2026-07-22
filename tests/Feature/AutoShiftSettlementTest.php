<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderCaregiverAssignment;
use App\Models\ShiftAct;
use App\Models\User;
use App\Services\OrderFinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AutoShiftSettlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_act_without_dispute_is_settled_automatically(): void
    {
        config([
            'legal.agent_commission_percent' => 10,
            'legal.shift_auto_confirmation_hours' => 24,
        ]);

        $client = User::factory()->create([
            'role' => 'client',
            'email_verified_at' => now(),
        ]);
        $client->forceFill(['wallet_balance' => 1200])->save();

        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'email_verified_at' => now(),
        ]);

        $order = Order::create([
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
            'title' => 'Автоподтверждаемая смена',
            'description' => 'Клиент не ответил в установленный срок.',
            'city' => 'Казань',
            'schedule_type' => 'hourly',
            'status' => 'in_progress',
            'payment_status' => 'pending',
            'hourly_budget' => 600,
            'custom_services' => [],
            'starts_at' => now()->subHours(3),
            'ends_at' => now()->subHour(),
        ]);

        $slot = $order->scheduleSlots()->create([
            'scheduled_date' => now()->subDay()->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '11:00:00',
        ]);

        $assignment = OrderCaregiverAssignment::create([
            'order_id' => $order->id,
            'order_schedule_slot_id' => $slot->id,
            'caregiver_id' => $caregiver->id,
            'status' => 'accepted',
            'confirmed_at' => now()->subDays(3),
            'completion_requested_at' => now()->subHours(25),
            'completion_note' => 'Смена выполнена.',
        ]);

        ShiftAct::create([
            'public_id' => (string) Str::uuid(),
            'order_id' => $order->id,
            'order_caregiver_assignment_id' => $assignment->id,
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
            'number' => 'ACT-AUTO-1',
            'status' => ShiftAct::STATUS_AWAITING_CLIENT,
            'body_html' => '<p>Акт смены</p>',
            'document_hash' => hash('sha256', '<p>Акт смены</p>'),
            'gross_amount' => 1200,
            'commission_amount' => 120,
            'payout_amount' => 1080,
            'caregiver_confirmed_at' => now()->subHours(25),
            'meta' => ['commission_percent' => 10],
        ]);

        app(OrderFinanceService::class)
            ->holdBaseOrderPayment($order->fresh(['client', 'scheduleSlots']));
        $order->update(['payment_status' => 'held']);

        $this->artisan('shifts:settle-overdue')
            ->expectsOutput('Сформировано выплат: 1; ошибок: 0.')
            ->assertExitCode(0);

        $this->assertSame('completed', $assignment->fresh()->status);
        $this->assertNotNull($assignment->fresh()->payout_generated_at);
        $this->assertDatabaseHas('shift_acts', [
            'order_caregiver_assignment_id' => $assignment->id,
            'status' => ShiftAct::STATUS_RESOLVED,
            'client_confirmed_at' => null,
            'signed_at' => null,
        ]);
        $this->assertDatabaseHas('payouts', [
            'order_caregiver_assignment_id' => $assignment->id,
            'caregiver_id' => $caregiver->id,
            'gross_amount' => 1200,
            'commission_amount' => 120,
            'amount' => 1080,
            'status' => 'pending',
        ]);
    }
}
