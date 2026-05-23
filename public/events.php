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
$calendarCounts = published_ranking_counts_for_month($calendarYear, $calendarMonth);
$calendarOffset = (int) $calendarStart->format('w');
$calendarDays = (int) $calendarEnd->format('j');
$previousMonthDate = $calendarStart->modify('-1 month')->format('Y-m-01');
$nextMonthDate = $calendarStart->modify('+1 month')->format('Y-m-01');

$category = trim($_GET['category'] ?? '');
$region = trim($_GET['region'] ?? '');
$search = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'score_desc';
$filterOptions = published_ranking_filter_options($date);
$items = published_rankings_search($date, $category, $region, $search, $sort);

$dateLabel = $selectedDate->format('d/m/Y');
$publishedLabel = count($items) === 1 ? '1 insumo editorial publicado' : count($items) . ' insumos editoriais publicados';

render_page_start('Eventos históricos publicados', 'events', 'public', 'Consulte os fatos priorizados, aprovados e preparados para publicação editorial.', true);
?>
    <section class="public-events-layout">
        <aside class="calendar-panel" aria-label="Calendário de publicações">
            <div class="calendar-panel__head">
                <a class="button button-secondary" href="/eventos.php?date=<?= h($previousMonthDate) ?>">Anterior</a>
                <div>
                    <span class="eyebrow">Calendário</span>
                    <h2><?= h($calendarStart->format('m/Y')) ?></h2>
                </div>
                <a class="button button-secondary" href="/eventos.php?date=<?= h($nextMonthDate) ?>">Próximo</a>
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
                    <a class="<?= h(implode(' ', $classes)) ?>" href="/eventos.php?date=<?= h($dayDate) ?>" aria-label="<?= h($dayDate . ' com ' . $count . ' eventos publicados') ?>">
                        <strong><?= h((string) $day) ?></strong>
                        <span><?= h((string) $count) ?></span>
                    </a>
                <?php endfor; ?>
            </div>
            <p class="calendar-note">Os números indicam quantos fatos foram priorizados, aprovados e publicados em cada data.</p>
        </aside>

        <section class="published-events">
            <div class="section-heading">
                <div>
                    <?php component_badge($dateLabel); ?>
                    <h2><?= h($publishedLabel) ?></h2>
                    <p>Visualização pública dos fatos aprovados pela curadoria, com prioridade, fonte e motivo editorial.</p>
                </div>
                <a class="button button-secondary" href="/eventos.php">Hoje</a>
            </div>

            <form class="filter-form public-filter" method="get">
                <label>Data <input type="date" name="date" value="<?= h($date) ?>"></label>
                <label>Categoria
                    <select name="category">
                        <option value="">Todas</option>
                        <?php foreach ($filterOptions['categories'] as $option): ?>
                            <option value="<?= h($option) ?>" <?= $category === $option ? 'selected' : '' ?>><?= h($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Região
                    <select name="region">
                        <option value="">Todas</option>
                        <?php foreach ($filterOptions['regions'] as $option): ?>
                            <option value="<?= h($option) ?>" <?= $region === $option ? 'selected' : '' ?>><?= h($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Ordenar
                    <select name="sort">
                        <option value="score_desc" <?= $sort === 'score_desc' ? 'selected' : '' ?>>Prioridade</option>
                        <option value="year_asc" <?= $sort === 'year_asc' ? 'selected' : '' ?>>Ano crescente</option>
                        <option value="year_desc" <?= $sort === 'year_desc' ? 'selected' : '' ?>>Ano decrescente</option>
                        <option value="title_asc" <?= $sort === 'title_asc' ? 'selected' : '' ?>>Título</option>
                    </select>
                </label>
                <label>Busca <input name="q" value="<?= h($search) ?>" placeholder="Evento, contexto ou motivo"></label>
                <button type="submit">Aplicar</button>
                <a class="button button-secondary" href="/eventos.php?date=<?= h($date) ?>">Limpar</a>
            </form>

            <?php if (!$items): ?>
                <section class="empty">
                    <h2>Nenhum fato publicado para esta data.</h2>
                    <p>Use o calendário para navegar por outras datas ou acompanhe a próxima rodada de publicação editorial.</p>
                </section>
            <?php endif; ?>

            <section class="published-list">
                <?php foreach ($items as $item): ?>
                    <article class="published-card">
                        <?php if ($item['image_url']): ?>
                            <img class="published-card__image" src="<?= h($item['image_url']) ?>" alt="">
                        <?php endif; ?>
                        <div class="published-card__body">
                            <div class="published-card__top">
                                <span class="year"><?= h($item['year']) ?></span>
                                <span class="status-badge is-approved">Prioridade <?= h(number_format((float) $item['score'], 1)) ?></span>
                            </div>
                            <h3><?= h($item['title']) ?></h3>
                            <p><?= h($item['description']) ?></p>
                            <p class="context"><?= h($item['context_summary']) ?></p>
                            <div class="meta">
                                <span><?= h($item['category']) ?></span>
                                <span><?= h($item['region']) ?></span>
                                <span><?= h((string) $item['enrichment_count']) ?> enriquecimentos</span>
                                <?php if ($item['canonical_source']): ?><span><?= h($item['canonical_source']) ?></span><?php endif; ?>
                            </div>
                            <?php if ($item['reasons']): ?>
                                <details class="reason-box">
                                    <summary>Motivo da priorização</summary>
                                    <p><?= h($item['reasons']) ?></p>
                                </details>
                            <?php endif; ?>
                            <?php if ($item['source_url']): ?>
                                <a class="source" href="<?= h($item['source_url']) ?>" target="_blank" rel="noopener">Abrir fonte original</a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        </section>
    </section>
<?php render_page_end(); ?>
