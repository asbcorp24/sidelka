@extends('admin.layout')

@php($pageTitle = 'Банк и эквайринг')

@section('admin-page')
<div class="card-soft p-4">
    <h2 class="h4 mb-3">Настройки банка</h2>
    <form action="{{ route('admin.bank.update') }}" method="POST" class="row g-3">
        @csrf
        <div class="col-md-4">
            <label class="form-label">Провайдер</label>
            <select name="bank_provider" class="form-select">
                <option value="sber" @selected(old('bank_provider', $bankSettings['provider']) === 'sber')>Сбер</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Режим</label>
            <select name="bank_mode" class="form-select">
                <option value="test" @selected(old('bank_mode', $bankSettings['mode']) === 'test')>Тест</option>
                <option value="production" @selected(old('bank_mode', $bankSettings['mode']) === 'production')>Боевой</option>
            </select>
        </div>
        <div class="col-md-4 d-flex align-items-end">
            <label class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="bank_enabled" value="1" @checked(old('bank_enabled', $bankSettings['enabled']))>
                <span class="form-check-label">Эквайринг включен</span>
            </label>
        </div>
        <div class="col-md-6">
            <label class="form-label">Merchant / бренд</label>
            <input type="text" name="bank_merchant_name" class="form-control" value="{{ old('bank_merchant_name', $bankSettings['merchant_name']) }}">
        </div>
        <div class="col-md-6">
            <label class="form-label">API URL</label>
            <input type="text" name="bank_base_url" class="form-control" value="{{ old('bank_base_url', $bankSettings['base_url']) }}">
        </div>
        <div class="col-md-6">
            <label class="form-label">Логин банка</label>
            <input type="text" name="bank_username" class="form-control" value="{{ old('bank_username', $bankSettings['username']) }}">
        </div>
        <div class="col-md-6">
            <label class="form-label">Пароль / секрет</label>
            <input type="text" name="bank_password" class="form-control" value="{{ old('bank_password', $bankSettings['password']) }}">
        </div>
        <div class="col-md-8">
            <label class="form-label">Префикс описания платежа</label>
            <input type="text" name="bank_description_prefix" class="form-control" value="{{ old('bank_description_prefix', $bankSettings['description_prefix']) }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">Timeout, сек</label>
            <input type="number" name="bank_timeout" class="form-control" value="{{ old('bank_timeout', $bankSettings['timeout']) }}">
        </div>
        <div class="col-12">
            <label class="form-label">Email для callback-уведомлений</label>
            <input type="email" name="bank_callback_email" class="form-control" value="{{ old('bank_callback_email', $bankSettings['callback_email']) }}">
        </div>
        <div class="col-12">
            <button class="btn btn-dark rounded-pill px-4">Сохранить банковские настройки</button>
        </div>
    </form>
</div>
@endsection
