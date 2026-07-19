@extends('layouts.app')

@php($title = 'История выплат')

@section('content')
<div class="container py-4 py-lg-5">
    <h1 class="section-title mb-4">История выплат сиделке</h1>

    <div class="card-soft p-4 mb-4">
        <h2 class="h4 mb-3">Выплаты</h2>
        @forelse($payouts as $payout)
            <div class="d-flex justify-content-between py-2 border-bottom">
                <div>
                    <div>{{ $payout->order->title ?? 'Заказ' }}</div>
                    <div class="small text-secondary">{{ $payout->created_at->format('d.m.Y H:i') }}</div>
                </div>
                <div class="text-end">
                    <div><strong>{{ number_format($payout->amount, 0, ',', ' ') }} ₽</strong></div>
                    <div class="small text-secondary">{{ $payout->status }}</div>
                </div>
            </div>
        @empty
            <p class="text-secondary mb-0">Выплат пока нет.</p>
        @endforelse
    </div>

    <div class="card-soft p-4">
        <h2 class="h4 mb-3">Допрасходы и отдельные услуги</h2>
        @forelse($expenses as $expense)
            <div class="d-flex justify-content-between py-2 border-bottom">
                <div>
                    <div>{{ $expense->title }}</div>
                    <div class="small text-secondary">{{ $expense->order->title ?? 'Заказ' }} • {{ $expense->status }}</div>
                </div>
                <strong>{{ number_format($expense->line_total, 0, ',', ' ') }} ₽</strong>
            </div>
        @empty
            <p class="text-secondary mb-0">Допрасходов пока нет.</p>
        @endforelse
    </div>
</div>
@endsection
