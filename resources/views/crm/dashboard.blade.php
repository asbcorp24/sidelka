@extends('layouts.app')

@php($title = 'CRM')

@section('content')
<div class="container-fluid px-3 px-xl-5 py-4">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
        <div>
            <div class="text-uppercase small text-secondary">Телефонные обращения и подбор</div>
            <h1 class="section-title mb-0">CRM Сиделка24</h1>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('crm.people.index') }}" class="btn btn-outline-dark rounded-pill px-4">Клиенты и сиделки</a>
            @if(auth()->user()->isAdmin())
                <a href="{{ route('admin.dashboard') }}" class="btn btn-dark rounded-pill px-4">Админка</a>
            @endif
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md"><div class="metric"><div class="value">{{ $stats['new'] }}</div><div>Новых звонков</div></div></div>
        <div class="col-md"><div class="metric"><div class="value">{{ $stats['searching'] }}</div><div>В подборе</div></div></div>
        <div class="col-md"><div class="metric"><div class="value">{{ $stats['waiting'] }}</div><div>На согласовании</div></div></div>
        <div class="col-md"><div class="metric"><div class="value">{{ $stats['active'] }}</div><div>Оформлено / в работе</div></div></div>
        <div class="col-md"><div class="metric {{ $stats['overdue_tasks'] ? 'crm-overdue' : '' }}"><div class="value">{{ $stats['overdue_tasks'] }}</div><div>Просроченных задач</div></div></div>
    </div>

    <div class="row g-4 align-items-start">
        <div class="col-xl-4">
            <div class="card-soft p-4 position-sticky" style="top: 1rem;">
                <h2 class="h4 mb-2">Принять телефонную заявку</h2>
                <p class="small text-secondary">Заполните главное во время разговора. Остальное можно уточнить в карточке заявки.</p>
                <form action="{{ route('crm.requests.store') }}" method="POST" class="row g-3">
                    @csrf
                    <div class="col-md-6"><input type="text" name="caller_name" value="{{ old('caller_name') }}" class="form-control" placeholder="Кто звонит" required></div>
                    <div class="col-md-6"><input type="text" name="caller_phone" value="{{ old('caller_phone') }}" class="form-control" placeholder="Телефон" required></div>
                    <div class="col-12"><input type="email" name="caller_email" value="{{ old('caller_email') }}" class="form-control" placeholder="Email, если есть"></div>
                    <div class="col-md-8"><input type="text" name="patient_name" value="{{ old('patient_name') }}" class="form-control" placeholder="Имя подопечного"></div>
                    <div class="col-md-4"><input type="number" min="0" max="120" name="patient_age" value="{{ old('patient_age') }}" class="form-control" placeholder="Возраст"></div>
                    <div class="col-md-6"><input type="text" name="city" value="{{ old('city') }}" class="form-control" placeholder="Город"></div>
                    <div class="col-md-6"><input type="text" name="address" value="{{ old('address') }}" class="form-control" placeholder="Адрес"></div>
                    <div class="col-12"><textarea name="service_text" class="form-control" rows="4" placeholder="Что нужно делать сиделке, состояние пациента, ограничения" required>{{ old('service_text') }}</textarea></div>
                    <div class="col-12"><textarea name="schedule_text" class="form-control" rows="2" placeholder="Когда нужна помощь: дни, часы, постоянно или разово">{{ old('schedule_text') }}</textarea></div>
                    <div class="col-md-6"><label class="form-label small">Начало</label><input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" class="form-control"></div>
                    <div class="col-md-6"><label class="form-label small">Окончание</label><input type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" class="form-control"></div>
                    <div class="col-md-6"><input type="number" min="0" name="budget_per_hour" value="{{ old('budget_per_hour') }}" class="form-control" placeholder="Бюджет ₽/час"></div>
                    <div class="col-md-6">
                        <select name="priority" class="form-select">
                            @foreach($priorityLabels as $value => $label)
                                <option value="{{ $value }}" {{ old('priority', 'normal') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <select name="responsible_user_id" class="form-select">
                            <option value="">Ответственный: я</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12"><label class="form-label small">Следующий контакт</label><input type="datetime-local" name="next_contact_at" class="form-control"></div>
                    <div class="col-12"><textarea name="notes" class="form-control" rows="2" placeholder="Внутренние примечания"></textarea></div>
                    <div class="col-12"><button class="btn btn-dark w-100 py-2">Создать заявку</button></div>
                </form>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card-soft p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h2 class="h4 mb-0">Заявки</h2>
                    <span class="text-secondary small">Найдено: {{ $requests->total() }}</span>
                </div>
                <form method="GET" class="row g-2 mb-4">
                    <div class="col-lg-4"><input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Имя, телефон, город, услуга"></div>
                    <div class="col-lg-3">
                        <select name="status" class="form-select">
                            <option value="">Все статусы</option>
                            @foreach($statusLabels as $value => $label)
                                <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <select name="priority" class="form-select">
                            <option value="">Любой приоритет</option>
                            @foreach($priorityLabels as $value => $label)
                                <option value="{{ $value }}" {{ request('priority') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <select name="responsible_user_id" class="form-select">
                            <option value="">Все сотрудники</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}" {{ (string) request('responsible_user_id') === (string) $employee->id ? 'selected' : '' }}>{{ $employee->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-1"><button class="btn btn-outline-dark w-100">Найти</button></div>
                </form>

                <div class="table-responsive">
                    <table class="table crm-table align-middle">
                        <thead><tr><th>Заявка</th><th>Контакт</th><th>Потребность</th><th>Ответственный</th><th>Следующий контакт</th></tr></thead>
                        <tbody>
                        @forelse($requests as $item)
                            <tr class="{{ $item->next_contact_at && $item->next_contact_at->isPast() && !in_array($item->status, ['completed','cancelled']) ? 'table-danger' : '' }}">
                                <td>
                                    <a class="fw-bold text-decoration-none" href="{{ route('crm.requests.show', $item) }}">{{ strtoupper(substr($item->public_id, 0, 8)) }}</a>
                                    <div class="mt-1"><span class="badge {{ $item->status_badge_class }}">{{ $item->status_label }}</span></div>
                                    <div class="small text-secondary">{{ $item->priority_label }} • {{ $item->created_at->format('d.m.Y H:i') }}</div>
                                </td>
                                <td><strong>{{ $item->caller_name }}</strong><div><a href="tel:{{ $item->caller_phone }}">{{ $item->caller_phone }}</a></div><div class="small text-secondary">{{ $item->city }}</div></td>
                                <td style="min-width:260px"><div class="text-truncate" style="max-width:360px">{{ $item->service_text }}</div><div class="small text-secondary">{{ $item->schedule_text }}</div></td>
                                <td>{{ $item->responsible->name ?? 'Не назначен' }}<div class="small text-secondary">{{ $item->caregiverUser ? 'Сиделка: '.$item->caregiverUser->name : 'Сиделка не выбрана' }}</div></td>
                                <td>{{ $item->next_contact_at?->format('d.m.Y H:i') ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-secondary py-5">Заявок по фильтру нет.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $requests->links() }}
            </div>

            <div class="card-soft p-4">
                <h2 class="h4 mb-3">Мои ближайшие задачи</h2>
                @forelse($tasks as $task)
                    <div class="border rounded-4 p-3 mb-2 {{ $task->due_at && $task->due_at->isPast() ? 'crm-overdue' : '' }}">
                        <div class="d-flex justify-content-between gap-3 flex-wrap">
                            <div>
                                <strong>{{ $task->title }}</strong>
                                <div class="small text-secondary">
                                    {{ $task->crmRequest ? 'Заявка '.strtoupper(substr($task->crmRequest->public_id, 0, 8)) : ($task->personUser?->name ?? 'Общая задача') }}
                                    • {{ $task->due_at?->format('d.m.Y H:i') ?: 'без срока' }}
                                </div>
                            </div>
                            <form action="{{ route('crm.tasks.complete', $task) }}" method="POST">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-success">Выполнено</button></form>
                        </div>
                    </div>
                @empty
                    <p class="text-secondary mb-0">Открытых задач нет.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
