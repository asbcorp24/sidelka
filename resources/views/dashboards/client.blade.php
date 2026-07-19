@extends('layouts.app')

@php($title = 'Кабинет клиента')

@section('content')
<div class="container py-4 py-lg-5">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
        <div>
            <div class="text-uppercase small text-secondary">Личный кабинет клиента</div>
            <h1 class="section-title mb-0">{{ $user->name }}</h1>
        </div>
        <div class="text-secondary">{{ $user->city }} • онлайн {{ $user->last_seen_at?->diffForHumans() }}</div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-3"><div class="metric"><div class="value">{{ $stats['active_orders'] }}</div><div>Активных заказов</div></div></div>
        <div class="col-md-3"><div class="metric"><div class="value">{{ $stats['completed_orders'] }}</div><div>Завершено</div></div></div>
        <div class="col-md-3"><div class="metric"><div class="value">{{ number_format($stats['held_amount'], 0, ',', ' ') }} ₽</div><div>Удержано по заказам</div></div></div>
        <div class="col-md-3"><div class="metric"><div class="value">{{ number_format($stats['wallet_balance'], 0, ',', ' ') }} ₽</div><div>Баланс клиента</div></div></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card-soft p-4 mb-4">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div>
                        <h2 class="h4 mb-2">Заказы</h2>
                        <p class="text-secondary mb-0">Новые заявки создаются на отдельной странице. По каждому заказу есть отдельная карточка с чатом, счетом, клиниками, расходами и статусами.</p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="{{ route('client.orders.create') }}" class="btn btn-dark rounded-pill">Новый заказ</a>
                        <a href="{{ route('client.payments.index') }}" class="btn btn-outline-dark rounded-pill">История оплат</a>
                    </div>
                </div>
            </div>

            @if(($notifications['waiting_confirmation'] ?? 0) > 0 || ($notifications['new_messages'] ?? 0) > 0 || ($notifications['new_notifications'] ?? 0) > 0)
                <div class="card-soft p-4 mb-4">
                    <h2 class="h4 mb-3">Уведомления</h2>
                    <div class="d-flex flex-wrap gap-2">
                        @if(($notifications['waiting_confirmation'] ?? 0) > 0)
                            <span class="badge text-bg-warning">Ждут подтверждения: {{ $notifications['waiting_confirmation'] }}</span>
                        @endif
                        @if(($notifications['new_messages'] ?? 0) > 0)
                            <span class="badge text-bg-info">Новых сообщений: {{ $notifications['new_messages'] }}</span>
                        @endif
                        @if(($notifications['new_notifications'] ?? 0) > 0)
                            <span class="badge text-bg-secondary">Системных уведомлений: {{ $notifications['new_notifications'] }}</span>
                        @endif
                    </div>
                </div>
            @endif

            @forelse($orders as $order)
                <div class="card-soft p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                        <div>
                            <div class="text-uppercase small text-secondary">Заказ</div>
                            <h2 class="h4 mb-1">{{ $order->title }}</h2>
                            <div class="text-secondary">{{ $order->city }} • {{ $order->address ?: 'Адрес уточняется' }}</div>
                        </div>
                        <div class="text-lg-end">
                            <span class="badge {{ $order->status_badge_class }}">{{ $order->status_label }}</span>
                            <div class="mt-2"><span class="badge {{ $order->payment_status_badge_class }}">{{ $order->payment_status_label }}</span></div>
                        </div>
                    </div>

                    <p>{{ $order->description }}</p>

                    <div class="d-flex flex-wrap gap-2 mb-3">
                        @foreach($order->services as $service)
                            <span class="service-chip">{{ $service->name }}</span>
                        @endforeach
                        @foreach($order->custom_services ?? [] as $customService)
                            <span class="availability-chip border border-dark-subtle">{{ $customService }}</span>
                        @endforeach
                    </div>

                    <div class="text-secondary mb-3">
                        Сиделка:
                        <strong>{{ $order->caregiver?->name ?: 'еще не выбрана' }}</strong>
                        • Базовый счет {{ number_format($order->base_amount, 0, ',', ' ') }} ₽
                        • Итого с учетом согласованных допуслуг {{ number_format($order->total_invoice_amount, 0, ',', ' ') }} ₽
                    </div>

                    <a href="{{ route('client.orders.show', $order) }}" class="btn btn-dark rounded-pill">Открыть заказ</a>
                </div>
            @empty
                <div class="card-soft p-4 mb-4">
                    <h2 class="h4 mb-2">Заказов пока нет</h2>
                    <p class="text-secondary mb-0">Создайте первый заказ и укажите нужные услуги, даты, время и бюджет.</p>
                </div>
            @endforelse

            <div class="card-soft p-4">
                <h2 class="h4 mb-3">Отзывы о сиделках</h2>
                @forelse($reviews as $review)
                    <div class="border rounded-4 p-3 mb-3">
                        <div class="d-flex justify-content-between flex-wrap gap-2">
                            <strong>{{ $review->subject->name ?? 'Сиделка' }}</strong>
                            <span>{{ str_repeat('★', $review->rating) }}</span>
                        </div>
                        <p class="mb-1 mt-2">{{ $review->comment }}</p>
                        <div class="text-secondary small">{{ optional($review->published_at)->format('d.m.Y') }}</div>
                    </div>
                @empty
                    <p class="text-secondary mb-0">Пока нет отзывов о сиделках.</p>
                @endforelse
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card-soft p-4 mb-4">
                <h2 class="h4 mb-3">Баланс</h2>
                <div class="display-6 mb-3">{{ number_format($user->wallet_balance, 0, ',', ' ') }} ₽</div>
                <form action="{{ route('client.wallet.topup') }}" method="POST" class="row g-2">
                    @csrf
                    <div class="col-8">
                        <input type="number" min="100" step="100" name="amount" class="form-control" placeholder="Сумма пополнения">
                    </div>
                    <div class="col-4">
                        <button class="btn btn-dark w-100">Пополнить</button>
                    </div>
                </form>
                <div class="small text-secondary mt-3">Клиент может пополнить счет на любую сумму, а система будет удерживать деньги уже по подтвержденным услугам и расходам.</div>
            </div>

            <div class="card-soft p-4 mb-4">
                <h2 class="h4 mb-3">Последние движения</h2>
                @forelse($walletTransactions as $transaction)
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <div>
                            <div>{{ $transaction->description }}</div>
                            <div class="small text-secondary">{{ $transaction->created_at->format('d.m.Y H:i') }}</div>
                        </div>
                        <div class="{{ $transaction->amount >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ $transaction->amount >= 0 ? '+' : '' }}{{ number_format($transaction->amount, 0, ',', ' ') }} ₽
                        </div>
                    </div>
                @empty
                    <p class="text-secondary mb-0">История баланса пока пустая.</p>
                @endforelse
            </div>

            <div class="card-soft p-4 mb-4">
                <h2 class="h4 mb-3">Семейный доступ</h2>
                <form action="{{ route('client.family.store') }}" method="POST" class="row g-3 mb-4">
                    @csrf
                    <div class="col-12"><input type="text" name="name" class="form-control" placeholder="Имя родственника"></div>
                    <div class="col-12"><input type="text" name="relationship" class="form-control" placeholder="Кто это: дочь, сын, внук"></div>
                    <div class="col-md-6"><input type="text" name="phone" class="form-control" placeholder="Телефон"></div>
                    <div class="col-md-6"><input type="email" name="email" class="form-control" placeholder="Email"></div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="can_create_orders" value="1" checked>
                            <label class="form-check-label">Может создавать заказы</label>
                        </div>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="can_view_chats" value="1" checked>
                            <label class="form-check-label">Может видеть чаты</label>
                        </div>
                    </div>
                    <div class="col-12"><textarea name="notes" class="form-control" rows="2" placeholder="Примечание"></textarea></div>
                    <div class="col-12"><button class="btn btn-outline-dark rounded-pill px-4">Добавить родственника</button></div>
                </form>
                @foreach($familyMembers as $familyMember)
                    <div class="border rounded-4 p-3 mb-3">
                        <strong>{{ $familyMember->name }}</strong>
                        <div class="text-secondary">{{ $familyMember->relationship }} • {{ $familyMember->phone }}</div>
                    </div>
                @endforeach
            </div>

            <div class="card-soft p-4">
                <h2 class="h4 mb-3">Последние уведомления</h2>
                @forelse($recentNotifications as $notification)
                    <div class="border rounded-4 p-3 mb-3">
                        <strong>{{ $notification->title }}</strong>
                        <p class="text-secondary small mb-0 mt-1">{{ $notification->body }}</p>
                    </div>
                @empty
                    <p class="text-secondary mb-0">Новых уведомлений пока нет.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
