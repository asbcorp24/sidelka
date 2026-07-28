@extends('layouts.app')

@php($title = 'Кабинет сиделки')
@php($selectedCanIds = $profile->availableServices()->pluck('id')->all())
@php($selectedCannotIds = $profile->restrictedServices()->pluck('id')->all())
@php($calendarSeed = old('calendar_slots_json', json_encode($availabilityCalendarEvents, JSON_UNESCAPED_UNICODE)))
@php($currentOrdersPreview = $activeOrders->whereIn('status', ['matched', 'in_chat', 'in_progress'])->values())
@php($reviewsPreview = $reviews->take(3))

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .slot-item { border: 1px solid rgba(31, 111, 120, .08); border-radius: 16px; padding: .75rem 1rem; background: #fff; }
        .quick-chip { border: 1px solid rgba(31, 111, 120, .2); background: #fff; border-radius: 999px; padding: .45rem .9rem; font-size: .92rem; }
        .quick-chip:hover { background: #f4f8f9; }
        .flatpickr-calendar.inline { box-shadow: none; border: 0; width: 100%; }
        .flatpickr-day.selected, .flatpickr-day.startRange, .flatpickr-day.endRange { background: var(--brand); border-color: var(--brand); }
    </style>
@endpush

@section('content')
<div class="container py-4 py-lg-5">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
        <div>
            <div class="text-uppercase small text-secondary">Личный кабинет сиделки</div>
            <h1 class="section-title mb-0">{{ $user->name }}</h1>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('caregiver.orders.history') }}" class="btn btn-dark rounded-pill px-4">История заказов</a>
            <a href="{{ route('caregiver.reviews.clients') }}" class="btn btn-outline-dark rounded-pill px-4">Отзывы на клиентов</a>
            <a href="{{ route('caregiver.payouts.index') }}" class="btn btn-outline-dark rounded-pill px-4">История выплат</a>
            <a href="{{ route('caregiver.legal') }}" class="btn btn-outline-dark rounded-pill px-4">Документы</a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-3"><div class="metric"><div class="value">{{ $stats['new_matches'] }}</div><div>Подходящих заказов</div></div></div>
        <div class="col-md-3"><div class="metric"><div class="value">{{ $stats['orders_done'] }}</div><div>Завершено</div></div></div>
        <div class="col-md-3"><div class="metric"><div class="value">{{ number_format($stats['released_amount'], 0, ',', ' ') }} ₽</div><div>Уже выплачено</div></div></div>
        <div class="col-md-3"><div class="metric"><div class="value">{{ number_format($stats['pending_payout'], 0, ',', ' ') }} ₽</div><div>Ждет выплаты</div></div></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card-soft p-4 mb-4">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                    <div>
                        <h2 class="h4 mb-2">Анкета сиделки</h2>
                        <p class="text-secondary mb-0">Здесь вы управляете навыками, ставкой и доступностью по датам и времени.</p>
                    </div>
                    <div class="text-secondary small">{{ $user->city }} • онлайн {{ $user->last_seen_at?->diffForHumans() }}</div>
                </div>

                <form action="{{ route('caregiver.profile.update') }}" method="POST" class="row g-3">
                    @csrf
                    <div class="col-12">
                        <label class="form-label">Кратко о себе</label>
                        <textarea name="about" class="form-control" rows="2">{{ old('about', $user->about) }}</textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Опыт, лет</label>
                        <input type="number" name="experience_years" class="form-control" value="{{ old('experience_years', $profile->experience_years) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Ставка от, ₽/час</label>
                        <input type="number" name="hourly_rate_from" class="form-control" value="{{ old('hourly_rate_from', $profile->hourly_rate_from) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Смена от, ₽</label>
                        <input type="number" name="shift_rate_from" class="form-control" value="{{ old('shift_rate_from', $profile->shift_rate_from) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Формат занятости</label>
                        <input type="text" name="employment_format" class="form-control" value="{{ old('employment_format', $profile->employment_format) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Образование</label>
                        <input type="text" name="education" class="form-control" value="{{ old('education', $profile->education) }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Описание профиля</label>
                        <textarea name="bio" class="form-control" rows="3">{{ old('bio', $profile->bio) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Могу выполнять</label>
                        <div class="border rounded-4 p-3" style="max-height: 280px; overflow: auto;">
                            @foreach($services as $service)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="can_service_ids[]" value="{{ $service->id }}" {{ in_array($service->id, old('can_service_ids', $selectedCanIds), true) ? 'checked' : '' }}>
                                    <label class="form-check-label">{{ $service->name }}</label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Не выполняю</label>
                        <div class="border rounded-4 p-3" style="max-height: 280px; overflow: auto;">
                            @foreach($services as $service)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="cannot_service_ids[]" value="{{ $service->id }}" {{ in_array($service->id, old('cannot_service_ids', $selectedCannotIds), true) ? 'checked' : '' }}>
                                    <label class="form-check-label">{{ $service->name }}</label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" name="ready_for_night" value="1" {{ old('ready_for_night', $profile->ready_for_night) ? 'checked' : '' }}>
                            <label class="form-check-label">Готова к ночным сменам</label>
                        </div>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="ready_for_live_in" value="1" {{ old('ready_for_live_in', $profile->ready_for_live_in) ? 'checked' : '' }}>
                            <label class="form-check-label">Готова к работе с проживанием</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Календарь доступности</label>
                        <div class="border rounded-4 p-3 p-lg-4 bg-white">
                            <div class="row g-3 align-items-start">
                                <div class="col-lg-6">
                                    <label class="form-label small text-secondary">Выберите даты</label>
                                    <input type="text" class="form-control" id="caregiver-date-picker" readonly>
                                    <div class="d-flex gap-2 flex-wrap mt-2">
                                        <button type="button" class="quick-chip" data-caregiver-quick-date="today">Сегодня</button>
                                        <button type="button" class="quick-chip" data-caregiver-quick-date="tomorrow">Завтра</button>
                                        <button type="button" class="quick-chip" data-caregiver-open-modal="1">Добавить слот</button>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <label class="form-label small text-secondary">Шаблон по дням недели</label>
                                    <div class="row g-2 mb-2">
                                        @foreach(['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'] as $weekdayIndex => $weekdayLabel)
                                            <div class="col-4 col-md-3">
                                                <label class="form-check border rounded-3 px-2 py-2 small w-100">
                                                    <input class="form-check-input me-1 caregiver-template-weekday" type="checkbox" value="{{ $weekdayIndex + 1 }}">
                                                    <span class="form-check-label">{{ $weekdayLabel }}</span>
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <input type="text" class="form-control" id="caregiver-template-start-date" placeholder="Дата начала" readonly>
                                        </div>
                                        <div class="col-md-6">
                                            <input type="text" class="form-control" id="caregiver-template-end-date" placeholder="Дата конца" readonly>
                                        </div>
                                        <div class="col-12">
                                            <button type="button" class="btn btn-outline-dark rounded-pill px-4" id="caregiver-apply-template">Применить шаблон</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="calendar_slots_json" id="caregiver-calendar-slots" value='{{ $calendarSeed }}'>
                        <div id="caregiver-calendar-summary" class="mt-3"></div>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-dark rounded-pill px-4">Сохранить анкету</button>
                    </div>
                </form>
            </div>

            <div class="card-soft p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h2 class="h4 mb-0">Открытые заказы для отклика</h2>
                    <span class="badge text-bg-light">{{ $matchedOrders->count() }}</span>
                </div>
                @forelse($matchedOrders as $order)
                    <div class="border rounded-4 p-3 mb-3">
                        <div class="d-flex justify-content-between flex-wrap gap-2">
                            <strong>{{ $order->title }}</strong>
                            <span class="badge text-bg-secondary">{{ $order->needs_today ? 'Срочно сегодня' : 'Открытый заказ' }}</span>
                        </div>
                        <div class="text-secondary mt-2">{{ $order->client->name }} • {{ $order->city }} • от {{ number_format($order->hourly_budget, 0, ',', ' ') }} ₽/час</div>
                        <div class="small text-secondary mt-1">Совпало услуг: {{ $order->match_count }}</div>
                        <div class="mt-2 d-flex flex-wrap gap-2">
                            @foreach($order->services->take(4) as $service)
                                <span class="service-chip">{{ $service->name }}</span>
                            @endforeach
                        </div>
                        <div class="mt-3">
                            @if($order->my_application)
                                <span class="badge {{ in_array($order->my_application->status, ['accepted', 'completed'], true) ? 'text-bg-success' : ($order->my_application->status === 'invited' ? 'text-bg-warning' : 'text-bg-primary') }}">
                                    {{ $order->my_application->status === 'applied' ? 'Отклик отправлен' : ($order->my_application->status === 'invited' ? 'Есть приглашение' : 'Уже подтверждено') }}
                                </span>
                            @else
                                <form action="{{ route('caregiver.orders.apply', $order) }}" method="POST">
                                    @csrf
                                    <button class="btn btn-dark rounded-pill">Откликнуться</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-secondary mb-0">Сейчас нет новых открытых заказов, которые подходят вам по городу, услугам и расписанию.</p>
                @endforelse
            </div>

            <div class="card-soft p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h2 class="h4 mb-0">Входящие приглашения</h2>
                    <span class="badge text-bg-light">{{ $notifications['new_invitations'] }} новых слотов</span>
                </div>
                @forelse($incomingInvitations as $order)
                    <div class="border rounded-4 p-3 mb-3">
                        <div class="d-flex justify-content-between flex-wrap gap-2">
                            <strong>{{ $order->title }}</strong>
                            <span class="badge {{ $order->status_badge_class }}">{{ $order->status_label }}</span>
                        </div>
                        <div class="text-secondary mt-2">{{ $order->client->name }} • {{ $order->city }} • Базовый счет {{ number_format($order->base_amount, 0, ',', ' ') }} ₽</div>
                        <div class="mt-3">
                            <a href="{{ route('caregiver.orders.show', $order) }}" class="btn btn-dark rounded-pill">Открыть заказ</a>
                        </div>
                    </div>
                @empty
                    <p class="text-secondary mb-0">Новых приглашений пока нет.</p>
                @endforelse
            </div>

            <div class="card-soft p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h2 class="h4 mb-0">Текущие заказы</h2>
                    <a href="{{ route('caregiver.orders.history') }}" class="btn btn-outline-dark rounded-pill px-4">Вся история заказов</a>
                </div>
                @forelse($currentOrdersPreview as $order)
                    <div class="border rounded-4 p-3 mb-3">
                        <div class="d-flex justify-content-between flex-wrap gap-2">
                            <strong>{{ $order->title }}</strong>
                            <span class="badge {{ $order->status_badge_class }}">{{ $order->status_label }}</span>
                        </div>
                        <div class="text-secondary mt-2">{{ $order->client->name }} • {{ $order->payment_status_label }} • {{ number_format($order->total_invoice_amount, 0, ',', ' ') }} ₽</div>
                        <div class="mt-3">
                            <a href="{{ route('caregiver.orders.show', $order) }}" class="btn btn-dark rounded-pill">Открыть заказ</a>
                        </div>
                    </div>
                @empty
                    <p class="text-secondary mb-0">Текущих заказов пока нет.</p>
                @endforelse
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card-soft p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h2 class="h4 mb-0">Отзывы на клиентов</h2>
                    <a href="{{ route('caregiver.reviews.clients') }}" class="btn btn-outline-dark rounded-pill px-4">Все отзывы</a>
                </div>
                @forelse($reviewsPreview as $review)
                    <div class="border rounded-4 p-3 mb-3">
                        <div class="d-flex justify-content-between flex-wrap gap-2">
                            <strong>{{ $review->subject->name ?? 'Клиент' }}</strong>
                            <span>{{ str_repeat('★', $review->rating) }}</span>
                        </div>
                        <p class="mb-1 mt-2">{{ $review->comment }}</p>
                        <div class="text-secondary small">{{ optional($review->published_at)->format('d.m.Y') }}</div>
                    </div>
                @empty
                    <p class="text-secondary mb-0">Пока нет отзывов на клиентов.</p>
                @endforelse
            </div>

            <div class="card-soft p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h2 class="h4 mb-0">Документы и статус</h2>
                    <a href="{{ route('caregiver.legal') }}" class="btn btn-outline-dark rounded-pill px-4">Открыть документы</a>
                </div>
                @forelse($documents as $document)
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom gap-2">
                        <span>{{ $document['title'] }}</span>
                        <span class="badge {{ $document['status_class'] ?? 'text-bg-light' }}">{{ $document['status'] }}</span>
                    </div>
                @empty
                    <p class="text-secondary mb-0">Документы еще не загружены.</p>
                @endforelse
            </div>

            <div class="card-soft p-4">
                <h2 class="h4 mb-3">Последние уведомления</h2>
                @forelse($recentNotifications as $notification)
                    <div class="border rounded-4 p-3 mb-3">
                        <strong>{{ $notification->title }}</strong>
                        <p class="text-secondary small mb-0 mt-1">{{ $notification->body }}</p>
                    </div>
                @empty
                    <p class="text-secondary mb-0">Новых уведомлений пока нет.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="caregiver-slot-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header border-0">
                <h2 class="modal-title h4 mb-0">Добавить слот доступности</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-0">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Время с</label>
                        <input type="time" class="form-control" id="caregiver-slot-start" value="09:00">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Время до</label>
                        <input type="time" class="form-control" id="caregiver-slot-end" value="18:00">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Комментарий</label>
                        <input type="text" class="form-control" id="caregiver-slot-notes" placeholder="Например: дневная смена, только центр, возможна ночь">
                    </div>
                    <div class="col-12">
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="button" class="quick-chip" data-caregiver-time-preset="morning">Утро</button>
                            <button type="button" class="quick-chip" data-caregiver-time-preset="day">День</button>
                            <button type="button" class="quick-chip" data-caregiver-time-preset="evening">Вечер</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-dark rounded-pill px-4" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-dark rounded-pill px-4" id="caregiver-add-slot">Добавить слот</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/ru.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var input = document.getElementById('caregiver-calendar-slots');
            var summary = document.getElementById('caregiver-calendar-summary');
            var startInput = document.getElementById('caregiver-slot-start');
            var endInput = document.getElementById('caregiver-slot-end');
            var notesInput = document.getElementById('caregiver-slot-notes');
            var addButton = document.getElementById('caregiver-add-slot');
            var applyTemplateButton = document.getElementById('caregiver-apply-template');
            var seedEvents = [];
            var slots = [];
            var selectedDateValues = [];
            var caregiverModal = new bootstrap.Modal(document.getElementById('caregiver-slot-modal'));

            try {
                seedEvents = JSON.parse(input.value || '[]');
            } catch (error) {
                seedEvents = [];
            }

            function normalizeSeed(events) {
                return events.map(function (event, index) {
                    var start = new Date(event.start);
                    var end = new Date(event.end);
                    return {
                        id: event.id || String(Date.now() + index),
                        start: start.toISOString(),
                        end: end.toISOString(),
                        notes: event.notes || ''
                    };
                });
            }

            function formatEventLabel(event) {
                var start = new Date(event.start);
                var end = new Date(event.end);
                var date = start.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric' });
                var startTime = start.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
                var endTime = end.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
                return date + ' ' + startTime + '-' + endTime + (event.notes ? ' • ' + event.notes : '');
            }

            function syncState() {
                slots.sort(function (a, b) {
                    return new Date(a.start) - new Date(b.start);
                });
                input.value = JSON.stringify(slots);
                summary.innerHTML = slots.length
                    ? slots.map(function (slot) {
                        return '<div class="slot-item d-flex justify-content-between align-items-start gap-3 mb-2">'
                            + '<div><strong>' + formatEventLabel(slot) + '</strong></div>'
                            + '<button type="button" class="btn btn-sm btn-outline-danger rounded-pill" data-remove-caregiver-slot="' + slot.id + '">Удалить</button>'
                            + '</div>';
                    }).join('')
                    : '<div class="text-secondary small">Пока не выбрано ни одного слота доступности.</div>';
            }

            function makeIso(dateString, timeString) {
                return new Date(dateString + 'T' + timeString).toISOString();
            }

            function addSlotsForDates(dates, startTime, endTime, notes) {
                if (!dates.length) {
                    window.alert('Сначала выберите хотя бы одну дату.');
                    return false;
                }

                if (!startTime || !endTime || startTime >= endTime) {
                    window.alert('Проверьте время начала и окончания.');
                    return false;
                }

                dates.forEach(function (dateString, index) {
                    slots.push({
                        id: String(Date.now() + index),
                        start: makeIso(dateString, startTime),
                        end: makeIso(dateString, endTime),
                        notes: notes
                    });
                });

                syncState();
                return true;
            }

            var caregiverDatePicker = flatpickr('#caregiver-date-picker', {
                inline: true,
                locale: 'ru',
                minDate: 'today',
                mode: 'multiple',
                dateFormat: 'Y-m-d',
                defaultDate: ['today'],
                onChange: function (pickedDates) {
                    selectedDateValues = pickedDates.map(function (date) {
                        return caregiverDatePicker.formatDate(date, 'Y-m-d');
                    });
                },
                onReady: function (selected, _, instance) {
                    selectedDateValues = selected.map(function (date) {
                        return instance.formatDate(date, 'Y-m-d');
                    });
                }
            });

            flatpickr('#caregiver-template-start-date', { locale: 'ru', minDate: 'today', dateFormat: 'Y-m-d' });
            flatpickr('#caregiver-template-end-date', { locale: 'ru', minDate: 'today', dateFormat: 'Y-m-d' });

            slots = normalizeSeed(seedEvents);
            syncState();

            addButton.addEventListener('click', function () {
                if (addSlotsForDates(selectedDateValues, startInput.value, endInput.value, notesInput.value.trim())) {
                    notesInput.value = '';
                    caregiverModal.hide();
                }
            });

            applyTemplateButton.addEventListener('click', function () {
                var startDate = document.getElementById('caregiver-template-start-date').value;
                var endDate = document.getElementById('caregiver-template-end-date').value;
                var selectedWeekdays = Array.from(document.querySelectorAll('.caregiver-template-weekday:checked')).map(function (checkbox) {
                    return Number(checkbox.value);
                });

                if (!startDate || !endDate || startDate > endDate || !selectedWeekdays.length) {
                    window.alert('Выберите диапазон дат и хотя бы один день недели.');
                    return;
                }

                var dates = [];
                var cursor = new Date(startDate + 'T00:00:00');
                var finish = new Date(endDate + 'T00:00:00');
                while (cursor <= finish) {
                    var weekday = cursor.getDay() === 0 ? 7 : cursor.getDay();
                    if (selectedWeekdays.includes(weekday)) {
                        dates.push(caregiverDatePicker.formatDate(cursor, 'Y-m-d'));
                    }
                    cursor.setDate(cursor.getDate() + 1);
                }

                if (addSlotsForDates(dates, startInput.value, endInput.value, 'Шаблон доступности')) {
                    document.querySelectorAll('.caregiver-template-weekday').forEach(function (checkbox) {
                        checkbox.checked = false;
                    });
                }
            });

            document.addEventListener('click', function (event) {
                var quickDate = event.target.getAttribute('data-caregiver-quick-date');
                if (quickDate) {
                    var baseDate = new Date();
                    if (quickDate === 'tomorrow') {
                        baseDate.setDate(baseDate.getDate() + 1);
                    }
                    caregiverDatePicker.setDate([caregiverDatePicker.formatDate(baseDate, 'Y-m-d')], true);
                }

                if (event.target.getAttribute('data-caregiver-open-modal')) {
                    caregiverModal.show();
                }

                var timePreset = event.target.getAttribute('data-caregiver-time-preset');
                if (timePreset === 'morning') {
                    startInput.value = '08:00';
                    endInput.value = '12:00';
                } else if (timePreset === 'day') {
                    startInput.value = '12:00';
                    endInput.value = '18:00';
                } else if (timePreset === 'evening') {
                    startInput.value = '18:00';
                    endInput.value = '22:00';
                }

                var removeId = event.target.getAttribute('data-remove-caregiver-slot');
                if (removeId) {
                    slots = slots.filter(function (slot) {
                        return slot.id !== removeId;
                    });
                    syncState();
                }
            });
        });
    </script>
@endpush
