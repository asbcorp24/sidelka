@extends('layouts.app')

@php($title = 'История заказов сиделки')

@section('content')
<div class="container py-4 py-lg-5">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
        <div>
            <div class="text-uppercase small text-secondary">Кабинет сиделки</div>
            <h1 class="section-title mb-0">История заказов</h1>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('caregiver.dashboard') }}" class="btn btn-outline-dark rounded-pill px-4">Назад в кабинет</a>
            <a href="{{ route('caregiver.orders.open') }}" class="btn btn-outline-dark rounded-pill px-4">Открытые заказы</a>
            <a href="{{ route('caregiver.reviews.clients') }}" class="btn btn-dark rounded-pill px-4">Отзывы на клиентов</a>
        </div>
    </div>

    <div class="card-soft p-3 p-lg-4 mb-4">
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('caregiver.orders.history', ['group' => 'current']) }}" class="btn {{ $group === 'current' ? 'btn-dark' : 'btn-outline-dark' }} rounded-pill px-4">Текущие</a>
            <a href="{{ route('caregiver.orders.history', ['group' => 'completed']) }}" class="btn {{ $group === 'completed' ? 'btn-dark' : 'btn-outline-dark' }} rounded-pill px-4">Завершенные</a>
            <a href="{{ route('caregiver.orders.history', ['group' => 'cancelled']) }}" class="btn {{ $group === 'cancelled' ? 'btn-dark' : 'btn-outline-dark' }} rounded-pill px-4">Отмененные</a>
        </div>
    </div>

    @php($orders = $group === 'completed' ? $completedOrders : ($group === 'cancelled' ? $cancelledOrders : $currentOrders))
    <div class="card-soft p-4">
        @forelse($orders as $order)
            <div class="border rounded-4 p-3 mb-3">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <strong>{{ $order->title }}</strong>
                        <div class="text-secondary small mt-1">{{ $order->client->name ?? 'Клиент' }} • {{ $order->city }}</div>
                    </div>
                    <div class="text-end">
                        <span class="badge {{ $order->status_badge_class }}">{{ $order->status_label }}</span>
                        <div class="mt-2"><span class="badge {{ $order->payment_status_badge_class }}">{{ $order->payment_status_label }}</span></div>
                    </div>
                </div>
                <div class="small text-secondary mt-2">
                    @if($order->scheduleSlots->isNotEmpty())
                        {{ $order->scheduleSlots->first()->scheduled_date->format('d.m.Y') }}
                        • смен: {{ $order->scheduleSlots->count() }}
                    @elseif($order->starts_at)
                        {{ $order->starts_at->format('d.m.Y H:i') }}
                    @endif
                    • счет {{ number_format($order->total_invoice_amount, 0, ',', ' ') }} ₽
                </div>
                <div class="mt-3">
                    <a href="{{ route('caregiver.orders.show', $order) }}" class="btn btn-dark rounded-pill">Открыть заказ</a>
                </div>
            </div>
        @empty
            <p class="text-secondary mb-0">
                @if($group === 'completed')
                    Завершенных заказов пока нет.
                @elseif($group === 'cancelled')
                    Отмененных заказов пока нет.
                @else
                    Текущих заказов пока нет.
                @endif
            </p>
        @endforelse
    </div>
</div>
@endsection
