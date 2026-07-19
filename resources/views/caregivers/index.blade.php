@extends('layouts.app')

@php($title = 'Каталог сиделок')

@section('content')
<div class="container py-4 py-lg-5">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
        <div>
            <div class="text-uppercase small text-secondary">Каталог</div>
            <h1 class="section-title mb-0">Анкеты сиделок</h1>
        </div>
        <div class="text-secondary">Подбор по услугам, ставке, опыту и доступности по дням недели.</div>
    </div>

    <div class="row g-4">
        @foreach($caregivers as $profile)
            <div class="col-lg-6">
                <div class="card-soft p-4 h-100">
                    <div class="d-flex justify-content-between gap-3 flex-wrap mb-3">
                        <div>
                            <h2 class="h4 mb-1">{{ $profile->user->name }}</h2>
                            <div class="text-secondary">{{ $profile->user->city }} • {{ $profile->education }}</div>
                        </div>
                        <div class="text-lg-end">
                            <div class="price-tag">от {{ number_format($profile->hourly_rate_from, 0, ',', ' ') }} ₽/час</div>
                            <div class="text-secondary">{{ $profile->experience_years }} лет опыта</div>
                        </div>
                    </div>
                    <p>{{ $profile->bio }}</p>
                    <div class="mb-3">
                        @foreach($profile->availableServices() as $service)
                            <span class="service-chip">{{ $service->name }}</span>
                        @endforeach
                    </div>
                    @if($profile->restrictedServices()->isNotEmpty())
                        <div class="mb-3">
                            <div class="small text-secondary mb-2">Не выполняет:</div>
                            @foreach($profile->restrictedServices() as $service)
                                <span class="availability-chip text-danger bg-danger-subtle">{{ $service->name }}</span>
                            @endforeach
                        </div>
                    @endif
                    <div class="mb-3">
                        @foreach($profile->availabilitySlots as $slot)
                            <span class="availability-chip">
                                {{ ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'][$slot->weekday] }}
                                {{ substr($slot->starts_at, 0, 5) }}-{{ substr($slot->ends_at, 0, 5) }}
                            </span>
                        @endforeach
                    </div>
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="text-secondary">Рейтинг {{ number_format((float) $profile->user->rating, 1, ',', ' ') }} • {{ $profile->user->reviews_count }} отзывов</div>
                        <a href="{{ route('caregivers.show', $profile) }}" class="btn btn-dark rounded-pill">Подробнее</a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
