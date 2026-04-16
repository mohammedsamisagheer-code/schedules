<?php
session_start();
require_once 'includes/config.php';

// ─── Brute Force Protection ─── (BF_MAX_ATTEMPTS / BF_LOCKOUT_MINUTES defined in config.php via settings table)


function bf_get_ip() {
    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    return $forwarded
        ? trim(explode(',', $forwarded)[0])
        : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function bf_is_locked($pdo, $ip) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at >= NOW() - INTERVAL " . BF_LOCKOUT_MINUTES . " MINUTE");
    $stmt->execute([$ip]);
    return $stmt->fetchColumn() >= BF_MAX_ATTEMPTS;
}

function bf_remaining_seconds($pdo, $ip) {
    $stmt = $pdo->prepare("SELECT GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), MIN(attempted_at) + INTERVAL " . BF_LOCKOUT_MINUTES . " MINUTE)) FROM login_attempts WHERE ip = ? AND attempted_at >= NOW() - INTERVAL " . BF_LOCKOUT_MINUTES . " MINUTE");
    $stmt->execute([$ip]);
    return (int)$stmt->fetchColumn();
}

function bf_record_failure($pdo, $ip) {
    $stmt = $pdo->prepare("INSERT INTO login_attempts (ip) VALUES (?)");
    $stmt->execute([$ip]);
}

function bf_clear($pdo, $ip) {
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip = ?");
    $stmt->execute([$ip]);
}

function bf_cleanup($pdo) {
    $pdo->prepare("DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL " . BF_LOCKOUT_MINUTES . " MINUTE")->execute();
}
// ─────────────────────────────────────────────────────────────────────────────

$error = '';
$success = '';
$locked_seconds = 0;
$client_ip = bf_get_ip();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (bf_is_locked($pdo, $client_ip)) {
        $locked_seconds = bf_remaining_seconds($pdo, $client_ip);
        $mins = ceil($locked_seconds / 60);
        $error = "تم تجاوز عدد المحاولات المسموح بها. يرجى الانتظار {$mins} دقيقة قبل المحاولة مجدداً.";
    } elseif (empty($username) || empty($password)) {
        $error = 'جميع الحقول مطلوبة';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && ($user['password'] === $password || $user['password'] === md5($password))) {
            bf_clear($pdo, $client_ip);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_title'] = $user['title'] ?? '';
            $_SESSION['role'] = $user['role'];
            $_SESSION['last_activity'] = time();
            logActivity($pdo, 'تسجيل دخول (' . $user['role'] . ')', $user['name']);
            if ($user['role'] === 'admin') {
                header('Location: Admin/dashboard.php');
            } else {
                header('Location: Admin/view_schedule.php');
            }
            exit;
        } else {
            bf_record_failure($pdo, $client_ip);
            bf_cleanup($pdo);
            $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at >= NOW() - INTERVAL " . BF_LOCKOUT_MINUTES . " MINUTE");
            $stmt2->execute([$client_ip]);
            $attempts_left = BF_MAX_ATTEMPTS - (int)$stmt2->fetchColumn();
            if ($attempts_left <= 0) {
                $error = "تم تجاوز عدد المحاولات المسموح بها. يرجى الانتظار " . BF_LOCKOUT_MINUTES . " دقيقة قبل المحاولة مجدداً.";
                $locked_seconds = BF_LOCKOUT_MINUTES * 60;
            } else {
                $error = 'اسم المستخدم أو كلمة المرور غير صحيحة. (' . $attempts_left . ' محاولات متبقية)';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>تسجيل الدخول - نظام الجدول الدراسي</title>
    <link rel="stylesheet" href="assets/CSS/style.css">
    <link href="assets/fonts/cairo.css" rel="stylesheet"/>
</head>
<body class="font-sans antialiased bg-gray-50 min-h-screen flex items-center justify-center px-4 py-8">
    <div class="max-w-md w-full space-y-6">
        <!-- Header -->
        <div class="text-center">
            <div class="flex flex-col items-center gap-3 mb-4">
                <img src="assets/images/logo.png" alt="logo" class="w-16 h-16 sm:w-24 sm:h-24 object-contain">
                <h1 class="text-lg sm:text-2xl font-bold text-gray-900 leading-snug">نظام الجدول الدراسي لقسم هندسة تقنيات نظم الحاسوب</h1>
            </div>
            <h2 class="text-base sm:text-lg font-semibold text-gray-700">تسجيل الدخول</h2>
            <p class="text-sm text-gray-600 mt-1">أدخل بياناتك للوصول إلى النظام</p>
        </div>

        <!-- Login Form -->
        <div class="bg-white rounded-custom shadow-lg border border-gray-200 p-5 sm:p-8">
            <?php if ($error): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-custom">
                    <p class="text-sm text-red-800"><?php echo $error; ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <!-- Username -->
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-2">اسم المستخدم</label>
                    <input id="username" name="username" type="text" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary text-base"
                           placeholder="أدخل اسم المستخدم" value="<?php echo htmlspecialchars($username ?? ''); ?>">
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">كلمة المرور</label>
                    <input id="password" name="password" type="password" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary text-base"
                           placeholder="أدخل كلمة المرور">
                </div>

                <!-- Submit Button -->
                <div>
                    <button type="submit" id="submitBtn" <?php if ($locked_seconds > 0): ?>disabled<?php endif; ?> class="w-full px-4 py-3 bg-primary text-white rounded-custom hover:bg-primary/90 transition-colors font-medium text-base disabled:opacity-50 disabled:cursor-not-allowed">
                        <?php if ($locked_seconds > 0): ?><span id="countdownTxt"></span><?php else: ?>تسجيل الدخول<?php endif; ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- Footer -->
        <div class="text-center text-sm text-gray-500">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row justify-between items-center gap-4">
                <p class="text-sm text-gray-400"> 2026 <?php echo htmlspecialchars(COLLEGE_NAME); ?>. جميع الحقوق محفوظة.</p>
                <div class="flex gap-6">
                    <a class="text-sm text-gray-400 hover:text-primary" href="#">الدعم</a>
                    <a class="text-sm text-gray-400 hover:text-primary" href="#">سياسة الخصوصية</a>
                </div>
            </div>
        </div>
    </div>
<?php if ($locked_seconds > 0): ?>
<script>
(function() {
    let secs = <?php echo (int)$locked_seconds; ?>;
    const btn = document.getElementById('submitBtn');
    const txt = document.getElementById('countdownTxt');
    function tick() {
        if (secs <= 0) { location.reload(); return; }
        const m = Math.floor(secs / 60), s = secs % 60;
        txt.textContent = 'انتظر ' + m + ':' + String(s).padStart(2, '0');
        secs--;
        setTimeout(tick, 1000);
    }
    tick();
})();
</script>
<?php endif; ?>
</body>
</html>
