<?php

return [
    'company' => [
        'name' => env('LEGAL_COMPANY_NAME', env('APP_NAME', 'Сиделка24')),
        'short_name' => env('LEGAL_COMPANY_SHORT_NAME', env('APP_NAME', 'Сиделка24')),
        'inn' => env('LEGAL_COMPANY_INN'),
        'kpp' => env('LEGAL_COMPANY_KPP'),
        'ogrn' => env('LEGAL_COMPANY_OGRN'),
        'address' => env('LEGAL_COMPANY_ADDRESS'),
        'email' => env('LEGAL_COMPANY_EMAIL', env('MAIL_FROM_ADDRESS')),
        'phone' => env('LEGAL_COMPANY_PHONE'),
        'bank_name' => env('LEGAL_COMPANY_BANK_NAME'),
        'bank_bik' => env('LEGAL_COMPANY_BANK_BIK'),
        'bank_account' => env('LEGAL_COMPANY_BANK_ACCOUNT'),
        'correspondent_account' => env('LEGAL_COMPANY_CORRESPONDENT_ACCOUNT'),
        'signatory_name' => env('LEGAL_COMPANY_SIGNATORY_NAME'),
        'signatory_position' => env('LEGAL_COMPANY_SIGNATORY_POSITION', 'Генеральный директор'),
        'signatory_basis' => env('LEGAL_COMPANY_SIGNATORY_BASIS', 'Устава'),
    ],

    'agent_commission_percent' => (float) env('LEGAL_AGENT_COMMISSION_PERCENT', 10),
    'contract_version' => (int) env('LEGAL_CONTRACT_VERSION', 1),
    'contract_lifetime_days' => (int) env('LEGAL_CONTRACT_LIFETIME_DAYS', 30),

    'signature' => [
        'channel' => env('LEGAL_SIGNATURE_CHANNEL', 'auto'),
        'code_ttl_minutes' => (int) env('LEGAL_SIGNATURE_CODE_TTL', 10),
        'max_attempts' => (int) env('LEGAL_SIGNATURE_MAX_ATTEMPTS', 5),
    ],

    'sms_ru' => [
        'api_id' => env('SMS_RU_API_ID'),
        'from' => env('SMS_RU_FROM'),
        'test' => (bool) env('SMS_RU_TEST', false),
        'url' => env('SMS_RU_URL', 'https://sms.ru/sms/send'),
    ],
];
