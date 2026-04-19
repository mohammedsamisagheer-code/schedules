<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

// Check if user is admin
checkAuth('admin');
if (!isAdmin()) { header('Location: view_schedule.php'); exit; }

// Get current user info
$current_user = getCurrentUser();

// Get all teachers for the selector
$all_teachers = $pdo->query("SELECT id, name, title FROM teachers ORDER BY name")->fetchAll();

// Determine selected teacher: from GET param or default to first teacher
$selected_teacher_id = isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : (!empty($all_teachers) ? $all_teachers[0]['id'] : 0);

// Get selected teacher's info
$teacher_info = $pdo->prepare("SELECT * FROM teachers WHERE id = ?");
$teacher_info->execute([$selected_teacher_id]);
$teacher = $teacher_info->fetch();

// If teacher not found, fallback to first teacher
if (!$teacher && !empty($all_teachers)) {
    $selected_teacher_id = $all_teachers[0]['id'];
    $teacher_info->execute([$selected_teacher_id]);
    $teacher = $teacher_info->fetch();
}

$current_teacher = $selected_teacher_id;

// Get all schedules from all teachers (for display)
$stmt = $pdo->prepare(
       "SELECT s.*, sb.subject_code, sb.subject_name, sb.term, r.name as room_name, t.name as teacher_name, t.title as teacher_title, t.id as teacher_id
        FROM schedules s 
        LEFT JOIN subjects sb ON s.subject_id = sb.id 
        LEFT JOIN rooms r ON s.room_id = r.id
        LEFT JOIN teachers t ON s.teacher_id = t.id
        ORDER BY s.day_of_week, s.time");
$stmt->execute();
$schedules = $stmt->fetchAll();

// Get available rooms
$rooms = $pdo->query("SELECT * FROM rooms ORDER BY name")->fetchAll();

// Get subjects assigned to selected teacher
$subjects = $pdo->prepare("SELECT * FROM subjects WHERE teacher_id = ? ORDER BY term, subject_name");
$subjects->execute([$current_teacher]);
$teacher_subjects = $subjects->fetchAll();

// Get selected term from URL parameter
$selected_term_filter = isset($_GET['selected_term']) ? $_GET['selected_term'] : '';

// Get selected subject from URL parameter
$selected_subject = isset($_GET['selected_subject']) ? $_GET['selected_subject'] : '';

// Define predefined time slots from settings
$time_slots = buildTimeSlots(CLASSES_START_TIME, PERIODS_COUNT);

// Handle form submissions for adding/editing schedules
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_schedule'])) {
        // Get the term of the subject being scheduled
        $subject_term_check = $pdo->prepare("SELECT term FROM subjects WHERE id = ?");
        $subject_term_check->execute([$_POST['subject_id']]);
        $subject_term = $subject_term_check->fetch()['term'];
        
        // Rule 1: No two classes in the same term at the same time on the same day
        $term_conflict_check = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM schedules s
            LEFT JOIN subjects sb ON s.subject_id = sb.id
            WHERE sb.term = ?
            AND s.day_of_week = ? 
            AND (
                (s.time <= ? AND ADDTIME(s.time, '02:00:00') > ?) OR
                (s.time < ADDTIME(?, '02:00:00') AND ADDTIME(s.time, '02:00:00') >= ADDTIME(?, '02:00:00')) OR
                (s.time >= ? AND s.time < ADDTIME(?, '02:00:00'))
            )
        ");
        $term_conflict_check->execute([
            $subject_term,
            $_POST['day_of_week'], 
            $_POST['time'], $_POST['time'],
            $_POST['time'], $_POST['time'],
            $_POST['time'], $_POST['time']
        ]);
        $has_term_conflict = $term_conflict_check->fetch()['count'] > 0;
        
        // Rule 2: No two classes in the same room at the same time
        $room_conflict_check = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM schedules 
            WHERE room_id = ? 
            AND day_of_week = ? 
            AND (
                (time <= ? AND ADDTIME(time, '02:00:00') > ?) OR
                (time < ADDTIME(?, '02:00:00') AND ADDTIME(time, '02:00:00') >= ADDTIME(?, '02:00:00')) OR
                (time >= ? AND time < ADDTIME(?, '02:00:00'))
            )
        ");
        $room_conflict_check->execute([
            $_POST['room_id'], 
            $_POST['day_of_week'], 
            $_POST['time'], $_POST['time'],
            $_POST['time'], $_POST['time'],
            $_POST['time'], $_POST['time']
        ]);
        $has_room_conflict = $room_conflict_check->fetch()['count'] > 0;
        
        // Rule 3: Same teacher can't teach two classes at the same time
        $teacher_conflict_check = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM schedules s
            WHERE s.teacher_id = ?
            AND s.day_of_week = ? 
            AND (
                (s.time <= ? AND ADDTIME(s.time, '02:00:00') > ?) OR
                (s.time < ADDTIME(?, '02:00:00') AND ADDTIME(s.time, '02:00:00') >= ADDTIME(?, '02:00:00')) OR
                (s.time >= ? AND s.time < ADDTIME(?, '02:00:00'))
            )
        ");
        $teacher_conflict_check->execute([
            $current_teacher,
            $_POST['day_of_week'], 
            $_POST['time'], $_POST['time'],
            $_POST['time'], $_POST['time'],
            $_POST['time'], $_POST['time']
        ]);
        $has_teacher_conflict = $teacher_conflict_check->fetch()['count'] > 0;
        
        if ($has_term_conflict) {
            header('Location: my_schedule.php?teacher_id=' . $current_teacher . '&error=term_conflict');
            exit;
        } elseif ($has_room_conflict) {
            header('Location: my_schedule.php?teacher_id=' . $current_teacher . '&error=room_conflict');
            exit;
        } elseif ($has_teacher_conflict) {
            header('Location: my_schedule.php?teacher_id=' . $current_teacher . '&error=teacher_conflict');
            exit;
        } else {
            $stmt = $pdo->prepare("INSERT INTO schedules (subject_id, teacher_id, room_id, day_of_week, time) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$_POST['subject_id'], $current_teacher, $_POST['room_id'], $_POST['day_of_week'], $_POST['time']]);
            logActivity($pdo, 'أضاف حصة (' . $_POST['day_of_week'] . ' - ' . $_POST['time'] . ')', $current_user['name'] ?? '');
            header('Location: my_schedule.php?teacher_id=' . $current_teacher . '&success=added');
            exit;
        }
    }
    
    if (isset($_POST['edit_schedule'])) {
        // Get the term of the subject being edited
        $subject_term_check = $pdo->prepare("SELECT term FROM subjects WHERE id = ?");
        $subject_term_check->execute([$_POST['subject_id']]);
        $subject_term = $subject_term_check->fetch()['term'];
        
        // Rule 1: No two classes in the same term at the same time on the same day
        $term_conflict_check = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM schedules s
            LEFT JOIN subjects sb ON s.subject_id = sb.id
            WHERE sb.term = ?
            AND s.day_of_week = ? 
            AND s.id != ?
            AND (
                (s.time <= ? AND ADDTIME(s.time, '02:00:00') > ?) OR
                (s.time < ADDTIME(?, '02:00:00') AND ADDTIME(s.time, '02:00:00') >= ADDTIME(?, '02:00:00')) OR
                (s.time >= ? AND s.time < ADDTIME(?, '02:00:00'))
            )
        ");
        $term_conflict_check->execute([
            $subject_term,
            $_POST['day_of_week'], 
            $_POST['id'],
            $_POST['time'], $_POST['time'],
            $_POST['time'], $_POST['time'],
            $_POST['time'], $_POST['time']
        ]);
        $has_term_conflict = $term_conflict_check->fetch()['count'] > 0;
        
        // Rule 2: No two classes in the same room at the same time
        $room_conflict_check = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM schedules 
            WHERE room_id = ? 
            AND day_of_week = ? 
            AND id != ?
            AND (
                (time <= ? AND ADDTIME(time, '02:00:00') > ?) OR
                (time < ADDTIME(?, '02:00:00') AND ADDTIME(time, '02:00:00') >= ADDTIME(?, '02:00:00')) OR
                (time >= ? AND time < ADDTIME(?, '02:00:00'))
            )
        ");
        $room_conflict_check->execute([
            $_POST['room_id'], 
            $_POST['day_of_week'], 
            $_POST['id'],
            $_POST['time'], $_POST['time'],
            $_POST['time'], $_POST['time'],
            $_POST['time'], $_POST['time']
        ]);
        $has_room_conflict = $room_conflict_check->fetch()['count'] > 0;
        
        // Rule 3: Same teacher can't teach two classes at the same time
        $teacher_conflict_check = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM schedules s
            WHERE s.teacher_id = ?
            AND s.day_of_week = ? 
            AND s.id != ?
            AND (
                (s.time <= ? AND ADDTIME(s.time, '02:00:00') > ?) OR
                (s.time < ADDTIME(?, '02:00:00') AND ADDTIME(s.time, '02:00:00') >= ADDTIME(?, '02:00:00')) OR
                (s.time >= ? AND s.time < ADDTIME(?, '02:00:00'))
            )
        ");
        $teacher_conflict_check->execute([
            $current_teacher,
            $_POST['day_of_week'], 
            $_POST['id'],
            $_POST['time'], $_POST['time'],
            $_POST['time'], $_POST['time'],
            $_POST['time'], $_POST['time']
        ]);
        $has_teacher_conflict = $teacher_conflict_check->fetch()['count'] > 0;
        
        if ($has_term_conflict) {
            header('Location: my_schedule.php?teacher_id=' . $current_teacher . '&error=term_conflict');
            exit;
        } elseif ($has_room_conflict) {
            header('Location: my_schedule.php?teacher_id=' . $current_teacher . '&error=room_conflict');
            exit;
        } elseif ($has_teacher_conflict) {
            header('Location: my_schedule.php?teacher_id=' . $current_teacher . '&error=teacher_conflict');
            exit;
        } else {
            $stmt = $pdo->prepare("UPDATE schedules SET subject_id = ?, room_id = ?, day_of_week = ?, time = ? WHERE id = ? AND teacher_id = ?");
            $stmt->execute([$_POST['subject_id'], $_POST['room_id'], $_POST['day_of_week'], $_POST['time'], $_POST['id'], $current_teacher]);
            logActivity($pdo, 'عدّل حصة (' . $_POST['day_of_week'] . ' - ' . $_POST['time'] . ')', $current_user['name'] ?? '');
            header('Location: my_schedule.php?teacher_id=' . $current_teacher . '&success=updated');
            exit;
        }
    }
    
    if (isset($_POST['delete_schedule'])) {
        $stmt = $pdo->prepare("DELETE FROM schedules WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$_POST['id'], $current_teacher]);
        logActivity($pdo, 'حذف حصة من الجدول', $current_user['name'] ?? '');
        header('Location: my_schedule.php?teacher_id=' . $current_teacher . '&success=deleted');
        exit;
    }
}

// Group schedules by day and time for display
$schedules_by_day_time = [];
$time_slots_list = [];
$teacher_classes_count = 0;
foreach ($schedules as $schedule) {
    $day = $schedule['day_of_week'];
    $time_slot = $schedule['time'];
    $duration = $schedule['duration'] ?? 1;
    
    $time_formatted = date('H:i', strtotime($time_slot));
    
    if (!in_array($time_formatted, $time_slots_list)) {
        $time_slots_list[] = $time_formatted;
    }
    
    $schedules_by_day_time[$day][$time_formatted][] = $schedule;

    // Count only the selected teacher's classes and teaching days
    if ($schedule['teacher_id'] == $current_teacher) {
        $teacher_classes_count++;
    }
}

// Unique days the selected teacher teaches
$teacher_teaching_days = count(array_unique(array_map(
    fn($s) => $s['day_of_week'],
    array_filter($schedules, fn($s) => $s['teacher_id'] == $current_teacher)
)));

sort($time_slots_list);

$days = ['السبت', 'الأحد','الإثنين', 'الثلاثاء', 'الإربعاء', 'الخميس'];
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>جدولي - لوحة التحكم</title>
    <link rel="stylesheet" href="../assets/CSS/style.css">
    <script src="https://cdn.jsdelivr.net/npm/exceljs@4.4.0/dist/exceljs.min.js"></script>
    <link href="../assets/fonts/cairo.css" rel="stylesheet">
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
                    <a href="subjects.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-custom">
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
                    <a href="my_schedule.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium bg-primary/10 text-primary rounded-custom">
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
            <div class="px-6 py-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">جدولي الدراسي</h1>
                    <p class="text-sm text-gray-600 mt-1">إدارة جدول المحاضرات</p>
                </div>
                <div class="flex items-center gap-3">
                    <form method="GET" class="flex items-center gap-3">
                        <label class="text-sm font-medium text-gray-700">المدرس:</label>
                        <select name="teacher_id" onchange="this.form.submit()" class="px-4 py-2 bg-white border border-gray-200 rounded-custom text-sm font-medium text-gray-700 hover:bg-gray-50 shadow-sm focus:outline-none focus:ring-2 focus:ring-primary">
                            <?php foreach ($all_teachers as $t): ?>
                                <option value="<?php echo $t['id']; ?>" <?php echo ($t['id'] == $current_teacher) ? 'selected' : ''; ?>>
                                    <?php echo getTitleAbbr($t['title']) . htmlspecialchars($t['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <button type="button" onclick="exportToExcel()" class="px-4 py-2 bg-white border border-gray-200 rounded-custom text-sm font-medium text-gray-700 hover:bg-gray-50 shadow-sm flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        تصدير Excel
                    </button>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="p-3 md:p-6">
            <!-- Success/Error Messages -->
            <?php if (isset($_GET['success'])): ?>
                <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-custom">
                    <p class="text-sm text-green-800">
                        <?php
                        switch ($_GET['success']) {
                            case 'added': echo 'تم إضافة المحاضرة بنجاح'; break;
                            case 'updated': echo 'تم تحديث المحاضرة بنجاح'; break;
                            case 'deleted': echo 'تم حذف المحاضرة بنجاح'; break;
                        }
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-custom">
                    <p class="text-sm text-red-800">
                        <?php
                        switch ($_GET['error']) {
                            case 'term_conflict': echo 'هناك تعارض: يوجد محاضرة أخرى في نفس الفصل الدراسي في هذا الوقت.'; break;
                            case 'room_conflict': echo 'القاعة محجوزة في هذا الوقت.'; break;
                            case 'teacher_conflict': echo 'لدى المدرس محاضرة أخرى في نفس الوقت.'; break;
                        }
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <!-- Add Schedule Form -->
            <div class="bg-white rounded-custom shadow border border-gray-200 p-4 md:p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">إضافة محاضرة جديدة لـ <?php echo getTitleAbbr($teacher['title']) . htmlspecialchars($teacher['name']); ?></h2>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-3 md:gap-4">
                    <select name="subject_id" required class="px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary">
                        <option value="">اختر المادة</option>
                        <?php foreach ($teacher_subjects as $subject): ?>
                            <option value="<?php echo $subject['id']; ?>" <?php echo ($selected_subject == $subject['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name']); ?> (الفصل <?php echo $subject['term']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="day_of_week" required class="px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary">
                        <option value="">اختر اليوم</option>
                        <option value="السبت">السبت</option>
                        <option value="الأحد">الأحد</option>
                        <option value="الإثنين">الإثنين</option>
                        <option value="الثلاثاء">الثلاثاء</option>
                        <option value="الإربعاء">الإربعاء</option>
                        <option value="الخميس">الخميس</option>
                    </select>
                    <select name="time" required class="px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary">
                        <option value="">اختر الوقت</option>
                        <?php foreach ($time_slots as $time_value => $time_label): ?>
                            <option value="<?php echo $time_value; ?>"><?php echo $time_label; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="room_id" required class="px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary">
                        <option value="">اختر القاعة</option>
                        <?php foreach ($rooms as $room): ?>
                            <option value="<?php echo $room['id']; ?>"><?php echo htmlspecialchars($room['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="add_schedule" class="md:col-span-2 lg:col-span-5 px-6 py-2 bg-primary text-white rounded-custom hover:bg-primary/90 transition-colors">
                        إضافة محاضرة
                    </button>
                </form>
            </div>

            <!-- Schedule Table -->
            <!-- Stats Bar -->
            <div class="flex flex-wrap items-center gap-3 mb-4">
                <span class="inline-flex items-center gap-2 px-4 py-2 rounded-custom text-sm font-semibold bg-blue-50 text-blue-700 border border-blue-200 shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                    <?php echo $teacher_classes_count; ?> محاضرة أسبوعياً
                </span>
                <span class="inline-flex items-center gap-2 px-4 py-2 rounded-custom text-sm font-semibold bg-green-50 text-green-700 border border-green-200 shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <?php echo ($teacher_classes_count * 2); ?> ساعة أسبوعياً
                </span>
                <span class="inline-flex items-center gap-2 px-4 py-2 rounded-custom text-sm font-semibold bg-purple-50 text-purple-700 border border-purple-200 shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <?php echo $teacher_teaching_days; ?> أيام تدريس أسبوعياً
                </span>
            </div>

            <?php if (!empty($selected_term_filter)): ?>
            <div class="bg-white rounded-custom shadow border border-gray-200 overflow-hidden">
                <div class="p-4 border-b border-gray-200 bg-gray-50">
                    <p class="text-sm font-semibold text-gray-700">جدول الفصل الدراسي <?php echo htmlspecialchars($selected_term_filter); ?> - <?php echo getTitleAbbr($teacher['title']) . htmlspecialchars($teacher['name']); ?></p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-right border-collapse min-w-[800px]">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center w-[120px]">الوقت</th>
                                <th class="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center">السبت</th>
                                <th class="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center">الأحد</th>
                                <th class="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center">الاثنين</th>
                                <th class="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center">الثلاثاء</th>
                                <th class="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center">الإربعاء</th>
                                <th class="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center">الخميس</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($time_slots as $slot_time => $slot_label): ?>
                                <?php $slot_key = date('H:i', strtotime($slot_time)); ?>
                                <tr>
                                    <td class="bg-gray-50/50 p-4 text-center">
                                        <div class="flex flex-col items-center justify-center text-xs text-gray-500 font-medium">
                                            <span><?php echo $slot_label; ?></span>
                                        </div>
                                    </td>
                                    <?php foreach ($days as $day): ?>
                                        <td class="day-col p-2 border-r border-gray-100">
                                            <?php
                                            $slot_schedules = $schedules_by_day_time[$day][$slot_key] ?? [];
                                            $current_teacher_class = null;
                                            $has_same_term_conflict = false;
                                            
                                            foreach ($slot_schedules as $s) {
                                                if ($s['teacher_id'] == $current_teacher) {
                                                    $current_teacher_class = $s;
                                                } elseif ($s['term'] == $selected_term_filter) {
                                                    $has_same_term_conflict = true;
                                                }
                                            }
                                            ?>
                                            <?php if ($current_teacher_class): ?>
                                                <?php $end_time_display = date('h:i A', strtotime($current_teacher_class['time']) + (2 * 3600)); ?>
                                                <div class="class-card bg-blue-50 border-r-4 border-blue-500 p-3 rounded flex flex-col justify-between relative">
                                                    <div>
                                                        <p class="text-sm font-bold text-blue-900 truncate pl-12">
                                                            <?php echo htmlspecialchars($current_teacher_class['subject_code'] . ' - ' . $current_teacher_class['subject_name']); ?>
                                                        </p>
                                                        <p class="text-xs text-blue-700 font-medium mt-1">
                                                            الفصل <?php echo htmlspecialchars($current_teacher_class['term']); ?>
                                                        </p>
                                                        <p class="text-xs text-blue-600 font-medium">
                                                            <?php echo date('h:i A', strtotime($current_teacher_class['time'])); ?> - <?php echo $end_time_display; ?>
                                                        </p>
                                                        <p class="text-xs text-blue-600 font-medium">
                                                            المدة: ساعتين
                                                        </p>
                                                    </div>
                                                    <p class="text-xs text-blue-600 font-semibold">
                                                        <?php echo htmlspecialchars($current_teacher_class['room_name']); ?>
                                                    </p>
                                                    <div class="absolute top-2 left-2 flex gap-1">
                                                        <button onclick="editSchedule(<?php echo $current_teacher_class['id']; ?>, '<?php echo $current_teacher_class['subject_id']; ?>', '<?php echo $current_teacher_class['day_of_week']; ?>', '<?php echo $current_teacher_class['time']; ?>', '<?php echo $current_teacher_class['room_id']; ?>')"
                                                                class="text-blue-600 hover:text-blue-800 text-xs">تعديل</button>
                                                        <button type="button" onclick="showDeleteClassModal(<?php echo $current_teacher_class['id']; ?>)" class="text-red-600 hover:text-red-800 text-xs">حذف</button>
                                                    </div>
                                                </div>
                                            <?php elseif ($has_same_term_conflict): ?>
                                                <div class="class-card bg-red-50 border-r-4 border-red-500 p-3 rounded flex items-center justify-center">
                                                    <p class="text-sm font-bold text-red-900">(محجوز)</p>
                                                </div>
                                            <?php else: ?>
                                                <div class="class-card bg-green-50 border-r-4 border-green-500 p-3 rounded flex items-center justify-center">
                                                    <p class="text-xs font-bold text-green-600">فارغ</p>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php else: ?>
            <div class="bg-white rounded-custom shadow border border-gray-200 overflow-hidden">
                <div class="p-4 border-b border-gray-200 bg-gray-50">
                    <p class="text-sm font-semibold text-gray-700">محاضرات <?php echo getTitleAbbr($teacher['title']) . htmlspecialchars($teacher['name']); ?></p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-right border-collapse min-w-[800px]">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center w-[120px]">الوقت</th>
                                <th class="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center">السبت</th>
                                <th class="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center">الأحد</th>
                                <th class="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center">الاثنين</th>
                                <th class="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center">الثلاثاء</th>
                                <th class="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center">الإربعاء</th>
                                <th class="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center">الخميس</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($time_slots as $slot_time => $slot_label): ?>
                                <?php $slot_key = date('H:i', strtotime($slot_time)); ?>
                                <tr>
                                    <td class="bg-gray-50/50 p-4 text-center">
                                        <div class="flex flex-col items-center justify-center text-xs text-gray-500 font-medium">
                                            <span><?php echo $slot_label; ?></span>
                                        </div>
                                    </td>
                                    <?php foreach ($days as $day): ?>
                                        <td class="day-col p-2 border-r border-gray-100">
                                            <?php
                                            $slot_schedules = $schedules_by_day_time[$day][$slot_key] ?? [];
                                            $my_class = null;
                                            foreach ($slot_schedules as $s) {
                                                if ($s['teacher_id'] == $current_teacher) {
                                                    $my_class = $s;
                                                    break;
                                                }
                                            }
                                            ?>
                                            <?php if ($my_class): ?>
                                                <?php $end_time_display = date('h:i A', strtotime($my_class['time']) + (2 * 3600)); ?>
                                                <div class="class-card bg-blue-50 border-r-4 border-blue-500 p-3 rounded flex flex-col justify-between relative">
                                                    <div>
                                                        <p class="text-sm font-bold text-blue-900 truncate pl-12">
                                                            <?php echo htmlspecialchars($my_class['subject_code'] . ' - ' . $my_class['subject_name']); ?>
                                                        </p>
                                                        <p class="text-xs text-blue-700 font-medium mt-1">
                                                            الفصل <?php echo htmlspecialchars($my_class['term']); ?>
                                                        </p>
                                                        <p class="text-xs text-blue-600 font-medium">
                                                            <?php echo date('h:i A', strtotime($my_class['time'])); ?> - <?php echo $end_time_display; ?>
                                                        </p>
                                                        <p class="text-xs text-blue-600 font-medium">
                                                            المدة: ساعتين
                                                        </p>
                                                    </div>
                                                    <p class="text-xs text-blue-600 font-semibold">
                                                        <?php echo htmlspecialchars($my_class['room_name']); ?>
                                                    </p>
                                                    <div class="absolute top-2 left-2 flex gap-1">
                                                        <button onclick="editSchedule(<?php echo $my_class['id']; ?>, '<?php echo $my_class['subject_id']; ?>', '<?php echo $my_class['day_of_week']; ?>', '<?php echo $my_class['time']; ?>', '<?php echo $my_class['room_id']; ?>')"
                                                                class="text-blue-600 hover:text-blue-800 text-xs">تعديل</button>
                                                        <button type="button" onclick="showDeleteClassModal(<?php echo $my_class['id']; ?>)" class="text-red-600 hover:text-red-800 text-xs">حذف</button>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="class-card bg-gray-100 border-r-4 border-gray-400 p-3 rounded flex items-center justify-center">
                                                    <p class="text-xs font-bold text-gray-500 uppercase italic">فارغ</p>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Edit Modal -->
<div id="editModal" class="hidden fixed inset-0 bg-gray-600/50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-10 md:top-20 mx-auto p-5 border w-[90%] max-w-md shadow-lg rounded-custom bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">تعديل المحاضرة</h3>
            <form method="POST">
                <input type="hidden" name="id" id="editId">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">المادة</label>
                    <select name="subject_id" id="editSubjectId" required class="w-full px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary">
                        <option value="">اختر المادة</option>
                        <?php foreach ($teacher_subjects as $subject): ?>
                            <option value="<?php echo $subject['id']; ?>">
                                <?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name']); ?> (الفصل <?php echo $subject['term']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">اليوم</label>
                    <select name="day_of_week" id="editDayOfWeek" required class="w-full px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary">
                        <option value="السبت">السبت</option>
                        <option value="الأحد">الأحد</option>
                        <option value="الإثنين">الإثنين</option>
                        <option value="الثلاثاء">الثلاثاء</option>
                        <option value="الإربعاء">الإربعاء</option>
                        <option value="الخميس">الخميس</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">الوقت</label>
                    <select name="time" id="editTime" required class="w-full px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary">
                        <option value="">اختر الوقت</option>
                        <?php foreach ($time_slots as $time_value => $time_label): ?>
                            <option value="<?php echo $time_value; ?>"><?php echo $time_label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">القاعة</label>
                    <select name="room_id" id="editRoomId" required class="w-full px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary">
                        <option value="">اختر القاعة</option>
                        <?php foreach ($rooms as $room): ?>
                            <option value="<?php echo $room['id']; ?>"><?php echo htmlspecialchars($room['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex gap-3">
                    <button type="submit" name="edit_schedule" class="flex-1 px-4 py-2 bg-primary text-white rounded-custom hover:bg-primary/90 transition-colors">
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

<form id="deleteClassForm" method="POST" class="hidden">
    <input type="hidden" name="delete_schedule" value="1">
    <input type="hidden" name="id" id="deleteClassId">
</form>

<div id="deleteClassModal" class="hidden fixed inset-0 bg-gray-600/50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-6 border w-[90%] max-w-sm shadow-lg rounded-custom bg-white">
        <div class="text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856C19.07 19 20 18.07 20 16.928V7.072C20 5.93 19.07 5 17.928 5H6.072C4.93 5 4 5.93 4 7.072v9.856C4 18.07 4.93 19 6.072 19z"></path>
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">تأكيد حذف المحاضرة</h3>
            <p class="text-sm text-gray-600 mb-6">هل أنت متأكد من حذف هذه المحاضرة؟</p>
            <div class="flex gap-3">
                <button type="button" onclick="submitDeleteClass()" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-custom hover:bg-red-700 transition-colors font-medium">
                    حذف
                </button>
                <button type="button" onclick="closeDeleteClassModal()" class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-custom hover:bg-gray-300 transition-colors font-medium">
                    إلغاء
                </button>
            </div>
        </div>
    </div>
</div>


<!-- Hidden input to store selected subject term -->
<input type="hidden" id="selectedSubjectTerm" value="<?php echo htmlspecialchars($selected_term_filter); ?>">

<script>
const subjectTerms = <?php
    $terms = [];
    foreach ($teacher_subjects as $subject) {
        $terms[$subject['id']] = $subject['term'];
    }
    echo json_encode($terms);
?>;
const currentTeacherId    = <?php echo json_encode($current_teacher); ?>;
const teacherScheduleData = <?php
    $export_data = [];
    foreach ($schedules as $s) {
        if ($s['teacher_id'] == $current_teacher) {
            $export_data[] = [
                'day'     => $s['day_of_week'],
                'time'    => date('H:i', strtotime($s['time'])),
                'subject' => ($s['subject_code'] ? $s['subject_code'] . ' - ' : '') . $s['subject_name'],
                'room'    => $s['room_name'] ?? '',
                'term'    => $s['term'],
            ];
        }
    }
    echo json_encode($export_data, JSON_UNESCAPED_UNICODE);
?>;
const teacherName = <?php echo json_encode(($teacher ? getTitleAbbr($teacher['title']) . $teacher['name'] : ''), JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="../assets/JS/admin-common.js"></script>
<script src="../assets/JS/my-schedule.js?v=<?php echo filemtime('../assets/JS/my-schedule.js'); ?>"></script>
</body>
</html>
