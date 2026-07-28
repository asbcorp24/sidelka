@extends('layouts.app')

@php($title = 'CRM — '.$person->name)
@php($selectedCanIds = $person->caregiverProfile?->availableServices()->pluck('id')->all() ?? [])
@php($selectedCannotIds = $person->caregiverProfile?->restrictedServices()->pluck('id')->all() ?? [])

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
                            <h2 class="h4 mb-1">Анкета сиделки</h2>
                            <div class="text-secondary small">CRM может дополнять и корректировать карточку вручную.</div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge {{ $person->is_verified ? 'text-bg-success' : 'text-bg-warning' }}">{{ $person->is_verified ? 'Пользователь проверен' : 'Пользователь не проверен' }}</span>
                            <span class="badge {{ $person->caregiverProfile?->documents_verified ? 'text-bg-success' : 'text-bg-warning' }}">{{ $person->caregiverProfile?->documents_verified ? 'Документы проверены' : 'Документы не проверены' }}</span>
                        </div>
                    </div>

                    <form action="{{ route('crm.caregivers.profile.update', $person) }}" method="POST" class="row g-3">
                        @csrf
                        <div class="col-md-6">
                            <label class="form-label">Имя</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $person->name) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Телефон</label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone', $person->phone) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email', str_ends_with($person->email, '@sidelka.local') ? '' : $person->email) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Город</label>
                            <input type="text" name="city" class="form-control" value="{{ old('city', $person->city) }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Кратко о себе</label>
                            <textarea name="about" class="form-control" rows="2">{{ old('about', $person->about) }}</textarea>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Опыт, лет</label>
                            <input type="number" name="experience_years" class="form-control" value="{{ old('experience_years', $person->caregiverProfile?->experience_years ?? 0) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Ставка от, ₽/час</label>
                            <input type="number" name="hourly_rate_from" class="form-control" value="{{ old('hourly_rate_from', $person->caregiverProfile?->hourly_rate_from ?? 0) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Смена от, ₽</label>
                            <input type="number" name="shift_rate_from" class="form-control" value="{{ old('shift_rate_from', $person->caregiverProfile?->shift_rate_from ?? '') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Формат занятости</label>
                            <input type="text" name="employment_format" class="form-control" value="{{ old('employment_format', $person->caregiverProfile?->employment_format ?? 'hourly') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Образование</label>
                            <input type="text" name="education" class="form-control" value="{{ old('education', $person->caregiverProfile?->education ?? '') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Профильное описание</label>
                            <input type="text" name="bio" class="form-control" value="{{ old('bio', $person->caregiverProfile?->bio ?? '') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Медицинские навыки</label>
                            <textarea name="medical_skills" class="form-control" rows="3">{{ old('medical_skills', $person->caregiverProfile?->medical_skills ?? '') }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Бытовые навыки</label>
                            <textarea name="household_skills" class="form-control" rows="3">{{ old('household_skills', $person->caregiverProfile?->household_skills ?? '') }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label d-block">Может выполнять</label>
                            <div class="border rounded-4 p-3" style="max-height: 260px; overflow:auto;">
                                @foreach($services as $service)
                                    <label class="form-check mb-1">
                                        <input class="form-check-input" type="checkbox" name="can_service_ids[]" value="{{ $service->id }}" {{ in_array($service->id, old('can_service_ids', $selectedCanIds), true) ? 'checked' : '' }}>
                                        <span class="form-check-label small">{{ $service->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label d-block">Не выполняет</label>
                            <div class="border rounded-4 p-3" style="max-height: 260px; overflow:auto;">
                                @foreach($services as $service)
                                    <label class="form-check mb-1">
                                        <input class="form-check-input" type="checkbox" name="cannot_service_ids[]" value="{{ $service->id }}" {{ in_array($service->id, old('cannot_service_ids', $selectedCannotIds), true) ? 'checked' : '' }}>
                                        <span class="form-check-label small">{{ $service->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-md-3"><label class="form-check mt-4"><input class="form-check-input" type="checkbox" name="ready_for_night" value="1" {{ old('ready_for_night', $person->caregiverProfile?->ready_for_night) ? 'checked' : '' }}><span class="form-check-label">Готова к ночным сменам</span></label></div>
                        <div class="col-md-3"><label class="form-check mt-4"><input class="form-check-input" type="checkbox" name="ready_for_live_in" value="1" {{ old('ready_for_live_in', $person->caregiverProfile?->ready_for_live_in) ? 'checked' : '' }}><span class="form-check-label">Готова к проживанию</span></label></div>
                        <div class="col-md-3"><label class="form-check mt-4"><input class="form-check-input" type="checkbox" name="documents_verified" value="1" {{ old('documents_verified', $person->caregiverProfile?->documents_verified) ? 'checked' : '' }}><span class="form-check-label">Документы проверены</span></label></div>
                        <div class="col-md-3"><label class="form-check mt-4"><input class="form-check-input" type="checkbox" name="is_verified" value="1" {{ old('is_verified', $person->is_verified) ? 'checked' : '' }}><span class="form-check-label">Пользователь проверен</span></label></div>
                        <div class="col-12">
                            <button class="btn btn-dark rounded-pill px-4">Сохранить карточку сиделки</button>
                        </div>
                    </form>
                </div>

                <div class="card-soft p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                        <div>
                            <h2 class="h4 mb-1">Доступность сиделки</h2>
                            <div class="text-secondary small">Опыт: {{ $person->caregiverProfile?->experience_years ?? 0 }} лет • ставка от {{ number_format($person->caregiverProfile?->hourly_rate_from ?? 0, 0, ',', ' ') }} ₽/час</div>
                        </div>
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
                        <p class="text-secondary">Свободные часы еще не записаны.</p>
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
                    <div class="border rounded-4 p-3 mb-2">
                        <strong>#{{ $order->id }} {{ $order->title }}</strong>
                        <div class="small text-secondary">{{ $order->status_label }} • {{ $order->starts_at?->format('d.m.Y H:i') }}</div>
                    </div>
                @empty
                    <p class="text-secondary mb-0">Заказов пока нет.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
