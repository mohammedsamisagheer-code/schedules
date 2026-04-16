<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

checkAuth('admin');
if (!isAdmin()) { header('Location: view_schedule.php'); exit; }
$current_user = getCurrentUser();
$current_id   = $_SESSION['user_id'] ?? null;

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['add_user'])) {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $name     = trim($_POST['name']);
        $title    = trim($_POST['title']);
        $role     = $_POST['role'];

        // Check duplicate username
        $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $chk->execute([$username]);
        if ($chk->fetchColumn() > 0) {
            $error = 'اسم المستخدم مستخدم بالفعل';
        } elseif (empty($username) || empty($password) || empty($name)) {
            $error = 'الاسم واسم المستخدم وكلمة المرور مطلوبة';
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, name, title, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$username, md5($password), $name, $title ?: null, $role]);
            logActivity($pdo, 'أضاف مستخدماً: ' . $name . ' (' . $role . ')', $current_user['name'] ?? '');
            header('Location: users.php?success=added');
            exit;
        }
    }

    if (isset($_POST['edit_user'])) {
        $id       = (int)$_POST['id'];
        $username = trim($_POST['username']);
        $name     = trim($_POST['name']);
        $title    = trim($_POST['title']);
        $role     = $_POST['role'];
        $password = trim($_POST['password']);

        // Check duplicate username excluding self
        $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
        $chk->execute([$username, $id]);
        if ($chk->fetchColumn() > 0) {
            $error = 'اسم المستخدم مستخدم بالفعل';
        } else {
            if (!empty($password)) {
                $stmt = $pdo->prepare("UPDATE users SET username=?, password=?, name=?, title=?, role=? WHERE id=?");
                $stmt->execute([$username, md5($password), $name, $title ?: null, $role, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username=?, name=?, title=?, role=? WHERE id=?");
                $stmt->execute([$username, $name, $title ?: null, $role, $id]);
            }
            logActivity($pdo, 'عدّل مستخدماً: ' . $name, $current_user['name'] ?? '');
            header('Location: users.php?success=updated');
            exit;
        }
    }

    if (isset($_POST['delete_user'])) {
        $id = (int)$_POST['id'];
        if ($id === (int)$current_id) {
            $error = 'لا يمكنك حذف حسابك الخاص';
        } else {
            $del_row = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $del_row->execute([$id]);
            $del_name = $del_row->fetch()['name'] ?? '';
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            logActivity($pdo, 'حذف مستخدماً: ' . $del_name, $current_user['name'] ?? '');
            header('Location: users.php?success=deleted');
            exit;
        }
    }
}

if (isset($_GET['success'])) {
    $msgs = ['added' => 'تم إضافة المستخدم بنجاح', 'updated' => 'تم تحديث المستخدم بنجاح', 'deleted' => 'تم حذف المستخدم بنجاح'];
    $success = $msgs[$_GET['success']] ?? '';
}

$users = $pdo->query("SELECT * FROM users ORDER BY id")->fetchAll();
$titles = ['', 'دكتور', 'أستاذ', 'مهندس'];
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>إدارة المستخدمين - لوحة التحكم</title>
    <link rel="stylesheet" href="../assets/CSS/style.css">
    <link href="../assets/fonts/cairo.css" rel="stylesheet">
</head>
<body class="font-sans antialiased bg-gray-50">

<!-- Mobile Top Bar -->
<div class="md:hidden flex items-center justify-between p-4 bg-white border-b border-gray-200 sticky top-0 z-40">
    <div class="flex items-center gap-2">
        <div class="w-7 h-7 bg-primary rounded-custom flex items-center justify-center">
            <span class="text-white font-bold text-sm">iT</span>
        </div>
        <span class="font-bold text-lg tracking-tight">لوحة التحكم</span>
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
                <li>
                    <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-custom">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        الرئيسية
                    </a>
                </li>
                <li>
                    <a href="subjects.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-custom">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
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
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        القاعات
                    </a>
                </li>
                <li>
                    <a href="my_schedule.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-custom">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        جدولي
                    </a>
                </li>
                <li>
                    <a href="view_schedule.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-custom">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
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
                    <a href="users.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium bg-primary/10 text-primary rounded-custom">
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
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        حسابي
                    </a>
                </li>
                <li>
                    <a href="../logout.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-red-600 hover:bg-red-50 rounded-custom">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                        تسجيل الخروج
                    </a>
                </li>
            </ul>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 overflow-y-auto">
        <div class="p-4 md:p-6 max-w-5xl mx-auto">

            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">إدارة المستخدمين</h1>
                    <p class="text-sm text-gray-500 mt-1">إضافة وتعديل وحذف حسابات المستخدمين</p>
                </div>
                <button onclick="openAddModal()" class="px-4 py-2 bg-primary text-white rounded-custom text-sm font-medium hover:bg-primary/90 shadow-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    إضافة مستخدم
                </button>
            </div>

            <!-- Alerts -->
            <?php if ($success): ?>
                <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-custom">
                    <p class="text-sm text-green-800"><?php echo htmlspecialchars($success); ?></p>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-custom">
                    <p class="text-sm text-red-800"><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php endif; ?>

            <!-- Users Table -->
            <div class="bg-white rounded-custom shadow-sm border border-gray-200">
                <div class="overflow-x-auto">
                    <table class="w-full text-right">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase">#</th>
                                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase">الاسم</th>
                                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase hidden sm:table-cell">اسم المستخدم</th>
                                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase hidden md:table-cell">المؤهل</th>
                                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase">الدور</th>
                                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase text-center">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($users as $u): ?>
                            <tr class="hover:bg-gray-50 transition-colors <?php echo ($u['id'] == $current_id) ? 'bg-primary/5' : ''; ?>">
                                <td class="px-4 py-3 text-sm text-gray-500"><?php echo $u['id']; ?></td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                                            <span class="text-primary text-xs font-bold"><?php echo mb_substr($u['name'], 0, 1); ?></span>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars(getTitleAbbr($u['title']) . $u['name']); ?></p>
                                            <?php if ($u['id'] == $current_id): ?>
                                                <span class="text-xs text-primary font-medium">(أنت)</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 hidden sm:table-cell"><?php echo htmlspecialchars($u['username']); ?></td>
                                <td class="px-4 py-3 text-sm text-gray-600 hidden md:table-cell"><?php echo htmlspecialchars($u['title'] ?? '—'); ?></td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $u['role'] === 'admin' ? 'bg-primary/10 text-primary' : 'bg-gray-100 text-gray-600'; ?>">
                                        <?php echo $u['role'] === 'admin' ? 'مدير' : 'مستخدم'; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <button onclick='openEditModal(<?php echo json_encode($u); ?>)'
                                            class="px-3 py-1.5 text-xs font-medium bg-white border border-gray-200 rounded-custom hover:bg-gray-50 text-gray-700">
                                            تعديل
                                        </button>
                                        <?php if ($u['id'] != $current_id): ?>
                                        <button onclick="openDeleteModal(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars(addslashes($u['name'])); ?>')"
                                            class="px-3 py-1.5 text-xs font-medium bg-red-50 border border-red-200 rounded-custom hover:bg-red-100 text-red-700">
                                            حذف
                                        </button>
                                        <?php else: ?>
                                        <span class="px-3 py-1.5 text-xs text-gray-400">محمي</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">لا يوجد مستخدمون</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
</div>

<!-- Add/Edit Modal -->
<div id="userModal" class="hidden fixed inset-0 bg-gray-600/50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-10 mx-auto p-6 border w-[90%] max-w-lg shadow-lg rounded-custom bg-white mb-10">
        <div class="flex items-center justify-between mb-5">
            <h3 id="modalTitle" class="text-lg font-bold text-gray-900">إضافة مستخدم</h3>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form method="POST" id="userForm">
            <input type="hidden" name="id" id="userId">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <!-- Name -->
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">الاسم الكامل <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="userName" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary text-sm"
                        placeholder="اسم المستخدم الكامل">
                </div>
                <!-- Title -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">المؤهل</label>
                    <select name="title" id="userTitle"
                        class="w-full px-3 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary text-sm bg-white">
                        <option value="">بدون مؤهل</option>
                        <option value="دكتور">دكتور</option>
                        <option value="أستاذ">أستاذ</option>
                        <option value="مهندس">مهندس</option>
                    </select>
                </div>
                <!-- Role -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الدور</label>
                    <select name="role" id="userRole"
                        class="w-full px-3 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary text-sm bg-white">
                        <option value="admin">مدير</option>
                        <option value="user">مستخدم</option>
                    </select>
                </div>
                <!-- Username -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">اسم المستخدم <span class="text-red-500">*</span></label>
                    <input type="text" name="username" id="userUsername" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary text-sm"
                        placeholder="username">
                </div>
                <!-- Password -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" id="passwordLabel">كلمة المرور <span class="text-red-500">*</span></label>
                    <input type="password" name="password" id="userPassword"
                        class="w-full px-3 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary text-sm"
                        placeholder="كلمة المرور">
                    <p id="passwordHint" class="text-xs text-gray-400 mt-1 hidden">اتركها فارغة للإبقاء على كلمة المرور الحالية</p>
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" id="submitBtn"
                    class="flex-1 px-4 py-2 bg-primary text-white rounded-custom hover:bg-primary/90 transition-colors font-medium text-sm">
                    إضافة
                </button>
                <button type="button" onclick="closeModal()"
                    class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-custom hover:bg-gray-300 transition-colors font-medium text-sm">
                    إلغاء
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="hidden fixed inset-0 bg-gray-600/50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-6 border w-[90%] max-w-sm shadow-lg rounded-custom bg-white">
        <div class="text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 mb-1">حذف مستخدم</h3>
            <p class="text-sm text-gray-600 mb-5">هل تريد حذف <strong id="deleteUserName"></strong>؟ لا يمكن التراجع عن هذا الإجراء.</p>
            <div class="flex gap-3">
                <form method="POST" class="flex-1" id="deleteForm">
                    <input type="hidden" name="id" id="deleteUserId">
                    <button type="submit" name="delete_user" value="1"
                        class="w-full px-4 py-2 bg-red-600 text-white rounded-custom hover:bg-red-700 transition-colors font-medium text-sm">
                        تأكيد الحذف
                    </button>
                </form>
                <button type="button" onclick="document.getElementById('deleteModal').classList.add('hidden')"
                    class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-custom hover:bg-gray-300 transition-colors font-medium text-sm">
                    إلغاء
                </button>
            </div>
        </div>
    </div>
</div>

<script src="../assets/JS/admin-common.js"></script>
<script src="../assets/JS/users.js"></script>
</body>
</html>
