<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>كلية التقنية الهندسية-جنزور - الجدول الأسبوعي</title>
    <link rel="stylesheet" href="assets/CSS/style.css">
    <link href="assets/fonts/cairo.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="font-sans antialiased">
    <!-- Animated Background -->
    <div class="fixed inset-0 animated-bg opacity-10"></div>
    
    <!-- Main Content -->
    <div class="relative min-h-screen flex flex-col">
        <!-- Header -->
        <header class="bg-white/90 backdrop-blur-md shadow-lg border-b">
            <div class="max-w-7xl mx-auto px-4 py-6">
                <div class="flex justify-between items-center">
                    <div class="flex items-center space-x-4 space-x-reverse">
                        <img src="assets/images/logo.png" alt="كلية التقنية الهندسية - جنزور" class="h-16 w-16">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">كلية التقنية الهندسية - جنزور</h1>
                            <p class="text-gray-600">قسم هندسة تقنيات نظم الحاسوب</p>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Section -->
        <main class="flex-1 flex items-center justify-center px-4 py-12">
            <div class="max-w-4xl w-full">
                <!-- Welcome Section -->
                <div class="text-center mb-12">
                    <h2 class="text-4xl font-bold text-gray-900 mb-4">مرحباً بك في نظام الجدول الأسبوعي</h2>
                    <p class="text-xl text-gray-600">اختر نوع الدخول لمتابعة العمل</p>
                </div>

                <!-- Cards Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Student Card -->
                    <div class="card-hover bg-white rounded-2xl shadow-xl overflow-hidden cursor-pointer group" onclick="window.location.href='schedule.php'">
                        <div class="h-48 student-gradient flex items-center justify-center relative overflow-hidden">
                            <div class="absolute inset-0 bg-black/20"></div>
                            <i class="fas fa-user-graduate text-white text-6xl relative z-10 group-hover:scale-110 transition-transform duration-300"></i>
                            <div class="absolute -bottom-2 -right-2 w-24 h-24 bg-white/10 rounded-full"></div>
                            <div class="absolute -top-2 -left-2 w-16 h-16 bg-white/10 rounded-full"></div>
                        </div>
                        <div class="p-8">
                            <h3 class="text-2xl font-bold text-gray-900 mb-3">طالب</h3>
                            <p class="text-gray-600 mb-6">عرض الجدول الأسبوعي للمحاضرات والاختبارات</p>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-500">الدخول كطالب</span>
                                <i class="fas fa-arrow-left text-primary group-hover:translate-x-2 transition-transform duration-300"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Faculty Card -->
                    <div class="card-hover bg-white rounded-2xl shadow-xl overflow-hidden cursor-pointer group" onclick="window.location.href='login.php'">
                        <div class="h-48 faculty-gradient flex items-center justify-center relative overflow-hidden">
                            <div class="absolute inset-0 bg-black/20"></div>
                            <i class="fas fa-chalkboard-teacher text-white text-6xl relative z-10 group-hover:scale-110 transition-transform duration-300"></i>
                            <div class="absolute -bottom-2 -right-2 w-24 h-24 bg-white/10 rounded-full"></div>
                            <div class="absolute -top-2 -left-2 w-16 h-16 bg-white/10 rounded-full"></div>
                        </div>
                        <div class="p-8">
                            <h3 class="text-2xl font-bold text-gray-900 mb-3">عضو هيئة التدريس</h3>
                            <p class="text-gray-600 mb-6">إدارة الجدول والمحاضرات والنظام</p>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-500">الدخول كعضو هيئة تدريس</span>
                                <i class="fas fa-arrow-left text-primary group-hover:translate-x-2 transition-transform duration-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="bg-white/90 backdrop-blur-md border-t">
            <div class="max-w-7xl mx-auto px-4 py-6">
                <div class="text-center text-gray-600">
                    <p class="mb-2">© 2026 كلية التقنية الهندسية-جنزور. جميع الحقوق محفوظة.</p>
                    <div class="flex justify-center space-x-6 space-x-reverse text-sm">
                        <a href="#" class="hover:text-primary transition-colors">الدعم الفني</a>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <script src="assets/JS/index.js"></script>
</body>
</html>
