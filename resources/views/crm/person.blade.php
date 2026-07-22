@extends('layouts.app')

@php($title = 'CRM — '.$person->name)

@section('content')
<div class="container-fluid px-3 px-xl-5 py-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <div class="text-uppercase small text-secondary">{{ $person->isCaregiver() ? 'Карточка сиделки' : 'Карточка клиента' }}</div>
            <h1 class="section-title mb-1">{{ $person->name }}</h1>
            <div class="text-secondary">
                <a href="tel:{{ $person->phone }}">{{ $person->phone ?: 'телефон не указан' }}</a>
                • {{ $person->city ?: 'город не указан' }}
                • {{ str_ends_with($person->email, '@sidelka.local') ? 'внутренняя карточка без email' : $person->email }}
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('crm.people.index') }}" class="btn btn-outline-dark rounded-pill">Все люди</a>
            <a href="{{ route('crm.dashboard') }}" class="btn btn-dark rounded-pill">CRM</a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            @if($person->isCaregiver())
                <div class="card-soft p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                        <div>
                            <h2 class="h4 mb-1">Доступность сиделки</h2>
                            <div class="text-secondary small">Опыт: {{ $person->caregiverProfile?->experience_years ?? 0 }} лет • ставка от {{ number_format($person->caregiverProfile?->hourly_rate_from ?? 0, 0, ',', ' ') }} ₽/час</div>
                        </div>
                        <span class="badge {{ $person->is_verified ? 'text-bg-success' : 'text-bg-warning' }}">{{ $person->is_verified ? 'Проверена' : 'Требует проверки' }}</span>
                    </div>

                    <form action="{{ route('crm.caregivers.availability.store', $person) }}" method="POST" class="row g-2 mb-4">
                        @csrf
                        <div class="col-md-3">
                            <select name="weekday" class="form-select">
                                <option value="">День недели</option>
                                @foreach([1=>'Понедельник',2=>'Вторник',3=>'Среда',4=>'Четверг',5=>'Пятница',6=>'Суббота',7=>'Воскресенье'] as $day => $label)
                                    <option value="{{ $day }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3"><input type="date" name="specific_date" class="form-control" title="Конкретная дата"></div>
                        <div class="col-md-2"><input type="time" name="starts_at" class="form-control" required></div>
                        <div class="col-md-2"><input type="time" name="ends_at" class="form-control" required></div>
                        <div class="col-md-2"><button class="btn btn-dark w-100">Добавить</button></div>
                        <div class="col-md-4">
                            <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="is_recurring" value="1" id="recurring"><label class="form-check-label" for="recurring">Повторяется еженедельно</label></div>
                        </div>
                        <div class="col-md-8"><input type="text" name="notes" class="form-control" placeholder="Комментарий: только центр, не может ночью и т. п."></div>
                    </form>

                    @forelse($person->caregiverProfile?->availabilitySlots ?? [] as $slot)
                        <div class="d-flex justify-content-between align-items-center border rounded-4 p-3 mb-2">
                            <div>
                                <strong>
                                    @if($slot->specific_date)
                                        {{ $slot->specific_date->format('d.m.Y') }}
                                    @else
                                        {{ [1=>'Понедельник',2=>'Вторник',3=>'Среда',4=>'Четверг',5=>'Пятница',6=>'Суббота',7=>'Воскресенье'][$slot->weekday] ?? 'День' }}
                                    @endif
                                    {{ substr($slot->starts_at, 0, 5) }}–{{ substr($slot->ends_at, 0, 5) }}
                                </strong>
                                <div class="small text-secondary">{{ $slot->is_recurring ? 'еженедельно' : 'разово' }} {{ $slot->notes ? '• '.$slot->notes : '' }}</div>
                            </div>
                            <form action="{{ route('crm.availability.destroy', $slot) }}" method="POST">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Удалить</button></form>
                        </div>
                    @empty
                        <p class="text-secondary">Свободные часы ещё не записаны.</p>
                    @endforelse
                </div>
            @endif

            <div class="card-soft p-4 mb-4">
                <h2 class="h4 mb-3">Связанные CRM-заявки</h2>
                @forelse($requests as $item)
                    <div class="border rounded-4 p-3 mb-2 d-flex justify-content-between align-items-center gap-3">
                        <div><a href="{{ route('crm.requests.show', $item) }}" class="fw-bold text-decoration-none">{{ strtoupper(substr($item->public_id, 0, 8)) }}</a><div class="small text-secondary">{{ $item->service_text }}</div></div>
                        <span class="badge {{ $item->status_badge_class }}">{{ $item->status_label }}</span>
                    </div>
                @empty
                    <p class="text-secondary mb-0">Пока не связан ни с одной заявкой.</p>
                @endforelse
            </div>

            <div class="card-soft p-4">
                <h2 class="h4 mb-3">История контактов</h2>
                <form action="{{ route('crm.people.interactions.store', $person) }}" method="POST" class="row g-2 mb-4">
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
                    <div class="col-md-6"><input type="text" name="comment" class="form-control" placeholder="Что сообщил человек" required></div>
                    <div class="col-12"><button class="btn btn-outline-dark">Записать контакт</button></div>
                </form>

                @forelse($interactions as $interaction)
                    <div class="border-start border-3 ps-3 py-2 mb-3">
                        <div class="d-flex justify-content-between gap-2"><strong>{{ $interaction->type }}</strong><span class="small text-secondary">{{ $interaction->happened_at->format('d.m.Y H:i') }}</span></div>
                        <div>{{ $interaction->comment }}</div>
                        <div class="small text-secondary">{{ $interaction->employee->name ?? 'Система' }} {{ $interaction->result ? '• '.$interaction->result : '' }}</div>
                    </div>
                @empty
                    <p class="text-secondary mb-0">Контактов пока нет.</p>
                @endforelse
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card-soft p-4 mb-4">
                <h2 class="h4 mb-3">Новая задача</h2>
                <form action="{{ route('crm.people.tasks.store', $person) }}" method="POST" class="row g-2">
                    @csrf
                    <div class="col-12"><input type="text" name="title" class="form-control" placeholder="Например: перезвонить и уточнить субботу" required></div>
                    <div class="col-12"><textarea name="description" class="form-control" rows="2" placeholder="Подробности"></textarea></div>
                    <div class="col-12"><select name="assigned_to_id" class="form-select"><option value="">Назначить себе</option>@foreach($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->name }}</option>@endforeach</select></div>
                    <div class="col-md-6"><select name="priority" class="form-select"><option value="normal">Обычная</option><option value="high">Высокая</option><option value="urgent">Срочная</option><option value="low">Низкая</option></select></div>
                    <div class="col-md-6"><input type="datetime-local" name="due_at" class="form-control"></div>
                    <div class="col-12"><button class="btn btn-dark w-100">Создать задачу</button></div>
                </form>
            </div>

            <div class="card-soft p-4 mb-4">
                <h2 class="h4 mb-3">Задачи</h2>
                @forelse($tasks as $task)
                    <div class="border rounded-4 p-3 mb-2 {{ $task->status === 'open' && $task->due_at && $task->due_at->isPast() ? 'crm-overdue' : '' }}">
                        <strong>{{ $task->title }}</strong>
                        <div class="small text-secondary">{{ $task->assignedTo->name ?? 'Не назначено' }} • {{ $task->due_at?->format('d.m.Y H:i') ?: 'без срока' }}</div>
                        @if($task->status === 'open')
                            <form action="{{ route('crm.tasks.complete', $task) }}" method="POST" class="mt-2">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-success">Выполнено</button></form>
                        @else
                            <span class="badge text-bg-success mt-2">Выполнена</span>
                        @endif
                    </div>
                @empty
                    <p class="text-secondary mb-0">Задач нет.</p>
                @endforelse
            </div>

            <div class="card-soft p-4">
                <h2 class="h4 mb-3">Заказы платформы</h2>
                @php($orders = $person->isCaregiver() ? $person->caregiverOrders : $person->clientOrders)
                @forelse($orders as $order)
                    <div class="border rounded-4 p-3 mb-2"><strong>#{{ $order->id }} {{ $order->title }}</strong><div class="small text-secondary">{{ $order->status_label }} • {{ $order->starts_at?->format('d.m.Y H:i') }}</div></div>
                @empty
                    <p class="text-secondary mb-0">Заказов пока нет.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
