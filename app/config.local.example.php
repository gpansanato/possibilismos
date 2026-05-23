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
        'historical' => [
            'europeana' => [
                'enabled' => false,
                'api_key' => 'INFORME_A_CHAVE_SE_USAR_EUROPEANA',
            ],
            'smithsonian' => [
                'enabled' => false,
                'api_key' => 'INFORME_A_CHAVE_SE_USAR_SMITHSONIAN',
            ],
            'dpla' => [
                'enabled' => false,
                'api_key' => 'INFORME_A_CHAVE_SE_USAR_DPLA',
            ],
            'openhistoricalmap' => [
                'enabled' => false,
                'url' => 'INFORME_ENDPOINT_OHM_SE_USAR',
            ],
        ],
        'news' => [
            'enabled' => true,
        ],
        'trends' => [
            'enabled' => true,
            'media_cloud' => [
                'enabled' => false,
                'api_key' => 'INFORME_A_CHAVE_SE_USAR_MEDIA_CLOUD',
                'url' => 'INFORME_O_ENDPOINT_DA_API_MEDIA_CLOUD',
            ],
        ],
    ],
];
