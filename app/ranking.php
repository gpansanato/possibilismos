<?php

function run_daily_ranking(?string $runDate = null, ?int $limit = null): array
{
    $today = today_key();
    $runDate = $runDate ?: $today['date'];

    import_historical_events_for_today();
    collect_daily_context_topics($runDate);

    return apply_daily_priority_score($runDate, $limit);
}

function collect_daily_context_topics(?string $runDate = null): array
{
    $runDate = $runDate ?: today_key()['date'];
    db()->prepare('DELETE FROM current_topics WHERE run_date = ?')->execute([$runDate]);

    return current_topics_for_today($runDate);
}

function collect_daily_news_topics(?string $runDate = null): array
{
    $runDate = $runDate ?: today_key()['date'];

    return collect_news_topics_for_date($runDate);
}

function collect_daily_trend_topics(?string $runDate = null): array
{
    $runDate = $runDate ?: today_key()['date'];

    return collect_trend_topics_for_date($runDate);
}

function apply_daily_priority_score(?string $runDate = null, ?int $limit = null): array
{
    $runDate = $runDate ?: today_key()['date'];
    $dateParts = date_parts_from_run_date($runDate);

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO daily_runs (run_date, status, started_at)
             VALUES (?, "running", NOW())
             ON DUPLICATE KEY UPDATE status = "running", started_at = NOW(), finished_at = NULL'
        );
        $stmt->execute([$runDate]);

        rebuild_current_topics_from_collected_contexts($runDate);
        $topics = topics_for_date($runDate);
        if (!$topics) {
            $topics = collect_daily_context_topics($runDate);
        }

        $events = historical_events_for_day($dateParts['month'], $dateParts['day']);
        $ranked = rank_events($events, $topics, $dateParts['year']);
        $existingStatuses = ranking_statuses_for_date($runDate);
        $pdo->prepare('DELETE FROM daily_rankings WHERE run_date = ?')->execute([$runDate]);

        $insert = $pdo->prepare(
            'INSERT INTO daily_rankings
             (run_date, event_id, score, reasons, context_summary, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                score = VALUES(score),
                reasons = VALUES(reasons),
                context_summary = VALUES(context_summary),
                status = VALUES(status)'
        );

        foreach ($ranked as $item) {
            $insert->execute([
                $runDate,
                $item['event']['id'],
                $item['score'],
                implode('; ', $item['reasons']),
                $item['context_summary'],
                $existingStatuses[(int) $item['event']['id']] ?? 'suggested',
            ]);
        }

        $pdo->prepare('UPDATE daily_runs SET status = "done", finished_at = NOW() WHERE run_date = ?')
            ->execute([$runDate]);

        $pdo->commit();
        return $limit ? array_slice($ranked, 0, $limit) : $ranked;
    } catch (Throwable $e) {
        $pdo->rollBack();
        $stmt = $pdo->prepare('UPDATE daily_runs SET status = "failed", error_message = ?, finished_at = NOW() WHERE run_date = ?');
        $stmt->execute([$e->getMessage(), $runDate]);
        throw $e;
    }
}

function rank_events(array $events, array $topics, int $currentYear): array
{
    $ranked = [];
    $categoryCounts = [];
    $settings = scoring_settings();

    foreach ($events as $event) {
        $components = score_event_components($event, $topics, $currentYear, $settings);
        $category = (string) $event['category'];
        $diversityPenalty = min(
            (float) $settings['diversity_max'],
            ($categoryCounts[$category] ?? 0) * (float) $settings['diversity_penalty']
        );
        $components['diversity'] = -$diversityPenalty;
        $score = array_sum(array_filter($components, 'is_numeric'));
        $reasons = build_score_reasons($event, $components, $currentYear);
        $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;

        $ranked[] = [
            'event' => $event,
            'score' => $score,
            'reasons' => $reasons,
            'context_summary' => build_context_summary($event, $reasons),
            'components' => $components,
        ];
    }

    usort($ranked, fn ($a, $b) => $b['score'] <=> $a['score']);

    return $ranked;
}

function score_event_components(array $event, array $topics, int $currentYear, ?array $settings = null): array
{
    $settings = $settings ?: scoring_settings();
    $baseScore = max(0, min(100, (float) $event['base_score']));
    $components = [
        'historical' => $baseScore * (float) $settings['historical_weight'],
        'news' => 0.0,
        'trends' => 0.0,
        'anniversary' => 0.0,
        'category' => 0.0,
        'diversity' => 0.0,
    ];

    $anniversary = $currentYear - (int) $event['year'];
    if ($anniversary > 0) {
        $components['anniversary'] = anniversary_score($anniversary, $settings);
    }

    $match = event_context_match_score($event, $topics, $settings);
    $components['news'] = $match['news_score'];
    $components['trends'] = $match['trend_score'];
    $components['category'] = category_context_score((string) $event['category'], $topics, $settings);
    $components['_details'] = $match;

    return $components;
}

function anniversary_score(int $anniversary, array $settings): float
{
    if (in_array($anniversary, [50, 100, 150, 200, 250, 500], true)) {
        return (float) $settings['anniversary_major'];
    }

    if (in_array($anniversary, [10, 25, 75], true)) {
        return (float) $settings['anniversary_named'];
    }

    if ($anniversary % 100 === 0) {
        return (float) $settings['anniversary_major'];
    }

    if ($anniversary % 50 === 0) {
        return (float) $settings['anniversary_medium'];
    }

    if ($anniversary % 25 === 0) {
        return (float) $settings['anniversary_minor'];
    }

    return 0.0;
}

function event_context_match_score(array $event, array $topics, array $settings): array
{
    $eventText = normalize_score_text(
        $event['title'] . ' ' . $event['description'] . ' ' . $event['category'] . ' ' . $event['region']
    );
    $matchedNewsTopics = 0;
    $matchedNewsKeywords = [];
    $matchedNewsTitles = [];
    $matchedTrendTopics = 0;
    $matchedTrendKeywords = [];
    $matchedTrendTitles = [];

    foreach ($topics as $topic) {
        $keywords = topic_keywords($topic);
        $topicMatches = 0;

        foreach ($keywords as $keyword) {
            if (mb_strlen($keyword, 'UTF-8') < 4) {
                continue;
            }

            if (mb_strpos($eventText, $keyword, 0, 'UTF-8') !== false) {
                $topicMatches++;
                if (source_is_trend($topic['source'] ?? '')) {
                    $matchedTrendKeywords[$keyword] = true;
                } else {
                    $matchedNewsKeywords[$keyword] = true;
                }
            }
        }

        if ($topicMatches > 0) {
            if (source_is_trend($topic['source'] ?? '')) {
                $matchedTrendTopics++;
                $matchedTrendTitles[] = (string) ($topic['title'] ?? '');
            } else {
                $matchedNewsTopics++;
                $matchedNewsTitles[] = (string) ($topic['title'] ?? '');
            }
        }
    }

    $newsScore = min(
        (float) $settings['news_max'],
        ($matchedNewsTopics * (float) $settings['news_topic_points']) +
        (count($matchedNewsKeywords) * (float) $settings['news_keyword_points'])
    );
    $trendScore = min(
        (float) $settings['trend_max'],
        ($matchedTrendTopics * (float) $settings['trend_topic_points']) +
        (count($matchedTrendKeywords) * (float) $settings['trend_keyword_points'])
    );

    return [
        'news_score' => (float) $newsScore,
        'trend_score' => (float) $trendScore,
        'news_topics' => $matchedNewsTopics,
        'trend_topics' => $matchedTrendTopics,
        'news_keywords' => array_keys($matchedNewsKeywords),
        'trend_keywords' => array_keys($matchedTrendKeywords),
        'news_titles' => array_values(array_filter(array_unique($matchedNewsTitles))),
        'trend_titles' => array_values(array_filter(array_unique($matchedTrendTitles))),
    ];
}

function source_is_trend(string $source): bool
{
    return substr($source, 0, 6) === 'trend:';
}

function category_context_score(string $category, array $topics, array $settings): float
{
    $category = normalize_score_text($category);
    if ($category === '') {
        return 0.0;
    }

    $matches = 0;
    foreach ($topics as $topic) {
        $text = normalize_score_text($topic['title'] . ' ' . $topic['keywords']);
        if (mb_strpos($text, $category, 0, 'UTF-8') !== false) {
            $matches++;
        }
    }

    return (float) min(
        (float) $settings['category_max'],
        $matches * (float) $settings['category_points']
    );
}

function topic_keywords(array $topic): array
{
    $text = normalize_score_text($topic['keywords'] ?? '');
    $keywords = preg_split('/\s+/u', $text);

    return array_values(array_filter(array_unique($keywords)));
}

function normalize_score_text(string $text): string
{
    $text = mb_strtolower(strip_tags($text), 'UTF-8');
    $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);

    return trim(preg_replace('/\s+/u', ' ', (string) $text));
}

function build_score_reasons(array $event, array $components, int $currentYear): array
{
    $reasons = [];
    $details = is_array($components['_details'] ?? null) ? $components['_details'] : [];
    $reasons[] = 'relevância histórica base ' . number_format((float) $event['base_score'], 1) . ' com peso aplicado de ' . number_format((float) $components['historical'], 1);

    if ($components['news'] > 0) {
        $keywords = implode(', ', array_slice($details['news_keywords'] ?? [], 0, 6));
        $titles = implode(' | ', array_slice($details['news_titles'] ?? [], 0, 2));
        $reasons[] = 'conexão com ' . (int) ($details['news_topics'] ?? 0) . ' notícias por termos: ' . ($keywords ?: 'não informado') . ($titles ? ' em "' . $titles . '"' : '') . ' +' . number_format($components['news'], 1);
    }

    if ($components['trends'] > 0) {
        $keywords = implode(', ', array_slice($details['trend_keywords'] ?? [], 0, 6));
        $titles = implode(' | ', array_slice($details['trend_titles'] ?? [], 0, 2));
        $reasons[] = 'conexão com ' . (int) ($details['trend_topics'] ?? 0) . ' tendências por termos: ' . ($keywords ?: 'não informado') . ($titles ? ' em "' . $titles . '"' : '') . ' +' . number_format($components['trends'], 1);
    }

    if ($components['anniversary'] > 0) {
        $anniversary = $currentYear - (int) $event['year'];
        $reasons[] = 'aniversário de ' . $anniversary . ' anos';
    }

    if ($components['category'] > 0) {
        $reasons[] = 'categoria em pauta +' . number_format($components['category'], 1);
    }

    if ($components['diversity'] < 0) {
        $reasons[] = 'ajuste de diversidade ' . number_format($components['diversity'], 1);
    }

    return array_values(array_unique($reasons));
}

function build_context_summary(array $event, array $reasons): string
{
    return 'Priorizacao calculada por: ' . implode('; ', $reasons) . '.';
}

function scoring_setting_definitions(): array
{
    return [
        'historical_weight' => ['label' => 'Peso da relevância histórica', 'default' => 0.45],
        'news_topic_points' => ['label' => 'Pontos por notícia/tópico conectado', 'default' => 6.0],
        'news_keyword_points' => ['label' => 'Pontos por palavra-chave conectada', 'default' => 3.0],
        'news_max' => ['label' => 'Limite máximo de pontos por notícias', 'default' => 32.0],
        'trend_topic_points' => ['label' => 'Pontos por tendência conectada', 'default' => 8.0],
        'trend_keyword_points' => ['label' => 'Pontos por palavra-chave de tendência', 'default' => 4.0],
        'trend_max' => ['label' => 'Limite máximo de pontos por tendências', 'default' => 28.0],
        'anniversary_major' => ['label' => 'Bônus aniversário maior', 'default' => 18.0],
        'anniversary_medium' => ['label' => 'Bônus aniversário médio', 'default' => 14.0],
        'anniversary_minor' => ['label' => 'Bônus aniversário menor', 'default' => 8.0],
        'anniversary_named' => ['label' => 'Bônus aniversário 10/25/75 anos', 'default' => 10.0],
        'category_points' => ['label' => 'Pontos por categoria em pauta', 'default' => 4.0],
        'category_max' => ['label' => 'Limite máximo da categoria', 'default' => 12.0],
        'diversity_penalty' => ['label' => 'Penalidade por categoria repetida', 'default' => 4.0],
        'diversity_max' => ['label' => 'Penalidade máxima de diversidade', 'default' => 8.0],
    ];
}

function scoring_settings(): array
{
    $definitions = scoring_setting_definitions();
    $settings = [];

    foreach ($definitions as $key => $definition) {
        $settings[$key] = (float) $definition['default'];
    }

    $rows = db()->query('SELECT setting_key, setting_value FROM scoring_settings')->fetchAll();
    foreach ($rows as $row) {
        if (array_key_exists($row['setting_key'], $settings)) {
            $settings[$row['setting_key']] = (float) $row['setting_value'];
        }
    }

    return $settings;
}

function update_scoring_settings(array $input): void
{
    $definitions = scoring_setting_definitions();
    $stmt = db()->prepare(
        'INSERT INTO scoring_settings (setting_key, setting_value, updated_at)
         VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()'
    );

    foreach ($definitions as $key => $definition) {
        if (!isset($input[$key])) {
            continue;
        }

        $value = (float) str_replace(',', '.', (string) $input[$key]);
        $stmt->execute([$key, $value]);
    }
}

function date_parts_from_run_date(string $runDate): array
{
    $date = DateTimeImmutable::createFromFormat('Y-m-d', $runDate) ?: new DateTimeImmutable('now');

    return [
        'year' => (int) $date->format('Y'),
        'month' => (int) $date->format('m'),
        'day' => (int) $date->format('d'),
    ];
}

function ranking_statuses_for_date(string $runDate): array
{
    $stmt = db()->prepare('SELECT event_id, status FROM daily_rankings WHERE run_date = ?');
    $stmt->execute([$runDate]);

    $statuses = [];
    foreach ($stmt->fetchAll() as $row) {
        $statuses[(int) $row['event_id']] = (string) $row['status'];
    }

    return $statuses;
}
