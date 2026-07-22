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
                    @if($payout->assignment?->scheduleSlot)
                        <div class="small mt-1">
                            Смена: {{ $payout->assignment->scheduleSlot->scheduled_date->format('d.m.Y') }}
                            {{ substr($payout->assignment->scheduleSlot->starts_at, 0, 5) }}–{{ substr($payout->assignment->scheduleSlot->ends_at, 0, 5) }}
                        </div>
                    @elseif($payout->payment?->kind !== 'base_order')
                        <div class="small mt-1">Компенсация подтвержденного расхода</div>
                    @endif
                    <div class="small text-secondary mt-1">
                        {{ $payout->created_at->format('d.m.Y H:i') }} •
                        @if($payout->status === 'paid')
                            <span class="text-success">переведено</span>
                        @elseif(in_array($payout->status, ['pending', 'processing'], true))
                            <span class="text-warning">ожидает банковского перевода</span>
                        @else
                            {{ $payout->status }}
                        @endif
                    </div>
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
                    @if($payout->status === 'paid' && $payout->external_reference)
                        <div class="small text-secondary mt-1">Операция: {{ $payout->external_reference }}</div>
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
