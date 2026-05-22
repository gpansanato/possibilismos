<?php

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function today_key(): array
{
    $now = new DateTimeImmutable('now');

    return [
        'date' => $now->format('Y-m-d'),
        'month' => (int) $now->format('m'),
        'day' => (int) $now->format('d'),
        'year' => (int) $now->format('Y'),
    ];
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}
