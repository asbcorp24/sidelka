<?php

namespace App\Services;

use App\Models\LegalContract;
use App\Models\OrderCaregiverAssignment;
use App\Models\OrderScheduleSlot;
use App\Models\Payment;
use App\Models\ShiftAct;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShiftActService
{
    public function createForAssignment(OrderCaregiverAssignment $assignment, User $caregiver, Request $request): ShiftAct
    {
        $assignment->loadMissing(['order.client', 'order.scheduleSlots', 'order.payments', 'caregiver', 'scheduleSlot']);
        $order = $assignment->order;

        abort_unless($assignment->caregiver_id === $caregiver->id, 403);
        abort_unless($assignment->status === 'accepted', 422);

        $existing = $assignment->act;
        if ($existing) {
            return $existing;
        }

        $gross = $this->grossAmount($assignment);
        $commissionPercent = $this->commissionPercent($order->id, $caregiver->id);
        $commission = (int) round($gross * $commissionPercent / 100);
        $net = max(0, $gross - $commission);
        $number = sprintf('ACT-%d-%d-%s', $order->id, $assignment->id, now()->format('YmdHis'));

        $body = View::make('acts.shift', [
            'number' => $number,
            'order' => $order,
            'assignment' => $assignment,
            'slot' => $assignment->scheduleSlot,
            'client' => $order->client,
            'caregiver' => $caregiver,
            'grossAmount' => $gross,
            'commissionPercent' => $commissionPercent,
            'commissionAmount' => $commission,
            'payoutAmount' => $net,
        ])->render();

        return ShiftAct::create([
            'public_id' => (string) Str::uuid(),
            'order_id' => $order->id,
            'order_caregiver_assignment_id' => $assignment->id,
            'client_id' => $order->client_id,
            'caregiver_id' => $caregiver->id,
            'number' => $number,
            'status' => ShiftAct::STATUS_AWAITING_CLIENT,
            'body_html' => $body,
            'document_hash' => hash('sha256', $body),
            'gross_amount' => $gross,
            'commission_amount' => $commission,
            'payout_amount' => $net,
            'caregiver_confirmed_at' => now(),
            'caregiver_ip' => $request->ip(),
            'caregiver_user_agent' => Str::limit((string) $request->userAgent(), 4000, ''),
            'meta' => [
                'commission_percent' => $commissionPercent,
                'assignment_id' => $assignment->id,
                'slot_id' => $assignment->order_schedule_slot_id,
                'caregiver_confirmation' => 'Смена выполнена, сведения журнала достоверны, акт направлен заказчику.',
            ],
        ]);
    }

    public function signByClient(ShiftAct $act, User $client, Request $request): ShiftAct
    {
        abort_unless($act->client_id === $client->id, 403);

        if ($act->status === ShiftAct::STATUS_SIGNED) {
            return $act;
        }

        if ($act->status === ShiftAct::STATUS_DISPUTED) {
            throw ValidationException::withMessages(['act' => 'По этой смене открыт спор. Сначала требуется решение CRM.']);
        }

        $act->update([
            'status' => ShiftAct::STATUS_SIGNED,
            'client_confirmed_at' => now(),
            'client_ip' => $request->ip(),
            'client_user_agent' => Str::limit((string) $request->userAgent(), 4000, ''),
            'signed_at' => now(),
        ]);

        return $act->fresh();
    }

    private function grossAmount(OrderCaregiverAssignment $assignment): int
    {
        $order = $assignment->order;
        $payment = $order->payments->firstWhere('kind', 'base_order');

        if ($payment) {
            return $this->distributedPaymentAmount($assignment, $payment);
        }

        $slot = $assignment->scheduleSlot;
        if ($slot) {
            $start = Carbon::parse($slot->scheduled_date->format('Y-m-d') . ' ' . $slot->starts_at);
            $end = Carbon::parse($slot->scheduled_date->format('Y-m-d') . ' ' . $slot->ends_at);
            $minutes = max(1, $start->diffInMinutes($end));

            return (int) round(($minutes / 60) * (int) $order->hourly_budget);
        }

        return (int) $order->base_amount;
    }

    private function distributedPaymentAmount(OrderCaregiverAssignment $assignment, Payment $payment): int
    {
        $slots = $assignment->order->scheduleSlots
            ->sortBy(fn (OrderScheduleSlot $slot) => $slot->scheduled_date->format('Y-m-d') . ' ' . $slot->starts_at . ' ' . $slot->id)
            ->values();

        if (! $assignment->scheduleSlot || $slots->isEmpty()) {
            return (int) $payment->amount;
        }

        $minutes = $slots->mapWithKeys(function (OrderScheduleSlot $slot) {
            $start = Carbon::parse($slot->scheduled_date->format('Y-m-d') . ' ' . $slot->starts_at);
            $end = Carbon::parse($slot->scheduled_date->format('Y-m-d') . ' ' . $slot->ends_at);
            return [$slot->id => max(1, $start->diffInMinutes($end))];
        });

        $total = max(1, (int) $minutes->sum());
        $distributed = 0;
        $amounts = [];

        foreach ($slots as $index => $slot) {
            $amount = $index === $slots->count() - 1
                ? (int) $payment->amount - $distributed
                : (int) floor((int) $payment->amount * ((int) $minutes[$slot->id] / $total));
            $amounts[$slot->id] = max(0, $amount);
            $distributed += $amounts[$slot->id];
        }

        return (int) ($amounts[$assignment->order_schedule_slot_id] ?? 0);
    }

    private function commissionPercent(int $orderId, int $caregiverId): float
    {
        $contract = LegalContract::query()
            ->where('type', LegalContract::TYPE_ORDER_SERVICE)
            ->where('order_id', $orderId)
            ->where('meta->caregiver_id', $caregiverId)
            ->where('status', LegalContract::STATUS_SIGNED)
            ->latest('id')
            ->first();

        $percent = ($contract?->meta ?? [])['commission_percent']
            ?? config('legal.agent_commission_percent', 0);

        return max(0, min(100, (float) $percent));
    }
}
