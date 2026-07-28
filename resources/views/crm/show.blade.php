@extends('layouts.app')

@php($title = 'CRM заявка '.strtoupper(substr($crmRequest->public_id, 0, 8)))

@section('content')
<div class="container-fluid px-3 px-xl-5 py-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <div class="text-uppercase small text-secondary">Телефонная заявка {{ strtoupper(substr($crmRequest->public_id, 0, 8)) }}</div>
            <h1 class="section-title mb-1">{{ $crmRequest->caller_name }}</h1>
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <span class="badge {{ $crmRequest->status_badge_class }}">{{ $crmRequest->status_label }}</span>
                <span class="badge text-bg-light">{{ $crmRequest->priority_label }}</span>
                <a href="tel:{{ $crmRequest->caller_phone }}" class="fw-bold">{{ $crmRequest->caller_phone }}</a>
                <span class="text-secondary">{{ $crmRequest->city }} • ответственный {{ $crmRequest->responsible->name ?? 'не назначен' }}</span>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('crm.dashboard') }}" class="btn btn-outline-dark rounded-pill">Все заявки</a>
            <a href="{{ route('crm.kanban') }}" class="btn btn-outline-dark rounded-pill">Kanban</a>
            <a href="{{ route('crm.long-orders.index') }}" class="btn btn-outline-dark rounded-pill">Долгие заказы</a>
            <a href="{{ route('crm.people.index') }}" class="btn btn-dark rounded-pill">Люди</a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card-soft p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h2 class="h4 mb-0">Данные заявки и этап работы</h2>
                    <span class="text-secondary small">Воронка: новая → квалификация → ищем → предложили → согласовано → в работе → закрыта</span>
                </div>
                <form action="{{ route('crm.requests.update', $crmRequest) }}" method="POST" class="row g-3">
                    @csrf
                    @method('PATCH')
                    <div class="col-md-4">
                        <label class="form-label">Статус</label>
                        <select name="status" class="form-select">
                            @foreach($statusLabels as $value => $label)
                                <option value="{{ $value }}" {{ $crmRequest->status === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Приоритет</label>
                        <select name="priority" class="form-select">
                            @foreach($priorityLabels as $value => $label)
                                <option value="{{ $value }}" {{ $crmRequest->priority === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Ответственный</label>
                        <select name="responsible_user_id" class="form-select">
                            <option value="">Не назначен</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}" {{ $crmRequest->responsible_user_id === $employee->id ? 'selected' : '' }}>{{ $employee->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6"><label class="form-label">Кто звонит</label><input type="text" name="caller_name" value="{{ $crmRequest->caller_name }}" class="form-control" required></div>
                    <div class="col-md-3"><label class="form-label">Телефон</label><input type="text" name="caller_phone" value="{{ $crmRequest->caller_phone }}" class="form-control" required></div>
                    <div class="col-md-3"><label class="form-label">Email</label><input type="email" name="caller_email" value="{{ $crmRequest->caller_email }}" class="form-control"></div>
                    <div class="col-md-8"><label class="form-label">Подопечный</label><input type="text" name="patient_name" value="{{ $crmRequest->patient_name }}" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label">Возраст</label><input type="number" min="0" max="120" name="patient_age" value="{{ $crmRequest->patient_age }}" class="form-control"></div>
                    <div class="col-md-5"><label class="form-label">Город</label><input type="text" name="city" value="{{ $crmRequest->city }}" class="form-control"></div>
                    <div class="col-md-7"><label class="form-label">Адрес</label><input type="text" name="address" value="{{ $crmRequest->address }}" class="form-control"></div>
                    <div class="col-12"><label class="form-label">Что требуется</label><textarea name="service_text" class="form-control" rows="4" required>{{ $crmRequest->service_text }}</textarea></div>
                    <div class="col-12"><label class="form-label">Желаемый график</label><textarea name="schedule_text" class="form-control" rows="2">{{ $crmRequest->schedule_text }}</textarea></div>
                    <div class="col-md-4"><label class="form-label">Начало</label><input type="datetime-local" name="starts_at" value="{{ $crmRequest->starts_at?->format('Y-m-d\TH:i') }}" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label">Окончание</label><input type="datetime-local" name="ends_at" value="{{ $crmRequest->ends_at?->format('Y-m-d\TH:i') }}" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label">Бюджет, ₽/час</label><input type="number" min="0" name="budget_per_hour" value="{{ $crmRequest->budget_per_hour }}" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label">Стоимость лида, ₽</label><input type="number" min="0" name="lead_cost" value="{{ $crmRequest->lead_cost }}" class="form-control"></div>
                    <div class="col-md-6">
                        <label class="form-label">Связанный клиент</label>
                        <select name="client_user_id" class="form-select">
                            <option value="">Не выбран</option>
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}" {{ $crmRequest->client_user_id === $client->id ? 'selected' : '' }}>{{ $client->name }} — {{ $client->phone }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Выбранная сиделка</label>
                        <select name="caregiver_user_id" class="form-select">
                            <option value="">Не выбрана</option>
                            @foreach($caregivers as $caregiver)
                                <option value="{{ $caregiver->id }}" {{ $crmRequest->caregiver_user_id === $caregiver->id ? 'selected' : '' }}>{{ $caregiver->name }} — {{ $caregiver->phone }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6"><label class="form-label">Следующий контакт</label><input type="datetime-local" name="next_contact_at" value="{{ $crmRequest->next_contact_at?->format('Y-m-d\TH:i') }}" class="form-control"></div>
                    <div class="col-md-6"><label class="form-label">Последний контакт</label><input type="text" value="{{ $crmRequest->last_contact_at?->format('d.m.Y H:i') ?: '—' }}" class="form-control" disabled></div>
                    <div class="col-12"><label class="form-label">Внутренние примечания</label><textarea name="notes" class="form-control" rows="3">{{ $crmRequest->notes }}</textarea></div>
                    <div class="col-12"><button class="btn btn-dark px-5">Сохранить изменения</button></div>
                </form>
            </div>

            <div class="card-soft p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h2 class="h4 mb-0">Скрипт оператора</h2>
                    <span class="text-secondary small">Чек-лист разговора</span>
                </div>
                <div class="row g-2">
                    @foreach($operatorScript as $step)
                        <div class="col-md-6">
                            <div class="border rounded-4 p-3 h-100">{{ $step }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="card-soft p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h2 class="h4 mb-0">История касаний</h2>
                    <span class="text-secondary small">Звонки, сообщения, изменения статуса, цены и связей</span>
                </div>
                <form action="{{ route('crm.requests.interactions.store', $crmRequest) }}" method="POST" class="row g-2 mb-4" id="crm-interaction-form">
                    @csrf
                    <div class="col-md-3">
                        <select name="type" class="form-select">
                            <option value="call_in">Входящий звонок</option>
                            <option value="call_out">Исходящий звонок</option>
                            <option value="messenger">Мессенджер</option>
                            <option value="sms">SMS</option>
                            <option value="note">Заметка</option>
                            <option value="meeting">Встреча</option>
                        </select>
                    </div>
                    <div class="col-md-3"><input type="text" name="result" class="form-control" placeholder="Результат"></div>
                    <div class="col-md-6"><input type="text" name="comment" id="crm-comment-input" class="form-control" placeholder="Что обсудили и о чем договорились" required></div>
                    <div class="col-md-8">
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($messageTemplates as $templateKey => $template)
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-dark crm-template-btn"
                                    data-text="{{ $template['text'] }}"
                                    title="{{ $template['audience'] === 'client' ? 'Шаблон для клиента' : ($template['audience'] === 'caregiver' ? 'Шаблон для сиделки' : 'Общий шаблон') }}"
                                >{{ $template['title'] }}</button>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-md-4"><label class="form-label small">Следующий контакт</label><input type="datetime-local" name="next_contact_at" class="form-control"></div>
                    <div class="col-12"><button class="btn btn-outline-dark">Добавить в историю</button></div>
                </form>

                @forelse($crmRequest->interactions as $interaction)
                    <div class="border-start border-3 ps-3 py-2 mb-3">
                        <div class="d-flex justify-content-between gap-2 flex-wrap">
                            <strong>{{ $interaction->type }}{{ $interaction->result ? ' • '.$interaction->result : '' }}</strong>
                            <span class="small text-secondary">{{ $interaction->happened_at->format('d.m.Y H:i') }}</span>
                        </div>
                        <div>{{ $interaction->comment }}</div>
                        <div class="small text-secondary">{{ $interaction->employee->name ?? 'Система' }}</div>
                    </div>
                @empty
                    <p class="text-secondary mb-0">История пока пустая.</p>
                @endforelse
            </div>

            <div class="card-soft p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h2 class="h4 mb-0">Подбор сиделок в один клик</h2>
                    <span class="text-secondary small">С объяснением, почему подходит или не подходит</span>
                </div>
                <div class="row g-3">
                    @forelse($matchedCaregivers as $match)
                        @php($caregiver = $match['caregiver'])
                        <div class="col-md-6">
                            <div class="border rounded-4 p-3 h-100 {{ $crmRequest->caregiver_user_id === $caregiver->id ? 'border-success border-2' : '' }}">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div>
                                        <a href="{{ route('crm.people.show', $caregiver) }}" class="fw-bold text-decoration-none">{{ $caregiver->name }}</a>
                                        <div class="small text-secondary">{{ $caregiver->phone }} • {{ $caregiver->city }}</div>
                                    </div>
                                    <span class="badge text-bg-dark">{{ $match['score'] }} баллов</span>
                                </div>
                                <div class="small text-secondary mt-2">от {{ number_format($caregiver->caregiverProfile?->hourly_rate_from ?? 0, 0, ',', ' ') }} ₽/час • рейтинг {{ number_format((float) $caregiver->rating, 1, ',', ' ') }}</div>

                                <div class="mt-3">
                                    <div class="small fw-semibold mb-2">Почему подходит</div>
                                    @foreach($match['reasons'] as $reason)
                                        <span class="service-chip">{{ $reason }}</span>
                                    @endforeach
                                </div>

                                @if(!empty($match['warnings']))
                                    <div class="mt-3">
                                        <div class="small fw-semibold mb-2 text-danger">Что проверить</div>
                                        @foreach($match['warnings'] as $warning)
                                            <span class="availability-chip text-danger bg-danger-subtle">{{ $warning }}</span>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="row g-2 mt-3 small">
                                    <div class="col-6"><div class="border rounded-3 p-2">Жалобы: {{ $match['quality']['complaints'] }}</div></div>
                                    <div class="col-6"><div class="border rounded-3 p-2">Подтверждение: {{ $match['quality']['confirmation_rate'] !== null ? $match['quality']['confirmation_rate'].'%' : 'нет данных' }}</div></div>
                                    <div class="col-6"><div class="border rounded-3 p-2">Документов: {{ $match['documents']['total'] }}</div></div>
                                    <div class="col-6"><div class="border rounded-3 p-2">Блокирующих: {{ $match['documents']['blocking'] }}</div></div>
                                </div>

                                <form action="{{ route('crm.requests.select-caregiver', [$crmRequest, $caregiver]) }}" method="POST" class="mt-3">
                                    @csrf
                                    <button class="btn btn-outline-dark rounded-pill w-100">Выбрать сиделку</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="col-12 text-secondary">В городе {{ $crmRequest->city }} сиделок не найдено. Можно изменить город или создать карточку новой сиделки.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card-soft p-4 mb-4">
                <h2 class="h4 mb-3">Связанные карточки</h2>
                <div class="mb-3">
                    <div class="small text-secondary">Клиент</div>
                    @if($crmRequest->clientUser)
                        <a href="{{ route('crm.people.show', $crmRequest->clientUser) }}" class="fw-bold">{{ $crmRequest->clientUser->name }}</a>
                        <div>{{ $crmRequest->clientUser->phone }}</div>
                    @else
                        <span>Не создан или не выбран</span>
                    @endif
                </div>
                <div>
                    <div class="small text-secondary">Сиделка</div>
                    @if($crmRequest->caregiverUser)
                        <a href="{{ route('crm.people.show', $crmRequest->caregiverUser) }}" class="fw-bold">{{ $crmRequest->caregiverUser->name }}</a>
                        <div>{{ $crmRequest->caregiverUser->phone }}</div>
                    @else
                        <span>Не выбрана</span>
                    @endif
                </div>
            </div>

            <div class="card-soft p-4 mb-4">
                <h2 class="h4 mb-3">CRM-финансы</h2>
                <div class="d-flex justify-content-between py-2 border-bottom"><span>Баланс клиента</span><strong>{{ number_format($financialSummary['client_balance'], 0, ',', ' ') }} ₽</strong></div>
                <div class="d-flex justify-content-between py-2 border-bottom"><span>Оплачено клиентом</span><strong>{{ number_format($financialSummary['client_payments'], 0, ',', ' ') }} ₽</strong></div>
                <div class="d-flex justify-content-between py-2 border-bottom"><span>Заморожено</span><strong>{{ number_format($financialSummary['frozen'], 0, ',', ' ') }} ₽</strong></div>
                <div class="d-flex justify-content-between py-2 border-bottom"><span>Счет заказа</span><strong>{{ number_format($financialSummary['order_total'], 0, ',', ' ') }} ₽</strong></div>
                <div class="d-flex justify-content-between py-2 border-bottom"><span>Выплаты сиделке</span><strong>{{ number_format($financialSummary['payouts'], 0, ',', ' ') }} ₽</strong></div>
                <div class="d-flex justify-content-between py-2 border-bottom"><span>Возвраты</span><strong>{{ number_format($financialSummary['refunds'], 0, ',', ' ') }} ₽</strong></div>
                <div class="d-flex justify-content-between pt-3"><span>Долг клиента</span><strong class="{{ $financialSummary['debt'] > 0 ? 'text-danger' : '' }}">{{ number_format($financialSummary['debt'], 0, ',', ' ') }} ₽</strong></div>
            </div>

            <div class="card-soft p-4 mb-4">
                <h2 class="h4 mb-2">Создать карточку человека</h2>
                <p class="small text-secondary">Используйте, если звонящий или новая сиделка еще не зарегистрированы.</p>
                <form action="{{ route('crm.requests.people.store', $crmRequest) }}" method="POST" class="row g-2">
                    @csrf
                    <div class="col-12"><select name="role" id="requestPersonRole" class="form-select"><option value="client">Клиент</option><option value="caregiver">Сиделка</option></select></div>
                    <div class="col-12"><input type="text" name="name" value="{{ $crmRequest->caller_name }}" class="form-control" placeholder="ФИО" required></div>
                    <div class="col-12"><input type="text" name="phone" value="{{ $crmRequest->caller_phone }}" class="form-control" placeholder="Телефон" required></div>
                    <div class="col-12"><input type="text" name="city" value="{{ $crmRequest->city }}" class="form-control" placeholder="Город"></div>
                    <div class="col-12"><input type="email" name="email" value="{{ $crmRequest->caller_email }}" class="form-control" placeholder="Email, если нужен кабинет"></div>
                    <div class="request-caregiver-extra col-md-6 d-none"><input type="number" name="experience_years" min="0" class="form-control" placeholder="Опыт"></div>
                    <div class="request-caregiver-extra col-md-6 d-none"><input type="number" name="hourly_rate_from" min="0" class="form-control" placeholder="Ставка"></div>
                    <div class="col-12"><button class="btn btn-outline-dark w-100">Создать и связать</button></div>
                </form>
            </div>

            <div class="card-soft p-4 mb-4">
                <h2 class="h4 mb-3">Задачи сотруднику</h2>
                <form action="{{ route('crm.requests.tasks.store', $crmRequest) }}" method="POST" class="row g-2 mb-3">
                    @csrf
                    <div class="col-12"><input type="text" name="title" class="form-control" placeholder="Перезвонить, найти сиделку, проверить документы" required></div>
                    <div class="col-12"><textarea name="description" class="form-control" rows="2" placeholder="Подробности"></textarea></div>
                    <div class="col-12"><select name="assigned_to_id" class="form-select"><option value="">Назначить себе</option>@foreach($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->name }}</option>@endforeach</select></div>
                    <div class="col-md-6"><select name="priority" class="form-select"><option value="normal">Обычная</option><option value="high">Высокая</option><option value="urgent">Срочная</option><option value="low">Низкая</option></select></div>
                    <div class="col-md-6"><input type="datetime-local" name="due_at" class="form-control"></div>
                    <div class="col-12"><button class="btn btn-dark w-100">Создать задачу</button></div>
                </form>
                @foreach($crmRequest->tasks as $task)
                    <div class="border rounded-4 p-3 mb-2 {{ $task->status === 'open' && $task->due_at && $task->due_at->isPast() ? 'crm-overdue' : '' }}">
                        <strong>{{ $task->title }}</strong>
                        <div class="small text-secondary">{{ $task->assignedTo->name ?? 'Не назначена' }} • {{ $task->due_at?->format('d.m.Y H:i') ?: 'без срока' }}</div>
                        @if($task->status === 'open')
                            <form action="{{ route('crm.tasks.complete', $task) }}" method="POST" class="mt-2">
                                @csrf
                                @method('PATCH')
                                <button class="btn btn-sm btn-outline-success">Выполнено</button>
                            </form>
                        @else
                            <span class="badge text-bg-success mt-2">Выполнена</span>
                        @endif
                    </div>
                @endforeach
            </div>

            @if($longOrders->isNotEmpty())
                <div class="card-soft p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <h2 class="h4 mb-0">Долгие заказы</h2>
                        <span class="text-secondary small">Много смен, много сиделок, замены</span>
                    </div>
                    @foreach($longOrders as $longOrder)
                        <div class="border rounded-4 p-3 mb-2">
                            <strong>#{{ $longOrder->id }} {{ $longOrder->title }}</strong>
                            <div class="small text-secondary">Смен: {{ $longOrder->scheduleSlots->count() }} • Назначений: {{ $longOrder->caregiverAssignments->count() }}</div>
                            <div class="small text-secondary">Статус: {{ $longOrder->status_label }}</div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if($incidents->isNotEmpty())
                <div class="card-soft p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <h2 class="h4 mb-0">Журнал инцидентов</h2>
                        <span class="text-secondary small">Падения, пропуск лекарства, конфликты, экстренные вызовы</span>
                    </div>
                    @foreach($incidents as $incident)
                        <div class="border rounded-4 p-3 mb-2">
                            <div class="d-flex justify-content-between gap-2 flex-wrap">
                                <strong>{{ $incident->incident_type }}</strong>
                                <span class="badge text-bg-danger">{{ $incident->severity }}</span>
                            </div>
                            <div class="small text-secondary">{{ $incident->occurred_at?->format('d.m.Y H:i') }}</div>
                            <div class="mt-1">{{ $incident->description }}</div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="card-soft p-4">
                <h2 class="h4 mb-3">Оформить заказ платформы</h2>
                @if($crmRequest->order)
                    <div class="alert alert-success mb-0">Создан заказ #{{ $crmRequest->order->id }}: {{ $crmRequest->order->title }}</div>
                @else
                    <form action="{{ route('crm.requests.convert', $crmRequest) }}" method="POST" class="row g-2">
                        @csrf
                        <div class="col-12"><label class="form-label small">Клиент</label><select name="client_user_id" class="form-select" required><option value="">Выберите клиента</option>@foreach($clients as $client)<option value="{{ $client->id }}" {{ $crmRequest->client_user_id === $client->id ? 'selected' : '' }}>{{ $client->name }} — {{ $client->phone }}</option>@endforeach</select></div>
                        <div class="col-12"><label class="form-label small">Сиделка, необязательно</label><select name="caregiver_user_id" class="form-select"><option value="">Опубликовать без сиделки</option>@foreach($caregivers as $caregiver)<option value="{{ $caregiver->id }}" {{ $crmRequest->caregiver_user_id === $caregiver->id ? 'selected' : '' }}>{{ $caregiver->name }}</option>@endforeach</select></div>
                        <div class="col-12"><label class="form-label small">Начало</label><input type="datetime-local" name="starts_at" value="{{ $crmRequest->starts_at?->format('Y-m-d\TH:i') }}" class="form-control" required></div>
                        <div class="col-12"><label class="form-label small">Окончание</label><input type="datetime-local" name="ends_at" value="{{ $crmRequest->ends_at?->format('Y-m-d\TH:i') }}" class="form-control" required></div>
                        <div class="col-12"><label class="form-label small">Стоимость, ₽/час</label><input type="number" min="0" name="hourly_budget" value="{{ $crmRequest->budget_per_hour }}" class="form-control" required></div>
                        <div class="col-12"><button class="btn btn-success w-100">Создать настоящий заказ</button></div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const role = document.getElementById('requestPersonRole');
    const commentInput = document.getElementById('crm-comment-input');
    const updatePersonFields = () => document.querySelectorAll('.request-caregiver-extra').forEach(el => el.classList.toggle('d-none', role.value !== 'caregiver'));
    role.addEventListener('change', updatePersonFields);
    updatePersonFields();

    document.querySelectorAll('.crm-template-btn').forEach((button) => {
        button.addEventListener('click', () => {
            if (commentInput) {
                commentInput.value = button.dataset.text || '';
                commentInput.focus();
            }
        });
    });
</script>
@endpush
