<?php

return [
    'enabled_collections' => [],

    'grace_days' => 1,

    'signing_key' => env('RESRV_VOUCHERS_SIGNING_KEY'),

    'email' => [
        'attended' => [
            'subject' => null,
            'from' => [
                'address' => null,
                'name' => null,
            ],
            'markdown' => null,
        ],
    ],
];
