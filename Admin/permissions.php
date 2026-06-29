<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

checkAuth('admin');
if (!isAdmin()) { header('Location: view_schedule.php'); exit; }

$current_user = getCurrentUser();

// Seed all permission rows into settings table
$pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
    `key`   VARCHAR(100) NOT NULL PRIMARY KEY,
    `value` TEXT         NOT NULL DEFAULT '',
    `label` VARCHAR(200) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$perm_defaults = [
    'perm_user_subjects_view'    => ['1', 'عرض المواد الدراسية'],
    'perm_user_subjects_add'     => ['1', 'إضافة مواد دراسية'],
    'perm_user_subjects_edit'    => ['0', 'تعديل المواد الدراسية'],
    'perm_user_subjects_delete'  => ['0', 'حذف المواد الدراسية'],
    'perm_user_teachers_view'    => ['1', 'عرض الأساتذة'],
    'perm_user_teachers_add'     => ['1', 'إضافة أساتذة'],
    'perm_user_teachers_edit'    => ['0', 'تعديل الأساتذة'],
    'perm_user_teachers_delete'  => ['0', 'حذف الأساتذة'],
    'perm_user_rooms_view'       => ['1', 'عرض القاعات'],
    'perm_user_rooms_add'        => ['1', 'إضافة قاعات'],
    'perm_user_rooms_edit'       => ['0', 'تعديل القاعات'],
    'perm_user_rooms_delete'     => ['0', 'حذف القاعات'],
    'perm_user_view_schedule'    => ['1', 'عرض الجدول الدراسي'],
    'perm_user_exam_schedule'    => ['1', 'عرض جدول الإمتحانات'],
];
$ins = $pdo->prepare("INSERT IGNORE INTO settings (`key`, `value`, `label`) VALUES (?, ?, ?)");
foreach ($perm_defaults as $key => [$val, $label]) {
    $ins->execute([$key, $val, $label]);
}

$users_list = $pdo->query("SELECT id, name, title FROM users WHERE role = 'user' ORDER BY name")->fetchAll();
$selected_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$selected_user_name = '';
foreach ($users_list as $u) {
    if ($u['id'] == $selected_user_id) {
        $selected_user_name = $u['name'];
        break;
    }
}

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_permissions'])) {
    $target_user_id = isset($_POST['target_user_id']) ? (int)$_POST['target_user_id'] : 0;
    if ($target_user_id > 0) {
        $upd = $pdo->prepare("INSERT INTO user_permissions (user_id, perm_key, value) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE value = VALUES(value)");
        foreach (array_keys($perm_defaults) as $key) {
            $value = isset($_POST[$key]) ? '1' : '0';
            $upd->execute([$target_user_id, $key, $value]);
        }
        logActivity($pdo, 'عدّل صلاحيات المستخدم: ' . $selected_user_name, $current_user['name'] ?? '');
        $success = 'تم حفظ صلاحيات المستخدم بنجاح.';
    } else {
        $upd = $pdo->prepare("INSERT INTO settings (`key`, `value`, `label`) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
        foreach (array_keys($perm_defaults) as $key) {
            $value = isset($_POST[$key]) ? '1' : '0';
            $label = $perm_defaults[$key][1];
            $upd->execute([$key, $value, $label]);
        }
        logActivity($pdo, 'عدّل الصلاحيات الافتراضية', $current_user['name'] ?? '');
        $success = 'تم حفظ الصلاحيات الافتراضية بنجاح.';
    }
}

if ($selected_user_id > 0) {
    $perms = getUserPermissions($pdo, $selected_user_id);
} else {
    $perms = getUserPermissions($pdo);
}

$sections = [
    'subjects' => [
        'title' => 'المواد الدراسية',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>',
        'perms' => ['view' => 'عرض', 'add' => 'إضافة', 'edit' => 'تعديل', 'delete' => 'حذف'],
    ],
    'teachers' => [
        'title' => 'الأساتذة',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>',
        'perms' => ['view' => 'عرض', 'add' => 'إضافة', 'edit' => 'تعديل', 'delete' => 'حذف'],
    ],
    'rooms' => [
        'title' => 'القاعات',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>',
        'perms' => ['view' => 'عرض', 'add' => 'إضافة', 'edit' => 'تعديل', 'delete' => 'حذف'],
    ],
];
$schedule_perms = [
    'perm_user_view_schedule' => 'عرض الجدول الدراسي',
    'perm_user_exam_schedule' => 'عرض جدول الإمتحانات',
];
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>صلاحيات المستخدمين - نظام الجدول الدراسي</title>
    <link rel="stylesheet" href="../assets/CSS/style.css">
    <link href="../assets/fonts/cairo.css" rel="stylesheet"/>
</head>
<body class="font-sans antialiased bg-gray-50">

<?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 overflow-y-auto">
        <div class="p-6 max-w-3xl mx-auto">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900">صلاحيات المستخدمين</h1>
                <p class="text-sm text-gray-500 mt-1">
                    <?php if ($selected_user_id > 0 && $selected_user_name): ?>
                        تعديل صلاحيات المستخدم: <strong><?php echo htmlspecialchars($selected_user_name); ?></strong>
                    <?php else: ?>
                        تحكم في الصلاحيات الافتراضية التي يحصل عليها المستخدمون الجدد
                    <?php endif; ?>
                </p>
            </div>

            <?php if ($success): ?>
            <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-custom text-green-800 text-sm"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-custom text-red-800 text-sm"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- User Selector -->
            <div class="bg-white rounded-custom shadow border border-gray-200 mb-5">
                <div class="px-6 py-4">
                    <label for="userSelector" class="block text-sm font-medium text-gray-700 mb-2">اختر مستخدماً لتعديل صلاحياته</label>
                    <select id="userSelector" onchange="window.location.href='permissions.php?user_id='+this.value"
                        class="w-full px-3 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary text-sm bg-white">
                        <option value="0">الإعدادات الافتراضية العامة</option>
                        <?php foreach ($users_list as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo $u['id'] == $selected_user_id ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(getTitleAbbr($u['title']) . $u['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="save_permissions" value="1">
                <input type="hidden" name="target_user_id" value="<?php echo $selected_user_id; ?>">

                <?php foreach ($sections as $sec_key => $sec): ?>
                <div class="bg-white rounded-custom shadow border border-gray-200 mb-5">
                    <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100">
                        <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><?php echo $sec['icon']; ?></svg>
                        <h2 class="font-semibold text-gray-800"><?php echo $sec['title']; ?></h2>
                    </div>
                    <div class="px-6 py-4 grid grid-cols-2 gap-4">
                        <?php foreach ($sec['perms'] as $perm_suffix => $perm_label): ?>
                        <?php $key = "perm_user_{$sec_key}_{$perm_suffix}"; ?>
                        <label class="flex items-center gap-3 p-3 rounded-custom border border-gray-100 hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="<?php echo $key; ?>" id="<?php echo $key; ?>"
                                   class="w-4 h-4 rounded border-gray-300 text-primary accent-primary cursor-pointer"
                                   <?php echo $perms[$key] ? 'checked' : ''; ?>>
                            <span class="text-sm font-medium text-gray-700"><?php echo $perm_label; ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Schedule access -->
                <div class="bg-white rounded-custom shadow border border-gray-200 mb-6">
                    <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100">
                        <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <h2 class="font-semibold text-gray-800">الجداول</h2>
                    </div>
                    <div class="px-6 py-4 grid grid-cols-2 gap-4">
                        <?php foreach ($schedule_perms as $key => $label): ?>
                        <label class="flex items-center gap-3 p-3 rounded-custom border border-gray-100 hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="<?php echo $key; ?>" id="<?php echo $key; ?>"
                                   class="w-4 h-4 rounded border-gray-300 text-primary accent-primary cursor-pointer"
                                   <?php echo $perms[$key] ? 'checked' : ''; ?>>
                            <span class="text-sm font-medium text-gray-700"><?php echo $label; ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="submit" class="w-full px-6 py-3 bg-primary text-white rounded-custom hover:bg-primary/90 font-medium transition-colors">
                    <?php echo $selected_user_id > 0 ? 'حفظ صلاحيات المستخدم' : 'حفظ الصلاحيات الافتراضية'; ?>
                </button>
            </form>
        </div>
    </main>
</div>

<script src="../assets/JS/admin-common.js"></script>
</body>
</html>
