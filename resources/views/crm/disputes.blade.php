@extends('layouts.app')
@php($title = 'Споры по сменам')
@section('content')
<div class="container-fluid px-3 px-xl-5 py-4">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4"><div><div class="text-uppercase small text-secondary">Контроль качества и расчётов</div><h1 class="section-title mb-0">Споры по сменам</h1></div><form class="d-flex gap-2"><select name="status" class="form-select"><option value="">Все статусы</option><option value="open">Открытые</option><option value="in_review">На рассмотрении</option><option value="resolved">Решённые</option></select><button class="btn btn-dark">Показать</button></form></div>

    @forelse($disputes as $dispute)
        <div class="card-soft p-4 mb-4 {{ in_array($dispute->status,['open','in_review'],true) ? 'border border-danger-subtle' : '' }}">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div><div class="small text-secondary">Спор {{ strtoupper(substr($dispute->public_id,0,8)) }} • заказ #{{ $dispute->order_id }} • смена #{{ $dispute->order_caregiver_assignment_id }}</div><h2 class="h5 mb-1">{{ $dispute->assignment?->caregiver?->name }} — {{ $dispute->reason }}</h2><div>{{ $dispute->description }}</div></div>
                <span class="badge {{ $dispute->status === 'resolved' ? 'text-bg-success' : 'text-bg-danger' }}">{{ \App\Models\ShiftDispute::STATUS_LABELS[$dispute->status] ?? $dispute->status }}</span>
            </div>
            <div class="row g-4 mt-1">
                <div class="col-lg-7">
                    <h3 class="h6">История</h3>
                    @foreach($dispute->messages as $message)<div class="border-start border-3 ps-3 py-2 mb-2"><strong>{{ $message->author?->name }}</strong><div>{{ $message->body }}</div><div class="small text-secondary">{{ $message->created_at->format('d.m.Y H:i') }} {{ $message->is_internal ? '• внутренняя заметка' : '' }}</div></div>@endforeach
                    <form action="{{ route('shift-disputes.messages.store',$dispute) }}" method="POST" class="row g-2 mt-3">@csrf<div class="col-12"><textarea name="body" class="form-control" rows="2" placeholder="Комментарий или запрос документов" required></textarea></div><div class="col-md-6"><label class="form-check"><input type="checkbox" class="form-check-input" name="is_internal" value="1"><span>Только для сотрудников</span></label></div><div class="col-md-6 text-end"><button class="btn btn-outline-dark">Добавить</button></div></form>
                </div>
                <div class="col-lg-5">
                    @if(in_array($dispute->status,['open','in_review'],true))
                        <form action="{{ route('crm.shift-disputes.resolve',$dispute) }}" method="POST" class="border rounded-4 p-3">@csrf @method('PATCH')<h3 class="h6">Решение супервайзера</h3><select name="decision" class="form-select mb-2"><option value="approve_full">Подтвердить полную оплату</option><option value="approve_partial">Подтвердить частично</option><option value="reject_payment">Отказать в оплате</option></select><input type="number" name="approved_gross_amount" class="form-control mb-2" min="0" placeholder="Сумма до комиссии для частичной оплаты"><textarea name="resolution" class="form-control mb-2" rows="4" placeholder="Обоснование решения" required></textarea><button class="btn btn-success w-100">Принять решение</button></form>
                    @else
                        <div class="border rounded-4 p-3"><strong>Решение:</strong> {{ $dispute->decision }}<div class="mt-2">{{ $dispute->resolution }}</div><div class="mt-2">Согласованная сумма: {{ number_format($dispute->approved_gross_amount ?? 0,0,',',' ') }} ₽</div></div>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="card-soft p-5 text-center text-secondary">Споров не найдено.</div>
    @endforelse
    {{ $disputes->links() }}
</div>
@endsection
