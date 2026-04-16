<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

if (!isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?expired=1');
    exit;
}

if ($_SESSION['role'] === 'admin') {
    header('Location: dashboard.php');
} else {
    header('Location: view_schedule.php');
}
exit;
