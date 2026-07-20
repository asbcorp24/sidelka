<?php

return [
    'enabled' => env('SBER_ACQUIRING_ENABLED', false),

    'base_url' => env(
        'SBER_ACQUIRING_URL',
        'https://ecomtest.sberbank.ru/ecomm/gw/partner/api/v1'
    ),

    'username' => env('SBER_ACQUIRING_USERNAME'),
    'password' => env('SBER_ACQUIRING_PASSWORD'),

    'timeout' => (int) env('SBER_ACQUIRING_TIMEOUT', 20),

    'description_prefix' => env(
        'SBER_ACQUIRING_DESCRIPTION',
        'Пополнение баланса Сиделка24'
    ),
];
