<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

checkAuth();
$current_user = getCurrentUser();
$action = trim($_POST['action'] ?? '');

if ($action === '') {
    http_response_code(400);
    exit;
}

logActivity($pdo, $action, $current_user['name'] ?? '');
http_response_code(204);
