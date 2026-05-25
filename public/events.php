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
$publishedLabel = count($items) === 1
    ? '1 evento publicado em ' . $dateLabel
    : count($items) . ' eventos publicados em ' . $dateLabel;

render_page_start('Eventos históricos destacados', 'events', 'public', 'Fatos aprovados pela curadoria, com contexto, fontes e justificativa editorial.', true);
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
                <?php foreach (['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $weekday): ?>
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
            <p class="calendar-note">Cada número indica quantos eventos foram aprovados e publicados naquela data.</p>
        </aside>

        <section class="published-events">
            <div class="section-heading section-heading--compact">
                <div>
                    <?php component_badge($dateLabel); ?>
                    <h2><?= h($publishedLabel) ?></h2>
                    <p>Use os filtros para encontrar fatos publicados por data, categoria, entidade, região ou prioridade editorial.</p>
                </div>
                <a class="button button-secondary" href="/eventos.php">Hoje</a>
            </div>

            <form class="filter-form public-filter" method="get" aria-label="Filtros de eventos publicados">
                <label>Data <input type="date" name="date" value="<?= h($date) ?>"></label>
                <label>Categoria
                    <select name="category">
                        <option value="">Todas</option>
                        <?php foreach ($filterOptions['categories'] as $option): ?>
                            <option value="<?= h($option) ?>" <?= $category === $option ? 'selected' : '' ?>><?= h(public_display_label($option)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Região ou entidade
                    <select name="region">
                        <option value="">Todas</option>
                        <?php foreach ($filterOptions['regions'] as $option): ?>
                            <option value="<?= h($option) ?>" <?= $region === $option ? 'selected' : '' ?>><?= h(public_display_label($option)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Ordenar
                    <select name="sort">
                        <option value="score_desc" <?= $sort === 'score_desc' ? 'selected' : '' ?>>Prioridade editorial</option>
                        <option value="year_asc" <?= $sort === 'year_asc' ? 'selected' : '' ?>>Ano crescente</option>
                        <option value="year_desc" <?= $sort === 'year_desc' ? 'selected' : '' ?>>Ano decrescente</option>
                        <option value="title_asc" <?= $sort === 'title_asc' ? 'selected' : '' ?>>Título</option>
                    </select>
                </label>
                <label>Busca <input name="q" value="<?= h($search) ?>" placeholder="Evento, tema ou motivo"></label>
                <button type="submit">Aplicar</button>
                <a class="button button-secondary" href="/eventos.php?date=<?= h($date) ?>">Limpar</a>
            </form>

            <?php if (!$items): ?>
                <section class="empty">
                    <h2>Nenhum evento publicado para esta data.</h2>
                    <p>Use o calendário para navegar por outras datas ou acompanhe a próxima rodada de publicação editorial.</p>
                </section>
            <?php endif; ?>

            <section class="published-list" aria-label="Lista de eventos publicados">
                <?php foreach ($items as $item): ?>
                    <?php
                    $contextCount = substr_count((string) $item['reasons'], 'conexão com');
                    $priorityLabel = public_priority_label((float) $item['score']);
                    $editorialReason = public_editorial_reason($item, $contextCount);
                    $structuredEntities = event_structured_entities($item);
                    $structuredLocation = event_structured_location($item);
                    $eventTypes = event_structured_tags($structuredEntities, 'types', 2);
                    ?>
                    <article class="published-card">
                        <?php if ($item['image_url']): ?>
                            <a class="published-card__media" href="/evento.php?id=<?= h((string) $item['id']) ?>" aria-label="Abrir dossiê de <?= h($item['title']) ?>">
                                <img class="published-card__image" src="<?= h($item['image_url']) ?>" alt="">
                            </a>
                        <?php endif; ?>
                        <div class="published-card__body">
                            <div class="published-card__top">
                                <span class="year"><?= h((string) $item['year']) ?></span>
                                <span class="status-badge is-approved">Prioridade <?= h($priorityLabel) ?></span>
                            </div>
                            <h3><a class="table-title" href="/evento.php?id=<?= h((string) $item['id']) ?>"><?= h($item['title']) ?></a></h3>
                            <p class="published-card__summary"><?= h($item['description'] ?: 'Resumo editorial em validação.') ?></p>
                            <p class="published-card__reason"><?= h($editorialReason) ?></p>
                            <div class="meta">
                                <span><?= h(public_display_label($item['category'])) ?></span>
                                <?php if (trim((string) $item['region']) !== ''): ?><span><?= h(public_display_label($item['region'])) ?></span><?php endif; ?>
                                <span><?= h((string) $item['enrichment_count']) ?> enriquecimentos</span>
                                <?php foreach ($eventTypes as $type): ?><span><?= h($type) ?></span><?php endforeach; ?>
                                <?php if (!empty($structuredLocation['country'])): ?><span><?= h((string) $structuredLocation['country']) ?></span><?php endif; ?>
                                <?php if ($item['canonical_source']): ?><span><?= h($item['canonical_source']) ?></span><?php endif; ?>
                            </div>
                            <div class="published-card__actions">
                                <a class="button button-primary" href="/evento.php?id=<?= h((string) $item['id']) ?>">Abrir dossiê</a>
                                <?php if ($item['source_url']): ?>
                                    <a class="button button-secondary" href="<?= h($item['source_url']) ?>" target="_blank" rel="noopener">Fonte</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        </section>
    </section>
<?php render_page_end(); ?>
