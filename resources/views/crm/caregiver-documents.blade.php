@extends('layouts.app')

@php($title = 'Документы сиделок')

@section('content')
<div class="container-fluid px-3 px-xl-5 py-4">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
        <div>
            <div class="text-uppercase small text-secondary">Допуск к сменам</div>
            <h1 class="section-title mb-0">Документы сиделок</h1>
        </div>
        <form class="d-flex gap-2">
            <select name="status" class="form-select">
                <option value="">Все документы</option>
                <option value="expired" {{ request('status') === 'expired' ? 'selected' : '' }}>Просроченные</option>
                <option value="expiring" {{ request('status') === 'expiring' ? 'selected' : '' }}>Истекают за 30 дней</option>
                <option value="unverified" {{ request('status') === 'unverified' ? 'selected' : '' }}>Не проверены</option>
            </select>
            <button class="btn btn-dark">Показать</button>
        </form>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="metric"><div class="value text-danger">{{ $stats['expired'] }}</div><div>Просрочено</div></div></div>
        <div class="col-md-4"><div class="metric"><div class="value text-warning">{{ $stats['expiring'] }}</div><div>Истекает за 30 дней</div></div></div>
        <div class="col-md-4"><div class="metric"><div class="value">{{ $stats['blocking'] }}</div><div>Документов с блокировкой</div></div></div>
    </div>

    <div class="card-soft p-4">
        @forelse($documents as $document)
            @php($expired = $document->isExpired())
            <form action="{{ route('crm.caregiver-documents.update', $document) }}" method="POST" class="border rounded-4 p-3 mb-3 {{ $expired ? 'border-danger border-2' : '' }}">
                @csrf
                @method('PATCH')
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div>
                        <strong>{{ $document->user?->name }} — {{ $document->title ?: $document->type_label }}</strong>
                        <div class="small text-secondary">
                            {{ $document->type_label }}
                            • № {{ $document->document_number ?: 'не указан' }}
                            • срок {{ $document->expires_at?->format('d.m.Y') ?: 'бессрочно' }}
                        </div>
                        <div class="small text-secondary mt-1">
                            Выдан: {{ $document->issued_at?->format('d.m.Y') ?: 'не указано' }}
                        </div>
                        @if($document->file_path)
                            <a href="{{ route('contracts.document.download', $document) }}" class="btn btn-sm btn-outline-dark rounded-pill mt-2">Открыть скан</a>
                        @else
                            <span class="badge text-bg-secondary mt-2">Файл не загружен</span>
                        @endif
                    </div>
                    <span class="badge {{ $expired ? 'text-bg-danger' : ($document->verification_status === 'verified' ? 'text-bg-success' : ($document->verification_status === 'rejected' ? 'text-bg-danger' : 'text-bg-warning')) }}">
                        {{ $expired ? 'Просрочен' : $document->status_label }}
                    </span>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-md-3">
                        <label class="form-label small">Проверка</label>
                        <select name="verification_status" class="form-select">
                            <option value="pending" {{ $document->verification_status === 'pending' ? 'selected' : '' }}>На проверке</option>
                            <option value="verified" {{ $document->verification_status === 'verified' ? 'selected' : '' }}>Проверен</option>
                            <option value="rejected" {{ $document->verification_status === 'rejected' ? 'selected' : '' }}>Отклонен</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Срок действия</label>
                        <input type="date" name="expires_at" class="form-control" value="{{ $document->expires_at?->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="is_required" value="1" {{ $document->is_required ? 'checked' : '' }}>
                            <span>Обязательный</span>
                        </label>
                    </div>
                    <div class="col-md-2">
                        <label class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="blocks_assignments" value="1" {{ $document->blocks_assignments ? 'checked' : '' }}>
                            <span>Блокирует смены</span>
                        </label>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-outline-dark w-100">Сохранить</button>
                    </div>
                    <div class="col-12">
                        <textarea name="notes" class="form-control" rows="2" placeholder="Комментарий проверки">{{ $document->notes }}</textarea>
                    </div>
                </div>
            </form>
        @empty
            <p class="text-secondary mb-0">Документы не найдены.</p>
        @endforelse

        {{ $documents->links() }}
    </div>
</div>
@endsection
