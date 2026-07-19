@extends('layouts.app')

@php($title = 'Завершение регистрации')

@section('content')
<div class="container py-4 py-lg-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card-soft p-4 p-lg-5">
                <div class="text-uppercase small text-secondary mb-2">Вход через соцсеть</div>
                <h1 class="section-title mb-3">Завершите создание аккаунта</h1>
                <p class="text-secondary">Мы получили ваши данные из соцсети. Остается выбрать роль и заполнить базовую информацию для сервиса.</p>

                <form action="{{ route('social.complete.store') }}" method="POST" class="row g-3">
                    @csrf
                    <div class="col-md-6">
                        <label class="form-label">Имя</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $pending['name'] ?? '') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Телефон</label>
                        <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email', $pending['email'] ?? '') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Город</label>
                        <input list="cities-list" type="text" name="city" class="form-control" value="{{ old('city') }}">
                        <datalist id="cities-list">
                            @foreach($cities as $city)
                                <option value="{{ $city->name }}"></option>
                            @endforeach
                        </datalist>
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
                        <button class="btn btn-dark rounded-pill px-4">Создать аккаунт</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
