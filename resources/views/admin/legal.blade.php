@extends('admin.layout')

@php($pageTitle = 'Реквизиты площадки')

@section('admin-page')
<div class="card-soft p-4">
    <h2 class="h4 mb-3">Юридические данные площадки</h2>
    <form action="{{ route('admin.legal.update') }}" method="POST" class="row g-3">
        @csrf
        <div class="col-md-8">
            <label class="form-label">Полное наименование</label>
            <input type="text" name="legal_company_name" class="form-control" value="{{ old('legal_company_name', $legalSettings['name']) }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">Краткое наименование</label>
            <input type="text" name="legal_company_short_name" class="form-control" value="{{ old('legal_company_short_name', $legalSettings['short_name']) }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">ИНН</label>
            <input type="text" name="legal_company_inn" class="form-control" value="{{ old('legal_company_inn', $legalSettings['inn']) }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">КПП</label>
            <input type="text" name="legal_company_kpp" class="form-control" value="{{ old('legal_company_kpp', $legalSettings['kpp']) }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">ОГРН</label>
            <input type="text" name="legal_company_ogrn" class="form-control" value="{{ old('legal_company_ogrn', $legalSettings['ogrn']) }}">
        </div>
        <div class="col-md-6">
            <label class="form-label">Телефон</label>
            <input type="text" name="legal_company_phone" class="form-control" value="{{ old('legal_company_phone', $legalSettings['phone']) }}">
        </div>
        <div class="col-md-6">
            <label class="form-label">Email</label>
            <input type="email" name="legal_company_email" class="form-control" value="{{ old('legal_company_email', $legalSettings['email']) }}">
        </div>
        <div class="col-12">
            <label class="form-label">Юридический адрес</label>
            <textarea name="legal_company_address" class="form-control" rows="2">{{ old('legal_company_address', $legalSettings['address']) }}</textarea>
        </div>
        <div class="col-md-4">
            <label class="form-label">Банк площадки</label>
            <input type="text" name="legal_company_bank_name" class="form-control" value="{{ old('legal_company_bank_name', $legalSettings['bank_name']) }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">БИК</label>
            <input type="text" name="legal_company_bank_bik" class="form-control" value="{{ old('legal_company_bank_bik', $legalSettings['bank_bik']) }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">Расчетный счет</label>
            <input type="text" name="legal_company_bank_account" class="form-control" value="{{ old('legal_company_bank_account', $legalSettings['bank_account']) }}">
        </div>
        <div class="col-12">
            <label class="form-label">Корреспондентский счет</label>
            <input type="text" name="legal_company_correspondent_account" class="form-control" value="{{ old('legal_company_correspondent_account', $legalSettings['correspondent_account']) }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">Подписант</label>
            <input type="text" name="legal_company_signatory_name" class="form-control" value="{{ old('legal_company_signatory_name', $legalSettings['signatory_name']) }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">Должность подписанта</label>
            <input type="text" name="legal_company_signatory_position" class="form-control" value="{{ old('legal_company_signatory_position', $legalSettings['signatory_position']) }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">Основание полномочий</label>
            <input type="text" name="legal_company_signatory_basis" class="form-control" value="{{ old('legal_company_signatory_basis', $legalSettings['signatory_basis']) }}">
        </div>
        <div class="col-12">
            <button class="btn btn-dark rounded-pill px-4">Сохранить реквизиты площадки</button>
        </div>
    </form>
</div>
@endsection
