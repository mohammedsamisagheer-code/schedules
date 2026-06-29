# AGENTS.md — Schedules (بوابة الجداول)

## Tech stack
- PHP 8+ (PDO, no framework), MySQL, Tailwind CSS v4 (CLI), vanilla JS.
- Runs on XAMPP / Apache. All UI is RTL Arabic.

## Quick start
```bash
npm run build       # compile assets/CSS/input.css → assets/CSS/style.css (Tailwind v4 CLI)
npm run watch       # watch mode
```
DB connection: `includes/config.php` (gitignored, MySQL `class_schedule`).
DB setup: `php migrate.php` — creates all 11 tables + seeds system data (settings, terms, admin user). Idempotent, safe to re-run.

## RBAC
| Role | Access |
|------|--------|
| **admin** | Full access to all Admin/ pages |
| **user** | Granular CRUD via `user_permissions` table (keys: `perm_user_*`) |
| **teacher** | Login via `teacher_login.php`, only sees `my_schedule.php` + `change_password.php` |

Auth helpers in `includes/auth_check.php`: `checkAuth()`, `isAdmin()`, `isUser()`, `isTeacher()`, `getCurrentUser()`, `checkMustChangePassword()`.

## Architecture
- `Admin/*.php` — dashboard CRUD; each starts with `session_start()` → require config + auth_check → `checkAuth()` → logic → HTML.
- `includes/config.php` — DB connection, globals (`buildTimeSlots`, `logActivity`, `getUserPermissions`, `getSettings`, `getTerms`), settings constants.
- `includes/auth_check.php` — auth guards and `checkMustChangePassword()`.
- `schedule.php`, `exams.php` — **public** viewers (no auth, just `require_once 'includes/config.php'`). `exams.php` filters by `exam_type` (`mid_term`/`final`).
- `login.php` — admin/user login with brute-force protection (tracks IP, configurable `BF_MAX_ATTEMPTS`/`BF_LOCKOUT_MINUTES`).
- `teacher_login.php` — teacher login by `national_id` (no brute-force lockout).

## Schedule generation
`Admin/view_schedule.php` (`_generateOnce` function): automated timetable generator with hard constraints (same-term, teacher/room double-booking, max 4 days/week, non-adjacent days for priority-2 subjects). Drag-drop moves via `Admin/move_schedule.php`. Exam generation in `Admin/exam_schedule.php`, with settings `exam_interval` and `exam_exams_per_day`. Algorithm rules documented in `Rules.txt`.

## Key domain details
- **Subject `priority`**: `1` = 1 slot/week, `2` = 2 slots/week (non-adjacent days attempted first).
- **`requires_subject_id`**: a PREFERENCE, not a hard constraint — scheduler tries to co-schedule the subject with its prerequisite at the same day+time.
- **`exam_type`** enum on `exam_schedules`: `'mid_term'`, `'final'`.
- **Schedule `time` field**: stores `HH:MM:SS` (keys from `buildTimeSlots()`). Compared with `LEFT(time,5)` in move logic.
- **Terms**: `3`–`8` auto-seeded in `terms` table, mapped to `الفصل الثالث`–`الفصل الثامن`.
- **Day names** (Arabic enum): `'السبت','الأحد','الإثنين','الثلاثاء','الإربعاء','الخميس'`.

## Database
- `class_schedule` DB, utf8mb4. DB dump at `includes/class_schedule_*.sql`.
- Tables: `subjects`, `teachers`, `rooms`, `schedules`, `exam_schedules`, `settings` (key-value), `users`, `user_permissions`, `activity_logs`, `login_attempts`, `terms`.

## Notable config
- Timezone: `Africa/Tripoli` (UTC+2). MySQL time_zone set to `+02:00`.
- Class periods: configurable start time (`CLASSES_START_TIME`) + N×2h slots (`PERIODS_COUNT`), default 3 periods from 09:00.
- Constants loaded from DB `settings` table at boot: `SESSION_TIMEOUT`, `BF_MAX_ATTEMPTS`, `BF_LOCKOUT_MINUTES`, `COLLEGE_NAME`, `ACADEMIC_YEAR`.

## Conventions
- All POST handlers check `!isAdmin()` before destructive actions (delete, clear, generate). User CRUD checks `user_permissions`.
- Logging via `logActivity($pdo, $action, $user_name)`. AJAX logging also exists at `Admin/log_action.php`.
- `checkMustChangePassword()` is called on Admin/ pages to redirect users flagged with `must_change_password`.
- Password comparison: legacy code compares plain text AND MD5 (`$stored === $password || $stored === md5($password)`). Teacher login also allows `$password === $national_id` as default when no password is set.
- Excel export available for schedules and exams (visible in sidebar + logs).
- Print CSS built in: `.no-print` class hides elements, print styles in `assets/CSS/input.css`.

## Testing
- PHPUnit 11 via Composer (`composer require --dev phpunit/phpunit`).
- Run: `vendor/bin/phpunit` (reads `phpunit.xml.dist`, bootstrap: `tests/bootstrap.php`).
- Bootstrap requires MySQL `class_schedule` DB (via `includes/config.php`) + `tests/helpers/SchedulerHelper.php`.
- 4 test classes, 29 tests, 97 assertions.
- `SchedulerTest` uses `SchedulerHelper` (test-only flattened OOP copy of `_generateOnce` + `_countConflictsInMemory` from `view_schedule.php`).
- `GetUserPermissionsTest` uses SQLite `:memory:` PDO to avoid MySQL dependency.
- `tests/fixtures/` directory exists but is empty.

## Constraints
- `includes/` denied from web (`.htaccess`: `Deny from all`).
- `includes/config.php` is gitignored.
- `Admin/` uses `Options -Indexes` + `DirectoryIndex index.php`.
