@extends('admin.layout')

@php($pageTitle = 'Услуги')

@section('admin-page')
<div class="row g-4">
    <div class="col-lg-5">
        <div class="card-soft p-4">
            <h2 class="h4 mb-3">Добавить услугу</h2>
            <form action="{{ route('admin.services.store') }}" method="POST" class="row g-3">
                @csrf
                <div class="col-12"><input type="text" name="name" class="form-control" placeholder="Название услуги"></div>
                <div class="col-12"><input type="text" name="category" class="form-control" placeholder="Категория"></div>
                <div class="col-12"><textarea name="description" class="form-control" rows="3" placeholder="Описание"></textarea></div>
                <div class="col-12"><input type="number" name="hourly_surcharge" class="form-control" placeholder="Доплата, ₽/час"></div>
                <div class="col-12">
                    <label class="form-check">
                        <input class="form-check-input" type="checkbox" name="requires_medical_training" value="1">
                        <span class="form-check-label">Требует медобразование</span>
                    </label>
                </div>
                <div class="col-12"><button class="btn btn-dark rounded-pill px-4">Сохранить услугу</button></div>
            </form>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card-soft p-4">
            <h2 class="h4 mb-3">Каталог услуг</h2>
            @foreach($services as $service)
                <div class="d-flex justify-content-between align-items-start border-bottom py-3 gap-3">
                    <div>
                        <div class="fw-semibold">{{ $service->name }}</div>
                        <div class="small text-secondary">{{ $service->category ?: 'Без категории' }}</div>
                        @if($service->description)
                            <div class="small text-secondary mt-1">{{ $service->description }}</div>
                        @endif
                    </div>
                    <div class="text-end small text-secondary">
                        <div>{{ $service->requires_medical_training ? 'Медицинская' : 'Бытовая' }}</div>
                        <div>+{{ $service->hourly_surcharge }} ₽/час</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
