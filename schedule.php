<?php
require_once 'includes/config.php';

$selected_term = isset($_GET['term']) ? $_GET['term'] : 'all';
$show_all = ($selected_term === 'all');

$query = "SELECT s.*, sb.subject_name, sb.term, t.name as teacher_name, t.title as teacher_title, r.name as room_name
          FROM schedules s
          LEFT JOIN subjects sb ON s.subject_id = sb.id
          LEFT JOIN teachers t ON s.teacher_id = t.id
          LEFT JOIN rooms r ON s.room_id = r.id";

if (!$show_all) {
    $query .= " WHERE sb.term = :term";
}
$query .= " ORDER BY sb.term, s.time";

$stmt = $pdo->prepare($query);
if (!$show_all) {
    $stmt->execute(['term' => $selected_term]);
} else {
    $stmt->execute();
}
$schedules = $stmt->fetchAll();

// Predefined time slots from settings
$time_slots = buildTimeSlots(CLASSES_START_TIME, PERIODS_COUNT);

$days = ['السبت', 'الأحد', 'الإثنين', 'الثلاثاء', 'الإربعاء', 'الخميس'];

$term_names = [
    '3' => 'الفصل الثالث', '4' => 'الفصل الرابع',
    '5' => 'الفصل الخامس', '6' => 'الفصل السادس',
    '7' => 'الفصل السابع', '8' => 'الفصل الثامن',
];

// Dual grouping: by term+day+time (all view) and by day+time (single view)
$schedules_by_term_day_time = [];
$schedules_by_day_time = [];
$available_terms = [];
foreach ($schedules as $schedule) {
    $term = (string)$schedule['term'];
    $day  = $schedule['day_of_week'];
    $tf   = date('H:i', strtotime($schedule['time']));
    $schedules_by_term_day_time[$term][$day][$tf][] = $schedule;
    $schedules_by_day_time[$day][$tf][] = $schedule;
    if (!in_array($term, $available_terms)) $available_terms[] = $term;
}
sort($available_terms);

// Assign teacher colors
$unique_teachers = [];
foreach ($schedules as $s) {
    $dn = getTitleAbbr($s['teacher_title']) . $s['teacher_name'];
    if (!in_array($dn, $unique_teachers)) $unique_teachers[] = $dn;
}
$available_colors = ['blue','green','purple','orange','red','pink','indigo','yellow','teal','cyan'];
$teacher_colors = [];
foreach ($unique_teachers as $i => $t) {
    $teacher_colors[$t] = $available_colors[$i % count($available_colors)];
}

function getTeacherColorClass($teacher, $teacher_colors) {
    $color = isset($teacher_colors[$teacher]) ? $teacher_colors[$teacher] : 'gray';
    return [
        'bg'           => "bg-{$color}-50",
        'border'       => "border-r-{$color}-500",
        'text'         => "text-{$color}-900",
        'text_light'   => "text-{$color}-700",
        'text_lighter' => "text-{$color}-600"
    ];
}
?>

<!DOCTYPE html>

<html dir="rtl" lang="ar"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>لوحة تحكم الطالب - الجدول الأسبوعي</title>
<link rel="stylesheet" href="assets/CSS/style.css">
<!-- Local Fonts: Cairo (better for Arabic) -->
<link href="assets/fonts/cairo.css" rel="stylesheet"/>
</head>
<body class="font-sans antialiased">
<!-- BEGIN: MainHeader -->
<header class="bg-white border-b border-gray-200 sticky top-0 z-50" data-purpose="navigation-header">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between h-16 items-center">
      <div class="flex items-center gap-8">
        <div class="flex-shrink-0 flex items-center">
          <img src="assets/images/logo.png" alt="logo" class="w-10 h-10 object-contain ml-2">
          <span class="font-bold text-xl tracking-tight">هندسة تقنيات الحاسوب</span>
        </div>
        <nav class="flex items-center gap-1">
          <a href="schedule.php" class="px-3 py-2 rounded-custom text-sm font-medium text-primary bg-primary/5 transition-colors">جدول المحاضرات</a>
          <a href="exams.php" class="px-3 py-2 rounded-custom text-sm font-medium text-gray-600 hover:text-primary hover:bg-gray-50 transition-colors">جدول الإمتحانات</a>
        </nav>
      </div>
    </div>
  </div>
</header>
<!-- END: MainHeader -->
<!-- BEGIN: MainContent -->
<main class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
<!-- BEGIN: DashboardIntro -->
<div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
<div>
<h1 class="text-2xl font-bold text-gray-900">جدول المحاضرات</h1>
<p class="text-sm text-gray-600 mt-1">
  <?php echo $show_all ? 'جميع الفصول' : ($term_names[$selected_term] ?? 'الفصل ' . $selected_term); ?>
</p>
</div>
<div class="flex items-center gap-3">
<form method="GET" class="flex items-center gap-3">
  <select name="term" id="term-select" onchange="this.form.submit()" class="px-4 py-2 bg-white border border-gray-200 rounded-custom text-sm font-medium text-gray-700 hover:bg-gray-50 shadow-sm focus:outline-none focus:ring-2 focus:ring-primary">
    <option value="all" <?php echo $show_all ? 'selected' : ''; ?>>جميع الفصول</option>
    <option value="3" <?php echo $selected_term === '3' ? 'selected' : ''; ?>>الفصل الثالث</option>
    <option value="4" <?php echo $selected_term === '4' ? 'selected' : ''; ?>>الفصل الرابع</option>
    <option value="5" <?php echo $selected_term === '5' ? 'selected' : ''; ?>>الفصل الخامس</option>
    <option value="6" <?php echo $selected_term === '6' ? 'selected' : ''; ?>>الفصل السادس</option>
    <option value="7" <?php echo $selected_term === '7' ? 'selected' : ''; ?>>الفصل السابع</option>
    <option value="8" <?php echo $selected_term === '8' ? 'selected' : ''; ?>>الفصل الثامن</option>
  </select>
</form>
</div>
</div>
<!-- END: DashboardIntro -->
<!-- BEGIN: ScheduleView -->
<div class="bg-white rounded-custom shadow border border-gray-200 overflow-hidden">
<div class="overflow-x-auto" id="scheduleZoomWrapper">
<table id="scheduleZoomTable" class="w-full text-right border-collapse min-w-[800px]">
<thead>
<tr class="bg-gray-50">
<?php if ($show_all): ?>
<th class="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center w-[80px]">الفصل</th>
<?php endif; ?>
<th class="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center w-[120px]">الوقت</th>
<th class="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center ">السبت</th>
<th class="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center">الأحد</th>
<th class="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center">الاثنين</th>
<th class="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center">الثلاثاء</th>
<th class="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center">الإربعاء</th>
<th class="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center">الخميس</th>
</tr>
</thead>
<tbody class="divide-y divide-gray-200">
<?php if ($show_all): ?>
    <?php foreach ($available_terms as $term): ?>
        <?php $slot_count = count($time_slots); $first = true; ?>
        <?php foreach ($time_slots as $slot_raw => $slot_label): ?>
            <?php $slot_key = date('H:i', strtotime($slot_raw)); ?>
            <tr>
                <?php if ($first): ?>
                <td class="bg-primary/5 p-2 text-center font-bold text-primary text-sm border-r border-gray-100" rowspan="<?php echo $slot_count; ?>">
                    <?php echo htmlspecialchars($term_names[$term] ?? 'الفصل ' . $term); ?>
                </td>
                <?php $first = false; endif; ?>
                <td class="bg-gray-50/50 p-4 text-center">
                    <div class="flex flex-col items-center justify-center text-xs text-gray-500 font-medium">
                        <span><?php echo $slot_label; ?></span>
                    </div>
                </td>
                <?php foreach ($days as $day): ?>
                <td class="day-col p-2 border-r border-gray-100">
                    <?php if (isset($schedules_by_term_day_time[$term][$day][$slot_key])): ?>
                        <?php foreach ($schedules_by_term_day_time[$term][$day][$slot_key] as $schedule): ?>
                            <?php $td = getTitleAbbr($schedule['teacher_title']) . $schedule['teacher_name']; ?>
                            <?php $colors = getTeacherColorClass($td, $teacher_colors); ?>
                            <div class="class-card <?php echo $colors['bg']; ?> border-r-4 <?php echo $colors['border']; ?> p-3 rounded flex flex-col justify-between">
                                <div>
                                    <p class="text-sm font-bold <?php echo $colors['text']; ?> truncate"><?php echo htmlspecialchars($schedule['subject_name']); ?></p>
                                    <p class="text-xs <?php echo $colors['text_light']; ?> font-medium mt-1"><?php echo htmlspecialchars($td); ?></p>
                                </div>
                                <p class="text-xs <?php echo $colors['text_lighter']; ?> font-semibold"><?php echo htmlspecialchars($schedule['room_name']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="class-card bg-gray-100 border-r-4 border-gray-400 p-3 rounded flex items-center justify-center">
                            <p class="text-xs text-gray-400"> </p>
                        </div>
                    <?php endif; ?>
                </td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
    <?php endforeach; ?>
<?php else: ?>
    <?php foreach ($time_slots as $slot_raw => $slot_label): ?>
        <?php $slot_key = date('H:i', strtotime($slot_raw)); ?>
        <tr>
            <td class="bg-gray-50/50 p-4 text-center">
                <div class="flex flex-col items-center justify-center text-xs text-gray-500 font-medium">
                    <span><?php echo $slot_label; ?></span>
                </div>
            </td>
            <?php foreach ($days as $day): ?>
            <td class="day-col p-2 border-r border-gray-100">
                <?php if (isset($schedules_by_day_time[$day][$slot_key])): ?>
                    <?php foreach ($schedules_by_day_time[$day][$slot_key] as $schedule): ?>
                        <?php $td = getTitleAbbr($schedule['teacher_title']) . $schedule['teacher_name']; ?>
                        <?php $colors = getTeacherColorClass($td, $teacher_colors); ?>
                        <div class="class-card <?php echo $colors['bg']; ?> border-r-4 <?php echo $colors['border']; ?> p-3 rounded flex flex-col justify-between">
                            <div>
                                <p class="text-sm font-bold <?php echo $colors['text']; ?> truncate"><?php echo htmlspecialchars($schedule['subject_name']); ?></p>
                                <p class="text-xs <?php echo $colors['text_light']; ?> font-medium mt-1"><?php echo htmlspecialchars($td); ?></p>
                            </div>
                            <p class="text-xs <?php echo $colors['text_lighter']; ?> font-semibold"><?php echo htmlspecialchars($schedule['room_name']); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="class-card bg-gray-100 border-r-4 border-gray-400 p-3 rounded flex items-center justify-center">
                        <p class="text-xs text-gray-400"> </p>
                    </div>
                <?php endif; ?>
            </td>
            <?php endforeach; ?>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
<div class="flex items-center justify-center gap-4 mt-4 pb-2 no-print" id="zoomControls">
<button type="button" onclick="zoomOut()" class="w-9 h-9 flex items-center justify-center bg-white border border-gray-200 rounded-full text-gray-600 hover:bg-gray-50 shadow-sm text-xl font-bold select-none">&#x2212;</button>
<span id="zoomLevel" class="text-sm font-semibold text-gray-500 min-w-[52px] text-center">70%</span>
<button type="button" onclick="zoomIn()" class="w-9 h-9 flex items-center justify-center bg-white border border-gray-200 rounded-full text-gray-600 hover:bg-gray-50 shadow-sm text-xl font-bold select-none">&#x2B;</button>
</div>
<!-- END: ScheduleView -->
</main>
<!-- END: MainContent -->
<!-- BEGIN: Footer -->
<footer class="bg-white border-t border-gray-200 py-6 mt-12">
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row justify-between items-center gap-4">
<p class="text-sm text-gray-400"> 2026 <?php echo htmlspecialchars(COLLEGE_NAME); ?>. جميع الحقوق محفوظة.</p>
<div class="flex gap-6">
<a class="text-sm text-gray-400 hover:text-primary" href="#">الدعم</a>
</div>
</div>
</footer>
<!-- END: Footer -->
<script src="assets/JS/schedule.js?v=2"></script>
</body>
</html>