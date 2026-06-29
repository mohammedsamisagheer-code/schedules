<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'class_schedule');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Set timezone
date_default_timezone_set('Africa/Tripoli');

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

function getUserPermissions($pdo, $user_id = null) {
    $defaults = [
        'perm_user_subjects_view'    => '1',
        'perm_user_subjects_add'     => '1',
        'perm_user_subjects_edit'    => '0',
        'perm_user_subjects_delete'  => '0',
        'perm_user_teachers_view'    => '1',
        'perm_user_teachers_add'     => '1',
        'perm_user_teachers_edit'    => '0',
        'perm_user_teachers_delete'  => '0',
        'perm_user_rooms_view'       => '1',
        'perm_user_rooms_add'        => '1',
        'perm_user_rooms_edit'       => '0',
        'perm_user_rooms_delete'     => '0',
        'perm_user_view_schedule'    => '1',
        'perm_user_exam_schedule'    => '1',
    ];
    try {
        $keys = array_keys($defaults);
        $in   = implode(',', array_fill(0, count($keys), '?'));
        $rows = $pdo->prepare("SELECT `key`, `value` FROM settings WHERE `key` IN ($in)");
        $rows->execute($keys);
        foreach ($rows->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) {
            $defaults[$k] = $v;
        }
    } catch (Exception $e) {}

    if ($user_id) {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `user_permissions` (
                `user_id` INT NOT NULL,
                `perm_key` VARCHAR(100) NOT NULL,
                `value` VARCHAR(1) NOT NULL DEFAULT '0',
                PRIMARY KEY (`user_id`, `perm_key`),
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

            $keys = array_keys($defaults);
            $in   = implode(',', array_fill(0, count($keys), '?'));
            $stmt = $pdo->prepare("SELECT `perm_key`, `value` FROM user_permissions WHERE `user_id` = ? AND `perm_key` IN ($in)");
            $stmt->execute(array_merge([$user_id], $keys));
            foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) {
                $defaults[$k] = $v;
            }
        } catch (Exception $e) {}
    }

    return $defaults;
}

function getSettings($pdo) {
    try {
        return $pdo->query("SELECT `key`, `value` FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get all academic terms, ordered by sort_order.
 * Auto-creates the table and seeds defaults if missing.
 * Returns array of [term_number, name, short_name, sort_order].
 */
function getTerms($pdo): array {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `terms` (
            `term_number` INT NOT NULL PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `short_name` VARCHAR(50) NOT NULL DEFAULT '',
            `sort_order` INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        $pdo->exec("INSERT IGNORE INTO `terms` (`term_number`, `name`, `short_name`, `sort_order`) VALUES
            (3, 'الفصل الثالث', 'ف3', 1),
            (4, 'الفصل الرابع', 'ف4', 2),
            (5, 'الفصل الخامس', 'ف5', 3),
            (6, 'الفصل السادس', 'ف6', 4),
            (7, 'الفصل السابع', 'ف7', 5),
            (8, 'الفصل الثامن', 'ف8', 6)");
        return $pdo->query("SELECT * FROM `terms` ORDER BY `sort_order`")->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Build term_names map from getTerms() result: [term_number => name]
 */
function getTermNames($pdo): array {
    $map = [];
    foreach (getTerms($pdo) as $t) {
        $map[(int)$t['term_number']] = $t['name'];
    }
    return $map;
}

/**
 * Build flat list of term numbers from getTerms() result.
 */
function getTermNumbers($pdo): array {
    $nums = [];
    foreach (getTerms($pdo) as $t) {
        $nums[] = (int)$t['term_number'];
    }
    return $nums;
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