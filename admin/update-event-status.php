<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$id = (int) ($_POST['id'] ?? 0);
$active = $_POST['active'] ?? null;

if ($id > 0 && in_array($active, ['0', '1'], true)) {
    $stmt = db()->prepare('UPDATE events SET active = ? WHERE id = ?');
    $stmt->execute([(int) $active, $id]);
}

redirect('/admin/events.php');
