<?php
use PHPUnit\Framework\TestCase;

class BuildTimeSlotsTest extends TestCase {

    public function testDefaultConfig(): void {
        $slots = buildTimeSlots('09:00', 3);
        $this->assertCount(3, $slots);
    }

    public function testKeysAreHmsFormat(): void {
        $slots = buildTimeSlots('09:00', 3);
        foreach (array_keys($slots) as $key) {
            $this->assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}$/', $key, "Key '$key' should be H:i:s");
        }
    }

    public function testLabelsMatchExpectedFormat(): void {
        $slots = buildTimeSlots('09:00', 1);
        $this->assertSame('09:00 - 11:00', $slots['09:00:00']);
    }

    public function testCustomStartTime(): void {
        $slots = buildTimeSlots('10:00', 2);
        $this->assertCount(2, $slots);
        $this->assertArrayHasKey('10:00:00', $slots);
        $this->assertArrayHasKey('12:00:00', $slots);
        $this->assertSame('10:00 - 12:00', $slots['10:00:00']);
        $this->assertSame('12:00 - 14:00', $slots['12:00:00']);
    }

    public function testSinglePeriod(): void {
        $slots = buildTimeSlots('08:00', 1);
        $this->assertCount(1, $slots);
        $this->assertArrayHasKey('08:00:00', $slots);
        $this->assertSame('08:00 - 10:00', $slots['08:00:00']);
    }

    public function testFourPeriods(): void {
        $slots = buildTimeSlots('09:00', 4);
        $this->assertCount(4, $slots);
        $expected_keys = ['09:00:00', '11:00:00', '13:00:00', '15:00:00'];
        foreach ($expected_keys as $k) {
            $this->assertArrayHasKey($k, $slots);
        }
        $this->assertSame('15:00 - 17:00', $slots['15:00:00']);
    }

    public function testTimeSlotsAreTwoHoursApart(): void {
        $slots = buildTimeSlots('09:00', 5);
        $keys = array_keys($slots);
        for ($i = 1; $i < count($keys); $i++) {
            $prev = strtotime($keys[$i - 1]);
            $cur  = strtotime($keys[$i]);
            $this->assertEquals(7200, $cur - $prev, "Expected 2-hour gap between slots");
        }
    }

    public function testEmptyPeriods(): void {
        $slots = buildTimeSlots('09:00', 0);
        $this->assertCount(0, $slots);
    }
}
