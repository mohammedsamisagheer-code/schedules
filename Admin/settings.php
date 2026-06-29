<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

checkAuth('admin');
if (!isAdmin()) { header('Location: view_schedule.php'); exit; }

$current_user = getCurrentUser();

// Create settings table and seed defaults if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
    `key`   VARCHAR(100) NOT NULL PRIMARY KEY,
    `value` TEXT         NOT NULL DEFAULT '',
    `label` VARCHAR(200) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$pdo->exec("INSERT IGNORE INTO `settings` (`key`, `value`, `label`) VALUES
    ('academic_year',           '2025-2026',                   'العام الدراسي'),
    ('session_timeout_minutes', '60',                          'مدة انتهاء الجلسة (دقيقة)'),
    ('max_teaching_days',       '4',                           'أقصى أيام التدريس في الأسبوع'),
    ('bf_max_attempts',         '10',                          'أقصى محاولات تسجيل الدخول'),
    ('bf_lockout_minutes',      '5',                           'مدة حظر تسجيل الدخول (دقيقة)'),
    ('classes_start_time',      '09:00',                       'وقت بدء المحاضرات'),
    ('periods_count',           '3',                           'عدد الفترات اليومية'),
    ('period_duration_hours', '2',                           'مدة الفترة (ساعة)'),
    ('exam_interval',           '2',                           'الفترة بين الإمتحانات (أيام)'),
    ('exam_exams_per_day',      '2',                           'عدد الإمتحانات في اليوم')");

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $fields = ['academic_year', 'session_timeout_minutes',
               'max_teaching_days', 'bf_max_attempts', 'bf_lockout_minutes',
               'classes_start_time', 'periods_count', 'period_duration_hours',
               'exam_interval', 'exam_exams_per_day'];
    try {
        $stmt = $pdo->prepare("INSERT INTO `settings` (`key`, `value`) VALUES (?, ?)
                               ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
        foreach ($fields as $key) {
            $val = trim($_POST[$key] ?? '');
            if ($val !== '') $stmt->execute([$key, $val]);
        }
        logActivity($pdo, 'عدّل إعدادات النظام', $current_user['name'] ?? '');
        $success = 'تم حفظ الإعدادات بنجاح';
    } catch (Exception $e) {
        $error = 'حدث خطأ أثناء الحفظ';
    }
}

// ── Database Backup ─────────────────────────────────────────────────────────
$backup_error = '';
if (isset($_POST['download_backup'])) {
    $backup_pass = $_POST['backup_password'] ?? '';
    $uid = $_SESSION['user_id'] ?? null;
    $admin_row = $uid ? $pdo->prepare("SELECT password FROM users WHERE id = ?") : null;
    if ($admin_row) { $admin_row->execute([$uid]); $admin_row = $admin_row->fetch(); }
    $pass_ok = $admin_row && ($admin_row['password'] === $backup_pass || $admin_row['password'] === md5($backup_pass));

    if (!$pass_ok) {
        $backup_error = 'كلمة المرور غير صحيحة';
    } else {
    $db_name = $pdo->query("SELECT DATABASE()")->fetchColumn();
    $filename = $db_name . '_' . date('Ymd_Hi') . '.sql';

    logActivity($pdo, 'نسخ احتياطي لقاعدة البيانات', $current_user['name'] ?? '');

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    ob_end_clean();

    echo "-- Database Backup: $db_name\n";
    echo '-- Generated: ' . date('Y-m-d H:i:s') . "\n";
    echo "-- Generator: \xd9\x86\xd8\xb8\xd8\xa7\xd9\x85 \xd8\xa7\xd9\x84\xd8\xac\xd8\xaf\xd9\x88\xd9\x84 \xd8\xa7\xd9\x84\xd8\xaf\xd8\xb1\xd8\xa7\xd8\xb3\xd9\x8a\n\n";
    echo "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        echo "-- ----------------------------\n";
        echo "-- Table: `$table`\n";
        echo "-- ----------------------------\n";
        echo "DROP TABLE IF EXISTS `$table`;\n";
        $row = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
        echo $row[1] . ";\n\n";

        $cols = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
        $col_list = '`' . implode('`, `', $cols) . '`';

        $all_rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_NUM);
        if ($all_rows) {
            $batch = [];
            foreach ($all_rows as $data_row) {
                $vals = array_map(function ($v) {
                    if ($v === null) return 'NULL';
                    return "'" . str_replace(
                        ['\\', "'",  "\n",  "\r",  "\x00", "\x1a"],
                        ['\\\\', "\\'", '\\n', '\\r', '\\0',   '\\Z'],
                        $v
                    ) . "'";
                }, $data_row);
                $batch[] = '(' . implode(', ', $vals) . ')';

                if (count($batch) >= 100) {
                    echo "INSERT INTO `$table` ($col_list) VALUES\n" . implode(",\n", $batch) . ";\n";
                    $batch = [];
                }
            }
            if ($batch) {
                echo "INSERT INTO `$table` ($col_list) VALUES\n" . implode(",\n", $batch) . ";\n";
            }
            echo "\n";
        }
    }

    echo "SET FOREIGN_KEY_CHECKS = 1;\n";
    exit;
    } // end pass_ok
}
// ─────────────────────────────────────────────────────────────────────────────

$s = getSettings($pdo);
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>إعدادات النظام - لوحة التحكم</title>
    <link rel="stylesheet" href="../assets/CSS/style.css">
    <link href="../assets/fonts/cairo.css" rel="stylesheet">
</head>
<body class="font-sans antialiased bg-gray-50">

<?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 overflow-y-auto pt-0">
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="px-6 py-4">
                <h1 class="text-2xl font-bold text-gray-900">إعدادات النظام</h1>
                <p class="text-sm text-gray-600 mt-1">ضبط الإعدادات العامة لنظام الجدول الدراسي</p>
            </div>
        </header>

        <div class="p-4 md:p-6">
            <?php if ($success): ?>
            <div class="mb-5 p-4 bg-green-50 border border-green-200 rounded-custom text-sm text-green-800"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="mb-5 p-4 bg-red-50 border border-red-200 rounded-custom text-sm text-red-800"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <!-- Section 1: College Info -->
                <div class="bg-white rounded-custom shadow border border-gray-200 p-6">
                    <h2 class="text-base font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-100">معلومات الكلية</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">العام الدراسي</label>
                            <input type="text" name="academic_year" value="<?php echo htmlspecialchars($s['academic_year'] ?? '2025-2026'); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary text-sm" placeholder="مثال: 2025-2026">
                        </div>
                    </div>
                </div>

                <!-- Section: Exam Settings -->
                <div class="bg-white rounded-custom shadow border border-gray-200 p-6">
                    <h2 class="text-base font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-100">إعدادات الإمتحانات</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">الفترة بين الإمتحانات</label>
                            <select name="exam_interval" class="w-full px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary text-sm">
                                <option value="2" <?php echo (($s['exam_interval'] ?? '2') == '2') ? 'selected' : ''; ?>>يوم فراغ واحد (كل يومين)</option>
                                <option value="3" <?php echo (($s['exam_interval'] ?? '') == '3') ? 'selected' : ''; ?>>يومان فراغ (كل 3 أيام)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">عدد الإمتحانات في اليوم</label>
                            <input type="number" name="exam_exams_per_day" min="1" max="5"
                                   value="<?php echo (int)($s['exam_exams_per_day'] ?? 2); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary text-sm">
                        </div>
                    </div>
                </div>

                <!-- Section: Scheduling Rules -->
                <div class="bg-white rounded-custom shadow border border-gray-200 p-6">
                    <h2 class="text-base font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-100">إعدادات الجدولة</h2>
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">أقصى أيام التدريس في الأسبوع</label>
                            <input type="number" name="max_teaching_days" min="1" max="6"
                                   value="<?php echo (int)($s['max_teaching_days'] ?? 4); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary text-sm">
                        </div>
                        <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">وقت بدء المحاضرات</label>
                                <input type="time" id="classes_start_time" name="classes_start_time" step="3600"
                                       value="<?php echo htmlspecialchars($s['classes_start_time'] ?? '09:00'); ?>"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary text-sm"
                                       oninput="updatePeriodPreview()">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">عدد الفترات اليومية</label>
                                <input type="number" id="periods_count" name="periods_count" min="1" max="6"
                                       value="<?php echo (int)($s['periods_count'] ?? 3); ?>"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary text-sm"
                                       oninput="updatePeriodPreview()">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">مدة الفترة (ساعة)</label>
                                <input type="number" id="period_duration_hours" name="period_duration_hours" min="0.5" max="3" step="0.5"
                                       value="<?php echo htmlspecialchars($s['period_duration_hours'] ?? '2'); ?>"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary text-sm"
                                       oninput="updatePeriodPreview()">
                                <p class="text-xs text-gray-400 mt-1">مدة كل فترة دراسية بالساعات</p>
                            </div>
                        </div>
                        <div id="period_preview" class="flex flex-wrap gap-2 pt-1"></div>
                        <script>
                        function updatePeriodPreview() {
                            const start = document.getElementById('classes_start_time').value || '09:00';
                            const count = parseInt(document.getElementById('periods_count').value) || 3;
                            const dur   = (parseFloat(document.getElementById('period_duration_hours').value) || 2) * 60;
                            const [h, m] = start.split(':').map(Number);
                            let base = h * 60 + m;
                            const fmt = (mins) => String(Math.floor(mins/60)).padStart(2,'0') + ':' + String(mins%60).padStart(2,'0');
                            let html = '';
                            for (let i = 0; i < count && i < 8; i++) {
                                html += `<span class="text-xs bg-primary/10 text-primary px-2 py-1 rounded">${fmt(base + i*dur)} - ${fmt(base + (i+1)*dur)}</span>`;
                            }
                            document.getElementById('period_preview').innerHTML = html;
                        }
                        updatePeriodPreview();
                        </script>
                    </div>
                </div>

                <!-- Section: Security -->
                <div class="bg-white rounded-custom shadow border border-gray-200 p-6">
                    <h2 class="text-base font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-100">إعدادات الأمان</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">مدة انتهاء الجلسة (دقيقة)</label>
                            <input type="number" name="session_timeout_minutes" min="5" max="1440"
                                   value="<?php echo (int)($s['session_timeout_minutes'] ?? 60); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary text-sm">
                            <p class="text-xs text-gray-400 mt-1">يُطبَّق على الجلسة التالية بعد تسجيل الدخول</p>
                        </div>
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">أقصى محاولات تسجيل الدخول</label>
                                <input type="number" name="bf_max_attempts" min="3" max="20"
                                       value="<?php echo (int)($s['bf_max_attempts'] ?? 10); ?>"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">مدة الحظر (دقيقة)</label>
                                <input type="number" name="bf_lockout_minutes" min="1" max="60"
                                       value="<?php echo (int)($s['bf_lockout_minutes'] ?? 5); ?>"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary text-sm">
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <button type="submit" name="save_settings"
                            class="px-4 py-2 bg-primary text-white rounded-custom hover:bg-primary/90 transition-colors font-medium">
                        حفظ الإعدادات
                    </button>
                </div>
            </form>

            <!-- Section: Database Backup -->
            <form method="POST" class="mt-6">
                <div class="bg-white rounded-custom shadow border border-gray-200 p-6">
                    <h2 class="text-base font-semibold text-gray-900 mb-1 pb-2 border-b border-gray-100">نسخة احتياطية من قاعدة البيانات</h2>
                    <p class="text-sm text-gray-500 mt-3 mb-4">
                        تنزيل نسخة احتياطية كاملة بصيغة <code class="bg-gray-100 px-1 rounded text-xs">.sql</code>
                        تشمل جميع الجداول والبيانات. تعمل على أي خادم دون الحاجة إلى أدوات إضافية.
                    </p>
                    <?php if ($backup_error): ?>
                        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-custom text-sm text-red-800"><?php echo $backup_error; ?></div>
                    <?php endif; ?>
                    <div class="flex items-center gap-3">
                        <input type="password" name="backup_password" required
                               class="px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary text-sm w-56"
                               placeholder="كلمة مرورك للتأكيد">
                        <button type="submit" name="download_backup"
                                class="px-4 py-2 bg-primary text-white rounded-custom hover:bg-primary/90 transition-colors font-medium">
                            تنزيل النسخة الاحتياطية
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </main>
</div>

<script src="../assets/JS/admin-common.js"></script>
</body>
</html>
