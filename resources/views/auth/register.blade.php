@extends('layouts.app')

@php($title = 'Регистрация')

@push('styles')
<style>
    .captcha-box {
        border: 1px solid rgba(31, 111, 120, 0.18);
        border-radius: 18px;
        background: linear-gradient(135deg, rgba(215, 235, 229, 0.55), rgba(244, 239, 231, 0.75));
    }

    .captcha-question {
        min-width: 150px;
        font-size: 1.35rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        user-select: none;
    }

    .registration-honeypot {
        position: absolute !important;
        left: -10000px !important;
        width: 1px !important;
        height: 1px !important;
        overflow: hidden !important;
    }
</style>
@endpush

@section('content')
<div class="container py-4 py-lg-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card-soft p-4 p-lg-5">
                <div class="text-uppercase small text-secondary mb-2">Регистрация</div>
                <h1 class="section-title mb-3">Создать аккаунт</h1>
                <p class="text-secondary mb-4">После регистрации мы отправим на email ссылку подтверждения.</p>

                <div class="d-grid gap-2 mb-4">
                    <a href="{{ route('social.redirect', 'vk') }}" class="btn btn-outline-dark rounded-pill">Продолжить через ВКонтакте</a>
                    <a href="{{ route('social.redirect', 'yandex') }}" class="btn btn-outline-dark rounded-pill">Продолжить через Яндекс</a>
                </div>

                <div class="text-center text-secondary small mb-3">или зарегистрируйтесь по email</div>

                <form action="{{ route('register.store') }}" method="POST" class="row g-3" novalidate>
                    @csrf

                    <div class="registration-honeypot" aria-hidden="true">
                        <label for="website">Не заполняйте это поле</label>
                        <input type="text" name="website" id="website" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="col-md-6">
                        <label for="name" class="form-label">Имя</label>
                        <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required autocomplete="name">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="phone" class="form-label">Телефон</label>
                        <input type="tel" name="phone" id="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}" autocomplete="tel">
                        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required autocomplete="email">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="city" class="form-label">Город</label>
                        <input type="text" name="city" id="city" class="form-control @error('city') is-invalid @enderror" value="{{ old('city') }}" required autocomplete="address-level2">
                        @error('city')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="password" class="form-label">Пароль</label>
                        <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" required minlength="8" autocomplete="new-password">
                        <div class="form-text">Не менее 8 символов.</div>
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="password_confirmation" class="form-label">Подтверждение пароля</label>
                        <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" required minlength="8" autocomplete="new-password">
                    </div>
                    <div class="col-12">
                        <label class="form-label d-block">Роль</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="role" id="role-client" value="client" {{ old('role', 'client') === 'client' ? 'checked' : '' }}>
                            <label class="form-check-label" for="role-client">Клиент</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="role" id="role-caregiver" value="caregiver" {{ old('role') === 'caregiver' ? 'checked' : '' }}>
                            <label class="form-check-label" for="role-caregiver">Сиделка</label>
                        </div>
                        @error('role')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <div class="captcha-box p-3">
                            <label for="captcha_answer" class="form-label fw-bold">Проверка: решите пример</label>
                            <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-3">
                                <div id="captcha-question" class="captcha-question">{{ $captcha['question'] }}</div>
                                <input type="hidden" name="captcha_token" id="captcha-token" value="{{ $captcha['token'] }}">
                                <input
                                    type="number"
                                    name="captcha_answer"
                                    id="captcha_answer"
                                    class="form-control @error('captcha_answer') is-invalid @enderror"
                                    inputmode="numeric"
                                    autocomplete="off"
                                    placeholder="Ответ"
                                    required
                                >
                                <button type="button" id="captcha-refresh" class="btn btn-outline-dark rounded-pill text-nowrap">Другой пример</button>
                            </div>
                            @error('captcha_answer')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                            @error('captcha_token')<div class="text-danger small mt-2">Обновите проверочный пример.</div>@enderror
                        </div>
                    </div>

                    <div class="col-12 d-flex flex-column flex-sm-row align-items-sm-center gap-3">
                        <button type="submit" class="btn btn-dark rounded-pill px-4">Зарегистрироваться</button>
                        <span class="small text-secondary">Уже есть аккаунт? <a href="{{ route('login') }}">Войти</a></span>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (() => {
        const refreshButton = document.getElementById('captcha-refresh');
        const question = document.getElementById('captcha-question');
        const token = document.getElementById('captcha-token');
        const answer = document.getElementById('captcha_answer');

        if (!refreshButton || !question || !token || !answer) {
            return;
        }

        refreshButton.addEventListener('click', async () => {
            refreshButton.disabled = true;

            try {
                const response = await fetch(@json(route('register.captcha')), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    cache: 'no-store',
                });

                if (!response.ok) {
                    throw new Error('Captcha refresh failed');
                }

                const captcha = await response.json();
                question.textContent = captcha.question;
                token.value = captcha.token;
                answer.value = '';
                answer.focus();
            } catch (error) {
                question.textContent = 'Обновите страницу';
            } finally {
                refreshButton.disabled = false;
            }
        });
    })();
</script>
@endpush
