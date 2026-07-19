@extends('layouts.app')

@php($title = 'Заказ сиделки')

@section('content')
<div class="container py-4 py-lg-5">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <div class="text-uppercase small text-secondary">Карточка заказа</div>
            <h1 class="section-title mb-1">{{ $order->title }}</h1>
            <div class="text-secondary">{{ $order->client->name }} • {{ $order->city }}</div>
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
                            <div class="small text-secondary">
                                @if($expense->status === 'pending_approval') Ждет подтверждения клиента
                                @elseif($expense->status === 'rejected') Отклонено клиентом
                                @elseif($expense->status === 'approved') Согласовано
                                @elseif($expense->status === 'billed') Удержано с баланса клиента
                                @endif
                            </div>
                        </div>
                        <strong>{{ number_format($expense->line_total, 0, ',', ' ') }} ₽</strong>
                    </div>
                @endforeach
                <div class="d-flex justify-content-between pt-3 mt-2">
                    <strong>Итого по заказу</strong>
                    <strong>{{ number_format($order->total_invoice_amount, 0, ',', ' ') }} ₽</strong>
                </div>
            </div>

            @if($order->status === 'matched')
                <div class="card-soft p-4 mb-4">
                    <h2 class="h4 mb-3">Приглашение на заказ</h2>
                    <div class="d-flex gap-2 flex-wrap">
                        <form action="{{ route('caregiver.orders.accept', $order) }}" method="POST">
                            @csrf
                            <button class="btn btn-dark rounded-pill">Подтвердить заказ</button>
                        </form>
                        <form action="{{ route('caregiver.orders.decline', $order) }}" method="POST">
                            @csrf
                            <button class="btn btn-outline-dark rounded-pill">Отказаться</button>
                        </form>
                    </div>
                </div>
            @endif

            @if($order->active_conversation && $order->active_conversation->status === 'active')
                <div class="card-soft p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <h2 class="h4 mb-0">Чат по заказу</h2>
                        <span class="text-secondary">Клиент: {{ $order->client->name }}</span>
                    </div>

                    @if(($order->unread_messages_count ?? 0) > 0)
                        <form action="{{ route('caregiver.orders.messages.read', $order) }}" method="POST" class="mb-3">
                            @csrf
                            <button class="btn btn-outline-dark rounded-pill">Отметить как прочитанное</button>
                        </form>
                    @endif

                    @foreach($order->active_conversation->messages as $message)
                        <div class="chat-bubble {{ $message->sender_id === $user->id ? 'caregiver' : 'client' }} mb-2">
                            <strong>{{ $message->sender->name }}</strong>
                            <div>{{ $message->body }}</div>
                        </div>
                    @endforeach

                    <form action="{{ route('caregiver.orders.messages.store', $order) }}" method="POST" class="mt-3">
                        @csrf
                        <div class="input-group">
                            <input type="text" name="body" class="form-control" placeholder="Напишите сообщение клиенту">
                            <button class="btn btn-dark" type="submit">Отправить</button>
                        </div>
                    </form>
                </div>
            @endif

            @if(in_array($order->status, ['in_chat', 'in_progress'], true))
                <div class="card-soft p-4 mb-4">
                    <h2 class="h4 mb-3">Добавить покупку или допуслугу</h2>
                    <form action="{{ route('caregiver.orders.expenses.store', $order) }}" method="POST" class="row g-3">
                        @csrf
                        <div class="col-md-4">
                            <select name="kind" class="form-select">
                                <option value="purchase">Покупка</option>
                                <option value="extra_service">Допуслуга</option>
                                <option value="clinic">Услуга клиники</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <input type="text" name="title" class="form-control" placeholder="Например, лекарства или перевязочный материал">
                        </div>
                        <div class="col-md-4">
                            <input type="number" step="0.1" min="0.1" name="quantity" class="form-control" placeholder="Количество">
                        </div>
                        <div class="col-md-4">
                            <input type="number" min="0" name="unit_price" class="form-control" placeholder="Цена за единицу">
                        </div>
                        <div class="col-md-4">
                            <input type="date" name="purchased_at" class="form-control" value="{{ now()->format('Y-m-d') }}">
                        </div>
                        <div class="col-12">
                            <textarea name="description" class="form-control" rows="2" placeholder="Что именно было куплено или выполнено"></textarea>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-dark rounded-pill">Отправить клиенту на подтверждение</button>
                        </div>
                    </form>
                </div>
            @endif

            @if($order->status === 'completed' && $canReviewClient)
                <div class="card-soft p-4 mb-4">
                    <h2 class="h4 mb-3">Оставить отзыв о клиенте</h2>
                    <form action="{{ route('caregiver.orders.review.store', $order) }}" method="POST" class="row g-3">
                        @csrf
                        <div class="col-md-3">
                            <select name="rating" class="form-select">
                                @for($i = 5; $i >= 1; $i--)
                                    <option value="{{ $i }}">{{ $i }} из 5</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-12">
                            <textarea name="comment" class="form-control" rows="3" placeholder="Как прошла работа с клиентом?"></textarea>
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
                    <a href="{{ route('caregiver.dashboard') }}" class="btn btn-outline-dark rounded-pill">Назад в кабинет</a>
                    <a href="{{ route('caregiver.payouts.index') }}" class="btn btn-dark rounded-pill">История выплат</a>
                </div>
                @if(! in_array($order->status, ['completed', 'cancelled'], true))
                    <form action="{{ route('caregiver.orders.cancel', $order) }}" method="POST" class="mt-3">
                        @csrf
                        <input type="hidden" name="reason" value="Отмена по инициативе сиделки">
                        <button class="btn btn-outline-danger w-100 rounded-pill">Отменить заказ</button>
                    </form>
                @endif
            </div>

            @if($order->payouts->isNotEmpty())
                <div class="card-soft p-4">
                    <h2 class="h4 mb-3">Выплаты по заказу</h2>
                    @foreach($order->payouts as $payout)
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span>{{ optional($payout->paid_at)->format('d.m.Y') ?: 'Ожидает' }}</span>
                            <strong>{{ number_format($payout->amount, 0, ',', ' ') }} ₽</strong>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
