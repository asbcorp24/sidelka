@extends('layouts.app')

@php($title = 'Заказ сиделки')

@section('content')
<div class="container py-4 py-lg-5">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <div class="text-uppercase small text-secondary">Карточка заказа</div>
            <h1 class="section-title mb-1">{{ $order->title }}</h1>
            <div class="text-secondary">{{ $order->client->name }} • {{ $order->city }}</div>
        </div>
        <div class="text-lg-end">
            <span class="badge {{ $order->status_badge_class }}">{{ $order->status_label }}</span>
            <div class="mt-2"><span class="badge {{ $order->payment_status_badge_class }}">{{ $order->payment_status_label }}</span></div>
            @if($order->allows_multiple_caregivers)
                <div class="mt-2"><span class="badge text-bg-dark">Долгий заказ с несколькими сиделками</span></div>
            @endif
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card-soft p-4 mb-4">
                <p>{{ $order->description }}</p>

                <div class="d-flex flex-wrap gap-2 mb-3">
                    @foreach($order->services as $service)
                        <span class="service-chip">{{ $service->name }}</span>
                    @endforeach
                </div>

                @if($order->assignments_by_slot->isNotEmpty())
                    <div class="mb-3">
                        <strong>Смены по заказу:</strong>
                        <div class="mt-3">
                            @foreach($order->assignments_by_slot as $slot)
                                <div class="border rounded-4 p-3 mb-3">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                        <div>
                                            <strong>{{ $slot->scheduled_date->format('d.m.Y') }} {{ substr($slot->starts_at, 0, 5) }}-{{ substr($slot->ends_at, 0, 5) }}</strong>
                                            @if($slot->label)
                                                <div class="small text-secondary mt-1">{{ $slot->label }}</div>
                                            @endif
                                        </div>
                                        @php($myAssignment = $slot->assignments_for_view->firstWhere('caregiver_id', $user->id))
                                        @if($myAssignment)
                                            <span class="badge {{ $myAssignment->status === 'accepted' || $myAssignment->status === 'completed' ? 'text-bg-success' : ($myAssignment->status === 'declined' ? 'text-bg-danger' : 'text-bg-warning') }}">
                                                {{ $myAssignment->status === 'accepted' ? 'Моя подтвержденная смена' : ($myAssignment->status === 'declined' ? 'Я отказалась' : ($myAssignment->status === 'completed' ? 'Моя завершенная смена' : 'Ждет моего решения')) }}
                                            </span>
                                        @endif
                                    </div>

                                    @if($slot->assignments_for_view->isNotEmpty())
                                        <div class="mt-3">
                                            @foreach($slot->assignments_for_view as $assignment)
                                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 border-top pt-2 mt-2">
                                                    <div>
                                                        <strong>{{ $assignment->caregiver?->name ?: 'Сиделка' }}</strong>
                                                        <div class="small text-secondary">
                                                            @if($assignment->status === 'invited')
                                                                Ожидает подтверждения
                                                            @elseif($assignment->status === 'accepted')
                                                                Смена подтверждена
                                                            @elseif($assignment->status === 'declined')
                                                                Отказ
                                                            @elseif($assignment->status === 'completed')
                                                                Смена выполнена
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <span class="badge {{ $assignment->status === 'accepted' || $assignment->status === 'completed' ? 'text-bg-success' : ($assignment->status === 'declined' ? 'text-bg-danger' : 'text-bg-warning') }}">
                                                        {{ $assignment->status === 'accepted' ? 'Подтверждено' : ($assignment->status === 'declined' ? 'Отказ' : ($assignment->status === 'completed' ? 'Завершено' : 'Ожидание')) }}
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span>Основной счет</span>
                    <strong>{{ number_format($order->base_amount, 0, ',', ' ') }} ₽</strong>
                </div>
                @foreach($order->clinicPartnerServices as $clinicService)
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>{{ $clinicService->clinic->name }} — {{ $clinicService->name }}</span>
                        <strong>{{ number_format($clinicService->pivot->price_at_booking, 0, ',', ' ') }} ₽</strong>
                    </div>
                @endforeach
                @foreach($order->expenses as $expense)
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <div>
                            <div>{{ $expense->title }}</div>
                            <div class="small text-secondary">
                                @if($expense->status === 'pending_approval')
                                    Ждет подтверждения клиента
                                @elseif($expense->status === 'rejected')
                                    Отклонено клиентом
                                @elseif($expense->status === 'approved')
                                    Согласовано
                                @elseif($expense->status === 'billed')
                                    Удержано с баланса клиента
                                @endif
                            </div>
                        </div>
                        <strong>{{ number_format($expense->line_total, 0, ',', ' ') }} ₽</strong>
                    </div>
                @endforeach
                <div class="d-flex justify-content-between pt-3 mt-2">
                    <strong>Итого по заказу</strong>
                    <strong>{{ number_format($order->total_invoice_amount, 0, ',', ' ') }} ₽</strong>
                </div>
            </div>

            @if($order->my_invited_slots_count > 0)
                <div class="card-soft p-4 mb-4">
                    <h2 class="h4 mb-3">Приглашение на смены</h2>
                    <p class="text-secondary">Вы подтверждаете или отклоняете только свои смены. Остальные назначения по заказу не затрагиваются.</p>
                    <div class="d-flex gap-2 flex-wrap">
                        <form action="{{ route('caregiver.orders.accept', $order) }}" method="POST">
                            @csrf
                            <button class="btn btn-dark rounded-pill">Подтвердить мои смены</button>
                        </form>
                        <form action="{{ route('caregiver.orders.decline', $order) }}" method="POST">
                            @csrf
                            <button class="btn btn-outline-dark rounded-pill">Отказаться от моих смен</button>
                        </form>
                    </div>
                </div>
            @endif

            @if($order->active_conversation && $order->active_conversation->status === 'active')
                <div class="card-soft p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <h2 class="h4 mb-0">Чат по заказу</h2>
                        <span class="text-secondary">Клиент: {{ $order->client->name }}</span>
                    </div>

                    @if(($order->unread_messages_count ?? 0) > 0)
                        <form action="{{ route('caregiver.orders.messages.read', $order) }}" method="POST" class="mb-3">
                            @csrf
                            <button class="btn btn-outline-dark rounded-pill">Отметить как прочитанное</button>
                        </form>
                    @endif

                    @foreach($order->active_conversation->messages as $message)
                        <div class="chat-bubble {{ $message->sender_id === $user->id ? 'caregiver' : 'client' }} mb-2">
                            <strong>{{ $message->sender->name }}</strong>
                            <div>{{ $message->body }}</div>
                        </div>
                    @endforeach

                    <form action="{{ route('caregiver.orders.messages.store', $order) }}" method="POST" class="mt-3">
                        @csrf
                        <div class="input-group">
                            <input type="text" name="body" class="form-control" placeholder="Напишите сообщение клиенту">
                            <button class="btn btn-dark" type="submit">Отправить</button>
                        </div>
                    </form>
                </div>
            @endif

            @if($order->patientProfile)
                <div class="card-soft p-4 mb-4">
                    <h2 class="h4 mb-3">Карточка пациента</h2>
                    <div class="row g-3 small">
                        @if($order->patientProfile->diagnosis)
                            <div class="col-md-6"><strong>Диагноз:</strong><br>{{ $order->patientProfile->diagnosis }}</div>
                        @endif
                        @if($order->patientProfile->limitations)
                            <div class="col-md-6"><strong>Ограничения:</strong><br>{{ $order->patientProfile->limitations }}</div>
                        @endif
                        @if($order->patientProfile->daily_routine)
                            <div class="col-md-6"><strong>Режим дня:</strong><br>{{ $order->patientProfile->daily_routine }}</div>
                        @endif
                        @if($order->patientProfile->medications)
                            <div class="col-md-6"><strong>Лекарства:</strong><br>{{ $order->patientProfile->medications }}</div>
                        @endif
                        @if($order->patientProfile->care_features)
                            <div class="col-md-6"><strong>Особенности ухода:</strong><br>{{ $order->patientProfile->care_features }}</div>
                        @endif
                        @if($order->patientProfile->emergency_contact_name || $order->patientProfile->emergency_contact_phone)
                            <div class="col-md-6"><strong>Экстренный контакт:</strong><br>{{ $order->patientProfile->emergency_contact_name }}{{ $order->patientProfile->emergency_contact_phone ? ' • '.$order->patientProfile->emergency_contact_phone : '' }}</div>
                        @endif
                    </div>
                </div>
            @endif

            @if(in_array($order->status, ['in_chat', 'in_progress'], true) && $order->my_assignments->isNotEmpty())
                <div class="card-soft p-4 mb-4">
                    <h2 class="h4 mb-3">Отчет по смене</h2>
                    @foreach($order->my_assignments as $assignment)
                        <form action="{{ route('caregiver.assignments.report.store', [$order, $assignment]) }}" method="POST" class="border rounded-4 p-3 mb-3">
                            @csrf
                            <div class="small text-secondary mb-2">
                                @if($assignment->scheduleSlot)
                                    Смена {{ $assignment->scheduleSlot->scheduled_date->format('d.m.Y') }} {{ substr($assignment->scheduleSlot->starts_at, 0, 5) }}-{{ substr($assignment->scheduleSlot->ends_at, 0, 5) }}
                                @endif
                            </div>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Краткий итог</label>
                                    <textarea name="summary" class="form-control" rows="2">{{ $assignment->report->summary ?? '' }}</textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label d-block">Чек-лист смены</label>
                                    @foreach(['Утренний уход', 'Дневной уход', 'Вечерний уход', 'Лекарства выданы', 'Питание проконтролировано', 'Прогулка/перемещение', 'Уборка и гигиена'] as $task)
                                        <label class="form-check mb-1">
                                            <input class="form-check-input" type="checkbox" name="completed_tasks[]" value="{{ $task }}" {{ in_array($task, $assignment->report->completed_tasks ?? [], true) ? 'checked' : '' }}>
                                            <span class="form-check-label">{{ $task }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Покупки и расходники</label>
                                    <textarea name="purchased_items_text" class="form-control" rows="2">{{ isset($assignment->report) ? implode("\n", $assignment->report->purchased_items ?? []) : '' }}</textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Изменения состояния</label>
                                    <textarea name="health_changes" class="form-control" rows="2">{{ $assignment->report->health_changes ?? '' }}</textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Ссылки на фото</label>
                                    <textarea name="photo_links_text" class="form-control" rows="2">{{ isset($assignment->report) ? implode("\n", $assignment->report->photo_paths ?? []) : '' }}</textarea>
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-outline-dark rounded-pill">Сохранить отчет по смене</button>
                                </div>
                            </div>
                        </form>
                    @endforeach
                </div>
            @endif

            @if(in_array($order->status, ['in_chat', 'in_progress'], true))
                <div class="card-soft p-4 mb-4">
                    <h2 class="h4 mb-3">Добавить покупку или допуслугу</h2>
                    <form action="{{ route('caregiver.orders.expenses.store', $order) }}" method="POST" class="row g-3">
                        @csrf
                        <div class="col-md-4">
                            <select name="kind" class="form-select">
                                <option value="purchase">Покупка</option>
                                <option value="extra_service">Допуслуга</option>
                                <option value="clinic">Услуга клиники</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <input type="text" name="title" class="form-control" placeholder="Например, лекарства или перевязочный материал">
                        </div>
                        <div class="col-md-4">
                            <input type="number" step="0.1" min="0.1" name="quantity" class="form-control" placeholder="Количество">
                        </div>
                        <div class="col-md-4">
                            <input type="number" min="0" name="unit_price" class="form-control" placeholder="Цена за единицу">
                        </div>
                        <div class="col-md-4">
                            <input type="date" name="purchased_at" class="form-control" value="{{ now()->format('Y-m-d') }}">
                        </div>
                        <div class="col-12">
                            <textarea name="description" class="form-control" rows="2" placeholder="Что именно было куплено или выполнено"></textarea>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-dark rounded-pill">Отправить клиенту на подтверждение</button>
                        </div>
                    </form>
                </div>
            @endif

            @if($order->status === 'completed' && $canReviewClient)
                <div class="card-soft p-4 mb-4">
                    <h2 class="h4 mb-3">Оставить отзыв о клиенте</h2>
                    <form action="{{ route('caregiver.orders.review.store', $order) }}" method="POST" class="row g-3">
                        @csrf
                        <div class="col-md-3">
                            <select name="rating" class="form-select">
                                @for($i = 5; $i >= 1; $i--)
                                    <option value="{{ $i }}">{{ $i }} из 5</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-12">
                            <textarea name="comment" class="form-control" rows="3" placeholder="Как прошла работа с клиентом?"></textarea>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-dark rounded-pill">Сохранить отзыв</button>
                        </div>
                    </form>
                </div>
            @endif
        </div>

        <div class="col-lg-4">
            <div class="card-soft p-4 mb-4">
                <h2 class="h4 mb-3">Действия</h2>
                <div class="d-grid gap-2">
                    <a href="{{ route('caregiver.dashboard') }}" class="btn btn-outline-dark rounded-pill">Назад в кабинет</a>
                    <a href="{{ route('caregiver.payouts.index') }}" class="btn btn-dark rounded-pill">История выплат</a>
                </div>
                @if(! in_array($order->status, ['completed', 'cancelled'], true))
                    <form action="{{ route('caregiver.orders.cancel', $order) }}" method="POST" class="mt-3">
                        @csrf
                        <input type="hidden" name="reason" value="Отмена по инициативе сиделки">
                        <button class="btn btn-outline-danger w-100 rounded-pill">Отменить заказ</button>
                    </form>
                @endif
            </div>

            <div class="card-soft p-4 mb-4">
                <h2 class="h4 mb-3">Жалоба на клиента</h2>
                <form action="{{ route('orders.report.store', $order) }}" method="POST" class="row g-3">
                    @csrf
                    <input type="hidden" name="reported_user_id" value="{{ $order->client_id }}">
                    <div class="col-12">
                        <select name="kind" class="form-select" required>
                            <option value="complaint">Жалоба</option>
                            <option value="blacklist">Добавить клиента в черный список</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <input type="text" name="reason" class="form-control" placeholder="Коротко: в чем проблема" required>
                    </div>
                    <div class="col-12">
                        <textarea name="details" class="form-control" rows="3" placeholder="Подробности для CRM качества"></textarea>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-outline-danger rounded-pill w-100">Отправить жалобу</button>
                    </div>
                </form>
            </div>

            @if($order->payouts->isNotEmpty())
                <div class="card-soft p-4">
                    <h2 class="h4 mb-3">Выплаты по заказу</h2>
                    @foreach($order->payouts->where('caregiver_id', $user->id) as $payout)
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span>{{ optional($payout->paid_at)->format('d.m.Y') ?: 'Ожидает' }}</span>
                            <strong>{{ number_format($payout->amount, 0, ',', ' ') }} ₽</strong>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
