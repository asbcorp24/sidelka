@extends('layouts.app')

@php($title = 'CRM — Люди')

@section('content')
<div class="container-fluid px-3 px-xl-5 py-4">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
        <div>
            <div class="text-uppercase small text-secondary">Единый справочник</div>
            <h1 class="section-title mb-0">Клиенты и сиделки</h1>
        </div>
        <a href="{{ route('crm.dashboard') }}" class="btn btn-outline-dark rounded-pill px-4">К заявкам</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="metric"><div class="value">{{ $stats['clients'] }}</div><div>Клиентов</div></div></div>
        <div class="col-md-4"><div class="metric"><div class="value">{{ $stats['caregivers'] }}</div><div>Сиделок</div></div></div>
        <div class="col-md-4"><div class="metric"><div class="value">{{ $stats['available_today'] }}</div><div>Указали доступность сегодня</div></div></div>
    </div>

    <div class="row g-4 align-items-start">
        <div class="col-xl-4">
            <div class="card-soft p-4 position-sticky" style="top:1rem">
                <h2 class="h4 mb-2">Создать карточку по телефону</h2>
                <p class="small text-secondary">Email необязателен. Без email будет создана внутренняя карточка без доступа к личному кабинету.</p>
                <form action="{{ route('crm.people.store') }}" method="POST" class="row g-3">
                    @csrf
                    <div class="col-12">
                        <select name="role" id="personRole" class="form-select" required>
                            <option value="client">Клиент</option>
                            <option value="caregiver">Сиделка</option>
                        </select>
                    </div>
                    <div class="col-12"><input type="text" name="name" class="form-control" placeholder="ФИО" required></div>
                    <div class="col-md-6"><input type="text" name="phone" class="form-control" placeholder="Телефон" required></div>
                    <div class="col-md-6"><input type="text" name="city" class="form-control" placeholder="Город"></div>
                    <div class="col-12"><input type="email" name="email" class="form-control" placeholder="Email, если нужен вход"></div>
                    <div class="caregiver-extra col-md-6 d-none"><input type="number" min="0" name="experience_years" class="form-control" placeholder="Опыт, лет"></div>
                    <div class="caregiver-extra col-md-6 d-none"><input type="number" min="0" name="hourly_rate_from" class="form-control" placeholder="Ставка от, ₽/час"></div>
                    <div class="col-md-6"><input type="password" name="password" class="form-control" placeholder="Пароль, необязательно"></div>
                    <div class="col-md-6"><input type="password" name="password_confirmation" class="form-control" placeholder="Повтор пароля"></div>
                    <div class="col-12"><button class="btn btn-dark w-100">Создать карточку</button></div>
                </form>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card-soft p-4">
                <form method="GET" class="row g-2 mb-4">
                    <div class="col-lg-5"><input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="ФИО, телефон или email"></div>
                    <div class="col-lg-3">
                        <select name="role" class="form-select">
                            <option value="">Клиенты и сиделки</option>
                            <option value="client" {{ request('role') === 'client' ? 'selected' : '' }}>Только клиенты</option>
                            <option value="caregiver" {{ request('role') === 'caregiver' ? 'selected' : '' }}>Только сиделки</option>
                        </select>
                    </div>
                    <div class="col-lg-3"><input type="text" name="city" value="{{ request('city') }}" class="form-control" placeholder="Город"></div>
                    <div class="col-lg-1"><button class="btn btn-outline-dark w-100">Найти</button></div>
                </form>

                <div class="table-responsive">
                    <table class="table crm-table">
                        <thead><tr><th>Человек</th><th>Роль</th><th>Контакты</th><th>Город</th><th>Доступность</th></tr></thead>
                        <tbody>
                        @forelse($people as $person)
                            <tr>
                                <td><a href="{{ route('crm.people.show', $person) }}" class="fw-bold text-decoration-none">{{ $person->name }}</a><div class="small text-secondary">ID {{ $person->id }}</div></td>
                                <td><span class="badge {{ $person->isCaregiver() ? 'text-bg-info' : 'text-bg-primary' }}">{{ $person->isCaregiver() ? 'Сиделка' : 'Клиент' }}</span>@if($person->is_verified)<span class="badge text-bg-success ms-1">Проверен</span>@endif</td>
                                <td><a href="tel:{{ $person->phone }}">{{ $person->phone ?: '—' }}</a><div class="small text-secondary">{{ str_ends_with($person->email, '@sidelka.local') ? 'внутренняя карточка' : $person->email }}</div></td>
                                <td>{{ $person->city ?: '—' }}</td>
                                <td>
                                    @if($person->isCaregiver())
                                        {{ $person->caregiverProfile?->availabilitySlots?->count() ?? 0 }} интервалов
                                    @else
                                        Заказов: {{ $person->clientOrders()->count() }}
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-secondary py-5">Люди не найдены.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $people->links() }}
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const role = document.getElementById('personRole');
    const updateExtra = () => document.querySelectorAll('.caregiver-extra').forEach(el => el.classList.toggle('d-none', role.value !== 'caregiver'));
    role.addEventListener('change', updateExtra);
    updateExtra();
</script>
@endpush
