<h1>АКТ ОКАЗАННЫХ УСЛУГ ПО СМЕНЕ № {{ $number }}</h1>
<p><strong>Заказ:</strong> #{{ $order->id }} — {{ $order->title }}.</p>
<p><strong>Заказчик:</strong> {{ $client->name }}. <strong>Исполнитель:</strong> {{ $caregiver->name }}.</p>

<table width="100%" cellpadding="6" cellspacing="0" border="1">
    <tr><td width="40%"><strong>Подопечный</strong></td><td>{{ $order->patient_name ?: 'не указан' }}</td></tr>
    <tr><td><strong>Место оказания</strong></td><td>{{ $order->city }}, {{ $order->address ?: 'адрес заказа' }}</td></tr>
    @if($slot)
        <tr><td><strong>Дата смены</strong></td><td>{{ $slot->scheduled_date->format('d.m.Y') }}</td></tr>
        <tr><td><strong>Время</strong></td><td>{{ substr($slot->starts_at, 0, 5) }}–{{ substr($slot->ends_at, 0, 5) }}</td></tr>
    @endif
    <tr><td><strong>Содержание услуг</strong></td><td>{{ $order->description }}</td></tr>
</table>

<h2>Расчёт</h2>
<table width="100%" cellpadding="6" cellspacing="0" border="1">
    <tr><td>Стоимость выполненной смены</td><td>{{ number_format($grossAmount, 0, ',', ' ') }} ₽</td></tr>
    <tr><td>Агентское вознаграждение ({{ number_format($commissionPercent, 2, ',', ' ') }}%)</td><td>{{ number_format($commissionAmount, 0, ',', ' ') }} ₽</td></tr>
    <tr><td><strong>К выплате Исполнителю</strong></td><td><strong>{{ number_format($payoutAmount, 0, ',', ' ') }} ₽</strong></td></tr>
</table>

<p>Исполнитель подтверждает фактическое выполнение указанной смены и достоверность журнала ухода. Заказчик подтверждает объём и качество либо открывает мотивированный спор до формирования выплаты.</p>
<p>Подтверждения сторон фиксируются системой с датой, IP-адресом, устройством и SHA-256 хешем неизменяемого текста акта.</p>
