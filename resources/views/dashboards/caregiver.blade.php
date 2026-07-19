@extends('layouts.app')

@php($title = 'Кабинет сиделки')
@php($selectedCanIds = $profile->availableServices()->pluck('id')->all())
@php($selectedCannotIds = $profile->restrictedServices()->pluck('id')->all())
@php($calendarSeed = old('calendar_slots_json', json_encode($availabilityCalendarEvents, JSON_UNESCAPED_UNICODE)))

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .slot-builder-shell {
            border: 1px solid rgba(31, 111, 120, 0.12);
            border-radius: 24px;
            padding: 1.2rem;
            background: #fff;
        }

        .flatpickr-calendar.inline {
            width: 100%;
            box-shadow: none;
            border: 0;
        }

        .flatpickr-day.selected,
        .flatpickr-day.startRange,
        .flatpickr-day.endRange {
            background: #1f6f78;
            border-color: #1f6f78;
        }

        .slot-item {
            border: 1px solid rgba(31, 111, 120, 0.12);
            border-radius: 18px;
            padding: 0.9rem 1rem;
            background: #f9fcfb;
        }

        .quick-chip {
            border-radius: 999px;
            border: 1px solid rgba(31, 111, 120, 0.16);
            background: #fff;
            padding: 0.45rem 0.8rem;
            font-size: 0.92rem;
        }
    </style>
@endpush

@section('content')
<div class="container py-4 py-lg-5">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
        <div>
            <div class="text-uppercase small text-secondary">Личный кабинет сиделки</div>
            <h1 class="section-title mb-0">{{ $user->name }}</h1>
        </div>
        <div class="text-secondary">{{ $user->city }} • онлайн {{ $user->last_seen_at?->diffForHumans() }}</div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-3"><div class="metric"><div class="value">{{ $stats['new_matches'] }}</div><div>Новых совпадений по заявкам</div></div></div>
        <div class="col-md-3"><div class="metric"><div class="value">{{ $stats['orders_done'] }}</div><div>Завершенных заказов</div></div></div>
        <div class="col-md-3"><div class="metric"><div class="value">{{ number_format($stats['monthly_income'], 0, ',', ' ') }} ₽</div><div>Уже выплачено</div></div></div>
        <div class="col-md-3"><div class="metric"><div class="value">{{ number_format($stats['pending_payout'], 0, ',', ' ') }} ₽</div><div>Ожидает подтверждения</div></div></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card-soft p-4 mb-4">
                <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
                    <h2 class="h4 mb-0">Анкета сиделки</h2>
                    <span class="badge {{ $profile->documents_verified ? 'text-bg-success' : 'text-bg-warning' }}">
                        {{ $profile->documents_verified ? 'Верификация пройдена' : 'Нужно дозагрузить документы' }}
                    </span>
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
                        <label class="form-label">Медицинские навыки</label>
                        <textarea name="medical_skills" class="form-control" rows="3">{{ old('medical_skills', $profile->medical_skills) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Бытовые навыки</label>
                        <textarea name="household_skills" class="form-control" rows="3">{{ old('household_skills', $profile->household_skills) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label d-block">Могу выполнять</label>
                        @foreach($services as $service)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="can_service_ids[]" value="{{ $service->id }}" {{ in_array($service->id, $selectedCanIds, true) ? 'checked' : '' }}>
                                <label class="form-check-label">
                                    {{ $service->name }}
                                    @if($service->requires_medical_training)
                                        <span class="text-danger">• требует медподготовки</span>
                                    @endif
                                </label>
                            </div>
                        @endforeach
                    </div>
                    <div class="col-md-6">
                        <label class="form-label d-block">Не выполняю</label>
                        @foreach($services as $service)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="cannot_service_ids[]" value="{{ $service->id }}" {{ in_array($service->id, $selectedCannotIds, true) ? 'checked' : '' }}>
                                <label class="form-check-label">{{ $service->name }}</label>
                            </div>
                        @endforeach
                    </div>
                    <div class="col-md-6">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="ready_for_night" value="1" {{ $profile->ready_for_night ? 'checked' : '' }}>
                            <label class="form-check-label">Готова к ночным сменам</label>
                        </div>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="ready_for_live_in" value="1" {{ $profile->ready_for_live_in ? 'checked' : '' }}>
                            <label class="form-check-label">Готова с проживанием</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                            <label class="form-label mb-0">Календарь доступности</label>
                            <div class="small text-secondary">Выберите дату, время и добавьте слот. Так проще редактировать реальные смены.</div>
                        </div>
                        <div class="slot-builder-shell">
                            <div class="row g-3 align-items-start">
                                <div class="col-lg-6">
                                    <label class="form-label">Даты</label>
                                    <input type="text" class="form-control" id="caregiver-date-picker" readonly>
                                    <div class="d-flex gap-2 flex-wrap mt-2">
                                        <button type="button" class="quick-chip" data-caregiver-quick-date="today">Сегодня</button>
                                        <button type="button" class="quick-chip" data-caregiver-quick-date="tomorrow">Завтра</button>
                                        <button type="button" class="quick-chip" data-caregiver-open-modal="1">Добавить слот</button>
                                    </div>
                                    <div class="form-text">Можно выбрать несколько дат сразу, а потом одним действием добавить одинаковый интервал.</div>
                                </div>
                                <div class="col-lg-6">
                                    <label class="form-label">Шаблон по дням недели</label>
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
                        <input type="hidden" name="calendar_slots_json" id="caregiver-calendar-slots" value='@json(json_decode($calendarSeed, true))'>
                        <div id="caregiver-calendar-summary" class="mt-3"></div>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-dark rounded-pill px-4">Сохранить анкету</button>
                    </div>
                </form>
            </div>

            <div class="card-soft p-4 mb-4">
                <h2 class="h4 mb-3">Подобранные заявки</h2>
                @forelse($matchedOrders as $order)
                    <div class="border rounded-4 p-3 mb-3">
                        <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
                            <strong>{{ $order->title }}</strong>
                            <span class="badge text-bg-light">{{ $order->match_count }} совпадения по услугам</span>
                        </div>
                        <div class="text-secondary mb-2">{{ $order->client->name }} • {{ $order->city }} • {{ $order->starts_at->format('d.m H:i') }} - {{ $order->ends_at->format('d.m H:i') }}</div>
                        <p class="mb-2">{{ $order->description }}</p>
                        <div class="mb-2">
                            @foreach($order->services as $service)
                                <span class="service-chip">{{ $service->name }}</span>
                            @endforeach
                        </div>
                        <span class="price-tag">до {{ number_format($order->hourly_budget, 0, ',', ' ') }} ₽/час</span>
                    </div>
                @empty
                    <p class="text-secondary mb-0">Подходящих заявок пока нет.</p>
                @endforelse
            </div>

            <div class="card-soft p-4">
                <h2 class="h4 mb-3">Отзывы о вас</h2>
                @forelse($reviews as $review)
                    <div class="border rounded-4 p-3 mb-3">
                        <div class="d-flex justify-content-between flex-wrap gap-2">
                            <strong>{{ $review->author->name }}</strong>
                            <span>{{ str_repeat('★', $review->rating) }}</span>
                        </div>
                        <p class="mb-1">{{ $review->comment }}</p>
                        <div class="text-secondary small">{{ optional($review->published_at)->format('d.m.Y') }}</div>
                    </div>
                @empty
                    <p class="text-secondary mb-0">Отзывы пока не опубликованы.</p>
                @endforelse
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card-soft p-4 mb-4">
                <h2 class="h4 mb-3">Финансы</h2>
                <div class="mb-2">Минимальная ставка: <strong>{{ number_format($profile->hourly_rate_from, 0, ',', ' ') }} ₽/час</strong></div>
                <div class="mb-2">Смена от: <strong>{{ number_format($profile->shift_rate_from ?? 0, 0, ',', ' ') }} ₽</strong></div>
                <div class="mb-0">Формат: <strong>{{ $profile->employment_format }}</strong></div>
            </div>

            <div class="card-soft p-4 mb-4">
                <h2 class="h4 mb-3">Документы</h2>
                @foreach($documents as $document)
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span>{{ $document['title'] }}</span>
                        <span class="text-secondary">{{ $document['status'] }}</span>
                    </div>
                @endforeach
            </div>

            <div class="card-soft p-4">
                <h2 class="h4 mb-3">Что изменилось</h2>
                <ul class="mb-0">
                    <li>теперь график задается визуально, по реальным датам и часам</li>
                    <li>слоты можно быстро добавить мышью прямо на неделе</li>
                    <li>по клику слот удаляется без ручного редактирования таблицы</li>
                    <li>подбор заявок теперь смотрит и на доступность по времени</li>
                </ul>
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
                    <div class="col-12">
                        <div class="small text-secondary">Слот добавится сразу на все выбранные даты.</div>
                    </div>
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
                        <input type="text" class="form-control" id="caregiver-slot-notes" placeholder="Например, только дневная смена">
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
            var selectedDateValues = [];
            var seedEvents = [];
            var slots = [];
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
                        title: event.title || 'Доступна',
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
                            + '<button type="button" class="btn btn-sm btn-outline-danger rounded-pill" data-remove-slot="' + slot.id + '">Удалить</button>'
                            + '</div>';
                    }).join('')
                    : '<div class="text-secondary small">Пока нет выбранных слотов.</div>';
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
                        title: notes || 'Доступна',
                        start: makeIso(dateString, startTime),
                        end: makeIso(dateString, endTime),
                        notes: notes
                    });
                });

                syncState();
                return true;
            }

            function addSlot() {
                var ok = addSlotsForDates(selectedDateValues, startInput.value, endInput.value, notesInput.value.trim());
                if (ok) {
                    notesInput.value = '';
                    caregiverModal.hide();
                }
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

            flatpickr('#caregiver-template-start-date', {
                locale: 'ru',
                minDate: 'today',
                dateFormat: 'Y-m-d'
            });

            flatpickr('#caregiver-template-end-date', {
                locale: 'ru',
                minDate: 'today',
                dateFormat: 'Y-m-d'
            });

            slots = normalizeSeed(seedEvents);
            addButton.addEventListener('click', addSlot);
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
                    if (selectedWeekdays.indexOf(weekday) !== -1) {
                        dates.push(cursor.toISOString().slice(0, 10));
                    }
                    cursor.setDate(cursor.getDate() + 1);
                }

                if (!dates.length) {
                    window.alert('В выбранном диапазоне нет совпадений по этим дням недели.');
                    return;
                }

                caregiverModal.show();
                document.getElementById('caregiver-slot-modal').dataset.templateDates = JSON.stringify(dates);
            });

            document.querySelectorAll('[data-caregiver-open-modal]').forEach(function (button) {
                button.addEventListener('click', function () {
                    delete document.getElementById('caregiver-slot-modal').dataset.templateDates;
                    caregiverModal.show();
                });
            });

            document.querySelectorAll('[data-caregiver-quick-date]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var kind = button.getAttribute('data-caregiver-quick-date');
                    var date = new Date();
                    if (kind === 'tomorrow') {
                        date.setDate(date.getDate() + 1);
                    }
                    caregiverDatePicker.setDate([date], true);
                });
            });

            document.querySelectorAll('[data-caregiver-time-preset]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var preset = button.getAttribute('data-caregiver-time-preset');
                    if (preset === 'morning') {
                        startInput.value = '08:00';
                        endInput.value = '12:00';
                    }
                    if (preset === 'day') {
                        startInput.value = '12:00';
                        endInput.value = '17:00';
                    }
                    if (preset === 'evening') {
                        startInput.value = '17:00';
                        endInput.value = '21:00';
                    }
                });
            });

            document.getElementById('caregiver-slot-modal').addEventListener('show.bs.modal', function () {
                var templateDates = this.dataset.templateDates;
                if (templateDates) {
                    selectedDateValues = JSON.parse(templateDates);
                }
            });

            summary.addEventListener('click', function (event) {
                var id = event.target.getAttribute('data-remove-slot');
                if (!id) {
                    return;
                }

                slots = slots.filter(function (slot) {
                    return slot.id !== id;
                });
                syncState();
            });

            syncState();
        });
    </script>
@endpush
