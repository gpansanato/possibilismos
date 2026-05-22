<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$today = today_key();
$message = null;
$error = null;
$items = [];
$rankingResult = null;
$eventsBefore = events_count_for_day($today['month'], $today['day']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'historical_events') {
            $count = import_historical_events_for_today();
            $message = $count . ' novos eventos historicos importados.';
        } elseif ($action === 'news') {
            $items = collect_daily_news_topics($today['date']);
            $message = count($items) . ' noticias persistidas/coletadas.';
        } elseif ($action === 'trends') {
            $items = collect_daily_trend_topics($today['date']);
            $message = count($items) . ' tendencias persistidas/coletadas.';
        } elseif ($action === 'context') {
            $news = collect_daily_news_topics($today['date']);
            $trends = collect_daily_trend_topics($today['date']);
            $items = array_merge($news, $trends);
            $message = count($news) . ' noticias e ' . count($trends) . ' tendencias persistidas/coletadas.';
        } elseif ($action === 'full_run') {
            $rankingResult = run_daily_ranking();
            $message = 'Execucao completa finalizada com ' . count($rankingResult) . ' eventos ranqueados.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$eventCounts = events_count_by_review_status($today['month'], $today['day']);
$newsCount = collected_contexts_count_for_date($today['date'], 'news');
$trendCount = collected_contexts_count_for_date($today['date'], 'trend');
$topicsCount = current_topics_count_for_date($today['date']);

render_page_start('Coletas', 'collections', 'admin', 'Centraliza coletas individuais, contexto e execucao completa do processo diario.');
?>
    <section class="option-grid" aria-label="Acoes de coleta">
        <form class="option-card is-featured" method="post">
            <span>Execucao completa</span>
            <strong>Rodar processo diario</strong>
            <p>Executa eventos historicos, noticias, tendencias e aplicacao do score em sequencia.</p>
            <button name="action" value="full_run" type="submit">Executar tudo</button>
        </form>

        <form class="option-card" method="post">
            <span>Eventos historicos</span>
            <strong>Buscar fatos do dia</strong>
            <p>Importa eventos da Wikimedia como nao avaliados para curadoria.</p>
            <button name="action" value="historical_events" type="submit">Coletar eventos</button>
        </form>

        <form class="option-card" method="post">
            <span>Noticias</span>
            <strong>Buscar noticias do dia</strong>
            <p>Coleta RSS, higieniza, deduplica e atualiza topicos de noticias.</p>
            <button name="action" value="news" type="submit">Coletar noticias</button>
        </form>

        <form class="option-card" method="post">
            <span>Tendencias</span>
            <strong>Buscar temas em alta</strong>
            <p>Coleta GDELT, Wikimedia Pageviews, Agencia Brasil RSS e Hacker News. Media Cloud fica disponivel via configuracao.</p>
            <button name="action" value="trends" type="submit">Coletar tendencias</button>
        </form>

        <form class="option-card" method="post">
            <span>Contexto completo</span>
            <strong>Noticias + tendencias</strong>
            <p>Atualiza todo o contexto usado pelo score sem recalcular o ranking.</p>
            <button name="action" value="context" type="submit">Coletar contexto</button>
        </form>
    </section>

    <?php if ($error): ?><section class="empty"><p><?= h($error) ?></p></section><?php endif; ?>
    <?php if ($message): ?><section class="panel"><p><?= h($message) ?></p></section><?php endif; ?>

    <section class="panel">
        <h1>Resumo de hoje</h1>
        <p>
            Eventos: <?= h((string) $eventCounts['pending']) ?> nao avaliados,
            <?= h((string) $eventCounts['approved']) ?> aprovados,
            <?= h((string) $eventCounts['rejected']) ?> reprovados.
        </p>
        <p>Noticias persistidas: <?= h((string) $newsCount) ?></p>
        <p>Tendencias persistidas: <?= h((string) $trendCount) ?></p>
        <p>Topicos operacionais no score: <?= h((string) $topicsCount) ?></p>
        <p>Fontes de tendencias: GDELT, Media Cloud, Wikimedia Pageviews, Agencia Brasil RSS e Hacker News.</p>
        <?php if ($rankingResult !== null): ?>
            <p>Antes da execucao completa havia <?= h((string) $eventsBefore) ?> eventos aprovados para hoje.</p>
        <?php endif; ?>
        <p><a href="/admin/contexts.php">Ver base higienizada de contexto</a></p>
    </section>

    <?php if ($items): ?>
        <section class="list">
            <?php foreach (array_slice($items, 0, 12) as $item): ?>
                <article class="event">
                    <div class="year"><?= h($item['context_type'] ?? 'ctx') ?></div>
                    <div>
                        <h2><?= h($item['title']) ?></h2>
                        <p class="meta"><?= h($item['source']) ?></p>
                        <p><?= h($item['keywords']) ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
<?php render_page_end(); ?>
