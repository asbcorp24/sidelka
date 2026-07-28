@extends('layouts.app')

@php($title = 'CRM: качество и жалобы')

@section('content')
<div class="container-fluid px-3 px-xl-5 py-4">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
        <div>
            <div class="text-uppercase small text-secondary">Контроль качества</div>
            <h1 class="section-title mb-0">Жалобы, черный список и отчеты смен</h1>
        </div>
        <a href="{{ route('crm.dashboard') }}" class="btn btn-outline-dark rounded-pill px-4">Назад в CRM</a>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card-soft p-4">
                <h2 class="h4 mb-3">Жалобы и черный список</h2>
                @forelse($reports as $report)
                    <div class="border rounded-4 p-3 mb-3">
                        <div class="d-flex justify-content-between flex-wrap gap-2">
                            <strong>{{ $report->reporter?->name }} → {{ $report->reportedUser?->name }}</strong>
                            <span class="badge {{ $report->adds_to_blacklist ? 'text-bg-danger' : 'text-bg-warning' }}">
                                {{ $report->adds_to_blacklist ? 'Черный список' : 'Жалоба' }}
                            </span>
                        </div>
                        <div class="small text-secondary mt-1">
                            Заказ #{{ $report->order_id ?: '—' }} • {{ $report->created_at->format('d.m.Y H:i') }}
                        </div>
                        <div class="mt-2"><strong>Причина:</strong> {{ $report->reason }}</div>
                        @if($report->details)
                            <div class="mt-2 text-secondary">{{ $report->details }}</div>
                        @endif
                    </div>
                @empty
                    <p class="text-secondary mb-0">Жалоб пока нет.</p>
                @endforelse
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card-soft p-4">
                <h2 class="h4 mb-3">Отчеты по сменам</h2>
                @forelse($shiftReports as $report)
                    <div class="border rounded-4 p-3 mb-3">
                        <div class="d-flex justify-content-between flex-wrap gap-2">
                            <strong>{{ $report->caregiver?->name }}</strong>
                            <span class="small text-secondary">{{ $report->submitted_at?->format('d.m.Y H:i') ?: $report->created_at->format('d.m.Y H:i') }}</span>
                        </div>
                        <div class="small text-secondary mt-1">
                            Заказ #{{ $report->order_id }}
                            @if($report->assignment?->scheduleSlot)
                                • {{ $report->assignment->scheduleSlot->scheduled_date->format('d.m.Y') }} {{ substr($report->assignment->scheduleSlot->starts_at, 0, 5) }}-{{ substr($report->assignment->scheduleSlot->ends_at, 0, 5) }}
                            @endif
                        </div>
                        @if($report->completed_tasks)
                            <div class="mt-2"><strong>Сделано:</strong> {{ implode(', ', $report->completed_tasks) }}</div>
                        @endif
                        @if($report->summary)
                            <div class="mt-2">{{ $report->summary }}</div>
                        @endif
                        @if($report->health_changes)
                            <div class="mt-2 text-secondary"><strong>Изменения состояния:</strong> {{ $report->health_changes }}</div>
                        @endif
                    </div>
                @empty
                    <p class="text-secondary mb-0">Отчетов по сменам пока нет.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
