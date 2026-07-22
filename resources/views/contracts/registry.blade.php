@extends('layouts.app')

@php($title = 'Реестр договоров CRM')

@section('content')
<div class="container-fluid px-3 px-xl-5 py-4">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
        <div>
            <div class="text-uppercase small text-secondary">Агентская модель площадки</div>
            <h1 class="section-title mb-0">Договоры и комиссии</h1>
        </div>
        <a href="{{ route('crm.dashboard') }}" class="btn btn-outline-dark rounded-pill">Вернуться в CRM</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl"><div class="metric"><div class="value">{{ $stats['awaiting'] }}</div><div>Ожидают подписей</div></div></div>
        <div class="col-md-6 col-xl"><div class="metric"><div class="value">{{ $stats['signed'] }}</div><div>Подписано</div></div></div>
        <div class="col-md-6 col-xl"><div class="metric"><div class="value">{{ $stats['expired'] }}</div><div>Истекли без подписи</div></div></div>
        <div class="col-md-6 col-xl"><div class="metric"><div class="value">{{ number_format($stats['commission_month'], 0, ',', ' ') }} ₽</div><div>Комиссия за месяц</div></div></div>
        <div class="col-md-6 col-xl"><div class="metric"><div class="value">{{ number_format($stats['commission_total'], 0, ',', ' ') }} ₽</div><div>Комиссия всего</div></div></div>
    </div>

    <div class="card-soft p-4 mb-4">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-lg-5">
                <label class="form-label">Поиск</label>
                <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Номер, ФИО, телефон или email">
            </div>
            <div class="col-md-4 col-lg-3">
                <label class="form-label">Тип</label>
                <select name="type" class="form-select">
                    <option value="">Все документы</option>
                    <option value="client_agency" {{ request('type') === 'client_agency' ? 'selected' : '' }}>Агентский с заказчиком</option>
                    <option value="caregiver_agency" {{ request('type') === 'caregiver_agency' ? 'selected' : '' }}>Агентский с сиделкой</option>
                    <option value="order_service" {{ request('type') === 'order_service' ? 'selected' : '' }}>По заказу</option>
                </select>
            </div>
            <div class="col-md-4 col-lg-2">
                <label class="form-label">Статус</label>
                <select name="status" class="form-select">
                    <option value="">Все</option>
                    <option value="awaiting_signatures" {{ request('status') === 'awaiting_signatures' ? 'selected' : '' }}>Ожидает</option>
                    <option value="signed" {{ request('status') === 'signed' ? 'selected' : '' }}>Подписан</option>
                    <option value="superseded" {{ request('status') === 'superseded' ? 'selected' : '' }}>Заменён</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Отменён</option>
                </select>
            </div>
            <div class="col-md-4 col-lg-2 d-grid">
                <button class="btn btn-dark">Применить</button>
            </div>
        </form>
    </div>

    <div class="card-soft p-4">
        <div class="table-responsive">
            <table class="table crm-table align-middle">
                <thead>
                    <tr>
                        <th>Документ</th>
                        <th>Стороны</th>
                        <th>Заказ</th>
                        <th>Статус</th>
                        <th>Срок</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($contracts as $legalContract)
                        <tr>
                            <td>
                                <strong>{{ $legalContract->number }}</strong>
                                <div class="small text-secondary">{{ $legalContract->title }} • версия {{ $legalContract->version }}</div>
                            </td>
                            <td>
                                @foreach($legalContract->parties->where('role', '!=', 'platform') as $party)
                                    <div class="small">
                                        {{ $party->name }}
                                        <span class="badge {{ $party->status === 'signed' ? 'text-bg-success' : 'text-bg-warning' }}">{{ $party->status === 'signed' ? 'подписал' : 'ожидается' }}</span>
                                    </div>
                                @endforeach
                            </td>
                            <td>
                                @if($legalContract->order)
                                    #{{ $legalContract->order->id }}<br>
                                    <span class="small text-secondary">{{ $legalContract->order->title }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $legalContract->status === 'signed' ? 'text-bg-success' : ($legalContract->status === 'awaiting_signatures' ? 'text-bg-warning' : 'text-bg-secondary') }}">
                                    {{ $legalContract->status }}
                                </span>
                            </td>
                            <td>
                                @if($legalContract->status === 'awaiting_signatures' && $legalContract->expires_at)
                                    <span class="{{ $legalContract->expires_at->isPast() ? 'text-danger fw-bold' : '' }}">{{ $legalContract->expires_at->format('d.m.Y H:i') }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('legal.contracts.show', $legalContract) }}" class="btn btn-sm btn-outline-dark rounded-pill">Открыть</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-secondary py-4">Документы не найдены.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $contracts->links() }}</div>
    </div>
</div>
@endsection
