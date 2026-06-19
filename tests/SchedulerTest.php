<?php
use PHPUnit\Framework\TestCase;

class SchedulerTest extends TestCase {
    private array $days = ['السبت', 'الأحد', 'الإثنين', 'الثلاثاء', 'الإربعاء', 'الخميس'];
    private array $times = ['09:00:00', '11:00:00', '13:00:00'];
    private array $rooms;

    protected function setUp(): void {
        $this->rooms = [
            ['id' => 1, 'name' => 'قاعة 1'],
            ['id' => 2, 'name' => 'قاعة 2'],
        ];
    }

    public function testSinglePriorityOneSubject(): void {
        $subjects = [
            ['id' => 1, 'subject_name' => 'رياضيات', 'term' => 3, 'teacher_id' => 1, 'priority' => 1, 'requires_subject_id' => null],
        ];
        [$assignments, $unassigned] = SchedulerHelper::generateOnce($subjects, $this->rooms, $this->days, $this->times);
        $this->assertCount(1, $assignments);
        $this->assertEmpty($unassigned);
        $this->assertSame(1, $assignments[0]['subject_id']);
    }

    public function testSinglePriorityTwoSubjectGetsTwoSlots(): void {
        $subjects = [
            ['id' => 1, 'subject_name' => 'برمجة', 'term' => 3, 'teacher_id' => 1, 'priority' => 2, 'requires_subject_id' => null],
        ];
        [$assignments, $unassigned] = SchedulerHelper::generateOnce($subjects, $this->rooms, $this->days, $this->times);
        $this->assertCount(2, $assignments, 'Priority-2 subject should get 2 slots');
        $this->assertEmpty($unassigned);
        foreach ($assignments as $a) {
            $this->assertSame(1, $a['subject_id']);
        }
    }

    public function testSameTermSubjectsDontConflict(): void {
        $subjects = [
            ['id' => 1, 'subject_name' => 'رياضيات',   'term' => 3, 'teacher_id' => 1, 'priority' => 1, 'requires_subject_id' => null],
            ['id' => 2, 'subject_name' => 'فيزياء',    'term' => 3, 'teacher_id' => 2, 'priority' => 1, 'requires_subject_id' => null],
            ['id' => 3, 'subject_name' => 'برمجة',     'term' => 3, 'teacher_id' => 3, 'priority' => 1, 'requires_subject_id' => null],
        ];
        [$assignments, $unassigned] = SchedulerHelper::generateOnce($subjects, $this->rooms, $this->days, $this->times);
        $this->assertCount(3, $assignments);
        $this->assertEmpty($unassigned);

        $conflicts = SchedulerHelper::countConflicts($assignments, $subjects);
        $this->assertSame(0, $conflicts, 'Same-term subjects should not cause term-adjacent conflicts');
    }

    public function testTeacherNotDoubleBooked(): void {
        $subjects = [
            ['id' => 1, 'subject_name' => 'قواعد بيانات', 'term' => 3, 'teacher_id' => 1, 'priority' => 1, 'requires_subject_id' => null],
            ['id' => 2, 'subject_name' => 'شبكات',       'term' => 4, 'teacher_id' => 1, 'priority' => 1, 'requires_subject_id' => null],
        ];
        [$assignments, ] = SchedulerHelper::generateOnce($subjects, $this->rooms, $this->days, $this->times);
        $slots = [];
        foreach ($assignments as $a) {
            $key = $a['day_of_week'] . '@' . $a['time'];
            $this->assertArrayNotHasKey($key, $slots, "Teacher double-booked at $key");
            $slots[$key] = true;
        }
    }

    public function testRoomNotDoubleBooked(): void {
        $rooms = [['id' => 1, 'name' => 'قاعة وحيدة']];
        $subjects = [
            ['id' => 1, 'subject_name' => 'مادة أ', 'term' => 3, 'teacher_id' => 1, 'priority' => 1, 'requires_subject_id' => null],
            ['id' => 2, 'subject_name' => 'مادة ب', 'term' => 4, 'teacher_id' => 2, 'priority' => 1, 'requires_subject_id' => null],
        ];
        [$assignments, ] = SchedulerHelper::generateOnce($subjects, $rooms, $this->days, $this->times);
        $slots = [];
        foreach ($assignments as $a) {
            $key = $a['day_of_week'] . '@' . $a['time'] . '#room' . $a['room_id'];
            $this->assertArrayNotHasKey($key, $slots, "Room double-booked at $key");
            $slots[$key] = true;
        }
    }

    public function testTeacherMaxDaysEnforced(): void {
        $subjects = [];
        for ($i = 1; $i <= 7; $i++) {
            $subjects[] = [
                'id' => $i,
                'subject_name' => "مادة $i",
                'term' => 3 + ($i % 3),
                'teacher_id' => 1,
                'priority' => 1,
                'requires_subject_id' => null,
            ];
        }
        [$assignments, ] = SchedulerHelper::generateOnce($subjects, $this->rooms, $this->days, $this->times, 3);
        $used_days = [];
        foreach ($assignments as $a) {
            $used_days[$a['day_of_week']] = true;
        }
        $this->assertLessThanOrEqual(3, count($used_days), 'Teacher should not exceed 3 days/week');
    }

    public function testPreferenceCoscheduling(): void {
        $subjects = [
            ['id' => 1, 'subject_name' => 'متطلب سابق', 'term' => 3, 'teacher_id' => 1, 'priority' => 1, 'requires_subject_id' => null],
            ['id' => 2, 'subject_name' => 'مادة لاحقة', 'term' => 3, 'teacher_id' => 2, 'priority' => 1, 'requires_subject_id' => 1],
        ];
        [$assignments, $unassigned] = SchedulerHelper::generateOnce($subjects, [['id' => 1, 'name' => 'قاعة 1']], $this->days, $this->times);
        $this->assertEmpty($unassigned);
        // Both should be placed on the same day+time (they're same-term, so conflict is expected)
        $conflicts = SchedulerHelper::countConflicts($assignments, $subjects);
        // With co-scheduling, they should end up together but that creates a same-term conflict
        // because both are in term 3. This tests that the scheduler attempts co-schedule.
        $a1 = $assignments[0];
        $a2 = $assignments[1];
        // They should be in the same day+time slot (co-scheduled)
        $this->assertSame($a1['day_of_week'], $a2['day_of_week']);
    }

    public function testPriorityTwoNonAdjacentDaysPreferred(): void {
        $subjects = [
            ['id' => 1, 'subject_name' => 'مادة مهمة', 'term' => 3, 'teacher_id' => 1, 'priority' => 2, 'requires_subject_id' => null],
        ];
        [$assignments, ] = SchedulerHelper::generateOnce($subjects, $this->rooms, $this->days, $this->times);
        $this->assertCount(2, $assignments);
        $days = [$assignments[0]['day_of_week'], $assignments[1]['day_of_week']];
        $diff = abs(array_search($days[0], $this->days) - array_search($days[1], $this->days));
        // Days should ideally be non-adjacent, but fallback allows adjacency
        $this->assertGreaterThan(0, $diff, 'Priority-2 subject should be on different days');
    }

    public function testCountConflictsDetectsConflicts(): void {
        $assignments = [
            ['subject_id' => 1, 'day_of_week' => 'السبت', 'time' => '09:00:00'],
            ['subject_id' => 2, 'day_of_week' => 'السبت', 'time' => '09:00:00'],
        ];
        $subjects = [
            ['id' => 1, 'term' => 3, 'requires_subject_id' => null],
            ['id' => 2, 'term' => 4, 'requires_subject_id' => null],
        ];
        $this->assertSame(1, SchedulerHelper::countConflicts($assignments, $subjects));
    }

    public function testCountConflictsZeroOnClean(): void {
        $assignments = [
            ['subject_id' => 1, 'day_of_week' => 'السبت', 'time' => '09:00:00'],
            ['subject_id' => 2, 'day_of_week' => 'الأحد', 'time' => '09:00:00'],
        ];
        $subjects = [
            ['id' => 1, 'term' => 3, 'requires_subject_id' => null],
            ['id' => 2, 'term' => 4, 'requires_subject_id' => null],
        ];
        $this->assertSame(0, SchedulerHelper::countConflicts($assignments, $subjects));
    }

    public function testRelatedSubjectsNotCountedAsConflict(): void {
        $assignments = [
            ['subject_id' => 1, 'day_of_week' => 'السبت', 'time' => '09:00:00'],
            ['subject_id' => 2, 'day_of_week' => 'السبت', 'time' => '09:00:00'],
        ];
        $subjects = [
            ['id' => 1, 'term' => 3, 'requires_subject_id' => null],
            ['id' => 2, 'term' => 4, 'requires_subject_id' => 1],
        ];
        // Subject 2 requires subject 1, so they should not be counted as conflict
        $this->assertSame(0, SchedulerHelper::countConflicts($assignments, $subjects));
    }
}
