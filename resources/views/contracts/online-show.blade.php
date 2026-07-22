@extends('layouts.app')

@php($title = $contract->title)

@section('content')
<div class="container py-4 py-lg-5">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <div class="text-uppercase small text-secondary">Онлайн-договор • {{ $contract->number }}</div>
            <h1 class="section-title mb-1">{{ $contract->title }}</h1>
            <div class="text-secondary">Версия {{ $contract->version }} • SHA-256: <code>{{ $contract->document_hash }}</code></div>
        </div>
        <div class="text-end">
            @if($contract->status === 'signed')
                <span class="badge text-bg-success fs-6">Полностью подписан</span>
            @elseif($contract->status === 'awaiting_signatures')
                <span class="badge text-bg-warning fs-6">Ожидает подписей</span>
            @else
                <span class="badge text-bg-secondary fs-6">{{ $contract->status }}</span>
            @endif
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card-soft p-4 p-lg-5 mb-4 contract-document">
                {!! $contract->body_html !!}
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card-soft p-4 mb-4">
                <h2 class="h4 mb-3">Стороны и подписи</h2>
                @foreach($contract->parties as $party)
                    <div class="border rounded-4 p-3 mb-3">
                        <div class="d-flex justify-content-between gap-2">
                            <div>
                                <strong>{{ $party->name }}</strong>
                                <div class="small text-secondary">
                                    @if($party->role === 'platform') Площадка / агент
                                    @elseif($party->role === 'client') Заказчик
                                    @elseif($party->role === 'caregiver') Сиделка / исполнитель
                                    @else {{ $party->role }}
                                    @endif
                                </div>
                            </div>
                            <span class="badge {{ $party->status === 'signed' ? 'text-bg-success' : 'text-bg-warning' }} align-self-start">
                                {{ $party->status === 'signed' ? 'Подписано' : 'Ожидается' }}
                            </span>
                        </div>
                        @if($party->signed_at)
                            <div class="small mt-2">{{ $party->signed_at->format('d.m.Y H:i:s') }}</div>
                        @endif

                        @auth
                            @if((auth()->user()->isAdmin() || auth()->user()->isCrm()) && $party->is_required && $party->status !== 'signed')
                                <form action="{{ route('legal.staff.send-code', $party) }}" method="POST" class="mt-3">
                                    @csrf
                                    <button class="btn btn-outline-dark btn-sm rounded-pill">Отправить код стороне</button>
                                </form>
                                <div class="small mt-2">
                                    Ссылка: <a href="{{ route('legal.public.show', $party) }}" target="_blank">открыть страницу подписания</a>
                                </div>
                            @endif
                        @endauth
                    </div>
                @endforeach
            </div>

            @if($partyContext && $partyContext->is_required && $partyContext->status !== 'signed' && $contract->status === 'awaiting_signatures')
                <div class="card-soft p-4 mb-4">
                    <h2 class="h4 mb-2">Подписать документ</h2>
                    <p class="text-secondary small">Код придет на подтвержденный телефон или email. Сотрудник CRM не имеет права просить назвать этот код.</p>

                    <form action="{{ $publicMode ? route('legal.public.code', $partyContext) : route('legal.contracts.code', $contract) }}" method="POST" class="mb-3">
                        @csrf
                        <button class="btn btn-outline-dark w-100 rounded-pill">Получить одноразовый код</button>
                    </form>

                    <form action="{{ $publicMode ? route('legal.public.sign', $partyContext) : route('legal.contracts.sign', $contract) }}" method="POST" class="row g-3">
                        @csrf
                        <div class="col-12">
                            <label class="form-label">Код из SMS или письма</label>
                            <input type="text" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" name="code" class="form-control form-control-lg text-center" required>
                        </div>
                        <div class="col-12">
                            <label class="form-check border rounded-4 p-3">
                                <input class="form-check-input me-2" type="checkbox" name="accept" value="1" required>
                                <span class="form-check-label">Я прочитал(а) неизменяемую версию документа, согласен(на) с условиями и использую одноразовый код как простую электронную подпись.</span>
                            </label>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-success w-100 rounded-pill">Подписать договор</button>
                        </div>
                    </form>
                </div>
            @elseif($partyContext && $partyContext->status === 'signed')
                <div class="alert alert-success rounded-4">Ваша подпись сохранена и связана с хешем документа.</div>
            @endif

            <div class="card-soft p-4">
                <div class="d-grid gap-2">
                    @if($publicMode)
                        <a href="{{ route('legal.public.pdf', $partyContext) }}" class="btn btn-outline-dark rounded-pill">Скачать PDF</a>
                    @else
                        <a href="{{ route('legal.contracts.pdf', $contract) }}" class="btn btn-outline-dark rounded-pill">Скачать PDF</a>
                        <a href="{{ route('legal.contracts.protocol', $contract) }}" class="btn btn-outline-dark rounded-pill">Протокол подписания</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .contract-document { line-height: 1.55; }
    .contract-document h1 { font-size: 1.7rem; text-align: center; margin-bottom: 1.5rem; }
    .contract-document h2 { font-size: 1.2rem; margin-top: 1.6rem; }
    .contract-document table { width: 100%; border-collapse: collapse; }
    .contract-document td, .contract-document th { vertical-align: top; }
    .contract-document code { word-break: break-all; }
</style>
@endpush
