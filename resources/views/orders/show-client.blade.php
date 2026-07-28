@extends('layouts.app')

@php($title = 'Заказ клиента')

@section('content')
<div class="container py-4 py-lg-5">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <div class="text-uppercase small text-secondary">Карточка заказа</div>
            <h1 class="section-title mb-1">{{ $order->title }}</h1>
            <div class="text-secondary">{{ $order->city }} • {{ $order->address ?: 'Адрес уточняется' }}</div>
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
                    @foreach($order->custom_services ?? [] as $customService)
                        <span class="availability-chip border border-dark-subtle">{{ $customService }}</span>
                    @endforeach
                </div>

                @if($order->patientProfile)
                    <div class="border rounded-4 p-3 mb-4 bg-light">
                        <h2 class="h5 mb-3">Карточка пациента</h2>
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
                                <div class="col-md-6">
                                    <strong>Экстренный контакт:</strong><br>
                                    {{ $order->patientProfile->emergency_contact_name }}{{ $order->patientProfile->emergency_contact_phone ? ' • '.$order->patientProfile->emergency_contact_phone : '' }}
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                @if($order->scheduleSlots->isNotEmpty())
                    <div class="mb-3">
                        <strong>Расписание и назначения:</strong>
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
                                        @if($slot->assignments_for_view->isEmpty())
                                            <span class="badge text-bg-secondary">Пока без назначенной сиделки</span>
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
                                                                Приглашение отправлено, ждем подтверждения
                                                            @elseif($assignment->status === 'accepted')
                                                                Смена подтверждена
                                                            @elseif($assignment->status === 'declined')
                                                                Сиделка отказалась
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

                @if($order->clinicPartnerServices->isNotEmpty())
                    <div>
                        <strong>Партнерские клиники и скидки:</strong>
                        <div class="mt-2">
                            @foreach($order->clinicPartnerServices as $clinicService)
                                <div class="border rounded-4 p-3 mb-2">
                                    <strong>{{ $clinicService->clinic->name }}</strong>
                                    <div class="text-secondary">{{ $clinicService->name }} • скидка {{ $clinicService->pivot->discount_percent }}%</div>
                                    <div class="mt-1">{{ number_format($clinicService->pivot->price_at_booking, 0, ',', ' ') }} ₽</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="card-soft p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h2 class="h4 mb-0">Счет по заказу</h2>
                    <div class="text-secondary">Баланс клиента: {{ number_format($user->wallet_balance, 0, ',', ' ') }} ₽</div>
                </div>
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
                                    Ждет подтверждения
                                @elseif($expense->status === 'rejected')
                                    Отклонено
                                @else
                                    Согласовано
                                @endif
                            </div>
                        </div>
                        <strong>{{ number_format($expense->line_total, 0, ',', ' ') }} ₽</strong>
                    </div>
                    @if($expense->status === 'pending_approval')
                        <div class="d-flex gap-2 mt-2 mb-3">
                            <form action="{{ route('client.orders.expenses.approve', [$order, $expense]) }}" method="POST">
                                @csrf
                                <button class="btn btn-dark rounded-pill btn-sm">Подтвердить расход</button>
                            </form>
                            <form action="{{ route('client.orders.expenses.reject', [$order, $expense]) }}" method="POST">
                                @csrf
                                <button class="btn btn-outline-dark rounded-pill btn-sm">Отклонить</button>
                            </form>
                        </div>
                    @endif
                @endforeach
                <div class="d-flex justify-content-between pt-3 mt-2">
                    <strong>Итого</strong>
                    <strong>{{ number_format($order->total_invoice_amount, 0, ',', ' ') }} ₽</strong>
                </div>
            </div>

            @if($order->assignedCaregivers->isNotEmpty())
                <div class="card-soft p-4 mb-4">
                    <h2 class="h4 mb-3">Чаты по заказу</h2>
                    @foreach($order->conversations->where('status', 'active') as $conversation)
                        <div class="border rounded-4 p-3 mb-3">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                <div>
                                    <strong>{{ $conversation->caregiver?->name ?: 'Сиделка' }}</strong>
                                    <div class="small text-secondary">Личный чат по этому заказу</div>
                                </div>
                                <form action="{{ route('client.orders.messages.read', $order) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="caregiver_id" value="{{ $conversation->caregiver_id }}">
                                    <button class="btn btn-outline-dark rounded-pill btn-sm">Отметить как прочитанное</button>
                                </form>
                            </div>

                            @foreach($conversation->messages as $message)
                                <div class="chat-bubble {{ $message->sender_id === $user->id ? 'client' : 'caregiver' }} mb-2">
                                    <strong>{{ $message->sender->name }}</strong>
                                    <div>{{ $message->body }}</div>
                                </div>
                            @endforeach

                            <form action="{{ route('client.orders.messages.store', $order) }}" method="POST" class="mt-3">
                                @csrf
                                <input type="hidden" name="caregiver_id" value="{{ $conversation->caregiver_id }}">
                                <div class="input-group">
                                    <input type="text" name="body" class="form-control" placeholder="Напишите сообщение сиделке">
                                    <button class="btn btn-dark" type="submit">Отправить</button>
                                </div>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif

            @if($order->status === 'completed' && $canReviewCaregiver && $order->assignedCaregivers->isNotEmpty())
                <div class="card-soft p-4 mb-4">
                    <h2 class="h4 mb-3">Оставить отзыв о сиделке</h2>
                    <form action="{{ route('client.orders.review.store', $order) }}" method="POST" class="row g-3">
                        @csrf
                        <div class="col-md-5">
                            <select name="subject_id" class="form-select">
                                @foreach($order->assignedCaregivers as $caregiver)
                                    <option value="{{ $caregiver->id }}">{{ $caregiver->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="rating" class="form-select">
                                @for($i = 5; $i >= 1; $i--)
                                    <option value="{{ $i }}">{{ $i }} из 5</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-12">
                            <textarea name="comment" class="form-control" rows="3" placeholder="Как прошла работа сиделки?"></textarea>
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
                    <a href="{{ route('client.dashboard') }}" class="btn btn-outline-dark rounded-pill">Назад в кабинет</a>
                    <a href="{{ route('client.orders.create') }}" class="btn btn-dark rounded-pill">Создать новый заказ</a>
                    @if($order->status === 'completed' && $order->payment_status === 'released')
                        <a href="{{ route('client.orders.extend', $order) }}" class="btn btn-outline-dark rounded-pill">Продлить заказ</a>
                    @endif
                </div>
                @if($order->status === 'in_chat')
                    <form action="{{ route('client.orders.start', $order) }}" method="POST" class="mt-3">
                        @csrf
                        <button class="btn btn-success w-100 rounded-pill">Перевести в работу и удержать оплату</button>
                    </form>
                @elseif($order->status === 'in_progress')
                    <form action="{{ route('client.orders.complete', $order) }}" method="POST" class="mt-3">
                        @csrf
                        <button class="btn btn-success w-100 rounded-pill">Подтвердить завершение и выплату</button>
                    </form>
                @endif
                @if(! in_array($order->status, ['completed', 'cancelled'], true))
                    <form action="{{ route('client.orders.cancel', $order) }}" method="POST" class="mt-3">
                        @csrf
                        <input type="hidden" name="reason" value="Отмена по инициативе клиента">
                        <button class="btn btn-outline-danger w-100 rounded-pill">Отменить заказ</button>
                    </form>
                @endif
            </div>

            @if($order->assignedCaregivers->isNotEmpty())
                <div class="card-soft p-4 mb-4">
                    <h2 class="h4 mb-3">Жалоба или черный список</h2>
                    <form action="{{ route('orders.report.store', $order) }}" method="POST" class="row g-3">
                        @csrf
                        <div class="col-12">
                            <select name="reported_user_id" class="form-select" required>
                                <option value="">Выберите сиделку</option>
                                @foreach($order->assignedCaregivers as $caregiver)
                                    <option value="{{ $caregiver->id }}">{{ $caregiver->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <select name="kind" class="form-select" required>
                                <option value="complaint">Жалоба</option>
                                <option value="blacklist">Добавить в черный список</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <input type="text" name="reason" class="form-control" placeholder="Коротко: причина обращения" required>
                        </div>
                        <div class="col-12">
                            <textarea name="details" class="form-control" rows="3" placeholder="Опишите ситуацию подробнее"></textarea>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-outline-danger rounded-pill w-100">Отправить в CRM качества</button>
                        </div>
                    </form>
                </div>
            @endif

            @if($order->applicantCaregivers->isNotEmpty())
                <div class="card-soft p-4 mb-4">
                    <h2 class="h4 mb-3">Откликнувшиеся сиделки</h2>
                    @foreach($order->applicantCaregivers as $caregiver)
                        @php($caregiverApplications = $order->caregiverAssignments->where('caregiver_id', $caregiver->id)->whereIn('status', ['applied', 'reserved'])->values())
                        <div class="border rounded-4 p-3 mb-3">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                <div>
                                    <strong>{{ $caregiver->name }}</strong>
                                    <div class="text-secondary small">
                                        {{ $caregiver->caregiverProfile->experience_years ?? 0 }} лет опыта
                                        • от {{ number_format($caregiver->caregiverProfile->hourly_rate_from ?? 0, 0, ',', ' ') }} ₽/час
                                    </div>
                                </div>
                                <span class="badge text-bg-primary">Отклик получен</span>
                            </div>

                            @if($caregiverApplications->isNotEmpty())
                                <div class="mt-3">
                                    <div class="small fw-semibold mb-2">Какие смены сиделка готова взять:</div>
                                    <form action="{{ route('client.orders.applicants.confirm', [$order, $caregiver]) }}" method="POST">
                                        @csrf
                                        @foreach($caregiverApplications as $application)
                                            @php($slot = $application->scheduleSlot)
                                            @if($slot)
                                                <label class="form-check border rounded-3 px-3 py-2 mb-2">
                                                    <input class="form-check-input me-2" type="checkbox" name="slot_ids[]" value="{{ $slot->id }}" {{ $order->allows_multiple_caregivers ? '' : 'checked' }} {{ $order->allows_multiple_caregivers ? '' : 'disabled' }}>
                                                    <span class="form-check-label">
                                                        {{ $slot->scheduled_date->format('d.m.Y') }} {{ substr($slot->starts_at, 0, 5) }}-{{ substr($slot->ends_at, 0, 5) }}
                                                        @if($slot->label)
                                                            <span class="text-secondary">• {{ $slot->label }}</span>
                                                        @endif
                                                    </span>
                                                </label>
                                                @if(! $order->allows_multiple_caregivers)
                                                    <input type="hidden" name="slot_ids[]" value="{{ $slot->id }}">
                                                @endif
                                            @endif
                                        @endforeach
                                        <button class="btn btn-dark rounded-pill btn-sm mt-2">
                                            {{ $order->allows_multiple_caregivers ? 'Подтвердить на выбранные смены' : 'Подтвердить сиделку и открыть чат' }}
                                        </button>
                                    </form>
                                    <div class="d-flex gap-2 flex-wrap mt-2">
                                        <form action="{{ route('client.orders.applicants.reserve', [$order, $caregiver]) }}" method="POST">
                                            @csrf
                                            <button class="btn btn-outline-dark rounded-pill btn-sm">Оставить в резерве</button>
                                        </form>
                                        <form action="{{ route('client.orders.applicants.decline', [$order, $caregiver]) }}" method="POST">
                                            @csrf
                                            <button class="btn btn-outline-danger rounded-pill btn-sm">Отклонить отклик</button>
                                        </form>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            @if($order->matched_caregivers->isNotEmpty())
                <div class="card-soft p-4">
                    <h2 class="h4 mb-3">{{ $order->allows_multiple_caregivers ? 'Подобрать сиделок по сменам' : 'Подходящие сиделки' }}</h2>
                    @foreach($order->matched_caregivers as $caregiver)
                        <div class="border rounded-4 p-3 mb-3">
                            <strong>{{ $caregiver->name }}</strong>
                            <div class="text-secondary">{{ $caregiver->caregiverProfile->experience_years }} лет опыта • от {{ number_format($caregiver->caregiverProfile->hourly_rate_from, 0, ',', ' ') }} ₽/час</div>
                            <div class="small text-secondary mt-1">Совпадение: {{ $caregiver->match_score ?? 0 }} баллов • рейтинг {{ number_format((float) $caregiver->rating, 1, ',', ' ') }}</div>
                            <div class="mt-2 d-flex flex-wrap gap-2">
                                @foreach($caregiver->matched_services as $matchedService)
                                    <span class="service-chip">{{ $matchedService }}</span>
                                @endforeach
                            </div>

                            <form action="{{ route('client.orders.invite', [$order, $caregiver->caregiverProfile]) }}" method="POST" class="mt-3">
                                @csrf
                                @if($order->allows_multiple_caregivers)
                                    <div class="small fw-semibold mb-2">Назначить на смены:</div>
                                    @foreach($order->scheduleSlots->whereIn('id', $caregiver->available_slot_ids) as $slot)
                                        <label class="form-check border rounded-3 px-3 py-2 mb-2">
                                            <input class="form-check-input me-2" type="checkbox" name="slot_ids[]" value="{{ $slot->id }}">
                                            <span class="form-check-label">
                                                {{ $slot->scheduled_date->format('d.m.Y') }} {{ substr($slot->starts_at, 0, 5) }}-{{ substr($slot->ends_at, 0, 5) }}
                                            </span>
                                        </label>
                                    @endforeach
                                    <button class="btn btn-dark rounded-pill btn-sm">Отправить приглашение на выбранные смены</button>
                                @else
                                    <button class="btn btn-dark rounded-pill btn-sm">Выбрать эту сиделку</button>
                                @endif
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
