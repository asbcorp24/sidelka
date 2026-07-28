<?php

namespace App\Http\Controllers;

use App\Models\AvailabilitySlot;
use App\Models\CaregiverProfile;
use App\Models\Conversation;
use App\Models\CrmInteraction;
use App\Models\CrmRequest;
use App\Models\CrmTask;
use App\Models\Order;
use App\Models\OrderCaregiverAssignment;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Refund;
use App\Models\Review;
use App\Models\SafetyIncident;
use App\Models\ShiftReport;
use App\Models\Service;
use App\Models\User;
use App\Models\UserDocument;
use App\Models\UserReport;
use App\Support\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CrmController extends Controller
{
    public function __construct(private PlatformSettings $platformSettings)
    {
    }

    public function dashboard(Request $request): View
    {
        $query = CrmRequest::query()
            ->with(['responsible', 'clientUser', 'caregiverUser', 'order'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', (string) $request->input('priority'));
        }

        if ($request->filled('responsible_user_id')) {
            $query->where('responsible_user_id', $request->integer('responsible_user_id'));
        }

        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $query->where(function ($inner) use ($term) {
                $inner->where('caller_name', 'like', "%{$term}%")
                    ->orWhere('caller_phone', 'like', "%{$term}%")
                    ->orWhere('patient_name', 'like', "%{$term}%")
                    ->orWhere('city', 'like', "%{$term}%")
                    ->orWhere('service_text', 'like', "%{$term}%");
            });
        }

        $employees = User::query()
            ->whereIn('role', ['admin', 'crm'])
            ->orderBy('name')
            ->get();

        $tasks = CrmTask::query()
            ->with(['crmRequest', 'personUser', 'assignedTo'])
            ->where('status', 'open')
            ->where(function ($taskQuery) use ($request) {
                $taskQuery->whereNull('assigned_to_id')
                    ->orWhere('assigned_to_id', $request->user()->id);
            })
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->limit(12)
            ->get();

        $longOrdersCount = Order::query()
            ->withCount('scheduleSlots')
            ->get()
            ->filter(fn (Order $order) => $order->allows_multiple_caregivers || $order->schedule_slots_count > 3)
            ->count();

        return view('crm.dashboard', [
            'requests' => $query->paginate(30)->withQueryString(),
            'employees' => $employees,
            'tasks' => $tasks,
            'stats' => [
                'new' => CrmRequest::where('status', 'new')->count(),
                'searching' => CrmRequest::whereIn('status', ['qualification', 'searching'])->count(),
                'waiting' => CrmRequest::whereIn('status', ['caregiver_found', 'awaiting_client'])->count(),
                'active' => CrmRequest::whereIn('status', ['booked', 'active'])->count(),
                'overdue_tasks' => CrmTask::where('status', 'open')->whereNotNull('due_at')->where('due_at', '<', now())->count(),
                'long_orders' => $longOrdersCount,
                'funnel' => [
                    ['label' => 'Новая', 'count' => CrmRequest::where('status', 'new')->count(), 'class' => 'text-bg-danger'],
                    ['label' => 'Квалификация', 'count' => CrmRequest::where('status', 'qualification')->count(), 'class' => 'text-bg-warning'],
                    ['label' => 'Ищем', 'count' => CrmRequest::where('status', 'searching')->count(), 'class' => 'text-bg-info'],
                    ['label' => 'Предложили', 'count' => CrmRequest::where('status', 'caregiver_found')->count(), 'class' => 'text-bg-primary'],
                    ['label' => 'Согласовано', 'count' => CrmRequest::whereIn('status', ['awaiting_client', 'booked'])->count(), 'class' => 'text-bg-success'],
                    ['label' => 'В работе', 'count' => CrmRequest::where('status', 'active')->count(), 'class' => 'text-bg-dark'],
                    ['label' => 'Закрыта', 'count' => CrmRequest::where('status', 'completed')->count(), 'class' => 'text-bg-secondary'],
                ],
            ],
            'statusLabels' => CrmRequest::STATUS_LABELS,
            'priorityLabels' => CrmRequest::PRIORITY_LABELS,
        ]);
    }

    public function kanban(Request $request): View
    {
        $statuses = ['new', 'qualification', 'searching', 'caregiver_found', 'awaiting_client', 'booked', 'active', 'completed'];

        $requests = CrmRequest::query()
            ->with(['responsible', 'caregiverUser', 'order'])
            ->whereIn('status', $statuses)
            ->latest()
            ->get()
            ->groupBy('status');

        return view('crm.kanban', [
            'statuses' => $statuses,
            'requestsByStatus' => $requests,
            'statusLabels' => CrmRequest::STATUS_LABELS,
        ]);
    }

    public function people(Request $request): View
    {
        $query = User::query()
            ->whereIn('role', ['client', 'caregiver'])
            ->with(['caregiverProfile.availabilitySlots'])
            ->latest();

        if ($request->filled('role')) {
            $query->where('role', (string) $request->input('role'));
        }

        if ($request->filled('city')) {
            $query->where('city', 'like', '%' . trim((string) $request->input('city')) . '%');
        }

        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $query->where(function ($inner) use ($term) {
                $inner->where('name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            });
        }

        return view('crm.people', [
            'people' => $query->paginate(40)->withQueryString(),
            'stats' => [
                'clients' => User::where('role', 'client')->count(),
                'caregivers' => User::where('role', 'caregiver')->count(),
                'available_today' => AvailabilitySlot::query()
                    ->whereDate('specific_date', today())
                    ->distinct('caregiver_profile_id')
                    ->count('caregiver_profile_id'),
            ],
        ]);
    }

    public function showPerson(User $person): View
    {
        abort_unless(in_array($person->role, ['client', 'caregiver'], true), 404);

        $person->load([
            'caregiverProfile.availabilitySlots',
            'caregiverProfile.services',
            'clientOrders.caregiver',
            'caregiverOrders.client',
        ]);

        $interactions = CrmInteraction::query()
            ->where('person_user_id', $person->id)
            ->with('employee')
            ->orderByDesc('happened_at')
            ->limit(100)
            ->get();

        $tasks = CrmTask::query()
            ->where('person_user_id', $person->id)
            ->with('assignedTo')
            ->orderBy('status')
            ->orderBy('due_at')
            ->get();

        $requests = CrmRequest::query()
            ->where(function ($query) use ($person) {
                $query->where('client_user_id', $person->id)
                    ->orWhere('caregiver_user_id', $person->id);
            })
            ->with(['responsible', 'order'])
            ->latest()
            ->get();

        return view('crm.person', [
            'person' => $person,
            'interactions' => $interactions,
            'tasks' => $tasks,
            'requests' => $requests,
            'employees' => User::whereIn('role', ['admin', 'crm'])->orderBy('name')->get(),
            'services' => Service::orderBy('category')->orderBy('name')->get(),
        ]);
    }

    public function quality(): View
    {
        return view('crm.quality', [
            'reports' => UserReport::query()
                ->with(['order', 'reporter', 'reportedUser'])
                ->latest()
                ->limit(200)
                ->get(),
            'shiftReports' => ShiftReport::query()
                ->with(['order', 'caregiver', 'assignment.scheduleSlot'])
                ->latest()
                ->limit(200)
                ->get(),
        ]);
    }

    public function storeStandalonePerson(Request $request): RedirectResponse
    {
        $data = $this->validatePerson($request);
        [$user, $generatedPassword] = $this->createPerson($data);

        $message = 'Карточка ' . ($user->isCaregiver() ? 'сиделки' : 'клиента') . ' создана.';
        if (! empty($data['email']) && empty($data['password'])) {
            $message .= ' Временный пароль: ' . $generatedPassword;
        }

        return redirect()->route('crm.people.show', $user)->with('status', $message);
    }

    public function storePersonInteraction(Request $request, User $person): RedirectResponse
    {
        abort_unless(in_array($person->role, ['client', 'caregiver'], true), 404);

        $data = $request->validate([
            'type' => ['required', Rule::in(['call_in', 'call_out', 'note', 'sms', 'messenger', 'meeting'])],
            'result' => ['nullable', 'string', 'max:64'],
            'comment' => ['required', 'string', 'max:5000'],
            'happened_at' => ['nullable', 'date'],
        ]);

        CrmInteraction::create([
            'person_user_id' => $person->id,
            'employee_id' => $request->user()->id,
            'type' => $data['type'],
            'result' => $data['result'] ?? null,
            'comment' => $data['comment'],
            'happened_at' => $data['happened_at'] ?? now(),
        ]);

        return back()->with('status', 'Контакт с человеком записан.');
    }

    public function storePersonTask(Request $request, User $person): RedirectResponse
    {
        abort_unless(in_array($person->role, ['client', 'caregiver'], true), 404);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'assigned_to_id' => ['nullable', 'integer', 'exists:users,id'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'due_at' => ['nullable', 'date'],
        ]);

        CrmTask::create([
            ...$data,
            'person_user_id' => $person->id,
            'assigned_to_id' => $data['assigned_to_id'] ?? $request->user()->id,
            'created_by_id' => $request->user()->id,
            'status' => 'open',
        ]);

        return back()->with('status', 'Задача по человеку создана.');
    }

    public function storeRequest(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'caller_name' => ['required', 'string', 'max:255'],
            'caller_phone' => ['required', 'string', 'max:64'],
            'caller_email' => ['nullable', 'email', 'max:255'],
            'patient_name' => ['nullable', 'string', 'max:255'],
            'patient_age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'city' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'service_text' => ['required', 'string', 'max:5000'],
            'schedule_text' => ['nullable', 'string', 'max:3000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'budget_per_hour' => ['nullable', 'integer', 'min:0'],
            'lead_cost' => ['nullable', 'integer', 'min:0'],
            'priority' => ['required', Rule::in(array_keys(CrmRequest::PRIORITY_LABELS))],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'next_contact_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $crmRequest = DB::transaction(function () use ($data, $request) {
            $responsibleId = $data['responsible_user_id'] ?? $this->resolveResponsibleUserId($request->user()->id);

            $crmRequest = CrmRequest::create([
                ...$data,
                'public_id' => (string) Str::uuid(),
                'source' => 'phone',
                'status' => 'new',
                'responsible_user_id' => $responsibleId,
                'created_by_id' => $request->user()->id,
                'last_contact_at' => now(),
            ]);

            $crmRequest->interactions()->create([
                'employee_id' => $request->user()->id,
                'type' => 'call_in',
                'result' => 'request_created',
                'comment' => 'Принят входящий звонок и создана CRM-заявка.',
                'happened_at' => now(),
            ]);

            if ($responsibleId !== $request->user()->id) {
                $responsible = User::find($responsibleId);
                if ($responsible) {
                    $crmRequest->interactions()->create([
                        'employee_id' => $request->user()->id,
                        'type' => 'assignment',
                        'result' => 'auto_assigned',
                        'comment' => 'Заявка автоматически распределена на менеджера ' . $responsible->name . '.',
                        'happened_at' => now(),
                    ]);
                }
            }

            $this->syncFollowUpTask($crmRequest, $responsibleId, $data['next_contact_at'] ?? null);

            return $crmRequest;
        });

        return redirect()->route('crm.requests.show', $crmRequest)
            ->with('status', 'Телефонная заявка создана.');
    }

    public function showRequest(CrmRequest $crmRequest): View
    {
        $crmRequest->load([
            'responsible',
            'clientUser',
            'clientUser.payments',
            'clientUser.refunds',
            'caregiverUser.caregiverProfile.availabilitySlots',
            'caregiverUser.documents',
            'order',
            'order.payments',
            'order.payouts',
            'order.refunds',
            'order.scheduleSlots',
            'order.caregiverAssignments',
            'interactions.employee',
            'tasks.assignedTo',
        ]);

        $caregivers = User::query()
            ->where('role', 'caregiver')
            ->with(['caregiverProfile.availabilitySlots', 'caregiverProfile.services', 'documents'])
            ->when($crmRequest->city, fn ($query, $city) => $query->where('city', $city))
            ->orderByDesc('is_verified')
            ->orderByDesc('rating')
            ->limit(50)
            ->get();

        return view('crm.show', [
            'crmRequest' => $crmRequest,
            'employees' => User::whereIn('role', ['admin', 'crm'])->orderBy('name')->get(),
            'clients' => User::where('role', 'client')->orderBy('name')->limit(200)->get(),
            'caregivers' => $caregivers,
            'matchedCaregivers' => $this->buildCaregiverMatches($crmRequest, $caregivers),
            'financialSummary' => $this->buildFinancialSummary($crmRequest),
            'longOrders' => $this->buildLongOrders($crmRequest),
            'incidents' => $this->buildIncidentFeed($crmRequest),
            'messageTemplates' => $this->messageTemplates(),
            'operatorScript' => $this->operatorScript(),
            'statusLabels' => CrmRequest::STATUS_LABELS,
            'priorityLabels' => CrmRequest::PRIORITY_LABELS,
        ]);
    }

    public function updateRequest(Request $request, CrmRequest $crmRequest): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(CrmRequest::STATUS_LABELS))],
            'priority' => ['required', Rule::in(array_keys(CrmRequest::PRIORITY_LABELS))],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'client_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'caregiver_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'caller_name' => ['required', 'string', 'max:255'],
            'caller_phone' => ['required', 'string', 'max:64'],
            'caller_email' => ['nullable', 'email', 'max:255'],
            'patient_name' => ['nullable', 'string', 'max:255'],
            'patient_age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'city' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'service_text' => ['required', 'string', 'max:5000'],
            'schedule_text' => ['nullable', 'string', 'max:3000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'budget_per_hour' => ['nullable', 'integer', 'min:0'],
            'lead_cost' => ['nullable', 'integer', 'min:0'],
            'next_contact_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        if (! empty($data['client_user_id'])) {
            abort_unless(User::whereKey($data['client_user_id'])->where('role', 'client')->exists(), 422);
        }

        if (! empty($data['caregiver_user_id'])) {
            abort_unless(User::whereKey($data['caregiver_user_id'])->where('role', 'caregiver')->exists(), 422);
        }

        $before = $crmRequest->only([
            'status',
            'priority',
            'responsible_user_id',
            'client_user_id',
            'caregiver_user_id',
            'city',
            'address',
            'budget_per_hour',
            'starts_at',
            'ends_at',
            'next_contact_at',
        ]);

        $oldStatus = $crmRequest->status;
        $isClosed = in_array($data['status'], ['completed', 'cancelled'], true);

        $crmRequest->update([
            ...$data,
            'closed_at' => $isClosed ? ($crmRequest->closed_at ?: now()) : null,
        ]);

        $this->syncFollowUpTask($crmRequest, (int) ($data['responsible_user_id'] ?? $request->user()->id), $data['next_contact_at'] ?? null);

        if ($oldStatus !== $data['status']) {
            $crmRequest->interactions()->create([
                'employee_id' => $request->user()->id,
                'type' => 'status_change',
                'result' => $data['status'],
                'comment' => 'Статус изменен: ' . (CrmRequest::STATUS_LABELS[$oldStatus] ?? $oldStatus) . ' → ' . $crmRequest->status_label,
                'happened_at' => now(),
            ]);
        }

        foreach ($this->describeRequestChanges($crmRequest, $before) as $changeLine) {
            $crmRequest->interactions()->create([
                'employee_id' => $request->user()->id,
                'type' => 'update',
                'result' => 'field_changed',
                'comment' => $changeLine,
                'happened_at' => now(),
            ]);
        }

        return back()->with('status', 'Заявка обновлена.');
    }

    public function updateRequestStatus(Request $request, CrmRequest $crmRequest): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(CrmRequest::STATUS_LABELS))],
        ]);

        $oldStatus = $crmRequest->status;
        $crmRequest->update([
            'status' => $data['status'],
            'closed_at' => in_array($data['status'], ['completed', 'cancelled'], true) ? ($crmRequest->closed_at ?: now()) : null,
        ]);

        if ($oldStatus !== $data['status']) {
            $crmRequest->interactions()->create([
                'employee_id' => $request->user()->id,
                'type' => 'status_change',
                'result' => $data['status'],
                'comment' => 'Статус изменен через kanban: ' . (CrmRequest::STATUS_LABELS[$oldStatus] ?? $oldStatus) . ' → ' . $crmRequest->status_label,
                'happened_at' => now(),
            ]);
        }

        return back()->with('status', 'Этап заявки обновлен.');
    }

    public function selectCaregiver(Request $request, CrmRequest $crmRequest, User $caregiver): RedirectResponse
    {
        abort_unless($caregiver->isCaregiver() && $caregiver->caregiverProfile, 404);

        $match = $this->buildCaregiverMatch($crmRequest, $caregiver);

        $crmRequest->update([
            'caregiver_user_id' => $caregiver->id,
            'status' => in_array($crmRequest->status, ['new', 'qualification', 'searching'], true)
                ? 'caregiver_found'
                : $crmRequest->status,
        ]);

        $crmRequest->interactions()->create([
            'employee_id' => $request->user()->id,
            'person_user_id' => $caregiver->id,
            'type' => 'match',
            'result' => 'caregiver_selected',
            'comment' => 'Выбрана сиделка ' . $caregiver->name . '. Причины: ' . implode('; ', $match['reasons']),
            'happened_at' => now(),
        ]);

        return back()->with('status', 'Сиделка выбрана и привязана к CRM-заявке.');
    }

    public function longOrders(Request $request): View
    {
        $orders = Order::query()
            ->with([
                'client',
                'scheduleSlots',
                'caregiverAssignments.caregiver',
                'caregiverAssignments.scheduleSlot',
            ])
            ->withCount('scheduleSlots')
            ->latest()
            ->get()
            ->filter(fn (Order $order) => $order->allows_multiple_caregivers || $order->schedule_slots_count > 3)
            ->map(function (Order $order) {
                $openSlots = $order->scheduleSlots->filter(function ($slot) use ($order) {
                    return ! $order->caregiverAssignments->contains(fn (OrderCaregiverAssignment $assignment) => $assignment->order_schedule_slot_id === $slot->id && in_array($assignment->status, ['accepted', 'completed', 'completion_requested'], true));
                });

                $conflicts = $order->scheduleSlots->filter(function ($slot) use ($order) {
                    return $order->caregiverAssignments
                        ->where('order_schedule_slot_id', $slot->id)
                        ->whereIn('status', ['accepted', 'completed', 'completion_requested'])
                        ->count() > 1;
                });

                $order->open_slots_count = $openSlots->count();
                $order->conflicts_count = $conflicts->count();

                return $order;
            })
            ->values();

        $caregivers = User::query()
            ->where('role', 'caregiver')
            ->with('caregiverProfile')
            ->orderByDesc('rating')
            ->orderBy('name')
            ->get();

        return view('crm.long-orders', [
            'orders' => $orders,
            'caregivers' => $caregivers,
        ]);
    }

    public function replaceAssignmentCaregiver(Request $request, Order $order, OrderCaregiverAssignment $assignment): RedirectResponse
    {
        abort_unless($assignment->order_id === $order->id, 404);

        $data = $request->validate([
            'caregiver_id' => ['required', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $caregiver = User::query()->whereKey($data['caregiver_id'])->where('role', 'caregiver')->firstOrFail();

        DB::transaction(function () use ($request, $assignment, $caregiver, $data) {
            $oldCaregiverName = $assignment->caregiver?->name ?? 'Сиделка';

            $assignment->update([
                'caregiver_id' => $caregiver->id,
                'status' => 'invited',
                'confirmed_at' => null,
                'completion_requested_at' => null,
                'client_confirmed_at' => null,
                'completed_at' => null,
                'payout_generated_at' => null,
                'notes' => trim(($assignment->notes ? $assignment->notes . "\n" : '') . 'Замена через CRM: ' . ($data['notes'] ?? 'без комментария')),
            ]);

            $assignment->order->conversations()->firstOrCreate(
                [
                    'client_id' => $assignment->order->client_id,
                    'caregiver_id' => $caregiver->id,
                ],
                [
                    'status' => 'requested',
                ]
            );

            CrmInteraction::create([
                'employee_id' => $request->user()->id,
                'type' => 'replacement',
                'result' => 'caregiver_replaced',
                'comment' => 'Замена по смене: ' . $oldCaregiverName . ' → ' . $caregiver->name . '. ' . ($data['notes'] ?? ''),
                'happened_at' => now(),
            ]);
        });

        return back()->with('status', 'Сиделка по выбранной смене заменена без пересоздания длинного заказа.');
    }

    public function storeInteraction(Request $request, CrmRequest $crmRequest): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['call_in', 'call_out', 'note', 'sms', 'messenger', 'meeting'])],
            'result' => ['nullable', 'string', 'max:64'],
            'comment' => ['required', 'string', 'max:5000'],
            'happened_at' => ['nullable', 'date'],
            'next_contact_at' => ['nullable', 'date'],
        ]);

        $crmRequest->interactions()->create([
            'employee_id' => $request->user()->id,
            'type' => $data['type'],
            'result' => $data['result'] ?? null,
            'comment' => $data['comment'],
            'happened_at' => $data['happened_at'] ?? now(),
        ]);

        $crmRequest->update([
            'last_contact_at' => $data['happened_at'] ?? now(),
            'next_contact_at' => $data['next_contact_at'] ?? $crmRequest->next_contact_at,
        ]);

        $this->syncFollowUpTask($crmRequest, $crmRequest->responsible_user_id ?: $request->user()->id, $data['next_contact_at'] ?? null);

        return back()->with('status', 'Контакт записан в историю.');
    }

    public function storeTask(Request $request, CrmRequest $crmRequest): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'assigned_to_id' => ['nullable', 'integer', 'exists:users,id'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'due_at' => ['nullable', 'date'],
        ]);

        $crmRequest->tasks()->create([
            ...$data,
            'assigned_to_id' => $data['assigned_to_id'] ?? $request->user()->id,
            'created_by_id' => $request->user()->id,
            'status' => 'open',
        ]);

        return back()->with('status', 'Задача создана.');
    }

    public function completeTask(CrmTask $crmTask): RedirectResponse
    {
        $crmTask->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return back()->with('status', 'Задача выполнена.');
    }

    public function storePerson(Request $request, CrmRequest $crmRequest): RedirectResponse
    {
        $data = $this->validatePerson($request);
        [$user, $generatedPassword] = $this->createPerson($data);

        $crmRequest->update([
            $data['role'] === 'client' ? 'client_user_id' : 'caregiver_user_id' => $user->id,
        ]);

        $crmRequest->interactions()->create([
            'person_user_id' => $user->id,
            'employee_id' => $request->user()->id,
            'type' => 'note',
            'result' => $data['role'] . '_created',
            'comment' => 'Создана карточка ' . ($data['role'] === 'client' ? 'клиента' : 'сиделки') . ': ' . $user->name . '.',
            'happened_at' => now(),
        ]);

        $message = 'Карточка пользователя создана и связана с заявкой.';
        if (! empty($data['email']) && empty($data['password'])) {
            $message .= ' Временный пароль: ' . $generatedPassword;
        }

        return back()->with('status', $message);
    }

    public function storeAvailability(Request $request, User $caregiver): RedirectResponse
    {
        abort_unless($caregiver->isCaregiver() && $caregiver->caregiverProfile, 404);

        $data = $request->validate([
            'weekday' => ['nullable', 'integer', 'between:1,7'],
            'specific_date' => ['nullable', 'date'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'is_recurring' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if (empty($data['weekday']) && empty($data['specific_date'])) {
            throw ValidationException::withMessages([
                'specific_date' => 'Укажите конкретную дату или день недели.',
            ]);
        }

        $caregiver->caregiverProfile->availabilitySlots()->create([
            ...$data,
            'is_recurring' => (bool) ($data['is_recurring'] ?? false),
        ]);

        return back()->with('status', 'Доступность сиделки добавлена.');
    }

    public function updateCaregiverProfile(Request $request, User $caregiver): RedirectResponse
    {
        abort_unless($caregiver->isCaregiver() && $caregiver->caregiverProfile, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($caregiver->id)],
            'city' => ['nullable', 'string', 'max:255'],
            'about' => ['nullable', 'string'],
            'experience_years' => ['required', 'integer', 'min:0', 'max:80'],
            'hourly_rate_from' => ['required', 'integer', 'min:0'],
            'shift_rate_from' => ['nullable', 'integer', 'min:0'],
            'employment_format' => ['required', 'string', 'max:255'],
            'education' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'medical_skills' => ['nullable', 'string'],
            'household_skills' => ['nullable', 'string'],
            'ready_for_night' => ['nullable', 'boolean'],
            'ready_for_live_in' => ['nullable', 'boolean'],
            'documents_verified' => ['nullable', 'boolean'],
            'is_verified' => ['nullable', 'boolean'],
            'can_service_ids' => ['array'],
            'can_service_ids.*' => ['integer', 'exists:services,id'],
            'cannot_service_ids' => ['array'],
            'cannot_service_ids.*' => ['integer', 'exists:services,id'],
        ]);

        DB::transaction(function () use ($caregiver, $data) {
            $caregiver->update([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?: $caregiver->email,
                'city' => $data['city'] ?? null,
                'about' => $data['about'] ?? null,
                'is_verified' => (bool) ($data['is_verified'] ?? false),
            ]);

            $caregiver->caregiverProfile->update([
                'experience_years' => $data['experience_years'],
                'hourly_rate_from' => $data['hourly_rate_from'],
                'shift_rate_from' => $data['shift_rate_from'] ?? null,
                'employment_format' => $data['employment_format'],
                'education' => $data['education'] ?? null,
                'bio' => $data['bio'] ?? null,
                'medical_skills' => $data['medical_skills'] ?? null,
                'household_skills' => $data['household_skills'] ?? null,
                'ready_for_night' => (bool) ($data['ready_for_night'] ?? false),
                'ready_for_live_in' => (bool) ($data['ready_for_live_in'] ?? false),
                'documents_verified' => (bool) ($data['documents_verified'] ?? false),
            ]);

            $this->syncCaregiverServices($caregiver->caregiverProfile, $data['can_service_ids'] ?? [], $data['cannot_service_ids'] ?? []);
        });

        return back()->with('status', 'Карточка сиделки обновлена сотрудником CRM.');
    }

    public function destroyAvailability(AvailabilitySlot $availabilitySlot): RedirectResponse
    {
        $availabilitySlot->delete();

        return back()->with('status', 'Интервал доступности удален.');
    }

    public function convertToOrder(Request $request, CrmRequest $crmRequest): RedirectResponse
    {
        abort_if($crmRequest->order_id, 422, 'Заказ по этой заявке уже создан.');

        $data = $request->validate([
            'client_user_id' => ['required', 'integer', 'exists:users,id'],
            'caregiver_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'hourly_budget' => ['required', 'integer', 'min:0'],
        ]);

        $client = User::whereKey($data['client_user_id'])->where('role', 'client')->firstOrFail();
        $caregiver = ! empty($data['caregiver_user_id'])
            ? User::whereKey($data['caregiver_user_id'])->where('role', 'caregiver')->firstOrFail()
            : null;

        $order = DB::transaction(function () use ($crmRequest, $data, $client, $caregiver, $request) {
            $order = Order::create([
                'client_id' => $client->id,
                'caregiver_id' => $caregiver?->id,
                'title' => 'Телефонная заявка ' . Str::upper(Str::substr($crmRequest->public_id, 0, 8)),
                'description' => $crmRequest->service_text,
                'city' => $crmRequest->city ?: $client->city,
                'address' => $crmRequest->address,
                'schedule_type' => 'hourly',
                'status' => $caregiver ? 'matched' : 'published',
                'payment_status' => 'pending',
                'is_urgent' => $crmRequest->priority === 'urgent',
                'needs_today' => now()->isSameDay($data['starts_at']),
                'allows_multiple_caregivers' => false,
                'hourly_budget' => $data['hourly_budget'],
                'patient_age' => $crmRequest->patient_age,
                'patient_name' => $crmRequest->patient_name,
                'special_requirements' => $crmRequest->notes,
                'custom_services' => [$crmRequest->service_text],
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
            ]);

            $start = Carbon::parse($data['starts_at']);
            $end = Carbon::parse($data['ends_at']);

            $slot = $order->scheduleSlots()->create([
                'scheduled_date' => $start->toDateString(),
                'starts_at' => $start->format('H:i:s'),
                'ends_at' => $end->format('H:i:s'),
                'label' => 'Создано CRM по телефонной заявке',
            ]);

            if ($caregiver) {
                OrderCaregiverAssignment::create([
                    'order_id' => $order->id,
                    'order_schedule_slot_id' => $slot->id,
                    'caregiver_id' => $caregiver->id,
                    'status' => 'invited',
                    'notes' => 'Назначено сотрудником CRM.',
                ]);

                Conversation::create([
                    'order_id' => $order->id,
                    'client_id' => $client->id,
                    'caregiver_id' => $caregiver->id,
                    'status' => 'requested',
                ]);
            }

            $crmRequest->update([
                'client_user_id' => $client->id,
                'caregiver_user_id' => $caregiver?->id,
                'order_id' => $order->id,
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'budget_per_hour' => $data['hourly_budget'],
                'status' => 'booked',
            ]);

            $crmRequest->interactions()->create([
                'employee_id' => $request->user()->id,
                'type' => 'status_change',
                'result' => 'order_created',
                'comment' => 'Создан заказ #' . $order->id . ' из CRM-заявки.',
                'happened_at' => now(),
            ]);

            $crmRequest->interactions()->create([
                'employee_id' => $request->user()->id,
                'type' => 'finance',
                'result' => 'price_locked',
                'comment' => 'Зафиксирована цена заказа: ' . number_format((float) $data['hourly_budget'], 0, ',', ' ') . ' ₽/час.',
                'happened_at' => now(),
            ]);

            return $order;
        });

        return redirect()->route('crm.requests.show', $crmRequest)
            ->with('status', 'Заказ #' . $order->id . ' создан и связан с CRM-заявкой.');
    }

    private function validatePerson(Request $request): array
    {
        return $request->validate([
            'role' => ['required', Rule::in(['client', 'caregiver'])],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'city' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'experience_years' => ['nullable', 'integer', 'min:0', 'max:80'],
            'hourly_rate_from' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function createPerson(array $data): array
    {
        $email = $data['email'] ?: 'crm-' . Str::uuid() . '@sidelka.local';
        $generatedPassword = $data['password'] ?? Str::password(16);

        $user = DB::transaction(function () use ($data, $email, $generatedPassword) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $email,
                'email_verified_at' => now(),
                'role' => $data['role'],
                'phone' => $data['phone'],
                'city' => $data['city'] ?? null,
                'is_verified' => false,
                'password' => Hash::make($generatedPassword),
            ]);

            if ($data['role'] === 'caregiver') {
                CaregiverProfile::create([
                    'user_id' => $user->id,
                    'experience_years' => $data['experience_years'] ?? 0,
                    'hourly_rate_from' => $data['hourly_rate_from'] ?? 0,
                    'employment_format' => 'hourly',
                ]);
            }

            return $user;
        });

        return [$user, $generatedPassword];
    }

    private function syncCaregiverServices(CaregiverProfile $profile, array $canIds, array $cannotIds): void
    {
        $payload = [];

        foreach ($canIds as $serviceId) {
            $payload[$serviceId] = ['capability_status' => 'can_do'];
        }

        foreach ($cannotIds as $serviceId) {
            if (! isset($payload[$serviceId])) {
                $payload[$serviceId] = ['capability_status' => 'cannot_do'];
            }
        }

        $profile->services()->sync($payload);
    }

    private function resolveResponsibleUserId(int $fallbackUserId): int
    {
        $candidates = User::query()
            ->whereIn('role', ['admin', 'crm'])
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user) => ($user->isAdmin() || $user->hasStaffPermission('crm.requests.manage')) && ($user->role !== 'crm' || $user->staff_active));

        if ($candidates->isEmpty()) {
            return $fallbackUserId;
        }

        return $candidates
            ->sortBy(fn (User $user) => CrmRequest::where('responsible_user_id', $user->id)->whereNotIn('status', ['completed', 'cancelled'])->count())
            ->first()
            ?->id ?? $fallbackUserId;
    }

    private function syncFollowUpTask(CrmRequest $crmRequest, int $assignedToId, mixed $nextContactAt): void
    {
        if (empty($nextContactAt)) {
            return;
        }

        CrmTask::query()->updateOrCreate(
            [
                'crm_request_id' => $crmRequest->id,
                'dedup_key' => 'next-contact',
            ],
            [
                'person_user_id' => $crmRequest->client_user_id,
                'assigned_to_id' => $assignedToId,
                'created_by_id' => $crmRequest->created_by_id,
                'title' => 'Связаться по заявке ' . Str::upper(Str::substr($crmRequest->public_id, 0, 8)),
                'description' => 'Плановый повторный контакт по CRM-заявке.',
                'category' => 'follow_up',
                'source_type' => CrmRequest::class,
                'source_id' => $crmRequest->id,
                'status' => 'open',
                'priority' => $crmRequest->priority === 'urgent' ? 'urgent' : 'normal',
                'due_at' => $nextContactAt,
            ]
        );
    }

    private function describeRequestChanges(CrmRequest $crmRequest, array $before): array
    {
        $after = $crmRequest->fresh()->only(array_keys($before));
        $lines = [];

        $labels = [
            'priority' => 'Приоритет',
            'responsible_user_id' => 'Ответственный',
            'client_user_id' => 'Клиент',
            'caregiver_user_id' => 'Сиделка',
            'city' => 'Город',
            'address' => 'Адрес',
            'budget_per_hour' => 'Цена за час',
            'starts_at' => 'Начало',
            'ends_at' => 'Окончание',
            'next_contact_at' => 'Следующий контакт',
        ];

        foreach ($labels as $field => $label) {
            $beforeValue = $before[$field] ?? null;
            $afterValue = $after[$field] ?? null;
            if ((string) $beforeValue === (string) $afterValue) {
                continue;
            }

            $lines[] = $label . ': ' . $this->formatCrmValue($field, $beforeValue) . ' → ' . $this->formatCrmValue($field, $afterValue);
        }

        return $lines;
    }

    private function formatCrmValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'не указано';
        }

        return match ($field) {
            'responsible_user_id', 'client_user_id', 'caregiver_user_id' => User::find($value)?->name ?? 'не найдено',
            'priority' => CrmRequest::PRIORITY_LABELS[$value] ?? (string) $value,
            'starts_at', 'ends_at', 'next_contact_at' => Carbon::parse($value)->format('d.m.Y H:i'),
            'budget_per_hour' => number_format((float) $value, 0, ',', ' ') . ' ₽',
            default => (string) $value,
        };
    }

    private function buildCaregiverMatches(CrmRequest $crmRequest, Collection $caregivers): Collection
    {
        return $caregivers
            ->map(fn (User $caregiver) => $this->buildCaregiverMatch($crmRequest, $caregiver))
            ->sortByDesc('score')
            ->values();
    }

    private function buildCaregiverMatch(CrmRequest $crmRequest, User $caregiver): array
    {
        $score = 0;
        $reasons = [];
        $warnings = [];
        $profile = $caregiver->caregiverProfile;
        $documents = $caregiver->documents ?? collect();
        $serviceText = Str::lower((string) $crmRequest->service_text);
        $needsMedical = Str::contains($serviceText, ['укол', 'инъек', 'капель', 'перевяз', 'катетер', 'мед']);

        if ($crmRequest->city && Str::lower((string) $crmRequest->city) === Str::lower((string) $caregiver->city)) {
            $score += 25;
            $reasons[] = 'совпадает город';
        } else {
            $warnings[] = 'нужно уточнить город или выезд';
        }

        if ($profile && $crmRequest->budget_per_hour && $profile->hourly_rate_from <= $crmRequest->budget_per_hour) {
            $score += 20;
            $reasons[] = 'укладывается в бюджет';
        } elseif ($profile && $crmRequest->budget_per_hour) {
            $warnings[] = 'ставка выше бюджета';
        }

        if ($needsMedical && ! empty($profile?->education)) {
            $score += 15;
            $reasons[] = 'есть медподготовка';
        } elseif ($needsMedical) {
            $warnings[] = 'медподготовка не подтверждена';
        }

        if ($profile?->availabilitySlots?->isNotEmpty()) {
            $score += 10;
            $reasons[] = 'есть свободные слоты';
        } else {
            $warnings[] = 'график не заполнен';
        }

        $rating = (float) $caregiver->rating;
        if ($rating > 0) {
            $score += min(20, (int) round($rating * 4));
            $reasons[] = 'рейтинг ' . number_format($rating, 1, ',', ' ');
        }

        $expiredDocuments = $documents->filter(fn (UserDocument $document) => $document->isExpired())->count();
        $blockingDocuments = $documents->filter(fn (UserDocument $document) => $document->blocksCaregiver())->count();

        if ($blockingDocuments === 0) {
            $score += 10;
            $reasons[] = 'нет блокирующих документов';
        } else {
            $warnings[] = 'есть блок по документам';
        }

        $complaints = UserReport::where('reported_user_id', $caregiver->id)->count();
        $completedAssignments = OrderCaregiverAssignment::where('caregiver_id', $caregiver->id)->where('status', 'completed')->count();
        $declinedAssignments = OrderCaregiverAssignment::where('caregiver_id', $caregiver->id)->where('status', 'declined')->count();
        $confirmationRate = ($completedAssignments + $declinedAssignments) > 0
            ? round(($completedAssignments / max(1, $completedAssignments + $declinedAssignments)) * 100)
            : null;

        if ($confirmationRate !== null) {
            $reasons[] = 'подтверждение смен ' . $confirmationRate . '%';
        }
        if ($complaints > 0) {
            $warnings[] = 'жалоб: ' . $complaints;
        }

        return [
            'caregiver' => $caregiver,
            'score' => $score,
            'reasons' => $reasons,
            'warnings' => $warnings,
            'documents' => [
                'expired' => $expiredDocuments,
                'blocking' => $blockingDocuments,
                'total' => $documents->count(),
            ],
            'quality' => [
                'complaints' => $complaints,
                'confirmation_rate' => $confirmationRate,
                'completed_assignments' => $completedAssignments,
                'declined_assignments' => $declinedAssignments,
                'reviews' => Review::where('subject_id', $caregiver->id)->count(),
            ],
        ];
    }

    private function buildFinancialSummary(CrmRequest $crmRequest): array
    {
        $client = $crmRequest->clientUser;
        $order = $crmRequest->order;

        return [
            'client_balance' => $client?->wallet_balance ?? 0,
            'client_payments' => $client ? Payment::where('client_id', $client->id)->sum('amount') : 0,
            'client_refunds' => $client ? Refund::where('client_id', $client->id)->sum('amount') : 0,
            'order_total' => $order?->total_invoice_amount ?? 0,
            'frozen' => $order ? Payment::where('order_id', $order->id)->whereNotNull('held_at')->sum('amount') : 0,
            'payouts' => $order ? Payout::where('order_id', $order->id)->sum('amount') : 0,
            'refunds' => $order ? Refund::where('order_id', $order->id)->sum('amount') : 0,
            'debt' => $order ? max(0, ($order->total_invoice_amount ?? 0) - ($client?->wallet_balance ?? 0)) : 0,
        ];
    }

    private function buildLongOrders(CrmRequest $crmRequest): Collection
    {
        if (! $crmRequest->client_user_id) {
            return collect();
        }

        return Order::query()
            ->with(['caregiverAssignments.caregiver', 'scheduleSlots'])
            ->withCount('scheduleSlots')
            ->where('client_id', $crmRequest->client_user_id)
            ->latest()
            ->get()
            ->filter(fn (Order $order) => $order->allows_multiple_caregivers || $order->schedule_slots_count > 3)
            ->take(8)
            ->values();
    }

    private function buildIncidentFeed(CrmRequest $crmRequest): Collection
    {
        if (! $crmRequest->order_id) {
            return collect();
        }

        return SafetyIncident::query()
            ->with(['reportedBy', 'assignedTo'])
            ->where('order_id', $crmRequest->order_id)
            ->latest('occurred_at')
            ->limit(20)
            ->get();
    }

    private function operatorScript(): array
    {
        return [
            'Уточнить кто звонит и кем приходится пациенту.',
            'Понять срочность: сегодня, завтра или плановый старт.',
            'Уточнить адрес, город, формат смен и бюджет.',
            'Собрать диагнозы, ограничения, лекарства и риски.',
            'Проверить, нужна ли медподготовка и какие манипуляции требуются.',
            'Сразу назначить следующий контакт и ответственного менеджера.',
        ];
    }

    private function messageTemplates(): array
    {
        return $this->platformSettings->crmPayload();
    }
}
