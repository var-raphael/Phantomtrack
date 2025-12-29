<?php
session_start();
require_once "../includes/functions.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['has_email' => false]);
    exit;
}

$user = fetchOne("SELECT email FROM users WHERE user_id = ?", [$_SESSION['user_id']]);

echo json_encode([
    'has_email' => !empty($user['email'])
]);