<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$id = (int) ($_POST['id'] ?? 0);
$status = $_POST['review_status'] ?? null;
$returnTo = (string) ($_POST['return_to'] ?? '/admin/events.php');

if ($id > 0 && in_array($status, ['approved', 'rejected'], true)) {
    $stmt = db()->prepare('UPDATE events SET review_status = ?, active = ? WHERE id = ?');
    $stmt->execute([$status, event_active_from_review_status($status), $id]);
}

if (strpos($returnTo, '/admin/') !== 0) {
    $returnTo = '/admin/events.php';
}

redirect($returnTo);
