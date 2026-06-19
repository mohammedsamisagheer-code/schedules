# PHPUnit Test Results — Schedules (بوابة الجداول)

## Setup

PHPUnit installed via Composer (version 11.5.55), running under PHP 8.2.12 (ZTS).

```bash
composer require --dev phpunit/phpunit
```

## Running

```bash
vendor/bin/phpunit
```

Configuration in `phpunit.xml.dist` points to `tests/bootstrap.php`.

## Test Suite

| Test Class | Tests | Assertions | Purpose |
|---|---|---|---|
| `BuildTimeSlotsTest` | 8 | 23 | `buildTimeSlots()` from `includes/config.php` |
| `GetTitleAbbrTest` | 5 | 5 | `getTitleAbbr()` from `includes/config.php` |
| `GetUserPermissionsTest` | 5 | 19 | `getUserPermissions()` with in-memory SQLite PDO |
| `SchedulerTest` | 11 | 50 | Scheduler algorithm via `tests/helpers/SchedulerHelper.php` |
| **Total** | **29** | **97** | |

## Results: **OK (29 tests, 97 assertions)**

```
PHPUnit 11.5.55 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.2.12
Configuration: C:\xampp\htdocs\schedules\phpunit.xml.dist

.............................                                     29 / 29 (100%)

Time: 00:00.040, Memory: 8.00 MB

OK (29 tests, 97 assertions)
```

## Test Breakdown

### `BuildTimeSlotsTest` (8 tests, 23 assertions)

Tests the pure function `buildTimeSlots($start_time, $periods)` which generates N×2-hour time slot keys/labels from a start time:

| Test | What it checks |
|---|---|
| `testDefaultConfig` | 3 slots from `09:00` by default |
| `testKeysAreHmsFormat` | Keys are `HH:MM:SS` format |
| `testLabelsMatchExpectedFormat` | Label `'09:00 - 11:00'` for key `09:00:00` |
| `testCustomStartTime` | `10:00` start with 2 periods |
| `testSinglePeriod` | Single slot from `08:00` |
| `testFourPeriods` | 4 periods covers `09:00`–`17:00` |
| `testTimeSlotsAreTwoHoursApart` | Consecutive keys are exactly 7200s apart |
| `testEmptyPeriods` | `periods=0` returns empty array |

### `GetTitleAbbrTest` (5 tests, 5 assertions)

Tests `getTitleAbbr($title)` mapping:

| Test | Result |
|---|---|
| `دكتور` → `'د. '` | ✓ |
| `أستاذ` → `'أ. '` | ✓ |
| `مهندس` → `'م. '` | ✓ |
| `غير معروف` → `''` | ✓ |
| Empty string → `''` | ✓ |

### `GetUserPermissionsTest` (5 tests, 19 assertions)

Tests `getUserPermissions($pdo, $user_id)` using an **in-memory SQLite PDO** mock (avoids MySQL dependency in unit tests):

| Test | What it checks |
|---|---|
| `testReturnsDefaultsForNullUser` | All 14 expected keys exist with defaults |
| `testAllExpectedKeysPresent` | All keys present with DB-backed PDO |
| `testValueIsOneOrZero` | Every value is `'0'` or `'1'` |
| `testReturnsArray` | Return type is array |
| `testDbValuesOverrideDefaults` | DB settings override hardcoded defaults |

### `SchedulerTest` (11 tests, 50 assertions)

Tests the timetable generation algorithm via `SchedulerHelper`, a test-only class that mirrors `Admin/view_schedule.php`'s `_generateOnce()` and `_countConflictsInMemory()` closures.

| Test | Constraint Verified |
|---|---|
| `testSinglePriorityOneSubject` | Basic single-slot placement works |
| `testSinglePriorityTwoSubjectGetsTwoSlots` | Priority-2 subjects get 2 slots |
| `testSameTermSubjectsDontConflict` | Term-adjacent conflicts are zero for same-term subjects |
| `testTeacherNotDoubleBooked` | A teacher is never assigned two classes at same day+time |
| `testRoomNotDoubleBooked` | A room is never double-booked |
| `testTeacherMaxDaysEnforced` | Teacher's `max_teaching_days` limit is respected (≤3 verified) |
| `testPreferenceCoscheduling` | `requires_subject_id` places dependent subjects on same day |
| `testPriorityTwoNonAdjacentDaysPreferred` | Priority-2 slots land on different days |
| `testCountConflictsDetectsConflicts` | `countConflicts()` correctly finds term conflicts |
| `testCountConflictsZeroOnClean` | `countConflicts()` returns 0 on clean data |
| `testRelatedSubjectsNotCountedAsConflict` | Subjects linked via `requires_subject_id` are not counted as conflicts |

## Test Structure

```
tests/
├── README.md                  ← This file
├── bootstrap.php              ← Session start + autoload + helpers
├── helpers/
│   └── SchedulerHelper.php    ← Test-only copy of scheduler logic
├── BuildTimeSlotsTest.php
├── GetTitleAbbrTest.php
├── GetUserPermissionsTest.php
└── SchedulerTest.php
```

The bootstrap requires:
1. Composer autoload
2. `includes/config.php` (uses real MySQL — needs `class_schedule` DB available)
3. `helpers/SchedulerHelper.php`

## Notes

- `SchedulerHelper.php` is a flattened OOP version of the closure-based scheduler in `view_schedule.php`. It preserves the exact same constraint logic but exposes the functions as static methods.
- `GetUserPermissionsTest` uses SQLite `:memory:` to avoid MySQL dependency. The real `getUserPermissions()` uses MySQL with `pdo_mysql`, but the tested logic (defaults, DB override, key structure) is identical.
- Tests deliberately avoid modifying any system files — all test helpers are self-contained in `tests/`.
