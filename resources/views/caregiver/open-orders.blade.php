@extends('layouts.app')

@php($title = 'Открытые заказы')

@section('content')
<div class="container py-4 py-lg-5">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
        <div>
            <div class="text-uppercase small text-secondary">Кабинет сиделки</div>
            <h1 class="section-title mb-0">Открытые заказы</h1>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('caregiver.dashboard') }}" class="btn btn-outline-dark rounded-pill px-4">Назад в кабинет</a>
            <a href="{{ route('caregiver.orders.history') }}" class="btn btn-dark rounded-pill px-4">История заказов</a>
        </div>
    </div>

    <div class="card-soft p-4">
        @forelse($orders as $order)
            <div class="border rounded-4 p-3 mb-3">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <strong>{{ $order->title }}</strong>
                        <div class="text-secondary small mt-1">{{ $order->client->name }} • {{ $order->city }}</div>
                    </div>
                    <div class="text-end">
                        <span class="badge {{ $order->status_badge_class }}">{{ $order->status_label }}</span>
                        <div class="small text-secondary mt-2">от {{ number_format($order->hourly_budget, 0, ',', ' ') }} ₽/час</div>
                    </div>
                </div>
                <div class="mt-2 d-flex flex-wrap gap-2">
                    @foreach($order->services->take(6) as $service)
                        <span class="service-chip">{{ $service->name }}</span>
                    @endforeach
                </div>
                <div class="small text-secondary mt-3">
                    Смен: {{ $order->scheduleSlots->count() }} • Совпало услуг: {{ $order->match_count }}
                </div>
                <div class="mt-3 d-flex gap-2 flex-wrap">
                    @if($order->my_application)
                        <span class="badge {{ $order->my_application->status_badge_class }}">{{ $order->my_application->status_label }}</span>
                    @else
                        <form action="{{ route('caregiver.orders.apply', $order) }}" method="POST">
                            @csrf
                            <button class="btn btn-dark rounded-pill">Откликнуться</button>
                        </form>
                    @endif
                    <a href="{{ route('caregiver.orders.show', $order) }}" class="btn btn-outline-dark rounded-pill">Открыть заказ</a>
                </div>
            </div>
        @empty
            <p class="text-secondary mb-0">Сейчас нет открытых заказов, подходящих вам по услугам, городу и расписанию.</p>
        @endforelse
    </div>
</div>
@endsection
