<?php

function import_historical_events_for_today(): int
{
    $today = today_key();
    return import_historical_events_for_day($today['month'], $today['day']);
}

function import_historical_events_for_day(int $month, int $day): int
{
    $config = require __DIR__ . '/config.php';
    $settings = $config['sources']['wikimedia'] ?? [];

    if (empty($settings['enabled'])) {
        return 0;
    }

    $imported = 0;
    $maxImport = (int) ($settings['max_import'] ?? 30);
    $languages = $settings['languages'] ?? ['pt', 'en'];
    $types = $settings['types'] ?? ['selected', 'events'];

    foreach ($languages as $language) {
        foreach ($types as $type) {
            $events = fetch_wikimedia_on_this_day($language, $type, $month, $day);

            foreach ($events as $event) {
                if ($imported >= $maxImport) {
                    return $imported;
                }

                if (save_wikimedia_event($event, $month, $day, $language, $type)) {
                    $imported++;
                }
            }

            if ($imported > 0 && $type === 'selected') {
                break;
            }
        }

        if ($imported > 0) {
            break;
        }
    }

    return $imported;
}

function fetch_wikimedia_on_this_day(string $language, string $type, int $month, int $day): array
{
    $config = require __DIR__ . '/config.php';
    $settings = $config['sources']['wikimedia'] ?? [];
    $url = sprintf(
        'https://api.wikimedia.org/feed/v1/wikipedia/%s/onthisday/%s/%02d/%02d',
        rawurlencode($language),
        rawurlencode($type),
        $month,
        $day
    );

    $body = http_get_json($url, $settings['user_agent'] ?? 'PossibilismosMVP/0.1');
    if (!$body) {
        return [];
    }

    $data = json_decode($body, true);
    if (!is_array($data)) {
        return [];
    }

    return $data[$type] ?? [];
}

function http_get_json(string $url, string $userAgent): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => $userAgent,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Api-User-Agent: ' . $userAgent,
            ],
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($status >= 200 && $status < 300 && is_string($body)) ? $body : null;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 20,
            'header' => implode("\r\n", [
                'Accept: application/json',
                'User-Agent: ' . $userAgent,
                'Api-User-Agent: ' . $userAgent,
            ]),
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    return is_string($body) ? $body : null;
}

function save_wikimedia_event(array $event, int $month, int $day, string $language, string $type): bool
{
    if (empty($event['text']) || !isset($event['year'])) {
        return false;
    }

    $year = (int) $event['year'];
    $description = trim(strip_tags((string) $event['text']));
    $title = make_event_title($description, $year);
    $sourceUrl = wikimedia_event_url($event);

    if (event_exists($month, $day, $year, $title)) {
        return false;
    }

    $stmt = db()->prepare(
        'INSERT INTO events
         (event_month, event_day, year, title, description, category, region, source_url, base_score, review_status, active, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "pending", 0, NOW())'
    );

    $stmt->execute([
        $month,
        $day,
        $year,
        $title,
        $description,
        infer_event_category($description),
        infer_event_region($event, $language),
        $sourceUrl,
        $type === 'selected' ? 72.00 : 58.00,
    ]);

    return true;
}

function event_exists(int $month, int $day, int $year, string $title): bool
{
    $stmt = db()->prepare(
        'SELECT id FROM events WHERE event_month = ? AND event_day = ? AND year = ? AND title = ? LIMIT 1'
    );
    $stmt->execute([$month, $day, $year, $title]);

    return (bool) $stmt->fetch();
}

function make_event_title(string $description, int $year): string
{
    $title = preg_replace('/\s+/', ' ', $description);
    $title = trim((string) $title);

    if (mb_strlen($title, 'UTF-8') > 115) {
        $title = mb_substr($title, 0, 112, 'UTF-8') . '...';
    }

    return $year . ' - ' . $title;
}

function wikimedia_event_url(array $event): ?string
{
    $page = $event['pages'][0] ?? null;
    if (!$page || empty($page['content_urls']['desktop']['page'])) {
        return null;
    }

    return $page['content_urls']['desktop']['page'];
}

function infer_event_category(string $description): string
{
    $text = mb_strtolower($description, 'UTF-8');

    $categories = [
        'tecnologia' => ['computer', 'software', 'internet', 'spacecraft', 'satellite', 'computador', 'internet'],
        'ciencia' => ['earthquake', 'science', 'medical', 'space', 'terremoto', 'cientista', 'espaco'],
        'politica' => ['president', 'king', 'queen', 'government', 'war', 'treaty', 'election', 'presidente', 'governo', 'guerra', 'tratado'],
        'cultura' => ['film', 'music', 'writer', 'novel', 'artist', 'cinema', 'musica', 'escritor', 'arte'],
        'economia' => ['bank', 'market', 'company', 'trade', 'banco', 'mercado', 'empresa', 'comercio'],
        'esporte' => ['football', 'olympic', 'world cup', 'soccer', 'futebol', 'olimpiada'],
    ];

    foreach ($categories as $category => $keywords) {
        foreach ($keywords as $keyword) {
            if (mb_strpos($text, $keyword, 0, 'UTF-8') !== false) {
                return $category;
            }
        }
    }

    return 'historia';
}

function infer_event_region(array $event, string $language): string
{
    $page = $event['pages'][0]['title'] ?? null;
    if ($page) {
        return str_replace('_', ' ', (string) $page);
    }

    return 'Wikipedia ' . $language;
}

function current_topics_for_today(?string $runDate = null): array
{
    $runDate = $runDate ?: today_key()['date'];
    $topics = [
        ['title' => 'tecnologia', 'keywords' => 'tecnologia inteligencia artificial internet software dados'],
        ['title' => 'politica', 'keywords' => 'politica governo eleicao congresso diplomacia'],
        ['title' => 'economia', 'keywords' => 'economia mercado juros inflacao empresas comercio'],
        ['title' => 'ciencia', 'keywords' => 'ciencia pesquisa espaco medicina clima energia'],
        ['title' => 'cultura', 'keywords' => 'cultura cinema musica literatura arte televisao'],
    ];

    foreach ($topics as $topic) {
        $stmt = db()->prepare(
            'INSERT INTO current_topics (run_date, title, keywords, source, created_at)
             VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$runDate, $topic['title'], $topic['keywords'], 'seed']);
    }

    return $topics;
}

function topics_for_date(string $runDate): array
{
    $stmt = db()->prepare('SELECT * FROM current_topics WHERE run_date = ? ORDER BY id ASC');
    $stmt->execute([$runDate]);

    return $stmt->fetchAll();
}
