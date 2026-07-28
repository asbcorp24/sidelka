@extends('admin.layout')

@php($pageTitle = 'CRM-сотрудники')

@section('admin-page')
<div class="row g-4">
    <div class="col-lg-5">
        <div class="card-soft p-4">
            <h2 class="h4 mb-2">Новый сотрудник CRM</h2>
            <p class="small text-secondary">Доступ определяется должностью. Дополнительные разрешения можно включить отдельно.</p>
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
    </div>
    <div class="col-lg-7">
        <div class="card-soft p-4">
            <h2 class="h4 mb-3">Текущие сотрудники</h2>
            @foreach($crmEmployees as $employee)
                <div class="border rounded-4 p-3 mb-3">
                    <div class="d-flex justify-content-between flex-wrap gap-2">
                        <div>
                            <strong>{{ $employee->name }}</strong>
                            <div class="text-secondary small">{{ $employee->email }}{{ $employee->phone ? ' • ' . $employee->phone : '' }}</div>
                        </div>
                        <div class="text-secondary small">{{ $employee->role === 'admin' ? 'Администратор' : $employee->staffRoleLabel() }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
