-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 07, 2026 at 01:31 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `class_schedule`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_name` varchar(255) DEFAULT '',
  `action` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_name`, `action`, `created_at`) VALUES
(1, 'أسماء الطويل', 'تسجيل دخول (admin)', '2026-04-06 23:08:41'),
(2, 'أسماء الطويل', 'أضاف قاعة: asa', '2026-04-06 23:08:54'),
(3, 'أسماء الطويل', 'حذف قاعة: asa', '2026-04-06 23:08:58');

-- --------------------------------------------------------

--
-- Table structure for table `exam_schedules`
--

CREATE TABLE `exam_schedules` (
  `id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `term` int(11) NOT NULL,
  `exam_date` date NOT NULL,
  `slot` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exam_schedules`
--

INSERT INTO `exam_schedules` (`id`, `subject_id`, `term`, `exam_date`, `slot`) VALUES
(117, 13, 3, '2026-04-11', 1),
(118, 24, 5, '2026-04-11', 2),
(119, 30, 7, '2026-04-11', 3),
(120, 17, 4, '2026-04-13', 1),
(121, 26, 6, '2026-04-13', 2),
(122, 36, 8, '2026-04-13', 3),
(123, 10, 3, '2026-04-15', 1),
(124, 22, 5, '2026-04-15', 2),
(125, 32, 7, '2026-04-15', 3),
(126, 16, 4, '2026-04-18', 1),
(127, 27, 6, '2026-04-18', 2),
(128, 35, 8, '2026-04-18', 3),
(129, 14, 3, '2026-04-20', 1),
(130, 23, 5, '2026-04-20', 2),
(131, 33, 7, '2026-04-20', 3),
(132, 18, 4, '2026-04-22', 1),
(133, 28, 6, '2026-04-22', 2),
(134, 37, 8, '2026-04-22', 3),
(135, 12, 3, '2026-04-25', 1),
(136, 20, 5, '2026-04-25', 2),
(137, 34, 7, '2026-04-25', 3),
(138, 19, 4, '2026-04-27', 1),
(139, 25, 6, '2026-04-27', 2),
(140, 11, 3, '2026-04-29', 1),
(141, 21, 5, '2026-04-29', 2),
(142, 31, 7, '2026-04-29', 3),
(143, 15, 4, '2026-05-02', 1),
(144, 29, 6, '2026-05-02', 2),
(145, 9, 3, '2026-05-04', 1);

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`id`, `name`) VALUES
(1, 'قاعة 16'),
(2, 'معمل 6'),
(3, 'قاعة 15'),
(4, 'معمل 1'),
(5, 'معمل 2'),
(6, 'معمل 3');

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

CREATE TABLE `schedules` (
  `id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `day_of_week` enum('السبت','الأحد','الإثنين','الثلاثاء','الإربعاء','الخميس') NOT NULL,
  `time` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `schedules`
--

INSERT INTO `schedules` (`id`, `subject_id`, `teacher_id`, `room_id`, `day_of_week`, `time`) VALUES
(5234, 13, 12, 1, 'الأحد', '09:00:00'),
(5235, 13, 12, 1, 'الإربعاء', '09:00:00'),
(5236, 22, 18, 1, 'السبت', '09:00:00'),
(5237, 22, 18, 1, 'الخميس', '09:00:00'),
(5238, 24, 14, 1, 'الإثنين', '09:00:00'),
(5239, 24, 14, 1, 'الخميس', '11:00:00'),
(5240, 15, 12, 1, 'الأحد', '11:00:00'),
(5241, 15, 12, 1, 'الثلاثاء', '09:00:00'),
(5242, 17, 7, 2, 'السبت', '09:00:00'),
(5243, 17, 7, 2, 'الإربعاء', '09:00:00'),
(5244, 14, 13, 3, 'السبت', '09:00:00'),
(5245, 14, 13, 2, 'الثلاثاء', '09:00:00'),
(5246, 9, 14, 2, 'الأحد', '11:00:00'),
(5247, 9, 14, 2, 'الخميس', '09:00:00'),
(5248, 37, 18, 2, 'الأحد', '09:00:00'),
(5249, 37, 18, 2, 'الخميس', '11:00:00'),
(5250, 30, 23, 3, 'الأحد', '09:00:00'),
(5251, 30, 23, 3, 'الإربعاء', '09:00:00'),
(5252, 20, 9, 4, 'الأحد', '09:00:00'),
(5253, 20, 9, 4, 'الإربعاء', '09:00:00'),
(5254, 16, 12, 1, 'الأحد', '13:00:00'),
(5255, 16, 12, 1, 'الإربعاء', '11:00:00'),
(5256, 27, 23, 3, 'الأحد', '11:00:00'),
(5257, 27, 23, 2, 'الإربعاء', '11:00:00'),
(5258, 25, 24, 2, 'الإثنين', '09:00:00'),
(5259, 25, 24, 3, 'الخميس', '09:00:00'),
(5260, 31, 9, 4, 'السبت', '09:00:00'),
(5261, 31, 9, 3, 'الإربعاء', '11:00:00'),
(5262, 33, 24, 4, 'الأحد', '11:00:00'),
(5263, 33, 24, 3, 'الخميس', '11:00:00'),
(5264, 35, 24, 2, 'الأحد', '13:00:00'),
(5265, 35, 24, 1, 'الخميس', '13:00:00'),
(5266, 26, 7, 1, 'السبت', '11:00:00'),
(5267, 26, 7, 4, 'الخميس', '11:00:00'),
(5268, 36, 13, 5, 'الإربعاء', '09:00:00'),
(5269, 23, 18, 2, 'السبت', '11:00:00'),
(5270, 23, 18, 2, 'الخميس', '13:00:00'),
(5271, 10, 13, 3, 'السبت', '11:00:00'),
(5272, 10, 13, 4, 'الإربعاء', '11:00:00'),
(5273, 21, 9, 1, 'السبت', '13:00:00'),
(5274, 21, 9, 1, 'الإربعاء', '13:00:00'),
(5275, 29, 7, 2, 'السبت', '13:00:00'),
(5276, 29, 7, 3, 'الخميس', '13:00:00'),
(5277, 34, 14, 3, 'الأحد', '13:00:00'),
(5278, 34, 14, 4, 'الخميس', '13:00:00'),
(5279, 32, 23, 3, 'الإثنين', '09:00:00'),
(5280, 32, 23, 2, 'الإربعاء', '13:00:00'),
(5281, 28, 8, 5, 'الأحد', '09:00:00'),
(5282, 28, 8, 6, 'الإربعاء', '09:00:00'),
(5283, 18, 8, 4, 'السبت', '11:00:00'),
(5284, 18, 8, 3, 'الإربعاء', '13:00:00'),
(5285, 19, 10, 6, 'الأحد', '09:00:00'),
(5286, 12, 6, 4, 'الأحد', '13:00:00'),
(5287, 11, 11, 4, 'الإربعاء', '13:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `subject_name` varchar(50) NOT NULL,
  `subject_code` varchar(50) DEFAULT NULL,
  `term` int(11) NOT NULL,
  `priority` int(11) NOT NULL DEFAULT 2,
  `requires_subject_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `teacher_id`, `subject_name`, `subject_code`, `term`, `priority`, `requires_subject_id`) VALUES
(9, 14, 'مقدمة في تقنية المعلومات', 'EC201', 3, 2, NULL),
(10, 13, 'أسس أنظمة رقمية', 'EE231', 3, 2, NULL),
(11, 11, 'مصطلحات فنية', 'GH221', 3, 1, NULL),
(12, 6, 'دوائر كهربائية', 'EC205', 3, 1, NULL),
(13, 12, 'أساسيات برمجة', 'EC200', 3, 2, NULL),
(14, 13, 'تراكيب منفصلة', 'EC205', 3, 2, NULL),
(15, 12, 'نظم قواعد البيانات 1', 'EC324', 4, 2, 13),
(16, 12, 'برمجة شيئية 1', 'EC220', 4, 2, 13),
(17, 7, 'الوسائط المتعددة', 'EC202', 4, 2, NULL),
(18, 8, 'بنية حاسب', 'EC216', 4, 2, 10),
(19, 10, 'توثيق تقني', 'EC222', 4, 1, 11),
(20, 9, 'نظم تشغيل', 'EC212', 5, 2, 18),
(21, 9, 'نظم قواعد البيانات 2', 'EC330', 5, 2, 15),
(22, 18, 'برمجة شيئية 2', 'EC319', 5, 2, 16),
(23, 18, 'تراكيب البيانات', 'EC214', 5, 2, 16),
(24, 14, 'أساسيات شبكات', 'EC316', 5, 2, NULL),
(25, 24, 'تصميم صفحات الإنترنت - HTML', 'EC355', 6, 2, NULL),
(26, 7, 'إدارة مشاريع', 'EC309', 6, 2, 21),
(27, 23, 'برمجة مرئية 1', 'EC366', 6, 2, 22),
(28, 8, 'تحليل وتصميم النظم', 'EC314', 6, 2, 22),
(29, 7, 'مبادئ أمن المعلومات', 'EC307', 6, 2, 24),
(30, 23, 'برمجة صفحات الإنترنت - PHP', 'ECP405', 7, 2, 25),
(31, 9, 'نمذجة عمليات', 'ECP401', 7, 2, 25),
(32, 23, 'برمجة مرئية 2', 'ECP410', 7, 2, 27),
(33, 24, 'برمجة موبايل', 'EC400', 7, 2, 25),
(34, 14, 'خدمات تقنية المعلومات', 'EC407', 7, 2, 29),
(35, 24, 'تصميم واجهة المستخدم', 'ECP409', 8, 2, NULL),
(36, 13, 'أخلاقيات مهنة', 'ECP480', 8, 1, NULL),
(37, 18, 'هندسة برمجيات', 'ECP420', 8, 2, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `title` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`id`, `name`, `title`) VALUES
(6, 'عبدالباسط', 'دكتور'),
(7, 'حسين', 'دكتور'),
(8, 'حسام', 'دكتور'),
(9, 'الصديق', 'دكتور'),
(10, 'حنان', 'دكتور'),
(11, 'نورا', 'أستاذ'),
(12, 'يسرا', 'أستاذ'),
(13, 'هيثم', 'أستاذ'),
(14, 'عزيزة', 'أستاذ'),
(15, 'نعيمة', 'أستاذ'),
(16, 'نجوى', 'أستاذ'),
(17, 'إيمان', 'أستاذ'),
(18, 'سندس', 'أستاذ'),
(19, 'أحلام', 'أستاذ'),
(20, 'عزالدين', 'أستاذ'),
(21, 'ناجية', 'أستاذ'),
(22, 'منال', 'أستاذ'),
(23, 'أسماء', 'أستاذ'),
(24, 'نسرين', 'أستاذ'),
(25, 'هناء', 'أستاذ'),
(26, 'عواطف', 'أستاذ'),
(27, 'خلود', 'أستاذ');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `title` varchar(20) DEFAULT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'admin'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `name`, `title`, `role`) VALUES
(2, 'asma', 'fc13e1d392c581f341fdb3f7cb093de8', 'أسماء الطويل', 'مهندس', 'admin'),
(4, 'md_sghr', '08897611', 'محمد سامي الصغير', 'مهندس', 'user');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `exam_schedules`
--
ALTER TABLE `exam_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `exam_date` (`exam_date`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `exam_schedules`
--
ALTER TABLE `exam_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=146;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5288;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
