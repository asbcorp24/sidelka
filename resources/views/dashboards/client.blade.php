@extends('layouts.app')

@php($title = 'Кабинет клиента')
@php($calendarSeed = old('calendar_slots_json', '[]'))

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

        .slot-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.55rem 0.8rem;
            margin: 0 0.5rem 0.5rem 0;
            border-radius: 999px;
            background: #eef7f5;
            font-size: 0.92rem;
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
            <div class="text-uppercase small text-secondary">Личный кабинет клиента</div>
            <h1 class="section-title mb-0">{{ $user->name }}</h1>
        </div>
        <div class="text-secondary">{{ $user->city }} • онлайн {{ $user->last_seen_at?->diffForHumans() }}</div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-3"><div class="metric"><div class="value">{{ $stats['active_orders'] }}</div><div>Активных заказов</div></div></div>
        <div class="col-md-3"><div class="metric"><div class="value">{{ $stats['completed_orders'] }}</div><div>Завершенных заказов</div></div></div>
        <div class="col-md-3"><div class="metric"><div class="value">{{ number_format($stats['escrow_amount'], 0, ',', ' ') }} ₽</div><div>Зарезервировано на сайте</div></div></div>
        <div class="col-md-3"><div class="metric"><div class="value">{{ $stats['new_responses'] }}</div><div>Подходящих сиделок</div></div></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card-soft p-4 mb-4">
                <h2 class="h4 mb-3">Создать заявку</h2>
                <form action="{{ route('client.orders.store') }}" method="POST" class="row g-3">
                    @csrf
                    <div class="col-md-6">
                        <label class="form-label">Название заявки</label>
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
                        <label class="form-label">Повторяемость / шаблон</label>
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
                            <div class="small text-secondary">Выберите дату, задайте время и добавьте смену. Удаление тоже в один клик.</div>
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
                                    <div class="form-text">Можно отметить несколько дат сразу и одним нажатием добавить одинаковое время.</div>
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
                        <div class="form-text mt-2">Первая и последняя дата заказа будут рассчитаны автоматически по выбранным слотам.</div>
                        <div id="client-calendar-summary" class="mt-3"></div>
                    </div>
                    <div class="col-12 d-flex flex-wrap gap-2">
                        <button class="btn btn-dark rounded-pill px-4">Создать заявку</button>
                        <button class="btn btn-outline-dark rounded-pill px-4" type="submit" formaction="{{ route('client.templates.store') }}">Сохранить как шаблон</button>
                    </div>
                </form>
            </div>

            @foreach($orders as $order)
                <div class="card-soft p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                        <div>
                            <div class="text-uppercase small text-secondary">Заявка клиента</div>
                            <h2 class="h3 mb-1">{{ $order->title }}</h2>
                            <div class="text-secondary">
                                {{ $order->city }} • {{ $order->address }}
                                @if($order->familyMember)
                                    • создано: {{ $order->familyMember->name }}
                                @endif
                            </div>
                        </div>
                        <div class="text-lg-end">
                            <span class="badge text-bg-light">{{ $order->status }}</span>
                            @if($order->needs_today)
                                <div class="badge text-bg-danger mt-2">Нужна сиделка сегодня</div>
                            @elseif($order->is_urgent)
                                <div class="badge text-bg-warning mt-2">Срочный заказ</div>
                            @endif
                            <div class="price-tag mt-2">до {{ number_format($order->hourly_budget, 0, ',', ' ') }} ₽/час</div>
                        </div>
                    </div>
                    <p>{{ $order->description }}</p>
                    @if($order->recurrence_label)
                        <div class="text-secondary mb-2">Повторяемость: {{ $order->recurrence_label }}</div>
                    @endif
                    <div class="mb-3">
                        @foreach($order->services as $service)
                            <span class="service-chip">{{ $service->name }}</span>
                        @endforeach
                        @foreach($order->custom_services ?? [] as $customService)
                            <span class="availability-chip border border-dark-subtle">{{ $customService }}</span>
                        @endforeach
                    </div>
                    @if($order->scheduleSlots->isNotEmpty())
                        <div class="mb-3">
                            <strong>Календарь заказа:</strong>
                            <div class="mt-2">
                                @foreach($order->scheduleSlots as $slot)
                                    <span class="slot-pill">{{ $slot->scheduled_date->format('d.m.Y') }} {{ substr($slot->starts_at, 0, 5) }}-{{ substr($slot->ends_at, 0, 5) }}{{ $slot->label ? ' • ' . $slot->label : '' }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <h3 class="h5">Подобранные сиделки</h3>
                    @forelse($order->matched_caregivers->take(3) as $caregiver)
                        <div class="border rounded-4 p-3 mb-3">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                <div>
                                    <strong>{{ $caregiver->name }}</strong>
                                    <div class="text-secondary">{{ $caregiver->caregiverProfile->experience_years }} лет опыта • рейтинг {{ number_format((float) $caregiver->rating, 1, ',', ' ') }}</div>
                                </div>
                                <div class="text-end">
                                    <div class="price-tag">от {{ number_format($caregiver->caregiverProfile->hourly_rate_from, 0, ',', ' ') }} ₽</div>
                                    <a href="{{ route('caregivers.show', $caregiver->caregiverProfile) }}" class="btn btn-dark rounded-pill btn-sm mt-2">Открыть анкету</a>
                                </div>
                            </div>
                            <div class="mt-2">
                                @foreach($caregiver->matched_services as $matchedService)
                                    <span class="service-chip">{{ $matchedService }}</span>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="text-secondary">Пока нет полных совпадений, но можно ослабить фильтры по ставке или услугам.</p>
                    @endforelse

                    @if($order->caregiver)
                        <div class="border rounded-4 p-3 mt-4">
                            <h3 class="h5">Активный чат по заказу</h3>
                            @php($conversation = $order->conversations->first())
                            @if($conversation)
                                @foreach($conversation->messages as $message)
                                    <div class="chat-bubble {{ $message->sender_id === $user->id ? 'client' : 'caregiver' }} mb-2">
                                        <strong>{{ $message->sender->name }}</strong>
                                        <div>{{ $message->body }}</div>
                                    </div>
                                @endforeach
                            @else
                                <p class="text-secondary mb-0">Чат появится после отклика сиделки.</p>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach

            <div class="card-soft p-4">
                <h2 class="h4 mb-3">Отзывы о клиенте</h2>
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
                    <p class="text-secondary mb-0">Отзывы пока отсутствуют.</p>
                @endforelse
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card-soft p-4 mb-4">
                <h2 class="h4 mb-3">Семейный доступ</h2>
                <form action="{{ route('client.family.store') }}" method="POST" class="row g-3 mb-4">
                    @csrf
                    <div class="col-12"><input type="text" name="name" class="form-control" placeholder="Имя родственника"></div>
                    <div class="col-12"><input type="text" name="relationship" class="form-control" placeholder="Кто это: дочь, сын, внук"></div>
                    <div class="col-md-6"><input type="text" name="phone" class="form-control" placeholder="Телефон"></div>
                    <div class="col-md-6"><input type="email" name="email" class="form-control" placeholder="Email"></div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="can_create_orders" value="1" checked>
                            <label class="form-check-label">Может создавать заявки</label>
                        </div>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="can_view_chats" value="1" checked>
                            <label class="form-check-label">Может видеть чаты</label>
                        </div>
                    </div>
                    <div class="col-12"><textarea name="notes" class="form-control" rows="2" placeholder="Примечание"></textarea></div>
                    <div class="col-12"><button class="btn btn-outline-dark rounded-pill px-4">Добавить родственника</button></div>
                </form>
                @forelse($familyMembers as $familyMember)
                    <div class="border rounded-4 p-3 mb-3">
                        <strong>{{ $familyMember->name }}</strong>
                        <div class="text-secondary">{{ $familyMember->relationship }} • {{ $familyMember->phone }}</div>
                    </div>
                @empty
                    <p class="text-secondary mb-0">Пока добавлен только основной клиент.</p>
                @endforelse
            </div>

            <div class="card-soft p-4 mb-4">
                <h2 class="h4 mb-3">Шаблоны повторяющихся заказов</h2>
                @forelse($templates as $template)
                    <div class="border rounded-4 p-3 mb-3">
                        <strong>{{ $template->title }}</strong>
                        <div class="text-secondary">{{ $template->recurrence_label ?: 'Без повтора' }}</div>
                        <div class="mt-2">
                            @foreach($template->services as $service)
                                <span class="service-chip">{{ $service->name }}</span>
                            @endforeach
                            @foreach($template->custom_services ?? [] as $customService)
                                <span class="availability-chip border border-dark-subtle">{{ $customService }}</span>
                            @endforeach
                        </div>
                        @if($template->scheduleSlots->isNotEmpty())
                            <div class="mt-2">
                                @foreach($template->scheduleSlots as $slot)
                                    <span class="slot-pill">{{ $slot->scheduled_date->format('d.m') }} {{ substr($slot->starts_at, 0, 5) }}-{{ substr($slot->ends_at, 0, 5) }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="text-secondary mb-0">Шаблоны пока не сохранены.</p>
                @endforelse
            </div>

            <div class="card-soft p-4">
                <h2 class="h4 mb-3">Что изменилось</h2>
                <ul class="mb-0">
                    <li>клиент теперь выбирает даты и часы прямо на календаре</li>
                    <li>срочный заказ и “нужна сиделка сегодня” остались в форме</li>
                    <li>семейный доступ и шаблоны повторов работают рядом с календарем</li>
                    <li>свои услуги клиента по-прежнему можно добавлять отдельно от общего каталога</li>
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
                    <div class="col-12">
                        <div class="small text-secondary">Смена добавится сразу на все выбранные даты.</div>
                    </div>
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
                        <input type="text" class="form-control" id="client-slot-notes" placeholder="Например, утро, перевязка, прогулка">
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
                        title: event.title || 'Смена',
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
                        title: notes || 'Смена',
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
                    clientModal.hide();
                }
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

            flatpickr('#client-template-start-date', {
                locale: 'ru',
                minDate: 'today',
                dateFormat: 'Y-m-d'
            });

            flatpickr('#client-template-end-date', {
                locale: 'ru',
                minDate: 'today',
                dateFormat: 'Y-m-d'
            });

            slots = normalizeSeed(seedEvents);
            addButton.addEventListener('click', addSlot);
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

                clientModal.show();
                document.getElementById('client-slot-modal').dataset.templateDates = JSON.stringify(dates);
            });

            document.querySelectorAll('[data-client-open-modal]').forEach(function (button) {
                button.addEventListener('click', function () {
                    delete document.getElementById('client-slot-modal').dataset.templateDates;
                    clientModal.show();
                });
            });

            document.querySelectorAll('[data-client-quick-date]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var kind = button.getAttribute('data-client-quick-date');
                    var date = new Date();
                    if (kind === 'tomorrow') {
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
