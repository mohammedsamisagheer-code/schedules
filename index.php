<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>كلية التقنية الهندسية-جنزور - الجدول الأسبوعي</title>
    <link rel="stylesheet" href="assets/CSS/style.css">
    <link href="assets/fonts/cairo.css" rel="stylesheet">
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
                            <p class="text-gray-600">قسم هندسة تقنيات الحاسوب</p>
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
                            <svg class="text-white w-16 h-16 relative z-10 group-hover:scale-110 transition-transform duration-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.906 59.906 0 0112 3.493a59.903 59.903 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5"/></svg>
                            <div class="absolute -bottom-2 -right-2 w-24 h-24 bg-white/10 rounded-full"></div>
                            <div class="absolute -top-2 -left-2 w-16 h-16 bg-white/10 rounded-full"></div>
                        </div>
                        <div class="p-8">
                            <h3 class="text-2xl font-bold text-gray-900 mb-3">طالب</h3>
                            <p class="text-gray-600 mb-6">عرض الجدول الأسبوعي للمحاضرات والاختبارات</p>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-500">الدخول كطالب</span>
                                <svg class="w-5 h-5 text-primary group-hover:-translate-x-1 transition-transform duration-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
                            </div>
                        </div>
                    </div>

                    <!-- Faculty Card -->
                    <div class="card-hover bg-white rounded-2xl shadow-xl overflow-hidden cursor-pointer group" onclick="window.location.href='login.php'">
                        <div class="h-48 faculty-gradient flex items-center justify-center relative overflow-hidden">
                            <div class="absolute inset-0 bg-black/20"></div>
                            <svg class="text-white w-16 h-16 relative z-10 group-hover:scale-110 transition-transform duration-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h12A2.25 2.25 0 0020.25 14.25V3M3.75 3H20.25M3.75 3H2.25m18 0h1.5M3.75 16.5v2.25A2.25 2.25 0 006 21h12a2.25 2.25 0 002.25-2.25V16.5M12 12.75V7.5m0 5.25l2.25-2.25M12 12.75L9.75 10.5"/></svg>
                            <div class="absolute -bottom-2 -right-2 w-24 h-24 bg-white/10 rounded-full"></div>
                            <div class="absolute -top-2 -left-2 w-16 h-16 bg-white/10 rounded-full"></div>
                        </div>
                        <div class="p-8">
                            <h3 class="text-2xl font-bold text-gray-900 mb-3">عضو هيئة التدريس</h3>
                            <p class="text-gray-600 mb-6">إدارة الجدول والمحاضرات والنظام</p>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-500">الدخول كعضو هيئة تدريس</span>
                                <svg class="w-5 h-5 text-primary group-hover:-translate-x-1 transition-transform duration-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
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
