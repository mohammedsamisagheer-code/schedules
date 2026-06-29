<?php
/**
 * Database Migration — Schedules (بوابة الجداول)
 *
 * Creates all tables and seeds required system data.
 * Idempotent: safe to re-run (CREATE TABLE IF NOT EXISTS / INSERT IGNORE).
 *
 * Usage:
 *   php migrate.php                  (CLI)
 *   http://host/schedules/migrate.php (Web — then delete this file!)
 */

$is_cli = (php_sapi_name() === 'cli');

// ── helpers ──────────────────────────────────────────────────────────────

function _out(string $msg, string $type = 'info'): void {
    global $is_cli;
    if ($is_cli) {
        $pre = match($type) {
            'ok'   => "  \xE2\x9C\x93",
            'skip' => "  \xE2\x8F\xAD",
            'seed' => "  \xF0\x9F\x8C\xB1",
            'err'  => "  \xE2\x9C\x97",
            default => "   "
        };
        echo "$pre $msg\n";
    } else {
        $badge = match($type) {
            'ok'   => '<span class="badge badge-ok">&#10004;</span>',
            'skip' => '<span class="badge badge-skip">&#9199;</span>',
            'seed' => '<span class="badge badge-seed">&#127793;</span>',
            'err'  => '<span class="badge badge-err">&#10008;</span>',
            default => '<span class="badge">&nbsp;</span>'
        };
        echo "<tr><td>$badge</td><td>$msg</td></tr>\n";
    }
}

function _ok(string $msg): void   { _out($msg, 'ok'); }
function _skip(string $msg): void { _out($msg, 'skip'); }
function _seed(string $msg): void { _out($msg, 'seed'); }
function _err(string $msg): void  { _out($msg, 'err'); }

function _addColumnIfMissing(PDO $pdo, string $table, string $column, string $definition): void {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN $definition");
            _ok("Column `$column` added to `$table`");
        }
    } catch (Exception $e) {
        // table probably doesn't exist yet
    }
}

function _createTable(PDO $pdo, string $name, string $sql): void {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS $sql");
        _ok("Table `$name` ready");
    } catch (Exception $e) {
        _err("Failed creating table `$name`: " . $e->getMessage());
    }
}

// ── boot ─────────────────────────────────────────────────────────────────

require_once __DIR__ . '/includes/config.php';

if (!$is_cli) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html lang='en'><head><meta charset='utf-8'>";
    echo "<title>Database Migration</title>";
    echo "<style>
        body{font-family:sans-serif;background:#f3f4f6;margin:2rem;color:#111}
        h1{font-size:1.5rem;margin-bottom:0.25rem}
        p{color:#6b7280;margin-bottom:1.5rem}
        table{border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.1);width:100%;max-width:700px}
        th,td{padding:0.5rem 1rem;border-bottom:1px solid #e5e7eb}
        th{background:#f9fafb;font-weight:600;text-align:left}
        td:first-child{width:40px;text-align:center}
        .badge-ok{color:#059669}
        .badge-skip{color:#d97706}
        .badge-seed{color:#2563eb}
        .badge-err{color:#dc2626}
        .summary{background:#fff;border-radius:8px;padding:1rem 1.5rem;margin-top:1rem;box-shadow:0 1px 3px rgba(0,0,0,.1);max-width:700px}
        .summary h2{margin:0 0 0.5rem;font-size:1.1rem}
        .ok{color:#059669}.warn{color:#d97706}
    </style></head><body>";
    echo "<h1>Database Migration</h1><p>Creating tables and seeding system data</p><table>";
    echo "<thead><tr><th></th><th>Step</th></tr></thead><tbody>";
}

// ── 1. create tables ─────────────────────────────────────────────────────

_out('── Creating tables ──', 'info');

_createTable($pdo, 'activity_logs', '`activity_logs` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `user_name`  VARCHAR(255) DEFAULT \'\',
    `action`     TEXT NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci');

_createTable($pdo, 'login_attempts', '`login_attempts` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `ip`           VARCHAR(45) NOT NULL,
    `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ip_time` (`ip`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci');

_createTable($pdo, 'rooms', '`rooms` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `name`       VARCHAR(50) NOT NULL,
    `exam_only`  TINYINT(1) NOT NULL DEFAULT 0,
    `class_only` TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci');

_createTable($pdo, 'teachers', '`teachers` (
    `id`                   INT AUTO_INCREMENT PRIMARY KEY,
    `name`                 VARCHAR(50) NOT NULL,
    `title`                VARCHAR(20) DEFAULT NULL,
    `national_id`          VARCHAR(20) DEFAULT NULL,
    `password`             VARCHAR(255) DEFAULT NULL,
    `must_change_password` TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY `national_id` (`national_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci');

_createTable($pdo, 'subjects', '`subjects` (
    `id`                  INT AUTO_INCREMENT PRIMARY KEY,
    `teacher_id`          INT NOT NULL,
    `subject_name`        VARCHAR(50) NOT NULL,
    `subject_code`        VARCHAR(50) DEFAULT NULL,
    `term`                INT NOT NULL,
    `priority`            INT NOT NULL DEFAULT 2,
    `requires_subject_id` INT DEFAULT NULL,
    FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`requires_subject_id`) REFERENCES `subjects`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci');

_createTable($pdo, 'schedules', '`schedules` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `subject_id`  INT NOT NULL,
    `teacher_id`  INT NOT NULL,
    `room_id`     INT NOT NULL,
    `day_of_week` ENUM(\'السبت\',\'الأحد\',\'الإثنين\',\'الثلاثاء\',\'الإربعاء\',\'الخميس\') NOT NULL,
    `time`        VARCHAR(50) NOT NULL,
    FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`room_id`)    REFERENCES `rooms`(`id`)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci');

_createTable($pdo, 'exam_schedules', '`exam_schedules` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `subject_id`  INT NOT NULL,
    `term`        INT NOT NULL,
    `exam_date`   DATE NOT NULL,
    `slot`        INT NOT NULL DEFAULT 1,
    `room_id`     INT DEFAULT NULL,
    `start_time`  TIME DEFAULT NULL,
    INDEX `exam_date` (`exam_date`),
    FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`room_id`)    REFERENCES `rooms`(`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci');

_createTable($pdo, 'settings', '`settings` (
    `key`   VARCHAR(100) NOT NULL PRIMARY KEY,
    `value` TEXT NOT NULL DEFAULT \'\',
    `label` VARCHAR(200) NOT NULL DEFAULT \'\'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci');

_createTable($pdo, 'terms', '`terms` (
    `term_number` INT NOT NULL PRIMARY KEY,
    `name`        VARCHAR(100) NOT NULL,
    `short_name`  VARCHAR(50) NOT NULL DEFAULT \'\',
    `sort_order`  INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci');

_createTable($pdo, 'users', '`users` (
    `id`                   INT AUTO_INCREMENT PRIMARY KEY,
    `username`             VARCHAR(100) NOT NULL,
    `password`             VARCHAR(255) NOT NULL,
    `name`                 VARCHAR(100) NOT NULL,
    `title`                VARCHAR(20) DEFAULT NULL,
    `role`                 VARCHAR(20) NOT NULL DEFAULT \'admin\',
    `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
    `teacher_id`           INT DEFAULT NULL,
    UNIQUE KEY `username` (`username`),
    FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci');

_createTable($pdo, 'user_permissions', '`user_permissions` (
    `user_id`  INT NOT NULL,
    `perm_key` VARCHAR(100) NOT NULL,
    `value`    VARCHAR(1) NOT NULL DEFAULT \'0\',
    PRIMARY KEY (`user_id`, `perm_key`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci');

// ── 1b. migrate existing tables (add columns the code expects) ─────────

_out('── Migrating existing tables ──', 'info');

_addColumnIfMissing($pdo, 'users', 'must_change_password', '`must_change_password` TINYINT(1) NOT NULL DEFAULT 0');
_addColumnIfMissing($pdo, 'users', 'teacher_id',           '`teacher_id` INT DEFAULT NULL');

// ── 2. seed system data ──────────────────────────────────────────────────

_out('── Seeding system data ──', 'info');

// 2a  settings  (INSERT IGNORE — keeps existing values)
$settings = [
    // system
    'academic_year'          => ['2025-2026',                      'Academic year'],
    'bf_lockout_minutes'     => ['5',                              'Lockout duration (minutes)'],
    'bf_max_attempts'        => ['10',                             'Max login attempts'],
    'classes_start_time'     => ['09:00',                          'Classes start time'],
    'college_name'           => ['كلية التقنية الهندسية-جنزور',   'College name'],
    'current_exam_type'      => ['',                               'Current exam type'],
    'exam_exams_per_day'     => ['3',                              'Exams per day'],
    'exam_interval'          => ['3',                              'Interval between exams (days)'],
    'exam_prevent_conflict'  => ['1',                              'Prevent exam conflicts'],
    'max_teaching_days'      => ['4',                              'Max teaching days per week'],
    'periods_count'          => ['3',                              'Daily lecture periods'],
    'period_duration_hours'  => ['2',                             'Period duration (hours)'],
    'session_timeout_minutes'=> ['60',                             'Session timeout (minutes)'],
    // permissions — match defaults in getUserPermissions()
    'perm_user_subjects_view'   => ['1', 'View subjects'],
    'perm_user_subjects_add'    => ['1', 'Add subject'],
    'perm_user_subjects_edit'   => ['0', 'Edit subject'],
    'perm_user_subjects_delete' => ['0', 'Delete subject'],
    'perm_user_teachers_view'   => ['1', 'View teachers'],
    'perm_user_teachers_add'    => ['1', 'Add teacher'],
    'perm_user_teachers_edit'   => ['0', 'Edit teacher'],
    'perm_user_teachers_delete' => ['0', 'Delete teacher'],
    'perm_user_rooms_view'      => ['1', 'View rooms'],
    'perm_user_rooms_add'       => ['1', 'Add room'],
    'perm_user_rooms_edit'      => ['0', 'Edit room'],
    'perm_user_rooms_delete'    => ['0', 'Delete room'],
    'perm_user_view_schedule'   => ['1', 'View schedule'],
    'perm_user_exam_schedule'   => ['1', 'View exam schedule'],
];
$seed_count = 0;
$stmt_setting = $pdo->prepare("INSERT IGNORE INTO `settings` (`key`, `value`, `label`) VALUES (?, ?, ?)");
foreach ($settings as $key => [$value, $label]) {
    $stmt_setting->execute([$key, $value, $label]);
    if ($stmt_setting->rowCount()) $seed_count++;
}
_seed("$seed_count settings seeded into `settings`");

// 2b  terms
$terms = [
    [3, 'الفصل الثالث',   'ف3', 1],
    [4, 'الفصل الرابع',   'ف4', 2],
    [5, 'الفصل الخامس',   'ف5', 3],
    [6, 'الفصل السادس',   'ف6', 4],
    [7, 'الفصل السابع',   'ف7', 5],
    [8, 'الفصل الثامن',   'ف8', 6],
];
$seed_count = 0;
$stmt_term = $pdo->prepare("INSERT IGNORE INTO `terms` (`term_number`, `name`, `short_name`, `sort_order`) VALUES (?, ?, ?, ?)");
foreach ($terms as $t) {
    $stmt_term->execute($t);
    if ($stmt_term->rowCount()) $seed_count++;
}
_seed("$seed_count terms seeded into `terms`");

// 2c  users — default admin
$stmt_user = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE `username` = ?");
$stmt_user->execute(['admin']);
if ((int)$stmt_user->fetchColumn() === 0) {
    $pdo->prepare("INSERT INTO `users` (`username`, `password`, `name`, `role`, `must_change_password`) VALUES (?, ?, ?, ?, ?)")
        ->execute(['admin', md5('admin'), 'مدير النظام', 'admin', 1]);
    _seed('Default user `admin` / `admin` seeded into `users`');
} else {
    _skip('User `admin` already exists');
}

// ── 3. done ──────────────────────────────────────────────────────────────

$pdo = null;

if ($is_cli) {
    echo "\n  \xE2\x9C\x93 Migration complete\n\n";
} else {
    echo "</tbody></table>";
    echo "<div class='summary'><h2>&#10004; Migration complete</h2>";
    echo "<p class='ok'>All tables created and system data seeded.</p>";
    echo "<p class='warn'>&#9888;&#65039; Delete this file after use for security.</p></div>";
    echo "</body></html>";
}
