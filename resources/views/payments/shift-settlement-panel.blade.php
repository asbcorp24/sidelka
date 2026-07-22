@php
    $viewer = auth()->user();
    $assignmentQuery = $order->caregiverAssignments()
        ->with(['scheduleSlot', 'caregiver', 'payout', 'act', 'disputes.messages'])
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
                <div class="text-uppercase small text-secondary">Акты и расчеты по сменам</div>
                <h2 class="h4 mb-1">Каждая смена оформляется и оплачивается отдельно</h2>
                <p class="text-secondary mb-0">Спор по одной смене не задерживает выплаты другим сиделкам.</p>
            </div>
            @if($viewer->isCaregiver())<a href="{{ route('caregiver.payouts.index') }}" class="btn btn-outline-dark rounded-pill">Мои выплаты</a>@endif
        </div>

        <div class="row g-3">
            @foreach($settlementAssignments as $assignment)
                @php
                    $slot = $assignment->scheduleSlot;
                    $endsAt = $slot ? \Illuminate\Support\Carbon::parse($slot->scheduled_date->format('Y-m-d').' '.$slot->ends_at) : now()->subMinute();
                    $shiftEnded = $endsAt->isPast();
                    $payout = $assignment->payout;
                    $act = $assignment->act;
                    $openDispute = $assignment->disputes->first(fn($item) => in_array($item->status, ['open', 'in_review'], true));
                    $waitingClient = $assignment->status === 'accepted' && $assignment->completion_requested_at;
                @endphp
                <div class="col-xl-6">
                    <div class="border rounded-4 p-3 h-100 {{ $openDispute ? 'border-danger border-2' : '' }}">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <strong>{{ $assignment->caregiver?->name ?: 'Сиделка' }}</strong>
                                <div class="small text-secondary">@if($slot){{ $slot->scheduled_date->format('d.m.Y') }} {{ substr($slot->starts_at,0,5) }}–{{ substr($slot->ends_at,0,5) }}@else Смена без отдельного слота @endif</div>
                            </div>
                            @if($openDispute)<span class="badge text-bg-danger">Спор открыт</span>
                            @elseif($assignment->status === 'completed')<span class="badge text-bg-success">Смена подтверждена</span>
                            @elseif($waitingClient)<span class="badge text-bg-warning">Ждет заказчика</span>
                            @else<span class="badge text-bg-info">В работе</span>@endif
                        </div>

                        @if($assignment->completion_note)<div class="small bg-light rounded-3 p-2 mt-3">{{ $assignment->completion_note }}</div>@endif

                        @if($act)
                            <details class="mt-3" {{ $openDispute ? 'open' : '' }}>
                                <summary class="fw-semibold">Акт {{ $act->number }}</summary>
                                <div class="small text-secondary mt-2">Хеш: {{ $act->document_hash }}</div>
                                <div class="d-flex justify-content-between mt-2"><span>Стоимость смены</span><strong>{{ number_format($act->gross_amount,0,',',' ') }} ₽</strong></div>
                                <div class="d-flex justify-content-between"><span>Комиссия</span><strong>{{ number_format($act->commission_amount,0,',',' ') }} ₽</strong></div>
                                <div class="d-flex justify-content-between"><span>К выплате</span><strong>{{ number_format($act->payout_amount,0,',',' ') }} ₽</strong></div>
                                <div class="border rounded-3 p-2 mt-2 small" style="max-height:220px;overflow:auto">{!! $act->body_html !!}</div>
                            </details>
                        @endif

                        @if($payout)
                            <div class="border-top mt-3 pt-3">
                                <div class="d-flex justify-content-between"><span>Начислено</span><strong>{{ number_format($payout->gross_amount,0,',',' ') }} ₽</strong></div>
                                <div class="d-flex justify-content-between"><span>Комиссия</span><strong>{{ number_format($payout->commission_amount,0,',',' ') }} ₽</strong></div>
                                <div class="d-flex justify-content-between"><span>К выплате</span><strong>{{ number_format($payout->amount,0,',',' ') }} ₽</strong></div>
                                <div class="small mt-2 {{ $payout->status === 'paid' ? 'text-success' : 'text-warning' }}">{{ $payout->status === 'paid' ? 'Деньги переведены' : ($payout->status === 'cancelled' ? 'Выплата отклонена решением' : 'Выплата сформирована и ожидает перевода') }}</div>
                            </div>
                        @elseif($openDispute)
                            <div class="alert alert-danger rounded-4 py-2 mt-3 mb-0">Выплата заморожена. Решение принимает супервайзер CRM.</div>
                        @elseif($order->status === 'in_progress' && $shiftEnded)
                            @if($viewer->isCaregiver() && $assignment->status === 'accepted' && ! $waitingClient)
                                <div class="mt-3"><div class="small text-secondary mb-2">Сначала отправьте журнал смены, затем сформируйте акт.</div><form action="{{ route('caregiver.assignments.complete-request', [$order,$assignment]) }}" method="POST">@csrf<textarea name="completion_note" class="form-control mb-2" rows="2" placeholder="Кратко укажите, как прошла смена"></textarea><button class="btn btn-dark rounded-pill">Смена отработана — сформировать акт</button></form></div>
                            @elseif($viewer->isCaregiver() && $waitingClient)
                                <div class="alert alert-warning rounded-4 py-2 mt-3 mb-0">Акт направлен заказчику.</div>
                            @elseif($viewer->isClient() && $assignment->status === 'accepted' && $waitingClient)
                                <form action="{{ route('client.assignments.confirm', [$order,$assignment]) }}" method="POST" class="mt-3">@csrf<textarea name="client_comment" class="form-control mb-2" rows="2" placeholder="Комментарий к журналу, необязательно"></textarea><button class="btn btn-success rounded-pill">Подписать акт и сформировать выплату</button></form>
                            @endif
                        @elseif(! $shiftEnded)
                            <div class="small text-secondary mt-3">Завершение станет доступно после {{ $endsAt->format('d.m.Y H:i') }}.</div>
                        @endif

                        @if(!$payout && !$openDispute && ($waitingClient || $assignment->completion_requested_at))
                            <details class="mt-3">
                                <summary class="text-danger">Открыть спор или претензию</summary>
                                <form action="{{ route('shift-disputes.store', [$order,$assignment]) }}" method="POST" class="row g-2 mt-2">@csrf<div class="col-md-6"><select name="reason" class="form-select"><option value="quality">Качество услуги</option><option value="time">Время смены</option><option value="scope">Объём работ</option><option value="behavior">Поведение стороны</option><option value="damage">Ущерб</option><option value="payment">Расчёт оплаты</option><option value="other">Другое</option></select></div><div class="col-md-6"><select name="requested_action" class="form-select"><option value="investigation">Проверка CRM</option><option value="full_payment">Полная оплата</option><option value="partial_payment">Частичная оплата</option><option value="no_payment">Без оплаты</option><option value="refund">Возврат</option></select></div><div class="col-12"><textarea name="description" class="form-control" rows="3" placeholder="Подробно опишите разногласие" required></textarea></div><div class="col-12"><button class="btn btn-outline-danger rounded-pill">Открыть спор</button></div></form>
                            </details>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endif
