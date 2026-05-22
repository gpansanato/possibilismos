<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$id = (int) ($_POST['id'] ?? 0);
$status = $_POST['status'] ?? '';

if ($id > 0 && in_array($status, ['approved', 'rejected', 'suggested'], true)) {
    $stmt = db()->prepare('UPDATE daily_rankings SET status = ? WHERE id = ?');
    $stmt->execute([$status, $id]);
}

redirect('/admin/dashboard.php');
