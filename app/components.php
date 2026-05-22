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
