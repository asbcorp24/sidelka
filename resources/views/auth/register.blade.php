@extends('layouts.app')

@php($title = 'Регистрация')

@section('content')
<div class="container py-4 py-lg-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card-soft p-4 p-lg-5">
                <div class="text-uppercase small text-secondary mb-2">Регистрация</div>
                <h1 class="section-title mb-3">Создать аккаунт</h1>

                <div class="d-grid gap-2 mb-4">
                    <a href="{{ route('social.redirect', 'vk') }}" class="btn btn-outline-dark rounded-pill">Продолжить через ВКонтакте</a>
                    <a href="{{ route('social.redirect', 'yandex') }}" class="btn btn-outline-dark rounded-pill">Продолжить через Яндекс</a>
                </div>

                <div class="text-center text-secondary small mb-3">или заполните форму вручную</div>

                <form action="{{ route('register.store') }}" method="POST" class="row g-3">
                    @csrf
                    <div class="col-md-6">
                        <label class="form-label">Имя</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Телефон</label>
                        <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Город</label>
                        <input type="text" name="city" class="form-control" value="{{ old('city') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Пароль</label>
                        <input type="password" name="password" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Подтверждение пароля</label>
                        <input type="password" name="password_confirmation" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label d-block">Роль</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="role" value="client" checked>
                            <label class="form-check-label">Клиент</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="role" value="caregiver">
                            <label class="form-check-label">Сиделка</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-dark rounded-pill px-4">Зарегистрироваться</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
