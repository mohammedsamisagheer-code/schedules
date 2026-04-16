# Project Brief — Academic Schedule Management System
### كلية التقنية الهندسية - جنزور | Engineering Technology College, Janzour

---

## 1. Project Overview

This is a **web-based academic schedule management system** developed for the Department of Computer Systems Engineering Technology at Janzour Engineering Technology College. The system allows administrators to manage the full academic timetable lifecycle: defining teachers, rooms, and subjects; generating weekly lecture schedules (manually or automatically); managing exam schedules; and publishing the final timetable to students and staff via a public-facing read-only view. It supports two user roles (admin and regular user), enforces scheduling constraints at the database level, and provides an Arabic-language, RTL interface.

---

## 2. Tech Stack

### Backend
| Technology | Version | Role |
|---|---|---|
| PHP | 8.2.12 | Server-side logic, routing, form handling |
| PDO (PHP Data Objects) | Built-in | Database abstraction layer |
| MySQL | Relational database |
| Apache (XAMPP) | — | Web server |

### Frontend
| Technology | Version | Role |
|---|---|---|
| TailwindCSS | 4.2.2 | Utility-first CSS framework |
| @tailwindcss/forms | 0.5.11 | Form element styling plugin |
| Font Awesome | 6.4.0 | Icon library (CDN) |
| Google Fonts — Cairo | — | Arabic-optimised web font |
| Vanilla JavaScript | ES6+ | UI interactions (drag-drop, modals, AJAX-free form flows) |

### Build Tools
| Tool | Version | Role |
|---|---|---|
| Node.js / npm | — | Runs Tailwind CSS CLI compilation only |
| @tailwindcss/cli | 4.2.2 | Compiles `input.css` → `style.css` |

> **Note:** Node.js is a **dev-only** dependency. The compiled `style.css` is committed and served statically. No JavaScript bundler is used at runtime.

---

## 3. System Architecture

The application uses a **Page Controller** pattern (not a full MVC framework). Each PHP page is self-contained: it handles its own request parsing, business logic, database queries, and HTML rendering.

```
Browser Request
      │
      ▼
 PHP Page (e.g. Admin/view_schedule.php)
      ├── session_start() + require config.php + auth_check.php
      ├── POST handling (form actions, redirects)
      ├── Data queries via $pdo (PDO)
      ├── Business logic (conflict checks, auto-generation)
      └── HTML rendering (inline PHP + TailwindCSS)
```

### Shared Infrastructure
| File | Responsibility |
|---|---|
| `includes/config.php` | DB connection (PDO), global helper functions (`logActivity`, `getTitleAbbr`, `buildTimeSlots`, `getSettings`), system constants loaded from `settings` table |
| `includes/auth_check.php` | `checkAuth()`, `isAdmin()`, `getCurrentUser()` — session validation and role enforcement |
| `includes/class_schedule.sql` | Full database dump (schema + seed data) |
| `assets/CSS/style.css` | Compiled Tailwind output — single CSS file served to all pages |
| `assets/JS/admin-common.js` | Shared sidebar toggle, session-expiry overlay |

### Directory Structure
```
schedules/
├── index.php               # Public landing page
├── login.php               # Authentication
├── logout.php              # Session destroy
├── schedule.php            # Public read-only schedule viewer
├── includes/
│   ├── config.php
│   ├── auth_check.php
│   └── class_schedule.sql
├── Admin/
│   ├── dashboard.php
│   ├── view_schedule.php   # Main schedule management
│   ├── my_schedule.php     # Per-teacher schedule editor
│   ├── exam_schedule.php   # Exam timetable management
│   ├── teachers.php
│   ├── subjects.php
│   ├── rooms.php
│   ├── users.php
│   ├── account.php
│   ├── settings.php
│   ├── move_schedule.php   # AJAX-style slot-move handler
│   └── log_action.php
└── assets/
    ├── CSS/style.css
    ├── JS/
    └── images/
```

---

## 4. Database Schema

**Database name:** `class_schedule`  
**Charset:** `utf8mb4` / `utf8`  
**Engine:** InnoDB

---

### 4.1 `users`
Stores system login accounts.

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | INT | PK, AUTO_INCREMENT | |
| `username` | VARCHAR(100) | UNIQUE, NOT NULL | Login identifier |
| `password` | VARCHAR(255) | NOT NULL | Plain text or MD5 hash |
| `name` | VARCHAR(100) | NOT NULL | Display name (Arabic) |
| `title` | VARCHAR(20) | NULL | e.g. مهندس |
| `role` | VARCHAR(20) | NOT NULL, default `admin` | `admin` or `user` |

---

### 4.2 `teachers`
Faculty members who are assigned subjects and schedule slots.

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | INT | PK, AUTO_INCREMENT | |
| `name` | VARCHAR(50) | NOT NULL | Teacher's name |
| `title` | VARCHAR(20) | NULL | `دكتور`, `أستاذ`, `مهندس` |

---

### 4.3 `rooms`
Physical lecture halls and labs.

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | INT | PK, AUTO_INCREMENT | |
| `name` | VARCHAR(50) | NOT NULL | e.g. `قاعة 16`, `معمل 1` |

---

### 4.4 `subjects`
Academic subjects offered per semester term.

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | INT | PK, AUTO_INCREMENT | |
| `teacher_id` | INT | NOT NULL, FK → `teachers.id` | Assigned teacher |
| `subject_name` | VARCHAR(50) | NOT NULL | Full subject name |
| `subject_code` | VARCHAR(50) | NULL | e.g. `EC200` |
| `term` | INT | NOT NULL | Semester number (3–8) |
| `priority` | INT | NOT NULL, default `2` | Sessions per week (1 or 2) |
| `requires_subject_id` | INT | NULL, FK → `subjects.id` | Self-referencing: prerequisite/equivalent subject; used by auto-scheduler for preferred overlap |

---

### 4.5 `schedules`
Weekly lecture timetable — each row is one 2-hour class session.

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | INT | PK, AUTO_INCREMENT | |
| `subject_id` | INT | NOT NULL, FK → `subjects.id` | |
| `teacher_id` | INT | NOT NULL, FK → `teachers.id` | |
| `room_id` | INT | NOT NULL, FK → `rooms.id` | |
| `day_of_week` | ENUM | NOT NULL | `السبت`, `الأحد`, `الإثنين`, `الثلاثاء`, `الإربعاء`, `الخميس` |
| `time` | VARCHAR(50) | NOT NULL | Start time e.g. `09:00:00` |

---

### 4.6 `exam_schedules`
Exam timetable — one row per subject exam.

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | INT | PK, AUTO_INCREMENT | |
| `subject_id` | INT | NOT NULL, FK → `subjects.id` | |
| `term` | INT | NOT NULL | Redundant copy for display grouping |
| `exam_date` | DATE | NOT NULL, indexed | |
| `slot` | INT | NOT NULL, default `1` | Exam slot order within the day |

---

### 4.7 `activity_logs`
Audit trail of admin actions.

| Column | Type | Description |
|---|---|---|
| `id` | INT | PK, AUTO_INCREMENT |
| `user_name` | VARCHAR(255) | Actor's display name |
| `action` | TEXT | Description of the action (Arabic) |
| `created_at` | TIMESTAMP | Auto-set on insert |

---

### 4.8 `settings`
Key-value configuration store. Auto-created on first load of `settings.php`.

| Column | Type | Description |
|---|---|---|
| `key` | VARCHAR(100) | PK — configuration key |
| `value` | TEXT | Configuration value |
| `label` | VARCHAR(200) | Human-readable Arabic label |

**Default keys:**

| Key | Default | Description |
|---|---|---|
| `academic_year` | `2025-2026` | Displayed in reports |
| `session_timeout_minutes` | `60` | Auto-logout timeout |
| `max_teaching_days` | `4` | Max days/week a teacher can teach |
| `bf_max_attempts` | `10` | Login attempts before lockout |
| `bf_lockout_minutes` | `5` | Lockout duration |
| `classes_start_time` | `09:00` | First period start time |
| `periods_count` | `3` | Number of daily periods |

---

### 4.9 `login_attempts`
Brute-force protection — one row per failed login per IP.

| Column | Type | Description |
|---|---|---|
| `ip` | VARCHAR(45) | Client IP address |
| `attempted_at` | TIMESTAMP | Auto-set on insert |

---

### Entity Relationship Summary

```
users           (standalone — no FK to teachers)
teachers ──────< subjects (teacher_id)
subjects ──────< schedules (subject_id)
teachers ──────< schedules (teacher_id)
rooms    ──────< schedules (room_id)
subjects ──────< exam_schedules (subject_id)
subjects ───┐
            └──> subjects.requires_subject_id (self-ref)
```

---

## 5. Core Logic & Algorithms

### 5.1 Manual Conflict Detection

Triggered every time a schedule slot is added or edited via `Admin/my_schedule.php`. Three independent SQL checks run before any INSERT/UPDATE, all using MySQL's `ADDTIME()` to detect overlapping 2-hour windows:

**Rule 1 — Term Conflict**
> No two subjects from the same semester term can occupy the same day+time slot (students in a given term would have two classes at once).

```sql
SELECT COUNT(*) FROM schedules s
LEFT JOIN subjects sb ON s.subject_id = sb.id
WHERE sb.term = :term
  AND s.day_of_week = :day
  AND (
    (s.time <= :t AND ADDTIME(s.time, '02:00:00') > :t) OR
    (s.time <  ADDTIME(:t, '02:00:00') AND ADDTIME(s.time, '02:00:00') >= ADDTIME(:t, '02:00:00')) OR
    (s.time >= :t AND s.time < ADDTIME(:t, '02:00:00'))
  )
```

**Rule 2 — Room Conflict**
> The same room cannot host two classes at the same time.

```sql
SELECT COUNT(*) FROM schedules
WHERE room_id = :room_id AND day_of_week = :day
  AND (/* same overlap logic */)
```

**Rule 3 — Teacher Conflict**
> The same teacher cannot be assigned to two classes at the same time.

```sql
SELECT COUNT(*) FROM schedules s
WHERE s.teacher_id = :teacher_id AND s.day_of_week = :day
  AND (/* same overlap logic */)
```

On edit, each query also excludes `s.id != :current_id` to avoid self-conflict.  
If any check returns `count > 0`, the request is rejected and the user is redirected with an `?error=` parameter (`term_conflict`, `room_conflict`, or `teacher_conflict`).

---

### 5.2 Auto-Schedule Generation Algorithm

Located in `Admin/view_schedule.php`. Triggered by the admin via `POST auto_generate`. The algorithm is a **greedy constraint-satisfaction** procedure:

#### Setup Phase
1. Deletes the existing schedule.
2. Loads all subjects ordered by teacher load (most-constrained teacher first — a teacher with many subjects gets priority in slot assignment to avoid being locked out).
3. Shuffles subjects within the same load tier to introduce variety across runs.
4. Builds a **preferred-overlap map** from `requires_subject_id`: if Subject A requires Subject B, the scheduler tries to place both on the same day+time (since students who need A have already passed B, there is no attendance conflict — deliberate co-scheduling is used to maximise room utilisation).
5. Pre-computes all valid **non-adjacent day pairs** (|index difference| > 1) for 2-session subjects to avoid back-to-back teaching days.

#### Placement Phase (per subject)
For each subject needing `priority` sessions (1 or 2):

- **Pass 0 (Preferred Overlap):** If any related subject is already scheduled, try to place this subject on the same days — to achieve the desired overlap.
- **Pass 1 (Non-Adjacent Pairs):** Try all non-adjacent day pairs sorted by term-gap priority (days free in the previous term are preferred — keeps each term's schedule spread across different days). If placing on Day A succeeds but Day B fails, the Day A assignment is rolled back.
- **Pass 2 (Any Day):** Fallback — try any free day.
- **Pass 3 (Any Day, Adjacent Allowed):** Last resort when the teacher's schedule is heavily loaded.

#### Within Each Day (`tryPlaceOnDay`)
- Checks the teacher has not exceeded `max_teaching_days`.
- Ensures the subject is not already placed on this day.
- Prioritises time slots in 3 tiers:
  - **T1:** Slot where a preferred/related subject is already scheduled.
  - **T2:** Slot where the previous term has no class (fills cross-term schedule gaps).
  - **T3:** Any remaining available slot.
- Picks the **least-used room** from available rooms to distribute load evenly.
- Validates: no teacher double-booking, no term double-booking, no room double-booking.

#### Additional Constraints
- A teacher must not have 3+ consecutive teaching days (checked via `wouldCreate3Consecutive()`).
- A subject cannot appear twice on the same day.
- All assignments are bulk-inserted at the end.
- Unassigned subjects are reported to the admin.

---

### 5.3 Exam Schedule Auto-Generation

Located in `Admin/exam_schedule.php`:

1. Subjects are split into **odd-term** (3, 5, 7) and **even-term** (4, 6, 8) groups.
2. Starting from an admin-specified date, each exam day receives up to `exams_per_day` exams.
3. Exams alternate round-robin across terms within each parity group, ensuring students in different terms do not have overlapping exam dates.
4. Days between exam sessions are controlled by the `interval` parameter (min 2, max 7 days).
5. Each exam is assigned a `slot` number (1-based) for within-day ordering.

---

## 6. Functional Requirements

### Admin Role
| Feature | File |
|---|---|
| Dashboard with counts and activity feed | `dashboard.php` |
| Full schedule view (all terms / single term) | `view_schedule.php` |
| Auto-generate weekly schedule | `view_schedule.php` |
| Clear entire schedule | `view_schedule.php` |
| Drag-and-drop schedule slot editing | `view-schedule.js` |
| Per-teacher schedule editor with conflict detection | `my_schedule.php` |
| Add / edit / delete schedule entries | `my_schedule.php` |
| Exam schedule management (CRUD) | `exam_schedule.php` |
| Auto-generate exam schedule | `exam_schedule.php` |
| Excel export of exam schedule | `exam_schedule.php` |
| Teacher CRUD | `teachers.php` |
| Room CRUD | `rooms.php` |
| Subject CRUD (with prerequisite links) | `subjects.php` |
| User management (add/edit/delete/reset password) | `users.php` |
| Account profile edit | `account.php` |
| System settings (timeout, periods, lockout, etc.) | `settings.php` |
| Activity audit log | `dashboard.php` |

### Regular User Role
| Feature | File |
|---|---|
| Read-only schedule view (all terms / single term) | `view_schedule.php` |
| Read-only exam schedule view | `exam_schedule.php` |

### Public (Unauthenticated)
| Feature | File |
|---|---|
| Landing page with login link | `index.php` |
| Read-only public schedule viewer | `schedule.php` |

### Security Features
- Role-based access control via session `role` key (`admin` / `user`).
- Session timeout enforced server-side (configurable, default 60 min).
- Brute-force login protection: IP-based failed attempt tracking with configurable lockout (`login_attempts` table).
- All DB queries use **PDO prepared statements** (prevents SQL injection).
- `includes/` directory protected by `.htaccess` to block direct web access.

---

## 7. Application Routes (URL → File Mapping)

| URL / File | Method | Access | Description |
|---|---|---|---|
| `index.php` | GET | Public | Landing page |
| `login.php` | GET/POST | Public | Login form + authentication |
| `logout.php` | GET | Any | Destroys session, redirects to login |
| `schedule.php` | GET | Public | Public weekly schedule viewer; `?term=N` or `?term=all` |
| `Admin/dashboard.php` | GET | Admin | Stats cards + recent activity log |
| `Admin/view_schedule.php` | GET/POST | Auth | Full schedule grid; POST actions: `auto_generate`, `clear_schedule` |
| `Admin/my_schedule.php` | GET/POST | Admin | Per-teacher schedule; `?teacher_id=N`; POST: `add_schedule`, `edit_schedule`, `delete_schedule` |
| `Admin/exam_schedule.php` | GET/POST | Auth | Exam timetable; POST: `auto_generate`, `clear_exams`, add/edit/delete entries |
| `Admin/teachers.php` | GET/POST | Admin | Teacher CRUD |
| `Admin/subjects.php` | GET/POST | Admin | Subject CRUD |
| `Admin/rooms.php` | GET/POST | Admin | Room CRUD |
| `Admin/users.php` | GET/POST | Admin | User CRUD |
| `Admin/account.php` | GET/POST | Auth | Profile & password update |
| `Admin/settings.php` | GET/POST | Admin | System settings (key-value store) |
| `Admin/move_schedule.php` | POST | Admin | Move a schedule slot (day/time update) — called by drag-and-drop JS |
| `Admin/log_action.php` | POST | Auth | Write an activity log entry from client side |

**Query Parameters Used:**
- `?term=N` — Filter schedule by semester (3–8) or `all`
- `?teacher_id=N` — Select teacher in `my_schedule.php`
- `?success=added|updated|deleted` — Flash success messages
- `?error=term_conflict|room_conflict|teacher_conflict` — Flash error messages
- `?expired=1` — Session timeout notice on login page
- `?cleared=1` — Confirmation of full schedule clear

---

## 8. Installation & Environment

### Requirements
| Component | Version |
|---|---|
| PHP | 8.2+ |
| MySQL | 10.4+ / 8.0+ |
| Apache | 2.4+ (with `mod_rewrite`) |
| Node.js | 18+ *(dev only, for CSS rebuild)* |
| npm | 9+ *(dev only)* |

### Setup Steps

1. **Clone / copy** the project to your web server root (e.g. `C:/xampp/htdocs/schedules/`).

2. **Create the database:**
   ```sql
   CREATE DATABASE class_schedule CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
   ```

3. **Import the schema and seed data:**
   ```bash
   mysql -u root class_schedule < includes/class_schedule.sql
   ```

4. **Configure the DB connection** in `includes/config.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'class_schedule');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```

5. **Set the timezone** (default is `Africa/Cairo`, UTC+2):
   ```php
   date_default_timezone_set('Africa/Cairo');
   ```

6. **Rebuild CSS** *(only needed if you modify Tailwind classes):*
   ```bash
   npm install
   npm run build
   ```

7. **Access the application** at `http://localhost/schedules/`

### Default Credentials
| Username | Password | Role |
|---|---|---|
| `asma` | *(MD5 hash stored)* | admin |
| `md_sghr` | `08897611` | user |

> ⚠️ **Security Note:** The current implementation stores passwords as plain text or MD5 (non-salted). For production use, passwords should be migrated to `password_hash()` / `password_verify()` (bcrypt).

### Key Configuration Constants (auto-loaded from `settings` table)

| Constant | Setting Key | Default |
|---|---|---|
| `COLLEGE_NAME` | `college_name` | `كلية التقنية الهندسية-جنزور` |
| `ACADEMIC_YEAR` | `academic_year` | `2025-2026` |
| `SESSION_TIMEOUT` | `session_timeout_minutes` | `3600` seconds |
| `CLASSES_START_TIME` | `classes_start_time` | `09:00` |
| `PERIODS_COUNT` | `periods_count` | `3` |
| `BF_MAX_ATTEMPTS` | `bf_max_attempts` | `10` |
| `BF_LOCKOUT_MINUTES` | `bf_lockout_minutes` | `5` |

---

*Document generated from codebase analysis — April 2026*
