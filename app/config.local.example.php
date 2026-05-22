<?php

return [
    'db' => [
        'host' => 'localhost',
        'name' => 'NOME_DO_BANCO',
        'user' => 'USUARIO_DO_BANCO',
        'pass' => 'SENHA_DO_BANCO',
    ],

    'cron' => [
        'token' => 'UM_TOKEN_LONGO_E_ALEATORIO',
    ],

    'sources' => [
        'wikimedia' => [
            'user_agent' => 'PossibilismosMVP/0.1 (email-ou-url-de-contato)',
        ],
        'news' => [
            'enabled' => true,
        ],
        'trends' => [
            'enabled' => true,
        ],
    ],
];
