@extends('layouts.app')

@php($title = 'Админка')

@section('content')
<div class="container py-4 py-lg-5">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
        <div>
            <div class="text-uppercase small text-secondary">Управление платформой</div>
            <h1 class="section-title mb-0">Админ-панель</h1>
        </div>
        <a href="{{ route('crm.dashboard') }}" class="btn btn-dark rounded-pill px-4">Открыть CRM</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md"><div class="metric"><div class="value">{{ $stats['caregivers'] }}</div><div>Сиделок</div></div></div>
        <div class="col-md"><div class="metric"><div class="value">{{ $stats['clients'] }}</div><div>Клиентов</div></div></div>
        <div class="col-md"><div class="metric"><div class="value">{{ $stats['crm_employees'] }}</div><div>CRM-сотрудников</div></div></div>
        <div class="col-md"><div class="metric"><div class="value">{{ $stats['verified_caregivers'] }}</div><div>Проверенных сиделок</div></div></div>
        <div class="col-md"><div class="metric"><div class="value">{{ $stats['news_posts'] }}</div><div>Новостей</div></div></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card-soft p-4 mb-4">
                <h2 class="h4 mb-2">Новый сотрудник CRM</h2>
                <p class="small text-secondary">Доступ определяется должностью. Дополнительные разрешения можно выдать отдельно.</p>
                <form action="{{ route('admin.crm-employees.store') }}" method="POST" class="row g-3">
                    @csrf
                    <div class="col-12"><input type="text" name="name" class="form-control" placeholder="ФИО сотрудника" required></div>
                    <div class="col-12"><input type="email" name="email" class="form-control" placeholder="Рабочий email" required></div>
                    <div class="col-12"><input type="text" name="phone" class="form-control" placeholder="Телефон"></div>
                    <div class="col-12">
                        <label class="form-label">Должность</label>
                        <select name="staff_role" class="form-select" required>
                            @foreach($staffRoleLabels as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="small fw-semibold mb-2">Дополнительные права</div>
                        @foreach($permissionLabels as $value => $label)
                            <label class="form-check mb-1">
                                <input class="form-check-input" type="checkbox" name="staff_permissions[]" value="{{ $value }}">
                                <span class="form-check-label small">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div class="col-12"><input type="password" name="password" class="form-control" placeholder="Пароль, минимум 8 символов" required></div>
                    <div class="col-12"><input type="password" name="password_confirmation" class="form-control" placeholder="Повторите пароль" required></div>
                    <div class="col-12"><button class="btn btn-dark rounded-pill px-4">Создать сотрудника</button></div>
                </form>
            </div>

            <div class="card-soft p-4 mb-4">
                <h2 class="h4 mb-3">Добавить услугу</h2>
                <form action="{{ route('admin.services.store') }}" method="POST" class="row g-3">
                    @csrf
                    <div class="col-12"><input type="text" name="name" class="form-control" placeholder="Название услуги"></div>
                    <div class="col-12"><input type="text" name="category" class="form-control" placeholder="Категория"></div>
                    <div class="col-12"><textarea name="description" class="form-control" rows="3" placeholder="Описание"></textarea></div>
                    <div class="col-12"><input type="number" name="hourly_surcharge" class="form-control" placeholder="Доплата, ₽/час"></div>
                    <div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" name="requires_medical_training" value="1"><span class="form-check-label">Требует медобразование</span></label></div>
                    <div class="col-12"><button class="btn btn-dark rounded-pill px-4">Сохранить услугу</button></div>
                </form>
            </div>

            <div class="card-soft p-4">
                <h2 class="h4 mb-3">Добавить новость</h2>
                <form action="{{ route('admin.news.store') }}" method="POST" class="row g-3">
                    @csrf
                    <div class="col-12"><input type="text" name="title" class="form-control" placeholder="Заголовок"></div>
                    <div class="col-12"><textarea name="excerpt" class="form-control" rows="2" placeholder="Краткое описание"></textarea></div>
                    <div class="col-12"><textarea name="body" class="form-control" rows="4" placeholder="Текст новости"></textarea></div>
                    <div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" name="is_published" value="1" checked><span class="form-check-label">Опубликовать сразу</span></label></div>
                    <div class="col-12"><button class="btn btn-dark rounded-pill px-4">Сохранить новость</button></div>
                </form>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card-soft p-4 mb-4">
                <h2 class="h4 mb-3">Пользователи и права</h2>
                @foreach($users as $platformUser)
                    <form action="{{ route('admin.users.update', $platformUser) }}" method="POST" class="border rounded-4 p-3 mb-3">
                        @csrf
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                            <div><strong>{{ $platformUser->name }}</strong><div class="text-secondary">{{ $platformUser->email }} • {{ $platformUser->phone ?: 'телефон не указан' }} • {{ $platformUser->city }}</div></div>
                            <div class="text-secondary">{{ $platformUser->role }} @if($platformUser->role === 'crm')• {{ $platformUser->staffRoleLabel() }}@endif</div>
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col-md-4">
                                <label class="form-label small">Основная роль</label>
                                <select name="role" class="form-select">
                                    <option value="client" {{ $platformUser->role === 'client' ? 'selected' : '' }}>Клиент</option>
                                    <option value="caregiver" {{ $platformUser->role === 'caregiver' ? 'selected' : '' }}>Сиделка</option>
                                    <option value="crm" {{ $platformUser->role === 'crm' ? 'selected' : '' }}>Сотрудник CRM</option>
                                    <option value="admin" {{ $platformUser->role === 'admin' ? 'selected' : '' }}>Администратор</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Должность CRM</label>
                                <select name="staff_role" class="form-select">
                                    <option value="">Не назначена</option>
                                    @foreach($staffRoleLabels as $value => $label)
                                        <option value="{{ $value }}" {{ $platformUser->staff_role === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2"><label class="form-check mt-4"><input class="form-check-input" type="checkbox" name="is_verified" value="1" {{ $platformUser->is_verified ? 'checked' : '' }}><span class="form-check-label">Проверен</span></label></div>
                            <div class="col-md-2"><label class="form-check mt-4"><input class="form-check-input" type="checkbox" name="staff_active" value="1" {{ $platformUser->staff_active ? 'checked' : '' }}><span class="form-check-label">Активен</span></label></div>
                            @if($platformUser->role === 'crm')
                                <div class="col-12">
                                    <div class="small fw-semibold mb-2">Дополнительные разрешения</div>
                                    <div class="row">
                                        @foreach($permissionLabels as $value => $label)
                                            <div class="col-md-6"><label class="form-check"><input class="form-check-input" type="checkbox" name="staff_permissions[]" value="{{ $value }}" {{ in_array($value, $platformUser->staff_permissions ?? [], true) ? 'checked' : '' }}><span class="form-check-label small">{{ $label }}</span></label></div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            <div class="col-12"><button class="btn btn-outline-dark">Сохранить права</button></div>
                        </div>
                    </form>
                @endforeach
            </div>

            <div class="card-soft p-4 mb-4"><h2 class="h4 mb-3">Каталог услуг</h2>@foreach($services as $service)<div class="d-flex justify-content-between border-bottom py-2"><span>{{ $service->name }}</span><span class="text-secondary">{{ $service->requires_medical_training ? 'медуслуга' : 'бытовая' }}</span></div>@endforeach</div>
            <div class="card-soft p-4"><h2 class="h4 mb-3">Новости</h2>@foreach($posts as $post)<div class="border rounded-4 p-3 mb-3"><strong>{{ $post->title }}</strong><div class="text-secondary small">{{ $post->is_published ? 'Опубликовано' : 'Черновик' }}</div><p class="mb-0 mt-2">{{ $post->excerpt }}</p></div>@endforeach</div>
        </div>
    </div>
</div>
@endsection
