<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

checkAuth();
if (!isAdmin() && !isUser()) { header('Location: view_schedule.php'); exit; }

// Get current user info
$current_user = getCurrentUser();
$perms = isUser() ? getUserPermissions($pdo, $current_user['id'] ?? null) : null;
if (isUser() && !$perms['perm_user_teachers_view']) { header('Location: view_schedule.php'); exit; }

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_teacher'])) {
        if (isUser() && !$perms['perm_user_teachers_add']) { header('Location: teachers.php'); exit; }
        $national_id = trim($_POST['national_id'] ?? '');
        $init_password = $national_id ? md5($national_id) : null;
        $stmt = $pdo->prepare("INSERT INTO teachers (name, title, national_id, password, must_change_password) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['teacher_name'], $_POST['title'], $national_id ?: null, $init_password, $national_id ? 1 : 0]);
        logActivity($pdo, 'أضاف مدرساً: ' . $_POST['teacher_name'], $current_user['name'] ?? '');
        header('Location: teachers.php?success=added');
        exit;
    }
    
    if (isset($_POST['edit_teacher'])) {
        if (!isAdmin() && !(isUser() && $perms['perm_user_teachers_edit'])) { header('Location: teachers.php'); exit; }
        $national_id = trim($_POST['national_id'] ?? '');
        if ($national_id) {
            $stmt = $pdo->prepare("UPDATE teachers SET name = ?, title = ?, national_id = ?, password = ?, must_change_password = 1 WHERE id = ?");
            $stmt->execute([$_POST['teacher_name'], $_POST['title'], $national_id, md5($national_id), $_POST['id']]);
        } else {
            $stmt = $pdo->prepare("UPDATE teachers SET name = ?, title = ?, national_id = NULL WHERE id = ?");
            $stmt->execute([$_POST['teacher_name'], $_POST['title'], $_POST['id']]);
        }
        logActivity($pdo, 'عدّل مدرساً: ' . $_POST['teacher_name'], $current_user['name'] ?? '');
        header('Location: teachers.php?success=updated');
        exit;
    }
    
    if (isset($_POST['delete_teacher'])) {
        if (!isAdmin() && !(isUser() && $perms['perm_user_teachers_delete'])) { header('Location: teachers.php'); exit; }
        // Check if teacher has related subjects
        $check = $pdo->prepare("SELECT COUNT(*) as count FROM subjects WHERE teacher_id = ?");
        $check->execute([$_POST['id']]);
        if ($check->fetch()['count'] > 0) {
            header('Location: teachers.php?error=has_subjects');
            exit;
        }
        $teacher_row = $pdo->prepare("SELECT name FROM teachers WHERE id = ?");
        $teacher_row->execute([$_POST['id']]);
        $del_name = $teacher_row->fetch()['name'] ?? '';
        $stmt = $pdo->prepare("DELETE FROM teachers WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        logActivity($pdo, 'حذف مدرساً: ' . $del_name, $current_user['name'] ?? '');
        header('Location: teachers.php?success=deleted');
        exit;
    }
}

// Get all teachers
$teachers = $pdo->query("SELECT id, name, title, national_id FROM teachers ORDER BY name")->fetchAll();
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>إدارة المدرسين - نظام الجدول الدراسي</title>
    <link rel="stylesheet" href="../assets/CSS/style.css">
    <!-- Local Fonts: Cairo (better for Arabic) -->
    <link href="../assets/fonts/cairo.css" rel="stylesheet"/>
</head>
<body class="font-sans antialiased bg-gray-50">

<?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 overflow-y-auto pt-0">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="px-6 py-4">
                <h1 class="text-2xl font-bold text-gray-900">المدرسين</h1>
                <p class="text-sm text-gray-600 mt-1">إدارة قائمة المدرسين في النظام</p>
            </div>
        </header>

        <!-- Content -->
        <div class="p-3 md:p-6">
            <!-- Success Message -->
            <?php if (isset($_GET['error']) && $_GET['error'] === 'has_subjects'): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-custom">
                    <p class="text-sm text-red-800">لا يمكن حذف هذا المدرس لأنه مرتبط بمواد دراسية</p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['success'])): ?>
                <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-custom">
                    <p class="text-sm text-green-800">
                        <?php
                        switch ($_GET['success']) {
                            case 'added': echo 'تم إضافة المدرس بنجاح'; break;
                            case 'updated': echo 'تم تحديث المدرس بنجاح'; break;
                            case 'deleted': echo 'تم حذف المدرس بنجاح'; break;
                        }
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if (isAdmin() || (isUser() && $perms['perm_user_teachers_add'])): ?>
            <!-- Add Teacher Form -->
            <div class="bg-white rounded-custom shadow border border-gray-200 p-4 md:p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">إضافة مدرس جديد</h2>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4">
                    <input type="text" name="teacher_name" placeholder="اسم المدرس" required
                           class="px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary">
                    <select name="title" required class="px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary">
                        <option value="">اختر المؤهل</option>
                        <option value="دكتور">دكتور</option>
                        <option value="أستاذ">أستاذ</option>
                        <option value="مهندس">مهندس</option>
                    </select>
                    <input type="text" name="national_id" placeholder="الرقم الوطني (اختياري)"
                           class="px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary">
                    <button type="submit" name="add_teacher" class="px-6 py-2 bg-primary text-white rounded-custom hover:bg-primary/90 transition-colors">
                        إضافة مدرس
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <!-- Teachers Table -->
            <div class="bg-white rounded-custom shadow border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-right">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">اسم المدرس</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">المؤهل</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">الرقم الوطني</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">عدد المحاضرات</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($teachers as $teacher): ?>
                                <?php
                                // Get schedule count for this teacher
                                $schedule_count = $pdo->prepare("SELECT COUNT(*) as count FROM schedules WHERE teacher_id = ?");
                                $schedule_count->execute([$teacher['id']]);
                                $count = $schedule_count->fetch()['count'];
                                ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($teacher['name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($teacher['title'] ?? ''); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono">
                                        <?php echo htmlspecialchars($teacher['national_id'] ?? '—'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $count; ?>  محاضرة
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <div class="flex gap-2">
                                            <?php if (isAdmin() || (isUser() && $perms['perm_user_teachers_edit'])): ?>
                                            <button onclick="editTeacher(<?php echo $teacher['id']; ?>, '<?php echo htmlspecialchars(addslashes($teacher['name'])); ?>', '<?php echo htmlspecialchars($teacher['title'] ?? ''); ?>', '<?php echo htmlspecialchars($teacher['national_id'] ?? ''); ?>')"
                                                    class="text-blue-600 hover:text-blue-900 font-medium">تعديل</button>
                                            <?php endif; ?>
                                            <?php if (isAdmin() || (isUser() && $perms['perm_user_teachers_delete'])): ?>
                                            <button type="button" onclick="showDeleteTeacherModal(<?php echo $teacher['id']; ?>)" class="text-red-600 hover:text-red-900 font-medium">حذف</button>
                                            <?php endif; ?>
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
            <h3 class="text-lg font-semibold text-gray-900 mb-4">تعديل المدرس</h3>
            <form method="POST">
                <input type="hidden" name="id" id="editId">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">اسم المدرس</label>
                    <input type="text" name="teacher_name" id="editTeacherName" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">المؤهل</label>
                    <select name="title" id="editTitle" required class="w-full px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary">
                        <option value="">اختر المؤهل</option>
                        <option value="دكتور">دكتور</option>
                        <option value="أستاذ">أستاذ</option>
                        <option value="مهندس">مهندس</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">الرقم الوطني</label>
                    <input type="text" name="national_id" id="editNationalId"
                           class="w-full px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary"
                           placeholder="الرقم الوطني (اختياري)">
                </div>
                <div class="flex gap-3">
                    <button type="submit" name="edit_teacher" class="flex-1 px-4 py-2 bg-primary text-white rounded-custom hover:bg-primary/90 transition-colors">
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
<form id="deleteTeacherForm" method="POST" style="display:none;">
    <input type="hidden" name="id" id="deleteTeacherId">
    <input type="hidden" name="delete_teacher" value="1">
</form>

<!-- Delete Confirmation Modal -->
<div id="deleteTeacherModal" class="hidden fixed inset-0 bg-gray-600/50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-6 border w-[90%] max-w-sm shadow-lg rounded-custom bg-white">
        <div class="text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 mb-2">حذف المدرس</h3>
            <p class="text-sm text-gray-600 mb-6">هل أنت متأكد من حذف هذا المدرس؟ لا يمكن التراجع عن هذا الإجراء.</p>
            <div class="flex gap-3">
                <button type="button" onclick="document.getElementById('deleteTeacherForm').submit()"
                        class="flex-1 px-4 py-2 bg-red-600 text-white rounded-custom hover:bg-red-700 transition-colors font-medium">
                    حذف
                </button>
                <button type="button" onclick="closeDeleteTeacherModal()"
                        class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-custom hover:bg-gray-300 transition-colors font-medium">
                    إلغاء
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function showDeleteTeacherModal(id) {
    document.getElementById('deleteTeacherId').value = id;
    document.getElementById('deleteTeacherModal').classList.remove('hidden');
}
function closeDeleteTeacherModal() {
    document.getElementById('deleteTeacherModal').classList.add('hidden');
}
</script>

<script src="../assets/JS/admin-common.js"></script>
<script src="../assets/JS/teachers.js"></script>
</body>
</html>
