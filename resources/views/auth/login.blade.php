@extends('layouts.app')

@php($title = 'Вход')

@section('content')
<div class="container py-4 py-lg-5">
    <div class="row justify-content-center">
        <div class="col-lg-5">
            <div class="card-soft p-4 p-lg-5">
                <div class="text-uppercase small text-secondary mb-2">Авторизация</div>
                <h1 class="section-title mb-3">Вход в кабинет</h1>

                <div class="d-grid gap-2 mb-4">
                    <a href="{{ route('social.redirect', 'vk') }}" class="btn btn-outline-dark rounded-pill">Войти через ВКонтакте</a>
                    <a href="{{ route('social.redirect', 'yandex') }}" class="btn btn-outline-dark rounded-pill">Войти через Яндекс</a>
                </div>

                <div class="text-center text-secondary small mb-3">или войдите по email и паролю</div>

                <form action="{{ route('login.attempt') }}" method="POST" class="row g-3">
                    @csrf
                    <div class="col-12">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Пароль</label>
                        <input type="password" name="password" class="form-control">
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="remember" value="1">
                            <label class="form-check-label">Запомнить меня</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-dark rounded-pill px-4">Войти</button>
                    </div>
                </form>

                <div class="mt-3 text-secondary">Нет аккаунта? <a href="{{ route('register') }}">Зарегистрироваться</a></div>
            </div>
        </div>
    </div>
</div>
@endsection
