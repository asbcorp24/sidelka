@extends('layouts.app')

@php($title = 'CRM Kanban')

@section('content')
<div class="container-fluid px-3 px-xl-5 py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <div class="text-uppercase small text-secondary">CRM-воронка</div>
            <h1 class="section-title mb-0">Kanban заявок</h1>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('crm.dashboard') }}" class="btn btn-outline-dark rounded-pill">Назад в CRM</a>
            <a href="{{ route('crm.long-orders.index') }}" class="btn btn-dark rounded-pill">Долгие заказы</a>
        </div>
    </div>

    <style>
        .kanban-board { display:grid; grid-template-columns:repeat(8,minmax(240px,1fr)); gap:1rem; overflow:auto; padding-bottom:1rem; }
        .kanban-col { background:#fff; border:1px solid rgba(31,111,120,.12); border-radius:24px; min-height:280px; padding:1rem; }
        .kanban-card { border:1px solid rgba(31,111,120,.12); border-radius:18px; padding:.9rem 1rem; background:#f9fcfb; cursor:grab; }
        .kanban-drop { outline:2px dashed rgba(31,111,120,.35); outline-offset:4px; }
    </style>

    <div class="kanban-board">
        @foreach($statuses as $status)
            <div class="kanban-col" data-status="{{ $status }}">
                <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                    <strong>{{ $statusLabels[$status] ?? $status }}</strong>
                    <span class="badge text-bg-light">{{ ($requestsByStatus[$status] ?? collect())->count() }}</span>
                </div>

                <div class="d-grid gap-3">
                    @foreach(($requestsByStatus[$status] ?? collect()) as $item)
                        <div class="kanban-card" draggable="true" data-request-id="{{ $item->public_id }}">
                            <div class="d-flex justify-content-between gap-2">
                                <a href="{{ route('crm.requests.show', $item) }}" class="fw-bold text-decoration-none">{{ $item->caller_name }}</a>
                                <span class="small text-secondary">{{ strtoupper(substr($item->public_id, 0, 6)) }}</span>
                            </div>
                            <div class="small text-secondary mt-1">{{ $item->city ?: 'Город не указан' }} • {{ $item->caller_phone }}</div>
                            <div class="small text-secondary mt-1">{{ $item->responsible?->name ?: 'Без менеджера' }}</div>
                            @if($item->caregiverUser)
                                <div class="small mt-2"><span class="badge text-bg-success">Сиделка: {{ $item->caregiverUser->name }}</span></div>
                            @endif
                            @if($item->order)
                                <div class="small mt-2"><span class="badge text-bg-dark">Заказ #{{ $item->order->id }}</span></div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <form id="kanban-status-form" method="POST" class="d-none">
        @csrf
        <input type="hidden" name="status" id="kanban-status-input">
    </form>
</div>
@endsection

@push('scripts')
<script>
    const cards = document.querySelectorAll('.kanban-card');
    const columns = document.querySelectorAll('.kanban-col');
    const form = document.getElementById('kanban-status-form');
    const statusInput = document.getElementById('kanban-status-input');
    let draggedRequestId = null;

    cards.forEach((card) => {
        card.addEventListener('dragstart', () => {
            draggedRequestId = card.dataset.requestId;
        });
    });

    columns.forEach((column) => {
        column.addEventListener('dragover', (event) => {
            event.preventDefault();
            column.classList.add('kanban-drop');
        });
        column.addEventListener('dragleave', () => column.classList.remove('kanban-drop'));
        column.addEventListener('drop', (event) => {
            event.preventDefault();
            column.classList.remove('kanban-drop');
            if (!draggedRequestId) return;
            form.action = "{{ url('/crm/requests') }}/" + draggedRequestId + "/status";
            statusInput.value = column.dataset.status;
            form.submit();
        });
    });
</script>
@endpush

