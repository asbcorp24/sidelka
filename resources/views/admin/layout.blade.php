@extends('layouts.app')

@php($title = $pageTitle ?? 'Суперадмин')

@section('content')
<div class="container py-4 py-lg-5">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
        <div>
            <div class="text-uppercase small text-secondary">Управление платформой</div>
            <h1 class="section-title mb-0">{{ $pageTitle ?? 'Суперадмин' }}</h1>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('crm.dashboard') }}" class="btn btn-outline-dark rounded-pill px-4">Открыть CRM</a>
            <a href="{{ route('home') }}" class="btn btn-dark rounded-pill px-4">На сайт</a>
        </div>
    </div>

    <div class="card-soft p-3 p-lg-4 mb-4">
        <div class="d-flex flex-wrap gap-2">
            @foreach($adminMenu as $item)
                <a
                    href="{{ route($item['route']) }}"
                    class="btn rounded-pill px-4 {{ request()->routeIs($item['route']) ? 'btn-dark' : 'btn-outline-dark' }}"
                >
                    {{ $item['label'] }}
                </a>
            @endforeach
        </div>
    </div>

    @yield('admin-page')
</div>
@endsection
