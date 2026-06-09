<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

checkAuth();
if (!isAdmin() && !isUser()) { header('Location: ../login.php'); exit; }
$current_user = getCurrentUser();
if (isUser()) {
    $_up = getUserPermissions($pdo, $current_user['id'] ?? null);
    if (!$_up['perm_user_exam_schedule']) { header('Location: account.php'); exit; }
}

$success = '';
$error = '';



// Handle clear
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_exams'])) {
    if (!isAdmin()) { header('Location: exam_schedule.php'); exit; }
    $pdo->exec("DELETE FROM exam_schedules");
    logActivity($pdo, 'مسح جدول الاختبارات بالكامل', $current_user['name'] ?? '');
    header('Location: exam_schedule.php?cleared=1');
    exit;
}

// Handle save exam times (per-exam)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_exam_times'])) {
    if (!isAdmin()) { header('Location: exam_schedule.php'); exit; }
    $stmt = $pdo->prepare("UPDATE exam_schedules SET start_time = ? WHERE id = ?");
    foreach ($_POST['times'] ?? [] as $exam_id => $time) {
        $exam_id = (int)$exam_id;
        $time = trim($time);
        $time = preg_replace('/[^0-9:]/', '', $time);
        if ($exam_id) $stmt->execute([$time ?: null, $exam_id]);
    }
    logActivity($pdo, 'حدّث أوقات الإمتحانات', $current_user['name'] ?? '');
    header('Location: exam_schedule.php?times_saved=1');
    exit;
}

// Handle drag-drop move (admin only, AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['move_exam'])) {
    header('Content-Type: application/json');
    if (!isAdmin()) { echo json_encode(['ok'=>false,'error'=>'Unauthorized']); exit; }
    $id       = (int)($_POST['id'] ?? 0);
    $new_date = trim($_POST['new_date'] ?? '');
    $new_term = (int)($_POST['new_term'] ?? 0);
    $swap_id  = (int)($_POST['swap_id'] ?? 0);
    if (!$id || !$new_date || !$new_term) {
        echo json_encode(['ok'=>false,'error'=>'Missing parameters']); exit;
    }
    $stmt = $pdo->prepare("SELECT es.*, s.subject_name FROM exam_schedules es LEFT JOIN subjects s ON es.subject_id = s.id WHERE es.id = ?");
    $stmt->execute([$id]);
    $orig = $stmt->fetch();
    if (!$orig) { echo json_encode(['ok'=>false,'error'=>'Not found']); exit; }
    if ($swap_id) {
        $stmt2 = $pdo->prepare("SELECT * FROM exam_schedules WHERE id = ?");
        $stmt2->execute([$swap_id]);
        $other = $stmt2->fetch();
        if (!$other) { echo json_encode(['ok'=>false,'error'=>'Swap target not found']); exit; }
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE exam_schedules SET exam_date=?, term=? WHERE id=?")->execute([$new_date, $new_term, $id]);
            $pdo->prepare("UPDATE exam_schedules SET exam_date=?, term=? WHERE id=?")->execute([$orig['exam_date'], $orig['term'], $swap_id]);
            $pdo->commit();
            logActivity($pdo, 'تبديل إمتحان: "' . $orig['subject_name'] . '"', $current_user['name'] ?? '');
            echo json_encode(['ok'=>true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
        }
    } else {
        $pdo->prepare("UPDATE exam_schedules SET exam_date=?, term=? WHERE id=?")->execute([$new_date, $new_term, $id]);
        logActivity($pdo, 'نقل إمتحان "' . $orig['subject_name'] . '"', $current_user['name'] ?? '');
        echo json_encode(['ok'=>true]);
    }
    exit;
}

// Handle auto-generate (admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auto_generate'])) {
    if (!isAdmin()) { header('Location: exam_schedule.php'); exit; }
    $start_date_str  = trim($_POST['start_date'] ?? '');
    $_es = getSettings($pdo);
    $interval        = max(2, min(7,  (int)($_es['exam_interval']      ?? 2)));
    $exams_per_day   = max(1, min(10, (int)($_es['exam_exams_per_day'] ?? 2)));

    // Fetch rooms for round-robin assignment
    $rooms_list = $pdo->query("SELECT id FROM rooms WHERE class_only = 0 ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    if (empty($rooms_list)) $rooms_list = [null];
    $rooms_count = count($rooms_list);

    if (empty($start_date_str)) {
        $error = 'الرجاء اختيار تاريخ بداية الإمتحانات';
    } else {
        // Fetch all subjects ordered by term then name
        $subjects = $pdo->query(
            "SELECT s.id, s.subject_name, s.term FROM subjects s ORDER BY s.term, s.subject_name"
        )->fetchAll();

        // Group subjects by term within each parity
        $odd_by_term  = []; // [term => [subjects]]
        $even_by_term = [];
        foreach ($subjects as $s) {
            $t = (int)$s['term'];
            if ($t % 2 === 1) $odd_by_term[$t][]  = $s;
            else              $even_by_term[$t][] = $s;
        }
        ksort($odd_by_term);
        ksort($even_by_term);

        // Helper: convert a non-Friday day-offset to a real date from a base DateTime.
        $offsetToDate = function(DateTime $base, int $offset): string {
            $d = clone $base;
            $counted = 0;
            while ($counted < $offset) {
                $d->modify('+1 day');
                if ($d->format('N') != 5) $counted++;
            }
            return $d->format('Y-m-d');
        };

        // Helper: advance a DateTime by N days, skipping Friday
        $skipFri = function(DateTime $d, int $days) {
            $d->modify("+{$days} days");
            while ($d->format('N') == 5) $d->modify('+1 day');
        };

        // Parity start dates (captured before the round-robin mutates the pointers).
        $odd_date = new DateTime($start_date_str);
        while ($odd_date->format('N') == 5) $odd_date->modify('+1 day');
        $even_date = clone $odd_date;
        $even_date->modify('+1 day');
        while ($even_date->format('N') == 5) $even_date->modify('+1 day');

        // Separate sparse (≤3 subjects) from dense (>3) per parity.
        // Sparse terms are spread across the full exam period; dense terms use round-robin.
        $sparse_assignments = [];
        $dense_odd  = [];
        $dense_even = [];
        foreach (['odd' => [$odd_by_term, $odd_date], 'even' => [$even_by_term, $even_date]] as $parity => [$terms_map, $base]) {
            if (empty($terms_map)) continue;
            $max_count  = max(array_map('count', $terms_map));
            $total_span = ($max_count > 1) ? ($max_count - 1) * $interval : 0;
            foreach ($terms_map as $term => $subs) {
                if (count($subs) <= 3) {
                    $n = count($subs);
                    foreach ($subs as $i => $s) {
                        $day_off = ($n === 1) ? 0 : (int) round($i * $total_span / ($n - 1));
                        $sparse_assignments[] = [
                            'subject_id' => $s['id'],
                            'term'       => $term,
                            'exam_date'  => $offsetToDate($base, $day_off),
                            'slot'       => 0,
                        ];
                    }
                } else {
                    if ($parity === 'odd')  $dense_odd[$term]  = $subs;
                    else                   $dense_even[$term] = $subs;
                }
            }
        }

        $groups  = ['odd' => $dense_odd, 'even' => $dense_even];
        $idx     = [
            'odd'  => array_fill_keys(array_keys($dense_odd),  0),
            'even' => array_fill_keys(array_keys($dense_even), 0),
        ];
        $offsets = ['odd' => 0, 'even' => 0];

        $hasMore = function($p) use (&$groups, &$idx) {
            foreach ($groups[$p] as $t => $subs) {
                if ($idx[$p][$t] < count($subs)) return true;
            }
            return false;
        };

        $assignments = [];

        while ($hasMore('odd') || $hasMore('even')) {
            $odd_has  = $hasMore('odd');
            $even_has = $hasMore('even');

            if ($odd_has && (!$even_has || $odd_date->format('Y-m-d') <= $even_date->format('Y-m-d'))) {
                $parity      = 'odd';
                $active_date = $odd_date;
            } else {
                $parity      = 'even';
                $active_date = $even_date;
            }

            $terms  = array_keys($groups[$parity]);
            $n      = count($terms);
            $offset = $offsets[$parity];
            $count  = 0;

            for ($i = 0; $i < $n && $count < $exams_per_day; $i++) {
                $term = $terms[($offset + $i) % $n];
                if ($idx[$parity][$term] < count($groups[$parity][$term])) {
                    $s = $groups[$parity][$term][$idx[$parity][$term]];
                    $assignments[] = [
                        'subject_id' => $s['id'],
                        'term'       => $term,
                        'exam_date'  => $active_date->format('Y-m-d'),
                        'slot'       => $count + 1,
                    ];
                    $idx[$parity][$term]++;
                    $count++;
                }
            }
            $offsets[$parity] = ($n > 0) ? (($offset + $count) % $n) : 0;
            $skipFri($active_date, $interval);
            if ($odd_date->format('Y-m-d') === $even_date->format('Y-m-d')) {
                $active_date->modify('+1 day');
                while ($active_date->format('N') == 5) $active_date->modify('+1 day');
            }
        }

        // Merge sparse into the main pool, sort by date, then re-assign slots per date.
        $all = array_merge($assignments, $sparse_assignments);
        usort($all, fn($a, $b) => strcmp($a['exam_date'], $b['exam_date']));
        $slot_ctr = [];
        $assignments = [];
        foreach ($all as $a) {
            $slot_ctr[$a['exam_date']] = ($slot_ctr[$a['exam_date']] ?? 0) + 1;
            $a['slot'] = $slot_ctr[$a['exam_date']];
            $assignments[] = $a;
        }

        // Assign rooms round-robin per slot index across the whole schedule
        $room_idx = 0;
        foreach ($assignments as &$a) {
            $a['room_id'] = $rooms_list[$room_idx % $rooms_count];
            $room_idx++;
        }
        unset($a);

        // Save
        $pdo->exec("DELETE FROM exam_schedules");
        $stmt = $pdo->prepare("INSERT INTO exam_schedules (subject_id, term, exam_date, slot, room_id) VALUES (?, ?, ?, ?, ?)");
        foreach ($assignments as $a) {
            $stmt->execute([$a['subject_id'], $a['term'], $a['exam_date'], $a['slot'], $a['room_id']]);
        }

        logActivity($pdo, 'أنشأ جدول الاختبارات تلقائياً', $current_user['name'] ?? '');
        header('Location: exam_schedule.php?generated=1');
        exit;
    }
}

// Fetch existing schedule
$exam_schedules = $pdo->query("
    SELECT es.*, s.subject_name, t.name as teacher_name, t.title as teacher_title, r.name as room_name
    FROM exam_schedules es
    JOIN subjects s ON es.subject_id = s.id
    LEFT JOIN teachers t ON s.teacher_id = t.id
    LEFT JOIN rooms r ON es.room_id = r.id
    ORDER BY es.exam_date, es.term, es.slot
")->fetchAll();

// Build grid: [date => [term => entry]]
$grid      = [];
$all_terms = [3, 4, 5, 6, 7, 8];
foreach ($exam_schedules as $e) {
    $grid[$e['exam_date']][(int)$e['term']] = $e;
}
ksort($grid);

$arabic_days = [
    '6' => 'السبت', '7' => 'الأحد', '1' => 'الإثنين',
    '2' => 'الثلاثاء', '3' => 'الإربعاء', '4' => 'الخميس', '5' => 'الجمعة'
];

$term_names = [
    3 => 'الفصل الثالث', 4 => 'الفصل الرابع',
    5 => 'الفصل الخامس', 6 => 'الفصل السادس',
    7 => 'الفصل السابع', 8 => 'الفصل الثامن',
];

// Short term headers
$term_short = [
    3 => 'ف3', 4 => 'ف4', 5 => 'ف5', 6 => 'ف6', 7 => 'ف7', 8 => 'ف8'
];

// Cell styles per term (odd=blue, even=green)
$term_style = [
    3 => ['cell' => 'bg-blue-50',  'text' => 'text-blue-900',  'header' => 'bg-blue-600'],
    4 => ['cell' => 'bg-green-50', 'text' => 'text-green-900', 'header' => 'bg-green-600'],
    5 => ['cell' => 'bg-red-50',  'text' => 'text-red-900',  'header' => 'bg-red-600'],
    6 => ['cell' => 'bg-green-50', 'text' => 'text-green-900', 'header' => 'bg-green-600'],
    7 => ['cell' => 'bg-blue-50',  'text' => 'text-blue-900',  'header' => 'bg-blue-600'],
    8 => ['cell' => 'bg-red-50', 'text' => 'text-red-900', 'header' => 'bg-red-600'],
];

// Fetch saved day times
// $day_times = [];
// if (!empty($grid)) {
//     $dt_rows = $pdo->query("SELECT exam_date, TIME_FORMAT(start_time, '%H:%i') as start_time FROM exam_day_times")->fetchAll();
//     foreach ($dt_rows as $row) $day_times[$row['exam_date']] = $row['start_time'];
// }

// JSON data for Excel export
$exam_json = json_encode(array_values($exam_schedules));
$term_names_json = json_encode($term_names);
//$day_times_json = json_encode($day_times);

// Per-term date lists for JS filtering
$term_dates = [];
foreach ($grid as $date => $terms_row) {
    foreach ($terms_row as $t => $entry) {
        $term_dates[$t][] = $date;
    }
}
$term_dates_json = json_encode($term_dates);
$dates_list = array_keys($grid); // sorted
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>جدول الإمتحانات</title>
    <link rel="stylesheet" href="../assets/CSS/style.css">
    <link href="../assets/fonts/cairo.css" rel="stylesheet"/>
    <script src="https://cdn.jsdelivr.net/npm/exceljs@4.4.0/dist/exceljs.min.js"></script>
</head>
<body class="font-sans antialiased bg-gray-50">

<?php include 'sidebar.php'; ?>

    <!-- Main content -->
    <main class="flex-1 overflow-y-auto print-full">
        <div class="p-4 md:p-6 max-w-7xl mx-auto">

            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-3 mb-6 no-print">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">جدول الإمتحانات</h1>
                    <p class="text-sm text-gray-500 mt-1">توليد وعرض جدول إمتحانات الفصول الدراسية</p>
                </div>
                <div class="flex items-center gap-2">
                    <?php if (!empty($grid)): ?>
                    <button onclick="exportExams()" class="px-4 py-2 bg-white border border-gray-200 rounded-custom text-sm font-medium text-gray-700 hover:bg-gray-50 shadow-sm flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        تصدير Excel
                    </button>

                    <?php if (isAdmin()): ?>
                    <button type="button" id="examEditModeBtn" onclick="toggleExamEditMode()" class="px-4 py-2 bg-amber-50 border border-amber-200 rounded-custom text-sm font-medium text-amber-700 hover:bg-amber-100 shadow-sm flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        <span id="examEditModeBtnText">تعديل</span>
                    </button>
                    <form id="clearExamsForm" method="POST">
                        <input type="hidden" name="clear_exams" value="1">
                        <button type="button" onclick="showClearExamsModal()" class="px-4 py-2 bg-red-50 border border-red-200 rounded-custom text-sm font-medium text-red-700 hover:bg-red-100 shadow-sm">
                            مسح الجدول
                        </button>
                    </form>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Alerts -->
            <?php if (isset($_GET['generated'])): ?>
            <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-custom text-green-800 text-sm no-print">تم توليد جدول الإمتحانات بنجاح</div>
            <?php elseif (isset($_GET['times_saved'])): ?>
            <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-custom text-green-800 text-sm no-print">تم حفظ أوقات الإمتحانات بنجاح</div>
            <?php elseif (isset($_GET['cleared'])): ?>
            <div class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-custom text-yellow-800 text-sm no-print">تم مسح جدول الإمتحانات</div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-custom text-red-800 text-sm no-print"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (isAdmin()): ?>
            <!-- Settings form -->
            <div class="bg-white rounded-custom shadow border border-gray-200 p-5 mb-6 no-print">
                <h2 class="text-base font-semibold text-gray-800 mb-4">إعدادات التوليد التلقائي</h2>
                <form method="POST" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">تاريخ بداية الإمتحانات</label>
                        <input type="date" name="start_date" required
                               value="<?php echo htmlspecialchars($_POST['start_date'] ?? ''); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-custom text-sm focus:outline-none focus:ring-2 focus:ring-primary"/>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" name="auto_generate" value="1"
                                class="w-full px-4 py-2 bg-primary text-white rounded-custom text-sm font-medium hover:bg-primary/90 shadow-sm">
                            توليد الجدول تلقائياً
                        </button>
                    </div>
                </form>
                <!-- Legend -->
                <div class="flex items-center gap-4 mt-4 text-xs text-gray-500">
                    <span class="text-gray-400">— يوم الجمعة يُتخطى تلقائياً</span>
                </div>
            </div>
            <?php endif; ?>


            <!-- Schedule display -->
            <?php if (empty($grid)): ?>
            <div class="bg-white rounded-custom shadow border border-gray-200 p-12 text-center text-gray-400">
                <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <p class="text-sm"><?php echo isAdmin() ? 'لا يوجد جدول إمتحانات. استخدم الإعدادات أعلاه لتوليد جدول جديد.' : 'لا يوجد جدول إمتحانات حتى الآن.'; ?></p>
            </div>
            <?php else: ?>

            <div class="bg-white rounded-custom shadow border border-gray-200 overflow-hidden">
                <!-- Card header with select filter -->
                <div class="p-4 border-b border-gray-200 bg-gray-50 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-semibold text-gray-800">جدول الإمتحانات — <?php echo count($grid); ?> يوم إمتحان</h2>
                    <div class="flex items-center gap-3">
                        <?php if (isAdmin()): ?>
                        <button type="button" onclick="openTimesModal()"
                            class="px-3 py-2 bg-white border border-gray-300 rounded-custom text-sm font-medium text-gray-700 hover:bg-gray-50 flex items-center gap-1.5 shadow-sm">
                            <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            أوقات الإمتحانات
                        </button>
                        <?php endif; ?>
                        <select id="examTypeSelect"
                            class="px-3 py-2 border border-gray-300 rounded-custom text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                            <option value="النهائية">النهائية</option>
                            <option value="النصفية">النصفية</option>
                        </select>
                        <select id="termSelect" onchange="filterTerm(this.value)"
                            class="px-3 py-2 border border-gray-300 rounded-custom text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                            <option value="all">جميع الفصول</option>
                            <?php foreach ($all_terms as $t): ?>
                            <option value="<?php echo $t; ?>"><?php echo $term_names[$t]; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <!-- Transposed table: columns = dates, rows = terms -->
                <div class="overflow-x-auto">
                    <table class="text-sm border-collapse" style="width:max-content;min-width:100%;">
                        <thead>
                            <tr>
                                <!-- Term label column (right side in RTL) -->
                                <th class="bg-primary text-white px-4 py-3 text-right font-semibold border border-gray-200 sticky right-0 z-10" style="min-width:130px;">الفصل</th>
                                <!-- One column per exam date -->
                                <?php foreach ($dates_list as $date):
                                    $d_obj = new DateTime($date);
                                    $d_day = $arabic_days[$d_obj->format('N')] ?? '';
                                    $d_fmt = $d_obj->format('d/m/Y');
                                ?>
                                <th data-date-col="<?php echo $date; ?>"
                                    class="bg-primary text-white px-3 py-3 text-center font-semibold border border-gray-200 whitespace-nowrap" style="min-width:120px;">
                                    <div class="font-semibold"><?php echo $d_day; ?></div>
                                    <div class="text-xs font-normal opacity-80 mt-0.5"><?php echo $d_fmt; ?></div>
                                </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_terms as $t):
                                $st = $term_style[$t];
                            ?>
                            <tr data-term="<?php echo $t; ?>" class="hover:brightness-95">
                                <!-- Term name cell (sticky right in RTL) -->
                                <td class="px-4 py-3 font-semibold border border-gray-200 text-right whitespace-nowrap sticky right-0 <?php echo $st['cell']; ?> <?php echo $st['text']; ?>" style="min-width:130px;">
                                    <span class="inline-block w-2.5 h-2.5 rounded-sm mr-1 <?php echo $t%2===1 ? 'bg-blue-500' : 'bg-green-500'; ?>"></span>
                                    <?php echo $term_names[$t]; ?>
                                </td>
                                <!-- One cell per date -->
                                <?php foreach ($dates_list as $date):
                                    $entry = $grid[$date][$t] ?? null;
                                ?>
                                <td data-date-col="<?php echo $date; ?>"
                                    data-exam-date="<?php echo $date; ?>"
                                    data-term="<?php echo $t; ?>"
                                    class="exam-cell px-2 py-3 border border-gray-200 text-center <?php echo $entry ? $st['cell'] : 'bg-white'; ?>" style="min-width:120px;">
                                    <?php if ($entry): ?>
                                    <div class="exam-card" draggable="false"
                                         data-id="<?php echo $entry['id']; ?>"
                                         data-term="<?php echo $entry['term']; ?>"
                                         data-exam-date="<?php echo $entry['exam_date']; ?>">
                                        <div class="font-semibold <?php echo $st['text']; ?> text-xs leading-snug">
                                            <?php echo htmlspecialchars($entry['subject_name']); ?>
                                        </div>
                                        <?php if ($entry['teacher_name']): ?>
                                        <div class="text-xs text-gray-400 mt-0.5">
                                            <?php echo getTitleAbbr($entry['teacher_title']) . htmlspecialchars($entry['teacher_name']); ?>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($entry['room_name'])): ?>
                                        <div class="text-xs text-gray-500 mt-0.5 font-medium">
                                            <?php echo htmlspecialchars($entry['room_name']); ?>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($entry['start_time'])): ?>
                                        <div class="text-xs font-bold <?php echo $st['text']; ?> mt-1">
                                            <?php echo substr($entry['start_time'], 0, 5); ?>
                                        </div>
                                        <?php endif; ?>
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
            <?php endif; ?>

        </div>
    </main>
</div>

<?php if (isAdmin()): ?>
<div id="clearExamsModal" class="hidden fixed inset-0 bg-gray-600/50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-6 border w-[90%] max-w-sm shadow-lg rounded-custom bg-white">
        <div class="text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856C19.07 19 20 18.07 20 16.928V7.072C20 5.93 19.07 5 17.928 5H6.072C4.93 5 4 5.93 4 7.072v9.856C4 18.07 4.93 19 6.072 19z"></path>
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">تأكيد مسح الجدول</h3>
            <p class="text-sm text-gray-600 mb-6">هل أنت متأكد من حذف جدول الإمتحانات بالكامل؟</p>
            <div class="flex gap-3">
                <button type="button" onclick="submitClearExams()" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-custom hover:bg-red-700 transition-colors font-medium">
                    مسح
                </button>
                <button type="button" onclick="closeClearExamsModal()" class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-custom hover:bg-gray-300 transition-colors font-medium">
                    إلغاء
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.drag-over { outline: 3px dashed #f59e0b; outline-offset: -3px; background-color: #fef3c7 !important; }
.exam-card.dragging { opacity: 0.4; }
.exam-card { cursor: default; }
</style>

<!-- Drag Conflict Warning Modal -->
<div id="examConflictModal" class="hidden fixed inset-0 bg-gray-600/50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-6 border w-[90%] max-w-md shadow-lg rounded-custom bg-white">
        <div class="text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-amber-100 mb-4">
                <svg class="h-6 w-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 mb-2">تحذير: تعارض محتمل</h3>
            <p id="examConflictMsg" class="text-sm text-gray-600 mb-6"></p>
            <div class="flex gap-3">
                <button id="examConflictConfirmBtn" type="button" class="flex-1 px-4 py-2 bg-amber-600 text-white rounded-custom hover:bg-amber-700 transition-colors font-medium">
                    وضعه على أي حال
                </button>
                <button type="button" onclick="examCancelDrop()" class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-custom hover:bg-gray-300 transition-colors font-medium">
                    إلغاء
                </button>
            </div>
        </div>
    </div>
</div>

<?php if (isAdmin() && !empty($grid)): ?>
<div id="dayTimesModal" class="hidden fixed inset-0 bg-gray-600/50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-10 mx-auto p-6 border w-[90%] max-w-md shadow-lg rounded-custom bg-white">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-semibold text-gray-900">أوقات بداية الإمتحانات</h3>
            <button type="button" onclick="closeTimesModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="save_exam_times" value="1">
            <div class="space-y-3 max-h-[28rem] overflow-y-auto pr-1">
                <?php
                $arabic_days_full = ['6'=>'السبت','7'=>'الأحد','1'=>'الإثنين','2'=>'الثلاثاء','3'=>'الإربعاء','4'=>'الخميس','5'=>'الجمعة'];
                foreach ($dates_list as $date):
                    $d_obj2 = new DateTime($date);
                    $d_day2 = $arabic_days_full[$d_obj2->format('N')] ?? '';
                    $d_fmt2 = $d_obj2->format('d/m/Y');
                    $day_exams = $grid[$date] ?? [];
                    ksort($day_exams);
                ?>
                <div>
                    <div class="flex items-center gap-2 mb-1.5">
                        <span class="text-xs font-semibold text-gray-600"><?php echo $d_day2; ?></span>
                        <span class="text-xs text-gray-400"><?php echo $d_fmt2; ?></span>
                    </div>
                    <div class="space-y-1 pr-2">
                    <?php foreach ($day_exams as $t => $entry):
                        $saved_time = !empty($entry['start_time']) ? substr($entry['start_time'], 0, 5) : '';
                    ?>
                    <div class="flex items-center justify-between gap-3 py-1">
                        <div class="text-sm text-gray-800 leading-snug">
                            <span class="font-medium"><?php echo $term_names[$t] ?? "الفصل $t"; ?></span>
                            <span class="text-gray-400 text-xs mr-1">— <?php echo htmlspecialchars($entry['subject_name']); ?></span>
                        </div>
                        <input type="time" name="times[<?php echo $entry['id']; ?>]" value="<?php echo htmlspecialchars($saved_time); ?>"
                               class="px-2 py-1 border border-gray-300 rounded-custom text-sm focus:outline-none focus:ring-2 focus:ring-primary w-28 shrink-0"/>
                    </div>
                    <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="flex gap-3 mt-5">
                <button type="submit" class="flex-1 px-4 py-2 bg-primary text-white rounded-custom hover:bg-primary/90 transition-colors font-medium text-sm">حفظ</button>
                <button type="button" onclick="closeTimesModal()" class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-custom hover:bg-gray-300 transition-colors font-medium text-sm">إلغاء</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
const termDates     = <?php echo $term_dates_json ?? '{}'; ?>;
const examRawData   = <?php echo $exam_json ?? '[]'; ?>;
const termNamesData = <?php echo $term_names_json ?? '{}'; ?>;
const dayTimesData  = <?php echo $day_times_json ?? '{}'; ?>;
const academicYear  = <?php
    $_ay = $pdo->query("SELECT `value` FROM `settings` WHERE `key`='academic_year'")->fetchColumn();
    echo json_encode($_ay ?: '', JSON_UNESCAPED_UNICODE);
?>;

function showClearExamsModal() {
    const modal = document.getElementById('clearExamsModal');
    if (modal) modal.classList.remove('hidden');
}

function closeClearExamsModal() {
    const modal = document.getElementById('clearExamsModal');
    if (modal) modal.classList.add('hidden');
}

function submitClearExams() {
    const form = document.getElementById('clearExamsForm');
    if (form) form.submit();
}

function openTimesModal() {
    const modal = document.getElementById('dayTimesModal');
    if (modal) modal.classList.remove('hidden');
}

function closeTimesModal() {
    const modal = document.getElementById('dayTimesModal');
    if (modal) modal.classList.add('hidden');
}

// ─── Drag & Drop Edit Mode ────────────────────────────────────────────────────
let examEditMode = false;
let examDragState = null;
let examPendingDrop = null;

function toggleExamEditMode() {
    examEditMode = !examEditMode;
    const btn = document.getElementById('examEditModeBtn');
    const txt = document.getElementById('examEditModeBtnText');
    if (!btn) return;
    if (examEditMode) {
        txt.textContent = '\u0625\u0646\u0647\u0627\u0621 \u0627\u0644\u062a\u0639\u062f\u064a\u0644';
        btn.classList.add('active');
        document.querySelectorAll('.exam-card').forEach(c => {
            c.setAttribute('draggable', 'true');
            c.style.cursor = 'grab';
        });
    } else {
        txt.textContent = '\u062a\u0639\u062f\u064a\u0644';
        btn.classList.remove('active');
        document.querySelectorAll('.exam-card').forEach(c => {
            c.removeAttribute('draggable');
            c.style.cursor = '';
        });
        document.querySelectorAll('td.drag-over').forEach(td => td.classList.remove('drag-over'));
    }
}

function examCancelDrop() {
    examPendingDrop = null;
    document.getElementById('examConflictModal').classList.add('hidden');
}

async function examExecuteDrop({ id, swapId, newDate, newTerm }) {
    document.getElementById('examConflictModal').classList.add('hidden');
    examPendingDrop = null;
    const body = new URLSearchParams({ move_exam: '1', id, new_date: newDate, new_term: newTerm });
    if (swapId) body.append('swap_id', swapId);
    try {
        const res = await fetch('exam_schedule.php', { method: 'POST', body });
        const json = await res.json();
        if (json.ok) {
            window.location.reload();
        } else {
            alert('\u062d\u062f\u062b \u062e\u0637\u0623: ' + (json.error || '\u062e\u0637\u0623 \u063a\u064a\u0631 \u0645\u0639\u0631\u0648\u0641'));
        }
    } catch {
        alert('\u0641\u0634\u0644 \u0627\u0644\u0627\u062a\u0635\u0627\u0644 \u0628\u0627\u0644\u062e\u0627\u062f\u0645');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.addEventListener('dragstart', e => {
        if (!examEditMode) return;
        const card = e.target.closest('.exam-card');
        if (!card) return;
        examDragState = { id: card.dataset.id, term: card.dataset.term, examDate: card.dataset.examDate };
        e.dataTransfer.effectAllowed = 'move';
        setTimeout(() => card.classList.add('dragging'), 0);
    });

    document.addEventListener('dragend', e => {
        const card = e.target.closest('.exam-card');
        if (card) card.classList.remove('dragging');
        document.querySelectorAll('td.drag-over').forEach(td => td.classList.remove('drag-over'));
    });

    document.addEventListener('dragover', e => {
        if (!examEditMode || !examDragState) return;
        const td = e.target.closest('td.exam-cell');
        if (td) e.preventDefault();
    });

    document.addEventListener('dragenter', e => {
        if (!examEditMode || !examDragState) return;
        const td = e.target.closest('td.exam-cell');
        if (td) td.classList.add('drag-over');
    });

    document.addEventListener('dragleave', e => {
        if (!examEditMode) return;
        const td = e.target.closest('td.exam-cell');
        if (td && !td.contains(e.relatedTarget)) td.classList.remove('drag-over');
    });

    document.addEventListener('drop', e => {
        if (!examEditMode || !examDragState) return;
        const td = e.target.closest('td.exam-cell');
        if (!td) return;
        e.preventDefault();
        td.classList.remove('drag-over');

        const targetDate = td.dataset.examDate;
        const targetTerm = td.dataset.term;

        if (examDragState.examDate === targetDate && examDragState.term === targetTerm) {
            examDragState = null;
            return;
        }

        const targetCard = td.querySelector('.exam-card');
        const swapId = targetCard ? targetCard.dataset.id : 0;
        const draggedId = examDragState.id;
        const dropArgs = { id: draggedId, swapId, newDate: targetDate, newTerm: targetTerm };
        examDragState = null;

        if (swapId) {
            document.getElementById('examConflictMsg').textContent =
                '\u0647\u0630\u0647 \u0627\u0644\u062e\u0644\u064a\u0629 \u0645\u0634\u063a\u0648\u0644\u0629 \u0628\u0641\u062d\u0635 \u0622\u062e\u0631. \u0633\u064a\u062a\u0645 \u0627\u0644\u062a\u0628\u0627\u062f\u0644. \u0647\u0644 \u062a\u0648\u0627\u0635\u0644\u061f';
            examPendingDrop = dropArgs;
            document.getElementById('examConflictConfirmBtn').onclick = () => examExecuteDrop(examPendingDrop);
            document.getElementById('examConflictModal').classList.remove('hidden');
        } else {
            examExecuteDrop(dropArgs);
        }
    });
});
</script>
<script src="../assets/JS/admin-common.js"></script>
<script src="../assets/JS/exam-schedule.js?v=<?php echo filemtime('../assets/JS/exam-schedule.js'); ?>"></script>
</body>
</html>