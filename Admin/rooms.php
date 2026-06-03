<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

checkAuth();
if (!isAdmin() && !isUser()) { header('Location: view_schedule.php'); exit; }

// Get current user info
$current_user = getCurrentUser();
$perms = isUser() ? getUserPermissions($pdo, $current_user['id'] ?? null) : null;
if (isUser() && !$perms['perm_user_rooms_view']) { header('Location: view_schedule.php'); exit; }

// Ensure room type columns exist
try { $pdo->exec("ALTER TABLE rooms ADD COLUMN exam_only TINYINT(1) NOT NULL DEFAULT 0"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE rooms ADD COLUMN class_only TINYINT(1) NOT NULL DEFAULT 0"); } catch (Exception $e) {}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_room'])) {
        if (isUser() && !$perms['perm_user_rooms_add']) { header('Location: rooms.php'); exit; }
        $exam_only  = ($_POST['room_type'] ?? '') === 'exam_only'  ? 1 : 0;
        $class_only = ($_POST['room_type'] ?? '') === 'class_only' ? 1 : 0;
        $stmt = $pdo->prepare("INSERT INTO rooms (name, exam_only, class_only) VALUES (?, ?, ?)");
        $stmt->execute([$_POST['room_name'], $exam_only, $class_only]);
        logActivity($pdo, 'أضاف قاعة: ' . $_POST['room_name'], $current_user['name'] ?? '');
        header('Location: rooms.php?success=added');
        exit;
    }
    
    if (isset($_POST['edit_room'])) {
        if (!isAdmin() && !(isUser() && $perms['perm_user_rooms_edit'])) { header('Location: rooms.php'); exit; }
        $exam_only  = ($_POST['room_type'] ?? '') === 'exam_only'  ? 1 : 0;
        $class_only = ($_POST['room_type'] ?? '') === 'class_only' ? 1 : 0;
        $stmt = $pdo->prepare("UPDATE rooms SET name = ?, exam_only = ?, class_only = ? WHERE id = ?");
        $stmt->execute([$_POST['room_name'], $exam_only, $class_only, $_POST['id']]);
        logActivity($pdo, 'عدّل قاعة: ' . $_POST['room_name'], $current_user['name'] ?? '');
        header('Location: rooms.php?success=updated');
        exit;
    }
    
    if (isset($_POST['delete_room'])) {
        if (!isAdmin() && !(isUser() && $perms['perm_user_rooms_delete'])) { header('Location: rooms.php'); exit; }
        $check = $pdo->prepare("SELECT COUNT(*) as count FROM schedules WHERE room_id = ?");
        $check->execute([$_POST['id']]);
        if ($check->fetch()['count'] > 0) {
            header('Location: rooms.php?error=has_schedule');
            exit;
        }
        $room_row = $pdo->prepare("SELECT name FROM rooms WHERE id = ?");
        $room_row->execute([$_POST['id']]);
        $del_name = $room_row->fetch()['name'] ?? '';
        $stmt = $pdo->prepare("DELETE FROM rooms WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        logActivity($pdo, 'حذف قاعة: ' . $del_name, $current_user['name'] ?? '');
        header('Location: rooms.php?success=deleted');
        exit;
    }
}

// Get all rooms
$rooms = $pdo->query("SELECT * FROM rooms ORDER BY name")->fetchAll();
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>إدارة القاعات - نظام الجدول الدراسي</title>
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
                <h1 class="text-2xl font-bold text-gray-900">القاعات</h1>
                <p class="text-sm text-gray-600 mt-1">إدارة قاعات الدراسة في النظام</p>
            </div>
        </header>

        <!-- Content -->
        <div class="p-3 md:p-6">
            <!-- Success Message -->
            <?php if (isset($_GET['error']) && $_GET['error'] === 'has_schedule'): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-custom">
                    <p class="text-sm text-red-800">لا يمكن حذف هذه القاعة لأنها مرتبطة بحصص في الجدول</p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['success'])): ?>
                <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-custom">
                    <p class="text-sm text-green-800">
                        <?php
                        switch ($_GET['success']) {
                            case 'added': echo 'تم إضافة القاعة بنجاح'; break;
                            case 'updated': echo 'تم تحديث القاعة بنجاح'; break;
                            case 'deleted': echo 'تم حذف القاعة بنجاح'; break;
                        }
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if (isAdmin() || (isUser() && $perms['perm_user_rooms_add'])): ?>
            <!-- Add Room Form -->
            <div class="bg-white rounded-custom shadow border border-gray-200 p-4 md:p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">إضافة قاعة جديدة</h2>
                <form method="POST" class="flex flex-col gap-4">
                    <div class="flex flex-col md:flex-row gap-4">
                        <input type="text" name="room_name" placeholder="اسم القاعة (مثل: مختبر الحاسوب 1, قاعة 101)" required
                               class="flex-1 px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary">
                        <button type="submit" name="add_room" class="px-6 py-2 bg-primary text-white rounded-custom hover:bg-primary/90 transition-colors">
                            إضافة قاعة
                        </button>
                    </div>
                    <div class="flex flex-wrap gap-4">
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input type="radio" name="room_type" value="regular" checked class="w-4 h-4 text-primary border-gray-300 focus:ring-primary">
                            <span class="text-sm text-gray-700">عادي (دراسة وامتحانات)</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input type="radio" name="room_type" value="exam_only" class="w-4 h-4 text-primary border-gray-300 focus:ring-primary">
                            <span class="text-sm text-gray-700">للامتحانات فقط</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input type="radio" name="room_type" value="class_only" class="w-4 h-4 text-primary border-gray-300 focus:ring-primary">
                            <span class="text-sm text-gray-700">للدراسة فقط</span>
                        </label>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- Rooms Table -->
            <div class="bg-white rounded-custom shadow border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-right">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">اسم القاعة</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">النوع</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">عدد المحاضرات</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($rooms as $room): ?>
                                <?php
                                // Get schedule count for this room
                                $schedule_count = $pdo->prepare("SELECT COUNT(*) as count FROM schedules WHERE room_id = ?");
                                $schedule_count->execute([$room['id']]);
                                $count = $schedule_count->fetch()['count'];
                                ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($room['name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if (!empty($room['exam_only'])): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">للامتحانات فقط</span>
                                        <?php elseif (!empty($room['class_only'])): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">للدراسة فقط</span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">عادي</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $count; ?> محاضرة
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <div class="flex gap-2">
                                            <?php if (isAdmin() || (isUser() && $perms['perm_user_rooms_edit'])): ?>
                                            <button onclick="editRoom(<?php echo $room['id']; ?>, '<?php echo htmlspecialchars($room['name'], ENT_QUOTES); ?>', <?php echo (int)$room['exam_only']; ?>, <?php echo (int)$room['class_only']; ?>)"
                                                    class="text-blue-600 hover:text-blue-900 font-medium">تعديل</button>
                                            <?php endif; ?>
                                            <?php if (isAdmin() || (isUser() && $perms['perm_user_rooms_delete'])): ?>
                                            <button type="button" onclick="showDeleteRoomModal(<?php echo $room['id']; ?>)" class="text-red-600 hover:text-red-900 font-medium">حذف</button>
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
            <h3 class="text-lg font-semibold text-gray-900 mb-4">تعديل القاعة</h3>
            <form method="POST">
                <input type="hidden" name="id" id="editId">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">اسم القاعة</label>
                    <input type="text" name="room_name" id="editRoomName" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">نوع القاعة</label>
                    <div class="flex flex-wrap gap-4">
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input type="radio" name="room_type" id="editRoomTypeRegular" value="regular" class="w-4 h-4 text-primary border-gray-300 focus:ring-primary">
                            <span class="text-sm text-gray-700">عادي</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input type="radio" name="room_type" id="editRoomTypeExam" value="exam_only" class="w-4 h-4 text-primary border-gray-300 focus:ring-primary">
                            <span class="text-sm text-gray-700">للامتحانات فقط</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input type="radio" name="room_type" id="editRoomTypeClass" value="class_only" class="w-4 h-4 text-primary border-gray-300 focus:ring-primary">
                            <span class="text-sm text-gray-700">للدراسة فقط</span>
                        </label>
                    </div>
                </div>
                <div class="flex gap-3">
                    <button type="submit" name="edit_room" class="flex-1 px-4 py-2 bg-primary text-white rounded-custom hover:bg-primary/90 transition-colors">
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

<form id="deleteRoomForm" method="POST" class="hidden">
    <input type="hidden" name="delete_room" value="1">
    <input type="hidden" name="id" id="deleteRoomId">
</form>

<div id="deleteRoomModal" class="hidden fixed inset-0 bg-gray-600/50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-6 border w-[90%] max-w-sm shadow-lg rounded-custom bg-white">
        <div class="text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856C19.07 19 20 18.07 20 16.928V7.072C20 5.93 19.07 5 17.928 5H6.072C4.93 5 4 5.93 4 7.072v9.856C4 18.07 4.93 19 6.072 19z"></path>
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">تأكيد حذف القاعة</h3>
            <p class="text-sm text-gray-600 mb-6">هل أنت متأكد من حذف هذه القاعة؟</p>
            <div class="flex gap-3">
                <button type="button" onclick="submitDeleteRoom()" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-custom hover:bg-red-700 transition-colors font-medium">
                    حذف
                </button>
                <button type="button" onclick="closeDeleteRoomModal()" class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-custom hover:bg-gray-300 transition-colors font-medium">
                    إلغاء
                </button>
            </div>
        </div>
    </div>
</div>

<script src="../assets/JS/admin-common.js"></script>
<script src="../assets/JS/rooms.js?v=<?php echo filemtime('../assets/JS/rooms.js'); ?>"></script>
</body>
</html>
