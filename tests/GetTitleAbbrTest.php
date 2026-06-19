<?php
use PHPUnit\Framework\TestCase;

class GetTitleAbbrTest extends TestCase {

    public function testDoctorReturnsD(): void {
        $this->assertSame('د. ', getTitleAbbr('دكتور'));
    }

    public function testProfessorReturnsA(): void {
        $this->assertSame('أ. ', getTitleAbbr('أستاذ'));
    }

    public function testEngineerReturnsM(): void {
        $this->assertSame('م. ', getTitleAbbr('مهندس'));
    }

    public function testUnknownTitleReturnsEmpty(): void {
        $this->assertSame('', getTitleAbbr('غير معروف'));
    }

    public function testEmptyTitleReturnsEmpty(): void {
        $this->assertSame('', getTitleAbbr(''));
    }
}
