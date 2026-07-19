@extends('layouts.app')

@php($title = 'История оплат')

@section('content')
<div class="container py-4 py-lg-5">
    <h1 class="section-title mb-4">История оплат клиента</h1>

    <div class="card-soft p-4 mb-4">
        <h2 class="h4 mb-3">Баланс</h2>
        <div class="display-6">{{ number_format($user->wallet_balance, 0, ',', ' ') }} ₽</div>
    </div>

    <div class="card-soft p-4 mb-4">
        <h2 class="h4 mb-3">Платежи</h2>
        @forelse($payments as $payment)
            <div class="d-flex justify-content-between py-2 border-bottom">
                <div>
                    <div>{{ $payment->description }}</div>
                    <div class="small text-secondary">Заказ: {{ $payment->order->title ?? '—' }} • {{ $payment->created_at->format('d.m.Y H:i') }}</div>
                </div>
                <div class="text-end">
                    <div><strong>{{ number_format($payment->amount, 0, ',', ' ') }} ₽</strong></div>
                    <div class="small text-secondary">{{ $payment->status }}</div>
                </div>
            </div>
        @empty
            <p class="text-secondary mb-0">Платежей пока нет.</p>
        @endforelse
    </div>

    <div class="card-soft p-4 mb-4">
        <h2 class="h4 mb-3">Возвраты</h2>
        @forelse($refunds as $refund)
            <div class="d-flex justify-content-between py-2 border-bottom">
                <div>
                    <div>{{ $refund->reason }}</div>
                    <div class="small text-secondary">Заказ: {{ $refund->order->title ?? '—' }}</div>
                </div>
                <strong>{{ number_format($refund->amount, 0, ',', ' ') }} ₽</strong>
            </div>
        @empty
            <p class="text-secondary mb-0">Возвратов пока нет.</p>
        @endforelse
    </div>

    <div class="card-soft p-4">
        <h2 class="h4 mb-3">Движения по балансу</h2>
        @forelse($transactions as $transaction)
            <div class="d-flex justify-content-between py-2 border-bottom">
                <div>
                    <div>{{ $transaction->description }}</div>
                    <div class="small text-secondary">{{ $transaction->created_at->format('d.m.Y H:i') }}</div>
                </div>
                <div class="{{ $transaction->amount >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ $transaction->amount >= 0 ? '+' : '' }}{{ number_format($transaction->amount, 0, ',', ' ') }} ₽
                </div>
            </div>
        @empty
            <p class="text-secondary mb-0">Движений по балансу пока нет.</p>
        @endforelse
    </div>
</div>
@endsection
