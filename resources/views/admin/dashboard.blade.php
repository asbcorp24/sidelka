@extends('admin.layout')

@php($pageTitle = 'Суперадмин')

@section('admin-page')
<div class="row g-3 mb-4">
    <div class="col-md"><div class="metric"><div class="value">{{ $stats['caregivers'] }}</div><div>Сиделок</div></div></div>
    <div class="col-md"><div class="metric"><div class="value">{{ $stats['clients'] }}</div><div>Клиентов</div></div></div>
    <div class="col-md"><div class="metric"><div class="value">{{ $stats['crm_employees'] }}</div><div>CRM-сотрудников</div></div></div>
    <div class="col-md"><div class="metric"><div class="value">{{ $stats['verified_caregivers'] }}</div><div>Проверенных сиделок</div></div></div>
    <div class="col-md"><div class="metric"><div class="value">{{ $stats['news_posts'] }}</div><div>Новостей</div></div></div>
    <div class="col-md"><div class="metric"><div class="value">{{ $stats['services'] }}</div><div>Услуг</div></div></div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card-soft p-4 h-100">
            <h2 class="h4 mb-3">Быстрый доступ</h2>
            <div class="d-grid gap-2">
                <a href="{{ route('admin.seo') }}" class="btn btn-outline-dark text-start">SEO и метатеги</a>
                <a href="{{ route('admin.bank') }}" class="btn btn-outline-dark text-start">Банк и эквайринг</a>
                <a href="{{ route('admin.legal') }}" class="btn btn-outline-dark text-start">Реквизиты площадки</a>
                <a href="{{ route('admin.staff') }}" class="btn btn-outline-dark text-start">CRM-сотрудники</a>
                <a href="{{ route('admin.services') }}" class="btn btn-outline-dark text-start">Каталог услуг</a>
                <a href="{{ route('admin.news') }}" class="btn btn-outline-dark text-start">Новости</a>
                <a href="{{ route('admin.users') }}" class="btn btn-outline-dark text-start">Пользователи и права</a>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card-soft p-4 h-100">
            <h2 class="h4 mb-3">Что отсюда управляется</h2>
            <div class="text-secondary mb-2">Разделы вынесены по отдельным страницам, чтобы суперадминка не превращалась в одну длинную форму.</div>
            <ul class="mb-0">
                <li>SEO-настройки сайта и страниц</li>
                <li>Параметры банка и интернет-эквайринга</li>
                <li>Юридические реквизиты площадки для договоров</li>
                <li>Создание CRM-сотрудников и выдача ролей</li>
                <li>Услуги, новости и управление аккаунтами</li>
            </ul>
        </div>
    </div>
</div>
@endsection
