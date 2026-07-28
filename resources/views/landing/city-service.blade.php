@extends('layouts.app')

@php($title = $service ? $service->name . ' в ' . $cityName : 'Сиделки в ' . $cityName)

@section('content')
<div class="container py-4 py-lg-5">
    <div class="card-soft p-4 p-lg-5 mb-4">
        <div class="text-uppercase small text-secondary">Публичный лендинг</div>
        <h1 class="section-title mb-2">{{ $service ? $service->name : 'Подбор сиделки' }} в {{ $cityName }}</h1>
        <p class="lead mb-0">
            {{ $service ? 'Подбираем сиделок именно под эту услугу' : 'Помогаем подобрать сиделку по графику, навыкам и бюджету' }}
            с безопасной оплатой, календарем смен и подтверждением выполненной работы.
        </p>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-4"><div class="metric"><div class="value">{{ $caregivers->count() }}</div><div>Подходящих анкет</div></div></div>
        <div class="col-md-4"><div class="metric"><div class="value">{{ $ordersCount }}</div><div>Заказов по городу</div></div></div>
        <div class="col-md-4"><div class="metric"><div class="value">{{ $service ? 'Да' : 'Все услуги' }}</div><div>{{ $service ? 'Услуга выделена' : 'Режим лендинга' }}</div></div></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card-soft p-4 mb-4">
                <h2 class="h4 mb-3">Почему этот лендинг полезен</h2>
                <ul class="mb-0">
                    <li>Отдельная страница под город и услугу лучше индексируется в поиске.</li>
                    <li>Клиент сразу видит релевантных сиделок, а не общий каталог.</li>
                    <li>Можно вести рекламу на конкретную потребность: сиделка в больницу, уход после инсульта, срочная сиделка сегодня.</li>
                </ul>
            </div>

            <div class="card-soft p-4">
                <h2 class="h4 mb-3">Подходящие сиделки</h2>
                @forelse($caregivers as $caregiver)
                    <div class="border rounded-4 p-3 mb-3">
                        <div class="d-flex justify-content-between flex-wrap gap-2">
                            <div>
                                <strong>{{ $caregiver->user->name }}</strong>
                                <div class="text-secondary">{{ $caregiver->experience_years }} лет опыта • от {{ number_format($caregiver->hourly_rate_from, 0, ',', ' ') }} ₽/час</div>
                            </div>
                            <a href="{{ route('caregivers.show', $caregiver) }}" class="btn btn-outline-dark rounded-pill btn-sm">Открыть анкету</a>
                        </div>
                    </div>
                @empty
                    <p class="text-secondary mb-0">Сейчас для этого лендинга нет готовых анкет. Можно оставить заказ, и система подберет исполнителя вручную.</p>
                @endforelse
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card-soft p-4 mb-4">
                <h2 class="h4 mb-3">Что можно делать дальше</h2>
                <div class="d-grid gap-2">
                    <a href="{{ route('caregivers.index') }}" class="btn btn-outline-dark rounded-pill">Весь каталог сиделок</a>
                    <a href="{{ route('register') }}" class="btn btn-dark rounded-pill">Оформить заказ</a>
                </div>
            </div>

            <div class="card-soft p-4">
                <h2 class="h4 mb-3">Последние новости</h2>
                @forelse($news as $post)
                    <div class="border rounded-4 p-3 mb-3">
                        <strong>{{ $post->title }}</strong>
                        @if($post->excerpt)
                            <div class="text-secondary small mt-1">{{ $post->excerpt }}</div>
                        @endif
                    </div>
                @empty
                    <p class="text-secondary mb-0">Новостей пока нет.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
