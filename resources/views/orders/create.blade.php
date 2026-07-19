@extends('layouts.app')

@php($title = $selectedCaregiverProfile ? 'Заказ выбранной сиделке' : 'Новый заказ')

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
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card-soft p-4 mb-4">
                <div class="text-uppercase small text-secondary">Оформление заказа</div>
                <h1 class="section-title mb-2">{{ $selectedCaregiverProfile ? 'Заказ для выбранной сиделки' : 'Новый заказ' }}</h1>
                <p class="text-secondary mb-0">
                    {{ $selectedCaregiverProfile
                        ? 'Заказ будет сразу отправлен выбранной сиделке. После ее подтверждения откроется личный чат по этой заявке.'
                        : 'После сохранения система подберет сиделок по услугам, ставке и расписанию.' }}
                </p>
            </div>

            @if($selectedCaregiverProfile)
                <div class="card-soft p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <div>
                            <div class="text-uppercase small text-secondary">Выбрана сиделка</div>
                            <h2 class="h4 mb-1">{{ $selectedCaregiverProfile->user->name }}</h2>
                            <div class="text-secondary">{{ $selectedCaregiverProfile->user->city }} • {{ $selectedCaregiverProfile->experience_years }} лет опыта</div>
                        </div>
                        <div class="price-tag">от {{ number_format($selectedCaregiverProfile->hourly_rate_from, 0, ',', ' ') }} ₽/час</div>
                    </div>
                </div>
            @endif

            <div class="card-soft p-4">
                <form action="{{ route('client.orders.store') }}" method="POST" class="row g-3">
                    @csrf
                    @if($selectedCaregiverProfile)
                        <input type="hidden" name="caregiver_profile_id" value="{{ $selectedCaregiverProfile->id }}">
                    @endif

                    <div class="col-md-6">
                        <label class="form-label">Название заказа</label>
                        <input type="text" name="title" class="form-control" value="{{ old('title') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Город</label>
                        <input type="text" name="city" class="form-control" value="{{ old('city', $user->city) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Тип графика</label>
                        <select name="schedule_type" class="form-select">
                            <option value="hourly">Почасово</option>
                            <option value="daily">Дневная смена</option>
                            <option value="night">Ночная смена</option>
                            <option value="calendar">По календарю</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Описание ухода</label>
                        <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Адрес</label>
                        <input type="text" name="address" class="form-control" value="{{ old('address') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Бюджет, ₽/час</label>
                        <input type="number" name="hourly_budget" class="form-control" value="{{ old('hourly_budget') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Кто создает заказ</label>
                        <select name="family_member_id" class="form-select">
                            <option value="">Сам клиент</option>
                            @foreach($familyMembers as $familyMember)
                                <option value="{{ $familyMember->id }}">{{ $familyMember->name }} • {{ $familyMember->relationship }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Возраст пациента</label>
                        <input type="number" name="patient_age" class="form-control" value="{{ old('patient_age') }}">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Имя пациента</label>
                        <input type="text" name="patient_name" class="form-control" value="{{ old('patient_name') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Повторяемость</label>
                        <input type="text" name="recurrence_label" class="form-control" value="{{ old('recurrence_label') }}" placeholder="Например, пн/ср/пт">
                    </div>

                    <div class="col-md-3">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="is_urgent" value="1" {{ old('is_urgent') ? 'checked' : '' }}>
                            <label class="form-check-label">Срочный заказ</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="needs_today" value="1" {{ old('needs_today') ? 'checked' : '' }}>
                            <label class="form-check-label">Нужна сиделка сегодня</label>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Особые требования</label>
                        <textarea name="special_requirements" class="form-control" rows="2">{{ old('special_requirements') }}</textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Свои нужные услуги клиента</label>
                        <textarea name="custom_service_lines" class="form-control" rows="3">{{ old('custom_service_lines') }}</textarea>
                        <div class="form-text">Если нужной услуги нет в общем списке, впишите каждую новой строкой. Эти пункты будут только в вашей заявке.</div>
                    </div>

                    <div class="col-12">
                        <label class="form-label d-block">Общие услуги от суперадмина</label>
                        <div class="row">
                            @foreach($services as $service)
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="service_ids[]" value="{{ $service->id }}">
                                        <label class="form-check-label">
                                            {{ $service->name }}
                                            @if($service->requires_medical_training)
                                                <span class="text-danger">• медуслуга</span>
                                            @endif
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                            <label class="form-label mb-0">Календарь заказа</label>
                            <div class="small text-secondary">Выберите даты, задайте время и добавьте смены. Это и есть реальное расписание заказа.</div>
                        </div>
                        <div class="slot-builder-shell">
                            <div class="row g-3 align-items-start">
                                <div class="col-lg-6">
                                    <label class="form-label">Даты</label>
                                    <input type="text" class="form-control" id="client-date-picker" readonly>
                                    <div class="d-flex gap-2 flex-wrap mt-2">
                                        <button type="button" class="quick-chip" data-client-quick-date="today">Сегодня</button>
                                        <button type="button" class="quick-chip" data-client-quick-date="tomorrow">Завтра</button>
                                        <button type="button" class="quick-chip" data-client-open-modal="1">Добавить смену</button>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <label class="form-label">Шаблон по дням недели</label>
                                    <div class="row g-2 mb-2">
                                        @foreach(['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'] as $weekdayIndex => $weekdayLabel)
                                            <div class="col-4 col-md-3">
                                                <label class="form-check border rounded-3 px-2 py-2 small w-100">
                                                    <input class="form-check-input me-1 client-template-weekday" type="checkbox" value="{{ $weekdayIndex + 1 }}">
                                                    <span class="form-check-label">{{ $weekdayLabel }}</span>
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <input type="text" class="form-control" id="client-template-start-date" placeholder="Дата начала" readonly>
                                        </div>
                                        <div class="col-md-6">
                                            <input type="text" class="form-control" id="client-template-end-date" placeholder="Дата конца" readonly>
                                        </div>
                                        <div class="col-12">
                                            <button type="button" class="btn btn-outline-dark rounded-pill px-4" id="client-apply-template">Применить шаблон</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="calendar_slots_json" id="client-calendar-slots" value='@json(json_decode($calendarSeed, true))'>
                        <div id="client-calendar-summary" class="mt-3"></div>
                    </div>

                    <div class="col-12 d-flex flex-wrap gap-2">
                        <button class="btn btn-dark rounded-pill px-4">Создать заказ</button>
                        <button class="btn btn-outline-dark rounded-pill px-4" type="submit" formaction="{{ route('client.templates.store') }}">Сохранить как шаблон</button>
                        <a href="{{ route('client.dashboard') }}" class="btn btn-outline-secondary rounded-pill px-4">Вернуться в кабинет</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card-soft p-4 mb-4">
                <h2 class="h4 mb-3">Что будет дальше</h2>
                <ul class="mb-0">
                    @if($selectedCaregiverProfile)
                        <li>заказ сразу уйдет выбранной сиделке</li>
                        <li>после подтверждения откроется личный чат именно по этой заявке</li>
                        <li>дальше согласуете детали ухода и выход на смену</li>
                    @else
                        <li>система подберет сиделок по услугам, расписанию и ставке</li>
                        <li>в кабинете вы увидите подходящие анкеты</li>
                        <li>сможете нажать «Выбрать эту сиделку» и отправить приглашение</li>
                    @endif
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="client-slot-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header border-0">
                <h2 class="modal-title h4 mb-0">Добавить смену в заказ</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-0">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Время с</label>
                        <input type="time" class="form-control" id="client-slot-start" value="09:00">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Время до</label>
                        <input type="time" class="form-control" id="client-slot-end" value="18:00">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Комментарий к смене</label>
                        <input type="text" class="form-control" id="client-slot-notes" placeholder="Например, прогулка, перевязка, дневная смена">
                    </div>
                    <div class="col-12">
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="button" class="quick-chip" data-client-time-preset="morning">Утро</button>
                            <button type="button" class="quick-chip" data-client-time-preset="day">День</button>
                            <button type="button" class="quick-chip" data-client-time-preset="evening">Вечер</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-dark rounded-pill px-4" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-dark rounded-pill px-4" id="client-add-slot">Добавить смену</button>
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
            var input = document.getElementById('client-calendar-slots');
            var summary = document.getElementById('client-calendar-summary');
            var startInput = document.getElementById('client-slot-start');
            var endInput = document.getElementById('client-slot-end');
            var notesInput = document.getElementById('client-slot-notes');
            var addButton = document.getElementById('client-add-slot');
            var applyTemplateButton = document.getElementById('client-apply-template');
            var seedEvents = [];
            var slots = [];
            var selectedDateValues = [];
            var clientModal = new bootstrap.Modal(document.getElementById('client-slot-modal'));

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
                            + '<button type="button" class="btn btn-sm btn-outline-danger rounded-pill" data-remove-slot="' + slot.id + '">Удалить</button>'
                            + '</div>';
                    }).join('')
                    : '<div class="text-secondary small">Пока не выбрано ни одной смены.</div>';
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

            var clientDatePicker = flatpickr('#client-date-picker', {
                inline: true,
                locale: 'ru',
                minDate: 'today',
                mode: 'multiple',
                dateFormat: 'Y-m-d',
                defaultDate: ['today'],
                onChange: function (pickedDates) {
                    selectedDateValues = pickedDates.map(function (date) {
                        return clientDatePicker.formatDate(date, 'Y-m-d');
                    });
                },
                onReady: function (selected, _, instance) {
                    selectedDateValues = selected.map(function (date) {
                        return instance.formatDate(date, 'Y-m-d');
                    });
                }
            });

            flatpickr('#client-template-start-date', { locale: 'ru', minDate: 'today', dateFormat: 'Y-m-d' });
            flatpickr('#client-template-end-date', { locale: 'ru', minDate: 'today', dateFormat: 'Y-m-d' });

            slots = normalizeSeed(seedEvents);
            syncState();

            addButton.addEventListener('click', function () {
                if (addSlotsForDates(selectedDateValues, startInput.value, endInput.value, notesInput.value.trim())) {
                    notesInput.value = '';
                    clientModal.hide();
                }
            });

            applyTemplateButton.addEventListener('click', function () {
                var startDate = document.getElementById('client-template-start-date').value;
                var endDate = document.getElementById('client-template-end-date').value;
                var selectedWeekdays = Array.from(document.querySelectorAll('.client-template-weekday:checked')).map(function (checkbox) {
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

                document.getElementById('client-slot-modal').dataset.templateDates = JSON.stringify(dates);
                clientModal.show();
            });

            document.querySelectorAll('[data-client-open-modal]').forEach(function (button) {
                button.addEventListener('click', function () {
                    delete document.getElementById('client-slot-modal').dataset.templateDates;
                    clientModal.show();
                });
            });

            document.querySelectorAll('[data-client-quick-date]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var date = new Date();
                    if (button.getAttribute('data-client-quick-date') === 'tomorrow') {
                        date.setDate(date.getDate() + 1);
                    }
                    clientDatePicker.setDate([date], true);
                });
            });

            document.querySelectorAll('[data-client-time-preset]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var preset = button.getAttribute('data-client-time-preset');
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

            document.getElementById('client-slot-modal').addEventListener('show.bs.modal', function () {
                if (this.dataset.templateDates) {
                    selectedDateValues = JSON.parse(this.dataset.templateDates);
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
        });
    </script>
@endpush
