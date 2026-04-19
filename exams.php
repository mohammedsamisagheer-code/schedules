<?php
require_once 'includes/config.php';

$term_names = [
    3 => 'الفصل الثالث', 4 => 'الفصل الرابع',
    5 => 'الفصل الخامس', 6 => 'الفصل السادس',
    7 => 'الفصل السابع', 8 => 'الفصل الثامن',
];
$all_terms = [3, 4, 5, 6, 7, 8];

$selected_term = isset($_GET['term']) && $_GET['term'] !== 'all' ? (int)$_GET['term'] : 'all';

// Fetch exam schedule
$exam_schedules = $pdo->query("
    SELECT es.*, s.subject_name, t.name as teacher_name, t.title as teacher_title, r.name as room_name
    FROM exam_schedules es
    JOIN subjects s ON es.subject_id = s.id
    LEFT JOIN teachers t ON s.teacher_id = t.id
    LEFT JOIN rooms r ON es.room_id = r.id
    ORDER BY es.exam_date, es.slot
")->fetchAll();

// Build grid [date => [term => entry]]
$grid = [];
foreach ($exam_schedules as $e) {
    $grid[$e['exam_date']][(int)$e['term']] = $e;
}
ksort($grid);
$dates_list = array_keys($grid);

// Fetch saved day times
$day_times = [];
try {
    $dt_rows = $pdo->query("SELECT exam_date, TIME_FORMAT(start_time, '%H:%i') as start_time FROM exam_day_times")->fetchAll();
    foreach ($dt_rows as $row) $day_times[$row['exam_date']] = $row['start_time'];
} catch (Exception $e) {}

$arabic_days = [
    '6' => 'السبت', '7' => 'الأحد', '1' => 'الإثنين',
    '2' => 'الثلاثاء', '3' => 'الإربعاء', '4' => 'الخميس', '5' => 'الجمعة'
];

// Term card colors
$term_colors = [
    3 => ['bg' => 'bg-blue-50',   'border' => 'border-r-blue-500',   'text' => 'text-blue-900',  'sub' => 'text-blue-700',  'light' => 'text-blue-600'],
    4 => ['bg' => 'bg-green-50',  'border' => 'border-r-green-500',  'text' => 'text-green-900', 'sub' => 'text-green-700', 'light' => 'text-green-600'],
    5 => ['bg' => 'bg-red-50',    'border' => 'border-r-red-500',    'text' => 'text-red-900',   'sub' => 'text-red-700',   'light' => 'text-red-600'],
    6 => ['bg' => 'bg-green-50',  'border' => 'border-r-green-500',  'text' => 'text-green-900', 'sub' => 'text-green-700', 'light' => 'text-green-600'],
    7 => ['bg' => 'bg-blue-50',   'border' => 'border-r-blue-500',   'text' => 'text-blue-900',  'sub' => 'text-blue-700',  'light' => 'text-blue-600'],
    8 => ['bg' => 'bg-red-50',    'border' => 'border-r-red-500',    'text' => 'text-red-900',   'sub' => 'text-red-700',   'light' => 'text-red-600'],
];

// Filtered terms to show
$display_terms = $selected_term === 'all' ? $all_terms : [$selected_term];
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>جدول الإمتحانات - <?php echo htmlspecialchars(COLLEGE_NAME); ?></title>
<link rel="stylesheet" href="assets/CSS/style.css">
<link href="assets/fonts/cairo.css" rel="stylesheet"/>
</head>
<body class="font-sans antialiased">

<header class="bg-white border-b border-gray-200 sticky top-0 z-50">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between h-16 items-center">
      <div class="flex items-center gap-8">
        <div class="flex-shrink-0 flex items-center">
          <img src="assets/images/logo.png" alt="logo" class="w-10 h-10 object-contain ml-2">
          <span class="font-bold text-xl tracking-tight">تقنيات نظم الحاسوب</span>
        </div>
        <nav class="flex items-center gap-1">
          <a href="schedule.php" class="px-3 py-2 rounded-custom text-sm font-medium text-gray-600 hover:text-primary hover:bg-gray-50 transition-colors">جدول المحاضرات</a>
          <a href="exams.php" class="px-3 py-2 rounded-custom text-sm font-medium text-primary bg-primary/5 transition-colors">جدول الإمتحانات</a>
        </nav>
      </div>
    </div>
  </div>
</header>

<main class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">

  <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">جدول الإمتحانات</h1>
      <p class="text-sm text-gray-600 mt-1">
        <?php echo $selected_term === 'all' ? 'جميع الفصول' : ($term_names[$selected_term] ?? ''); ?>
      </p>
    </div>
    <form method="GET" class="flex items-center gap-3">
      <select name="term" onchange="this.form.submit()"
        class="px-4 py-2 bg-white border border-gray-200 rounded-custom text-sm font-medium text-gray-700 hover:bg-gray-50 shadow-sm focus:outline-none focus:ring-2 focus:ring-primary">
        <option value="all" <?php echo $selected_term === 'all' ? 'selected' : ''; ?>>جميع الفصول</option>
        <?php foreach ($all_terms as $t): ?>
        <option value="<?php echo $t; ?>" <?php echo $selected_term === $t ? 'selected' : ''; ?>>
          <?php echo $term_names[$t]; ?>
        </option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <?php if (empty($grid)): ?>
  <div class="bg-white rounded-custom shadow border border-gray-200 p-12 text-center text-gray-400">
    <svg class="mx-auto mb-3 w-10 h-10 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-3.75h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008zM9.75 15h.008v.008H9.75V15zm0 2.25h.008v.008H9.75v-.008zM7.5 15h.008v.008H7.5V15zm0 2.25h.008v.008H7.5v-.008zm6.75-4.5h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V15zm0 2.25h.008v.008h-.008v-.008zm2.25-4.5h.008v.008H16.5v-.008zm0 2.25h.008v.008H16.5V15z"/></svg>
    <p class="text-sm">لا يوجد جدول إمتحانات حتى الآن.</p>
  </div>
  <?php else: ?>

  <div class="bg-white rounded-custom shadow border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto" id="scheduleZoomWrapper">
      <table id="scheduleZoomTable" class="text-sm border-collapse" style="width:max-content;min-width:100%;">
        <thead>
          <tr>
            <th class="bg-primary text-white px-4 py-3 text-right font-semibold border border-gray-200 sticky right-0 z-10" style="min-width:130px;">الفصل</th>
            <?php foreach ($dates_list as $date):
                $d_obj = new DateTime($date);
                $d_day = $arabic_days[$d_obj->format('N')] ?? '';
                $d_fmt = $d_obj->format('d/m/Y');
            ?>
            <th data-date-col="<?php echo $date; ?>"
                class="bg-primary text-white px-3 py-3 text-center font-semibold border border-gray-200 whitespace-nowrap" style="min-width:140px;">
              <div class="font-semibold"><?php echo $d_day; ?></div>
              <div class="text-xs font-normal opacity-80 mt-0.5"><?php echo $d_fmt; ?></div>
              <?php if (!empty($day_times[$date])): ?>
              <div class="text-xs font-normal opacity-90 mt-0.5"><?php echo htmlspecialchars($day_times[$date]); ?></div>
              <?php endif; ?>
            </th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php foreach ($display_terms as $t):
              $c = $term_colors[$t] ?? $term_colors[3];
          ?>
          <tr data-term="<?php echo $t; ?>" class="hover:brightness-95">
            <td class="px-4 py-3 font-semibold border border-gray-200 text-right whitespace-nowrap sticky right-0 <?php echo $c['bg']; ?> <?php echo $c['text']; ?>" style="min-width:130px;">
              <span class="inline-block w-2.5 h-2.5 rounded-sm ml-1.5 <?php echo $t%2===1 ? 'bg-blue-500' : ($t===5||$t===8 ? 'bg-red-500' : 'bg-green-500'); ?>"></span>
              <?php echo $term_names[$t]; ?>
            </td>
            <?php foreach ($dates_list as $date):
                $entry = $grid[$date][$t] ?? null;
            ?>
            <td data-date-col="<?php echo $date; ?>"
                class="p-2 border border-gray-200" style="min-width:140px;">
              <?php if ($entry): ?>
              <div class="class-card <?php echo $c['bg']; ?> border-r-4 <?php echo $c['border']; ?> p-3 rounded-custom flex flex-col gap-1">
                <p class="text-sm font-bold <?php echo $c['text']; ?> leading-snug">
                  <?php echo htmlspecialchars($entry['subject_name']); ?>
                </p>
                <?php if ($entry['teacher_name']): ?>
                <p class="text-xs <?php echo $c['sub']; ?> font-medium">
                  <?php echo getTitleAbbr($entry['teacher_title']) . htmlspecialchars($entry['teacher_name']); ?>
                </p>
                <?php endif; ?>
                <?php if (!empty($entry['room_name'])): ?>
                <p class="text-xs <?php echo $c['light']; ?> font-semibold">
                  <?php echo htmlspecialchars($entry['room_name']); ?>
                </p>
                <?php endif; ?>
              </div>
              <?php else: ?>
              <div class="class-card bg-gray-50 border-r-4 border-gray-200 p-3 rounded-custom flex items-center justify-center min-h-[56px]">
                <p class="text-xs text-gray-300">—</p>
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
  <div class="flex items-center justify-center gap-4 mt-4 pb-2 no-print" id="zoomControls">
    <button type="button" onclick="zoomOut()" class="w-9 h-9 flex items-center justify-center bg-white border border-gray-200 rounded-full text-gray-600 hover:bg-gray-50 shadow-sm text-xl font-bold select-none">&#x2212;</button>
    <span id="zoomLevel" class="text-sm font-semibold text-gray-500 min-w-[52px] text-center">70%</span>
    <button type="button" onclick="zoomIn()" class="w-9 h-9 flex items-center justify-center bg-white border border-gray-200 rounded-full text-gray-600 hover:bg-gray-50 shadow-sm text-xl font-bold select-none">&#x2B;</button>
  </div>

  <?php endif; ?>
</main>

<footer class="bg-white border-t border-gray-200 py-6 mt-12">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row justify-between items-center gap-4">
    <p class="text-sm text-gray-400">© 2026 <?php echo htmlspecialchars(COLLEGE_NAME); ?>. جميع الحقوق محفوظة.</p>
    <div class="flex gap-6">
      <a class="text-sm text-gray-400 hover:text-primary" href="#">الدعم</a>
    </div>
  </div>
</footer>

<script src="assets/JS/exams.js"></script>
</body>
</html>
