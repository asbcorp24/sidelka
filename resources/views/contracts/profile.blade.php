@extends('layouts.app')

@php
    $title = 'Документы и договорные данные';
    $contract = $user->contractProfile;
    $frameworkType = $user->isCaregiver()
        ? \App\Models\LegalContract::TYPE_CAREGIVER_AGENCY
        : \App\Models\LegalContract::TYPE_CLIENT_AGENCY;

    $frameworkParty = $user->legalContractParties()
        ->whereHas('contract', function ($query) use ($frameworkType) {
            $query->where('type', $frameworkType)
                ->where(function ($statusQuery) {
                    $statusQuery->where('status', \App\Models\LegalContract::STATUS_SIGNED)
                        ->orWhere(function ($activeQuery) {
                            $activeQuery->where('status', \App\Models\LegalContract::STATUS_AWAITING)
                                ->where(function ($expiryQuery) {
                                    $expiryQuery->whereNull('expires_at')
                                        ->orWhere('expires_at', '>', now());
                                });
                        });
                });
        })
        ->with('contract.parties.signature')
        ->latest('id')
        ->first();

    $frameworkContract = $frameworkParty?->contract;
@endphp

@section('content')
<div class="container py-4 py-lg-5">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
        <div>
            <div class="text-uppercase small text-secondary">Юридический раздел</div>
            <h1 class="section-title mb-0">Данные для договора {{ $roleLabel }}</h1>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if($frameworkContract)
                <a href="{{ route('legal.contracts.show', $frameworkContract) }}" class="btn btn-dark rounded-pill px-4">Открыть онлайн-договор</a>
            @endif
            <a href="{{ $contractPreviewRoute }}" class="btn btn-outline-dark rounded-pill px-4" target="_blank">Черновик PDF</a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card-soft p-4 mb-4">
                <h2 class="h4 mb-3">Рамочный агентский договор</h2>
                <p class="text-secondary">Площадка организует подбор, документы и расчеты. Конкретный договор по заказу оформляется отдельно по фактической сделке.</p>
                @if($frameworkContract)
                    <div class="border rounded-4 p-3">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <div>
                                <strong>{{ $frameworkContract->title }}</strong>
                                <div class="small text-secondary">{{ $frameworkContract->number }} • версия {{ $frameworkContract->version }}</div>
                            </div>
                            <span class="badge {{ $frameworkContract->status === 'signed' ? 'text-bg-success' : 'text-bg-warning' }}">
                                {{ $frameworkContract->status === 'signed' ? 'Подписан' : 'Ожидает подписи' }}
                            </span>
                        </div>
                        <a href="{{ route('legal.contracts.show', $frameworkContract) }}" class="btn btn-outline-dark rounded-pill mt-3">Просмотреть договор</a>
                    </div>
                @else
                    <form action="{{ route('legal.framework.create') }}" method="POST">
                        @csrf
                        <button class="btn btn-dark rounded-pill px-4">Сформировать агентский договор</button>
                    </form>
                @endif
            </div>

            <div class="card-soft p-4">
                <h2 class="h4 mb-3">Договорные данные</h2>
                <form action="{{ route('contracts.profile.update') }}" method="POST" class="row g-3">
                    @csrf
                    <div class="col-md-6">
                        <label class="form-label">ФИО полностью</label>
                        <input type="text" name="legal_full_name" class="form-control" value="{{ old('legal_full_name', $contract->legal_full_name ?? $user->name) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Дата рождения</label>
                        <input type="date" name="birth_date" class="form-control" value="{{ old('birth_date', optional($contract?->birth_date)->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Город договора</label>
                        <input type="text" name="contract_city" class="form-control" value="{{ old('contract_city', $contract->contract_city ?? $user->city) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Паспорт серия</label>
                        <input type="text" name="passport_series" class="form-control" value="{{ old('passport_series', $contract->passport_series ?? '') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Паспорт номер</label>
                        <input type="text" name="passport_number" class="form-control" value="{{ old('passport_number', $contract->passport_number ?? '') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Дата выдачи</label>
                        <input type="date" name="passport_issued_at" class="form-control" value="{{ old('passport_issued_at', optional($contract?->passport_issued_at)->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Код подразделения</label>
                        <input type="text" name="passport_department_code" class="form-control" value="{{ old('passport_department_code', $contract->passport_department_code ?? '') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Кем выдан паспорт</label>
                        <input type="text" name="passport_issued_by" class="form-control" value="{{ old('passport_issued_by', $contract->passport_issued_by ?? '') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Адрес регистрации</label>
                        <input type="text" name="registration_address" class="form-control" value="{{ old('registration_address', $contract->registration_address ?? '') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Адрес проживания</label>
                        <input type="text" name="residence_address" class="form-control" value="{{ old('residence_address', $contract->residence_address ?? '') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">ИНН</label>
                        <input type="text" name="inn" class="form-control" value="{{ old('inn', $contract->inn ?? '') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">СНИЛС</label>
                        <input type="text" name="snils" class="form-control" value="{{ old('snils', $contract->snils ?? '') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Налоговый статус</label>
                        <input type="text" name="tax_status" class="form-control" value="{{ old('tax_status', $contract->tax_status ?? '') }}" placeholder="Самозанятый / физлицо / ИП">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Получатель выплат</label>
                        <input type="text" name="bank_recipient_name" class="form-control" value="{{ old('bank_recipient_name', $contract->bank_recipient_name ?? '') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Банк</label>
                        <input type="text" name="bank_name" class="form-control" value="{{ old('bank_name', $contract->bank_name ?? '') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">БИК</label>
                        <input type="text" name="bank_bik" class="form-control" value="{{ old('bank_bik', $contract->bank_bik ?? '') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Расчетный счет</label>
                        <input type="text" name="bank_account" class="form-control" value="{{ old('bank_account', $contract->bank_account ?? '') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Карта / номер</label>
                        <input type="text" name="card_number" class="form-control" value="{{ old('card_number', $contract->card_number ?? '') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Контакт на экстренный случай</label>
                        <input type="text" name="emergency_contact_name" class="form-control" value="{{ old('emergency_contact_name', $contract->emergency_contact_name ?? '') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Телефон экстренного контакта</label>
                        <input type="text" name="emergency_contact_phone" class="form-control" value="{{ old('emergency_contact_phone', $contract->emergency_contact_phone ?? '') }}">
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_self_employed" value="1" {{ old('is_self_employed', $contract->is_self_employed ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label">Самозанятый / специальный налоговый режим</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Примечание</label>
                        <textarea name="notes" class="form-control" rows="3">{{ old('notes', $contract->notes ?? '') }}</textarea>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-dark rounded-pill px-4">Сохранить договорные данные</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card-soft p-4 mb-4">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <h2 class="h4 mb-1">Документы</h2>
                        <div class="small text-secondary">Тип документа теперь выбирается из списка, а не вводится вручную.</div>
                    </div>
                    <span class="badge text-bg-light">{{ $user->documents->count() }} шт.</span>
                </div>

                <form action="{{ route('contracts.document.store') }}" method="POST" enctype="multipart/form-data" class="row g-3 mb-4">
                    @csrf
                    <div class="col-12">
                        <label class="form-label">Тип документа</label>
                        <select name="document_type" class="form-select" required>
                            <option value="">Выберите тип документа</option>
                            @foreach($documentTypeOptions as $key => $option)
                                @php($label = is_array($option) ? $option['label'] : $option)
                                <option value="{{ $key }}" {{ old('document_type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Название</label>
                        <input type="text" name="title" class="form-control" value="{{ old('title') }}" placeholder="Можно оставить пустым, подставится автоматически">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Номер документа</label>
                        <input type="text" name="document_number" class="form-control" value="{{ old('document_number') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Скан / файл</label>
                        <input type="file" name="scan" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Дата выдачи</label>
                        <input type="date" name="issued_at" class="form-control" value="{{ old('issued_at') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Срок действия</label>
                        <input type="date" name="expires_at" class="form-control" value="{{ old('expires_at') }}">
                    </div>
                    <div class="col-12">
                        <input type="hidden" name="verification_status" value="{{ \App\Models\UserDocument::STATUS_UPLOADED }}">
                        <label class="form-label">Комментарий</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Например: новый паспорт, продленная медкнижка, реквизиты для выплат">{{ old('notes') }}</textarea>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-outline-dark rounded-pill px-4">Добавить документ</button>
                    </div>
                </form>

                @forelse($user->documents->sortByDesc('created_at') as $document)
                    <div class="border rounded-4 p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                            <div>
                                <strong>{{ $document->title ?: $document->type_label }}</strong>
                                <div class="text-secondary small">{{ $document->type_label }} @if($document->document_number) • № {{ $document->document_number }} @endif</div>
                            </div>
                            <span class="badge {{ $document->verification_status === \App\Models\UserDocument::STATUS_VERIFIED ? 'text-bg-success' : ($document->verification_status === \App\Models\UserDocument::STATUS_REJECTED ? 'text-bg-danger' : 'text-bg-warning') }}">
                                {{ $document->status_label }}
                            </span>
                        </div>
                        <div class="small text-secondary mt-2">
                            Выдан: {{ $document->issued_at?->format('d.m.Y') ?: 'не указано' }}
                            • Срок: {{ $document->expires_at?->format('d.m.Y') ?: 'бессрочно' }}
                        </div>
                        @if($document->notes)
                            <div class="small mt-2">{{ $document->notes }}</div>
                        @endif
                        <div class="d-flex gap-2 flex-wrap mt-3">
                            @if($document->file_path)
                                <a href="{{ route('contracts.document.download', $document) }}" class="btn btn-sm btn-outline-dark rounded-pill">Открыть файл</a>
                            @endif
                            @if($document->is_required)
                                <span class="badge text-bg-light">Обязательный</span>
                            @endif
                            @if($document->blocks_assignments)
                                <span class="badge text-bg-warning">Блокирует новые смены</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-secondary mb-0">Документы пока не добавлены.</p>
                @endforelse
            </div>

            <div class="card-soft p-4">
                <h2 class="h4 mb-3">Как подписывается</h2>
                <ol class="mb-0 ps-3">
                    <li>Система формирует неизменяемую версию договора.</li>
                    <li>Вы проверяете текст и реквизиты.</li>
                    <li>Подтверждение связывается с конкретной версией документа.</li>
                    <li>PDF и история остаются в личном кабинете.</li>
                </ol>
            </div>
        </div>
    </div>
</div>
@endsection
