@extends('layouts.app')

@php($title = 'Отзывы на клиентов')

@section('content')
<div class="container py-4 py-lg-5">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
        <div>
            <div class="text-uppercase small text-secondary">Кабинет сиделки</div>
            <h1 class="section-title mb-0">Отзывы на клиентов</h1>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('caregiver.dashboard') }}" class="btn btn-outline-dark rounded-pill px-4">Назад в кабинет</a>
            <a href="{{ route('caregiver.orders.history') }}" class="btn btn-dark rounded-pill px-4">История заказов</a>
        </div>
    </div>

    @if($pendingReviewOrders->isNotEmpty())
        <div class="card-soft p-4 mb-4">
            <h2 class="h4 mb-3">Можно оставить отзыв</h2>
            @foreach($pendingReviewOrders as $order)
                <div class="border rounded-4 p-3 mb-3">
                    <div class="d-flex justify-content-between flex-wrap gap-2">
                        <strong>{{ $order->title }}</strong>
                        <span class="badge {{ $order->status_badge_class }}">{{ $order->status_label }}</span>
                    </div>
                    <div class="small text-secondary mt-1">{{ $order->client->name ?? 'Клиент' }}</div>
                    <form action="{{ route('caregiver.orders.review.store', $order) }}" method="POST" class="row g-2 mt-2">
                        @csrf
                        <div class="col-md-2">
                            <select name="rating" class="form-select" required>
                                <option value="">Оценка</option>
                                @for($i = 5; $i >= 1; $i--)
                                    <option value="{{ $i }}">{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-md-8">
                            <input type="text" name="comment" class="form-control" placeholder="Как прошла работа с клиентом" required>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-dark w-100">Отправить</button>
                        </div>
                    </form>
                </div>
            @endforeach
        </div>
    @endif

    <div class="card-soft p-4">
        <h2 class="h4 mb-3">Оставленные отзывы</h2>
        @forelse($reviews as $review)
            <div class="border rounded-4 p-3 mb-3">
                <div class="d-flex justify-content-between flex-wrap gap-2">
                    <div>
                        <strong>{{ $review->subject->name ?? 'Клиент' }}</strong>
                        @if($review->order)
                            <div class="small text-secondary mt-1">{{ $review->order->title }}</div>
                        @endif
                    </div>
                    <span>{{ str_repeat('★', $review->rating) }}</span>
                </div>
                <p class="mb-1 mt-2">{{ $review->comment }}</p>
                <div class="text-secondary small">{{ optional($review->published_at)->format('d.m.Y H:i') }}</div>
            </div>
        @empty
            <p class="text-secondary mb-0">Отзывов на клиентов пока нет.</p>
        @endforelse
    </div>
</div>
@endsection
