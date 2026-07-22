<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderCaregiverAssignment;
use App\Services\OrderFinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShiftCompletionController extends Controller
{
    public function __construct(private OrderFinanceService $financeService)
    {
    }

    public function requestCompletion(
        Request $request,
        Order $order,
        OrderCaregiverAssignment $assignment,
    ): RedirectResponse {
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

        abort_unless(in_array($assignment->status, ['accepted', 'completion_requested'], true), 422);
        $this->assertShiftEnded($assignment);

        $data = $request->validate([
            'completion_note' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($assignment->status !== 'completion_requested') {
            $assignment->update([
                'status' => 'completion_requested',
                'completion_requested_at' => now(),
                'completion_note' => $data['completion_note'] ?? null,
            ]);

            $this->financeService->notify(
                $order->client,
                'shift.completion_requested',
                'Сиделка завершила смену',
                $user->name . ' отметила смену по заказу «' . $order->title
                    . '» как выполненную. Подтвердите смену, чтобы сформировать выплату.',
                ['order_id' => $order->id, 'assignment_id' => $assignment->id],
            );
        }

        return back()->with('status', 'Смена отмечена как отработанная. Ожидается подтверждение клиента.');
    }

    public function confirmCompletion(
        Request $request,
        Order $order,
        OrderCaregiverAssignment $assignment,
    ): RedirectResponse {
        $user = $request->user();
        $canConfirm = ($user->isClient() && $order->client_id === $user->id)
            || $user->isAdmin()
            || $user->isCrm();

        abort_unless($canConfirm && $assignment->order_id === $order->id, 404);
        abort_unless($order->status === 'in_progress', 422);

        if ($assignment->status === 'completed') {
            return back()->with('status', 'Эта смена уже подтверждена и выплата сформирована.');
        }

        abort_unless(in_array($assignment->status, ['accepted', 'completion_requested'], true), 422);
        $this->assertShiftEnded($assignment);

        $payout = $this->financeService->releaseAssignmentPayout($assignment, $user);

        $conversation = $order->conversations()
            ->where('caregiver_id', $assignment->caregiver_id)
            ->first();

        if ($conversation) {
            $conversation->messages()->create([
                'sender_id' => $user->id,
                'body' => 'Смена подтверждена. Выплата '
                    . number_format($payout->amount, 0, ',', ' ')
                    . ' ₽ сформирована и передана на обработку.',
            ]);
        }

        return back()->with('status', 'Смена подтверждена. Выплата сиделке сформирована отдельно от остальных смен.');
    }

    public function completeOrder(Request $request, Order $order): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->isClient() && $order->client_id === $user->id, 404);
        abort_unless($order->status === 'in_progress', 422);

        $order->loadMissing(['caregiverAssignments', 'conversations']);

        $unfinished = $order->caregiverAssignments
            ->whereIn('status', ['invited', 'accepted', 'completion_requested']);

        if ($unfinished->isNotEmpty()) {
            throw ValidationException::withMessages([
                'shift' => 'Сначала подтвердите завершение всех принятых смен. Незавершенных назначений: '
                    . $unfinished->count() . '.',
            ]);
        }

        if ($order->caregiverAssignments->where('status', 'completed')->isEmpty()) {
            throw ValidationException::withMessages([
                'shift' => 'Нельзя закрыть заказ без хотя бы одной завершенной смены.',
            ]);
        }

        DB::transaction(function () use ($order, $user) {
            $this->financeService->releaseHeldPayments($order->fresh([
                'client',
                'caregiver',
                'caregiverAssignments.caregiver',
                'caregiverAssignments.scheduleSlot',
                'scheduleSlots',
                'payments',
            ]));

            $order->update(['status' => 'completed']);

            foreach ($order->conversations->where('status', 'active') as $conversation) {
                $conversation->messages()->create([
                    'sender_id' => $user->id,
                    'body' => 'Все смены заказа завершены. Сформированные выплаты обрабатываются площадкой отдельно по каждой сиделке.',
                ]);
            }
        });

        return redirect()->route('client.orders.show', $order)
            ->with('status', 'Заказ завершен. Каждая отработанная смена рассчитана отдельно.');
    }

    private function assertShiftEnded(OrderCaregiverAssignment $assignment): void
    {
        $assignment->loadMissing('scheduleSlot');
        $slot = $assignment->scheduleSlot;

        if (! $slot) {
            return;
        }

        $endsAt = Carbon::parse(
            $slot->scheduled_date->format('Y-m-d') . ' ' . $slot->ends_at
        );

        if ($endsAt->isFuture()) {
            throw ValidationException::withMessages([
                'shift' => 'Эту смену можно завершить после ' . $endsAt->format('d.m.Y H:i') . '.',
            ]);
        }
    }
}
