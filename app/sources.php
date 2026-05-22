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
                'Accept: application/json, application/rss+xml, application/xml;q=0.9, */*;q=0.8',
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
                'Accept: application/json, application/rss+xml, application/xml;q=0.9, */*;q=0.8',
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
    $topics = array_merge(
        fetch_current_news_topics($runDate),
        fetch_current_trend_topics($runDate)
    );

    if (!$topics) {
        $topics = fallback_current_topics();
    }

    foreach ($topics as $topic) {
        save_current_topic($runDate, $topic);
    }

    return $topics;
}

function collect_news_topics_for_date(string $runDate): array
{
    db()->prepare('DELETE FROM current_topics WHERE run_date = ? AND source LIKE "rss:%"')->execute([$runDate]);
    $topics = fetch_current_news_topics($runDate);

    foreach ($topics as $topic) {
        save_collected_context($runDate, 'news', $topic);
        save_current_topic($runDate, $topic);
    }

    return collected_contexts_for_date($runDate, 'news');
}

function collect_trend_topics_for_date(string $runDate): array
{
    db()->prepare('DELETE FROM current_topics WHERE run_date = ? AND source LIKE "trend:%"')->execute([$runDate]);
    $topics = fetch_current_trend_topics($runDate);

    foreach ($topics as $topic) {
        save_collected_context($runDate, 'trend', $topic);
        save_current_topic($runDate, $topic);
    }

    return collected_contexts_for_date($runDate, 'trend');
}

function save_current_topic(string $runDate, array $topic): void
{
    $stmt = db()->prepare(
        'INSERT INTO current_topics (run_date, title, keywords, source, created_at)
         VALUES (?, ?, ?, ?, NOW())'
    );
    $stmt->execute([
        $runDate,
        $topic['title'],
        $topic['keywords'],
        $topic['source'],
    ]);
}

function save_collected_context(string $runDate, string $type, array $topic): void
{
    $title = clean_context_text($topic['title'] ?? '');
    if ($title === '') {
        return;
    }

    $rawText = clean_context_text($topic['raw_text'] ?? $title);
    $keywords = clean_context_text($topic['keywords'] ?? '');
    $source = clean_context_text($topic['source'] ?? $type);
    $sourceUrl = $topic['source_url'] ?? null;
    $normalizedTitle = normalize_context_key($title);

    $stmt = db()->prepare(
        'INSERT INTO collected_contexts
         (run_date, context_type, source, title, normalized_title, keywords, raw_text, source_url, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            keywords = VALUES(keywords),
            raw_text = VALUES(raw_text),
            source_url = VALUES(source_url),
            updated_at = NOW()'
    );
    $stmt->execute([
        $runDate,
        $type,
        $source,
        mb_substr($title, 0, 255, 'UTF-8'),
        mb_substr($normalizedTitle, 0, 255, 'UTF-8'),
        $keywords,
        $rawText,
        $sourceUrl,
    ]);
}

function collected_contexts_for_date(string $runDate, ?string $type = null): array
{
    if ($type) {
        $stmt = db()->prepare(
            'SELECT * FROM collected_contexts WHERE run_date = ? AND context_type = ? ORDER BY updated_at DESC, id DESC'
        );
        $stmt->execute([$runDate, $type]);
    } else {
        $stmt = db()->prepare(
            'SELECT * FROM collected_contexts WHERE run_date = ? ORDER BY context_type, updated_at DESC, id DESC'
        );
        $stmt->execute([$runDate]);
    }

    return $stmt->fetchAll();
}

function collected_contexts_count_for_date(string $runDate, ?string $type = null): int
{
    if ($type) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM collected_contexts WHERE run_date = ? AND context_type = ?');
        $stmt->execute([$runDate, $type]);
    } else {
        $stmt = db()->prepare('SELECT COUNT(*) FROM collected_contexts WHERE run_date = ?');
        $stmt->execute([$runDate]);
    }

    return (int) $stmt->fetchColumn();
}

function clean_context_text(string $text): string
{
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8');
    return trim(preg_replace('/\s+/u', ' ', $text));
}

function normalize_context_key(string $text): string
{
    $text = normalize_score_text($text);
    return trim((string) $text);
}

function fetch_current_news_topics(string $runDate): array
{
    $config = require __DIR__ . '/config.php';
    $settings = $config['sources']['news'] ?? [];

    if (empty($settings['enabled'])) {
        return [];
    }

    $topics = [];
    $maxItems = (int) ($settings['max_items'] ?? 30);
    $feeds = $settings['feeds'] ?? [];

    foreach ($feeds as $feed) {
        if (count($topics) >= $maxItems) {
            break;
        }

        $body = fetch_feed_body($feed, $config['sources']['wikimedia']['user_agent'] ?? 'PossibilismosMVP/0.1');
        if (!$body) {
            continue;
        }

        foreach (parse_rss_items($body) as $item) {
            if (count($topics) >= $maxItems) {
                break 2;
            }

            $text = trim($item['title'] . ' ' . $item['description']);
            $keywords = extract_news_keywords($text, (int) ($settings['min_keyword_length'] ?? 4));
            if (!$keywords) {
                continue;
            }

            $topics[] = [
                'title' => $item['title'],
                'keywords' => implode(' ', $keywords),
                'source' => 'rss:' . ($feed['name'] ?? 'news'),
                'raw_text' => $text,
                'source_url' => $item['link'] ?? null,
            ];
        }
    }

    return $topics;
}

function fetch_feed_body(array $feed, string $userAgent): ?string
{
    $urls = [];
    if (!empty($feed['url'])) {
        $urls[] = $feed['url'];
    }
    if (!empty($feed['fallback_url'])) {
        $urls[] = $feed['fallback_url'];
    }

    foreach ($urls as $url) {
        $body = http_get_json($url, $userAgent);
        if ($body) {
            return $body;
        }
    }

    return null;
}

function derive_trend_topics_from_news(string $runDate, int $maxItems): array
{
    $newsTopics = topics_for_date($runDate);
    $newsTopics = array_values(array_filter($newsTopics, function ($topic) {
        return strpos((string) $topic['source'], 'rss:') === 0;
    }));

    if (!$newsTopics) {
        $newsTopics = fetch_current_news_topics($runDate);
    }

    $counts = [];
    foreach ($newsTopics as $topic) {
        foreach (preg_split('/\s+/u', normalize_score_text($topic['keywords'] ?? '')) as $keyword) {
            if (mb_strlen($keyword, 'UTF-8') < 4) {
                continue;
            }
            $counts[$keyword] = ($counts[$keyword] ?? 0) + 1;
        }
    }

    arsort($counts);
    $topics = [];
    foreach (array_slice(array_keys($counts), 0, $maxItems) as $keyword) {
            $topics[] = [
                'title' => 'Tendencia: ' . $keyword,
                'keywords' => $keyword,
                'source' => 'trend:derived-news',
                'raw_text' => $keyword,
                'source_url' => null,
            ];
        }

    return $topics;
}

function fetch_current_trend_topics(string $runDate): array
{
    $config = require __DIR__ . '/config.php';
    $settings = $config['sources']['trends'] ?? [];

    if (empty($settings['enabled'])) {
        return [];
    }

    $topics = [];
    $maxItems = (int) ($settings['max_items'] ?? 20);
    $feeds = $settings['feeds'] ?? [];

    foreach ($feeds as $feed) {
        if (count($topics) >= $maxItems) {
            break;
        }

        $body = http_get_json($feed['url'], $config['sources']['wikimedia']['user_agent'] ?? 'PossibilismosMVP/0.1');
        if (!$body) {
            continue;
        }

        foreach (parse_rss_items($body) as $item) {
            if (count($topics) >= $maxItems) {
                break 2;
            }

            $text = trim($item['title'] . ' ' . $item['description']);
            $keywords = extract_news_keywords($text, (int) ($settings['min_keyword_length'] ?? 3));
            if (!$keywords) {
                $keywords = [normalize_score_text($item['title'])];
            }

            $topics[] = [
                'title' => $item['title'],
                'keywords' => implode(' ', $keywords),
                'source' => 'trend:' . ($feed['name'] ?? 'Google Trends'),
                'raw_text' => $text,
                'source_url' => $item['link'] ?? null,
            ];
        }
    }

    return $topics;
}

function parse_rss_items(string $body): array
{
    if (!function_exists('simplexml_load_string')) {
        return [];
    }

    $xml = @simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
    if (!$xml || empty($xml->channel->item)) {
        return [];
    }

    $items = [];
    foreach ($xml->channel->item as $item) {
        $title = trim(strip_tags((string) $item->title));
        if ($title === '') {
            continue;
        }

        $items[] = [
            'title' => $title,
            'description' => trim(strip_tags((string) $item->description)),
            'link' => trim((string) $item->link),
        ];
    }

    return $items;
}

function extract_news_keywords(string $text, int $minLength): array
{
    $text = mb_strtolower(strip_tags($text), 'UTF-8');
    $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
    $words = preg_split('/\s+/u', (string) $text);
    $stopwords = news_stopwords();
    $counts = [];

    foreach ($words as $word) {
        $word = trim($word);
        if (mb_strlen($word, 'UTF-8') < $minLength || isset($stopwords[$word])) {
            continue;
        }

        $counts[$word] = ($counts[$word] ?? 0) + 1;
    }

    arsort($counts);

    return array_slice(array_keys($counts), 0, 12);
}

function news_stopwords(): array
{
    $words = [
        'para', 'pela', 'pelo', 'pelos', 'pelas', 'como', 'mais', 'menos', 'sobre',
        'entre', 'contra', 'apos', 'antes', 'ainda', 'isso', 'essa', 'esse', 'esta',
        'este', 'sera', 'foram', 'pela', 'pode', 'onde', 'quando', 'quem', 'porque',
        'brasil', 'google', 'news', 'noticias', 'veja', 'diz', 'tem', 'ter', 'com',
        'uma', 'dos', 'das', 'que', 'por', 'nas', 'nos', 'aos', 'a', 'o', 'e',
    ];

    return array_fill_keys($words, true);
}

function fallback_current_topics(): array
{
    return [
        ['title' => 'tecnologia', 'keywords' => 'tecnologia inteligencia artificial internet software dados', 'source' => 'seed'],
        ['title' => 'politica', 'keywords' => 'politica governo eleicao congresso diplomacia', 'source' => 'seed'],
        ['title' => 'economia', 'keywords' => 'economia mercado juros inflacao empresas comercio', 'source' => 'seed'],
        ['title' => 'ciencia', 'keywords' => 'ciencia pesquisa espaco medicina clima energia', 'source' => 'seed'],
        ['title' => 'cultura', 'keywords' => 'cultura cinema musica literatura arte televisao', 'source' => 'seed'],
    ];
}

function topics_for_date(string $runDate): array
{
    $stmt = db()->prepare('SELECT * FROM current_topics WHERE run_date = ? ORDER BY id ASC');
    $stmt->execute([$runDate]);

    return $stmt->fetchAll();
}
