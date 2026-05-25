<?php
require_once __DIR__ . '/../app/bootstrap.php';

$today = today_key();
$date = $_GET['date'] ?? $today['date'];
if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = $today['date'];
}

$selectedDate = DateTimeImmutable::createFromFormat('Y-m-d', $date) ?: new DateTimeImmutable($today['date']);
$date = $selectedDate->format('Y-m-d');
$calendarYear = (int) $selectedDate->format('Y');
$calendarMonth = (int) $selectedDate->format('m');
$calendarStart = $selectedDate->modify('first day of this month');
$calendarEnd = $selectedDate->modify('last day of this month');
$calendarCounts = public_context_counts_for_month($calendarYear, $calendarMonth);
$calendarOffset = (int) $calendarStart->format('w');
$calendarDays = (int) $calendarEnd->format('j');
$previousMonthDate = $calendarStart->modify('-1 month')->format('Y-m-01');
$nextMonthDate = $calendarStart->modify('+1 month')->format('Y-m-01');

$selectedType = $_GET['type'] ?? '';
if (!in_array($selectedType, ['', 'news', 'trend'], true)) {
    $selectedType = '';
}
$source = trim($_GET['source'] ?? '');
$search = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'updated_desc';
$items = collected_contexts_search($date, $selectedType !== '' ? $selectedType : null, $source, $search, $sort);
$sourceOptions = public_context_source_options($date);
$allItems = collected_contexts_for_date($date);
$contextStats = public_context_stats($allItems);

$dateLabel = $selectedDate->format('d/m/Y');
$itemLabel = count($items) === 1
    ? '1 contexto coletado em ' . $dateLabel
    : count($items) . ' contextos coletados em ' . $dateLabel;

render_page_start('Contextos coletados', 'contexts', 'public', 'Noticias e tendencias usadas como insumos de contexto para a curadoria editorial.', true);
?>
    <section class="public-events-layout">
        <aside class="calendar-panel" aria-label="Calendario de contextos coletados">
            <div class="calendar-panel__head">
                <a class="button button-secondary" href="/contextos.php?date=<?= h($previousMonthDate) ?>">Anterior</a>
                <div>
                    <span class="eyebrow">Calendario</span>
                    <h2><?= h($calendarStart->format('m/Y')) ?></h2>
                </div>
                <a class="button button-secondary" href="/contextos.php?date=<?= h($nextMonthDate) ?>">Proximo</a>
            </div>
            <div class="calendar-grid" role="grid">
                <?php foreach (['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'] as $weekday): ?>
                    <span class="calendar-weekday"><?= h($weekday) ?></span>
                <?php endforeach; ?>
                <?php for ($i = 0; $i < $calendarOffset; $i++): ?>
                    <span class="calendar-day is-empty"></span>
                <?php endfor; ?>
                <?php for ($day = 1; $day <= $calendarDays; $day++): ?>
                    <?php
                    $dayDate = sprintf('%04d-%02d-%02d', $calendarYear, $calendarMonth, $day);
                    $count = $calendarCounts[$dayDate] ?? 0;
                    $classes = ['calendar-day'];
                    if ($dayDate === $date) {
                        $classes[] = 'is-selected';
                    }
                    if ($dayDate === $today['date']) {
                        $classes[] = 'is-today';
                    }
                    if ($count > 0) {
                        $classes[] = 'has-items';
                    }
                    ?>
                    <a class="<?= h(implode(' ', $classes)) ?>" href="/contextos.php?date=<?= h($dayDate) ?>" aria-label="<?= h($dayDate . ' com ' . $count . ' contextos coletados') ?>">
                        <strong><?= h((string) $day) ?></strong>
                        <span><?= h((string) $count) ?></span>
                    </a>
                <?php endfor; ?>
            </div>
            <p class="calendar-note">Cada numero indica quantos contextos foram coletados e higienizados naquela data.</p>
        </aside>

        <section class="published-events">
            <div class="section-heading section-heading--compact">
                <div>
                    <?php component_badge($dateLabel); ?>
                    <h2><?= h($itemLabel) ?></h2>
                    <p>Consulte os sinais de contexto que apoiam a priorizacao editorial dos fatos historicos.</p>
                </div>
                <a class="button button-secondary" href="/contextos.php">Hoje</a>
            </div>

            <section class="context-summary-grid" aria-label="Resumo dos contextos coletados">
                <article class="context-summary-card">
                    <span class="eyebrow">Noticias</span>
                    <strong><?= h((string) $contextStats['news']) ?></strong>
                    <p>Itens noticiosos usados como sinal editorial do dia.</p>
                </article>
                <article class="context-summary-card">
                    <span class="eyebrow">Tendencias</span>
                    <strong><?= h((string) $contextStats['trends']) ?></strong>
                    <p>Sinais tematicos coletados ou derivados das fontes configuradas.</p>
                </article>
                <article class="context-summary-card">
                    <span class="eyebrow">Fontes</span>
                    <strong><?= h((string) $contextStats['source_count']) ?></strong>
                    <p>Origem dos sinais usados para compor o contexto editorial.</p>
                </article>
            </section>

            <?php if ($contextStats['keywords'] || $contextStats['sources']): ?>
                <section class="context-insights panel">
                    <div>
                        <span class="eyebrow">Termos recorrentes</span>
                        <div class="context-keywords">
                            <?php foreach ($contextStats['keywords'] as $keyword => $count): ?>
                                <span class="context-keyword"><?= h($keyword) ?> <small><?= h((string) $count) ?></small></span>
                            <?php endforeach; ?>
                            <?php if (!$contextStats['keywords']): ?><p>Sem termos recorrentes suficientes.</p><?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <span class="eyebrow">Fontes mais presentes</span>
                        <div class="context-keywords">
                            <?php foreach ($contextStats['sources'] as $sourceName => $count): ?>
                                <span class="context-keyword"><?= h(public_display_label($sourceName)) ?> <small><?= h((string) $count) ?></small></span>
                            <?php endforeach; ?>
                            <?php if (!$contextStats['sources']): ?><p>Sem fonte registrada.</p><?php endif; ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <form class="filter-form public-filter" method="get" aria-label="Filtros de contextos coletados">
                <label>Data <input type="date" name="date" value="<?= h($date) ?>"></label>
                <label>Tipo
                    <select name="type">
                        <option value="">Todos</option>
                        <option value="news" <?= $selectedType === 'news' ? 'selected' : '' ?>>Noticias</option>
                        <option value="trend" <?= $selectedType === 'trend' ? 'selected' : '' ?>>Tendencias</option>
                    </select>
                </label>
                <label>Fonte
                    <select name="source">
                        <option value="">Todas</option>
                        <?php foreach ($sourceOptions as $option): ?>
                            <option value="<?= h($option) ?>" <?= $source === $option ? 'selected' : '' ?>><?= h(public_display_label($option)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Ordenar
                    <select name="sort">
                        <option value="updated_desc" <?= $sort === 'updated_desc' ? 'selected' : '' ?>>Mais recentes</option>
                        <option value="updated_asc" <?= $sort === 'updated_asc' ? 'selected' : '' ?>>Mais antigos</option>
                        <option value="type" <?= $sort === 'type' ? 'selected' : '' ?>>Tipo</option>
                        <option value="source" <?= $sort === 'source' ? 'selected' : '' ?>>Fonte</option>
                    </select>
                </label>
                <label>Busca <input name="q" value="<?= h($search) ?>" placeholder="Tema, fonte ou palavra-chave"></label>
                <button type="submit">Aplicar</button>
                <a class="button button-secondary" href="/contextos.php?date=<?= h($date) ?>">Limpar</a>
            </form>

            <?php if (!$items): ?>
                <section class="empty">
                    <h2>Nenhum contexto coletado para esta data.</h2>
                    <p>Use o calendario para navegar por outras datas ou execute a coleta de contexto na area administrativa.</p>
                </section>
            <?php endif; ?>

            <section class="published-list context-list" aria-label="Lista de contextos coletados">
                <?php foreach ($items as $item): ?>
                    <article class="published-card context-public-card">
                        <div class="published-card__body">
                            <div class="context-card__top">
                                <span class="status-badge <?= $item['context_type'] === 'news' ? 'is-approved' : 'is-pending' ?>"><?= h(public_context_type_label($item['context_type'])) ?></span>
                                <span class="context-source"><?= h(public_display_label($item['source'])) ?></span>
                            </div>
                            <h3><?= h($item['title']) ?></h3>
                            <p class="published-card__summary"><?= h(public_context_excerpt($item)) ?></p>
                            <?php if (trim((string) $item['keywords']) !== ''): ?>
                                <div class="context-keywords context-keywords--card">
                                    <?php foreach (public_context_keyword_list((string) $item['keywords'], 6) as $keyword): ?>
                                        <span class="context-keyword"><?= h($keyword) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <div class="meta">
                                <span>Coleta <?= h($item['run_date']) ?></span>
                                <span><?= h(public_context_signal_label($item)) ?></span>
                            </div>
                            <?php if ($item['source_url']): ?>
                                <div class="published-card__actions">
                                    <a class="button button-secondary" href="<?= h($item['source_url']) ?>" target="_blank" rel="noopener">Abrir fonte</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        </section>
    </section>
<?php render_page_end(); ?>

<?php
function public_context_counts_for_month(int $year, int $month): array
{
    $start = sprintf('%04d-%02d-01', $year, $month);
    $end = (new DateTimeImmutable($start))->modify('last day of this month')->format('Y-m-d');
    $stmt = db()->prepare(
        'SELECT run_date, COUNT(*) AS total
         FROM collected_contexts
         WHERE run_date BETWEEN ? AND ?
         GROUP BY run_date'
    );
    $stmt->execute([$start, $end]);

    $counts = [];
    foreach ($stmt->fetchAll() as $row) {
        $counts[$row['run_date']] = (int) $row['total'];
    }

    return $counts;
}

function public_context_source_options(string $runDate): array
{
    $stmt = db()->prepare(
        'SELECT DISTINCT source
         FROM collected_contexts
         WHERE run_date = ? AND source <> ""
         ORDER BY source ASC'
    );
    $stmt->execute([$runDate]);

    return array_map(static fn($row) => $row['source'], $stmt->fetchAll());
}

function public_context_type_label(string $type): string
{
    return $type === 'news' ? 'Noticia' : 'Tendencia';
}

function public_context_stats(array $items): array
{
    $sourceCounts = [];
    $keywordCounts = [];
    $news = 0;
    $trends = 0;

    foreach ($items as $item) {
        if (($item['context_type'] ?? '') === 'news') {
            $news++;
        } else {
            $trends++;
        }

        $source = trim((string) ($item['source'] ?? ''));
        if ($source !== '') {
            $sourceCounts[$source] = ($sourceCounts[$source] ?? 0) + 1;
        }

        foreach (public_context_keyword_list((string) ($item['keywords'] ?? ''), 20) as $keyword) {
            if (public_context_is_stopword($keyword)) {
                continue;
            }
            $keywordCounts[$keyword] = ($keywordCounts[$keyword] ?? 0) + 1;
        }
    }

    arsort($sourceCounts);
    arsort($keywordCounts);

    return [
        'news' => $news,
        'trends' => $trends,
        'source_count' => count($sourceCounts),
        'sources' => array_slice($sourceCounts, 0, 6, true),
        'keywords' => array_slice($keywordCounts, 0, 12, true),
    ];
}

function public_context_keyword_list(string $keywords, int $limit): array
{
    $normalized = normalize_score_text($keywords);
    $parts = preg_split('/\s+/u', $normalized);
    $clean = [];
    foreach ($parts ?: [] as $part) {
        $part = trim($part);
        if ($part === '' || mb_strlen($part, 'UTF-8') < 4) {
            continue;
        }
        $clean[$part] = true;
    }

    return array_slice(array_keys($clean), 0, $limit);
}

function public_context_is_stopword(string $keyword): bool
{
    static $stopwords = [
        'para' => true,
        'como' => true,
        'mais' => true,
        'pela' => true,
        'pelo' => true,
        'sobre' => true,
        'apos' => true,
        'esta' => true,
        'este' => true,
        'that' => true,
        'with' => true,
        'from' => true,
        'this' => true,
        'have' => true,
    ];

    return isset($stopwords[$keyword]);
}

function public_context_excerpt(array $item): string
{
    $text = trim((string) ($item['raw_text'] ?? ''));
    if ($text === '') {
        $text = trim((string) ($item['title'] ?? ''));
    }

    return $text !== '' ? $text : 'Resumo do contexto em validacao.';
}

function public_context_signal_label(array $item): string
{
    $type = public_context_type_label((string) ($item['context_type'] ?? 'trend'));
    $source = public_display_label((string) ($item['source'] ?? ''), 'fonte em validacao');

    return $type . ' de ' . $source;
}
