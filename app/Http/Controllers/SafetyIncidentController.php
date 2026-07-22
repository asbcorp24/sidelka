<?php

namespace App\Http\Controllers;

use App\Models\CrmTask;
use App\Models\Order;
use App\Models\OrderCaregiverAssignment;
use App\Models\SafetyIncident;
use App\Models\ShiftJournal;
use App\Models\User;
use App\Services\OrderFinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SafetyIncidentController extends Controller
{
    public function __construct(private OrderFinanceService $finance)
    {
    }

    public function store(Request $request, Order $order): RedirectResponse
    {
        $user = $request->user();
        $hasOrderAccess = ($user->isClient() && $order->client_id === $user->id)
            || ($user->isCaregiver() && ($order->caregiver_id === $user->id || $order->caregiverAssignments()->where('caregiver_id', $user->id)->exists()))
            || $user->hasStaffPermission('crm.incidents.manage');
        abort_unless($hasOrderAccess, 403);

        $data = $request->validate([
            'order_caregiver_assignment_id' => ['nullable', 'integer', 'exists:order_caregiver_assignments,id'],
            'incident_type' => ['required', Rule::in(array_keys(SafetyIncident::TYPE_LABELS))],
            'severity' => ['required', Rule::in(array_keys(SafetyIncident::SEVERITY_LABELS))],
            'occurred_at' => ['required', 'date'],
            'description' => ['required', 'string', 'max:5000'],
            'actions_taken' => ['nullable', 'string', 'max:5000'],
            'emergency_called' => ['nullable', 'boolean'],
            'emergency_service_reference' => ['nullable', 'string', 'max:255'],
        ]);

        $assignment = null;
        if (! empty($data['order_caregiver_assignment_id'])) {
            $assignment = OrderCaregiverAssignment::findOrFail($data['order_caregiver_assignment_id']);
            abort_unless($assignment->order_id === $order->id, 422);
        }

        $incident = DB::transaction(function () use ($data, $user, $order, $assignment) {
            $supervisor = User::query()
                ->where(function ($query) {
                    $query->where('role', 'admin')
                        ->orWhere(fn ($staff) => $staff->where('role', 'crm')->where('staff_active', true)->whereIn('staff_role', ['supervisor', 'manager']));
                })
                ->orderByRaw("CASE WHEN role = 'crm' THEN 0 ELSE 1 END")
                ->first();

            $journalId = $assignment
                ? ShiftJournal::where('order_caregiver_assignment_id', $assignment->id)->value('id')
                : null;

            $incident = SafetyIncident::create([
                'public_id' => (string) Str::uuid(),
                'order_id' => $order->id,
                'order_caregiver_assignment_id' => $assignment?->id,
                'shift_journal_id' => $journalId,
                'reported_by_id' => $user->id,
                'assigned_to_id' => $supervisor?->id,
                'incident_type' => $data['incident_type'],
                'severity' => $data['severity'],
                'status' => 'open',
                'occurred_at' => $data['occurred_at'],
                'description' => $data['description'],
                'actions_taken' => $data['actions_taken'] ?? null,
                'emergency_called' => (bool) ($data['emergency_called'] ?? false),
                'emergency_service_reference' => $data['emergency_service_reference'] ?? null,
                'client_notified_at' => $user->isClient() ? now() : null,
            ]);

            $incident->updates()->create([
                'author_id' => $user->id,
                'body' => $data['description'],
                'status_to' => 'open',
                'is_internal' => false,
            ]);

            CrmTask::firstOrCreate(['dedup_key' => 'safety-incident:' . $incident->id], [
                'person_user_id' => $assignment?->caregiver_id,
                'assigned_to_id' => $supervisor?->id,
                'created_by_id' => $user->id,
                'title' => 'Инцидент безопасности: ' . SafetyIncident::TYPE_LABELS[$incident->incident_type],
                'description' => 'Заказ #' . $order->id . '. ' . $incident->description,
                'category' => 'safety_incident',
                'source_type' => SafetyIncident::class,
                'source_id' => $incident->id,
                'status' => 'open',
                'priority' => in_array($incident->severity, ['high', 'critical'], true) ? 'urgent' : 'high',
                'due_at' => $incident->severity === 'critical' ? now()->addMinutes(15) : now()->addHours(4),
            ]);

            return $incident;
        });

        if (! $user->isClient()) {
            $this->finance->notify(
                $order->client,
                'safety.incident',
                'Инцидент по заказу',
                SafetyIncident::TYPE_LABELS[$incident->incident_type] . ': ' . $incident->description,
                ['incident_id' => $incident->id, 'order_id' => $order->id],
            );
            $incident->update(['client_notified_at' => now()]);
        }

        return back()->with('status', 'Инцидент зарегистрирован и передан ответственному сотруднику.');
    }

    public function index(Request $request): View
    {
        $query = SafetyIncident::query()
            ->with(['order', 'assignment.caregiver', 'reportedBy', 'assignedTo', 'updates.author'])
            ->latest('occurred_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('severity')) {
            $query->where('severity', $request->input('severity'));
        }

        return view('crm.incidents', [
            'incidents' => $query->paginate(30)->withQueryString(),
            'employees' => User::whereIn('role', ['crm', 'admin'])->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, SafetyIncident $safetyIncident): RedirectResponse
    {
        abort_unless($request->user()->hasStaffPermission('crm.incidents.manage'), 403);

        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'in_progress', 'resolved', 'closed'])],
            'assigned_to_id' => ['nullable', 'integer', 'exists:users,id'],
            'body' => ['required', 'string', 'max:5000'],
            'resolution' => ['nullable', 'string', 'max:5000'],
            'is_internal' => ['nullable', 'boolean'],
        ]);

        $oldStatus = $safetyIncident->status;
        $safetyIncident->update([
            'status' => $data['status'],
            'assigned_to_id' => $data['assigned_to_id'] ?? $safetyIncident->assigned_to_id ?? $request->user()->id,
            'resolution' => $data['resolution'] ?? $safetyIncident->resolution,
            'resolved_at' => in_array($data['status'], ['resolved', 'closed'], true) ? now() : null,
        ]);

        $safetyIncident->updates()->create([
            'author_id' => $request->user()->id,
            'body' => $data['body'],
            'status_from' => $oldStatus,
            'status_to' => $data['status'],
            'is_internal' => (bool) ($data['is_internal'] ?? false),
        ]);

        if (in_array($data['status'], ['resolved', 'closed'], true)) {
            CrmTask::where('dedup_key', 'safety-incident:' . $safetyIncident->id)
                ->where('status', 'open')
                ->update(['status' => 'completed', 'completed_at' => now()]);
        }

        return back()->with('status', 'Инцидент обновлён.');
    }
}
