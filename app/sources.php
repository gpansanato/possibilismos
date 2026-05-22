<?php

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
