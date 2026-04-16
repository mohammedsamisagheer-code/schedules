<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

checkAuth('admin');
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$current_user = getCurrentUser();
$id       = intval($_POST['id']      ?? 0);
$new_day  = trim($_POST['new_day']   ?? '');
$new_time = trim($_POST['new_time']  ?? '');
$swap_id  = intval($_POST['swap_id'] ?? 0);

if (!$id || !$new_day || !$new_time) {
    echo json_encode(['ok' => false, 'error' => 'Missing parameters']);
    exit;
}

$stmt = $pdo->prepare("SELECT s.*, sb.subject_name FROM schedules s LEFT JOIN subjects sb ON s.subject_id = sb.id WHERE s.id = ?");
$stmt->execute([$id]);
$orig = $stmt->fetch();

if (!$orig) {
    echo json_encode(['ok' => false, 'error' => 'Schedule not found']);
    exit;
}

// ── Teacher conflict pre-check (mirrors auto-scheduler teacher_slots logic) ───
// Check: does orig teacher already have another class at the target slot?
$tc = $pdo->prepare("SELECT COUNT(*) FROM schedules WHERE teacher_id=? AND day_of_week=? AND LEFT(time,5)=LEFT(?,5) AND id!=? AND id!=?");
$tc->execute([$orig['teacher_id'], $new_day, $new_time, $id, $swap_id ?: 0]);
if ($tc->fetchColumn() > 0) {
    echo json_encode(['ok' => false, 'error' => 'teacher_conflict']);
    exit;
}

$other = null;
if ($swap_id) {
    $stmt2 = $pdo->prepare("SELECT s.*, sb.subject_name FROM schedules s LEFT JOIN subjects sb ON s.subject_id = sb.id WHERE s.id = ?");
    $stmt2->execute([$swap_id]);
    $other = $stmt2->fetch();
    if (!$other) {
        echo json_encode(['ok' => false, 'error' => 'Swap target not found']);
        exit;
    }
    // Check: does swap teacher already have another class at orig slot?
    $tc2 = $pdo->prepare("SELECT COUNT(*) FROM schedules WHERE teacher_id=? AND day_of_week=? AND LEFT(time,5)=LEFT(?,5) AND id!=? AND id!=?");
    $tc2->execute([$other['teacher_id'], $orig['day_of_week'], $orig['time'], $swap_id, $id]);
    if ($tc2->fetchColumn() > 0) {
        echo json_encode(['ok' => false, 'error' => 'teacher_conflict']);
        exit;
    }
}
// ─────────────────────────────────────────────────────────────────────────────

$pdo->beginTransaction();
try {
    if ($swap_id) {
        if (!$other) throw new Exception('Swap target not found');

        $pdo->prepare("UPDATE schedules SET day_of_week=?, time=? WHERE id=?")->execute([$new_day, $new_time . ':00', $id]);
        $pdo->prepare("UPDATE schedules SET day_of_week=?, time=? WHERE id=?")->execute([$orig['day_of_week'], $orig['time'], $swap_id]);

        logActivity($pdo, 'تبديل مكان: "' . $orig['subject_name'] . '" مع "' . $other['subject_name'] . '"', $current_user['name'] ?? '');
    } else {
        $pdo->prepare("UPDATE schedules SET day_of_week=?, time=? WHERE id=?")->execute([$new_day, $new_time . ':00', $id]);
        logActivity($pdo, 'نقل "' . $orig['subject_name'] . '" إلى ' . $new_day . ' الساعة ' . $new_time, $current_user['name'] ?? '');
    }
    $pdo->commit();
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
