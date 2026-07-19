<?php

namespace App\Http\Controllers;

use App\Models\AvailabilitySlot;
use App\Models\CaregiverProfile;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CaregiverController extends Controller
{
    public function myDashboard(Request $request)
    {
        return $this->dashboard($request->user());
    }

    public function dashboard(User $user)
    {
        abort_unless($user->isCaregiver() && $user->caregiverProfile, 404);

        $user->load([
            'caregiverProfile.services',
            'caregiverProfile.availabilitySlots',
            'receivedReviews.author',
            'caregiverOrders.services',
            'caregiverOrders.scheduleSlots',
        ]);

        $profile = $user->caregiverProfile;
        $serviceIds = $profile->availableServices()->pluck('id');

        $matchedOrders = Order::with(['client', 'services', 'scheduleSlots'])
            ->where('status', 'published')
            ->where('city', $user->city)
            ->where('hourly_budget', '>=', $profile->hourly_rate_from)
            ->whereHas('services', fn ($query) => $query->whereIn('services.id', $serviceIds))
            ->get()
            ->filter(fn (Order $order) => $this->matchesAvailability($profile, $order))
            ->map(function (Order $order) use ($serviceIds) {
                $order->match_count = $order->services->pluck('id')->intersect($serviceIds)->count();
                return $order;
            })
            ->sortByDesc('match_count')
            ->values();

        $reviews = $user->receivedReviews
            ->where('subject_role', 'caregiver')
            ->sortByDesc('published_at')
            ->values();

        $stats = [
            'orders_done' => $user->caregiverOrders->where('status', 'completed')->count(),
            'monthly_income' => $user->caregiverOrders
                ->where('payment_status', 'released')
                ->sum(fn ($order) => $order->hourly_budget * max(1, $order->starts_at->diffInHours($order->ends_at))),
            'pending_payout' => $user->caregiverOrders
                ->where('payment_status', 'held')
                ->sum(fn ($order) => $order->hourly_budget * max(1, $order->starts_at->diffInHours($order->ends_at))),
            'new_matches' => $matchedOrders->count(),
        ];

        $documents = collect([
            ['title' => 'Паспорт', 'status' => 'Проверен'],
            ['title' => 'Медкнижка', 'status' => $profile->documents_verified ? 'Проверена' : 'На проверке'],
            ['title' => 'Справка об опыте', 'status' => 'Загружена'],
        ]);

        $services = Service::orderBy('category')->orderBy('name')->get();
        $availabilityCalendarEvents = $this->buildAvailabilityCalendarEvents($profile);

        return view('dashboards.caregiver', compact(
            'user',
            'profile',
            'matchedOrders',
            'reviews',
            'stats',
            'documents',
            'services',
            'availabilityCalendarEvents'
        ));
    }

    public function updateProfile(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isCaregiver() && $user->caregiverProfile, 404);

        $data = $request->validate([
            'about' => ['nullable', 'string'],
            'experience_years' => ['required', 'integer', 'min:0', 'max:60'],
            'hourly_rate_from' => ['required', 'integer', 'min:0'],
            'shift_rate_from' => ['nullable', 'integer', 'min:0'],
            'employment_format' => ['required', 'string', 'max:255'],
            'education' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'medical_skills' => ['nullable', 'string'],
            'household_skills' => ['nullable', 'string'],
            'ready_for_night' => ['nullable', 'boolean'],
            'ready_for_live_in' => ['nullable', 'boolean'],
            'can_service_ids' => ['array'],
            'can_service_ids.*' => ['integer', 'exists:services,id'],
            'cannot_service_ids' => ['array'],
            'cannot_service_ids.*' => ['integer', 'exists:services,id'],
            'calendar_slots_json' => ['nullable', 'string'],
        ]);

        $user->update([
            'about' => $data['about'] ?? null,
        ]);

        $profile = $user->caregiverProfile;
        $profile->update([
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
        ]);

        $this->syncServices(
            $profile,
            $data['can_service_ids'] ?? [],
            $data['cannot_service_ids'] ?? []
        );

        $profile->availabilitySlots()->delete();
        foreach ($this->parseCalendarSlots($data['calendar_slots_json'] ?? null) as $slot) {
            $profile->availabilitySlots()->create($slot);
        }

        return back()->with('status', 'Профиль сиделки обновлен.');
    }

    private function syncServices(CaregiverProfile $profile, array $canIds, array $cannotIds): void
    {
        $syncPayload = [];

        foreach ($canIds as $serviceId) {
            $syncPayload[$serviceId] = ['capability_status' => 'can_do'];
        }

        foreach ($cannotIds as $serviceId) {
            if (! isset($syncPayload[$serviceId])) {
                $syncPayload[$serviceId] = ['capability_status' => 'cannot_do'];
            }
        }

        $profile->services()->sync($syncPayload);
    }

    private function parseCalendarSlots(?string $calendarSlotsJson): array
    {
        $slots = [];

        foreach (collect(json_decode($calendarSlotsJson ?: '[]', true)) as $slot) {
            $start = isset($slot['start']) ? Carbon::parse($slot['start']) : null;
            $end = isset($slot['end']) ? Carbon::parse($slot['end']) : null;

            if (! $start || ! $end || $end->lessThanOrEqualTo($start)) {
                continue;
            }

            $slots[] = [
                'weekday' => $start->dayOfWeek,
                'specific_date' => $start->toDateString(),
                'starts_at' => $start->format('H:i:s'),
                'ends_at' => $end->format('H:i:s'),
                'is_recurring' => false,
                'notes' => $slot['notes'] ?? null,
            ];
        }

        return $slots;
    }

    private function buildAvailabilityCalendarEvents(CaregiverProfile $profile): array
    {
        $events = [];

        foreach ($profile->availabilitySlots as $slot) {
            if ($slot->specific_date) {
                $events[] = $this->makeCalendarEvent(
                    $slot->specific_date->format('Y-m-d'),
                    $slot->starts_at,
                    $slot->ends_at,
                    $slot->notes
                );

                continue;
            }

            $startDate = Carbon::today();
            for ($offset = 0; $offset < 28; $offset++) {
                $date = $startDate->copy()->addDays($offset);
                if ($date->dayOfWeek !== (int) $slot->weekday) {
                    continue;
                }

                $events[] = $this->makeCalendarEvent(
                    $date->format('Y-m-d'),
                    $slot->starts_at,
                    $slot->ends_at,
                    $slot->notes
                );
            }
        }

        return $events;
    }

    private function makeCalendarEvent(string $date, string $startsAt, string $endsAt, ?string $notes): array
    {
        return [
            'title' => $notes ?: 'Доступна',
            'start' => $date . 'T' . substr($startsAt, 0, 5),
            'end' => $date . 'T' . substr($endsAt, 0, 5),
            'notes' => $notes,
        ];
    }

    private function matchesAvailability(CaregiverProfile $profile, Order $order): bool
    {
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
