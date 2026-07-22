<?php

namespace App\Http\Controllers;

use App\Models\CrmTask;
use App\Models\Order;
use App\Models\OrderCaregiverAssignment;
use App\Models\ShiftAct;
use App\Models\ShiftDispute;
use App\Models\User;
use App\Services\OrderFinanceService;
use App\Services\ShiftSettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ShiftDisputeController extends Controller
{
    public function __construct(
        private ShiftSettlementService $settlements,
        private OrderFinanceService $finance,
    ) {
    }

    public function store(Request $request, Order $order, OrderCaregiverAssignment $assignment): RedirectResponse
    {
        $user = $request->user();
        $isParty = ($user->isClient() && $order->client_id === $user->id)
            || ($user->isCaregiver() && $assignment->caregiver_id === $user->id);

        abort_unless($isParty && $assignment->order_id === $order->id, 404);
        abort_if($assignment->status === 'completed' && $assignment->payout?->status === 'paid', 422);

        $data = $request->validate([
            'reason' => ['required', Rule::in(['quality', 'time', 'scope', 'behavior', 'damage', 'payment', 'other'])],
            'description' => ['required', 'string', 'max:5000'],
            'requested_action' => ['nullable', Rule::in(['full_payment', 'partial_payment', 'no_payment', 'refund', 'investigation'])],
        ]);

        if ($assignment->disputes()->whereIn('status', ['open', 'in_review'])->exists()) {
            throw ValidationException::withMessages(['dispute' => 'По этой смене уже есть открытый спор.']);
        }

        $dispute = DB::transaction(function () use ($assignment, $order, $user, $data) {
            $supervisor = User::query()
                ->where(function ($query) {
                    $query->where('role', 'admin')
                        ->orWhere(fn ($staff) => $staff->where('role', 'crm')->where('staff_active', true)->whereIn('staff_role', ['supervisor', 'manager']));
                })
                ->orderByRaw("CASE WHEN role = 'crm' THEN 0 ELSE 1 END")
                ->first();

            $act = $assignment->act;
            if ($act) {
                $act->update(['status' => ShiftAct::STATUS_DISPUTED, 'disputed_at' => now()]);
            }

            $dispute = ShiftDispute::create([
                'public_id' => (string) Str::uuid(),
                'order_id' => $order->id,
                'order_caregiver_assignment_id' => $assignment->id,
                'shift_act_id' => $act?->id,
                'opened_by_id' => $user->id,
                'assigned_to_id' => $supervisor?->id,
                'status' => 'open',
                'reason' => $data['reason'],
                'description' => $data['description'],
                'requested_action' => $data['requested_action'] ?? null,
                'opened_at' => now(),
            ]);

            $dispute->messages()->create([
                'author_id' => $user->id,
                'body' => $data['description'],
                'is_internal' => false,
            ]);

            CrmTask::firstOrCreate(['dedup_key' => 'shift-dispute:' . $dispute->id], [
                'person_user_id' => $assignment->caregiver_id,
                'assigned_to_id' => $supervisor?->id,
                'created_by_id' => $user->id,
                'title' => 'Рассмотреть спор по смене заказа #' . $order->id,
                'description' => $data['description'],
                'category' => 'shift_dispute',
                'source_type' => ShiftDispute::class,
                'source_id' => $dispute->id,
                'status' => 'open',
                'priority' => 'high',
                'due_at' => now()->addHours(24),
            ]);

            return $dispute;
        });

        $this->finance->notify(
            $dispute->assignedTo,
            'shift.dispute.opened',
            'Открыт спор по смене',
            'Заказ #' . $order->id . ', смена #' . $assignment->id . '. Требуется решение.',
            ['dispute_id' => $dispute->id],
        );

        return back()->with('status', 'Спор открыт. Выплата по этой смене приостановлена до решения.');
    }

    public function index(Request $request): View
    {
        $query = ShiftDispute::query()
            ->with(['order', 'assignment.caregiver', 'openedBy', 'assignedTo', 'act', 'messages.author'])
            ->latest('opened_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return view('crm.disputes', [
            'disputes' => $query->paginate(30)->withQueryString(),
            'employees' => User::whereIn('role', ['crm', 'admin'])->orderBy('name')->get(),
        ]);
    }

    public function addMessage(Request $request, ShiftDispute $shiftDispute): RedirectResponse
    {
        $user = $request->user();
        $shiftDispute->loadMissing(['assignment', 'order']);
        $isParty = in_array($user->id, [
            $shiftDispute->opened_by_id,
            $shiftDispute->assignment?->caregiver_id,
            $shiftDispute->order?->client_id,
        ], true);
        abort_unless($isParty || $user->hasStaffPermission('crm.disputes.manage'), 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'is_internal' => ['nullable', 'boolean'],
        ]);

        $shiftDispute->messages()->create([
            'author_id' => $user->id,
            'body' => $data['body'],
            'is_internal' => $user->hasStaffPermission('crm.disputes.manage') && (bool) ($data['is_internal'] ?? false),
        ]);

        return back()->with('status', 'Комментарий к спору добавлен.');
    }

    public function resolve(Request $request, ShiftDispute $shiftDispute): RedirectResponse
    {
        abort_unless($request->user()->hasStaffPermission('crm.disputes.manage'), 403);
        abort_unless(in_array($shiftDispute->status, ['open', 'in_review'], true), 422);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve_full', 'approve_partial', 'reject_payment'])],
            'approved_gross_amount' => ['nullable', 'integer', 'min:0'],
            'resolution' => ['required', 'string', 'max:5000'],
        ]);

        if ($data['decision'] === 'approve_partial' && ! isset($data['approved_gross_amount'])) {
            throw ValidationException::withMessages(['approved_gross_amount' => 'Укажите согласованную сумму.']);
        }

        DB::transaction(function () use ($shiftDispute, $request, $data) {
            $shiftDispute->loadMissing(['assignment.act', 'order']);
            $assignment = $shiftDispute->assignment;
            $act = $assignment->act;

            if (! $act) {
                throw ValidationException::withMessages(['act' => 'У смены отсутствует акт.']);
            }

            $approved = match ($data['decision']) {
                'approve_full' => (int) $act->gross_amount,
                'approve_partial' => min((int) $data['approved_gross_amount'], (int) $act->gross_amount),
                default => 0,
            };

            $shiftDispute->update([
                'status' => 'resolved',
                'assigned_to_id' => $shiftDispute->assigned_to_id ?: $request->user()->id,
                'decision' => $data['decision'],
                'approved_gross_amount' => $approved,
                'resolution' => $data['resolution'],
                'resolved_at' => now(),
            ]);

            $act->update([
                'status' => $data['decision'] === 'reject_payment'
                    ? ShiftAct::STATUS_CANCELLED
                    : ShiftAct::STATUS_RESOLVED,
                'meta' => array_merge($act->meta ?? [], [
                    'resolved_by_id' => $request->user()->id,
                    'resolved_at' => now()->toIso8601String(),
                    'dispute_decision' => $data['decision'],
                    'approved_gross_amount' => $approved,
                    'resolution' => $data['resolution'],
                    'resolution_basis' => 'Решение уполномоченного сотрудника CRM по спору; не является подписью заказчика.',
                ]),
            ]);

            $this->settlements->settle($assignment, $approved);

            $shiftDispute->messages()->create([
                'author_id' => $request->user()->id,
                'body' => 'Решение: ' . $data['resolution'],
                'is_internal' => false,
            ]);

            CrmTask::where('dedup_key', 'shift-dispute:' . $shiftDispute->id)
                ->where('status', 'open')
                ->update(['status' => 'completed', 'completed_at' => now()]);
        });

        return back()->with('status', 'Спор решён. Расчёт выполнен только по этой смене.');
    }
}
