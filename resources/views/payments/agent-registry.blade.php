@extends('layouts.app')

@php($title = 'Агентские выплаты')

@section('content')
<div class="container-fluid px-3 px-xl-5 py-4">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
        <div>
            <div class="text-uppercase small text-secondary">Расчеты площадки</div>
            <h1 class="section-title mb-0">Выплаты сиделкам</h1>
        </div>
        <a href="{{ route('crm.contracts.index') }}" class="btn btn-outline-dark rounded-pill">Договоры и комиссии</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3"><div class="metric"><div class="value">{{ $stats['pending_count'] }}</div><div>Ожидают перевода</div></div></div>
        <div class="col-md-6 col-xl-3"><div class="metric"><div class="value">{{ number_format($stats['pending_amount'], 0, ',', ' ') }} ₽</div><div>К переводу</div></div></div>
        <div class="col-md-6 col-xl-3"><div class="metric"><div class="value">{{ number_format($stats['paid_month'], 0, ',', ' ') }} ₽</div><div>Переведено за месяц</div></div></div>
        <div class="col-md-6 col-xl-3"><div class="metric"><div class="value">{{ number_format($stats['commission_month'], 0, ',', ' ') }} ₽</div><div>Комиссия за месяц</div></div></div>
    </div>

    <div class="card-soft p-4 mb-4">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-lg-7">
                <label class="form-label">Поиск</label>
                <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Сиделка, телефон, заказ или номер операции">
            </div>
            <div class="col-lg-3">
                <label class="form-label">Статус</label>
                <select name="status" class="form-select">
                    <option value="">Все</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Ожидает перевода</option>
                    <option value="processing" {{ request('status') === 'processing' ? 'selected' : '' }}>Обрабатывается</option>
                    <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>Выплачено</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Отменено</option>
                </select>
            </div>
            <div class="col-lg-2 d-grid"><button class="btn btn-dark">Применить</button></div>
        </form>
    </div>

    <div class="row g-4">
        @forelse($payouts as $payout)
            <div class="col-xl-6">
                <div class="card-soft p-4 h-100 {{ in_array($payout->status, ['pending', 'processing'], true) ? 'border border-warning' : '' }}">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                        <div>
                            <div class="text-uppercase small text-secondary">Выплата #{{ $payout->id }}</div>
                            <h2 class="h5 mb-1">{{ $payout->caregiver?->name ?: 'Сиделка не найдена' }}</h2>
                            <div class="small text-secondary">{{ $payout->caregiver?->phone }} • {{ $payout->caregiver?->email }}</div>
                        </div>
                        <span class="badge {{ $payout->status === 'paid' ? 'text-bg-success' : ($payout->status === 'cancelled' ? 'text-bg-secondary' : 'text-bg-warning') }}">{{ $payout->status }}</span>
                    </div>

                    <div class="border rounded-4 p-3 mb-3">
                        <div class="d-flex justify-content-between"><span>Заказ</span><strong>#{{ $payout->order_id }} {{ $payout->order?->title }}</strong></div>
                        <div class="d-flex justify-content-between mt-2"><span>Начислено</span><strong>{{ number_format($payout->gross_amount ?? $payout->amount, 0, ',', ' ') }} ₽</strong></div>
                        <div class="d-flex justify-content-between mt-2"><span>Комиссия {{ number_format($payout->commission_percent, 2, ',', ' ') }}%</span><strong>{{ number_format($payout->commission_amount, 0, ',', ' ') }} ₽</strong></div>
                        <div class="d-flex justify-content-between border-top pt-2 mt-2"><span>К переводу</span><strong class="fs-5">{{ number_format($payout->amount, 0, ',', ' ') }} ₽</strong></div>
                    </div>

                    @if($payout->caregiver?->contractProfile)
                        <div class="small mb-3">
                            <strong>Реквизиты:</strong><br>
                            {{ $payout->caregiver->contractProfile->bank_recipient_name ?: $payout->caregiver->name }}<br>
                            {{ $payout->caregiver->contractProfile->bank_name }}
                            @if($payout->caregiver->contractProfile->bank_bik) • БИК {{ $payout->caregiver->contractProfile->bank_bik }} @endif<br>
                            @if($payout->caregiver->contractProfile->bank_account)
                                Счет: {{ $payout->caregiver->contractProfile->bank_account }}
                            @elseif($payout->caregiver->contractProfile->card_number)
                                Карта: {{ $payout->caregiver->contractProfile->card_number }}
                            @else
                                <span class="text-danger">Банковские реквизиты не заполнены</span>
                            @endif
                        </div>
                    @else
                        <div class="alert alert-warning rounded-4 py-2">Договорные и банковские данные сиделки не заполнены.</div>
                    @endif

                    @if(in_array($payout->status, ['pending', 'processing'], true))
                        <form action="{{ route('crm.payouts.paid', $payout) }}" method="POST" class="row g-2">
                            @csrf
                            @method('PATCH')
                            <div class="col-12">
                                <input type="text" name="destination" class="form-control" value="{{ old('destination', $payout->destination) }}" placeholder="Куда переведено: счет / карта / СБП" required>
                            </div>
                            <div class="col-md-7">
                                <input type="text" name="external_reference" class="form-control" placeholder="Номер банковской операции" required>
                            </div>
                            <div class="col-md-5 d-grid">
                                <button class="btn btn-success">Подтвердить перевод</button>
                            </div>
                        </form>
                    @elseif($payout->status === 'paid')
                        <div class="alert alert-success rounded-4 mb-0">
                            Переведено {{ optional($payout->paid_at)->format('d.m.Y H:i') }}.<br>
                            Операция: <strong>{{ $payout->external_reference }}</strong><br>
                            Назначение: {{ $payout->destination }}
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-12"><div class="card-soft p-5 text-center text-secondary">Выплаты не найдены.</div></div>
        @endforelse
    </div>

    <div class="mt-4">{{ $payouts->links() }}</div>
</div>
@endsection
