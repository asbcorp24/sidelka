@extends('layouts.app')

@php($title = $profile->user->name)

@section('content')
<div class="container py-4 py-lg-5">
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card-soft p-4 p-lg-5 mb-4">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                    <div>
                        <div class="text-uppercase small text-secondary">Анкета сиделки</div>
                        <h1 class="section-title mb-1">{{ $profile->user->name }}</h1>
                        <div class="text-secondary">{{ $profile->user->city }} • {{ $profile->education }} • {{ $profile->experience_years }} лет опыта</div>
                    </div>
                    <div class="text-lg-end">
                        <div class="price-tag">от {{ number_format($profile->hourly_rate_from, 0, ',', ' ') }} ₽/час</div>
                        <span class="badge {{ $profile->documents_verified ? 'text-bg-success' : 'text-bg-warning' }}">{{ $profile->documents_verified ? 'Документы проверены' : 'Документы на проверке' }}</span>
                    </div>
                </div>
                <p class="lead">{{ $profile->bio }}</p>
                <div class="mb-4">
                    <h2 class="h5">Навыки</h2>
                    <p class="mb-2"><strong>Медицинские:</strong> {{ $profile->medical_skills }}</p>
                    <p class="mb-0"><strong>Бытовые:</strong> {{ $profile->household_skills }}</p>
                </div>
                <div class="mb-4">
                    <h2 class="h5">Может выполнять</h2>
                    @foreach($profile->availableServices() as $service)
                        <span class="service-chip">{{ $service->name }}</span>
                    @endforeach
                </div>
                <div class="mb-4">
                    <h2 class="h5">Не выполняет</h2>
                    @foreach($profile->restrictedServices() as $service)
                        <span class="availability-chip text-danger bg-danger-subtle">{{ $service->name }}</span>
                    @endforeach
                </div>
                <div>
                    <h2 class="h5">Доступность</h2>
                    @foreach($profile->availabilitySlots as $slot)
                        <span class="availability-chip">{{ ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'][$slot->weekday] }} {{ substr($slot->starts_at, 0, 5) }}-{{ substr($slot->ends_at, 0, 5) }}</span>
                    @endforeach
                </div>
            </div>

            <div class="card-soft p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h4 mb-0">Отзывы о сиделке</h2>
                    <span class="text-secondary">Рейтинг {{ number_format((float) $profile->user->rating, 1, ',', ' ') }}</span>
                </div>
                @forelse($reviews as $review)
                    <div class="border rounded-4 p-3 mb-3">
                        <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
                            <strong>{{ $review->author->name }}</strong>
                            <span>{{ str_repeat('★', $review->rating) }}</span>
                        </div>
                        <p class="mb-1">{{ $review->comment }}</p>
                        <div class="text-secondary small">{{ optional($review->published_at)->format('d.m.Y') }}</div>
                    </div>
                @empty
                    <p class="text-secondary mb-0">Отзывы пока не добавлены.</p>
                @endforelse
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card-soft p-4 mb-4">
                <h2 class="h4">Что есть в кабинете сиделки</h2>
                <ul class="mb-0">
                    <li>календарь доступности по дням и часам</li>
                    <li>цены за час, день и ночную смену</li>
                    <li>чек-лист документов и верификации</li>
                    <li>заявки, подобранные по навыкам и ставке</li>
                    <li>чат с клиентом после отклика</li>
                    <li>кошелек и история выплат</li>
                </ul>
            </div>
            <div class="card-soft p-4">
                <h2 class="h4">Следующий шаг</h2>
                <p class="text-secondary">Позже сюда можно подключить реальную регистрацию, загрузку документов, уведомления и оплату через ЮKassa или другой шлюз.</p>
                <a href="{{ route('dashboard.caregiver', $profile->user) }}" class="btn btn-dark rounded-pill">Открыть кабинет сиделки</a>
            </div>
        </div>
    </div>
</div>
@endsection
