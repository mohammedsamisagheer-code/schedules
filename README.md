# Project Documentation: Class Schedule Portal

## 1. Project Overview
**Name:** بوابة الجداول - قسم هندسة تقنيات الحاسوب (Class Schedule Portal - Computer Technology Engineering Department)
**Institution:** كلية التقنية الهندسية - جنزور (Engineering Technology College - Janzour)
**Purpose:** A comprehensive web-based application designed to manage, generate, and display weekly class schedules and exam schedules for students, teachers, and administrators.

## 2. Tech Stack
- **Backend:** PHP 8+ (utilizing PDO for secure database operations)
- **Frontend:** HTML5, CSS3, JavaScript, Tailwind CSS (via PostCSS/CLI v4), Font Awesome
- **Database:** MySQL
- **Environment:** Apache / XAMPP
- **Build Tools:** Node.js (npm) for compiling Tailwind CSS

## 3. Directory Structure
- **`/Admin/`**: Backend dashboard and management modules for administrators, standard users, and teachers. Handles CRUD operations for all entities and the scheduling algorithms.
- **`/assets/`**: Static assets including:
  - `/CSS/`: Tailwind input (`input.css`) and compiled output (`style.css`).
  - `/JS/`: Frontend JavaScript logic, including animated landing page scripts.
  - `/fonts/`: Local font files (e.g., Cairo font).
  - `/images/`: Application images and logos.
- **`/includes/`**: Core backend files:
  - `config.php`: Database connection, global functions, and dynamic system constants.
  - `auth_check.php`: Authentication and role-based access control functions.
- **`index.php`**: Animated public landing page directing users to the schedules.
- **`schedule.php`, `exams.php`**: Public-facing portals to view the general weekly schedule and the exam schedules.
- **`login.php`, `teacher_login.php`**: Separate authentication portals for admins/users and teachers.
- **`logout.php`**: Secure session termination script.
- **`Rules.txt`**: Plaintext documentation outlining the exact algorithm constraints used for schedule generation.
- **`package.json` & `package-lock.json`**: NPM configuration to manage Tailwind CSS dependencies and build scripts (`npm run build`, `npm run watch`).

## 4. Database Schema (`class_schedule`)
The system is built on a normalized relational database containing the following core tables:
- **`users`**: Stores admins and standard users, their roles, and securely hashed passwords.
- **`teachers`**: Contains teacher profiles, academic titles, and workload limits (e.g., max days per week).
- **`subjects`**: Stores course metadata, credit hours, term associations, and prerequisite subject linkages.
- **`rooms`**: Manages physical classroom details and capacities.
- **`schedules`**: The core table for weekly timetables, linking `subject_id`, `teacher_id`, `room_id`, `day`, and `time_slot`.
- **`exam_schedules`**: Dedicated table for exam timetables, featuring an `exam_type` ENUM (`mid_term`, `final`) to differentiate between exam periods.
- **`settings`**: Dynamic system-wide configurations (academic year, session timeouts, brute-force lockouts, class time configurations).
- **`user_permissions`**: Granular access control settings defining exactly what modules standard users can view, add, edit, or delete.
- **`activity_logs`**: System audit trail capturing sensitive user actions.
- **`login_attempts`**: Security tracking for failed logins to enforce account lockouts.

## 5. Core Features & Architecture

### 5.1 Role-Based Access Control (RBAC)
- **Admin (`admin`)**: Full, unrestricted access to the dashboard, settings, permissions management, and schedule generation.
- **Standard User (`user`)**: Restricted access based on dynamic keys in the `user_permissions` table. Admins can grant or revoke specific CRUD rights (e.g., can view teachers but cannot edit them).
- **Teacher (`teacher`)**: Limited access focused purely on viewing their personal timetable (`my_schedule.php`) and account management.

### 5.2 Automated Scheduling Algorithm
The system features an automated schedule generation engine governed by constraints defined in `Rules.txt`:
- **Hard Constraints (Must be satisfied):**
  - **Same-term conflict:** Subjects from the same academic term cannot be scheduled at the exact same time.
  - **Prerequisite collision:** A subject and its prerequisite cannot overlap, ensuring students can take both if needed.
  - **Double-booking prevention:** Teachers and rooms cannot be assigned to multiple classes simultaneously.
  - **Daily limits:** A subject cannot be scheduled more than once per day.
  - **Teacher workload:** Enforces the maximum working days per week limit for each individual teacher (default: 4 days).
  - **Distribution:** Priority subjects attempt to distribute classes across non-adjacent days.
- **Soft Constraints (Optimizations):**
  - **Term-gap fill:** Attempts to align classes to give terms complete days off when possible.

### 5.3 Exam Management
- Distinct logic and database structures to handle Mid-term and Final exams.
- The public `exams.php` portal dynamically filters and displays schedules based on the selected `exam_type`.

### 5.4 Security Implementations
- **Brute-Force Protection:** Tracks failed login attempts and locks out IP/accounts for a configured duration.
- **Session Management:** Enforces strict session timeouts (e.g., 60 minutes of inactivity) configurable via the DB settings.
- **SQL Injection Prevention:** All database interactions use PDO prepared statements.
- **Password Policies:** Enforces secure hashing and mandatory password change flags where applicable.

### 5.5 UI/UX Design
- Completely responsive layout built with Tailwind CSS.
- Animated canvas background on the landing page for a modern feel.
- Interactive, mobile-friendly sidebar navigation in the Admin dashboard.
- Consistent Arabic typography (Cairo font) and RTL (Right-to-Left) layout design.
