<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Договор с клиентом</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; max-width: 920px; margin: 0 auto; padding: 32px; color: #1f2937; line-height: 1.55; }
        h1, h2 { margin-bottom: 12px; }
        .meta { margin-bottom: 24px; color: #4b5563; }
        .box { border: 1px solid #d1d5db; padding: 16px; border-radius: 12px; margin-bottom: 18px; }
    </style>
</head>
<body>
    <h1>Договор с клиентом платформы</h1>
    <div class="meta">№ {{ $agreementNumber }} от {{ $agreementDate->format('d.m.Y') }}</div>

    <div class="box">
        <strong>Клиент:</strong> {{ $profile->legal_full_name ?? $user->name }}<br>
        Паспорт: {{ trim(($profile->passport_series ?? '') . ' ' . ($profile->passport_number ?? '')) ?: 'не заполнено' }}<br>
        Адрес регистрации: {{ $profile->registration_address ?? 'не заполнено' }}<br>
        Контактный телефон: {{ $user->phone ?? 'не заполнено' }}
    </div>

    <h2>1. Предмет договора</h2>
    <p>Платформа предоставляет Клиенту доступ к сервису поиска, подбора и безопасной оплаты услуг сиделок, а Клиент обязуется использовать сервис в рамках правил платформы.</p>

    <h2>2. Обязанности клиента</h2>
    <p>Клиент обязуется указывать достоверные данные о себе и пациенте, корректно описывать услуги, график, медицинские ограничения и подтверждать выполненные смены после фактического оказания услуг.</p>

    <h2>3. Оплата</h2>
    <p>Средства вносятся через платформу и резервируются до подтверждения выполненной смены. После подтверждения деньги перечисляются сиделке в порядке, установленном правилами сервиса.</p>

    <h2>4. Документы и подтверждения</h2>
    <p>Данные, сохраненные в кабинете клиента, а также электронные подтверждения заказов и смен считаются частью договорных отношений сторон.</p>

    <h2>5. Подпись</h2>
    <p>Клиент: __________________ / {{ $profile->legal_full_name ?? $user->name }} /</p>
</body>
</html>
