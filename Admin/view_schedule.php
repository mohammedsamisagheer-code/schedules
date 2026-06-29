<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

checkAuth();
if (!isAdmin() && !isUser()) { header('Location: ../login.php'); exit; }
$current_user = getCurrentUser();
if (isUser()) {
    $_up = getUserPermissions($pdo, $current_user['id'] ?? null);
    if (!$_up['perm_user_view_schedule']) { header('Location: account.php'); exit; }
}

$auto_error = '';
$auto_success = '';

// Handle clear schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_schedule'])) {
    if (!isAdmin()) { header('Location: view_schedule.php'); exit; }
    $pdo->exec("DELETE FROM schedules");
    logActivity($pdo, 'مسح الجدول الدراسي بالكامل', $current_user['name'] ?? '');
    header('Location: view_schedule.php?term=all&cleared=1');
    exit;
}

// ── Scheduler helpers ────────────────────────────────────────────────────────
function _generateOnce(array $all_subjects, array $all_rooms, array $days_list, array $times_list, int $max_teaching_days, array $all_term_numbers = []): array {
    $teacher_slots = []; $room_slots = []; $term_slots = []; $subject_slots = [];
    $teacher_days  = []; $room_usage  = []; $assignments  = []; $unassigned   = [];
    $day_index = array_flip($days_list);
    $preferred = [];
    foreach ($all_subjects as $s) {
        $sid = $s['id'];
        if (!isset($preferred[$sid])) $preferred[$sid] = [];
        if (!empty($s['requires_subject_id'])) {
            $rid = $s['requires_subject_id'];
            if (!in_array($rid, $preferred[$sid])) $preferred[$sid][] = $rid;
            if (!isset($preferred[$rid])) $preferred[$rid] = [];
            if (!in_array($sid, $preferred[$rid])) $preferred[$rid][] = $sid;
        }
    }
    $day_pairs = [];
    for ($a = 0; $a < count($days_list); $a++)
        for ($b = $a+1; $b < count($days_list); $b++)
            if (abs($a-$b) > 1) $day_pairs[] = [$a,$b];

    $tryPlaceOnDay = function($subject,$teacher_id,$day,$di)
        use(&$teacher_slots,&$room_slots,&$term_slots,&$subject_slots,
            &$teacher_days,&$assignments,&$preferred,&$room_usage,
            $all_rooms,$times_list,$max_teaching_days,$all_term_numbers) {
        $t_days = $teacher_days[$teacher_id] ?? [];
        if (!in_array($di,$t_days) && count($t_days)>=$max_teaching_days) return false;
        $sid=$subject['id']; $st=(int)$subject['term'];
        if (!empty($subject_slots[$sid][$day])) return false;
        $pref_ids=$preferred[$sid]??[]; $prev=$st-1;
        $t1=[]; $t2=[]; $t3=[];
        foreach ($times_list as $time) {
            $ip=false;
            foreach ($pref_ids as $pid) { if(isset($subject_slots[$pid][$day][$time])){$ip=true;break;} }
            if ($ip) $t1[]=$time;
            elseif (in_array($prev, $all_term_numbers) && empty($term_slots[$prev][$day][$time])) $t2[]=$time;
            else $t3[]=$time;
        }
        foreach (array_merge($t1,$t2,$t3) as $time) {
            if (isset($teacher_slots[$teacher_id][$day][$time])) continue;
            if (isset($term_slots[$st][$day][$time])) continue;
            $fr=null; $sr=$all_rooms;
            usort($sr,function($a,$b)use($room_usage){return($room_usage[$a['id']]??0)-($room_usage[$b['id']]??0);});
            foreach ($sr as $room) { if(!isset($room_slots[$room['id']][$day][$time])){$fr=$room;break;} }
            if ($fr===null) continue;
            $teacher_slots[$teacher_id][$day][$time]=true;
            $room_slots[$fr['id']][$day][$time]=true;
            $room_usage[$fr['id']]=($room_usage[$fr['id']]??0)+1;
            $term_slots[$st][$day][$time]=true;
            $subject_slots[$sid][$day][$time]=true;
            if (!isset($teacher_days[$teacher_id])) $teacher_days[$teacher_id]=[];
            if (!in_array($di,$teacher_days[$teacher_id])) $teacher_days[$teacher_id][]=$di;
            $assignments[]=['subject_id'=>$sid,'teacher_id'=>$teacher_id,'room_id'=>$fr['id'],'day_of_week'=>$day,'time'=>$time];
            return true;
        }
        return false;
    };
    $sortDays = function($days,$term) use(&$term_slots,$all_term_numbers) {
        $prev=$term-1; if(!in_array($prev, $all_term_numbers)) return $days;
        usort($days,function($a,$b)use(&$term_slots,$prev){return(empty($term_slots[$prev][$b])?1:0)-(empty($term_slots[$prev][$a])?1:0);});
        return $days;
    };
    $sortPairs = function($pairs,$term) use($days_list,&$term_slots,$all_term_numbers) {
        $prev=$term-1; if(!in_array($prev, $all_term_numbers)) return $pairs;
        $empty=[];
        foreach($days_list as $i=>$d){if(empty($term_slots[$prev][$d]))$empty[$i]=true;}
        if(empty($empty)) return $pairs;
        usort($pairs,function($a,$b)use($empty){
            return ((isset($empty[$b[0]])?1:0)+(isset($empty[$b[1]])?1:0))-((isset($empty[$a[0]])?1:0)+(isset($empty[$a[1]])?1:0));
        });
        return $pairs;
    };
    $tload=[];
    foreach($all_subjects as $s) $tload[$s['teacher_id']]=($tload[$s['teacher_id']]??0)+1;
    $shuffled=$all_subjects; shuffle($shuffled);
    usort($shuffled,function($a,$b)use($tload){$d=($tload[$b['teacher_id']]??0)-($tload[$a['teacher_id']]??0);return $d!==0?$d:$a['term']-$b['term'];});

    foreach ($shuffled as $subject) {
        $teacher_id=$subject['teacher_id'];
        $needed=isset($subject['priority'])?(int)$subject['priority']:2;
        $cnt=0;
        if ($needed==2) {
            $pref_ids=$preferred[$subject['id']]??[];
            if(!empty($pref_ids)){
                foreach($pref_ids as $pid){
                    if($cnt>=$needed) break;
                    if(isset($subject_slots[$pid])){
                        foreach(array_keys($subject_slots[$pid]) as $pday){
                            if($cnt>=$needed) break;
                            if($tryPlaceOnDay($subject,$teacher_id,$pday,$day_index[$pday])) $cnt++;
                        }
                    }
                }
            }
            if ($cnt==0) {
                $pairs=$day_pairs; shuffle($pairs);
                $pairs=$sortPairs($pairs,(int)$subject['term']);
                foreach($pairs as $pair){
                    if($cnt>=2) break;
                    $di1=$pair[0];$di2=$pair[1];
                    $d1=$days_list[$di1];$d2=$days_list[$di2];
                    if(!$tryPlaceOnDay($subject,$teacher_id,$d1,$di1)) continue;
                    if($tryPlaceOnDay($subject,$teacher_id,$d2,$di2)){$cnt=2;}
                    else{
                        $last=array_pop($assignments);
                        unset($teacher_slots[$teacher_id][$last['day_of_week']][$last['time']]);
                        unset($room_slots[$last['room_id']][$last['day_of_week']][$last['time']]);
                        unset($term_slots[(int)$subject['term']][$last['day_of_week']][$last['time']]);
                        unset($subject_slots[$subject['id']][$last['day_of_week']][$last['time']]);
                        if(empty($teacher_slots[$teacher_id][$last['day_of_week']]??[]))
                            $teacher_days[$teacher_id]=array_values(array_diff($teacher_days[$teacher_id]??[],[$di1]));
                    }
                }
            }
            if ($cnt<2) {
                $sd=$days_list; shuffle($sd); $sd=$sortDays($sd,(int)$subject['term']);
                $pd=[];
                if(isset($subject_slots[$subject['id']]))
                    foreach(array_keys($subject_slots[$subject['id']]) as $p) $pd[]=$day_index[$p];
                foreach($sd as $day){
                    if($cnt>=2) break; $di=$day_index[$day]; $tc=false;
                    foreach($pd as $pdi){if(abs($di-$pdi)<=1){$tc=true;break;}}
                    if($tc) continue;
                    if($tryPlaceOnDay($subject,$teacher_id,$day,$di)){$pd[]=$di;$cnt++;}
                }
            }
            if ($cnt<2) {
                $sd=$days_list; shuffle($sd); $sd=$sortDays($sd,(int)$subject['term']);
                foreach($sd as $day){if($cnt>=2)break;if($tryPlaceOnDay($subject,$teacher_id,$day,$day_index[$day]))$cnt++;}
            }
        } else {
            $pref_ids=$preferred[$subject['id']]??[];
            if(!empty($pref_ids)){
                foreach($pref_ids as $pid){
                    if($cnt>=1) break;
                    if(isset($subject_slots[$pid])){
                        foreach(array_keys($subject_slots[$pid]) as $pday){
                            if($cnt>=1) break;
                            if($tryPlaceOnDay($subject,$teacher_id,$pday,$day_index[$pday])) $cnt=1;
                        }
                    }
                }
            }
            if($cnt<1){
                $sd=$days_list; shuffle($sd); $sd=$sortDays($sd,(int)$subject['term']);
                foreach($sd as $day){if($tryPlaceOnDay($subject,$teacher_id,$day,$day_index[$day])){$cnt=1;break;}}
            }
        }
        if($cnt<$needed) $unassigned[]=$subject['subject_name'].' (ف'.$subject['term'].') - '.$cnt.'/'.$needed;
    }
    return [$assignments,$unassigned];
}

function _countConflictsInMemory(array $assignments, array $all_subjects, array $all_term_numbers = []): int {
    $st=[]; $rel=[];
    foreach($all_subjects as $s){
        $st[(int)$s['id']]=(int)$s['term'];
        if(!empty($s['requires_subject_id'])){$a=(int)$s['id'];$b=(int)$s['requires_subject_id'];$rel[min($a,$b).'_'.max($a,$b)]=true;}
    }
    $map=[];
    foreach($assignments as $a){
        $sid=(int)$a['subject_id']; if(!isset($st[$sid])) continue;
        $map[$a['day_of_week']][substr($a['time'],0,5)][$st[$sid]][]=$sid;
    }
    $cnt=0;
    foreach($map as $times) foreach($times as $terms){
        $tk=array_keys($terms); sort($tk);
        foreach($tk as $t){ if(!isset($terms[$t+1])) continue;
            foreach($terms[$t] as $s1) foreach($terms[$t+1] as $s2)
                if(!isset($rel[min($s1,$s2).'_'.max($s1,$s2)])) $cnt++;
        }
    }
    return $cnt;
}
// ── End helpers ───────────────────────────────────────────────────────────────

// Handle auto-generate schedule (iterative best-of search)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auto_generate'])) {
    if (!isAdmin()) { header('Location: view_schedule.php'); exit; }
    $days_list         = ['السبت', 'الأحد', 'الإثنين', 'الثلاثاء', 'الإربعاء', 'الخميس'];
    $_gen_settings     = getSettings($pdo);
    $max_teaching_days = (int)($_gen_settings['max_teaching_days'] ?? 4);
    $times_list        = array_keys(buildTimeSlots(
        $_gen_settings['classes_start_time'] ?? CLASSES_START_TIME,
        (int)($_gen_settings['periods_count'] ?? PERIODS_COUNT)
    ));
    $all_subjects = $pdo->query("SELECT s.*, t.name as teacher_name, t.title as teacher_title FROM subjects s LEFT JOIN teachers t ON s.teacher_id = t.id ORDER BY s.term, s.id")->fetchAll();
    $all_rooms    = $pdo->query("SELECT * FROM rooms WHERE exam_only = 0 ORDER BY id")->fetchAll();

    if (empty($all_subjects)) {
        $auto_error = 'لا توجد مواد لإنشاء الجدول';
    } elseif (empty($all_rooms)) {
        $auto_error = 'لا توجد قاعات لإنشاء الجدول';
    } else {
        set_time_limit(180);
        $best_assignments = [];
        $best_unassigned  = [];
        $best_conflicts   = PHP_INT_MAX;
        $no_improve       = 0;
        $iterations       = 0;
        $_gen_term_nums   = getTermNumbers($pdo);

        while ($no_improve < 15 && $iterations < 100 && $best_conflicts > 0) {
            [$asgn, $unasgn] = _generateOnce($all_subjects, $all_rooms, $days_list, $times_list, $max_teaching_days, $_gen_term_nums);
            $conflicts = _countConflictsInMemory($asgn, $all_subjects, $_gen_term_nums);
            $iterations++;
            if ($conflicts < $best_conflicts) {
                $best_conflicts   = $conflicts;
                $best_assignments = $asgn;
                $best_unassigned  = $unasgn;
                $no_improve       = 0;
            } else {
                $no_improve++;
            }
        }

        $pdo->exec("DELETE FROM schedules");
        $ins = $pdo->prepare("INSERT INTO schedules (subject_id, teacher_id, room_id, day_of_week, time) VALUES (?, ?, ?, ?, ?)");
        foreach ($best_assignments as $a) {
            $ins->execute([$a['subject_id'], $a['teacher_id'], $a['room_id'], $a['day_of_week'], $a['time']]);
        }
        $count            = count($best_assignments);
        $unassigned_param = !empty($best_unassigned) ? '&unassigned=' . urlencode(implode('، ', $best_unassigned)) : '';
        logActivity($pdo, 'إنشاء الجدول (' . $iterations . ' محاولة، ' . $best_conflicts . ' تعارض)', $current_user['name'] ?? '');
        header('Location: view_schedule.php?term=all&auto=success&count=' . $count . '&conflicts=' . $best_conflicts . '&iterations=' . $iterations . $unassigned_param);
        exit;
    }
}

// Check for auto-generate result from redirect
if (isset($_GET['cleared']) && $_GET['cleared'] === '1') {
    $auto_success = 'تم مسح الجدول بنجاح.';
}

if (isset($_GET['auto'])) {
    if ($_GET['auto'] === 'success') {
        $count = (int)($_GET['count'] ?? 0);
        $iter = (int)($_GET['iterations'] ?? 0);
        $conf = (int)($_GET['conflicts']  ?? 0);
        $auto_success = 'تم إنشاء الجدول تلقائياً بنجاح! تمت جدولة ' . $count . ' حصة بعد ' . $iter . ' محاولة — أفضل نتيجة: ' . $conf . ($conf === 0 ? ' تعارض (مثالي ✓)' : ' تعارض متبقٍّ');
    }
    if (isset($_GET['unassigned']) && !empty($_GET['unassigned'])) {
        $auto_error = 'لم يتم جدولة المواد التالية (لا توجد فترات متاحة): ' . htmlspecialchars($_GET['unassigned']);
    }
}

// Get selected term from GET parameter, default to first term from DB
$_all_terms_for_view = getTerms($pdo);
$_first_term_str = !empty($_all_terms_for_view) ? (string)$_all_terms_for_view[0]['term_number'] : '3';
$selected_term = isset($_GET['term']) ? $_GET['term'] : $_first_term_str;

// Build query with term filter
$query = "SELECT s.*, sb.subject_name, sb.term, t.name as teacher_name, t.title as teacher_title, r.name as room_name
          FROM schedules s 
          LEFT JOIN subjects sb ON s.subject_id = sb.id 
          LEFT JOIN teachers t ON s.teacher_id = t.id
          LEFT JOIN rooms r ON s.room_id = r.id";

if ($selected_term !== 'all') {
    $query .= " WHERE sb.term = :term";
}

$query .= " ORDER BY sb.term, s.time";

$stmt = $pdo->prepare($query);

if ($selected_term !== 'all') {
    $stmt->execute(['term' => $selected_term]);
} else {
    $stmt->execute();
}

$schedules = $stmt->fetchAll();

// Define predefined time slots from settings
$time_slots = buildTimeSlots(CLASSES_START_TIME, PERIODS_COUNT);

// Group schedules by term, day and time
$schedules_by_term_day_time = [];
$available_terms = [];
foreach ($schedules as $schedule) {
    $term = $schedule['term'];
    $day = $schedule['day_of_week'];
    $time_formatted = date('H:i', strtotime($schedule['time']));
    $schedules_by_term_day_time[$term][$day][$time_formatted][] = $schedule;
    if (!in_array($term, $available_terms)) {
        $available_terms[] = $term;
    }
}
sort($available_terms);

// All slots across all terms — used by JS for adjacent-term conflict detection
$conflict_data = $pdo->query(
    "SELECT s.id, COALESCE(sb.term,0) as term, s.day_of_week, LEFT(s.time,5) as time FROM schedules s LEFT JOIN subjects sb ON s.subject_id=sb.id"
)->fetchAll(PDO::FETCH_ASSOC);

$term_names = [];
foreach ($_all_terms_for_view as $_t) {
    $term_names[(string)$_t['term_number']] = $_t['name'];
}

// For single term view, also keep flat grouping
$schedules_by_day_time = [];
foreach ($schedules as $schedule) {
    $day = $schedule['day_of_week'];
    $time_formatted = date('H:i', strtotime($schedule['time']));
    $schedules_by_day_time[$day][$time_formatted][] = $schedule;
}

$days = ['السبت', 'الأحد','الإثنين', 'الثلاثاء', 'الإربعاء', 'الخميس'];

// Get unique teachers and assign colors dynamically
$unique_teachers = [];
foreach ($schedules as $schedule) {
    $dn = getTitleAbbr($schedule['teacher_title']) . $schedule['teacher_name'];
    if (!in_array($dn, $unique_teachers)) {
        $unique_teachers[] = $dn;
    }
}

$available_colors = ['blue', 'green', 'purple', 'orange', 'red', 'pink', 'indigo', 'yellow', 'teal', 'cyan'];

$teacher_colors = [];
foreach ($unique_teachers as $index => $t) {
    $color_index = $index % count($available_colors);
    $teacher_colors[$t] = $available_colors[$color_index];
}

function getTeacherColorClass($teacher_name, $teacher_colors) {
    $color = isset($teacher_colors[$teacher_name]) ? $teacher_colors[$teacher_name] : 'gray';
    return [
        'bg' => "bg-{$color}-50",
        'border' => "border-r-{$color}-500",
        'text' => "text-{$color}-900",
        'text_light' => "text-{$color}-700",
        'text_lighter' => "text-{$color}-600"
    ];
}

// ===== BEGIN: CONFLICT LIST COMPUTATION =====
// Detects subjects from adjacent terms (T and T+1) sharing the same day+time slot,
// excluding pairs that have a requires_subject_id relation (those overlaps are intentional).

$_conflict_rows = $pdo->query(
    "SELECT sb.id as subject_id, sb.subject_name, sb.requires_subject_id,
            COALESCE(sb.term, 0) as term, s.day_of_week, LEFT(s.time, 5) as slot_time
     FROM schedules s
     LEFT JOIN subjects sb ON s.subject_id = sb.id
     ORDER BY sb.term, s.day_of_week, s.time"
)->fetchAll(PDO::FETCH_ASSOC);

// Build a set of related subject-ID pairs (bidirectional) to exclude
$_related_pairs = [];
foreach ($_conflict_rows as $row) {
    if (!empty($row['requires_subject_id'])) {
        $a = (int)$row['subject_id'];
        $b = (int)$row['requires_subject_id'];
        $_related_pairs[min($a,$b) . '_' . max($a,$b)] = true;
    }
}

$_slot_map = [];
foreach ($_conflict_rows as $row) {
    $_slot_map[$row['day_of_week']][$row['slot_time']][(int)$row['term']][] = [
        'id'   => (int)$row['subject_id'],
        'name' => $row['subject_name'],
    ];
}

$adjacent_conflicts = [];
foreach ($_slot_map as $_cday => $_ctimes) {
    foreach ($_ctimes as $_ctime => $_terms_at_slot) {
        $term_keys = array_keys($_terms_at_slot);
        sort($term_keys);
        foreach ($term_keys as $_t) {
            if (isset($_terms_at_slot[$_t + 1])) {
                foreach ($_terms_at_slot[$_t] as $_s1) {
                    foreach ($_terms_at_slot[$_t + 1] as $_s2) {
                        $pair_key = min($_s1['id'], $_s2['id']) . '_' . max($_s1['id'], $_s2['id']);
                        if (isset($_related_pairs[$pair_key])) continue; // intentional overlap — skip
                        $adjacent_conflicts[] = [
                            'day'      => $_cday,
                            'time'     => $_ctime,
                            'term1'    => $_t,
                            'subject1' => $_s1['name'],
                            'term2'    => $_t + 1,
                            'subject2' => $_s2['name'],
                        ];
                    }
                }
            }
        }
    }
}
// ===== END: CONFLICT LIST COMPUTATION =====

// Build flat schedule data for Excel export
$excel_entries = [];
foreach ($schedules as $s) {
    $td = getTitleAbbr($s['teacher_title']) . $s['teacher_name'];
    $excel_entries[] = [
        'term'    => (int)$s['term'],
        'day'     => $s['day_of_week'],
        'time'    => date('H:i', strtotime($s['time'])),
        'subject' => $s['subject_name'],
        'teacher' => $td,
        'room'    => $s['room_name'],
        'color'   => $teacher_colors[$td] ?? 'gray',
    ];
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>الجدول العام - لوحة التحكم</title>
    <link rel="stylesheet" href="../assets/CSS/style.css">
    <script src="https://cdn.jsdelivr.net/npm/exceljs@4.4.0/dist/exceljs.min.js"></script>
    <link href="../assets/fonts/cairo.css" rel="stylesheet"/>
</head>
<body class="font-sans antialiased bg-gray-50">

<?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-h-0">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b border-gray-200 flex-shrink-0">
            <div class="px-6 py-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">الجدول العام</h1>
                    <p class="text-sm text-gray-600 mt-1"><?php echo $selected_term === 'all' ? 'جميع الفصول' : 'الفصل ' . htmlspecialchars($selected_term); ?></p>
                </div>
                <div class="flex items-center gap-3 no-print flex-wrap">
                    <form method="GET" class="flex items-center gap-3">
                        <select name="term" onchange="this.form.submit()" class="px-4 py-2 bg-white border border-gray-200 rounded-custom text-sm font-medium text-gray-700 hover:bg-gray-50 shadow-sm focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="all" <?php echo $selected_term === 'all' ? 'selected' : ''; ?>>جميع الفصول</option>
                            <?php foreach ($_all_terms_for_view as $_t): ?>
                            <option value="<?php echo (int)$_t['term_number']; ?>" <?php echo $selected_term === (string)$_t['term_number'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($_t['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <button type="button" onclick="exportToExcel()" class="px-4 py-2 bg-white border border-gray-200 rounded-custom text-sm font-medium text-gray-700 hover:bg-gray-50 shadow-sm flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        تصدير Excel
                    </button>

                    <?php if (isAdmin()): ?>
                    <button type="button" onclick="document.getElementById('autoGenModal').classList.remove('hidden')" class="px-4 py-2 bg-primary text-white rounded-custom text-sm font-medium hover:bg-primary/90 shadow-sm">
                        إنشاء جدول تلقائي
                    </button>

                    <button type="button" onclick="document.getElementById('clearModal').classList.remove('hidden')" class="px-4 py-2 bg-red-50 border border-red-200 rounded-custom text-sm font-medium text-red-700 hover:bg-red-100 shadow-sm">
                        مسح الجدول
                    </button>

                    <button type="button" id="editModeBtn" onclick="toggleEditMode()" class="no-print px-4 py-2 bg-amber-50 border border-amber-200 rounded-custom text-sm font-medium text-amber-700 hover:bg-amber-100 shadow-sm flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        <span id="editModeBtnText">تعديل</span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <!-- Content -->
        <main class="flex-1 overflow-y-auto">
        <div class="p-3 md:p-6">
            <?php if ($auto_success): ?>
                <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-custom">
                    <p class="text-sm text-green-800"><?php echo $auto_success; ?></p>
                </div>
            <?php endif; ?>
            <?php if ($auto_error): ?>
                <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-custom">
                    <p class="text-sm text-red-800"><?php echo $auto_error; ?></p>
                </div>
            <?php endif; ?>
            <div class="bg-white rounded-custom shadow border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto" id="scheduleZoomWrapper">
                    <table class="w-full text-right border-collapse min-w-[800px]">
                        <thead>
                            <tr class="bg-gray-50">
                                <?php if ($selected_term === 'all'): ?>
                                    <th class="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center w-[80px]">الفصل</th>
                                <?php endif; ?>
                                <th class="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center w-[120px]">الوقت</th>
                                <th class="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center">السبت</th>
                                <th class="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center">الأحد</th>
                                <th class="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center">الاثنين</th>
                                <th class="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center">الثلاثاء</th>
                                <th class="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center">الإربعاء</th>
                                <th class="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center">الخميس</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                        <?php if ($selected_term === 'all'): ?>
                            <?php foreach ($available_terms as $term): ?>
                                <?php $term_slot_count = count($time_slots); $first_slot = true; ?>
                                <?php foreach ($time_slots as $slot_time => $slot_label): ?>
                                    <?php $slot_key = date('H:i', strtotime($slot_time)); ?>
                                    <tr>
                                        <?php if ($first_slot): ?>
                                            <td class="bg-primary/5 p-2 text-center font-bold text-primary text-sm" rowspan="<?php echo $term_slot_count; ?>">
                                                <span class="term-label"><?php echo htmlspecialchars($term_names[$term] ?? 'الفصل ' . $term); ?></span>
                                            </td>
                                            <?php $first_slot = false; ?>
                                        <?php endif; ?>
                                        <td class="bg-gray-50/50 p-4 text-center">
                                            <div class="flex flex-col items-center justify-center text-xs text-gray-500 font-medium">
                                                <span><?php echo $slot_label; ?></span>
                                            </div>
                                        </td>
                                        <?php foreach ($days as $day): ?>
                                            <td class="day-col p-2 border-r border-gray-100" data-term="<?php echo $term; ?>" data-day="<?php echo htmlspecialchars($day); ?>" data-time="<?php echo $slot_key; ?>">
                                                <?php if (isset($schedules_by_term_day_time[$term][$day][$slot_key])): ?>
                                                    <?php foreach ($schedules_by_term_day_time[$term][$day][$slot_key] as $schedule): ?>
                                                        <?php $td = getTitleAbbr($schedule['teacher_title']) . $schedule['teacher_name']; ?>
                                                        <?php $colors = getTeacherColorClass($td, $teacher_colors); ?>
                                                        <div class="class-card <?php echo $colors['bg']; ?> border-r-4 <?php echo $colors['border']; ?> p-3 rounded flex flex-col justify-between relative"
                                                             data-id="<?php echo $schedule['id']; ?>"
                                                             data-term="<?php echo $schedule['term']; ?>"
                                                             data-day="<?php echo htmlspecialchars($schedule['day_of_week']); ?>"
                                                             data-time="<?php echo date('H:i', strtotime($schedule['time'])); ?>">
                                                            <div>
                                                                <p class="text-sm font-bold <?php echo $colors['text']; ?> truncate">
                                                                    <?php echo htmlspecialchars($schedule['subject_name']); ?>
                                                                </p>
                                                                <p class="text-xs <?php echo $colors['text_light']; ?> font-medium mt-1">
                                                                    <?php echo htmlspecialchars($td); ?>
                                                                </p>
                                                            </div>
                                                            <p class="text-xs <?php echo $colors['text_lighter']; ?> font-semibold">
                                                                <?php echo htmlspecialchars($schedule['room_name']); ?>
                                                            </p>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <div class="class-card bg-gray-100 border-r-4 border-gray-400 p-3 rounded flex items-center justify-center">
                                                        <p class="text-xs font-bold text-gray-500 uppercase italic"> </p>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php foreach ($time_slots as $slot_time => $slot_label): ?>
                                <?php $slot_key = date('H:i', strtotime($slot_time)); ?>
                                <tr>
                                    <td class="bg-gray-50/50 p-4 text-center">
                                        <div class="flex flex-col items-center justify-center text-xs text-gray-500 font-medium">
                                            <span><?php echo $slot_label; ?></span>
                                        </div>
                                    </td>
                                    <?php foreach ($days as $day): ?>
                                        <td class="day-col p-2 border-r border-gray-100" data-term="<?php echo htmlspecialchars($selected_term); ?>" data-day="<?php echo htmlspecialchars($day); ?>" data-time="<?php echo $slot_key; ?>">
                                            <?php if (isset($schedules_by_day_time[$day][$slot_key])): ?>
                                                <?php foreach ($schedules_by_day_time[$day][$slot_key] as $schedule): ?>
                                                    <?php $td = getTitleAbbr($schedule['teacher_title']) . $schedule['teacher_name']; ?>
                                                    <?php $colors = getTeacherColorClass($td, $teacher_colors); ?>
                                                    <div class="class-card <?php echo $colors['bg']; ?> border-r-4 <?php echo $colors['border']; ?> p-3 rounded flex flex-col justify-between relative"
                                                         data-id="<?php echo $schedule['id']; ?>"
                                                         data-term="<?php echo $schedule['term']; ?>"
                                                         data-day="<?php echo htmlspecialchars($schedule['day_of_week']); ?>"
                                                         data-time="<?php echo date('H:i', strtotime($schedule['time'])); ?>">
                                                        <div>
                                                            <p class="text-sm font-bold <?php echo $colors['text']; ?> truncate">
                                                                <?php echo htmlspecialchars($schedule['subject_name']); ?>
                                                            </p>
                                                            <p class="text-xs <?php echo $colors['text_light']; ?> font-medium mt-1">
                                                                <?php echo htmlspecialchars($td); ?>
                                                            </p>
                                                        </div>
                                                        <p class="text-xs <?php echo $colors['text_lighter']; ?> font-semibold">
                                                            <?php echo htmlspecialchars($schedule['room_name']); ?>
                                                        </p>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="class-card bg-gray-100 border-r-4 border-gray-400 p-3 rounded flex items-center justify-center">
                                                    <p class="text-xs font-bold text-gray-500 uppercase italic"> </p>
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
                <span id="zoomLevel" class="text-sm font-semibold text-gray-500 min-w-[52px] text-center">100%</span>
                <button type="button" onclick="zoomIn()" class="w-9 h-9 flex items-center justify-center bg-white border border-gray-200 rounded-full text-gray-600 hover:bg-gray-50 shadow-sm text-xl font-bold select-none">&#x2B;</button>
            </div>

            <!-- ===== BEGIN: ConflictList ===== -->
            <?php if (!empty($adjacent_conflicts)): ?>
            <div class="mt-6 bg-white rounded-custom shadow border border-red-200 overflow-hidden no-print">
                <div class="px-5 py-3 bg-red-50 border-b border-red-200 flex items-center gap-2">
                    <svg class="w-4 h-4 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                    <div>
                        <h2 class="text-sm font-semibold text-red-700">تعارضات بين الفصول المتجاورة &mdash; <?php echo count($adjacent_conflicts); ?> تعارض</h2>
                        <p class="text-xs text-red-400 mt-0.5">مواد مجدولة في نفس اليوم والوقت مع مواد من الفصل السابق مباشرةً</p>
                    </div>
                </div>
                <ul class="divide-y divide-red-100 text-sm">
                    <?php foreach ($adjacent_conflicts as $_c): ?>
                    <li class="flex flex-wrap items-center gap-x-3 gap-y-1 px-5 py-2.5">
                        <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($_c['subject1']); ?></span>
                        <span class="text-xs bg-primary/10 text-primary font-medium px-1.5 py-0.5 rounded">ف<?php echo $_c['term1']; ?></span>
                        <span class="text-gray-300 font-bold">&#x2194;</span>
                        <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($_c['subject2']); ?></span>
                        <span class="text-xs bg-primary/10 text-primary font-medium px-1.5 py-0.5 rounded">ف<?php echo $_c['term2']; ?></span>
                        <span class="mr-auto text-xs text-gray-400"><?php echo htmlspecialchars($_c['day']); ?> &bull; <?php echo $_c['time']; ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            <!-- ===== END: ConflictList ===== -->

        </div>
        </main>
    </div>
</div>

<!-- Drag Conflict Warning Modal -->
<div id="conflictModal" class="hidden fixed inset-0 bg-gray-600/50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-6 border w-[90%] max-w-md shadow-lg rounded-custom bg-white">
        <div class="text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-amber-100 mb-4">
                <svg class="h-6 w-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 mb-2">تحذير: تعارض محتمل</h3>
            <p id="conflictMsg" class="text-sm text-gray-600 mb-6"></p>
            <div class="flex gap-3">
                <button id="conflictConfirmBtn" type="button" class="flex-1 px-4 py-2 bg-amber-600 text-white rounded-custom hover:bg-amber-700 transition-colors font-medium">
                    وضعه على أي حال
                </button>
                <button type="button" onclick="cancelDrop()" class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-custom hover:bg-gray-300 transition-colors font-medium">
                    إلغاء
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Clear Schedule Confirmation Modal -->
<div id="clearModal" class="hidden fixed inset-0 bg-gray-600/50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-6 border w-[90%] max-w-md shadow-lg rounded-custom bg-white">
        <div class="text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 mb-2">مسح الجدول</h3>
            <p class="text-sm text-gray-600 mb-6">سيتم <strong class="text-red-600">حذف جميع الجداول الحالية</strong>. هل أنت متأكد؟</p>
            <div class="flex gap-3">
                <form method="POST" class="flex-1">
                    <button type="submit" name="clear_schedule" value="1" class="w-full px-4 py-2 bg-red-600 text-white rounded-custom hover:bg-red-700 transition-colors font-medium">
                        تأكيد المسح
                    </button>
                </form>
                <button type="button" onclick="document.getElementById('clearModal').classList.add('hidden')" class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-custom hover:bg-gray-300 transition-colors font-medium">
                    إلغاء
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Auto-Generate Confirmation Modal -->
<div id="autoGenModal" class="hidden fixed inset-0 bg-gray-600/50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-6 border w-[90%] max-w-md shadow-lg rounded-custom bg-white">
        <div class="text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 mb-4">
                <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 mb-2">إنشاء جدول تلقائي</h3>
            <p class="text-sm text-gray-600 mb-1">يعيد النظام توليد الجدول <strong>حتى 100 مرة</strong> ويحتفظ بأقل نتيجة تعارضات، ويتوقف تلقائياً عند عدم التحسن لـ 15 محاولة متتالية.</p>
            <p class="text-xs text-gray-500 mb-6">سيتم <strong class="text-red-600">حذف الجدول الحالي</strong> واستبداله بأفضل نتيجة. قد يستغرق حتى دقيقتين.</p>
            <div class="flex gap-3">
                <form method="POST" class="flex-1">
                    <button type="submit" name="auto_generate" value="1" class="w-full px-4 py-2 bg-primary text-white rounded-custom hover:bg-primary/90 transition-colors font-medium">
                        تأكيد الإنشاء
                    </button>
                </form>
                <button type="button" onclick="document.getElementById('autoGenModal').classList.add('hidden')" class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-custom hover:bg-gray-300 transition-colors font-medium">
                    إلغاء
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const scheduleData   = <?php echo json_encode($excel_entries, JSON_UNESCAPED_UNICODE); ?>;
const timeSlotKeys   = <?php echo json_encode(array_keys($time_slots), JSON_UNESCAPED_UNICODE); ?>;
const timeSlotLabels = <?php echo json_encode(array_values($time_slots), JSON_UNESCAPED_UNICODE); ?>;
const days           = <?php echo json_encode($days, JSON_UNESCAPED_UNICODE); ?>;
const selectedTerm   = <?php echo json_encode($selected_term); ?>;
const availableTerms = <?php echo json_encode($available_terms); ?>;
const termNames      = <?php echo json_encode($term_names, JSON_UNESCAPED_UNICODE); ?>;
const conflictData   = <?php echo json_encode($conflict_data, JSON_UNESCAPED_UNICODE); ?>;
const termNumbers    = <?php echo json_encode(array_map('intval', getTermNumbers($pdo))); ?>;
const minTerm        = Math.min(...(termNumbers.length ? termNumbers : [3]));
const maxTerm        = Math.max(...(termNumbers.length ? termNumbers : [8]));
const academicYear   = <?php
    $_ay = $pdo->query("SELECT `value` FROM `settings` WHERE `key`='academic_year'")->fetchColumn();
    echo json_encode($_ay ?: '', JSON_UNESCAPED_UNICODE);
?>;
</script>
<script src="../assets/JS/admin-common.js"></script>
<script src="../assets/JS/view-schedule.js?v=<?php echo filemtime('../assets/JS/view-schedule.js'); ?>"></script>
</body>
</html>
