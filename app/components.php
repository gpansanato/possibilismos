<?php

function component_badge(string $text): void
{
    ?>
    <span class="badge"><?= h($text) ?></span>
    <?php
}

function component_metric(string $label, string $value): void
{
    ?>
    <div class="mock-metric">
        <span><?= h($label) ?></span>
        <strong><?= h($value) ?></strong>
    </div>
    <?php
}

function component_mock_row(string $title, string $meta, string $status = 'Ativo'): void
{
    ?>
    <div class="mock-row">
        <div>
            <strong><?= h($title) ?></strong>
            <span><?= h($meta) ?></span>
        </div>
        <em><?= h($status) ?></em>
    </div>
    <?php
}

function public_display_label(?string $value, string $fallback = 'Não informado'): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return $fallback;
    }

    $normalized = mb_strtolower($value, 'UTF-8');
    $labels = [
        'historia' => 'História',
        'history' => 'História',
        'politics' => 'Política',
        'politica' => 'Política',
        'science' => 'Ciência',
        'ciencia' => 'Ciência',
        'culture' => 'Cultura',
        'cultura' => 'Cultura',
        'war' => 'Conflitos',
        'conflict' => 'Conflitos',
        'society' => 'Sociedade',
    ];

    return $labels[$normalized] ?? $value;
}

function public_priority_label(float $score): string
{
    if ($score >= 75) {
        return 'Alta';
    }
    if ($score >= 45) {
        return 'Média';
    }

    return 'Baixa';
}

function public_editorial_reason(array $item, int $contextCount = 0): string
{
    $summary = trim((string) ($item['context_summary'] ?? ''));
    $technicalPrefixes = ['Priorizacao calculada por:', 'Priorização calculada por:'];

    foreach ($technicalPrefixes as $prefix) {
        if (substr($summary, 0, strlen($prefix)) === $prefix) {
            $summary = '';
            break;
        }
    }

    if ($summary !== '') {
        return $summary;
    }

    $priority = public_priority_label((float) ($item['score'] ?? 0));
    $category = public_display_label($item['category'] ?? null, 'histórica');
    $contextText = $contextCount > 0
        ? ' e conexões temáticas identificadas no contexto do dia'
        : ', aguardando validação de conexões contextuais públicas';

    return 'Este evento foi destacado por combinar prioridade editorial ' . mb_strtolower($priority, 'UTF-8') . ', relevância ' . mb_strtolower($category, 'UTF-8') . $contextText . '. A associação serve como apoio de curadoria, não como afirmação automática de causalidade.';
}
