<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

// Check if user is admin
checkAuth('admin');
if (!isAdmin()) { header('Location: view_schedule.php'); exit; }

// Get counts for dashboard
$subjects_count = $pdo->query("SELECT COUNT(*) as count FROM subjects")->fetch()['count'];
$teachers_count = $pdo->query("SELECT COUNT(*) as count FROM teachers")->fetch()['count'];
$rooms_count = $pdo->query("SELECT COUNT(*) as count FROM rooms")->fetch()['count'];
$schedules_count = $pdo->query("SELECT COUNT(*) as count FROM schedules")->fetch()['count'];

// Get current user info
$current_user = getCurrentUser();

// Get recent activity logs
$date_from = isset($_GET['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_from']) ? $_GET['date_from'] : null;
$date_to   = isset($_GET['date_to'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_to'])   ? $_GET['date_to']   : null;
$recent_activities = [];
try {
    if ($date_from && $date_to) {
        $stmt = $pdo->prepare("SELECT * FROM activity_logs WHERE DATE(created_at) BETWEEN ? AND ? ORDER BY created_at DESC");
        $stmt->execute([$date_from, $date_to]);
        $recent_activities = $stmt->fetchAll();
    } elseif ($date_from) {
        $stmt = $pdo->prepare("SELECT * FROM activity_logs WHERE DATE(created_at) >= ? ORDER BY created_at DESC");
        $stmt->execute([$date_from]);
        $recent_activities = $stmt->fetchAll();
    } elseif ($date_to) {
        $stmt = $pdo->prepare("SELECT * FROM activity_logs WHERE DATE(created_at) <= ? ORDER BY created_at DESC");
        $stmt->execute([$date_to]);
        $recent_activities = $stmt->fetchAll();
    } else {
        $recent_activities = $pdo->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 25")->fetchAll();
    }
} catch (Exception $e) {
    // Table will be created on first logActivity call
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>لوحة التحكم - نظام الجدول الدراسي</title>
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
                <h1 class="text-2xl font-bold text-gray-900">لوحة التحكم</h1>
                <p class="text-sm text-gray-600 mt-1">إدارة نظام الجدول الدراسي</p>
            </div>
        </header>

        <!-- Dashboard Content -->
        <div class="p-3 md:p-6">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-custom shadow border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-blue-100 rounded-custom p-3">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                            </svg>
                        </div>
                        <div class="mr-4">
                            <p class="text-sm font-medium text-gray-600">المواد الدراسية</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $subjects_count; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-custom shadow border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-green-100 rounded-custom p-3">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <div class="mr-4">
                            <p class="text-sm font-medium text-gray-600">الأساتذة</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $teachers_count; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-custom shadow border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-purple-100 rounded-custom p-3">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                        </div>
                        <div class="mr-4">
                            <p class="text-sm font-medium text-gray-600">القاعات</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $rooms_count; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-custom shadow border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-orange-100 rounded-custom p-3">
                            <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <div class="mr-4">
                            <p class="text-sm font-medium text-gray-600"> المحاضرات</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $schedules_count; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-custom shadow border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">إجراءات سريعة</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <a href="subjects.php" class="flex items-center gap-3 px-4 py-3 bg-blue-50 text-blue-700 rounded-custom hover:bg-blue-100 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        إضافة مادة جديدة
                    </a>
                    <a href="teachers.php" class="flex items-center gap-3 px-4 py-3 bg-green-50 text-green-700 rounded-custom hover:bg-green-100 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        إضافة أستاذ جديد
                    </a>
                    <a href="rooms.php" class="flex items-center gap-3 px-4 py-3 bg-purple-50 text-purple-700 rounded-custom hover:bg-purple-100 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        إضافة قاعة جديدة
                    </a>
                </div>
            </div>

            <!-- Activity Log -->
            <div class="bg-white rounded-custom shadow border border-gray-200 p-6 mt-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">سجل النشاطات</h2>
                        <span class="text-xs text-gray-400">
                            <?php
                            if ($date_from && $date_to) {
                                echo 'من ' . date('d/m/Y', strtotime($date_from)) . ' إلى ' . date('d/m/Y', strtotime($date_to));
                            } elseif ($date_from) {
                                echo 'من ' . date('d/m/Y', strtotime($date_from));
                            } elseif ($date_to) {
                                echo 'حتى ' . date('d/m/Y', strtotime($date_to));
                            } else {
                                echo count($recent_activities) . ' نشاط أخير';
                            }
                            ?>
                        </span>
                    </div>
                    <form method="GET" class="flex items-center gap-2 flex-wrap">
                        <input type="date" name="date_from"
                               value="<?php echo htmlspecialchars($date_from ?? ''); ?>"
                               class="px-3 py-1.5 border border-gray-300 rounded-custom text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                        <span class="text-sm text-gray-500">إلى</span>
                        <input type="date" name="date_to"
                               value="<?php echo htmlspecialchars($date_to ?? ''); ?>"
                               class="px-3 py-1.5 border border-gray-300 rounded-custom text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                        <button type="submit" class="px-3 py-1.5 bg-primary text-white rounded-custom text-sm font-medium hover:bg-primary/90">تصفية</button>
                        <?php if ($date_from || $date_to): ?>
                        <a href="dashboard.php" class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-custom text-sm font-medium hover:bg-gray-200">إلغاء</a>
                        <?php endif; ?>
                    </form>
                </div>
                <?php if (empty($recent_activities)): ?>
                    <p class="text-sm text-gray-400 text-center py-8">لا توجد نشاطات مسجّلة حتى الآن</p>
                <?php else: ?>
                    <div class="divide-y divide-gray-100 max-h-96 overflow-y-auto">
                        <?php foreach ($recent_activities as $log): ?>
                        <div class="flex items-start gap-3 py-3">
                            <div class="w-8 h-8 bg-primary/10 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                                <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-gray-800"><?php echo htmlspecialchars($log['action']); ?></p>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <?php if (!empty($log['user_name'])): ?>
                                        <span class="text-xs font-medium text-primary"><?php echo htmlspecialchars($log['user_name']); ?></span>
                                        <span class="text-gray-300 text-xs">•</span>
                                    <?php endif; ?>
                                    <span class="text-xs text-gray-400"><?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
<script src="../assets/JS/admin-common.js"></script>
</body>
</html>
