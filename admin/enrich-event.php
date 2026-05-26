<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/events.php');
}

$returnTo = $_POST['return_to'] ?? '/admin/events.php';
if (!is_string($returnTo) || strpos($returnTo, '/admin/') !== 0) {
    $returnTo = '/admin/events.php';
}

redirect($returnTo);
