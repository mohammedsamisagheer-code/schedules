# AGENTS.md — Schedules (بوابة الجداول)

## Tech stack
- PHP 8+ (PDO, no framework), MySQL, Tailwind CSS v4 (CLI), vanilla JS.
- Runs on XAMPP / Apache. All UI is RTL Arabic.

## Quick start
```bash
npm run build       # compile Tailwind (input.css → style.css)
npm run watch       # watch mode
```
DB connection: `includes/config.php` (gitignored, uses MySQL `class_schedule`).

## RBAC
- **admin** — full access to all Admin/ pages.
- **user** — granular CRUD via `user_permissions` table (keys: `perm_user_*`).
- **teacher** — login via `teacher_login.php`, can only view `my_schedule.php`.
- Auth helpers in `includes/auth_check.php`: `checkAuth()`, `isAdmin()`, `isUser()`, `isTeacher()`, `getCurrentUser()`.

## Architecture
| Path | Purpose |
|------|---------|
| `Admin/*.php` | Dashboard CRUD pages; each starts with `session_start()` + require config + auth_check |
| `includes/config.php` | DB connection, globals (`buildTimeSlots`, `logActivity`, `getUserPermissions`), settings constants |
| `includes/auth_check.php` | Auth guards |
| `schedule.php`, `exams.php` | Public-facing timetable viewers |
| `login.php` | Admin/user login with brute-force protection |
| `teacher_login.php` | Separate teacher login |

## Schedule generation
In `Admin/view_schedule.php:27` (`_generateOnce`): automated timetable generator with hard constraints (same-term, teacher/room double-booking, max 4 days/week, non-adjacent days for priority-2 subjects). Drag-drop moves via `Admin/move_schedule.php`. Exam generation in `Admin/exam_schedule.php`. Algorithm rules documented in `Rules.txt`.

## Database
- `class_schedule` DB, utf8mb4. Tables: `subjects`, `teachers`, `rooms`, `schedules` (per-day `enum` of Arabic day names), `exam_schedules`, `settings` (key-value), `users`, `user_permissions`, `activity_logs`, `login_attempts`.
- DB dump at `includes/class_schedule_*.sql`.
- Passwords appear to be MD5 (legacy — `fc13e1d392c581f341fdb3f7cb093de8`).

## Notable config
- Timezone: `Africa/Tripoli` (UTC+2).
- Class periods: configurable start time + N×2h slots (default 3 periods from 09:00).
- Session timeout, brute-force limits, academic year all stored in `settings` table.
- `CLASSES_START_TIME`, `PERIODS_COUNT`, `SESSION_TIMEOUT`, `BF_MAX_ATTEMPTS`, `BF_LOCKOUT_MINUTES` — constants loaded from DB at boot.

## Conventions
- Every Admin page: `session_start()` → require config + auth_check → `checkAuth()` → `getCurrentUser()` → permission check → logic → HTML.
- All POST handlers check `!isAdmin()` before admin-only actions.
- Activity logging via `logActivity($pdo, $action, $user_name)`.
- Subject `day_of_week` uses Arabic enum: `'السبت','الأحد','الإثنين','الثلاثاء','الإربعاء','الخميس'`.
- `requires_subject_id` on subjects creates prerequisite linkage in the scheduler.

## Constraints
- `includes/` is denied from web via `.htaccess` (Deny from all).
- `includes/config.php` is gitignored (contains DB credentials).
- `Admin/` uses `Options -Indexes` and `DirectoryIndex index.php`.
