@extends('layouts.app')

@php($title = 'Заказ клиента')

@section('content')
<div class="container py-4 py-lg-5">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <div class="text-uppercase small text-secondary">Карточка заказа</div>
            <h1 class="section-title mb-1">{{ $order->title }}</h1>
            <div class="text-secondary">{{ $order->city }} • {{ $order->address ?: 'Адрес уточняется' }}</div>
        </div>
        <div class="text-lg-end">
            <span class="badge {{ $order->status_badge_class }}">{{ $order->status_label }}</span>
            <div class="mt-2"><span class="badge {{ $order->payment_status_badge_class }}">{{ $order->payment_status_label }}</span></div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card-soft p-4 mb-4">
                <p>{{ $order->description }}</p>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @foreach($order->services as $service)
                        <span class="service-chip">{{ $service->name }}</span>
                    @endforeach
                    @foreach($order->custom_services ?? [] as $customService)
                        <span class="availability-chip border border-dark-subtle">{{ $customService }}</span>
                    @endforeach
                </div>

                @if($order->scheduleSlots->isNotEmpty())
                    <div class="mb-3">
                        <strong>Расписание:</strong>
                        <div class="mt-2 d-flex flex-wrap gap-2">
                            @foreach($order->scheduleSlots as $slot)
                                <span class="availability-chip">{{ $slot->scheduled_date->format('d.m.Y') }} {{ substr($slot->starts_at, 0, 5) }}-{{ substr($slot->ends_at, 0, 5) }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($order->clinicPartnerServices->isNotEmpty())
                    <div>
                        <strong>Партнерские клиники и скидки:</strong>
                        <div class="mt-2">
                            @foreach($order->clinicPartnerServices as $clinicService)
                                <div class="border rounded-4 p-3 mb-2">
                                    <strong>{{ $clinicService->clinic->name }}</strong>
                                    <div class="text-secondary">{{ $clinicService->name }} • скидка {{ $clinicService->pivot->discount_percent }}%</div>
                                    <div class="mt-1">{{ number_format($clinicService->pivot->price_at_booking, 0, ',', ' ') }} ₽</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="card-soft p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h2 class="h4 mb-0">Счет по заказу</h2>
                    <div class="text-secondary">Баланс клиента: {{ number_format($user->wallet_balance, 0, ',', ' ') }} ₽</div>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span>Основной счет</span>
                    <strong>{{ number_format($order->base_amount, 0, ',', ' ') }} ₽</strong>
                </div>
                @foreach($order->clinicPartnerServices as $clinicService)
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>{{ $clinicService->clinic->name }} — {{ $clinicService->name }}</span>
                        <strong>{{ number_format($clinicService->pivot->price_at_booking, 0, ',', ' ') }} ₽</strong>
                    </div>
                @endforeach
                @foreach($order->expenses as $expense)
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <div>
                            <div>{{ $expense->title }}</div>
                            <div class="small text-secondary">{{ $expense->status === 'pending_approval' ? 'Ждет подтверждения' : ($expense->status === 'rejected' ? 'Отклонено' : 'Согласовано') }}</div>
                        </div>
                        <strong>{{ number_format($expense->line_total, 0, ',', ' ') }} ₽</strong>
                    </div>
                    @if($expense->status === 'pending_approval')
                        <div class="d-flex gap-2 mt-2 mb-3">
                            <form action="{{ route('client.orders.expenses.approve', [$order, $expense]) }}" method="POST">
                                @csrf
                                <button class="btn btn-dark rounded-pill btn-sm">Подтвердить расход</button>
                            </form>
                            <form action="{{ route('client.orders.expenses.reject', [$order, $expense]) }}" method="POST">
                                @csrf
                                <button class="btn btn-outline-dark rounded-pill btn-sm">Отклонить</button>
                            </form>
                        </div>
                    @endif
                @endforeach
                <div class="d-flex justify-content-between pt-3 mt-2">
                    <strong>Итого</strong>
                    <strong>{{ number_format($order->total_invoice_amount, 0, ',', ' ') }} ₽</strong>
                </div>
            </div>

            @if($order->caregiver && $order->status === 'matched')
                <div class="card-soft p-4 mb-4">
                    <h2 class="h4 mb-2">Ожидаем подтверждение сиделки</h2>
                    <div class="text-secondary">Приглашение уже отправлено {{ $order->caregiver->name }}. После подтверждения откроется рабочий чат по заказу.</div>
                </div>
            @endif

            @if($order->active_conversation && $order->active_conversation->status === 'active')
                <div class="card-soft p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <h2 class="h4 mb-0">Чат по заказу</h2>
                        <span class="text-secondary">Сиделка: {{ $order->caregiver->name }}</span>
                    </div>

                    @if(($order->unread_messages_count ?? 0) > 0)
                        <form action="{{ route('client.orders.messages.read', $order) }}" method="POST" class="mb-3">
                            @csrf
                            <button class="btn btn-outline-dark rounded-pill">Отметить как прочитанное</button>
                        </form>
                    @endif

                    @foreach($order->active_conversation->messages as $message)
                        <div class="chat-bubble {{ $message->sender_id === $user->id ? 'client' : 'caregiver' }} mb-2">
                            <strong>{{ $message->sender->name }}</strong>
                            <div>{{ $message->body }}</div>
                        </div>
                    @endforeach

                    <form action="{{ route('client.orders.messages.store', $order) }}" method="POST" class="mt-3">
                        @csrf
                        <div class="input-group">
                            <input type="text" name="body" class="form-control" placeholder="Напишите сообщение сиделке">
                            <button class="btn btn-dark" type="submit">Отправить</button>
                        </div>
                    </form>
                </div>
            @endif

            @if($order->status === 'completed' && $canReviewCaregiver)
                <div class="card-soft p-4 mb-4">
                    <h2 class="h4 mb-3">Оставить отзыв о сиделке</h2>
                    <form action="{{ route('client.orders.review.store', $order) }}" method="POST" class="row g-3">
                        @csrf
                        <div class="col-md-3">
                            <select name="rating" class="form-select">
                                @for($i = 5; $i >= 1; $i--)
                                    <option value="{{ $i }}">{{ $i }} из 5</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-12">
                            <textarea name="comment" class="form-control" rows="3" placeholder="Как прошла работа сиделки?"></textarea>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-dark rounded-pill">Сохранить отзыв</button>
                        </div>
                    </form>
                </div>
            @endif
        </div>

        <div class="col-lg-4">
            <div class="card-soft p-4 mb-4">
                <h2 class="h4 mb-3">Действия</h2>
                <div class="d-grid gap-2">
                    <a href="{{ route('client.dashboard') }}" class="btn btn-outline-dark rounded-pill">Назад в кабинет</a>
                    <a href="{{ route('client.orders.create') }}" class="btn btn-dark rounded-pill">Создать новый заказ</a>
                </div>
                @if($order->status === 'in_chat')
                    <form action="{{ route('client.orders.start', $order) }}" method="POST" class="mt-3">
                        @csrf
                        <button class="btn btn-success w-100 rounded-pill">Перевести в работу и удержать оплату</button>
                    </form>
                @elseif($order->status === 'in_progress')
                    <form action="{{ route('client.orders.complete', $order) }}" method="POST" class="mt-3">
                        @csrf
                        <button class="btn btn-success w-100 rounded-pill">Подтвердить завершение и выплату</button>
                    </form>
                @endif
                @if(! in_array($order->status, ['completed', 'cancelled'], true))
                    <form action="{{ route('client.orders.cancel', $order) }}" method="POST" class="mt-3">
                        @csrf
                        <input type="hidden" name="reason" value="Отмена по инициативе клиента">
                        <button class="btn btn-outline-danger w-100 rounded-pill">Отменить заказ</button>
                    </form>
                @endif
            </div>

            @if(! $order->caregiver_id)
                <div class="card-soft p-4">
                    <h2 class="h4 mb-3">Подходящие сиделки</h2>
                    @forelse($order->matched_caregivers as $caregiver)
                        <div class="border rounded-4 p-3 mb-3">
                            <strong>{{ $caregiver->name }}</strong>
                            <div class="text-secondary">{{ $caregiver->caregiverProfile->experience_years }} лет опыта • от {{ number_format($caregiver->caregiverProfile->hourly_rate_from, 0, ',', ' ') }} ₽/час</div>
                            <div class="mt-2 d-flex flex-wrap gap-2">
                                @foreach($caregiver->matched_services as $matchedService)
                                    <span class="service-chip">{{ $matchedService }}</span>
                                @endforeach
                            </div>
                            <form action="{{ route('client.orders.invite', [$order, $caregiver->caregiverProfile]) }}" method="POST" class="mt-3">
                                @csrf
                                <button class="btn btn-dark rounded-pill btn-sm">Выбрать эту сиделку</button>
                            </form>
                        </div>
                    @empty
                        <p class="text-secondary mb-0">По этому заказу пока нет полных совпадений.</p>
                    @endforelse
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
