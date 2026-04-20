<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بوابة الجداول - قسم هندسة تقنيات الحاسوب</title>
    <meta name="description" content="بوابة الجداول الأسبوعية لقسم هندسة تقنيات الحاسوب - كلية التقنية الهندسية جنزور">
    <link rel="stylesheet" href="assets/CSS/style.css">
    <link href="assets/fonts/cairo.css" rel="stylesheet">
</head>
<body class="landing-body">

    <!-- ============ ANIMATED BACKGROUND ============ -->
    <div class="landing-bg">
        <!-- Gradient mesh layer -->
        <div class="landing-gradient"></div>

        <!-- Floating orbs -->
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
        <div class="orb orb-4"></div>
        <div class="orb orb-5"></div>

        <!-- Grid overlay -->
        <div class="landing-grid"></div>

        <!-- Particle canvas -->
        <canvas id="particleCanvas" class="landing-particles"></canvas>
    </div>

    <!-- ============ MAIN CONTENT ============ -->
    <div class="landing-content">

        <!-- Logo -->
        <div class="landing-logo" id="landingLogo">
            <img src="assets/images/logo.png" alt="كلية التقنية الهندسية - جنزور">
        </div>

        <!-- Welcome text -->
        <h1 class="landing-title" id="landingTitle">
            مرحبا بك في بوابة الجداول
            <br>
            <span class="landing-title-accent">لقسم هندسة تقنيات الحاسوب</span>
        </h1>

        <!-- Decorative divider -->
        <div class="landing-divider" id="landingDivider">
            <span></span>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                <path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58a.49.49 0 00.12-.61l-1.92-3.32a.49.49 0 00-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54a.484.484 0 00-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96a.49.49 0 00-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.07.62-.07.94s.02.64.07.94l-2.03 1.58a.49.49 0 00-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6A3.6 3.6 0 1115.6 12 3.611 3.611 0 0112 15.6z"/>
            </svg>
            <span></span>
        </div>

        <!-- CTA Button -->
        <a href="schedule.php" class="landing-cta" id="landingCta">
            <span class="landing-cta-text">اضغط هنا لعرض جدولك</span>
            <span class="landing-cta-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M15.75 19.5L8.25 12l7.5-7.5"/>
                </svg>
            </span>
            <span class="landing-cta-glow"></span>
        </a>


    </div>

    <!-- ============ FOOTER (contains hidden login) ============ -->
    <footer class="landing-footer">
        <p>
            © <span id="secretLogin" title="كلية التقنية الهندسية">2026</span>
            كلية التقنية الهندسية-جنزور
        </p>
    </footer>
    <script src="assets/JS/landing-page.js"></script>

</body>
</html>
