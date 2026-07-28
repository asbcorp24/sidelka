@extends('admin.layout')

@php($pageTitle = 'Пользователи и права')

@section('admin-page')
<div class="card-soft p-4">
    <h2 class="h4 mb-3">Пользователи платформы</h2>
    @foreach($users as $platformUser)
        <form action="{{ route('admin.users.update', $platformUser) }}" method="POST" class="border rounded-4 p-3 mb-3">
            @csrf
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <strong>{{ $platformUser->name }}</strong>
                    <div class="text-secondary">
                        {{ $platformUser->email }}
                        • {{ $platformUser->phone ?: 'телефон не указан' }}
                        • {{ $platformUser->city }}
                    </div>
                </div>
                <div class="text-secondary">
                    {{ $platformUser->role }}
                    @if($platformUser->role === 'crm')
                        • {{ $platformUser->staffRoleLabel() }}
                    @endif
                </div>
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
                <div class="col-md-2">
                    <label class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" name="is_verified" value="1" {{ $platformUser->is_verified ? 'checked' : '' }}>
                        <span class="form-check-label">Проверен</span>
                    </label>
                </div>
                <div class="col-md-2">
                    <label class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" name="staff_active" value="1" {{ $platformUser->staff_active ? 'checked' : '' }}>
                        <span class="form-check-label">Активен</span>
                    </label>
                </div>
                @if($platformUser->role === 'crm')
                    <div class="col-12">
                        <div class="small fw-semibold mb-2">Дополнительные разрешения</div>
                        <div class="row">
                            @foreach($permissionLabels as $value => $label)
                                <div class="col-md-6">
                                    <label class="form-check">
                                        <input class="form-check-input" type="checkbox" name="staff_permissions[]" value="{{ $value }}" {{ in_array($value, $platformUser->staff_permissions ?? [], true) ? 'checked' : '' }}>
                                        <span class="form-check-label small">{{ $label }}</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
                <div class="col-12"><button class="btn btn-outline-dark">Сохранить права</button></div>
            </div>
        </form>
    @endforeach
</div>
@endsection
