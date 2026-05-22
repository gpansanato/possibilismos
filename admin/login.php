<?php
require_once __DIR__ . '/../app/bootstrap.php';

if (current_user()) {
    redirect('/admin/dashboard.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (login_user($_POST['email'] ?? '', $_POST['password'] ?? '')) {
        redirect('/admin/dashboard.php');
    }

    $error = 'Login invalido.';
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login</title>
    <link rel="stylesheet" href="/public/style.css">
</head>
<body>
<main class="page">
    <section class="panel">
        <h1>Admin</h1>
        <?php if ($error): ?><p><?= h($error) ?></p><?php endif; ?>
        <form class="form" method="post">
            <label>Email <input type="email" name="email" required></label>
            <label>Senha <input type="password" name="password" required></label>
            <button type="submit">Entrar</button>
        </form>
    </section>
</main>
</body>
</html>
