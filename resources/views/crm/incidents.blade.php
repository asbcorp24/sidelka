@extends('layouts.app')
@php($title = 'Инциденты безопасности')
@section('content')
<div class="container-fluid px-3 px-xl-5 py-4">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4"><div><div class="text-uppercase small text-secondary">Безопасность подопечных</div><h1 class="section-title mb-0">Инциденты</h1></div><form class="d-flex gap-2"><select name="severity" class="form-select"><option value="">Любая важность</option>@foreach(\App\Models\SafetyIncident::SEVERITY_LABELS as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select><select name="status" class="form-select"><option value="">Все статусы</option><option value="open">Открыт</option><option value="in_progress">В работе</option><option value="resolved">Решён</option><option value="closed">Закрыт</option></select><button class="btn btn-dark">Показать</button></form></div>

    @forelse($incidents as $incident)
        @php($critical = in_array($incident->severity,['high','critical'],true))
        <div class="card-soft p-4 mb-4 {{ $critical && !in_array($incident->status,['resolved','closed'],true) ? 'border border-danger border-2' : '' }}">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div><div class="small text-secondary">{{ strtoupper(substr($incident->public_id,0,8)) }} • заказ #{{ $incident->order_id }} • {{ $incident->occurred_at->format('d.m.Y H:i') }}</div><h2 class="h5 mb-1">{{ \App\Models\SafetyIncident::TYPE_LABELS[$incident->incident_type] ?? $incident->incident_type }}</h2><div>{{ $incident->description }}</div><div class="small text-secondary mt-2">Сообщил: {{ $incident->reportedBy?->name }} • сиделка: {{ $incident->assignment?->caregiver?->name ?: 'не привязана' }}</div></div>
                <div class="text-end"><span class="badge {{ $incident->severity === 'critical' ? 'text-bg-danger' : ($incident->severity === 'high' ? 'text-bg-warning' : 'text-bg-info') }}">{{ \App\Models\SafetyIncident::SEVERITY_LABELS[$incident->severity] }}</span><div class="small mt-2">{{ $incident->status }}</div></div>
            </div>
            @if($incident->emergency_called)<div class="alert alert-danger rounded-4 py-2 mt-3">Экстренная помощь вызвана. Номер: {{ $incident->emergency_service_reference ?: 'не указан' }}</div>@endif
            <div class="row g-4 mt-1">
                <div class="col-lg-7"><h3 class="h6">Хронология</h3>@foreach($incident->updates as $update)<div class="border-start border-3 ps-3 py-2 mb-2"><strong>{{ $update->author?->name }}</strong><div>{{ $update->body }}</div><div class="small text-secondary">{{ $update->created_at->format('d.m.Y H:i') }} {{ $update->status_to ? '• '.$update->status_to : '' }} {{ $update->is_internal ? '• внутренняя' : '' }}</div></div>@endforeach</div>
                <div class="col-lg-5"><form action="{{ route('crm.incidents.update',$incident) }}" method="POST" class="border rounded-4 p-3">@csrf @method('PATCH')<select name="status" class="form-select mb-2"><option value="open" {{ $incident->status==='open'?'selected':'' }}>Открыт</option><option value="in_progress" {{ $incident->status==='in_progress'?'selected':'' }}>В работе</option><option value="resolved" {{ $incident->status==='resolved'?'selected':'' }}>Решён</option><option value="closed" {{ $incident->status==='closed'?'selected':'' }}>Закрыт</option></select><select name="assigned_to_id" class="form-select mb-2"><option value="">Назначить себе</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" {{ $incident->assigned_to_id===$employee->id?'selected':'' }}>{{ $employee->name }}</option>@endforeach</select><textarea name="body" class="form-control mb-2" rows="3" placeholder="Что сделано сейчас" required></textarea><textarea name="resolution" class="form-control mb-2" rows="3" placeholder="Итоговое решение">{{ $incident->resolution }}</textarea><label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_internal" value="1"><span>Внутренняя запись</span></label><button class="btn btn-dark w-100">Сохранить</button></form></div>
            </div>
        </div>
    @empty
        <div class="card-soft p-5 text-center text-secondary">Инцидентов не найдено.</div>
    @endforelse
    {{ $incidents->links() }}
</div>
@endsection
