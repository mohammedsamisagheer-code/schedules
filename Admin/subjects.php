<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

// Check if user is admin
checkAuth('admin');
if (!isAdmin()) { header('Location: view_schedule.php'); exit; }

// Get current user info
$current_user = getCurrentUser();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_subject'])) {
        $requires = !empty($_POST['requires_subject_id']) ? $_POST['requires_subject_id'] : null;
        $stmt = $pdo->prepare("INSERT INTO subjects (subject_code, subject_name, term, teacher_id, priority, requires_subject_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['subject_code'], $_POST['subject_name'], $_POST['term'], $_POST['teacher_id'], $_POST['priority'], $requires]);
        logActivity($pdo, 'أضاف مادة: ' . $_POST['subject_name'], $current_user['name'] ?? '');
        header('Location: subjects.php?success=added');
        exit;
    }
    
    if (isset($_POST['edit_subject'])) {
        $requires = !empty($_POST['requires_subject_id']) ? $_POST['requires_subject_id'] : null;
        $stmt = $pdo->prepare("UPDATE subjects SET subject_code = ?, subject_name = ?, term = ?, teacher_id = ?, priority = ?, requires_subject_id = ? WHERE id = ?");
        $stmt->execute([$_POST['subject_code'], $_POST['subject_name'], $_POST['term'], $_POST['teacher_id'], $_POST['priority'], $requires, $_POST['id']]);
        logActivity($pdo, 'عدّل مادة: ' . $_POST['subject_name'], $current_user['name'] ?? '');
        header('Location: subjects.php?success=updated');
        exit;
    }
    
    if (isset($_POST['delete_subject'])) {
        $check = $pdo->prepare("SELECT COUNT(*) as count FROM schedules WHERE subject_id = ?");
        $check->execute([$_POST['id']]);
        if ($check->fetch()['count'] > 0) {
            header('Location: subjects.php?error=has_schedule');
            exit;
        }
        $subj_row = $pdo->prepare("SELECT subject_name FROM subjects WHERE id = ?");
        $subj_row->execute([$_POST['id']]);
        $del_name = $subj_row->fetch()['subject_name'] ?? '';
        $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        logActivity($pdo, 'حذف مادة: ' . $del_name, $current_user['name'] ?? '');
        header('Location: subjects.php?success=deleted');
        exit;
    }
}

// Get all subjects with teacher info
$subjects = $pdo->query("SELECT s.*, t.name as teacher_name, t.title as teacher_title FROM subjects s LEFT JOIN teachers t ON s.teacher_id = t.id ORDER BY s.term, s.subject_name")->fetchAll();

// Get all teachers for dropdown
$teachers = $pdo->query("SELECT id, name, title FROM teachers ORDER BY name")->fetchAll();
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>إدارة المواد الدراسية - نظام الجدول الدراسي</title>
    <link rel="stylesheet" href="../assets/CSS/style.css">
    <!-- Local Fonts: Cairo (better for Arabic) -->
    <link href="../assets/fonts/cairo.css" rel="stylesheet"/>
</head>
<body class="font-sans antialiased bg-gray-50">

<!-- Mobile Top Bar -->
<div class="md:hidden bg-white shadow-sm border-b border-gray-200 px-4 py-3 flex items-center justify-between sticky top-0 z-40">
    <div class="flex items-center gap-3">
        <img src="../assets/images/logo.png" alt="logo" class="w-10 h-10 object-contain">
        <span class="font-bold text-lg tracking-tight">لوحة التحكم</span>
    </div>
    <button onclick="toggleSidebar()" class="p-2 rounded-custom hover:bg-gray-100">
        <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
    </button>
</div>

<!-- Sidebar Overlay (mobile) -->
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
        <!-- Admin Info -->
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
                <li>
                    <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-custom">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                        </svg>
                        الرئيسية
                    </a>
                </li>
                <li>
                    <a href="subjects.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium bg-primary/10 text-primary rounded-custom">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                        المواد الدراسية
                    </a>
                </li>
                <li>
                    <a href="teachers.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-custom">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        المدرسين
                    </a>
                </li>
                <li>
                    <a href="rooms.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-custom">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                        القاعات
                    </a>
                </li>
                <li>
                    <a href="my_schedule.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-custom">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        جدولي
                    </a>
                </li>
                <li>
                    <a href="view_schedule.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-custom">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        عرض الجدول العام
                    </a>
                </li>
                <li>
                    <a href="exam_schedule.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-custom">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        جدول الإمتحانات
                    </a>
                </li>
                <li>
                    <a href="users.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-custom">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        إدارة المستخدمين
                    </a>
                </li>
                <li>
                    <a href="settings.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-custom">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                        إعدادات النظام
                    </a>
                </li>
                <li>
                    <a href="account.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-custom">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        حسابي
                    </a>
                </li>
                <li>
                    <a href="../logout.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-red-600 hover:bg-red-50 rounded-custom">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        تسجيل الخروج
                    </a>
                </li>
            </ul>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 overflow-y-auto pt-0">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="px-6 py-4">
                <h1 class="text-2xl font-bold text-gray-900">المواد الدراسية</h1>
                <p class="text-sm text-gray-600 mt-1">إدارة المواد الدراسية للفصول من الثالث إلى الثامن</p>
            </div>
        </header>

        <!-- Content -->
        <div class="p-3 md:p-6">
            <!-- Success Message -->
            <?php if (isset($_GET['error']) && $_GET['error'] === 'has_schedule'): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-custom">
                    <p class="text-sm text-red-800">لا يمكن حذف هذه المادة لأنها مرتبطة بحصص في الجدول</p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['success'])): ?>
                <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-custom">
                    <p class="text-sm text-green-800">
                        <?php
                        switch ($_GET['success']) {
                            case 'added': echo 'تم إضافة المادة بنجاح'; break;
                            case 'updated': echo 'تم تحديث المادة بنجاح'; break;
                            case 'deleted': echo 'تم حذف المادة بنجاح'; break;
                        }
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <!-- Add Subject Form -->
            <div class="bg-white rounded-custom shadow border border-gray-200 p-4 md:p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">إضافة مادة جديدة</h2>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4">
                    <input type="text" name="subject_code" placeholder="كود المادة" required
                           class="px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary">
                    <input type="text" name="subject_name" placeholder="اسم المادة" required
                           class="px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary">
                    <select name="term" required class="px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary">
                        <option value="">اختر الفصل</option>
                        <option value="3">الفصل الثالث</option>
                        <option value="4">الفصل الرابع</option>
                        <option value="5">الفصل الخامس</option>
                        <option value="6">الفصل السادس</option>
                        <option value="7">الفصل السابع</option>
                        <option value="8">الفصل الثامن</option>
                    </select>
                    <select name="teacher_id" required class="px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary">
                        <option value="">اختر المدرس</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?php echo $teacher['id']; ?>"><?php echo getTitleAbbr($teacher['title']) . htmlspecialchars($teacher['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="priority" required class="px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary">
                        <option value="">عدد المحاضرات</option>
                        <option value="1">محاضرة في الأسبوع</option>
                        <option value="2">محاضرتان في الأسبوع</option>
                    </select>
                    <select name="requires_subject_id" class="px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary">
                        <option value="">مادة متطلبة (اختياري)</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['subject_name']) . ' (ف' . $s['term'] . ')'; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="add_subject" class="md:col-span-2 lg:col-span-4 px-6 py-2 bg-primary text-white rounded-custom hover:bg-primary/90 transition-colors">
                        إضافة مادة
                    </button>
                </form>
            </div>

            <!-- Subjects Table -->
            <div class="bg-white rounded-custom shadow border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-right">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">كود المادة</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">اسم المادة</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">الفصل</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">المدرس</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">المحاضرات/أسبوع</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">المادة المتطلبة</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($subjects as $subject): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($subject['subject_code']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($subject['subject_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        الفصل <?php echo htmlspecialchars($subject['term']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($subject['teacher_name'] ? getTitleAbbr($subject['teacher_title']) . $subject['teacher_name'] : 'غير محدد'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo ($subject['priority'] ?? 2) == 1 ? 'محاضرة واحدة' : 'محاضرتان'; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php
                                        $req_name = '-';
                                        if (!empty($subject['requires_subject_id'])) {
                                            foreach ($subjects as $s) {
                                                if ($s['id'] == $subject['requires_subject_id']) {
                                                    $req_name = htmlspecialchars($s['subject_name']) . ' (ف' . $s['term'] . ')';
                                                    break;
                                                }
                                            }
                                        }
                                        echo $req_name;
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <div class="flex gap-2">
                                            <button onclick="editSubject(<?php echo $subject['id']; ?>, '<?php echo htmlspecialchars($subject['subject_code']); ?>', '<?php echo htmlspecialchars($subject['subject_name']); ?>', <?php echo $subject['term']; ?>, <?php echo $subject['teacher_id']; ?>, <?php echo $subject['priority'] ?? 2; ?>, <?php echo $subject['requires_subject_id'] ?? 'null'; ?>)"
                                                    class="text-blue-600 hover:text-blue-900 font-medium">تعديل</button>
                                            <button type="button" onclick="showDeleteSubjectModal(<?php echo $subject['id']; ?>)" class="text-red-600 hover:text-red-900 font-medium">حذف</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Edit Modal -->
<div id="editModal" class="hidden fixed inset-0 bg-gray-600/50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-10 md:top-20 mx-auto p-5 border w-[90%] max-w-md shadow-lg rounded-custom bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">تعديل المادة</h3>
            <form method="POST">
                <input type="hidden" name="id" id="editId">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">كود المادة</label>
                    <input type="text" name="subject_code" id="editSubjectCode" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">اسم المادة</label>
                    <input type="text" name="subject_name" id="editSubjectName" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">الفصل</label>
                    <select name="term" id="editTerm" required class="w-full px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary">
                        <option value="3">الفصل الثالث</option>
                        <option value="4">الفصل الرابع</option>
                        <option value="5">الفصل الخامس</option>
                        <option value="6">الفصل السادس</option>
                        <option value="7">الفصل السابع</option>
                        <option value="8">الفصل الثامن</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">المدرس</label>
                    <select name="teacher_id" id="editTeacherId" required class="w-full px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary">
                        <option value="">اختر المدرس</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?php echo $teacher['id']; ?>"><?php echo getTitleAbbr($teacher['title']) . htmlspecialchars($teacher['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">عدد المحاضرات</label>
                    <select name="priority" id="editPriority" required class="w-full px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary">
                        <option value="1">محاضرة في الأسبوع</option>
                        <option value="2">محاضرتان في الأسبوع</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">المادة المتطلبة</label>
                    <select name="requires_subject_id" id="editRequiresSubject" class="w-full px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary">
                        <option value="">بدون</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['subject_name']) . ' (ف' . $s['term'] . ')'; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex gap-3">
                    <button type="submit" name="edit_subject" class="flex-1 px-4 py-2 bg-primary text-white rounded-custom hover:bg-primary/90 transition-colors">
                        حفظ التعديلات
                    </button>
                    <button type="button" onclick="closeEditModal()" class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-custom hover:bg-gray-300 transition-colors">
                        إلغاء
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hidden delete form -->
<form id="deleteSubjectForm" method="POST" style="display:none;">
    <input type="hidden" name="id" id="deleteSubjectId">
    <input type="hidden" name="delete_subject" value="1">
</form>

<!-- Delete Confirmation Modal -->
<div id="deleteSubjectModal" class="hidden fixed inset-0 bg-gray-600/50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-6 border w-[90%] max-w-sm shadow-lg rounded-custom bg-white">
        <div class="text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 mb-2">حذف المادة</h3>
            <p class="text-sm text-gray-600 mb-6">هل أنت متأكد من حذف هذه المادة؟ لا يمكن التراجع عن هذا الإجراء.</p>
            <div class="flex gap-3">
                <button type="button" onclick="document.getElementById('deleteSubjectForm').submit()"
                        class="flex-1 px-4 py-2 bg-red-600 text-white rounded-custom hover:bg-red-700 transition-colors font-medium">
                    حذف
                </button>
                <button type="button" onclick="closeDeleteSubjectModal()"
                        class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-custom hover:bg-gray-300 transition-colors font-medium">
                    إلغاء
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function showDeleteSubjectModal(id) {
    document.getElementById('deleteSubjectId').value = id;
    document.getElementById('deleteSubjectModal').classList.remove('hidden');
}
function closeDeleteSubjectModal() {
    document.getElementById('deleteSubjectModal').classList.add('hidden');
}
</script>

<script src="../assets/JS/admin-common.js"></script>
<script src="../assets/JS/subjects.js"></script>
</body>
</html>
