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
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CrmController extends Controller
{
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
            ],
            'statusLabels' => CrmRequest::STATUS_LABELS,
            'priorityLabels' => CrmRequest::PRIORITY_LABELS,
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
            'priority' => ['required', Rule::in(array_keys(CrmRequest::PRIORITY_LABELS))],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'next_contact_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $crmRequest = DB::transaction(function () use ($data, $request) {
            $crmRequest = CrmRequest::create([
                ...$data,
                'public_id' => (string) Str::uuid(),
                'source' => 'phone',
                'status' => 'new',
                'responsible_user_id' => $data['responsible_user_id'] ?? $request->user()->id,
                'created_by_id' => $request->user()->id,
                'last_contact_at' => now(),
            ]);

            $crmRequest->interactions()->create([
                'employee_id' => $request->user()->id,
                'type' => 'call_in',
                'result' => 'request_created',
                'comment' => 'Принят входящий звонок и создана заявка.',
                'happened_at' => now(),
            ]);

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
            'caregiverUser.caregiverProfile.availabilitySlots',
            'order',
            'interactions.employee',
            'tasks.assignedTo',
        ]);

        $caregivers = User::query()
            ->where('role', 'caregiver')
            ->with(['caregiverProfile.availabilitySlots', 'caregiverProfile.services'])
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
            'next_contact_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        if (! empty($data['client_user_id'])) {
            abort_unless(User::whereKey($data['client_user_id'])->where('role', 'client')->exists(), 422);
        }

        if (! empty($data['caregiver_user_id'])) {
            abort_unless(User::whereKey($data['caregiver_user_id'])->where('role', 'caregiver')->exists(), 422);
        }

        $oldStatus = $crmRequest->status;
        $isClosed = in_array($data['status'], ['completed', 'cancelled'], true);

        $crmRequest->update([
            ...$data,
            'closed_at' => $isClosed ? ($crmRequest->closed_at ?: now()) : null,
        ]);

        if ($oldStatus !== $data['status']) {
            $crmRequest->interactions()->create([
                'employee_id' => $request->user()->id,
                'type' => 'status_change',
                'result' => $data['status'],
                'comment' => 'Статус изменён: ' . (CrmRequest::STATUS_LABELS[$oldStatus] ?? $oldStatus) . ' → ' . $crmRequest->status_label,
                'happened_at' => now(),
            ]);
        }

        return back()->with('status', 'Заявка обновлена.');
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

    public function destroyAvailability(AvailabilitySlot $availabilitySlot): RedirectResponse
    {
        $availabilitySlot->delete();

        return back()->with('status', 'Интервал доступности удалён.');
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
                'comment' => 'Создан заказ #' . $order->id . ' из телефонной заявки.',
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
}
