<?php

namespace App\Http\Controllers;

use App\Models\AvailabilitySlot;
use App\Models\City;
use App\Models\Order;
use App\Models\OrderExpense;
use App\Models\Review;
use App\Models\Service;
use App\Models\ShiftType;
use App\Models\User;
use App\Services\OrderFinanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CaregiverController extends Controller
{
    public function __construct(private OrderFinanceService $financeService)
    {
    }

    public function myDashboard(Request $request)
    {
        return $this->dashboard($request->user());
    }

    public function showOrder(Request $request, Order $order)
    {
        $user = $request->user();
        abort_unless($user->isCaregiver() && $order->caregiver_id === $user->id, 404);

        $this->loadOrderRelations($order);
        $order = $this->decorateOrder($order);
        $canReviewClient = $order->status === 'completed'
            && ! $order->reviews->contains(fn (Review $review) => $review->author_id === $user->id && $review->subject_id === $order->client_id);

        return view('orders.show-caregiver', compact('order', 'user', 'canReviewClient'));
    }

    public function payoutsHistory(Request $request)
    {
        $user = $request->user();
        abort_unless($user->isCaregiver(), 404);

        $user->load(['payouts.order', 'caregiverOrders.expenses']);

        return view('payments.caregiver-history', [
            'user' => $user,
            'payouts' => $user->payouts->sortByDesc('created_at')->values(),
            'expenses' => OrderExpense::where('caregiver_id', $user->id)->with('order')->latest()->get(),
        ]);
    }

    public function dashboard(User $user)
    {
        abort_unless($user->isCaregiver() && $user->caregiverProfile, 404);

        $user->load([
            'caregiverProfile.services',
            'caregiverProfile.availabilitySlots',
            'caregiverOrders.services',
            'caregiverOrders.scheduleSlots',
            'caregiverOrders.client',
            'caregiverOrders.conversations.messages.sender',
            'caregiverOrders.expenses',
            'caregiverOrders.payments',
            'caregiverOrders.payouts',
            'notificationsFeed',
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
            ->values();

        $incomingInvitations = $user->caregiverOrders
            ->where('status', 'matched')
            ->sortByDesc('created_at')
            ->map(fn (Order $order) => $this->decorateOrder($order))
            ->values();

        $activeOrders = $user->caregiverOrders
            ->whereIn('status', ['in_chat', 'in_progress', 'completed'])
            ->sortByDesc('created_at')
            ->map(fn (Order $order) => $this->decorateOrder($order))
            ->values();

        $reviews = $user->caregiverOrders
            ->pluck('client')
            ->filter()
            ->unique('id')
            ->flatMap(fn (User $client) => $client->receivedReviews()->with('author', 'subject')->where('subject_role', 'client')->get())
            ->sortByDesc('published_at')
            ->values();

        $stats = [
            'new_matches' => $matchedOrders->count(),
            'orders_done' => $user->caregiverOrders->where('status', 'completed')->count(),
            'released_amount' => $user->payouts()->where('status', 'paid')->sum('amount'),
            'pending_payout' => $user->caregiverOrders->sum(fn (Order $order) => $order->payments->where('status', 'held')->sum('amount')),
        ];

        $notifications = [
            'new_invitations' => $incomingInvitations->count(),
            'new_messages' => $activeOrders->sum('unread_messages_count'),
            'new_notifications' => $user->notificationsFeed->whereNull('read_at')->count(),
        ];

        return view('dashboards.caregiver', [
            'user' => $user,
            'profile' => $profile,
            'matchedOrders' => $matchedOrders,
            'incomingInvitations' => $incomingInvitations,
            'activeOrders' => $activeOrders,
            'reviews' => $reviews,
            'stats' => $stats,
            'notifications' => $notifications,
            'documents' => collect([
                ['title' => 'Паспорт', 'status' => 'Проверен'],
                ['title' => 'Медицинские документы', 'status' => $profile->documents_verified ? 'Проверены' : 'На проверке'],
                ['title' => 'Реквизиты для выплат', 'status' => 'Заполнены'],
            ]),
            'services' => Service::orderBy('category')->orderBy('name')->get(),
            'availabilityCalendarEvents' => $this->buildAvailabilityCalendarEvents($profile),
            'recentNotifications' => $user->notificationsFeed->sortByDesc('created_at')->take(8)->values(),
            'cities' => City::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'shiftTypes' => ShiftType::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function updateProfile(Request $request)
    {
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

        $user->update(['about' => $data['about'] ?? null]);
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

        $this->syncServices($profile, $data['can_service_ids'] ?? [], $data['cannot_service_ids'] ?? []);
        $profile->availabilitySlots()->delete();

        foreach ($this->parseCalendarSlots($data['calendar_slots_json'] ?? null) as $slot) {
            $profile->availabilitySlots()->create($slot);
        }

        return back()->with('status', 'Анкета сиделки обновлена.');
    }

    public function acceptOrder(Request $request, Order $order)
    {
        $user = $request->user();
        abort_unless($user->isCaregiver() && $order->caregiver_id === $user->id, 404);

        DB::transaction(function () use ($order, $user) {
            $order->update([
                'status' => 'in_chat',
                'confirmed_at' => now(),
            ]);

            $conversation = $order->conversations()->where('caregiver_id', $user->id)->firstOrFail();
            $conversation->update(['status' => 'active']);
            $conversation->messages()->create([
                'sender_id' => $user->id,
                'body' => 'Подтверждаю заказ. Можем обсудить детали ухода и согласовать старт.',
            ]);

            $this->financeService->notify(
                $order->client,
                'order.accepted',
                'Сиделка подтвердила заказ',
                "Сиделка подтвердила заказ «{$order->title}»."
            );
        });

        return redirect()->route('caregiver.orders.show', $order)->with('status', 'Заказ подтвержден.');
    }

    public function declineOrder(Request $request, Order $order)
    {
        $user = $request->user();
        abort_unless($user->isCaregiver() && $order->caregiver_id === $user->id, 404);

        DB::transaction(function () use ($order, $user) {
            $conversation = $order->conversations()->where('caregiver_id', $user->id)->first();
            if ($conversation) {
                $conversation->update(['status' => 'declined']);
                $conversation->messages()->create([
                    'sender_id' => $user->id,
                    'body' => 'Сейчас не смогу взять этот заказ. Спасибо за приглашение.',
                ]);
            }

            $order->update([
                'caregiver_id' => null,
                'status' => 'published',
            ]);

            $this->financeService->notify(
                $order->client,
                'order.declined',
                'Сиделка отказалась от заказа',
                "Сиделка отказалась от заказа «{$order->title}». Можно выбрать другую."
            );
        });

        return redirect()->route('caregiver.dashboard')->with('status', 'Приглашение отклонено.');
    }

    public function cancelOrder(Request $request, Order $order)
    {
        $user = $request->user();
        abort_unless($user->isCaregiver() && $order->caregiver_id === $user->id, 404);
        abort_if(in_array($order->status, ['completed', 'cancelled'], true), 422);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string'],
        ]);

        $this->financeService->cancelOrder($order, $user, $data['reason'], $data['details'] ?? null);

        return redirect()->route('caregiver.dashboard')->with('status', 'Заказ отменен.');
    }

    public function storeExpense(Request $request, Order $order)
    {
        $user = $request->user();
        abort_unless($user->isCaregiver() && $order->caregiver_id === $user->id, 404);
        abort_unless(in_array($order->status, ['in_chat', 'in_progress'], true), 422);

        $data = $request->validate([
            'kind' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'quantity' => ['required', 'numeric', 'min:0.1'],
            'unit_price' => ['required', 'integer', 'min:0'],
            'purchased_at' => ['nullable', 'date'],
        ]);

        $quantity = (float) $data['quantity'];
        $expense = $order->expenses()->create([
            'caregiver_id' => $user->id,
            'kind' => $data['kind'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'quantity' => $quantity,
            'unit_price' => $data['unit_price'],
            'line_total' => (int) round($quantity * $data['unit_price']),
            'status' => 'pending_approval',
            'purchased_at' => isset($data['purchased_at']) ? Carbon::parse($data['purchased_at']) : now(),
        ]);

        $this->financeService->notify(
            $order->client,
            'expense.pending',
            'Новый дополнительный расход',
            "Сиделка добавила расход «{$expense->title}» по заказу «{$order->title}»."
        );

        return back()->with('status', 'Допрасход добавлен и отправлен клиенту на подтверждение.');
    }

    public function storeReview(Request $request, Order $order)
    {
        $user = $request->user();
        abort_unless($user->isCaregiver() && $order->caregiver_id === $user->id, 404);
        abort_unless($order->status === 'completed', 422);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        abort_if(
            Review::where('order_id', $order->id)->where('author_id', $user->id)->where('subject_id', $order->client_id)->exists(),
            422
        );

        Review::create([
            'order_id' => $order->id,
            'author_id' => $user->id,
            'subject_id' => $order->client_id,
            'subject_role' => 'client',
            'rating' => $data['rating'],
            'comment' => $data['comment'],
            'published_at' => now(),
        ]);

        return back()->with('status', 'Отзыв о клиенте сохранен.');
    }

    public function storeMessage(Request $request, Order $order)
    {
        $user = $request->user();
        abort_unless($user->isCaregiver() && $order->caregiver_id === $user->id, 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $conversation = $order->conversations()
            ->where('caregiver_id', $user->id)
            ->where('status', 'active')
            ->firstOrFail();

        $conversation->messages()->create([
            'sender_id' => $user->id,
            'body' => $data['body'],
        ]);

        $this->financeService->notify(
            $order->client,
            'message.new',
            'Новое сообщение по заказу',
            "Сиделка написала вам по заказу «{$order->title}»."
        );

        return back()->with('status', 'Сообщение отправлено.');
    }

    public function markMessagesRead(Request $request, Order $order)
    {
        $user = $request->user();
        abort_unless($user->isCaregiver() && $order->caregiver_id === $user->id, 404);

        $conversation = $order->conversations()
            ->where('caregiver_id', $user->id)
            ->where('status', 'active')
            ->firstOrFail();

        $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('status', 'Сообщения отмечены как прочитанные.');
    }

    private function loadOrderRelations(Order $order): void
    {
        $order->loadMissing([
            'client',
            'services',
            'clinicPartnerServices.clinic',
            'scheduleSlots',
            'conversations.messages.sender',
            'expenses',
            'payments',
            'payouts',
            'refunds',
            'cancellations.cancelledBy',
            'reviews.author',
        ]);
    }

    private function decorateOrder(Order $order): Order
    {
        $this->loadOrderRelations($order);

        $order->active_conversation = $order->conversations->where('caregiver_id', $order->caregiver_id)->first();
        $order->unread_messages_count = $order->active_conversation
            ? $order->active_conversation->messages->where('sender_id', '!=', $order->caregiver_id)->whereNull('read_at')->count()
            : 0;

        return $order;
    }

    private function syncServices($profile, array $canIds, array $cannotIds): void
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

    private function buildAvailabilityCalendarEvents($profile): array
    {
        $events = [];

        foreach ($profile->availabilitySlots as $slot) {
            if ($slot->specific_date) {
                $events[] = [
                    'title' => $slot->notes ?: 'Доступна',
                    'start' => $slot->specific_date->format('Y-m-d') . 'T' . substr($slot->starts_at, 0, 5),
                    'end' => $slot->specific_date->format('Y-m-d') . 'T' . substr($slot->ends_at, 0, 5),
                    'notes' => $slot->notes,
                ];
            }
        }

        return $events;
    }

    private function matchesAvailability($profile, Order $order): bool
    {
        if ($profile->availabilitySlots->isEmpty()) {
            return true;
        }

        return $order->scheduleSlots->every(function ($requiredSlot) use ($profile) {
            return $profile->availabilitySlots->contains(function (AvailabilitySlot $slot) use ($requiredSlot) {
                $dateMatches = $slot->specific_date
                    ? $slot->specific_date->format('Y-m-d') === $requiredSlot->scheduled_date->format('Y-m-d')
                    : (int) $slot->weekday === (int) $requiredSlot->scheduled_date->dayOfWeek;

                return $dateMatches
                    && substr($slot->starts_at, 0, 5) <= substr($requiredSlot->starts_at, 0, 5)
                    && substr($slot->ends_at, 0, 5) >= substr($requiredSlot->ends_at, 0, 5);
            });
        });
    }
}
