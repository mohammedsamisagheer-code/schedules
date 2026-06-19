<?php
// Suppress session header warnings in CLI
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/helpers/SchedulerHelper.php';
