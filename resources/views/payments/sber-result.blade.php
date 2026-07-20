@extends('layouts.app')

@php($title = 'Результат оплаты')

@section('content')
<div class="container py-5">
    <div class="card-soft p-4 p-lg-5 mx-auto" style="max-width: 720px;">
        @if($topUp->status === 'paid')
            <div class="display-5 mb-3">Оплата получена</div>
            <p class="lead">На баланс зачислено {{ number_format($topUp->amount, 0, ',', ' ') }} ₽.</p>
            <div class="alert alert-success">Статус подтверждён отдельным запросом к платёжному шлюзу Сбера.</div>
        @elseif(in_array($topUp->status, ['pending', 'awaiting_payment'], true))
            <div class="display-6 mb-3">Платёж обрабатывается</div>
            <p class="lead">Сбер ещё не подтвердил зачисление {{ number_format($topUp->amount, 0, ',', ' ') }} ₽.</p>
            <div class="alert alert-warning">Баланс будет пополнен только после получения подтверждённого статуса оплаты.</div>
        @else
            <div class="display-6 mb-3">Оплата не завершена</div>
            <p class="lead">Средства на баланс не зачислены.</p>
            <div class="alert alert-danger">
                {{ $topUp->error_message ?: ($isFailPage ? 'Платёж был отменён или отклонён.' : 'Сбер не подтвердил оплату.') }}
            </div>
        @endif

        <dl class="row mt-4">
            <dt class="col-sm-5">Номер платежа</dt>
            <dd class="col-sm-7 text-break">{{ $topUp->order_number }}</dd>
            <dt class="col-sm-5">Сумма</dt>
            <dd class="col-sm-7">{{ number_format($topUp->amount, 0, ',', ' ') }} ₽</dd>
            <dt class="col-sm-5">Статус</dt>
            <dd class="col-sm-7"><span class="badge {{ $topUp->status_badge_class }}">{{ $topUp->status_label }}</span></dd>
        </dl>

        <div class="d-flex gap-2 flex-wrap mt-4">
            @auth
                @if(auth()->id() === $topUp->user_id)
                    <a href="{{ route('client.dashboard') }}" class="btn btn-dark rounded-pill px-4">Вернуться в кабинет</a>
                    <a href="{{ route('client.payments.index') }}" class="btn btn-outline-dark rounded-pill px-4">История оплат</a>
                @else
                    <a href="{{ route('home') }}" class="btn btn-dark rounded-pill px-4">На главную</a>
                @endif
            @else
                <a href="{{ route('login') }}" class="btn btn-dark rounded-pill px-4">Войти в кабинет</a>
            @endauth
        </div>
    </div>
</div>
@endsection
