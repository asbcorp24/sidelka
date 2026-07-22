@extends('layouts.app')

@php($title = 'Протокол подписания ' . $contract->number)

@section('content')
<div class="container py-4 py-lg-5">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <div class="text-uppercase small text-secondary">Доказательства электронной подписи</div>
            <h1 class="section-title mb-1">Протокол {{ $contract->number }}</h1>
            <div class="text-secondary">SHA-256: <code>{{ $contract->document_hash }}</code></div>
        </div>
        <a href="{{ route('legal.contracts.show', $contract) }}" class="btn btn-outline-dark rounded-pill">Вернуться к договору</a>
    </div>

    <div class="card-soft p-4 mb-4">
        <h2 class="h4 mb-3">Стороны</h2>
        @foreach($contract->parties as $party)
            <div class="border rounded-4 p-3 mb-3">
                <div class="d-flex justify-content-between flex-wrap gap-2">
                    <div>
                        <strong>{{ $party->name }}</strong>
                        <div class="small text-secondary">{{ $party->role }} • {{ $party->email }} • {{ $party->phone }}</div>
                    </div>
                    <span class="badge {{ $party->status === 'signed' ? 'text-bg-success' : 'text-bg-warning' }}">{{ $party->status }}</span>
                </div>
                @if($party->signature)
                    <dl class="row small mt-3 mb-0">
                        <dt class="col-sm-3">Метод</dt><dd class="col-sm-9">{{ $party->signature->method }}</dd>
                        <dt class="col-sm-3">Канал</dt><dd class="col-sm-9">{{ $party->signature->channel }}</dd>
                        <dt class="col-sm-3">Назначение</dt><dd class="col-sm-9">{{ $party->signature->destination }}</dd>
                        <dt class="col-sm-3">Дата</dt><dd class="col-sm-9">{{ $party->signature->signed_at->format('d.m.Y H:i:s') }}</dd>
                        <dt class="col-sm-3">IP</dt><dd class="col-sm-9">{{ $party->signature->ip_address ?: 'системная подпись площадки' }}</dd>
                        <dt class="col-sm-3">User-Agent</dt><dd class="col-sm-9 text-break">{{ $party->signature->user_agent ?: '—' }}</dd>
                        <dt class="col-sm-3">Хеш документа</dt><dd class="col-sm-9"><code>{{ $party->signature->document_hash }}</code></dd>
                    </dl>
                @endif
            </div>
        @endforeach
    </div>

    <div class="card-soft p-4">
        <h2 class="h4 mb-3">Журнал событий</h2>
        @forelse($contract->events->sortBy('created_at') as $event)
            <div class="border-bottom py-3">
                <div class="d-flex justify-content-between flex-wrap gap-2">
                    <strong>{{ $event->event }}</strong>
                    <span class="text-secondary">{{ $event->created_at->format('d.m.Y H:i:s') }}</span>
                </div>
                <div class="small text-secondary">Сотрудник/пользователь: {{ $event->actor?->name ?: 'публичная ссылка или система' }} • IP {{ $event->ip_address ?: '—' }}</div>
                @if($event->data)
                    <pre class="small bg-light rounded-3 p-2 mt-2 mb-0">{{ json_encode($event->data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                @endif
            </div>
        @empty
            <p class="text-secondary mb-0">Событий пока нет.</p>
        @endforelse
    </div>
</div>
@endsection
