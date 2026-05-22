<?php

$config = [
    'app_name' => 'Historico Diario',
    'timezone' => 'America/Sao_Paulo',

    'db' => [
        'host' => 'localhost',
        'name' => 'NOME_DO_BANCO',
        'user' => 'USUARIO_DO_BANCO',
        'pass' => 'SENHA_DO_BANCO',
        'charset' => 'utf8mb4',
    ],

    'admin' => [
        'session_name' => 'historico_diario_admin',
    ],

    'cron' => [
        'token' => 'TROQUE_ESTE_TOKEN',
        'default_limit' => 5,
    ],

    'sources' => [
        'wikimedia' => [
            'enabled' => true,
            'languages' => ['pt', 'en'],
            'types' => ['selected', 'events'],
            'max_import' => 30,
            'user_agent' => 'PossibilismosMVP/0.1 (https://panblan.com.br)',
        ],
    ],
];

$localConfig = __DIR__ . '/config.local.php';
if (file_exists($localConfig)) {
    $config = array_replace_recursive($config, require $localConfig);
}

return $config;
