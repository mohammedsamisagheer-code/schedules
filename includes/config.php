<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'class_schedule');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Set timezone
date_default_timezone_set('Africa/Cairo');

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET time_zone = '+02:00'");
} catch (PDOException $e) {
    die("Database connection failed");
}

function logActivity($pdo, $action, $user_name = '') {
    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_name, action) VALUES (?, ?)");
    $stmt->execute([$user_name ?: '', $action]);
}

function getTitleAbbr($title) {
    $map = [
        'دكتور' => 'د',
        'أستاذ' => 'أ',
        'مهندس' => 'م',
    ];
    return isset($map[$title]) ? $map[$title] . '. ' : '';
}

function getSettings($pdo) {
    try {
        return $pdo->query("SELECT `key`, `value` FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Build a time-slot map from a start time + number of 2-hour periods.
 * Returns  [ 'HH:MM:SS' => 'HH:MM - HH:MM', ... ]
 */
function buildTimeSlots($start_time = '09:00', $periods = 3) {
    $slots = [];
    $base  = strtotime(date('Y-m-d') . ' ' . $start_time);
    for ($i = 0; $i < (int)$periods; $i++) {
        $from_ts  = $base + $i * 7200;
        $to_ts    = $base + ($i + 1) * 7200;
        $key      = date('H:i:s', $from_ts);
        $label    = date('H:i', $from_ts) . ' - ' . date('H:i', $to_ts);
        $slots[$key] = $label;
    }
    return $slots;
}

// Load system settings and expose as constants
$_app_settings = getSettings($pdo);
define('SESSION_TIMEOUT',      (int)($_app_settings['session_timeout_minutes'] ?? 60) * 60);
define('COLLEGE_NAME',         $_app_settings['college_name']         ?? 'كلية التقنية الهندسية-جنزور');
define('ACADEMIC_YEAR',        $_app_settings['academic_year']        ?? '2025-2026');
define('BF_MAX_ATTEMPTS',      (int)($_app_settings['bf_max_attempts']      ?? 10));
define('BF_LOCKOUT_MINUTES',   (int)($_app_settings['bf_lockout_minutes']   ?? 5));
define('CLASSES_START_TIME',   $_app_settings['classes_start_time']   ?? '09:00');
define('PERIODS_COUNT',        (int)($_app_settings['periods_count']        ?? 3));
unset($_app_settings);
?>