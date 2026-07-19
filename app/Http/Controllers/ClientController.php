<?php

namespace App\Http\Controllers;

use App\Models\AvailabilitySlot;
use App\Models\Order;
use App\Models\OrderTemplate;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClientController extends Controller
{
    public function myDashboard(Request $request)
    {
        return $this->dashboard($request->user());
    }

    public function dashboard(User $user)
    {
        abort_unless($user->isClient(), 404);

        $user->load([
            'familyMembers',
            'orderTemplates.services',
            'orderTemplates.scheduleSlots',
            'clientOrders.services',
            'clientOrders.scheduleSlots',
            'clientOrders.familyMember',
            'clientOrders.caregiver.caregiverProfile.services',
            'clientOrders.conversations.messages.sender',
            'receivedReviews.author',
        ]);

        $orders = $user->clientOrders
            ->sortByDesc('created_at')
            ->values()
            ->map(function (Order $order) {
                $matchedCaregivers = User::with(['caregiverProfile.services', 'caregiverProfile.availabilitySlots'])
                    ->where('role', 'caregiver')
                    ->where('city', $order->city)
                    ->whereHas('caregiverProfile', fn ($query) => $query->where('hourly_rate_from', '<=', $order->hourly_budget))
                    ->whereHas('caregiverProfile.services', fn ($query) => $query
                        ->whereIn('services.id', $order->services->pluck('id'))
                        ->where('caregiver_profile_service.capability_status', 'can_do'))
                    ->get()
                    ->filter(fn (User $caregiver) => $this->caregiverMatchesOrder($caregiver, $order))
                    ->map(function (User $caregiver) use ($order) {
                        $caregiver->matched_services = $caregiver->caregiverProfile->availableServices()
                            ->pluck('name')
                            ->intersect($order->services->pluck('name'))
                            ->values();

                        return $caregiver;
                    })
                    ->sortByDesc(fn (User $caregiver) => $caregiver->matched_services->count())
                    ->values();

                $order->matched_caregivers = $matchedCaregivers;

                return $order;
            });

        $reviews = $user->receivedReviews
            ->where('subject_role', 'client')
            ->sortByDesc('published_at')
            ->values();

        $stats = [
            'active_orders' => $orders->whereIn('status', ['published', 'matched', 'in_chat', 'in_progress'])->count(),
            'completed_orders' => $orders->where('status', 'completed')->count(),
            'escrow_amount' => $orders->where('payment_status', 'held')->sum(fn ($order) => $order->hourly_budget * max(1, $order->starts_at->diffInHours($order->ends_at))),
            'new_responses' => $orders->sum(fn ($order) => $order->matched_caregivers->count()),
        ];

        $services = Service::orderBy('category')->orderBy('name')->get();
        $templates = $user->orderTemplates->sortByDesc('created_at')->values();
        $familyMembers = $user->familyMembers->sortBy('name')->values();

        return view('dashboards.client', compact('user', 'orders', 'reviews', 'stats', 'services', 'templates', 'familyMembers'));
    }

    public function storeOrder(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isClient(), 404);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'city' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'schedule_type' => ['required', Rule::in(['hourly', 'daily', 'night', 'calendar'])],
            'recurrence_label' => ['nullable', 'string', 'max:255'],
            'hourly_budget' => ['required', 'integer', 'min:0'],
            'patient_age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'patient_name' => ['nullable', 'string', 'max:255'],
            'special_requirements' => ['nullable', 'string'],
            'custom_service_lines' => ['nullable', 'string'],
            'is_urgent' => ['nullable', 'boolean'],
            'needs_today' => ['nullable', 'boolean'],
            'family_member_id' => ['nullable', 'integer', 'exists:client_family_members,id'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'service_ids' => ['array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'calendar_slots_json' => ['nullable', 'string'],
        ]);

        $scheduleSlots = $this->parseCalendarSlots($data['calendar_slots_json'] ?? null);
        $timeRange = $this->resolveTimeRange($scheduleSlots, $data['starts_at'] ?? null, $data['ends_at'] ?? null);

        $order = DB::transaction(function () use ($data, $user, $scheduleSlots, $timeRange) {
            $order = Order::create([
                'client_id' => $user->id,
                'created_by_family_member_id' => $data['family_member_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'],
                'city' => $data['city'],
                'address' => $data['address'] ?? null,
                'schedule_type' => $data['schedule_type'],
                'recurrence_label' => $data['recurrence_label'] ?? null,
                'status' => 'published',
                'payment_status' => 'pending',
                'is_urgent' => (bool) ($data['is_urgent'] ?? false),
                'needs_today' => (bool) ($data['needs_today'] ?? false),
                'hourly_budget' => $data['hourly_budget'],
                'patient_age' => $data['patient_age'] ?? null,
                'patient_name' => $data['patient_name'] ?? null,
                'special_requirements' => $data['special_requirements'] ?? null,
                'custom_services' => $this->parseCustomServices($data['custom_service_lines'] ?? ''),
                'starts_at' => $timeRange['starts_at'],
                'ends_at' => $timeRange['ends_at'],
            ]);

            $order->services()->sync($data['service_ids'] ?? []);
            $this->syncScheduleSlots($order, $scheduleSlots);

            return $order;
        });

        return redirect()->route('client.dashboard')->with('status', 'Заявка создана.');
    }

    public function storeFamilyMember(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isClient(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'relationship' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'can_create_orders' => ['nullable', 'boolean'],
            'can_view_chats' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $user->familyMembers()->create([
            'name' => $data['name'],
            'relationship' => $data['relationship'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'can_create_orders' => (bool) ($data['can_create_orders'] ?? false),
            'can_view_chats' => (bool) ($data['can_view_chats'] ?? false),
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('client.dashboard')->with('status', 'Родственник добавлен.');
    }

    public function storeTemplate(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isClient(), 404);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'city' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'schedule_type' => ['required', Rule::in(['hourly', 'daily', 'night', 'calendar'])],
            'recurrence_label' => ['nullable', 'string', 'max:255'],
            'hourly_budget' => ['required', 'integer', 'min:0'],
            'patient_age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'patient_name' => ['nullable', 'string', 'max:255'],
            'special_requirements' => ['nullable', 'string'],
            'custom_service_lines' => ['nullable', 'string'],
            'is_urgent' => ['nullable', 'boolean'],
            'service_ids' => ['array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'calendar_slots_json' => ['nullable', 'string'],
        ]);

        $scheduleSlots = $this->parseCalendarSlots($data['calendar_slots_json'] ?? null);

        $template = $user->orderTemplates()->create([
            'title' => $data['title'],
            'description' => $data['description'],
            'city' => $data['city'],
            'address' => $data['address'] ?? null,
            'schedule_type' => $data['schedule_type'],
            'recurrence_label' => $data['recurrence_label'] ?? null,
            'hourly_budget' => $data['hourly_budget'],
            'patient_age' => $data['patient_age'] ?? null,
            'patient_name' => $data['patient_name'] ?? null,
            'special_requirements' => $data['special_requirements'] ?? null,
            'custom_services' => $this->parseCustomServices($data['custom_service_lines'] ?? ''),
            'is_urgent' => (bool) ($data['is_urgent'] ?? false),
        ]);

        $template->services()->sync($data['service_ids'] ?? []);
        $this->syncScheduleSlots($template, $scheduleSlots);

        return redirect()->route('client.dashboard')->with('status', 'Шаблон заказа сохранен.');
    }

    private function parseCustomServices(string $lines): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $lines))
            ->map(fn ($line) => trim((string) $line))
            ->filter()
            ->values()
            ->all();
    }

    private function parseCalendarSlots(?string $calendarSlotsJson): array
    {
        return collect(json_decode($calendarSlotsJson ?: '[]', true))
            ->map(function ($slot) {
                $start = isset($slot['start']) ? Carbon::parse($slot['start']) : null;
                $end = isset($slot['end']) ? Carbon::parse($slot['end']) : null;

                if (! $start || ! $end || $end->lessThanOrEqualTo($start)) {
                    return null;
                }

                return [
                    'scheduled_date' => $start->toDateString(),
                    'starts_at' => $start->format('H:i:s'),
                    'ends_at' => $end->format('H:i:s'),
                    'label' => $slot['notes'] ?? null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function resolveTimeRange(array $scheduleSlots, ?string $startsAt, ?string $endsAt): array
    {
        if (! empty($scheduleSlots)) {
            $minStart = collect($scheduleSlots)
                ->map(fn ($slot) => Carbon::parse($slot['scheduled_date'] . ' ' . $slot['starts_at']))
                ->sort()
                ->first();

            $maxEnd = collect($scheduleSlots)
                ->map(fn ($slot) => Carbon::parse($slot['scheduled_date'] . ' ' . $slot['ends_at']))
                ->sort()
                ->last();

            return [
                'starts_at' => $minStart,
                'ends_at' => $maxEnd,
            ];
        }

        if ($startsAt && $endsAt) {
            return [
                'starts_at' => Carbon::parse($startsAt),
                'ends_at' => Carbon::parse($endsAt),
            ];
        }

        throw ValidationException::withMessages([
            'calendar_slots_json' => 'Нужно выбрать хотя бы один слот в календаре.',
        ]);
    }

    private function syncScheduleSlots($model, array $slots): void
    {
        $model->scheduleSlots()->delete();

        foreach ($slots as $slot) {
            $model->scheduleSlots()->create([
                'scheduled_date' => $slot['scheduled_date'],
                'starts_at' => $slot['starts_at'],
                'ends_at' => $slot['ends_at'],
                'label' => $slot['label'] ?? null,
            ]);
        }
    }

    private function caregiverMatchesOrder(User $caregiver, Order $order): bool
    {
        $profile = $caregiver->caregiverProfile;
        if (! $profile) {
            return false;
        }

        if ($profile->availabilitySlots->isEmpty()) {
            return true;
        }

        $requiredSlots = $order->scheduleSlots->isNotEmpty()
            ? $order->scheduleSlots->map(function ($slot) {
                return [
                    'date' => $slot->scheduled_date->format('Y-m-d'),
                    'weekday' => $slot->scheduled_date->dayOfWeek,
                    'starts_at' => substr($slot->starts_at, 0, 5),
                    'ends_at' => substr($slot->ends_at, 0, 5),
                ];
            })
            : collect([[
                'date' => $order->starts_at->format('Y-m-d'),
                'weekday' => $order->starts_at->dayOfWeek,
                'starts_at' => $order->starts_at->format('H:i'),
                'ends_at' => $order->ends_at->format('H:i'),
            ]]);

        return $requiredSlots->every(function (array $requiredSlot) use ($profile) {
            return $profile->availabilitySlots->contains(function (AvailabilitySlot $slot) use ($requiredSlot) {
                $slotStart = substr($slot->starts_at, 0, 5);
                $slotEnd = substr($slot->ends_at, 0, 5);
                $dateMatches = $slot->specific_date
                    ? $slot->specific_date->format('Y-m-d') === $requiredSlot['date']
                    : (int) $slot->weekday === (int) $requiredSlot['weekday'];

                return $dateMatches
                    && $slotStart <= $requiredSlot['starts_at']
                    && $slotEnd >= $requiredSlot['ends_at'];
            });
        });
    }
}
