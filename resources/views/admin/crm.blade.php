@extends('admin.layout')

@php($pageTitle = 'CRM-шаблоны')

@section('admin-page')
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card-soft p-4">
            <h2 class="h4 mb-3">Шаблоны сообщений CRM</h2>
            <p class="text-secondary">Эти шаблоны используются в карточке CRM-заявки и подставляются менеджеру в историю касаний одним кликом.</p>

            <form action="{{ route('admin.crm.update') }}" method="POST" class="row g-3">
                @csrf
                @foreach($crmSettings as $templateKey => $template)
                    <div class="col-12">
                        <div class="border rounded-4 p-3">
                            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2">
                                <label class="form-label mb-0 fw-semibold">{{ $template['title'] }}</label>
                                <span class="badge text-bg-light">
                                    @if($template['audience'] === 'client')
                                        Для клиента
                                    @elseif($template['audience'] === 'caregiver')
                                        Для сиделки
                                    @else
                                        Общий
                                    @endif
                                </span>
                            </div>
                            <input type="hidden" name="crm_templates[{{ $templateKey }}][title]" value="{{ old('crm_templates.'.$templateKey.'.title', $template['title']) }}">
                            <input type="hidden" name="crm_templates[{{ $templateKey }}][audience]" value="{{ old('crm_templates.'.$templateKey.'.audience', $template['audience']) }}">
                            <textarea name="crm_templates[{{ $templateKey }}][text]" class="form-control" rows="4" required>{{ old('crm_templates.'.$templateKey.'.text', $template['text']) }}</textarea>
                        </div>
                    </div>
                @endforeach
                <div class="col-12">
                    <button class="btn btn-dark px-4">Сохранить CRM-шаблоны</button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card-soft p-4">
            <h2 class="h4 mb-3">Что это дает</h2>
            <ul class="mb-0">
                <li>единый стиль общения менеджеров</li>
                <li>быстрые ответы по клиентам и сиделкам без ручного копирования</li>
                <li>отдельные шаблоны под срочные, повторные и длинные заказы</li>
                <li>готовая база фраз для запуска CRM без правок в коде</li>
            </ul>
        </div>
    </div>
</div>
@endsection
