<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

checkAuth('admin');

$current_user = getCurrentUser();

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

// Handle auto-generate schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auto_generate'])) {
    if (!isAdmin()) { header('Location: view_schedule.php'); exit; }
    $days_list = ['السبت', 'الأحد', 'الإثنين', 'الثلاثاء', 'الإربعاء', 'الخميس'];
    $_gen_settings = getSettings($pdo);
    $max_teaching_days = (int)($_gen_settings['max_teaching_days'] ?? 4);
    $times_list = array_keys(buildTimeSlots(
        $_gen_settings['classes_start_time'] ?? CLASSES_START_TIME,
        (int)($_gen_settings['periods_count'] ?? PERIODS_COUNT)
    ));

    // Fetch all subjects with their teachers
    $all_subjects = $pdo->query("SELECT s.*, t.name as teacher_name, t.title as teacher_title FROM subjects s LEFT JOIN teachers t ON s.teacher_id = t.id ORDER BY s.term, s.id")->fetchAll();
    
    // Fetch all rooms
    $all_rooms = $pdo->query("SELECT * FROM rooms ORDER BY id")->fetchAll();

    if (empty($all_subjects)) {
        $auto_error = 'لا توجد مواد لإنشاء الجدول';
    } elseif (empty($all_rooms)) {
        $auto_error = 'لا توجد قاعات لإنشاء الجدول';
    } else {
        // Track occupied slots
        $teacher_slots = [];  // teacher_slots[teacher_id][day][time] = true
        $room_slots = [];     // room_slots[room_id][day][time] = true
        $term_slots = [];     // term_slots[term][day][time] = true
        $subject_slots = [];  // subject_slots[subject_id][day][time] = true
        $teacher_days = [];   // teacher_days[teacher_id] = [day_index, ...] (teaching days)
        $room_usage = [];     // room_usage[room_id] = total assignments (for equal distribution)
        
        $assignments = [];
        $unassigned = [];

        // Delete all existing schedules
        $pdo->exec("DELETE FROM schedules");

        // Day index map for adjacency check
        $day_index = array_flip($days_list);

        // Helper: check if adding a day would create 3 consecutive days for a teacher
        function wouldCreate3Consecutive($existing_days, $new_day) {
            $days_set = $existing_days;
            $days_set[] = $new_day;
            sort($days_set);
            $days_set = array_unique($days_set);
            for ($i = 0; $i < count($days_set) - 2; $i++) {
                if ($days_set[$i+1] == $days_set[$i] + 1 && $days_set[$i+2] == $days_set[$i] + 2) {
                    return true;
                }
            }
            return false;
        }

        // Build bidirectional preferred-overlap map from requires_subject_id
        // Students won't take both, so we PREFER placing them at the same day+time
        $preferred = []; // preferred[subject_id] = [related_subject_id, ...]
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

        // Pre-compute all valid non-adjacent day pairs (index diff > 1)
        $day_pairs = [];
        for ($a = 0; $a < count($days_list); $a++) {
            for ($b = $a + 1; $b < count($days_list); $b++) {
                if (abs($a - $b) > 1) {
                    $day_pairs[] = [$a, $b];
                }
            }
        }

        // Helper: try to place a subject on a specific day in any available time slot
        // Prefers time slots where its required subject is already scheduled (students won't take both)
        $tryPlaceOnDay = function($subject, $teacher_id, $day, $di)
            use (&$teacher_slots, &$room_slots, &$term_slots, &$subject_slots, &$teacher_days, &$assignments, &$preferred, &$room_usage, $all_rooms, $times_list, $max_teaching_days) {

            $t_days = isset($teacher_days[$teacher_id]) ? $teacher_days[$teacher_id] : [];

            // Teacher must not teach more than $max_teaching_days days per week
            if (!in_array($di, $t_days) && count($t_days) >= $max_teaching_days) return false;

            $subject_id = $subject['id'];
            $subject_term = (int)$subject['term'];

            // A subject can't be taught twice on the same day
            if (!empty($subject_slots[$subject_id][$day])) return false;

            $pref_ids = isset($preferred[$subject_id]) ? $preferred[$subject_id] : [];

            // 3-tier time slot priority:
            // T1: required subject already here (preferred overlap)
            // T2: previous term has no class at this day+time (fill gaps)
            // T3: all other slots
            $prev_term = $subject_term - 1;
            $t1 = []; $t2 = []; $t3 = [];
            foreach ($times_list as $time) {
                $is_preferred = false;
                foreach ($pref_ids as $pid) {
                    if (isset($subject_slots[$pid][$day][$time])) { $is_preferred = true; break; }
                }
                if ($is_preferred) {
                    $t1[] = $time;
                } elseif ($prev_term >= 3 && empty($term_slots[$prev_term][$day][$time])) {
                    $t2[] = $time;
                } else {
                    $t3[] = $time;
                }
            }

            $sorted_times = array_merge($t1, $t2, $t3);

            foreach ($sorted_times as $time) {
                if (isset($teacher_slots[$teacher_id][$day][$time])) continue;

                // Rule 1: no two subjects from same term at same day+time
                if (isset($term_slots[$subject_term][$day][$time])) continue;

                // Pick least-used available room for equal distribution
                $found_room = null;
                $sorted_rooms = $all_rooms;
                usort($sorted_rooms, function($a, $b) use ($room_usage) {
                    return ($room_usage[$a['id']] ?? 0) - ($room_usage[$b['id']] ?? 0);
                });
                foreach ($sorted_rooms as $room) {
                    if (!isset($room_slots[$room['id']][$day][$time])) {
                        $found_room = $room;
                        break;
                    }
                }
                if ($found_room === null) continue;

                // Assign!
                $teacher_slots[$teacher_id][$day][$time] = true;
                $room_slots[$found_room['id']][$day][$time] = true;
                $room_usage[$found_room['id']] = ($room_usage[$found_room['id']] ?? 0) + 1;
                $term_slots[$subject_term][$day][$time] = true;
                $subject_slots[$subject_id][$day][$time] = true;

                if (!isset($teacher_days[$teacher_id])) $teacher_days[$teacher_id] = [];
                if (!in_array($di, $teacher_days[$teacher_id])) $teacher_days[$teacher_id][] = $di;

                $assignments[] = [
                    'subject_id' => $subject_id,
                    'teacher_id' => $teacher_id,
                    'room_id' => $found_room['id'],
                    'day_of_week' => $day,
                    'time' => $time
                ];
                return true;
            }
            return false;
        };

        // Helper: sort days — prefer days that are empty in the previous term
        // Rule: if term T-1 has a day off, term T should fill that day
        $sortDaysByTermGap = function($days, $term) use (&$term_slots) {
            $prev = $term - 1;
            if ($prev < 3) return $days;
            usort($days, function($a, $b) use (&$term_slots, $prev) {
                $ae = empty($term_slots[$prev][$a]) ? 1 : 0;
                $be = empty($term_slots[$prev][$b]) ? 1 : 0;
                return $be - $ae;
            });
            return $days;
        };

        // Helper: sort day-pairs — prefer pairs whose days are empty in previous term
        $sortPairsByTermGap = function($pairs, $term) use ($days_list, &$term_slots) {
            $prev = $term - 1;
            if ($prev < 3) return $pairs;
            $empty = [];
            foreach ($days_list as $i => $d) {
                if (empty($term_slots[$prev][$d])) $empty[$i] = true;
            }
            if (empty($empty)) return $pairs;
            usort($pairs, function($a, $b) use ($empty) {
                $as = (isset($empty[$a[0]]) ? 1 : 0) + (isset($empty[$a[1]]) ? 1 : 0);
                $bs = (isset($empty[$b[0]]) ? 1 : 0) + (isset($empty[$b[1]]) ? 1 : 0);
                return $bs - $as;
            });
            return $pairs;
        };

        // Sort subjects: teachers with the most subjects go first (most-constrained first)
        // Prevents heavily-loaded teachers from being locked out by earlier placements
        $teacher_load = [];
        foreach ($all_subjects as $s) {
            $tid = $s['teacher_id'];
            $teacher_load[$tid] = ($teacher_load[$tid] ?? 0) + 1;
        }
        $shuffled_subjects = $all_subjects;
        shuffle($shuffled_subjects);
        usort($shuffled_subjects, function($a, $b) use ($teacher_load) {
            $load_diff = ($teacher_load[$b['teacher_id']] ?? 0) - ($teacher_load[$a['teacher_id']] ?? 0);
            if ($load_diff !== 0) return $load_diff;
            return $a['term'] - $b['term'];
        });

        foreach ($shuffled_subjects as $subject) {
            $teacher_id = $subject['teacher_id'];
            $classes_needed = isset($subject['priority']) ? (int)$subject['priority'] : 2;
            $assigned_count = 0;

            if ($classes_needed == 2) {
                // Pass 0: overlap with required/equivalent subjects already placed
                $pref_ids = $preferred[$subject['id']] ?? [];
                if (!empty($pref_ids)) {
                    foreach ($pref_ids as $pid) {
                        if ($assigned_count >= $classes_needed) break;
                        if (isset($subject_slots[$pid])) {
                            foreach (array_keys($subject_slots[$pid]) as $pday) {
                                if ($assigned_count >= $classes_needed) break;
                                $pdi = $day_index[$pday];
                                if ($tryPlaceOnDay($subject, $teacher_id, $pday, $pdi)) {
                                    $assigned_count++;
                                }
                            }
                        }
                    }
                }

                // Pass 1: non-adjacent day pairs (only if pass 0 placed nothing)
                if ($assigned_count == 0) {
                $pairs = $day_pairs;
                shuffle($pairs);
                $pairs = $sortPairsByTermGap($pairs, (int)$subject['term']);

                foreach ($pairs as $pair) {
                    if ($assigned_count >= 2) break;

                    $di1 = $pair[0];
                    $di2 = $pair[1];
                    $day1 = $days_list[$di1];
                    $day2 = $days_list[$di2];

                    $ok1 = $tryPlaceOnDay($subject, $teacher_id, $day1, $di1);
                    if (!$ok1) continue;

                    $ok2 = $tryPlaceOnDay($subject, $teacher_id, $day2, $di2);
                    if ($ok2) {
                        $assigned_count = 2;
                    } else {
                        // Undo first assignment if second fails
                        $last = array_pop($assignments);
                        unset($teacher_slots[$teacher_id][$last['day_of_week']][$last['time']]);
                        unset($room_slots[$last['room_id']][$last['day_of_week']][$last['time']]);
                        unset($term_slots[(int)$subject['term']][$last['day_of_week']][$last['time']]);
                        unset($subject_slots[$subject['id']][$last['day_of_week']][$last['time']]);
                        if (!isset($teacher_slots[$teacher_id][$last['day_of_week']]) || empty($teacher_slots[$teacher_id][$last['day_of_week']])) {
                            if (isset($teacher_days[$teacher_id])) {
                                $teacher_days[$teacher_id] = array_values(array_diff($teacher_days[$teacher_id], [$di1]));
                            }
                        }
                    }
                }
                } // end pass 1

                // Pass 2: try individually on non-adjacent days
                if ($assigned_count < 2) {
                    $shuffled_days = $days_list;
                    shuffle($shuffled_days);
                    $shuffled_days = $sortDaysByTermGap($shuffled_days, (int)$subject['term']);
                    // Seed with days already placed in pass 0
                    $placed_days = [];
                    if (isset($subject_slots[$subject['id']])) {
                        foreach (array_keys($subject_slots[$subject['id']]) as $pd) {
                            $placed_days[] = $day_index[$pd];
                        }
                    }

                    foreach ($shuffled_days as $day) {
                        if ($assigned_count >= 2) break;
                        $di = $day_index[$day];

                        $too_close = false;
                        foreach ($placed_days as $prev_di) {
                            if (abs($di - $prev_di) <= 1) { $too_close = true; break; }
                        }
                        if ($too_close) continue;

                        if ($tryPlaceOnDay($subject, $teacher_id, $day, $di)) {
                            $placed_days[] = $di;
                            $assigned_count++;
                        }
                    }
                }

                // Pass 3: try ANY day (drop non-adjacent constraint)
                if ($assigned_count < 2) {
                    $shuffled_days = $days_list;
                    shuffle($shuffled_days);
                    $shuffled_days = $sortDaysByTermGap($shuffled_days, (int)$subject['term']);
                    foreach ($shuffled_days as $day) {
                        if ($assigned_count >= 2) break;
                        $di = $day_index[$day];
                        if ($tryPlaceOnDay($subject, $teacher_id, $day, $di)) {
                            $assigned_count++;
                        }
                    }
                }
            } else {
                // Pass 0: try overlap with required subjects first
                $pref_ids = $preferred[$subject['id']] ?? [];
                if (!empty($pref_ids)) {
                    foreach ($pref_ids as $pid) {
                        if ($assigned_count >= 1) break;
                        if (isset($subject_slots[$pid])) {
                            foreach (array_keys($subject_slots[$pid]) as $pday) {
                                if ($assigned_count >= 1) break;
                                $pdi = $day_index[$pday];
                                if ($tryPlaceOnDay($subject, $teacher_id, $pday, $pdi)) {
                                    $assigned_count = 1;
                                }
                            }
                        }
                    }
                }
                // Fallback: place on any available day
                if ($assigned_count < 1) {
                    $shuffled_days = $days_list;
                    shuffle($shuffled_days);
                    $shuffled_days = $sortDaysByTermGap($shuffled_days, (int)$subject['term']);
                    foreach ($shuffled_days as $day) {
                        $di = $day_index[$day];
                        if ($tryPlaceOnDay($subject, $teacher_id, $day, $di)) {
                            $assigned_count = 1;
                            break;
                        }
                    }
                }
            }

            if ($assigned_count < $classes_needed) {
                $unassigned[] = $subject['subject_name'] . ' (ف' . $subject['term'] . ') - ' . $assigned_count . '/' . $classes_needed;
            }
        }

        // Insert all assignments
        $insert_stmt = $pdo->prepare("INSERT INTO schedules (subject_id, teacher_id, room_id, day_of_week, time) VALUES (?, ?, ?, ?, ?)");
        foreach ($assignments as $a) {
            $insert_stmt->execute([$a['subject_id'], $a['teacher_id'], $a['room_id'], $a['day_of_week'], $a['time']]);
        }

        $count = count($assignments);
        $unassigned_param = !empty($unassigned) ? '&unassigned=' . urlencode(implode('، ', $unassigned)) : '';
        logActivity($pdo, 'أنشأ الجدول الدراسي تلقائياً (' . $count . ' حصة)', $current_user['name'] ?? '');
        header('Location: view_schedule.php?term=all&auto=success&count=' . $count . $unassigned_param);
        exit;
    }
}

// Check for auto-generate result from redirect
if (isset($_GET['cleared']) && $_GET['cleared'] === '1') {
    $auto_success = 'تم مسح الجدول بنجاح.';
}

if (isset($_GET['auto']) && $_GET['auto'] === 'success') {
    $auto_success = 'تم إنشاء الجدول تلقائياً بنجاح! تم جدولة ' . intval($_GET['count'] ?? 0) . ' مادة.';
    if (isset($_GET['unassigned']) && !empty($_GET['unassigned'])) {
        $auto_error = 'لم يتم جدولة المواد التالية (لا توجد فترات متاحة): ' . htmlspecialchars($_GET['unassigned']);
    }
}

// Get selected term from GET parameter, default to '3'
$selected_term = isset($_GET['term']) ? $_GET['term'] : '3';

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

$term_names = [
    '3' => 'الفصل الثالث',
    '4' => 'الفصل الرابع',
    '5' => 'الفصل الخامس',
    '6' => 'الفصل السادس',
    '7' => 'الفصل السابع',
    '8' => 'الفصل الثامن',
];

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

<!-- Mobile Top Bar -->
<div class="md:hidden bg-white shadow-sm border-b border-gray-200 px-4 py-3 flex items-center justify-between sticky top-0 z-40 no-print">
    <div class="flex items-center gap-3">
        <img src="../assets/images/logo.png" alt="logo" class="w-10 h-10 object-contain">
        <span class="font-bold text-lg tracking-tight">لوحة التحكم</span>
    </div>
    <button onclick="toggleSidebar()" class="p-2 rounded-custom hover:bg-gray-100">
        <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
    </button>
</div>

<!-- Sidebar Overlay (mobile) -->
<div id="sidebarOverlay" onclick="toggleSidebar()" class="hidden fixed inset-0 bg-black/50 z-40 md:hidden"></div>

<div class="flex h-screen md:h-screen">
    <!-- Sidebar -->
    <aside id="sidebar" class="fixed md:static md:flex-none inset-y-0 right-0 z-50 w-64 bg-white shadow-lg md:translate-x-0 overflow-y-auto">
        <div class="p-6">
            <div class="flex items-center gap-3">
                <img src="../assets/images/logo.png" alt="logo" class="w-10 h-10 object-contain">
                <span class="font-bold text-xl tracking-tight">لوحة التحكم</span>
            </div>
        </div>
        
        <!-- Admin Info -->
        <div class="px-6 pb-4 border-b border-gray-200">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                </div>
                <div>
                    <p class="font-medium text-gray-900"><?php echo getTitleAbbr($current_user['title']) . htmlspecialchars($current_user['name']); ?></p>
                    <p class="text-sm text-gray-500"><?php echo isAdmin() ? 'مدير النظام' : 'مستخدم'; ?></p>
                </div>
            </div>
        </div>
        
        <nav class="px-4 pb-6 pt-4">
            <ul class="space-y-2">
                <?php if (isAdmin()): ?>
                <li>
                    <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-custom">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                        </svg>
                        الرئيسية
                    </a>
                </li>
                <li>
                    <a href="subjects.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-custom">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                        المواد الدراسية
                    </a>
                </li>
                <li>
                    <a href="teachers.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-custom">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        المدرسين
                    </a>
                </li>
                <li>
                    <a href="rooms.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-custom">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                        القاعات
                    </a>
                </li>
                <li>
                    <a href="my_schedule.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-custom">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        جدولي
                    </a>
                </li>
                <?php endif; ?>
                <li>
                    <a href="view_schedule.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium bg-primary/10 text-primary rounded-custom">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        عرض الجدول العام
                    </a>
                </li>
                <li>
                    <a href="exam_schedule.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-custom">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        جدول الإمتحانات
                    </a>
                </li>
                <?php if (isAdmin()): ?>
                <li>
                    <a href="users.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-custom">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        إدارة المستخدمين
                    </a>
                </li>
                <li>
                    <a href="settings.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-custom">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                        إعدادات النظام
                    </a>
                </li>
                <?php endif; ?>
                <li>
                    <a href="account.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-custom">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        حسابي
                    </a>
                </li>
                <li>
                    <a href="../logout.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-red-600 hover:bg-red-50 rounded-custom">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        تسجيل الخروج
                    </a>
                </li>
            </ul>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 overflow-y-auto pt-0">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="px-6 py-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">الجدول العام</h1>
                    <p class="text-sm text-gray-600 mt-1"><?php echo $selected_term === 'all' ? 'جميع الفصول' : 'الفصل ' . htmlspecialchars($selected_term); ?></p>
                </div>
                <div class="flex items-center gap-3 no-print flex-wrap">
                    <form method="GET" class="flex items-center gap-3">
                        <select name="term" onchange="this.form.submit()" class="px-4 py-2 bg-white border border-gray-200 rounded-custom text-sm font-medium text-gray-700 hover:bg-gray-50 shadow-sm focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="all" <?php echo $selected_term === 'all' ? 'selected' : ''; ?>>جميع الفصول</option>
                            <option value="3" <?php echo $selected_term === '3' ? 'selected' : ''; ?>>الفصل الثالث</option>
                            <option value="4" <?php echo $selected_term === '4' ? 'selected' : ''; ?>>الفصل الرابع</option>
                            <option value="5" <?php echo $selected_term === '5' ? 'selected' : ''; ?>>الفصل الخامس</option>
                            <option value="6" <?php echo $selected_term === '6' ? 'selected' : ''; ?>>الفصل السادس</option>
                            <option value="7" <?php echo $selected_term === '7' ? 'selected' : ''; ?>>الفصل السابع</option>
                            <option value="8" <?php echo $selected_term === '8' ? 'selected' : ''; ?>>الفصل الثامن</option>
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
            <p class="text-sm text-gray-600 mb-1">سيتم <strong class="text-red-600">حذف جميع الجداول الحالية</strong> وإنشاء جدول جديد تلقائياً.</p>
            <p class="text-xs text-gray-500 mb-6">القواعد: لا تعارض مدرس، لا تعارض قاعة، لا تعارض فصول مزدوجة (3↔4، 5↔6، 7↔8)</p>
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
const academicYear   = <?php
    $_ay = $pdo->query("SELECT `value` FROM `settings` WHERE `key`='academic_year'")->fetchColumn();
    echo json_encode($_ay ?: '', JSON_UNESCAPED_UNICODE);
?>;
</script>
<script src="../assets/JS/admin-common.js"></script>
<script src="../assets/JS/view-schedule.js?v=<?php echo filemtime('../assets/JS/view-schedule.js'); ?>"></script>
</body>
</html>
