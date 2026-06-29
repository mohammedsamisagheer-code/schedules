<?php
session_start();
require_once 'includes/config.php';

if (isset($_SESSION['role'])) {
    header('Location: Admin/my_schedule.php');
    exit;
}

$error      = '';
$national_id = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $national_id = trim($_POST['national_id'] ?? '');
    $password    = trim($_POST['password']    ?? '');

    if (empty($national_id) || empty($password)) {
        $error = 'جميع الحقول مطلوبة';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM teachers WHERE national_id = ? AND national_id IS NOT NULL");
        $stmt->execute([$national_id]);
        $teacher = $stmt->fetch();

        $password_ok = false;
        if ($teacher) {
            $stored = $teacher['password'];
            if ($stored === null || $stored === '') {
                $password_ok = ($password === $national_id || md5($password) === md5($national_id));
            } else {
                $password_ok = ($stored === $password || $stored === md5($password));
            }
        }

        if ($password_ok) {
            session_regenerate_id(true);
            $_SESSION['user_id']              = null;
            $_SESSION['user_name']            = $teacher['name'];
            $_SESSION['user_title']           = $teacher['title'] ?? '';
            $_SESSION['role']                 = 'teacher';
            $_SESSION['teacher_id']           = $teacher['id'];
            $_SESSION['must_change_password'] = (int)($teacher['must_change_password'] ?? 1);
            $_SESSION['last_activity']        = time();

            logActivity($pdo, 'تسجيل دخول (أستاذ)', $teacher['name']);

            if (!empty($teacher['must_change_password'])) {
                header('Location: Admin/change_password.php');
            } else {
                header('Location: Admin/my_schedule.php');
            }
            exit;
        }

        $error = 'الرقم الوطني أو كلمة المرور غير صحيحة';
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>تسجيل دخول الأساتذة - نظام الجدول الدراسي</title>
    <link rel="stylesheet" href="assets/CSS/style.css">
    <link href="assets/fonts/cairo.css" rel="stylesheet"/>
</head>
<body class="login-body font-sans antialiased min-h-screen flex flex-col">
    <div class="landing-bg login-bg-light" aria-hidden="true">
        <div class="landing-gradient"></div>
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
        <div class="orb orb-4"></div>
        <div class="orb orb-5"></div>
        <div class="landing-grid"></div>
        <canvas id="particleCanvas" class="landing-particles"></canvas>
    </div>
    <div class="relative z-10 flex-1 flex items-center justify-center px-4 py-8">
    <div class="max-w-md w-full space-y-6">
        <!-- Header -->
        <div class="text-center">
            <div class="flex flex-col items-center gap-3 mb-4">
                <img src="assets/images/logo.png" alt="logo" class="w-16 h-16 sm:w-24 sm:h-24 object-contain">
                <h1 class="text-lg sm:text-2xl font-bold text-gray-900 leading-snug">نظام الجدول الدراسي لقسم هندسة تقنيات الحاسوب</h1>
            </div>
            <h2 class="text-base sm:text-lg font-semibold text-gray-700">بوابة الأساتذة</h2>
            <p class="text-sm text-gray-600 mt-1">أدخل رقمك الوطني وكلمة المرور للوصول إلى جدولك</p>
        </div>

        <!-- Login Form -->
        <div class="bg-white rounded-custom shadow-lg border border-gray-200 p-5 sm:p-8">
            <?php if ($error): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-custom">
                    <p class="text-sm text-red-800"><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div>
                    <label for="national_id" class="block text-sm font-medium text-gray-700 mb-2">الرقم الوطني</label>
                    <input id="national_id" name="national_id" type="text" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary text-base"
                           placeholder="أدخل رقمك الوطني"
                           value="<?php echo htmlspecialchars($national_id); ?>">
                    <p class="text-xs text-gray-400 mt-1">كلمة المرور الافتراضية هي رقمك الوطني</p>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">كلمة المرور</label>
                    <input id="password" name="password" type="password" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary text-base"
                           placeholder="أدخل كلمة المرور">
                </div>

                <div>
                    <button type="submit"
                            class="w-full px-4 py-3 bg-primary text-white rounded-custom hover:bg-primary/90 transition-colors font-medium text-base">
                        دخول
                    </button>
                </div>
            </form>
        </div>

        <div class="text-center">
            <a href="login.php" class="text-sm text-gray-400 hover:text-primary transition-colors">تسجيل دخول الإداريين</a>
        </div>
    </div>
    </div>

    <footer class="relative z-10 w-full border-t border-gray-200 bg-white/90 backdrop-blur-sm">
        <div class="max-w-7xl mx-auto px-6 py-4 flex flex-col md:flex-row justify-between items-center gap-3">
            <p class="text-sm text-gray-400">© 2026 <?php echo htmlspecialchars(COLLEGE_NAME); ?>. جميع الحقوق محفوظة.</p>
        </div>
    </footer>
<script src="assets/JS/login.js"></script>
</body>
</html>
