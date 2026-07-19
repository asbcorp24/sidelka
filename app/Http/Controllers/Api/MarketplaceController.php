<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CaregiverProfile;
use App\Models\NewsPost;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MarketplaceController extends Controller
{
    public function bootstrap(): JsonResponse
    {
        return response()->json([
            'services' => Service::orderBy('category')->orderBy('name')->get(),
            'featured_caregivers' => CaregiverProfile::with(['user', 'services', 'availabilitySlots'])
                ->orderByDesc('documents_verified')
                ->orderBy('hourly_rate_from')
                ->take(6)
                ->get(),
            'news' => NewsPost::where('is_published', true)->latest('published_at')->take(5)->get(),
        ]);
    }

    public function caregivers(Request $request): JsonResponse
    {
        $query = CaregiverProfile::with(['user', 'services', 'availabilitySlots']);

        if ($request->filled('city')) {
            $query->whereHas('user', fn ($builder) => $builder->where('city', $request->string('city')));
        }

        if ($request->filled('max_rate')) {
            $query->where('hourly_rate_from', '<=', (int) $request->input('max_rate'));
        }

        if ($request->filled('service_ids')) {
            $ids = collect(explode(',', (string) $request->input('service_ids')))
                ->filter()
                ->map(fn ($id) => (int) $id);

            $query->whereHas('services', fn ($builder) => $builder
                ->whereIn('services.id', $ids)
                ->where('caregiver_profile_service.capability_status', 'can_do'));
        }

        return response()->json($query->get());
    }

    public function news(): JsonResponse
    {
        return response()->json(
            NewsPost::where('is_published', true)->latest('published_at')->get()
        );
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->load([
                'caregiverProfile.services',
                'caregiverProfile.availabilitySlots',
                'clientOrders.services',
                'caregiverOrders.services',
                'receivedReviews.author',
            ])
        );
    }

    public function updateCaregiverProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isCaregiver(), 403);

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
            'availability_slots' => ['array'],
            'availability_slots.*.weekday' => ['required', 'integer', 'between:0,6'],
            'availability_slots.*.starts_at' => ['required', 'date_format:H:i'],
            'availability_slots.*.ends_at' => ['required', 'date_format:H:i'],
            'availability_slots.*.notes' => ['nullable', 'string', 'max:255'],
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

        $this->syncCaregiverServices(
            $profile,
            $data['can_service_ids'] ?? [],
            $data['cannot_service_ids'] ?? []
        );

        $profile->availabilitySlots()->delete();
        foreach ($data['availability_slots'] ?? [] as $slot) {
            $profile->availabilitySlots()->create([
                'weekday' => $slot['weekday'],
                'starts_at' => $slot['starts_at'],
                'ends_at' => $slot['ends_at'],
                'notes' => $slot['notes'] ?? null,
            ]);
        }

        return response()->json([
            'message' => 'Профиль сиделки обновлен.',
            'profile' => $profile->load(['services', 'availabilitySlots', 'user']),
        ]);
    }

    public function orders(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $orders = $user->isClient()
            ? $user->clientOrders()->with(['services', 'caregiver'])->latest()->get()
            : $user->caregiverOrders()->with(['services', 'client'])->latest()->get();

        return response()->json($orders);
    }

    public function storeOrder(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isClient(), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'city' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'schedule_type' => ['required', Rule::in(['hourly', 'daily', 'night'])],
            'hourly_budget' => ['required', 'integer', 'min:0'],
            'patient_age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'patient_name' => ['nullable', 'string', 'max:255'],
            'special_requirements' => ['nullable', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'service_ids' => ['array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
        ]);

        $order = DB::transaction(function () use ($data, $user) {
            $order = Order::create([
                'client_id' => $user->id,
                'title' => $data['title'],
                'description' => $data['description'],
                'city' => $data['city'],
                'address' => $data['address'] ?? null,
                'schedule_type' => $data['schedule_type'],
                'status' => 'published',
                'payment_status' => 'pending',
                'hourly_budget' => $data['hourly_budget'],
                'patient_age' => $data['patient_age'] ?? null,
                'patient_name' => $data['patient_name'] ?? null,
                'special_requirements' => $data['special_requirements'] ?? null,
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
            ]);

            $order->services()->sync($data['service_ids'] ?? []);

            return $order->load('services');
        });

        return response()->json([
            'message' => 'Заявка создана.',
            'order' => $order,
        ], 201);
    }

    private function syncCaregiverServices(CaregiverProfile $profile, array $canIds, array $cannotIds): void
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
}
