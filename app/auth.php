<?php

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, email, name FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function require_admin(): array
{
    $user = current_user();
    if (!$user) {
        redirect('/admin/login.php');
    }

    return $user;
}

function login_user(string $email, string $password): bool
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    $_SESSION['user_id'] = (int) $user['id'];
    return true;
}

function logout_user(): void
{
    $_SESSION = [];
    session_destroy();
}

function user_count(): int
{
    return (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
}
