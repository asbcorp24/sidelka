<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Договор с сиделкой</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; max-width: 920px; margin: 0 auto; padding: 32px; color: #1f2937; line-height: 1.55; }
        h1, h2 { margin-bottom: 12px; }
        .meta { margin-bottom: 24px; color: #4b5563; }
        .box { border: 1px solid #d1d5db; padding: 16px; border-radius: 12px; margin-bottom: 18px; }
    </style>
</head>
<body>
    <h1>Договор сотрудничества с сиделкой</h1>
    <div class="meta">№ {{ $agreementNumber }} от {{ $agreementDate->format('d.m.Y') }}</div>

    <div class="box">
        <strong>Исполнитель:</strong> {{ $profile->legal_full_name ?? $user->name }}<br>
        Паспорт: {{ trim(($profile->passport_series ?? '') . ' ' . ($profile->passport_number ?? '')) ?: 'не заполнено' }}<br>
        Адрес регистрации: {{ $profile->registration_address ?? 'не заполнено' }}<br>
        ИНН: {{ $profile->inn ?? 'не заполнено' }}<br>
        СНИЛС: {{ $profile->snils ?? 'не заполнено' }}<br>
        Статус: {{ $profile->tax_status ?? 'не заполнено' }}
    </div>

    <h2>1. Предмет договора</h2>
    <p>Платформа предоставляет Исполнителю доступ к сервису подбора заказов на услуги сиделки, а Исполнитель обязуется использовать сервис добросовестно, актуализировать документы и корректно исполнять подтвержденные заказы.</p>

    <h2>2. Обязанности сиделки</h2>
    <p>Исполнитель подтверждает достоверность анкетных данных, перечня доступных услуг, медицинской подготовки, календаря доступности и обязуется немедленно сообщать об изменениях реквизитов, документов и ограничений по работе.</p>

    <h2>3. Финансовые условия</h2>
    <p>Выплаты производятся на реквизиты, указанные в договорном профиле: {{ $profile->bank_name ?? 'банк не указан' }}, получатель {{ $profile->bank_recipient_name ?? 'не указан' }}.</p>

    <h2>4. Электронный документооборот</h2>
    <p>Стороны признают юридическую силу данных, введенных в личном кабинете, и электронных подтверждений, сформированных через платформу.</p>

    <h2>5. Подпись</h2>
    <p>Исполнитель: __________________ / {{ $profile->legal_full_name ?? $user->name }} /</p>
</body>
</html>
