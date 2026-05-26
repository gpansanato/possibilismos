<?php

$config = [
    'app_name' => 'Histórico Diário',
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
            'languages' => ['pt', 'en', 'es'],
            'types' => ['selected', 'events'],
            'max_import' => 30,
            'user_agent' => 'PossibilismosMVP/0.1 (https://panblan.com.br)',
        ],
        'historical' => [
            'enabled' => true,
            'max_import' => 35,
            'max_duration_seconds' => 120,
            'max_enrich_during_collection' => 0,
            'max_enrichment_events_per_run' => 20,
            'max_enrichment_duration_seconds' => 120,
            'max_enrichments_per_source' => 1,
            'wikidata' => [
                'enabled' => true,
                'endpoint' => 'https://query.wikidata.org/sparql',
                'variant_limits' => [
                    'point_in_time' => 8,
                    'start_time' => 5,
                    'end_time' => 5,
                    'political_events' => 5,
                    'conflicts' => 5,
                    'discoveries_inventions' => 5,
                    'works_publications' => 5,
                    'births_deaths' => 5,
                    'deaths' => 5,
                ],
            ],
            'wikipedia' => [
                'enabled' => true,
                'summary_url' => 'https://en.wikipedia.org/api/rest_v1/page/summary/%s',
            ],
            'commons' => [
                'enabled' => true,
            ],
            'library_of_congress' => [
                'enabled' => true,
                'url' => 'https://www.loc.gov/search/',
            ],
            'europeana' => [
                'enabled' => false,
                'url' => 'https://api.europeana.eu/record/v2/search.json',
                'api_key' => '',
            ],
            'smithsonian' => [
                'enabled' => false,
                'url' => 'https://api.si.edu/openaccess/api/v1.0/search',
                'api_key' => '',
            ],
            'dpla' => [
                'enabled' => false,
                'url' => 'https://api.dp.la/v2/items',
                'api_key' => '',
            ],
            'openhistoricalmap' => [
                'enabled' => false,
                'url' => '',
            ],
        ],
        'news' => [
            'enabled' => true,
            'max_items' => 30,
            'min_keyword_length' => 4,
            'feeds' => [
                [
                    'name' => 'Google News Brasil',
                    'url' => 'https://news.google.com/rss?hl=pt-BR&gl=BR&ceid=BR:pt-419',
                ],
                [
                    'name' => 'Google News Mundo',
                    'url' => 'https://news.google.com/rss/search?q=mundo&hl=pt-BR&gl=BR&ceid=BR:pt-419',
                ],
                [
                    'name' => 'Google News Tecnologia',
                    'url' => 'https://news.google.com/rss/search?q=tecnologia&hl=pt-BR&gl=BR&ceid=BR:pt-419',
                ],
            ],
        ],
        'trends' => [
            'enabled' => true,
            'max_items' => 45,
            'min_keyword_length' => 3,
            'feeds' => [],
            'gdelt' => [
                'enabled' => true,
                'max_items' => 10,
                'query' => 'brasil OR brazil OR tecnologia OR economia OR politica OR saude OR clima',
                'url' => 'https://api.gdeltproject.org/api/v2/doc/doc',
            ],
            'media_cloud' => [
                'enabled' => false,
                'max_items' => 10,
                'api_key' => '',
                'url' => '',
                'query' => 'brasil OR brazil OR tecnologia OR economia OR politica OR saude OR clima',
            ],
            'wikimedia_pageviews' => [
                'enabled' => true,
                'max_items' => 12,
                'projects' => ['pt.wikipedia', 'en.wikipedia'],
                'url' => 'https://wikimedia.org/api/rest_v1/metrics/pageviews/top',
            ],
            'agencia_brasil' => [
                'enabled' => true,
                'max_items' => 10,
                'feeds' => [
                    [
                        'name' => 'Agencia Brasil Ultimas',
                        'url' => 'https://agenciabrasil.ebc.com.br/feed/ultimasnoticias/feed.xml',
                        'fallback_url' => 'https://agenciabrasil.ebc.com.br/rss/ultimasnoticias/feed.xml',
                    ],
                ],
            ],
            'hacker_news' => [
                'enabled' => true,
                'max_items' => 12,
                'list_url' => 'https://hacker-news.firebaseio.com/v0/topstories.json',
                'item_url' => 'https://hacker-news.firebaseio.com/v0/item/%d.json',
            ],
        ],
    ],
];

$localConfig = __DIR__ . '/config.local.php';
if (file_exists($localConfig)) {
    $config = array_replace_recursive($config, require $localConfig);
}

return $config;
