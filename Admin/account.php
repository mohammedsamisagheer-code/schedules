<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

checkAuth();
if (!isAdmin() && !isTeacher() && !isUser()) { header('Location: ../login.php'); exit; }

$current_user    = getCurrentUser();
$is_teacher_role = isTeacher();
$user_id         = $_SESSION['user_id']    ?? null;
$teacher_id      = $_SESSION['teacher_id'] ?? null;

// Fetch account info from the correct table
if ($is_teacher_role) {
    $stmt_fetch = $pdo->prepare("SELECT * FROM teachers WHERE id = ?");
    $stmt_fetch->execute([$teacher_id]);
} else {
    $stmt_fetch = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt_fetch->execute([$user_id]);
}
$user = $stmt_fetch->fetch();

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$is_teacher_role && isset($_POST['update_username'])) {
        $new_username = trim($_POST['new_username']);
        if (empty($new_username)) {
            $error = 'اسم المستخدم مطلوب';
        } else {
            $check = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE username = ? AND id != ?");
            $check->execute([$new_username, $user_id]);
            if ($check->fetch()['count'] > 0) {
                $error = 'اسم المستخدم مستخدم بالفعل';
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
                $stmt->execute([$new_username, $user_id]);
                logActivity($pdo, 'حدّث اسم المستخدم إلى: ' . $new_username, $current_user['name'] ?? '');
                $success = 'تم تحديث اسم المستخدم بنجاح';
                $user['username'] = $new_username;
            }
        }
    }

    if (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'];
        $new_password     = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'جميع حقول كلمة المرور مطلوبة';
        } elseif ($new_password !== $confirm_password) {
            $error = 'كلمة المرور الجديدة غير متطابقة';
        } elseif (strlen($new_password) < 4) {
            $error = 'كلمة المرور يجب أن تكون 4 أحرف على الأقل';
        } else {
            if ($user['password'] === $current_password || $user['password'] === md5($current_password)) {
                if ($is_teacher_role) {
                    $stmt = $pdo->prepare("UPDATE teachers SET password = ? WHERE id = ?");
                    $stmt->execute([md5($new_password), $teacher_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([md5($new_password), $user_id]);
                }
                logActivity($pdo, 'حدّث كلمة المرور', $current_user['name'] ?? '');
                $success = 'تم تحديث كلمة المرور بنجاح';
                $stmt_fetch->execute($is_teacher_role ? [$teacher_id] : [$user_id]);
                $user = $stmt_fetch->fetch();
            } else {
                $error = 'كلمة المرور الحالية غير صحيحة';
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
    <title>حسابي - لوحة التحكم</title>
    <link rel="stylesheet" href="../assets/CSS/style.css">
    <link href="../assets/fonts/cairo.css" rel="stylesheet"/>
</head>
<body class="font-sans antialiased bg-gray-50">

<?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 overflow-y-auto pt-0">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="px-6 py-4">
                <h1 class="text-2xl font-bold text-gray-900">حسابي</h1>
                <p class="text-sm text-gray-600 mt-1"><?php echo $is_teacher_role ? 'تغيير كلمة المرور' : 'تعديل اسم المستخدم وكلمة المرور'; ?></p>
            </div>
        </header>

        <!-- Content -->
        <div class="p-3 md:p-6">
            <!-- Success/Error Messages -->
            <?php if ($success): ?>
                <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-custom">
                    <p class="text-sm text-green-800"><?php echo $success; ?></p>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-custom">
                    <p class="text-sm text-red-800"><?php echo $error; ?></p>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php if (!$is_teacher_role): ?>
                <!-- Change Username -->
                <div class="bg-white rounded-custom shadow border border-gray-200 p-4 md:p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">تغيير اسم المستخدم</h2>
                    <form method="POST" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">اسم المستخدم الحالي</label>
                            <input type="text" disabled value="<?php echo htmlspecialchars($user['username']); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-custom bg-gray-50 text-gray-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">اسم المستخدم الجديد</label>
                            <input type="text" name="new_username" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary"
                                   placeholder="أدخل اسم المستخدم الجديد">
                        </div>
                        <button type="submit" name="update_username" class="w-full px-4 py-2 bg-primary text-white rounded-custom hover:bg-primary/90 transition-colors font-medium">
                            تحديث اسم المستخدم
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Change Password -->
                <div class="bg-white rounded-custom shadow border border-gray-200 p-4 md:p-6 <?php echo $is_teacher_role ? 'md:col-span-2 max-w-md' : ''; ?>">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">تغيير كلمة المرور</h2>
                    <form method="POST" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">كلمة المرور الحالية</label>
                            <input type="password" name="current_password" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary"
                                   placeholder="أدخل كلمة المرور الحالية">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">كلمة المرور الجديدة</label>
                            <input type="password" name="new_password" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary"
                                   placeholder="أدخل كلمة المرور الجديدة">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">تأكيد كلمة المرور الجديدة</label>
                            <input type="password" name="confirm_password" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-custom focus:outline-none focus:ring-2 focus:ring-primary"
                                   placeholder="أعد إدخال كلمة المرور الجديدة">
                        </div>
                        <button type="submit" name="update_password" class="w-full px-4 py-2 bg-primary text-white rounded-custom hover:bg-primary/90 transition-colors font-medium">
                            تحديث كلمة المرور
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="../assets/JS/admin-common.js"></script>
</body>
</html>
