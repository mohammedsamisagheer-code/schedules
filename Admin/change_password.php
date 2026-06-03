<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

checkAuth();
$current_user = getCurrentUser();
$user_id    = $_SESSION['user_id']    ?? null;
$teacher_id = $_SESSION['teacher_id'] ?? null;
$role       = $_SESSION['role']       ?? 'admin';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_password) || empty($confirm_password)) {
        $error = 'جميع الحقول مطلوبة';
    } elseif ($new_password !== $confirm_password) {
        $error = 'كلمة المرور الجديدة غير متطابقة';
    } elseif (strlen($new_password) < 4) {
        $error = 'كلمة المرور يجب أن تكون 4 أحرف على الأقل';
    } else {
        if ($role === 'teacher') {
            $stmt = $pdo->prepare("UPDATE teachers SET password = ?, must_change_password = 0 WHERE id = ?");
            $stmt->execute([md5($new_password), $teacher_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?");
            $stmt->execute([md5($new_password), $user_id]);
        }
        $_SESSION['must_change_password'] = 0;
        logActivity($pdo, 'غيّر كلمة المرور (إلزامي)', $current_user['name'] ?? '');
        header('Location: my_schedule.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>تغيير كلمة المرور - نظام الجدول الدراسي</title>
    <link rel="stylesheet" href="../assets/CSS/style.css">
    <link href="../assets/fonts/cairo.css" rel="stylesheet"/>
</head>
<body class="font-sans antialiased min-h-screen bg-gray-50 flex flex-col items-center justify-center px-4 py-8">

    <div class="w-full max-w-md space-y-6">
        <!-- Header -->
        <div class="text-center">
            <div class="flex flex-col items-center gap-3 mb-4">
                <img src="../assets/images/logo.png" alt="logo" class="w-16 h-16 object-contain">
                <h1 class="text-lg font-bold text-gray-900">تغيير كلمة المرور</h1>
            </div>
            <div class="p-4 bg-amber-50 border border-amber-200 rounded-custom">
                <p class="text-sm text-amber-800 font-medium">يجب عليك تغيير كلمة المرور قبل الاستمرار</p>
                <p class="text-xs text-amber-700 mt-1">مرحباً <?php echo htmlspecialchars($current_user['name']); ?> — هذا هو أول تسجيل دخول لك.</p>
            </div>
        </div>

        <!-- Form Card -->
        <div class="bg-white rounded-custom shadow-lg border border-gray-200 p-6">
            <?php if ($error): ?>
                <div class="mb-5 p-4 bg-red-50 border border-red-200 rounded-custom">
                    <p class="text-sm text-red-800"><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">كلمة المرور الجديدة</label>
                    <input type="password" name="new_password" required autocomplete="new-password"
                           class="w-full px-4 py-3 mb-4 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary text-base"
                           placeholder="أدخل كلمة المرور الجديدة">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">تأكيد كلمة المرور</label>
                    <input type="password" name="confirm_password" required autocomplete="new-password"
                           class="w-full px-4 py-3 mb-4 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary text-base"
                           placeholder="أعد إدخال كلمة المرور">
                </div>
                <button type="submit" name="change_password"
                        class="w-full px-4 py-3 bg-primary text-white rounded-custom hover:bg-primary/90 transition-colors font-medium text-base">
                    حفظ كلمة المرور والمتابعة
                </button>
            </form>
        </div>

        <!-- Logout link -->
        <div class="text-center">
            <a href="../logout.php" class="text-sm text-gray-400 hover:text-red-500 transition-colors">تسجيل الخروج</a>
        </div>
    </div>
</body>
</html>
