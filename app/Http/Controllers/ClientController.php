<?php

namespace App\Http\Controllers;

use App\Models\CaregiverProfile;
use App\Models\City;
use App\Models\ClinicPartner;
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
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClientController extends Controller
{
    public function __construct(private OrderFinanceService $financeService)
    {
    }

    public function myDashboard(Request $request)
    {
        return $this->dashboard($request->user());
    }

    public function createOrder(Request $request)
    {
        $user = $request->user();
        abort_unless($user->isClient(), 404);

        return $this->renderCreateOrderView($user);
    }

    public function createOrderForCaregiver(Request $request, CaregiverProfile $caregiverProfile)
    {
        $user = $request->user();
        abort_unless($user->isClient(), 404);

        return $this->renderCreateOrderView($user, $caregiverProfile->loadMissing('user'));
    }

    public function showOrder(Request $request, Order $order)
    {
        $user = $request->user();
        abort_unless($user->isClient() && $order->client_id === $user->id, 404);

        $this->loadOrderRelations($order);
        $order = $this->decorateOrder($order);
        $canReviewCaregiver = $order->status === 'completed'
            && $order->caregiver_id
            && ! $order->reviews->contains(fn (Review $review) => $review->author_id === $user->id && $review->subject_id === $order->caregiver_id);

        return view('orders.show-client', compact('order', 'user', 'canReviewCaregiver'));
    }

    public function paymentsHistory(Request $request)
    {
        $user = $request->user();
        abort_unless($user->isClient(), 404);

        $user->load(['payments.order', 'refunds.order', 'walletTransactions.order']);

        return view('payments.client-history', [
            'user' => $user,
            'payments' => $user->payments->sortByDesc('created_at')->values(),
            'refunds' => $user->refunds->sortByDesc('created_at')->values(),
            'transactions' => $user->walletTransactions->sortByDesc('created_at')->values(),
        ]);
    }

    public function topUpWallet(Request $request)
    {
        $user = $request->user();
        abort_unless($user->isClient(), 404);

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:100'],
        ]);

        $this->financeService->topUpWallet($user, $data['amount']);

        return back()->with('status', 'Баланс пополнен.');
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
            'clientOrders.expenses',
            'clientOrders.payments',
            'walletTransactions',
            'notificationsFeed',
        ]);

        $orders = $user->clientOrders
            ->sortByDesc('created_at')
            ->map(fn (Order $order) => $this->decorateOrder($order))
            ->values();

        $reviews = $user->clientOrders
            ->pluck('caregiver')
            ->filter()
            ->unique('id')
            ->flatMap(fn (User $caregiver) => $caregiver->receivedReviews()->with('author', 'subject')->where('subject_role', 'caregiver')->get())
            ->sortByDesc('published_at')
            ->values();

        $stats = [
            'active_orders' => $orders->whereIn('status', ['published', 'matched', 'in_chat', 'in_progress'])->count(),
            'completed_orders' => $orders->where('status', 'completed')->count(),
            'held_amount' => $orders->sum(fn (Order $order) => $order->payments->where('status', 'held')->sum('amount')),
            'wallet_balance' => $user->wallet_balance,
        ];

        $notifications = [
            'waiting_confirmation' => $orders->where('status', 'matched')->count(),
            'new_messages' => $orders->sum('unread_messages_count'),
            'new_notifications' => $user->notificationsFeed->whereNull('read_at')->count(),
        ];

        return view('dashboards.client', [
            'user' => $user,
            'orders' => $orders,
            'reviews' => $reviews,
            'stats' => $stats,
            'templates' => $user->orderTemplates->sortByDesc('created_at')->values(),
            'familyMembers' => $user->familyMembers->sortBy('name')->values(),
            'notifications' => $notifications,
            'walletTransactions' => $user->walletTransactions->sortByDesc('created_at')->take(8)->values(),
            'recentNotifications' => $user->notificationsFeed->sortByDesc('created_at')->take(8)->values(),
        ]);
    }

    public function storeOrder(Request $request)
    {
        $user = $request->user();
        abort_unless($user->isClient(), 404);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'address' => ['nullable', 'string', 'max:255'],
            'schedule_type' => ['required', Rule::in(['hourly', 'daily', 'night', 'calendar'])],
            'shift_type_id' => ['nullable', 'integer', 'exists:shift_types,id'],
            'recurrence_label' => ['nullable', 'string', 'max:255'],
            'hourly_budget' => ['required', 'integer', 'min:0'],
            'patient_age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'patient_name' => ['nullable', 'string', 'max:255'],
            'special_requirements' => ['nullable', 'string'],
            'custom_service_lines' => ['nullable', 'string'],
            'is_urgent' => ['nullable', 'boolean'],
            'needs_today' => ['nullable', 'boolean'],
            'allows_multiple_caregivers' => ['nullable', 'boolean'],
            'family_member_id' => ['nullable', 'integer', 'exists:client_family_members,id'],
            'caregiver_profile_id' => ['nullable', 'integer', 'exists:caregiver_profiles,id'],
            'service_ids' => ['array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'clinic_service_ids' => ['array'],
            'clinic_service_ids.*' => ['integer', 'exists:clinic_partner_services,id'],
            'calendar_slots_json' => ['nullable', 'string'],
        ]);

        $scheduleSlots = $this->parseCalendarSlots($data['calendar_slots_json'] ?? null);
        $timeRange = $this->resolveTimeRange($scheduleSlots);
        $selectedCaregiverProfile = isset($data['caregiver_profile_id'])
            ? CaregiverProfile::with('user')->findOrFail($data['caregiver_profile_id'])
            : null;
        $city = $this->resolveCityName($data['city_id'] ?? null, $data['city'] ?? null, $user->city);

        $order = DB::transaction(function () use ($data, $user, $scheduleSlots, $timeRange, $selectedCaregiverProfile, $city) {
            $order = Order::create([
                'client_id' => $user->id,
                'caregiver_id' => $selectedCaregiverProfile?->user_id,
                'created_by_family_member_id' => $data['family_member_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'],
                'city' => $city['name'],
                'city_id' => $city['id'],
                'address' => $data['address'] ?? null,
                'schedule_type' => $data['schedule_type'],
                'shift_type_id' => $data['shift_type_id'] ?? null,
                'recurrence_label' => $data['recurrence_label'] ?? null,
                'status' => $selectedCaregiverProfile ? 'matched' : 'published',
                'payment_status' => 'pending',
                'is_urgent' => (bool) ($data['is_urgent'] ?? false),
                'needs_today' => (bool) ($data['needs_today'] ?? false),
                'allows_multiple_caregivers' => (bool) ($data['allows_multiple_caregivers'] ?? false),
                'hourly_budget' => $data['hourly_budget'],
                'patient_age' => $data['patient_age'] ?? null,
                'patient_name' => $data['patient_name'] ?? null,
                'special_requirements' => $data['special_requirements'] ?? null,
                'custom_services' => $this->parseCustomServices($data['custom_service_lines'] ?? ''),
                'starts_at' => $timeRange['starts_at'],
                'ends_at' => $timeRange['ends_at'],
            ]);

            $order->services()->sync($data['service_ids'] ?? []);
            $order->clinicPartnerServices()->sync($this->buildClinicSyncPayload($data['clinic_service_ids'] ?? []));
            $this->syncScheduleSlots($order, $scheduleSlots);

            if ($selectedCaregiverProfile) {
                $conversation = $order->conversations()->create([
                    'client_id' => $user->id,
                    'caregiver_id' => $selectedCaregiverProfile->user_id,
                    'status' => 'requested',
                ]);

                $conversation->messages()->create([
                    'sender_id' => $user->id,
                    'body' => 'Здравствуйте. Отправляю вам заказ с выбранным расписанием и услугами. Если условия подходят, подтвердите заказ.',
                ]);

                $this->financeService->notify(
                    $selectedCaregiverProfile->user,
                    'order.invited',
                    'Новое приглашение на заказ',
                    "Клиент отправил вам заказ «{$order->title}»."
                );
            }

            return $order;
        });

        return redirect()->route('client.orders.show', $order)->with('status', 'Заказ сохранен.');
    }

    public function inviteCaregiver(Request $request, Order $order, CaregiverProfile $caregiverProfile)
    {
        $user = $request->user();
        abort_unless($user->isClient() && $order->client_id === $user->id, 404);
        abort_if($order->caregiver_id && $order->caregiver_id !== $caregiverProfile->user_id, 422);

        DB::transaction(function () use ($order, $caregiverProfile, $user) {
            $order->update([
                'caregiver_id' => $caregiverProfile->user_id,
                'status' => 'matched',
            ]);

            $conversation = $order->conversations()->firstOrCreate(
                ['caregiver_id' => $caregiverProfile->user_id],
                ['client_id' => $user->id, 'status' => 'requested']
            );

            $conversation->update(['status' => 'requested']);
            $conversation->messages()->create([
                'sender_id' => $user->id,
                'body' => 'Приглашаю вас на этот заказ. Посмотрите расписание и подтвердите, если готовы.',
            ]);

            $this->financeService->notify(
                $caregiverProfile->user,
                'order.invited',
                'Вас пригласили на заказ',
                "Новый заказ «{$order->title}» ожидает вашего решения."
            );
        });

        return redirect()->route('client.orders.show', $order)->with('status', 'Приглашение отправлено сиделке.');
    }

    public function startOrder(Request $request, Order $order)
    {
        $user = $request->user();
        abort_unless($user->isClient() && $order->client_id === $user->id, 404);
        abort_unless($order->caregiver_id && $order->status === 'in_chat', 422);

        DB::transaction(function () use ($order, $user) {
            $order->loadMissing('client');
            $this->financeService->holdBaseOrderPayment($order);

            $order->update([
                'status' => 'in_progress',
                'payment_status' => 'held',
            ]);

            $conversation = $order->conversations()
                ->where('caregiver_id', $order->caregiver_id)
                ->where('status', 'active')
                ->first();

            if ($conversation) {
                $conversation->messages()->create([
                    'sender_id' => $user->id,
                    'body' => 'Подтверждаю старт работы. Основная сумма заказа уже удержана с моего баланса.',
                ]);
            }
        });

        return redirect()->route('client.orders.show', $order)->with('status', 'Заказ переведен в работу. Средства удержаны с баланса клиента.');
    }

    public function completeOrder(Request $request, Order $order)
    {
        $user = $request->user();
        abort_unless($user->isClient() && $order->client_id === $user->id, 404);
        abort_unless($order->caregiver_id && $order->status === 'in_progress', 422);

        DB::transaction(function () use ($order, $user) {
            $order->update([
                'status' => 'completed',
                'payment_status' => 'released',
            ]);

            $this->financeService->releaseHeldPayments($order);

            $conversation = $order->conversations()
                ->where('caregiver_id', $order->caregiver_id)
                ->where('status', 'active')
                ->first();

            if ($conversation) {
                $conversation->messages()->create([
                    'sender_id' => $user->id,
                    'body' => 'Подтверждаю завершение смены. Выплата и все согласованные допрасходы переведены сиделке.',
                ]);
            }
        });

        return redirect()->route('client.orders.show', $order)->with('status', 'Заказ завершен. Выплата переведена сиделке.');
    }

    public function cancelOrder(Request $request, Order $order)
    {
        $user = $request->user();
        abort_unless($user->isClient() && $order->client_id === $user->id, 404);
        abort_if(in_array($order->status, ['completed', 'cancelled'], true), 422);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string'],
        ]);

        $this->financeService->cancelOrder($order, $user, $data['reason'], $data['details'] ?? null);

        return redirect()->route('client.orders.show', $order)->with('status', 'Заказ отменен.');
    }

    public function approveExpense(Request $request, Order $order, OrderExpense $expense)
    {
        $user = $request->user();
        abort_unless($user->isClient() && $order->client_id === $user->id && $expense->order_id === $order->id, 404);
        abort_unless($expense->status === 'pending_approval', 422);

        DB::transaction(function () use ($expense, $order, $user) {
            $expense->update([
                'status' => 'approved',
                'approved_by_id' => $user->id,
                'approved_at' => now(),
            ]);

            $this->financeService->holdExpensePayment($order->loadMissing('client'), $expense);
            $order->update(['payment_status' => 'held']);
        });

        return back()->with('status', 'Допрасход подтвержден и удержан с баланса клиента.');
    }

    public function rejectExpense(Request $request, Order $order, OrderExpense $expense)
    {
        $user = $request->user();
        abort_unless($user->isClient() && $order->client_id === $user->id && $expense->order_id === $order->id, 404);
        abort_unless($expense->status === 'pending_approval', 422);

        $expense->update([
            'status' => 'rejected',
            'approved_by_id' => $user->id,
            'approved_at' => now(),
        ]);

        return back()->with('status', 'Допрасход отклонен.');
    }

    public function storeReview(Request $request, Order $order)
    {
        $user = $request->user();
        abort_unless($user->isClient() && $order->client_id === $user->id, 404);
        abort_unless($order->status === 'completed' && $order->caregiver_id, 422);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        abort_if(
            Review::where('order_id', $order->id)->where('author_id', $user->id)->where('subject_id', $order->caregiver_id)->exists(),
            422
        );

        Review::create([
            'order_id' => $order->id,
            'author_id' => $user->id,
            'subject_id' => $order->caregiver_id,
            'subject_role' => 'caregiver',
            'rating' => $data['rating'],
            'comment' => $data['comment'],
            'published_at' => now(),
        ]);

        return back()->with('status', 'Отзыв о сиделке сохранен.');
    }

    public function storeMessage(Request $request, Order $order)
    {
        $user = $request->user();
        abort_unless($user->isClient() && $order->client_id === $user->id, 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $conversation = $order->conversations()
            ->where('client_id', $user->id)
            ->where('status', 'active')
            ->firstOrFail();

        $conversation->messages()->create([
            'sender_id' => $user->id,
            'body' => $data['body'],
        ]);

        $this->financeService->notify(
            $order->caregiver,
            'message.new',
            'Новое сообщение по заказу',
            "Клиент написал вам по заказу «{$order->title}»."
        );

        return back()->with('status', 'Сообщение отправлено.');
    }

    public function markMessagesRead(Request $request, Order $order)
    {
        $user = $request->user();
        abort_unless($user->isClient() && $order->client_id === $user->id, 404);

        $conversation = $order->conversations()
            ->where('client_id', $user->id)
            ->where('status', 'active')
            ->firstOrFail();

        $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('status', 'Сообщения отмечены как прочитанные.');
    }

    public function storeFamilyMember(Request $request)
    {
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

        return back()->with('status', 'Родственник добавлен.');
    }

    public function storeTemplate(Request $request)
    {
        $user = $request->user();
        abort_unless($user->isClient(), 404);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'address' => ['nullable', 'string', 'max:255'],
            'schedule_type' => ['required', Rule::in(['hourly', 'daily', 'night', 'calendar'])],
            'shift_type_id' => ['nullable', 'integer', 'exists:shift_types,id'],
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
        $city = $this->resolveCityName($data['city_id'] ?? null, $data['city'] ?? null, $user->city);

        $template = $user->orderTemplates()->create([
            'title' => $data['title'],
            'description' => $data['description'],
            'city' => $city['name'],
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

    private function renderCreateOrderView(User $user, ?CaregiverProfile $selectedCaregiverProfile = null)
    {
        $user->loadMissing('familyMembers');

        return view('orders.create', [
            'user' => $user,
            'services' => Service::orderBy('category')->orderBy('name')->get(),
            'familyMembers' => $user->familyMembers->sortBy('name')->values(),
            'selectedCaregiverProfile' => $selectedCaregiverProfile,
            'calendarSeed' => old('calendar_slots_json', '[]'),
            'cities' => City::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'shiftTypes' => ShiftType::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'clinicPartners' => ClinicPartner::with(['services' => fn ($query) => $query->where('is_active', true)])
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }

    private function loadOrderRelations(Order $order): void
    {
        $order->loadMissing([
            'services',
            'clinicPartnerServices.clinic',
            'scheduleSlots',
            'familyMember',
            'caregiver.caregiverProfile.services',
            'conversations.messages.sender',
            'expenses.caregiver',
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

        $matchedCaregivers = collect();
        if (! $order->caregiver_id) {
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
                ->values();
        }

        $order->matched_caregivers = $matchedCaregivers;
        $order->active_conversation = $order->conversations->where('caregiver_id', $order->caregiver_id)->first();
        $order->unread_messages_count = $order->active_conversation
            ? $order->active_conversation->messages->where('sender_id', '!=', $order->client_id)->whereNull('read_at')->count()
            : 0;

        return $order;
    }

    private function buildClinicSyncPayload(array $serviceIds): array
    {
        if ($serviceIds === []) {
            return [];
        }

        $services = \App\Models\ClinicPartnerService::with('clinic')->findMany($serviceIds);
        $payload = [];

        foreach ($services as $service) {
            $discount = max($service->discount_percent, $service->clinic->discount_percent);
            $payload[$service->id] = [
                'price_at_booking' => (int) round($service->base_price * ((100 - $discount) / 100)),
                'discount_percent' => $discount,
            ];
        }

        return $payload;
    }

    private function resolveCityName(?int $cityId, ?string $manualCity, string $fallback): array
    {
        if ($cityId) {
            $city = City::find($cityId);
            if ($city) {
                return ['id' => $city->id, 'name' => $city->name];
            }
        }

        return ['id' => null, 'name' => $manualCity ?: $fallback];
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

    private function resolveTimeRange(array $scheduleSlots): array
    {
        if (empty($scheduleSlots)) {
            throw ValidationException::withMessages([
                'calendar_slots_json' => 'Нужно выбрать хотя бы один слот в календаре.',
            ]);
        }

        $minStart = collect($scheduleSlots)->map(fn ($slot) => Carbon::parse($slot['scheduled_date'] . ' ' . $slot['starts_at']))->sort()->first();
        $maxEnd = collect($scheduleSlots)->map(fn ($slot) => Carbon::parse($slot['scheduled_date'] . ' ' . $slot['ends_at']))->sort()->last();

        return ['starts_at' => $minStart, 'ends_at' => $maxEnd];
    }

    private function syncScheduleSlots($model, array $slots): void
    {
        $model->scheduleSlots()->delete();

        foreach ($slots as $slot) {
            $model->scheduleSlots()->create($slot);
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

        return $order->scheduleSlots->every(function ($requiredSlot) use ($profile) {
            return $profile->availabilitySlots->contains(function ($slot) use ($requiredSlot) {
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
