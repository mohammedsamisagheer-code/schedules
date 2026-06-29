<?php
/**
 * Test-only standalone scheduler replicating _generateOnce and
 * _countConflictsInMemory from Admin/view_schedule.php.
 *
 * All original constraint logic is preserved; only the closure
 * structure has been flattened to a class for testability.
 */
class SchedulerHelper {

    /**
     * Run one scheduling pass.
     *
     * @param array<int,array> $all_subjects  Subject rows (id, term, teacher_id, priority, requires_subject_id)
     * @param array<int,array> $all_rooms     Room rows (id, name)
     * @param string[]         $days_list     Day-of-week string list
     * @param string[]         $times_list    Time keys H:i:s
     * @param int              $max_days      Max teaching days / week
     * @return array  [assignments[][], unassigned[]]
     */
    public static function generateOnce(
        array $all_subjects,
        array $all_rooms,
        array $days_list,
        array $times_list,
        int $max_days = 4,
        array $all_term_numbers = []
    ): array {
        $teacher_slots = [];
        $room_slots    = [];
        $term_slots    = [];
        $subject_slots = [];
        $teacher_days  = [];
        $room_usage    = [];
        $assignments   = [];
        $unassigned    = [];
        $day_index     = array_flip($days_list);

        // Build co-schedule preference map from requires_subject_id
        $preferred = [];
        foreach ($all_subjects as $s) {
            $sid = (int)$s['id'];
            if (!isset($preferred[$sid])) $preferred[$sid] = [];
            if (!empty($s['requires_subject_id'])) {
                $rid = (int)$s['requires_subject_id'];
                if (!in_array($rid, $preferred[$sid])) $preferred[$sid][] = $rid;
                if (!isset($preferred[$rid])) $preferred[$rid] = [];
                if (!in_array($sid, $preferred[$rid])) $preferred[$rid][] = $sid;
            }
        }

        // Non-adjacent day pairs
        $day_pairs = [];
        foreach ($days_list as $a => $da) {
            foreach ($days_list as $b => $db) {
                if ($b > $a && abs($a - $b) > 1) {
                    $day_pairs[] = [$a, $b];
                }
            }
        }

        // Sort pairs by term-gap fill (soft)
        $sortPairs = function (array $pairs, int $term) use ($days_list, &$term_slots, $all_term_numbers) {
            $prev = $term - 1;
            if (!in_array($prev, $all_term_numbers)) return $pairs;
            $empty = [];
            foreach ($days_list as $i => $d) {
                if (empty($term_slots[$prev][$d])) $empty[$i] = true;
            }
            if (empty($empty)) return $pairs;
            usort($pairs, function ($a, $b) use ($empty) {
                $sa = (isset($empty[$a[0]]) ? 1 : 0) + (isset($empty[$a[1]]) ? 1 : 0);
                $sb = (isset($empty[$b[0]]) ? 1 : 0) + (isset($empty[$b[1]]) ? 1 : 0);
                return $sb - $sa;
            });
            return $pairs;
        };

        // Sort single days: prefer days where prev term has free space
        $sortDays = function (array $days, int $term) use ($day_index, &$term_slots, $all_term_numbers) {
            $prev = $term - 1;
            if (!in_array($prev, $all_term_numbers)) return $days;
            usort($days, function ($a, $b) use ($term_slots, $prev) {
                $fa = empty($term_slots[$prev][$a]) ? 0 : 1;
                $fb = empty($term_slots[$prev][$b]) ? 0 : 1;
                return $fa - $fb;
            });
            return $days;
        };

        // Helper: try to place a subject on a given day+time
        $tryPlace = function (
            array $subject, int $teacher_id, string $day, int $di
        ) use (
            &$teacher_slots, &$room_slots, &$term_slots, &$subject_slots,
            &$teacher_days, &$assignments, &$preferred, &$room_usage,
            $all_rooms, $times_list, $max_days, $all_term_numbers
        ): bool {
            $t_days = $teacher_days[$teacher_id] ?? [];
            if (!in_array($di, $t_days) && count($t_days) >= $max_days) {
                return false;
            }
            $sid  = (int)$subject['id'];
            $st   = (int)$subject['term'];
            if (!empty($subject_slots[$sid][$day])) return false;

            $pref_ids   = $preferred[$sid] ?? [];
            $prev       = $st - 1;
            $t1 = []; $t2 = []; $t3 = [];

            foreach ($times_list as $time) {
                $is_pref = false;
                foreach ($pref_ids as $pid) {
                    if (isset($subject_slots[$pid][$day][$time])) {
                        $is_pref = true;
                        break;
                    }
                }
                if ($is_pref) {
                    $t1[] = $time;
                } elseif (in_array($prev, $all_term_numbers) && empty($term_slots[$prev][$day][$time])) {
                    $t2[] = $time;
                } else {
                    $t3[] = $time;
                }
            }

            // Deterministic room ordering by usage count
            $sorted_rooms = $all_rooms;
            usort($sorted_rooms, function ($a, $b) use ($room_usage) {
                return ($room_usage[$a['id']] ?? 0) - ($room_usage[$b['id']] ?? 0);
            });

            foreach (array_merge($t1, $t2, $t3) as $time) {
                if (isset($teacher_slots[$teacher_id][$day][$time])) continue;
                if (isset($term_slots[$st][$day][$time])) continue;
                $free_room = null;
                foreach ($sorted_rooms as $room) {
                    if (!isset($room_slots[$room['id']][$day][$time])) {
                        $free_room = $room;
                        break;
                    }
                }
                if ($free_room === null) continue;

                $teacher_slots[$teacher_id][$day][$time] = true;
                $room_slots[$free_room['id']][$day][$time] = true;
                $room_usage[$free_room['id']] = ($room_usage[$free_room['id']] ?? 0) + 1;
                $term_slots[$st][$day][$time] = true;
                $subject_slots[$sid][$day][$time] = true;
                if (!isset($teacher_days[$teacher_id])) $teacher_days[$teacher_id] = [];
                if (!in_array($di, $teacher_days[$teacher_id])) {
                    $teacher_days[$teacher_id][] = $di;
                }
                $assignments[] = [
                    'subject_id'  => $sid,
                    'teacher_id'  => $teacher_id,
                    'room_id'     => $free_room['id'],
                    'day_of_week' => $day,
                    'time'        => $time,
                ];
                return true;
            }
            return false;
        };

        // Load-balance order: teachers with more subjects go first
        $tload = [];
        foreach ($all_subjects as $s) {
            $tid = (int)$s['teacher_id'];
            $tload[$tid] = ($tload[$tid] ?? 0) + 1;
        }
        $shuffled = $all_subjects;
        shuffle($shuffled);
        usort($shuffled, function ($a, $b) use ($tload) {
            $ta = $tload[$a['teacher_id']] ?? 0;
            $tb = $tload[$b['teacher_id']] ?? 0;
            $d  = $tb - $ta;
            return $d !== 0 ? $d : ((int)$a['term'] - (int)$b['term']);
        });

        // Main assignment loop
        foreach ($shuffled as $subject) {
            $teacher_id = (int)$subject['teacher_id'];
            $needed     = isset($subject['priority']) ? (int)$subject['priority'] : 2;
            $cnt        = 0;
            $sid        = (int)$subject['id'];

            if ($needed === 2) {
                // Priority 2: attempt co-schedule first, then day pairs, then singles
                $pref_ids = $preferred[$sid] ?? [];

                // Phase 1: co-schedule with preferred subjects
                if (!empty($pref_ids)) {
                    foreach ($pref_ids as $pid) {
                        if ($cnt >= 2) break;
                        if (isset($subject_slots[$pid])) {
                            foreach (array_keys($subject_slots[$pid]) as $pday) {
                                if ($cnt >= 2) break;
                                if ($tryPlace($subject, $teacher_id, $pday, $day_index[$pday])) $cnt++;
                            }
                        }
                    }
                }

                // Phase 2: non-adjacent day pairs
                if ($cnt === 0) {
                    $pairs = $day_pairs;
                    shuffle($pairs);
                    $pairs = $sortPairs($pairs, (int)$subject['term']);
                    foreach ($pairs as $pair) {
                        if ($cnt >= 2) break;
                        $d1 = $days_list[$pair[0]];
                        $d2 = $days_list[$pair[1]];
                        if (!$tryPlace($subject, $teacher_id, $d1, $pair[0])) continue;
                        if ($tryPlace($subject, $teacher_id, $d2, $pair[1])) {
                            $cnt = 2;
                        } else {
                            // Rollback first placement
                            $last = array_pop($assignments);
                            unset($teacher_slots[$teacher_id][$last['day_of_week']][$last['time']]);
                            unset($room_slots[$last['room_id']][$last['day_of_week']][$last['time']]);
                            unset($term_slots[(int)$subject['term']][$last['day_of_week']][$last['time']]);
                            unset($subject_slots[$sid][$last['day_of_week']][$last['time']]);
                            $t_days = $teacher_days[$teacher_id] ?? [];
                            $t_days = array_values(array_diff($t_days, [$pair[0]]));
                            $teacher_days[$teacher_id] = $t_days;
                        }
                    }
                }

                // Phase 3: singles on non-adjacent days
                if ($cnt < 2) {
                    $sd = $days_list;
                    shuffle($sd);
                    $sd = $sortDays($sd, (int)$subject['term']);
                    $placed_days = [];
                    if (isset($subject_slots[$sid])) {
                        foreach (array_keys($subject_slots[$sid]) as $p) {
                            $placed_days[] = $day_index[$p];
                        }
                    }
                    foreach ($sd as $day) {
                        if ($cnt >= 2) break;
                        $di = $day_index[$day];
                        $too_close = false;
                        foreach ($placed_days as $pdi) {
                            if (abs($di - $pdi) <= 1) { $too_close = true; break; }
                        }
                        if ($too_close) continue;
                        if ($tryPlace($subject, $teacher_id, $day, $di)) {
                            $placed_days[] = $di;
                            $cnt++;
                        }
                    }
                }

                // Phase 4: fallback any day
                if ($cnt < 2) {
                    $sd = $days_list;
                    shuffle($sd);
                    $sd = $sortDays($sd, (int)$subject['term']);
                    foreach ($sd as $day) {
                        if ($cnt >= 2) break;
                        if ($tryPlace($subject, $teacher_id, $day, $day_index[$day])) $cnt++;
                    }
                }
            } else {
                // Priority 1: single slot
                $pref_ids = $preferred[$sid] ?? [];
                if (!empty($pref_ids)) {
                    foreach ($pref_ids as $pid) {
                        if ($cnt >= 1) break;
                        if (isset($subject_slots[$pid])) {
                            foreach (array_keys($subject_slots[$pid]) as $pday) {
                                if ($cnt >= 1) break;
                                if ($tryPlace($subject, $teacher_id, $pday, $day_index[$pday])) $cnt = 1;
                            }
                        }
                    }
                }
                if ($cnt < 1) {
                    $sd = $days_list;
                    shuffle($sd);
                    $sd = $sortDays($sd, (int)$subject['term']);
                    foreach ($sd as $day) {
                        if ($tryPlace($subject, $teacher_id, $day, $day_index[$day])) {
                            $cnt = 1;
                            break;
                        }
                    }
                }
            }

            if ($cnt < $needed) {
                $unassigned[] = $subject['subject_name'] . ' (ف' . $subject['term'] . ') - ' . $cnt . '/' . $needed;
            }
        }

        return [$assignments, $unassigned];
    }

    /**
     * Count same-day same-time term-adjacent conflicts from in-memory assignments.
     */
    public static function countConflicts(array $assignments, array $all_subjects, array $all_term_numbers = []): int {
        $st  = [];
        $rel = [];
        foreach ($all_subjects as $s) {
            $st[(int)$s['id']] = (int)$s['term'];
            if (!empty($s['requires_subject_id'])) {
                $a = (int)$s['id'];
                $b = (int)$s['requires_subject_id'];
                $rel[min($a, $b) . '_' . max($a, $b)] = true;
            }
        }
        $map = [];
        foreach ($assignments as $a) {
            $sid = (int)$a['subject_id'];
            if (!isset($st[$sid])) continue;
            $map[$a['day_of_week']][substr($a['time'], 0, 5)][$st[$sid]][] = $sid;
        }
        $cnt = 0;
        foreach ($map as $times) {
            foreach ($times as $terms) {
                $tk = array_keys($terms);
                sort($tk);
                foreach ($tk as $t) {
                    if (!isset($terms[$t + 1])) continue;
                    foreach ($terms[$t] as $s1) {
                        foreach ($terms[$t + 1] as $s2) {
                            if (!isset($rel[min($s1, $s2) . '_' . max($s1, $s2)])) $cnt++;
                        }
                    }
                }
            }
        }
        return $cnt;
    }
}
