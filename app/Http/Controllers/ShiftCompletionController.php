<?php

namespace App\Http\Controllers;

use App\Models\CarePlan;
use App\Models\Order;
use App\Models\OrderCaregiverAssignment;
use App\Models\ShiftJournal;
use App\Services\OrderBalanceClosureService;
use App\Services\OrderFinanceService;
use App\Services\ShiftActService;
use App\Services\ShiftSettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShiftCompletionController extends Controller
{
    public function __construct(
        private OrderFinanceService $financeService,
        private ShiftActService $acts,
        private ShiftSettlementService $settlements,
        private OrderBalanceClosureService $balanceClosure,
    ) {
    }

    public function requestCompletion(Request $request, Order $order, OrderCaregiverAssignment $assignment): RedirectResponse
    {
        $user = $request->user();

        abort_unless(
            $user->isCaregiver()
            && $assignment->order_id === $order->id
            && $assignment->caregiver_id === $user->id,
            404
        );
        abort_unless($order->status === 'in_progress', 422);

        if ($assignment->status === 'completed') {
            return back()->with('status', 'Эта смена уже подтверждена и передана в выплату.');
        }

        abort_unless($assignment->status === 'accepted', 422);
        $this->assertShiftEnded($assignment);

        $data = $request->validate([
            'completion_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $hasCarePlan = CarePlan::where('order_id', $order->id)->where('status', 'active')->exists();
        $journal = ShiftJournal::where('order_caregiver_assignment_id', $assignment->id)->first();

        if ($hasCarePlan && (! $journal || $journal->status !== 'submitted')) {
            throw ValidationException::withMessages([
                'journal' => 'Перед завершением смены заполните и отправьте журнал ухода.',
            ]);
        }

        $act = DB::transaction(function () use ($assignment, $user, $request, $data) {
            if (! $assignment->completion_requested_at) {
                $assignment->update([
                    'completion_requested_at' => now(),
                    'completion_note' => $data['completion_note'] ?? null,
                ]);
            }

            return $this->acts->createForAssignment($assignment->fresh(), $user, $request);
        });

        $this->financeService->notify(
            $order->client,
            'shift.completion_requested',
            'Сиделка завершила смену',
            $user->name . ' направила журнал и акт ' . $act->number
                . '. Подтвердите смену или откройте спор.',
            ['order_id' => $order->id, 'assignment_id' => $assignment->id, 'act_id' => $act->id],
        );

        return back()->with('status', 'Журнал и акт отправлены заказчику. Ожидается подтверждение.');
    }

    public function confirmCompletion(Request $request, Order $order, OrderCaregiverAssignment $assignment): RedirectResponse
    {
        $user = $request->user();

        abort_unless(
            $user->isClient()
            && $order->client_id === $user->id
            && $assignment->order_id === $order->id,
            404
        );
        abort_unless($order->status === 'in_progress', 422);

        if ($assignment->status === 'completed') {
            return back()->with('status', 'Эта смена уже подтверждена и выплата сформирована.');
        }

        abort_unless($assignment->status === 'accepted', 422);
        abort_unless($assignment->completion_requested_at !== null, 422);
        $this->assertShiftEnded($assignment);

        if ($assignment->disputes()->whereIn('status', ['open', 'in_review'])->exists()) {
            throw ValidationException::withMessages(['dispute' => 'По смене открыт спор. Подтверждение доступно после решения.']);
        }

        $act = $assignment->act()->firstOrFail();
        $this->acts->signByClient($act, $user, $request);
        $payout = $this->settlements->settle($assignment->fresh());

        ShiftJournal::where('order_caregiver_assignment_id', $assignment->id)->update([
            'status' => 'accepted',
            'accepted_at' => now(),
            'client_comment' => $request->input('client_comment'),
        ]);

        $conversation = $order->conversations()->where('caregiver_id', $assignment->caregiver_id)->first();
        if ($conversation) {
            $conversation->messages()->create([
                'sender_id' => $user->id,
                'body' => 'Акт смены подтверждён заказчиком. Выплата '
                    . number_format($payout->amount, 0, ',', ' ')
                    . ' ₽ сформирована и передана на обработку.',
            ]);
        }

        return back()->with('status', 'Акт подписан заказчиком. Выплата сиделке сформирована отдельно от остальных смен.');
    }

    public function completeOrder(Request $request, Order $order): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isClient() && $order->client_id === $user->id, 404);
        abort_unless($order->status === 'in_progress', 422);

        $order->loadMissing(['scheduleSlots', 'caregiverAssignments', 'conversations']);
        $unfinished = $order->caregiverAssignments->whereIn('status', ['invited', 'accepted']);

        if ($unfinished->isNotEmpty()) {
            throw ValidationException::withMessages([
                'shift' => 'Сначала завершите все принятые смены. Незавершенных назначений: ' . $unfinished->count() . '.',
            ]);
        }

        $completedSlotIds = $order->caregiverAssignments
            ->where('status', 'completed')
            ->pluck('order_schedule_slot_id')
            ->filter()
            ->unique();
        $uncoveredSlotIds = $order->scheduleSlots->pluck('id')->diff($completedSlotIds);

        if ($uncoveredSlotIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'shift' => 'Нельзя закрыть заказ: не завершено или не назначено календарных смен: ' . $uncoveredSlotIds->count() . '.',
            ]);
        }

        if ($completedSlotIds->isEmpty()) {
            throw ValidationException::withMessages(['shift' => 'Нельзя закрыть заказ без хотя бы одной завершенной смены.']);
        }

        $refunded = DB::transaction(function () use ($order, $user) {
            $this->financeService->releaseHeldPayments($order->fresh([
                'client', 'caregiver', 'caregiverAssignments.caregiver',
                'caregiverAssignments.scheduleSlot', 'scheduleSlots', 'payments',
            ]));

            $refundedAmount = $this->balanceClosure->refundUnusedBasePayment(
                $order->fresh(),
                'Возврат неиспользованной части после окончательного расчета по сменам',
            );

            $order->update(['status' => 'completed']);
            $this->balanceClosure->syncFinalPaymentStatus($order->fresh());

            foreach ($order->conversations->where('status', 'active') as $conversation) {
                $conversation->messages()->create([
                    'sender_id' => $user->id,
                    'body' => 'Все смены заказа завершены. Акты и выплаты сформированы отдельно по каждой сиделке.'
                        . ($refundedAmount > 0 ? ' Неиспользованный остаток возвращён заказчику.' : ''),
                ]);
            }

            return $refundedAmount;
        });

        return redirect()->route('client.orders.show', $order)
            ->with('status', 'Заказ завершен. Каждая смена оформлена отдельным актом.'
                . ($refunded > 0 ? ' Возвращено на баланс: ' . number_format($refunded, 0, ',', ' ') . ' ₽.' : ''));
    }

    private function assertShiftEnded(OrderCaregiverAssignment $assignment): void
    {
        $assignment->loadMissing('scheduleSlot');
        $slot = $assignment->scheduleSlot;

        if (! $slot) {
            return;
        }

        $endsAt = Carbon::parse($slot->scheduled_date->format('Y-m-d') . ' ' . $slot->ends_at);
        if ($endsAt->isFuture()) {
            throw ValidationException::withMessages([
                'shift' => 'Эту смену можно завершить после ' . $endsAt->format('d.m.Y H:i') . '.',
            ]);
        }
    }
}
