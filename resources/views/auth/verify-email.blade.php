@extends('layouts.app')

@php($title = 'Подтверждение email')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card-soft p-4 p-lg-5 text-center">
                <div class="display-5 mb-3">✉️</div>
                <div class="text-uppercase small text-secondary mb-2">Завершение регистрации</div>
                <h1 class="section-title mb-3">Подтвердите email</h1>
                <p class="text-secondary mb-2">Мы отправили письмо со ссылкой подтверждения на:</p>
                <div class="fw-bold fs-5 mb-4">{{ $user->email }}</div>

                <div class="alert alert-light border rounded-4 text-start mb-4">
                    <strong>Письма нет?</strong>
                    <div class="small text-secondary mt-1">Проверьте папки «Спам» и «Рассылки». Повторную ссылку можно запросить не чаще трёх раз в минуту.</div>
                </div>

                <div class="d-flex flex-column flex-sm-row justify-content-center gap-2">
                    <form action="{{ route('verification.send') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-dark rounded-pill px-4">Отправить повторно</button>
                    </form>

                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-outline-dark rounded-pill px-4">Выйти</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
