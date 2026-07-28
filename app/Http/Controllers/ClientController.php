<?php

namespace App\Http\Controllers;

use App\Models\CaregiverProfile;
use App\Models\City;
use App\Models\ClinicPartner;
use App\Models\ClinicPartnerService;
use App\Models\Order;
use App\Models\OrderCaregiverAssignment;
use App\Models\OrderExpense;
use App\Models\OrderScheduleSlot;
use App\Models\PatientProfile;
use App\Models\Review;
use App\Models\Service;
use App\Models\ShiftType;
use App\Models\User;
use App\Services\OrderFinanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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

    public function extendOrder(Request $request, Order $order)
    {
        $user = $request->user();
        abort_unless($user->isClient() && $order->client_id === $user->id, 404);
        abort_unless($order->status === 'completed' && $order->payment_status === 'released', 422);

        $this->loadOrderRelations($order);

        $selectedCaregiverProfile = null;
        if ($order->caregiver?->caregiverProfile) {
            $selectedCaregiverProfile = $order->caregiver->caregiverProfile->loadMissing('user');
        }

        return $this->renderCreateOrderView($user, $selectedCaregiverProfile, $order);
    }

    public function showOrder(Request $request, Order $order)
    {
        $user = $request->user();
        abort_unless($user->isClient() && $order->client_id === $user->id, 404);

        $order = $this->decorateOrder($order);

        $reviewedCaregiverIds = $order->reviews
            ->where('author_id', $user->id)
            ->pluck('subject_id')
            ->all();

        $canReviewCaregiver = $order->status === 'completed'
            && $order->assignedCaregivers->pluck('id')->diff($reviewedCaregiverIds)->isNotEmpty();

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
            'clientOrders.caregiverAssignments.caregiver',
            'clientOrders.caregiverAssignments.scheduleSlot',
            'clientOrders.conversations.messages.sender',
            'clientOrders.expenses',
            'clientOrders.payments',
            'clientOrders.patientProfile',
            'favoriteCaregivers.caregiver.caregiverProfile',
            'walletTransactions',
            'notificationsFeed',
        ]);

        $orders = $user->clientOrders
            ->sortByDesc('created_at')
            ->map(fn (Order $order) => $this->decorateOrder($order))
            ->values();

        $reviews = $user->clientOrders
            ->flatMap(fn (Order $order) => $order->assignedCaregivers)
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
            'waiting_confirmation' => $orders->sum(fn (Order $order) => $order->pending_assignment_count),
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
            'favoriteCaregivers' => $user->favoriteCaregivers->sortByDesc('created_at')->values(),
        ]);
    }

    public function storeOrder(Request $request)
    {
        $user = $request->user();
        abort_unless($user->isClient(), 404);

        $data = $request->validate([
            'order_mode' => ['nullable', Rule::in(['direct', 'open'])],
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
            'patient_diagnosis' => ['nullable', 'string', 'max:255'],
            'patient_limitations' => ['nullable', 'string'],
            'patient_daily_routine' => ['nullable', 'string'],
            'patient_medications' => ['nullable', 'string'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:64'],
            'patient_care_features' => ['nullable', 'string'],
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
            ? CaregiverProfile::with('user', 'availabilitySlots', 'services')->findOrFail($data['caregiver_profile_id'])
            : null;
        $orderMode = $selectedCaregiverProfile ? 'direct' : ($data['order_mode'] ?? 'open');
        $city = $this->resolveCityName($data['city_id'] ?? null, $data['city'] ?? null, $user->city);
        $allowsMultiple = (bool) ($data['allows_multiple_caregivers'] ?? false);

        $order = DB::transaction(function () use ($data, $user, $scheduleSlots, $timeRange, $selectedCaregiverProfile, $orderMode, $city, $allowsMultiple) {
            $order = Order::create([
                'client_id' => $user->id,
                'caregiver_id' => $allowsMultiple || $orderMode !== 'direct' ? null : $selectedCaregiverProfile?->user_id,
                'created_by_family_member_id' => $data['family_member_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'],
                'city' => $city['name'],
                'city_id' => $city['id'],
                'address' => $data['address'] ?? null,
                'schedule_type' => $data['schedule_type'],
                'shift_type_id' => $data['shift_type_id'] ?? null,
                'recurrence_label' => $data['recurrence_label'] ?? null,
                'status' => $selectedCaregiverProfile && $orderMode === 'direct' ? 'matched' : 'published',
                'payment_status' => 'pending',
                'is_urgent' => (bool) ($data['is_urgent'] ?? false),
                'needs_today' => (bool) ($data['needs_today'] ?? false),
                'allows_multiple_caregivers' => $allowsMultiple,
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
            $order->patientProfile()->create([
                'client_id' => $user->id,
                'diagnosis' => $data['patient_diagnosis'] ?? null,
                'limitations' => $data['patient_limitations'] ?? null,
                'daily_routine' => $data['patient_daily_routine'] ?? null,
                'medications' => $data['patient_medications'] ?? null,
                'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
                'care_features' => $data['patient_care_features'] ?? null,
            ]);

            if ($selectedCaregiverProfile && $orderMode === 'direct') {
                $this->inviteCaregiverToOrder($order->fresh('scheduleSlots', 'services'), $selectedCaregiverProfile, $user);
            }

            return $order;
        });

        return redirect()->route('client.orders.show', $order)->with('status', 'Заказ сохранен.');
    }

    public function inviteCaregiver(Request $request, Order $order, CaregiverProfile $caregiverProfile)
    {
        $user = $request->user();
        abort_unless($user->isClient() && $order->client_id === $user->id, 404);

        $data = $request->validate([
            'slot_ids' => ['array'],
            'slot_ids.*' => ['integer', 'exists:order_schedule_slots,id'],
        ]);

        $order->loadMissing('scheduleSlots', 'services', 'caregiverAssignments');
        $slotIds = collect($data['slot_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($order->allows_multiple_caregivers) {
            if ($slotIds->isEmpty()) {
                throw ValidationException::withMessages([
                    'slot_ids' => 'Выберите хотя бы одну смену для приглашения.',
                ]);
            }

            $invalidIds = $slotIds->diff($order->scheduleSlots->pluck('id'));
            if ($invalidIds->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'slot_ids' => 'Можно приглашать сиделку только на смены этого заказа.',
                ]);
            }
        }

        DB::transaction(function () use ($order, $caregiverProfile, $user, $slotIds) {
            $this->inviteCaregiverToOrder($order, $caregiverProfile, $user, $slotIds);
        });

        return redirect()->route('client.orders.show', $order)->with('status', 'Приглашение отправлено.');
    }

    public function confirmApplicant(Request $request, Order $order, User $caregiver)
    {
        $user = $request->user();
        abort_unless($user->isClient() && $order->client_id === $user->id, 404);
        abort_unless($caregiver->isCaregiver(), 404);

        $data = $request->validate([
            'slot_ids' => ['array'],
            'slot_ids.*' => ['integer', 'exists:order_schedule_slots,id'],
        ]);

        $order->loadMissing('scheduleSlots', 'caregiverAssignments', 'conversations', 'client');
        $slotIds = collect($data['slot_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->values();

        if (! $order->allows_multiple_caregivers && $order->caregiverAssignments()->whereIn('status', ['accepted', 'completed'])->exists()) {
            throw ValidationException::withMessages([
                'caregiver_id' => 'По этому заказу уже подтверждена сиделка.',
            ]);
        }

        DB::transaction(function () use ($order, $caregiver, $user, $slotIds) {
            $applications = $order->caregiverAssignments
                ->where('caregiver_id', $caregiver->id)
                ->whereIn('status', ['applied', 'reserved']);

            if ($order->allows_multiple_caregivers) {
                if ($slotIds->isEmpty()) {
                    throw ValidationException::withMessages([
                        'slot_ids' => 'Выберите хотя бы одну смену для подтверждения отклика.',
                    ]);
                }

                $applications = $applications->whereIn('order_schedule_slot_id', $slotIds->all());
            }

            if ($applications->isEmpty()) {
                throw ValidationException::withMessages([
                    'caregiver_id' => 'У этой сиделки нет активного отклика по выбранным сменам.',
                ]);
            }

            $order->caregiverAssignments()
                ->whereIn('id', $applications->pluck('id'))
                ->update([
                    'status' => 'accepted',
                    'confirmed_at' => now(),
                ]);

            $order->update([
                'status' => 'in_chat',
                'confirmed_at' => now(),
                'caregiver_id' => $order->caregiver_id ?: $caregiver->id,
            ]);

            $conversation = $order->conversations()->firstOrCreate(
                ['caregiver_id' => $caregiver->id],
                ['client_id' => $order->client_id, 'status' => 'active']
            );

            $conversation->update(['status' => 'active']);
            $conversation->messages()->create([
                'sender_id' => $user->id,
                'body' => $order->allows_multiple_caregivers
                    ? 'Подтверждаю ваши смены по этому заказу. Открываю чат для согласования деталей.'
                    : 'Подтверждаю вас по этому заказу. Открываю чат для согласования деталей.',
            ]);

            $this->financeService->notify(
                $caregiver,
                'order.application.confirmed',
                'Клиент подтвердил ваш отклик',
                "Клиент выбрал вас по заказу «{$order->title}». Можно перейти в чат и договориться о деталях.",
            );
        });

        return redirect()->route('client.orders.show', $order)->with('status', 'Сиделка подтверждена, чат открыт.');
    }

    public function reserveApplicant(Request $request, Order $order, User $caregiver)
    {
        $user = $request->user();
        abort_unless($user->isClient() && $order->client_id === $user->id, 404);
        abort_unless($caregiver->isCaregiver(), 404);

        $updated = $order->caregiverAssignments()
            ->where('caregiver_id', $caregiver->id)
            ->where('status', 'applied')
            ->update([
                'status' => 'reserved',
            ]);

        abort_unless($updated > 0, 422);

        $this->financeService->notify(
            $caregiver,
            'order.application.reserved',
            'Вы в резерве по заказу',
            "Клиент оставил вас в резерве по заказу «{$order->title}».",
        );

        return redirect()->route('client.orders.show', $order)->with('status', 'Сиделка оставлена в резерве.');
    }

    public function declineApplicant(Request $request, Order $order, User $caregiver)
    {
        $user = $request->user();
        abort_unless($user->isClient() && $order->client_id === $user->id, 404);
        abort_unless($caregiver->isCaregiver(), 404);

        $updated = $order->caregiverAssignments()
            ->where('caregiver_id', $caregiver->id)
            ->whereIn('status', ['applied', 'reserved'])
            ->update([
                'status' => 'declined',
            ]);

        abort_unless($updated > 0, 422);

        $this->financeService->notify(
            $caregiver,
            'order.application.declined',
            'Отклик отклонен',
            "Клиент отклонил ваш отклик по заказу «{$order->title}».",
        );

        return redirect()->route('client.orders.show', $order)->with('status', 'Отклик сиделки отклонен.');
    }

    public function startOrder(Request $request, Order $order)
    {
        $user = $request->user();
        abort_unless($user->isClient() && $order->client_id === $user->id, 404);
        abort_unless($order->status === 'in_chat', 422);

        $order = $this->decorateOrder($order);
        abort_unless($order->confirmed_assignment_count > 0, 422);

        DB::transaction(function () use ($order, $user) {
            $order->loadMissing('client', 'conversations');
            $this->financeService->holdBaseOrderPayment($order);

            $order->update([
                'status' => 'in_progress',
                'payment_status' => 'held',
            ]);

            foreach ($order->conversations->where('status', 'active') as $conversation) {
                $conversation->messages()->create([
                    'sender_id' => $user->id,
                    'body' => 'Подтверждаю старт заказа. Средства удержаны с баланса клиента и будут выплачены после подтверждения завершения.',
                ]);
            }
        });

        return redirect()->route('client.orders.show', $order)->with('status', 'Заказ переведен в работу.');
    }

    public function completeOrder(Request $request, Order $order)
    {
        $user = $request->user();
        abort_unless($user->isClient() && $order->client_id === $user->id, 404);
        abort_unless($order->status === 'in_progress', 422);

        DB::transaction(function () use ($order, $user) {
            $order->loadMissing('caregiverAssignments', 'conversations');

            $order->update([
                'status' => 'completed',
                'payment_status' => 'released',
            ]);

            $order->caregiverAssignments()
                ->where('status', 'accepted')
                ->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);

            $this->financeService->releaseHeldPayments($order->fresh([
                'client',
                'caregiver',
                'caregiverAssignments.caregiver',
                'caregiverAssignments.scheduleSlot',
                'payments',
            ]));

            foreach ($order->conversations->where('status', 'active') as $conversation) {
                $conversation->messages()->create([
                    'sender_id' => $user->id,
                    'body' => 'Подтверждаю завершение заказа. Выплата и согласованные расходы переведены исполнителям.',
                ]);
            }
        });

        return redirect()->route('client.orders.show', $order)->with('status', 'Заказ завершен.');
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

        return back()->with('status', 'Расход подтвержден и удержан с баланса клиента.');
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

        return back()->with('status', 'Расход отклонен.');
    }

    public function storeReview(Request $request, Order $order)
    {
        $user = $request->user();
        abort_unless($user->isClient() && $order->client_id === $user->id, 404);
        abort_unless($order->status === 'completed', 422);

        $data = $request->validate([
            'subject_id' => ['required', 'integer', 'exists:users,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        $order = $this->decorateOrder($order);
        abort_unless($order->assignedCaregivers->pluck('id')->contains((int) $data['subject_id']), 422);

        abort_if(
            Review::where('order_id', $order->id)->where('author_id', $user->id)->where('subject_id', $data['subject_id'])->exists(),
            422
        );

        Review::create([
            'order_id' => $order->id,
            'author_id' => $user->id,
            'subject_id' => $data['subject_id'],
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
            'caregiver_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $order = $this->decorateOrder($order);
        $caregiverId = (int) ($data['caregiver_id'] ?? ($order->active_conversation?->caregiver_id ?? 0));
        $conversation = $order->conversations
            ->where('caregiver_id', $caregiverId)
            ->where('status', 'active')
            ->first();

        abort_unless($conversation, 404);

        $conversation->messages()->create([
            'sender_id' => $user->id,
            'body' => $data['body'],
        ]);

        $this->financeService->notify(
            $conversation->caregiver,
            'message.new',
            'Новое сообщение по заказу',
            "Клиент написал вам по заказу «{$order->title}».",
        );

        return back()->with('status', 'Сообщение отправлено.');
    }

    public function markMessagesRead(Request $request, Order $order)
    {
        $user = $request->user();
        abort_unless($user->isClient() && $order->client_id === $user->id, 404);

        $data = $request->validate([
            'caregiver_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $order = $this->decorateOrder($order);
        $caregiverId = (int) ($data['caregiver_id'] ?? ($order->active_conversation?->caregiver_id ?? 0));
        $conversation = $order->conversations
            ->where('caregiver_id', $caregiverId)
            ->where('status', 'active')
            ->first();

        abort_unless($conversation, 404);

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

    private function renderCreateOrderView(User $user, ?CaregiverProfile $selectedCaregiverProfile = null, ?Order $sourceOrder = null)
    {
        $user->loadMissing('familyMembers');
        $sourceOrder?->loadMissing('services', 'clinicPartnerServices', 'scheduleSlots', 'patientProfile');

        $prefillSlots = $sourceOrder
            ? $sourceOrder->scheduleSlots->map(function (OrderScheduleSlot $slot) {
                return [
                    'id' => 'prefill-' . $slot->id,
                    'start' => $slot->scheduled_date->format('Y-m-d') . 'T' . substr($slot->starts_at, 0, 5),
                    'end' => $slot->scheduled_date->format('Y-m-d') . 'T' . substr($slot->ends_at, 0, 5),
                    'notes' => $slot->label,
                ];
            })->values()->all()
            : [];

        return view('orders.create', [
            'user' => $user,
            'services' => Service::orderBy('category')->orderBy('name')->get(),
            'familyMembers' => $user->familyMembers->sortBy('name')->values(),
            'selectedCaregiverProfile' => $selectedCaregiverProfile,
            'sourceOrder' => $sourceOrder,
            'calendarSeed' => old('calendar_slots_json', json_encode($prefillSlots, JSON_UNESCAPED_UNICODE)),
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
            'conversations.caregiver',
            'conversations.messages.sender',
            'expenses.caregiver',
            'patientProfile',
            'payments',
            'payouts',
            'refunds',
            'cancellations.cancelledBy',
            'reviews.author',
            'caregiverAssignments.caregiver.caregiverProfile.services',
            'caregiverAssignments.scheduleSlot',
            'caregiverAssignments.report',
        ]);
    }

    private function decorateOrder(Order $order): Order
    {
        $this->loadOrderRelations($order);

        $matchedCaregivers = collect();
        if ($order->allows_multiple_caregivers) {
            $matchedCaregivers = $this->buildMatchedCaregiversForOpenSlots($order);
        } elseif (! $order->caregiver_id) {
            $matchedCaregivers = $this->buildMatchedCaregiversForFullOrder($order);
        }

        $assignmentsBySlot = $order->scheduleSlots
            ->map(function (OrderScheduleSlot $slot) use ($order) {
                $slot->assignments_for_view = $order->caregiverAssignments
                    ->where('order_schedule_slot_id', $slot->id)
                    ->values();

                return $slot;
            })
            ->values();

        $assignedCaregivers = $order->caregiverAssignments
            ->pluck('caregiver')
            ->filter()
            ->unique('id')
            ->values();

        if ($order->caregiver && $assignedCaregivers->doesntContain('id', $order->caregiver->id)) {
            $assignedCaregivers->push($order->caregiver);
        }

        $activeConversation = $order->conversations->firstWhere('status', 'active');
        $applicantCaregivers = $order->caregiverAssignments
            ->whereIn('status', ['applied', 'reserved'])
            ->pluck('caregiver')
            ->filter()
            ->unique('id')
            ->values();
        $order->matched_caregivers = $matchedCaregivers;
        $order->assignments_by_slot = $assignmentsBySlot;
        $order->assignedCaregivers = $assignedCaregivers;
        $order->applicantCaregivers = $applicantCaregivers;
        $order->active_conversation = $activeConversation;
        $order->unread_messages_count = $order->conversations->sum(function ($conversation) use ($order) {
            return $conversation->messages
                ->where('sender_id', '!=', $order->client_id)
                ->whereNull('read_at')
                ->count();
        });
        $order->pending_assignment_count = $order->caregiverAssignments->where('status', 'invited')->count();
        $order->applied_assignment_count = $order->caregiverAssignments->whereIn('status', ['applied', 'reserved'])->count();
        $order->confirmed_assignment_count = $order->caregiverAssignments->where('status', 'accepted')->count();
        $order->open_schedule_slot_ids = $this->openScheduleSlotIds($order);

        return $order;
    }

    private function buildMatchedCaregiversForFullOrder(Order $order): Collection
    {
        return User::with(['caregiverProfile.services', 'caregiverProfile.availabilitySlots'])
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
                $caregiver->available_slot_ids = $order->scheduleSlots->pluck('id')->all();
                $caregiver->match_score = $this->caregiverMatchScore($caregiver, $order, collect($caregiver->available_slot_ids));

                return $caregiver;
            })
            ->sortByDesc('match_score')
            ->values();
    }

    private function buildMatchedCaregiversForOpenSlots(Order $order): Collection
    {
        $openSlotIds = $this->openScheduleSlotIds($order);
        if ($openSlotIds->isEmpty()) {
            return collect();
        }

        $slots = $order->scheduleSlots->whereIn('id', $openSlotIds)->values();

        return User::with(['caregiverProfile.services', 'caregiverProfile.availabilitySlots'])
            ->where('role', 'caregiver')
            ->where('city', $order->city)
            ->whereHas('caregiverProfile', fn ($query) => $query->where('hourly_rate_from', '<=', $order->hourly_budget))
            ->whereHas('caregiverProfile.services', fn ($query) => $query
                ->whereIn('services.id', $order->services->pluck('id'))
                ->where('caregiver_profile_service.capability_status', 'can_do'))
            ->get()
            ->map(function (User $caregiver) use ($order, $slots) {
                $caregiver->matched_services = $caregiver->caregiverProfile->availableServices()
                    ->pluck('name')
                    ->intersect($order->services->pluck('name'))
                    ->values();
                $availableSlotIds = $slots
                    ->filter(fn (OrderScheduleSlot $slot) => $this->caregiverMatchesSlot($caregiver, $slot))
                    ->pluck('id')
                    ->values()
                    ->all();
                $caregiver->available_slot_ids = $availableSlotIds;
                $caregiver->match_score = $this->caregiverMatchScore($caregiver, $order, collect($availableSlotIds));

                return $caregiver;
            })
            ->filter(fn (User $caregiver) => ! empty($caregiver->available_slot_ids))
            ->sortByDesc('match_score')
            ->values();
    }

    private function caregiverMatchScore(User $caregiver, Order $order, Collection $availableSlotIds): int
    {
        $score = 0;
        $profile = $caregiver->caregiverProfile;

        if ($caregiver->city === $order->city) {
            $score += 20;
        }

        if ($profile && $profile->hourly_rate_from <= $order->hourly_budget) {
            $score += 20;
            $score += max(0, min(15, (int) floor(($order->hourly_budget - $profile->hourly_rate_from) / 100)));
        }

        $orderServiceIds = $order->services->pluck('id');
        $matchedServiceIds = $profile?->availableServices()->pluck('id')->intersect($orderServiceIds) ?? collect();
        $score += $matchedServiceIds->count() * 10;

        $needsMedical = $order->services->contains(fn ($service) => (bool) $service->requires_medical_training);
        if ($needsMedical && $profile && filled($profile->education)) {
            $score += 15;
        }

        $totalSlots = max(1, $order->scheduleSlots->count());
        $score += (int) round(($availableSlotIds->count() / $totalSlots) * 20);

        $score += (int) round(((float) $caregiver->rating) * 4);

        $repeatCount = Order::query()
            ->where('client_id', $order->client_id)
            ->where('caregiver_id', $caregiver->id)
            ->where('status', 'completed')
            ->count();

        if ($repeatCount > 0) {
            $score += min(20, $repeatCount * 5);
        }

        return $score;
    }

    private function openScheduleSlotIds(Order $order): Collection
    {
        $acceptedOrCompleted = $order->caregiverAssignments
            ->whereIn('status', ['accepted', 'completed'])
            ->pluck('order_schedule_slot_id');

        return $order->scheduleSlots
            ->pluck('id')
            ->reject(fn ($id) => $acceptedOrCompleted->contains($id))
            ->values();
    }

    private function buildClinicSyncPayload(array $serviceIds): array
    {
        if ($serviceIds === []) {
            return [];
        }

        $services = ClinicPartnerService::with('clinic')->findMany($serviceIds);
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
                'calendar_slots_json' => 'Нужно выбрать хотя бы одну смену в календаре.',
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

    private function inviteCaregiverToOrder(Order $order, CaregiverProfile $caregiverProfile, User $client, ?Collection $slotIds = null): void
    {
        $order->loadMissing('scheduleSlots', 'services', 'caregiverAssignments', 'conversations');

        if ($order->allows_multiple_caregivers) {
            $requestedIds = ($slotIds && $slotIds->isNotEmpty())
                ? $slotIds
                : $this->openScheduleSlotIds($order);

            $targetSlots = $order->scheduleSlots
                ->whereIn('id', $requestedIds->all())
                ->filter(fn (OrderScheduleSlot $slot) => $this->caregiverMatchesSlot($caregiverProfile->user, $slot))
                ->filter(fn (OrderScheduleSlot $slot) => ! $order->caregiverAssignments
                    ->whereIn('status', ['accepted', 'completed'])
                    ->pluck('order_schedule_slot_id')
                    ->contains($slot->id))
                ->values();

            if ($targetSlots->isEmpty()) {
                throw ValidationException::withMessages([
                    'slot_ids' => 'Для этой сиделки нет доступных выбранных смен.',
                ]);
            }

            foreach ($targetSlots as $slot) {
                $order->caregiverAssignments()->updateOrCreate(
                    [
                        'order_schedule_slot_id' => $slot->id,
                        'caregiver_id' => $caregiverProfile->user_id,
                    ],
                    [
                        'status' => 'invited',
                        'confirmed_at' => null,
                        'completed_at' => null,
                        'notes' => $slot->label,
                    ]
                );
            }

            $order->update([
                'status' => 'matched',
                'caregiver_id' => $order->caregiver_id ?: $caregiverProfile->user_id,
            ]);

            $conversation = $order->conversations()->firstOrCreate(
                ['caregiver_id' => $caregiverProfile->user_id],
                ['client_id' => $client->id, 'status' => 'requested']
            );

            $conversation->update(['status' => 'requested']);
            $conversation->messages()->create([
                'sender_id' => $client->id,
                'body' => 'Отправляю приглашение на выбранные смены. Посмотрите даты и подтвердите только те часы, которые готовы взять.',
            ]);

            $this->financeService->notify(
                $caregiverProfile->user,
                'order.invited',
                'Новое приглашение на смены',
                "Клиент отправил вам смены по заказу «{$order->title}».",
            );

            return;
        }

        if (! $this->caregiverMatchesOrder($caregiverProfile->user, $order)) {
            throw ValidationException::withMessages([
                'caregiver_profile_id' => 'Выбранная сиделка не подходит по расписанию этого заказа.',
            ]);
        }

        $order->update([
            'caregiver_id' => $caregiverProfile->user_id,
            'status' => 'matched',
        ]);

        foreach ($order->scheduleSlots as $slot) {
            $order->caregiverAssignments()->updateOrCreate(
                [
                    'order_schedule_slot_id' => $slot->id,
                    'caregiver_id' => $caregiverProfile->user_id,
                ],
                [
                    'status' => 'invited',
                    'confirmed_at' => null,
                    'completed_at' => null,
                    'notes' => $slot->label,
                ]
            );
        }

        $conversation = $order->conversations()->firstOrCreate(
            ['caregiver_id' => $caregiverProfile->user_id],
            ['client_id' => $client->id, 'status' => 'requested']
        );

        $conversation->update(['status' => 'requested']);
        $conversation->messages()->create([
            'sender_id' => $client->id,
            'body' => 'Здравствуйте. Отправляю вам заказ с выбранным расписанием и услугами. Если условия подходят, подтвердите заказ.',
        ]);

        $this->financeService->notify(
            $caregiverProfile->user,
            'order.invited',
            'Новое приглашение на заказ',
            "Клиент отправил вам заказ «{$order->title}».",
        );
    }

    private function caregiverMatchesOrder(User $caregiver, Order $order): bool
    {
        return $order->scheduleSlots->every(fn (OrderScheduleSlot $slot) => $this->caregiverMatchesSlot($caregiver, $slot));
    }

    private function caregiverMatchesSlot(User $caregiver, OrderScheduleSlot $requiredSlot): bool
    {
        $profile = $caregiver->caregiverProfile;
        if (! $profile) {
            return false;
        }

        if ($profile->availabilitySlots->isEmpty()) {
            return true;
        }

        return $profile->availabilitySlots->contains(function ($slot) use ($requiredSlot) {
            $dateMatches = $slot->specific_date
                ? $slot->specific_date->format('Y-m-d') === $requiredSlot->scheduled_date->format('Y-m-d')
                : (int) $slot->weekday === (int) $requiredSlot->scheduled_date->dayOfWeek;

            return $dateMatches
                && substr($slot->starts_at, 0, 5) <= substr($requiredSlot->starts_at, 0, 5)
                && substr($slot->ends_at, 0, 5) >= substr($requiredSlot->ends_at, 0, 5);
        });
    }
}
