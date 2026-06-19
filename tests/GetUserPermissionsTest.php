<?php
use PHPUnit\Framework\TestCase;

class GetUserPermissionsTest extends TestCase {
    private PDO $mockPdo;

    protected function setUp(): void {
        // In-memory SQLite so PDO is non-null and ->prepare() works
        $this->mockPdo = new PDO('sqlite::memory:');
        $this->mockPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Create settings table with a few rows
        $this->mockPdo->exec("CREATE TABLE settings (`key` TEXT PRIMARY KEY, `value` TEXT NOT NULL DEFAULT '')");
        $this->mockPdo->exec("INSERT INTO settings (`key`, `value`) VALUES ('perm_user_subjects_edit', '0')");
    }

    private array $expected_keys = [
        'perm_user_subjects_view',
        'perm_user_subjects_add',
        'perm_user_subjects_edit',
        'perm_user_subjects_delete',
        'perm_user_teachers_view',
        'perm_user_teachers_add',
        'perm_user_teachers_edit',
        'perm_user_teachers_delete',
        'perm_user_rooms_view',
        'perm_user_rooms_add',
        'perm_user_rooms_edit',
        'perm_user_rooms_delete',
        'perm_user_view_schedule',
        'perm_user_exam_schedule',
    ];

    public function testReturnsDefaultsForNullUser(): void {
        $perms = getUserPermissions($this->mockPdo);
        foreach ($this->expected_keys as $key) {
            $this->assertArrayHasKey($key, $perms, "Missing key: $key");
        }
        $this->assertSame('1', $perms['perm_user_subjects_view']);
    }

    public function testAllExpectedKeysPresent(): void {
        $perms = getUserPermissions($this->mockPdo);
        foreach ($this->expected_keys as $key) {
            $this->assertArrayHasKey($key, $perms);
        }
    }

    public function testValueIsOneOrZero(): void {
        $perms = getUserPermissions($this->mockPdo);
        foreach ($this->expected_keys as $key) {
            $this->assertContains($perms[$key], ['0', '1'], "Unexpected value for $key");
        }
    }

    public function testReturnsArray(): void {
        $this->assertIsArray(getUserPermissions($this->mockPdo));
    }

    public function testDbValuesOverrideDefaults(): void {
        $this->mockPdo->exec("INSERT OR REPLACE INTO settings (`key`, `value`) VALUES ('perm_user_subjects_view', '0')");
        $perms = getUserPermissions($this->mockPdo);
        $this->assertSame('0', $perms['perm_user_subjects_view']);
    }
}
