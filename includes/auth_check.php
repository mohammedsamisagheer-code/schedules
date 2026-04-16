<?php
// Check if user is logged in — SESSION_TIMEOUT constant is defined in config.php

function checkAuth($required_role = null) {
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
    $_SESSION['last_activity'] = time();
}

// Check if current user is admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Get current user info
function getCurrentUser() {
    if (isset($_SESSION['role'])) {
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'name' => $_SESSION['user_name'] ?? '',
            'title' => $_SESSION['user_title'] ?? '',
            'role' => $_SESSION['role'] ?? 'admin'
        ];
    }
    return null;
}
?>
