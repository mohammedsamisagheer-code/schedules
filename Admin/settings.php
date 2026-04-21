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
    ('exam_interval',           '2',                           'الفترة بين الإمتحانات (أيام)'),
    ('exam_exams_per_day',      '2',                           'عدد الإمتحانات في اليوم')");

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $fields = ['academic_year', 'session_timeout_minutes',
               'max_teaching_days', 'bf_max_attempts', 'bf_lockout_minutes',
               'classes_start_time', 'periods_count',
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

<!-- Mobile Top Bar -->
<div class="md:hidden bg-white shadow-sm border-b border-gray-200 px-4 py-3 flex items-center justify-between sticky top-0 z-40">
    <div class="flex items-center gap-3">
        <img src="../assets/images/logo.png" alt="logo" class="w-10 h-10 object-contain">
        <span class="font-bold text-lg tracking-tight">إعدادات النظام</span>
    </div>
    <button onclick="toggleSidebar()" class="p-2 rounded-custom hover:bg-gray-100">
        <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
    </button>
</div>

<!-- Sidebar Overlay -->
<div id="sidebarOverlay" onclick="toggleSidebar()" class="hidden fixed inset-0 bg-black/50 z-40 md:hidden"></div>

<div class="flex h-screen md:h-screen">
    <!-- Sidebar -->
    <aside id="sidebar" class="fixed md:static md:flex-none inset-y-0 right-0 z-50 w-64 bg-white shadow-lg md:translate-x-0 overflow-y-auto">
        <div class="p-6">
            <div class="flex items-center gap-3">
                <img src="../assets/images/logo.png" alt="logo" class="w-10 h-10 object-contain">
                <span class="font-bold text-xl tracking-tight">لوحة التحكم</span>
            </div>
        </div>
        <div class="px-6 pb-4 border-b border-gray-200">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                </div>
                <div>
                    <p class="font-medium text-gray-900"><?php echo getTitleAbbr($current_user['title']) . htmlspecialchars($current_user['name']); ?></p>
                    <p class="text-sm text-gray-500">مدير النظام</p>
                </div>
            </div>
        </div>
        <nav class="px-4 pb-6 pt-4">
            <ul class="space-y-2">
                <li><a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-custom"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>الرئيسية</a></li>
                <li><a href="subjects.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-custom"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>المواد الدراسية</a></li>
                <li><a href="teachers.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-custom"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>المدرسين</a></li>
                <li><a href="rooms.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-custom"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>القاعات</a></li>
                <li><a href="my_schedule.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-custom"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>جدولي</a></li>
                <li><a href="view_schedule.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-custom"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>عرض الجدول العام</a></li>
                <li><a href="exam_schedule.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-custom"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>جدول الامتحانات</a></li>
                <li><a href="users.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-custom"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>إدارة المستخدمين</a></li>
                <li><a href="settings.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium bg-primary/10 text-primary rounded-custom"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>إعدادات النظام</a></li>
                <li><a href="account.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-custom"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>حسابي</a></li>
                <li><a href="../logout.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-red-600 hover:bg-red-50 rounded-custom"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>تسجيل الخروج</a></li>
            </ul>
        </nav>
    </aside>

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
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">عدد الفترات اليومية</label>
                                <input type="number" id="periods_count" name="periods_count" min="1" max="6"
                                       value="<?php echo (int)($s['periods_count'] ?? 3); ?>"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary text-sm"
                                       oninput="updatePeriodPreview()">
                                <p class="text-xs text-gray-400 mt-1">كل فترة مدتها ساعتان</p>
                            </div>
                        </div>
                        <div id="period_preview" class="flex flex-wrap gap-2 pt-1"></div>
                        <script>
                        function updatePeriodPreview() {
                            const start = document.getElementById('classes_start_time').value || '09:00';
                            const count = parseInt(document.getElementById('periods_count').value) || 3;
                            const [h, m] = start.split(':').map(Number);
                            let base = h * 60 + m;
                            const fmt = (mins) => String(Math.floor(mins/60)).padStart(2,'0') + ':' + String(mins%60).padStart(2,'0');
                            let html = '';
                            for (let i = 0; i < count && i < 8; i++) {
                                html += `<span class="text-xs bg-primary/10 text-primary px-2 py-1 rounded">${fmt(base + i*120)} - ${fmt(base + (i+1)*120)}</span>`;
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
        </div>
    </main>
</div>

<script src="../assets/JS/admin-common.js"></script>
</body>
</html>
