<?php

$config = [
    'app_name' => 'Historico Diario',
    'timezone' => 'America/Sao_Paulo',

    'db' => [
        'host' => 'mysql.panblan.com.br',
        'name' => 'panblan',
        'user' => 'panblan',
        'pass' => 'meuposs1bilism0',
        'charset' => 'utf8mb4',
    ],

    'admin' => [
        'session_name' => 'historico_diario_admin',
    ],

    'cron' => [
        'token' => '12312312312312312312312312312312a3333734534',
        'default_limit' => 5,
    ],
];

$localConfig = __DIR__ . '/config.local.php';
if (file_exists($localConfig)) {
    $config = array_replace_recursive($config, require $localConfig);
}

return $config;
