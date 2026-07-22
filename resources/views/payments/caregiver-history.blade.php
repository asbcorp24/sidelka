@extends('layouts.app')

@php($title = 'История выплат')

@section('content')
<div class="container py-4 py-lg-5">
    <h1 class="section-title mb-4">История выплат сиделке</h1>

    <div class="card-soft p-4 mb-4">
        <h2 class="h4 mb-3">Выплаты</h2>
        @forelse($payouts as $payout)
            <div class="d-flex justify-content-between align-items-start gap-3 py-3 border-bottom">
                <div>
                    <div><strong>{{ $payout->order->title ?? 'Заказ' }}</strong></div>
                    <div class="small text-secondary">{{ $payout->created_at->format('d.m.Y H:i') }} • {{ $payout->status }}</div>
                    @if($payout->gross_amount !== null)
                        <div class="small mt-2">
                            Начислено: {{ number_format($payout->gross_amount, 0, ',', ' ') }} ₽
                            @if($payout->commission_amount > 0)
                                • комиссия площадки {{ number_format($payout->commission_percent, 2, ',', ' ') }}%: {{ number_format($payout->commission_amount, 0, ',', ' ') }} ₽
                            @else
                                • без комиссии
                            @endif
                        </div>
                    @endif
                </div>
                <div class="text-end">
                    <div class="small text-secondary">К выплате</div>
                    <div><strong class="fs-5">{{ number_format($payout->amount, 0, ',', ' ') }} ₽</strong></div>
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
