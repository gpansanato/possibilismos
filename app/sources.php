<?php

function import_historical_events_for_today(): int
{
    $today = today_key();
    return import_historical_events_for_day($today['month'], $today['day'], $today['date']);
}

function import_historical_events_for_day(int $month, int $day, ?string $runDate = null): int
{
    $result = collect_historical_events_for_day($month, $day, $runDate);
    return (int) $result['imported'];
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

function http_get_json(string $url, string $userAgent, array $headers = []): ?string
{
    $requestHeaders = array_merge([
        'Accept: application/json, application/rss+xml, application/xml;q=0.9, */*;q=0.8',
        'Api-User-Agent: ' . $userAgent,
    ], $headers);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => $userAgent,
            CURLOPT_HTTPHEADER => $requestHeaders,
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
            'header' => implode("\r\n", array_merge(['User-Agent: ' . $userAgent], $requestHeaders)),
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    return is_string($body) ? $body : null;
}

function save_wikimedia_event(array $event, int $month, int $day, string $language, string $type, ?string $runDate = null): bool
{
    if (empty($event['text']) || !isset($event['year'])) {
        return false;
    }

    $year = (int) $event['year'];
    $description = trim(strip_tags((string) $event['text']));
    $title = make_event_title($description, $year);
    $sourceUrl = wikimedia_event_url($event);
    $pageTitle = (string) ($event['pages'][0]['title'] ?? '');
    $sourceEventId = mb_substr(
        'wikimedia-' . $language . '-' . $type . '-' . $month . '-' . $day . '-' . $year . '-' . normalize_context_key($pageTitle ?: $description),
        0,
        190,
        'UTF-8'
    );
    $region = infer_event_region($event, $language);
    $category = infer_event_category($description);
    $eventData = [
        'event_month' => $month,
        'event_day' => $day,
        'year' => $year,
        'title' => $title,
        'description' => $description,
        'category' => $category,
        'region' => $region,
        'source_url' => $sourceUrl,
        'canonical_id' => null,
        'canonical_source' => null,
        'canonical_title' => null,
        'base_score' => 0.00,
        'confidence_score' => 0.65,
    ];
    $source = [
        'source' => 'Wikipedia / Wikimedia',
        'source_event_id' => $sourceEventId,
        'source_url' => $sourceUrl,
        'title' => $title,
        'description' => $description,
        'language' => $language,
        'confidence_score' => 0.65,
    ];
    $import = [
        'run_date' => $runDate ?: historical_import_run_date($month, $day),
        'source' => 'Wikipedia / Wikimedia',
        'source_type' => $type,
        'source_event_id' => $sourceEventId,
        'source_url' => $sourceUrl,
        'event_month' => $month,
        'event_day' => $day,
        'event_year' => $year,
        'raw_title' => $title,
        'raw_description' => $description,
        'raw_category' => $category,
        'raw_location' => $region,
        'raw_language' => $language,
        'raw_payload' => $event,
        'normalized_key' => build_event_key($eventData),
        'status' => 'normalized',
    ];

    $eventId = persist_normalized_historical_event($eventData, $source, $import);
    if ($eventId <= 0) {
        return false;
    }

    save_event_enrichment($eventId, [
        'source' => 'Wikipedia / Wikimedia',
        'role' => 'context',
        'title' => $title,
        'description' => $description,
        'source_url' => $sourceUrl,
        'external_id' => $sourceEventId,
        'metadata' => $event,
    ]);
    enrich_historical_event($eventId, ['article' => ['value' => $sourceUrl]]);

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
    $newsTopics = fetch_current_news_topics($runDate);
    $trendTopics = fetch_current_trend_topics($runDate);
    if (!$trendTopics) {
        $trendTopics = derive_trend_topics_from_news($runDate, 20);
    }

    $topics = array_merge($newsTopics, $trendTopics);

    if (!$topics) {
        $topics = fallback_current_topics();
    }

    persist_context_topics($runDate, $topics);

    return $topics;
}

function collect_news_topics_for_date(string $runDate): array
{
    db()->prepare('DELETE FROM current_topics WHERE run_date = ? AND source LIKE "rss:%"')->execute([$runDate]);
    $topics = fetch_current_news_topics($runDate);

    foreach ($topics as $topic) {
        save_collected_context($runDate, 'news', $topic);
    }

    $contexts = collected_contexts_for_date($runDate, 'news');
    rebuild_current_topics_from_collected_contexts($runDate, 'news');

    return $contexts;
}

function collect_trend_topics_for_date(string $runDate): array
{
    db()->prepare('DELETE FROM current_topics WHERE run_date = ? AND source LIKE "trend:%"')->execute([$runDate]);
    $topics = fetch_current_trend_topics($runDate);
    if (!$topics) {
        $topics = derive_trend_topics_from_news($runDate, 20);
    }

    foreach ($topics as $topic) {
        save_collected_context($runDate, 'trend', $topic);
    }

    $contexts = collected_contexts_for_date($runDate, 'trend');
    rebuild_current_topics_from_collected_contexts($runDate, 'trend');

    return $contexts;
}

function persist_context_topics(string $runDate, array $topics): void
{
    foreach ($topics as $topic) {
        $type = strpos((string) ($topic['source'] ?? ''), 'trend:') === 0 ? 'trend' : 'news';
        save_collected_context($runDate, $type, $topic);
    }

    rebuild_current_topics_from_collected_contexts($runDate);
}

function rebuild_current_topics_from_collected_contexts(string $runDate, ?string $type = null): void
{
    if ($type === 'news') {
        db()->prepare('DELETE FROM current_topics WHERE run_date = ? AND source LIKE "rss:%"')->execute([$runDate]);
    } elseif ($type === 'trend') {
        db()->prepare('DELETE FROM current_topics WHERE run_date = ? AND source LIKE "trend:%"')->execute([$runDate]);
    } else {
        db()->prepare('DELETE FROM current_topics WHERE run_date = ?')->execute([$runDate]);
    }

    foreach (collected_contexts_for_date($runDate, $type) as $context) {
        save_current_topic($runDate, [
            'title' => $context['title'],
            'keywords' => $context['keywords'],
            'source' => $context['source'],
        ]);
    }
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

function collected_context_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM collected_contexts WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $context = $stmt->fetch();

    return $context ?: null;
}

function collected_contexts_search(string $runDate, ?string $type, string $source, string $search, string $sort): array
{
    $allowedSorts = [
        'updated_desc' => 'updated_at DESC, id DESC',
        'updated_asc' => 'updated_at ASC, id ASC',
        'date_desc' => 'run_date DESC, updated_at DESC, id DESC',
        'date_asc' => 'run_date ASC, updated_at ASC, id ASC',
        'type' => 'context_type ASC, source ASC, title ASC',
        'source' => 'source ASC, context_type ASC, title ASC',
    ];
    if (!isset($allowedSorts[$sort])) {
        $sort = 'updated_desc';
    }

    $where = [];
    $params = [];

    if ($runDate !== '') {
        $where[] = 'run_date = ?';
        $params[] = $runDate;
    }

    if ($type !== null) {
        $where[] = 'context_type = ?';
        $params[] = $type;
    }

    if ($source !== '') {
        $where[] = 'source LIKE ?';
        $params[] = '%' . $source . '%';
    }

    if ($search !== '') {
        $where[] = '(title LIKE ? OR keywords LIKE ? OR raw_text LIKE ? OR source LIKE ?)';
        $term = '%' . $search . '%';
        array_push($params, $term, $term, $term, $term);
    }

    $sql = 'SELECT * FROM collected_contexts';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY ' . $allowedSorts[$sort];

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

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
    $userAgent = $config['sources']['wikimedia']['user_agent'] ?? 'PossibilismosMVP/0.1';

    foreach ($feeds as $feed) {
        if (count($topics) >= $maxItems) {
            break;
        }

        $body = fetch_feed_body($feed, $userAgent);
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
                'source' => 'trend:' . ($feed['name'] ?? 'rss-feed'),
                'raw_text' => $text,
                'source_url' => $item['link'] ?? null,
            ];
        }
    }

    $providers = [
        fetch_gdelt_trend_topics($runDate, $settings['gdelt'] ?? [], $userAgent),
        fetch_media_cloud_trend_topics($runDate, $settings['media_cloud'] ?? [], $userAgent),
        fetch_wikimedia_pageview_trend_topics($runDate, $settings['wikimedia_pageviews'] ?? [], $userAgent),
        fetch_agencia_brasil_trend_topics($runDate, $settings['agencia_brasil'] ?? [], $userAgent),
        fetch_hacker_news_trend_topics($runDate, $settings['hacker_news'] ?? [], $userAgent),
    ];

    foreach ($providers as $providerTopics) {
        foreach ($providerTopics as $topic) {
            if (count($topics) >= $maxItems) {
                break 2;
            }

            $topics[] = $topic;
        }
    }

    return $topics;
}

function fetch_gdelt_trend_topics(string $runDate, array $settings, string $userAgent): array
{
    if (empty($settings['enabled'])) {
        return [];
    }

    $start = str_replace('-', '', $runDate) . '000000';
    $end = str_replace('-', '', $runDate) . '235959';
    $url = ($settings['url'] ?? 'https://api.gdeltproject.org/api/v2/doc/doc') . '?' . http_build_query([
        'query' => $settings['query'] ?? 'brasil OR brazil',
        'mode' => 'ArtList',
        'format' => 'json',
        'maxrecords' => (int) ($settings['max_items'] ?? 10),
        'sort' => 'HybridRel',
        'startdatetime' => $start,
        'enddatetime' => $end,
    ]);

    $body = http_get_json($url, $userAgent);
    $data = $body ? json_decode($body, true) : null;
    if (!is_array($data) || empty($data['articles']) || !is_array($data['articles'])) {
        return [];
    }

    $topics = [];
    foreach ($data['articles'] as $article) {
        $title = clean_context_text($article['title'] ?? '');
        if ($title === '') {
            continue;
        }

        $domain = clean_context_text($article['domain'] ?? '');
        $text = trim($title . ' ' . $domain);
        $topics[] = [
            'title' => $title,
            'keywords' => context_keywords_from_text($text, 4),
            'source' => 'trend:gdelt',
            'raw_text' => $text,
            'source_url' => $article['url'] ?? null,
        ];
    }

    return $topics;
}

function fetch_media_cloud_trend_topics(string $runDate, array $settings, string $userAgent): array
{
    if (empty($settings['enabled']) || empty($settings['url'])) {
        return [];
    }

    $query = [
        'q' => $settings['query'] ?? 'brasil OR brazil',
        'query' => $settings['query'] ?? 'brasil OR brazil',
        'start_date' => $runDate,
        'end_date' => $runDate,
        'page_size' => (int) ($settings['max_items'] ?? 10),
        'limit' => (int) ($settings['max_items'] ?? 10),
    ];
    $headers = [];
    if (!empty($settings['api_key'])) {
        $headers[] = 'Authorization: Bearer ' . $settings['api_key'];
    }

    $separator = strpos($settings['url'], '?') === false ? '?' : '&';
    $body = http_get_json($settings['url'] . $separator . http_build_query($query), $userAgent, $headers);
    $data = $body ? json_decode($body, true) : null;
    if (!is_array($data)) {
        return [];
    }

    $stories = $data['stories'] ?? $data['results'] ?? $data['data'] ?? [];
    if (!is_array($stories)) {
        return [];
    }

    $topics = [];
    foreach ($stories as $story) {
        $title = clean_context_text($story['title'] ?? $story['name'] ?? '');
        if ($title === '') {
            continue;
        }

        $media = clean_context_text($story['media_name'] ?? $story['media'] ?? $story['domain'] ?? '');
        $text = trim($title . ' ' . $media);
        $topics[] = [
            'title' => $title,
            'keywords' => context_keywords_from_text($text, 4),
            'source' => 'trend:media-cloud',
            'raw_text' => $text,
            'source_url' => $story['url'] ?? $story['story_url'] ?? null,
        ];
    }

    return $topics;
}

function fetch_wikimedia_pageview_trend_topics(string $runDate, array $settings, string $userAgent): array
{
    if (empty($settings['enabled'])) {
        return [];
    }

    $topics = [];
    $maxItems = (int) ($settings['max_items'] ?? 12);
    $projects = $settings['projects'] ?? ['pt.wikipedia'];
    $date = wikimedia_pageviews_reference_date($runDate);

    foreach ($projects as $project) {
        if (count($topics) >= $maxItems) {
            break;
        }

        $url = rtrim($settings['url'] ?? 'https://wikimedia.org/api/rest_v1/metrics/pageviews/top', '/')
            . '/' . rawurlencode((string) $project)
            . '/all-access/'
            . $date->format('Y/m/d');

        $body = http_get_json($url, $userAgent);
        $data = $body ? json_decode($body, true) : null;
        $articles = $data['items'][0]['articles'] ?? [];
        if (!is_array($articles)) {
            continue;
        }

        foreach ($articles as $article) {
            if (count($topics) >= $maxItems) {
                break 2;
            }

            $articleName = (string) ($article['article'] ?? '');
            if ($articleName === '' || strpos($articleName, ':') !== false || $articleName === 'Main_Page') {
                continue;
            }

            $title = str_replace('_', ' ', $articleName);
            $views = (int) ($article['views'] ?? 0);
            $topics[] = [
                'title' => $title,
                'keywords' => context_keywords_from_text($title, 3),
                'source' => 'trend:wikimedia-pageviews:' . $project,
                'raw_text' => $title . ' pageviews ' . $views,
                'source_url' => 'https://' . $project . '.org/wiki/' . rawurlencode($articleName),
            ];
        }
    }

    return $topics;
}

function wikimedia_pageviews_reference_date(string $runDate): DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('Y-m-d', $runDate) ?: new DateTimeImmutable('today');
    return $date->modify('-1 day');
}

function fetch_agencia_brasil_trend_topics(string $runDate, array $settings, string $userAgent): array
{
    if (empty($settings['enabled'])) {
        return [];
    }

    $topics = [];
    $maxItems = (int) ($settings['max_items'] ?? 10);
    foreach (($settings['feeds'] ?? []) as $feed) {
        if (count($topics) >= $maxItems) {
            break;
        }

        $body = fetch_feed_body($feed, $userAgent);
        if (!$body) {
            continue;
        }

        foreach (parse_rss_items($body) as $item) {
            if (count($topics) >= $maxItems) {
                break 2;
            }

            $text = trim($item['title'] . ' ' . $item['description']);
            $topics[] = [
                'title' => $item['title'],
                'keywords' => context_keywords_from_text($text, 4),
                'source' => 'trend:agencia-brasil',
                'raw_text' => $text,
                'source_url' => $item['link'] ?? null,
            ];
        }
    }

    return $topics;
}

function fetch_hacker_news_trend_topics(string $runDate, array $settings, string $userAgent): array
{
    if (empty($settings['enabled'])) {
        return [];
    }

    $body = http_get_json($settings['list_url'] ?? 'https://hacker-news.firebaseio.com/v0/topstories.json', $userAgent);
    $ids = $body ? json_decode($body, true) : null;
    if (!is_array($ids)) {
        return [];
    }

    $topics = [];
    $maxItems = (int) ($settings['max_items'] ?? 12);
    $itemUrl = $settings['item_url'] ?? 'https://hacker-news.firebaseio.com/v0/item/%d.json';

    foreach (array_slice($ids, 0, $maxItems * 2) as $id) {
        if (count($topics) >= $maxItems) {
            break;
        }

        $itemBody = http_get_json(sprintf($itemUrl, (int) $id), $userAgent);
        $item = $itemBody ? json_decode($itemBody, true) : null;
        if (!is_array($item) || ($item['type'] ?? '') !== 'story') {
            continue;
        }

        $title = clean_context_text($item['title'] ?? '');
        if ($title === '') {
            continue;
        }

        $score = (int) ($item['score'] ?? 0);
        $comments = (int) ($item['descendants'] ?? 0);
        $topics[] = [
            'title' => $title,
            'keywords' => context_keywords_from_text($title, 3),
            'source' => 'trend:hacker-news',
            'raw_text' => $title . ' score ' . $score . ' comments ' . $comments,
            'source_url' => $item['url'] ?? ('https://news.ycombinator.com/item?id=' . (int) $id),
        ];
    }

    return $topics;
}

function parse_rss_items(string $body): array
{
    if (!function_exists('simplexml_load_string')) {
        return [];
    }

    $xml = @simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
    if (!$xml) {
        return [];
    }

    $items = [];

    if (!empty($xml->channel->item)) {
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
    }

    if (!$items && !empty($xml->entry)) {
        foreach ($xml->entry as $entry) {
            $title = trim(strip_tags((string) $entry->title));
            if ($title === '') {
                continue;
            }

            $link = '';
            foreach ($entry->link as $entryLink) {
                $attributes = $entryLink->attributes();
                if (!empty($attributes['href'])) {
                    $link = (string) $attributes['href'];
                    break;
                }
            }

            $items[] = [
                'title' => $title,
                'description' => trim(strip_tags((string) ($entry->summary ?: $entry->content))),
                'link' => $link,
            ];
        }
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

function context_keywords_from_text(string $text, int $minLength): string
{
    $keywords = extract_news_keywords($text, $minLength);
    if ($keywords) {
        return implode(' ', $keywords);
    }

    return normalize_context_key($text);
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
