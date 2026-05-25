<?php
require_once __DIR__ . '/../app/bootstrap.php';

if (current_user()) {
    redirect('/admin/collections.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (login_user($_POST['email'] ?? '', $_POST['password'] ?? '')) {
        redirect('/admin/collections.php');
    }

    $error = 'Login invalido.';
}
render_page_start('Entrar no admin', 'admin', 'public', 'Acesso restrito para curadoria e publicacao dos fatos do dia.');
?>
    <section class="panel">
        <h1>Admin</h1>
        <?php if ($error): ?><p><?= h($error) ?></p><?php endif; ?>
        <form class="form" method="post">
            <label>Email <input type="email" name="email" required></label>
            <label>Senha <input type="password" name="password" required></label>
            <button type="submit">Entrar</button>
        </form>
    </section>
<?php render_page_end(); ?>
