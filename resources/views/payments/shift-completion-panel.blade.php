@php
    $shiftAssignments = $order->caregiverAssignments()
        ->with(['caregiver', 'scheduleSlot', 'payout'])
        ->whereIn('status', ['accepted', 'completed'])
        ->orderBy('order_schedule_slot_id')
        ->get();

    if (auth()->user()->isCaregiver()) {
        $shiftAssignments = $shiftAssignments->where('caregiver_id', auth()->id())->values();
    }
@endphp

@if($order->status === 'in_progress' && $shiftAssignments->isNotEmpty())
<div class="container mt-3">
    <div class="card-soft p-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
            <div>
                <div class="text-uppercase small text-secondary">Расчёты по сменам</div>
                <h2 class="h4 mb-1">Оплата каждой сиделке отдельно</h2>
                <p class="text-secondary mb-0">Завершение одной смены не закрывает весь заказ и не задерживает выплаты другим сиделкам.</p>
            </div>
        </div>

        @foreach($shiftAssignments as $assignment)
            @php
                $slot = $assignment->scheduleSlot;
                $endsAt = $slot
                    ? \Illuminate\Support\Carbon::parse($slot->scheduled_date->format('Y-m-d').' '.$slot->ends_at)
                    : null;
                $shiftEnded = ! $endsAt || $endsAt->isPast();
            @endphp
            <div class="border rounded-4 p-3 mb-3">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div>
                        <strong>{{ $assignment->caregiver?->name ?: 'Сиделка' }}</strong>
                        <div class="small text-secondary">
                            @if($slot)
                                {{ $slot->scheduled_date->format('d.m.Y') }} · {{ substr($slot->starts_at, 0, 5) }}–{{ substr($slot->ends_at, 0, 5) }}
                            @else
                                Смена без отдельного календарного интервала
                            @endif
                        </div>
                        @if($assignment->completion_note)
                            <div class="small mt-2">Комментарий: {{ $assignment->completion_note }}</div>
                        @endif
                    </div>

                    <div class="text-end">
                        @if($assignment->payout)
                            <span class="badge {{ $assignment->payout->status === 'paid' ? 'text-bg-success' : 'text-bg-warning' }}">
                                {{ $assignment->payout->status === 'paid' ? 'Выплачено' : 'Выплата сформирована' }}
                            </span>
                            <div class="fw-bold mt-1">{{ number_format($assignment->payout->amount, 0, ',', ' ') }} ₽</div>
                        @elseif($assignment->status === 'completed')
                            <span class="badge text-bg-warning">Ожидает формирования выплаты</span>
                        @elseif($assignment->completion_requested_at)
                            <span class="badge text-bg-info">Сиделка ждёт подтверждения</span>
                        @elseif(!$shiftEnded)
                            <span class="badge text-bg-secondary">Смена ещё идёт</span>
                        @else
                            <span class="badge text-bg-light">Можно завершить</span>
                        @endif
                    </div>
                </div>

                @if(auth()->user()->isCaregiver() && $assignment->caregiver_id === auth()->id() && $assignment->status === 'accepted' && !$assignment->completion_requested_at && $shiftEnded)
                    <form action="{{ route('caregiver.assignments.complete-request', [$order, $assignment]) }}" method="POST" class="mt-3">
                        @csrf
                        <textarea name="completion_note" class="form-control mb-2" rows="2" placeholder="Комментарий по выполненной смене, необязательно"></textarea>
                        <button class="btn btn-success rounded-pill">Смена отработана — запросить выплату</button>
                    </form>
                @endif

                @if(auth()->user()->isClient() && $order->client_id === auth()->id() && $assignment->status === 'accepted' && $shiftEnded)
                    <form action="{{ route('client.assignments.confirm', [$order, $assignment]) }}" method="POST" class="mt-3">
                        @csrf
                        <button class="btn btn-success rounded-pill">
                            Подтвердить смену и сформировать выплату
                        </button>
                        @if(!$assignment->completion_requested_at)
                            <div class="small text-secondary mt-2">Подтвердить можно и без запроса сиделки, если смена фактически выполнена.</div>
                        @endif
                    </form>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endif
