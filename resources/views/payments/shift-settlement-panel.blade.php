@php
    $viewer = auth()->user();
    $assignmentQuery = $order->caregiverAssignments()
        ->with(['scheduleSlot', 'caregiver', 'payout'])
        ->whereIn('status', ['accepted', 'completed'])
        ->orderBy('order_schedule_slot_id');

    if ($viewer->isCaregiver()) {
        $assignmentQuery->where('caregiver_id', $viewer->id);
    }

    $settlementAssignments = $assignmentQuery->get();
@endphp

@if(($viewer->isClient() || $viewer->isCaregiver()) && $settlementAssignments->isNotEmpty())
<div class="container mt-3">
    <div class="card-soft p-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
            <div>
                <div class="text-uppercase small text-secondary">Расчеты по сменам</div>
                <h2 class="h4 mb-1">Каждая смена оплачивается отдельно</h2>
                <p class="text-secondary mb-0">Завершение одной смены не закрывает весь заказ и не задерживает выплаты другим сиделкам.</p>
            </div>
            @if($viewer->isCaregiver())
                <a href="{{ route('caregiver.payouts.index') }}" class="btn btn-outline-dark rounded-pill">Мои выплаты</a>
            @endif
        </div>

        <div class="row g-3">
            @foreach($settlementAssignments as $assignment)
                @php
                    $slot = $assignment->scheduleSlot;
                    $endsAt = $slot
                        ? \Illuminate\Support\Carbon::parse($slot->scheduled_date->format('Y-m-d') . ' ' . $slot->ends_at)
                        : now()->subMinute();
                    $shiftEnded = $endsAt->isPast();
                    $payout = $assignment->payout;
                    $waitingClient = $assignment->status === 'accepted' && $assignment->completion_requested_at;
                @endphp
                <div class="col-xl-6">
                    <div class="border rounded-4 p-3 h-100">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <strong>{{ $assignment->caregiver?->name ?: 'Сиделка' }}</strong>
                                <div class="small text-secondary">
                                    @if($slot)
                                        {{ $slot->scheduled_date->format('d.m.Y') }} {{ substr($slot->starts_at, 0, 5) }}–{{ substr($slot->ends_at, 0, 5) }}
                                    @else
                                        Смена без отдельного календарного слота
                                    @endif
                                </div>
                            </div>
                            @if($assignment->status === 'completed')
                                <span class="badge text-bg-success">Смена подтверждена</span>
                            @elseif($waitingClient)
                                <span class="badge text-bg-warning">Ждет клиента</span>
                            @else
                                <span class="badge text-bg-info">В работе</span>
                            @endif
                        </div>

                        @if($assignment->completion_note)
                            <div class="small bg-light rounded-3 p-2 mt-3">{{ $assignment->completion_note }}</div>
                        @endif

                        @if($payout)
                            <div class="border-top mt-3 pt-3">
                                <div class="d-flex justify-content-between"><span>Начислено</span><strong>{{ number_format($payout->gross_amount, 0, ',', ' ') }} ₽</strong></div>
                                <div class="d-flex justify-content-between"><span>Комиссия</span><strong>{{ number_format($payout->commission_amount, 0, ',', ' ') }} ₽</strong></div>
                                <div class="d-flex justify-content-between"><span>К выплате</span><strong>{{ number_format($payout->amount, 0, ',', ' ') }} ₽</strong></div>
                                <div class="small mt-2 {{ $payout->status === 'paid' ? 'text-success' : 'text-warning' }}">
                                    {{ $payout->status === 'paid' ? 'Деньги переведены' : 'Выплата сформирована и ожидает перевода' }}
                                </div>
                            </div>
                        @elseif($order->status === 'in_progress' && $shiftEnded)
                            @if($viewer->isCaregiver() && $assignment->status === 'accepted' && ! $waitingClient)
                                <form action="{{ route('caregiver.assignments.complete-request', [$order, $assignment]) }}" method="POST" class="mt-3">
                                    @csrf
                                    <textarea name="completion_note" class="form-control mb-2" rows="2" placeholder="Кратко укажите, как прошла смена (необязательно)"></textarea>
                                    <button class="btn btn-dark rounded-pill">Смена отработана</button>
                                </form>
                            @elseif($viewer->isCaregiver() && $waitingClient)
                                <div class="alert alert-warning rounded-4 py-2 mt-3 mb-0">Клиенту отправлен запрос на подтверждение и формирование выплаты.</div>
                            @elseif($viewer->isClient() && $assignment->status === 'accepted')
                                <form action="{{ route('client.assignments.confirm', [$order, $assignment]) }}" method="POST" class="mt-3">
                                    @csrf
                                    <button class="btn btn-success rounded-pill">Подтвердить смену и сформировать выплату</button>
                                </form>
                            @endif
                        @elseif(! $shiftEnded)
                            <div class="small text-secondary mt-3">Завершение станет доступно после {{ $endsAt->format('d.m.Y H:i') }}.</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endif
