<?php
require_once __DIR__ . '/../app/bootstrap.php';

if (user_count() > 0) {
    http_response_code(403);
    echo 'Usuario inicial ja existe. Remova este arquivo do servidor depois da primeira configuracao.';
    exit;
}

$error = null;
$created = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $name && strlen($password) >= 8) {
        $stmt = db()->prepare(
            'INSERT INTO users (email, name, password_hash, created_at) VALUES (?, ?, ?, NOW())'
        );
        $stmt->execute([$email, $name, password_hash($password, PASSWORD_DEFAULT)]);
        $created = true;
    } else {
        $error = 'Preencha nome, email e senha com pelo menos 8 caracteres.';
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Criar usuario</title>
    <link rel="stylesheet" href="/public/style.css">
</head>
<body>
<main class="page">
    <section class="panel">
        <h1>Criar primeiro usuario</h1>
        <?php if ($created): ?>
            <p>Usuario criado. Acesse <a href="/admin/login.php">o login</a> e remova este arquivo do servidor.</p>
        <?php else: ?>
            <?php if ($error): ?><p><?= h($error) ?></p><?php endif; ?>
            <form class="form" method="post">
                <label>Nome <input name="name" required></label>
                <label>Email <input type="email" name="email" required></label>
                <label>Senha <input type="password" name="password" minlength="8" required></label>
                <button type="submit">Criar usuario</button>
            </form>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
