<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.45; color: #111; }
        h1 { font-size: 17px; text-align: center; margin-bottom: 14px; }
        h2 { font-size: 12px; margin-top: 15px; margin-bottom: 6px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { border: 1px solid #777; padding: 5px; vertical-align: top; }
        ol { padding-left: 20px; }
        .meta { border: 1px solid #999; padding: 8px; margin-bottom: 14px; }
        .signature { border: 1px solid #777; padding: 8px; margin-top: 8px; page-break-inside: avoid; }
        .hash { font-family: DejaVu Sans Mono, monospace; font-size: 8px; word-break: break-all; }
    </style>
</head>
<body>
    <div class="meta">
        <strong>{{ $contract->title }}</strong><br>
        Номер: {{ $contract->number }}; версия: {{ $contract->version }}; статус: {{ $contract->status }}.<br>
        SHA-256 неизменяемого текста: <span class="hash">{{ $contract->document_hash }}</span>
    </div>

    {!! $contract->body_html !!}

    <h2>Электронные подписи и системные отметки</h2>
    @foreach($contract->parties as $party)
        <div class="signature">
            <strong>{{ $party->name }}</strong> — {{ $party->role }}<br>
            Статус: {{ $party->status === 'signed' ? 'подписано' : 'подпись отсутствует' }}.<br>
            @if($party->signature)
                Метод: {{ $party->signature->method }}; канал: {{ $party->signature->channel }};<br>
                дата: {{ $party->signature->signed_at->format('d.m.Y H:i:s') }};<br>
                IP: {{ $party->signature->ip_address ?: 'системная отметка' }};<br>
                хеш подписанного документа: <span class="hash">{{ $party->signature->document_hash }}</span>
            @endif
        </div>
    @endforeach

    <p style="margin-top: 16px;">PDF сформирован {{ now()->format('d.m.Y H:i:s') }}. Юридически значимой является сохраненная в системе версия документа с указанным SHA-256 и протоколом подписания.</p>
</body>
</html>
