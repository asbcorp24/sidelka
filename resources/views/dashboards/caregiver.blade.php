@extends('layouts.app')

@php($title = 'Кабинет сиделки')
@php($selectedCanIds = $profile->availableServices()->pluck('id')->all())
@php($selectedCannotIds = $profile->restrictedServices()->pluck('id')->all())
@php($calendarSeed = old('calendar_slots_json', json_encode($availabilityCalendarEvents, JSON_UNESCAPED_UNICODE)))

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
@endpush

@section('content')
<div class="container py-4 py-lg-5">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
        <div>
            <div class="text-uppercase small text-secondary">Личный кабинет сиделки</div>
            <h1 class="section-title mb-0">{{ $user->name }}</h1>
        </div>
        <div class="text-secondary">{{ $user->city }} • онлайн {{ $user->last_seen_at?->diffForHumans() }}</div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-3"><div class="metric"><div class="value">{{ $stats['new_matches'] }}</div><div>Подходящих заказов</div></div></div>
        <div class="col-md-3"><div class="metric"><div class="value">{{ $stats['orders_done'] }}</div><div>Завершено</div></div></div>
        <div class="col-md-3"><div class="metric"><div class="value">{{ number_format($stats['released_amount'], 0, ',', ' ') }} ₽</div><div>Уже выплачено</div></div></div>
        <div class="col-md-3"><div class="metric"><div class="value">{{ number_format($stats['pending_payout'], 0, ',', ' ') }} ₽</div><div>Ждет выплаты</div></div></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card-soft p-4 mb-4">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                    <div>
                        <h2 class="h4 mb-2">Анкета сиделки</h2>
                        <p class="text-secondary mb-0">Здесь вы управляете навыками, ставкой и доступностью. Допрасходы и выполненные услуги по заказам будут выставляться клиенту отдельными строками.</p>
                    </div>
                    <a href="{{ route('caregiver.payouts.index') }}" class="btn btn-outline-dark rounded-pill">История выплат</a>
                </div>

                <form action="{{ route('caregiver.profile.update') }}" method="POST" class="row g-3">
                    @csrf
                    <div class="col-12">
                        <label class="form-label">Кратко о себе</label>
                        <textarea name="about" class="form-control" rows="2">{{ old('about', $user->about) }}</textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Опыт, лет</label>
                        <input type="number" name="experience_years" class="form-control" value="{{ old('experience_years', $profile->experience_years) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Ставка от, ₽/час</label>
                        <input type="number" name="hourly_rate_from" class="form-control" value="{{ old('hourly_rate_from', $profile->hourly_rate_from) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Смена от, ₽</label>
                        <input type="number" name="shift_rate_from" class="form-control" value="{{ old('shift_rate_from', $profile->shift_rate_from) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Формат занятости</label>
                        <input type="text" name="employment_format" class="form-control" value="{{ old('employment_format', $profile->employment_format) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Образование</label>
                        <input type="text" name="education" class="form-control" value="{{ old('education', $profile->education) }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Описание профиля</label>
                        <textarea name="bio" class="form-control" rows="3">{{ old('bio', $profile->bio) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Могу выполнять</label>
                        @foreach($services as $service)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="can_service_ids[]" value="{{ $service->id }}" {{ in_array($service->id, $selectedCanIds, true) ? 'checked' : '' }}>
                                <label class="form-check-label">{{ $service->name }}</label>
                            </div>
                        @endforeach
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Не выполняю</label>
                        @foreach($services as $service)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="cannot_service_ids[]" value="{{ $service->id }}" {{ in_array($service->id, $selectedCannotIds, true) ? 'checked' : '' }}>
                                <label class="form-check-label">{{ $service->name }}</label>
                            </div>
                        @endforeach
                    </div>
                    <div class="col-md-6">
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" name="ready_for_night" value="1" {{ $profile->ready_for_night ? 'checked' : '' }}>
                            <label class="form-check-label">Готова к ночным сменам</label>
                        </div>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="ready_for_live_in" value="1" {{ $profile->ready_for_live_in ? 'checked' : '' }}>
                            <label class="form-check-label">Готова к работе с проживанием</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Календарь доступности</label>
                        <input type="hidden" name="calendar_slots_json" id="caregiver-calendar-slots" value='@json(json_decode($calendarSeed, true))'>
                        <div id="caregiver-calendar-summary" class="small text-secondary">Сохраненные слоты доступны в редактировании профиля.</div>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-dark rounded-pill px-4">Сохранить анкету</button>
                    </div>
                </form>
            </div>

            <div class="card-soft p-4 mb-4">
                <h2 class="h4 mb-3">Входящие приглашения</h2>
                @forelse($incomingInvitations as $order)
                    <div class="border rounded-4 p-3 mb-3">
                        <div class="d-flex justify-content-between flex-wrap gap-2">
                            <strong>{{ $order->title }}</strong>
                            <span class="badge {{ $order->status_badge_class }}">{{ $order->status_label }}</span>
                        </div>
                        <div class="text-secondary mt-2">{{ $order->client->name }} • {{ $order->city }} • Базовый счет {{ number_format($order->base_amount, 0, ',', ' ') }} ₽</div>
                        <div class="mt-3">
                            <a href="{{ route('caregiver.orders.show', $order) }}" class="btn btn-dark rounded-pill">Открыть заказ</a>
                        </div>
                    </div>
                @empty
                    <p class="text-secondary mb-0">Новых приглашений пока нет.</p>
                @endforelse
            </div>

            <div class="card-soft p-4 mb-4">
                <h2 class="h4 mb-3">Активные заказы</h2>
                @forelse($activeOrders as $order)
                    <div class="border rounded-4 p-3 mb-3">
                        <div class="d-flex justify-content-between flex-wrap gap-2">
                            <strong>{{ $order->title }}</strong>
                            <span class="badge {{ $order->status_badge_class }}">{{ $order->status_label }}</span>
                        </div>
                        <div class="text-secondary mt-2">
                            {{ $order->client->name }} • {{ $order->payment_status_label }}
                            • Итого по счету {{ number_format($order->total_invoice_amount, 0, ',', ' ') }} ₽
                        </div>
                        <div class="mt-3">
                            <a href="{{ route('caregiver.orders.show', $order) }}" class="btn btn-dark rounded-pill">Открыть заказ</a>
                        </div>
                    </div>
                @empty
                    <p class="text-secondary mb-0">Активных заказов пока нет.</p>
                @endforelse
            </div>

            <div class="card-soft p-4">
                <h2 class="h4 mb-3">Отзывы о клиентах</h2>
                @forelse($reviews as $review)
                    <div class="border rounded-4 p-3 mb-3">
                        <div class="d-flex justify-content-between flex-wrap gap-2">
                            <strong>{{ $review->subject->name ?? 'Клиент' }}</strong>
                            <span>{{ str_repeat('★', $review->rating) }}</span>
                        </div>
                        <p class="mb-1 mt-2">{{ $review->comment }}</p>
                        <div class="text-secondary small">{{ optional($review->published_at)->format('d.m.Y') }}</div>
                    </div>
                @empty
                    <p class="text-secondary mb-0">Пока нет отзывов о клиентах.</p>
                @endforelse
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card-soft p-4 mb-4">
                <h2 class="h4 mb-3">Документы и статус</h2>
                @foreach($documents as $document)
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>{{ $document['title'] }}</span>
                        <span class="text-secondary">{{ $document['status'] }}</span>
                    </div>
                @endforeach
            </div>

            <div class="card-soft p-4 mb-4">
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

            <div class="card-soft p-4">
                <h2 class="h4 mb-3">Как теперь работает оплата</h2>
                <ul class="mb-0">
                    <li>Клиент заранее пополняет свой баланс.</li>
                    <li>После подтверждения услуги деньги удерживаются с его счета.</li>
                    <li>Сиделка может добавлять отдельные покупки и допуслуги по заказу.</li>
                    <li>После завершения заказа система переводит удержанные суммы в выплату сиделке.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
