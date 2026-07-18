-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Dec 31, 2025 at 04:03 PM
-- Server version: 11.8.3-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u605211817_papermanagemen`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_papers`
--

CREATE TABLE `admin_papers` (
  `paper_id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL COMMENT 'The admin who uploaded it',
  `file_path` varchar(255) NOT NULL,
  `stored_filename` varchar(255) NOT NULL,
  `num_questions` int(11) NOT NULL DEFAULT 20,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `answer_keys`
--

CREATE TABLE `answer_keys` (
  `answer_key_id` int(11) NOT NULL,
  `paper_id` int(11) NOT NULL COMMENT 'Links to admin_papers.paper_id',
  `teacher_id` int(11) NOT NULL COMMENT 'The teacher who submitted it',
  `answers` text NOT NULL COMMENT 'JSON-encoded answers { "1": "A", "2": "C", ... }',
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assignments`
--

CREATE TABLE `assignments` (
  `assignment_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `class_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `correction_papers`
--

CREATE TABLE `correction_papers` (
  `correction_paper_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `original_file_name` varchar(255) NOT NULL,
  `original_file_path` varchar(255) NOT NULL,
  `status` enum('pending_review','no_correction','corrected','completed') NOT NULL DEFAULT 'pending_review',
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `exams`
--

CREATE TABLE `exams` (
  `exam_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `status` enum('pending','active','completed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mcq_answer_keys`
--

CREATE TABLE `mcq_answer_keys` (
  `key_id` int(11) NOT NULL,
  `assignment_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `question_number` int(11) NOT NULL,
  `correct_option` enum('A','B','C','D') NOT NULL,
  `submitted_by` int(11) NOT NULL,
  `is_locked` tinyint(1) DEFAULT 1,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mcq_exams`
--

CREATE TABLE `mcq_exams` (
  `mcq_exam_id` int(11) NOT NULL,
  `exam_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `total_questions` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mcq_exam_assignments`
--

CREATE TABLE `mcq_exam_assignments` (
  `assignment_id` int(11) NOT NULL,
  `mcq_exam_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `assigned_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mcq_subject_ranges`
--

CREATE TABLE `mcq_subject_ranges` (
  `range_id` int(11) NOT NULL,
  `assignment_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `start_q` int(11) NOT NULL,
  `end_q` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'The user who receives the notification',
  `message` varchar(255) NOT NULL,
  `link` varchar(255) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `papers`
--

CREATE TABLE `papers` (
  `paper_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `assignment_id` int(11) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `stored_filename` varchar(255) NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `status` enum('pending_review','approved','rejected') NOT NULL DEFAULT 'pending_review',
  `submission_type` enum('file','text') NOT NULL DEFAULT 'file',
  `paper_content` longtext DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `paper_corrections`
--

CREATE TABLE `paper_corrections` (
  `correction_id` int(11) NOT NULL,
  `correction_paper_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `correction_notes` text DEFAULT NULL,
  `correction_image_path` varchar(255) DEFAULT NULL,
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_responses`
--

CREATE TABLE `student_responses` (
  `response_id` int(11) NOT NULL,
  `result_id` int(11) NOT NULL,
  `question_number` int(11) NOT NULL,
  `selected_option` enum('A','B','C','D') DEFAULT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_results`
--

CREATE TABLE `student_results` (
  `result_id` int(11) NOT NULL,
  `assignment_id` int(11) NOT NULL,
  `student_name` varchar(255) NOT NULL,
  `roll_no` varchar(50) NOT NULL,
  `omr_file_path` varchar(255) DEFAULT NULL,
  `total_score` int(11) NOT NULL DEFAULT 0,
  `correct_count` int(11) NOT NULL DEFAULT 0,
  `wrong_count` int(11) NOT NULL DEFAULT 0,
  `unattempted_count` int(11) NOT NULL DEFAULT 0,
  `graded_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `subject_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `teacher_queries`
--

CREATE TABLE `teacher_queries` (
  `query_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL COMMENT 'The admin who replied',
  `paper_id` int(11) DEFAULT NULL COMMENT 'Optional: link to a specific paper',
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `reply` text DEFAULT NULL,
  `status` enum('open','closed') NOT NULL DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `replied_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','teacher') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `email`, `password_hash`, `role`, `created_at`) VALUES
(1, 'Admin User', 'admin@school.com', '$2y$10$E.qJ0g/58.0R1.dGfF/xPu3D5.z.1/tE/i/u/0u.If/j/0u.If/j.', 'admin', '2025-10-24 10:00:00'),
(4, 'AKSHITA SOLANKI', 'suru730016@gmail.com', '$2y$10$Zd/C2EOnxluWm.eLf16NcuKNBKzK51VvWYhYFWwoH3RiALPJXtXmq', 'teacher', '2025-10-24 13:49:07'),
(5, 'SURESH KUMAR MEGHWAL', 'suru7392@gmail.com', '$2y$10$NlnXyfjKuexJ0XCtujA/5OYztN0O3talpD4p2Out1LrKIiYclKc26', 'admin', '2025-10-24 13:51:12'),
(6, 'Kalu ram', 'krkuli1979@gmail.com', '$2y$10$9QmPtBkHjF7p3Jf5kG33RuXMFzeqmX3k6UrkGy8j0AxYXeL7GfXga', 'teacher', '2025-10-24 15:18:17'),
(7, 'Shivraj Singh ', 'shivrajsinghshekhawat87@gmail.com', '$2y$10$vmglWELgwcC90EjpXl8zVOB76F6tojybYLvMdA9yt0RN4ChvIPfrW', 'teacher', '2025-10-24 15:41:05'),
(9, 'Rachit lawaniya', 'lawaniyarachit110@gmail.com', '$2y$10$V6O6tJeAL/WqOLoy4Bbi8efV308NZRtX1Dy6CVyhleopR1PvJ9V9a', 'teacher', '2025-10-24 16:24:10'),
(11, 'Rajveer ', 'rjvrtailor@gmail.com', '$2y$10$hUgCET25Q0GgDNeV0nw6LuTu1FqJqJn2MTcFcQpZAkfPXLZZHOn8W', 'teacher', '2025-10-24 16:36:15'),
(12, 'BHARAT Sir ', 'bharat.tlr2102@gmail.com', '$2y$10$vS.bL6bondWWBJMJtdsujuo/hxuEqMasH./9Pe7aMMK1V/NUPepnO', 'teacher', '2025-10-24 16:37:40'),
(13, 'Praveen Kumar Kumawat ', 'praveenkumawat0011@gmail.com', '$2y$10$WxbtdGx66HzM88exDkbaV.XEfinq7CRC.W2ls/EHilOUtEe8lApIO', 'teacher', '2025-10-25 00:08:46'),
(15, 'gourav tailor', 'gouravtailor2206@gmail.com', '$2y$10$hRnE49K3UHAS1IvRmrjDju2J9PVpQQjrM.KkEtAaUesW9lvsqtwVy', 'teacher', '2025-10-25 04:07:51'),
(16, 'CP SIR', 'CPSIR@GMAIL.COM', '$2y$10$TUKT04.YeaPLMqhbt5w/eewueDjvmR6Fp6mk5DSx/mWmub.T/IuSm', 'teacher', '2025-10-25 05:02:46'),
(17, 'Chena Ram ', 'suwasiya100@gmail.com', '$2y$10$WTkMiuRgIeK6Cz9qfRcjTOBLjRV32ENt61N2kpMO9g3YzA8X.ph0W', 'teacher', '2025-10-25 06:10:45'),
(18, 'Manish pareek ', 'pareekmanish1105@gmail.com', '$2y$10$ZLWmQ5UT0l0xJ9mLINZOO.Elkwnh89SUwFhHE17x26UhDJIuFjBSS', 'teacher', '2025-10-25 06:47:17'),
(19, 'ARUN KUMAR ', 'Arunbalara18308@gmail.com', '$2y$10$ZrPsMSApoP.OILE4kbk2oeXbmqddz7O5op0YzFWzMee56635O.ltW', 'teacher', '2025-10-25 07:37:01'),
(20, 'Nidhi pareek', 'nirvi@gmail.com', '$2y$10$E37U5u3MW9URdGjiaaS//u.j0hyDy9P6KJSe9x6gbodZPCtO80kGK', 'teacher', '2025-10-25 09:21:41'),
(21, 'Kailash Ram', 'kc43@gmail.com', '$2y$10$LFt3bUR8C0H5st2vgREh4ux3YN0h/GgWH2VI3ubVTH5FFfmHMkwcK', 'teacher', '2025-10-25 09:24:30'),
(22, 'Dipali pareek', 'Dipuprk@gmail.com', '$2y$10$DdTSOo3HLjxwhO33fvvvWegpHpiwmRlkjdD3O3Erifhr/f7FwoNGW', 'teacher', '2025-10-25 11:17:16'),
(24, 'Yogendra Singh ', 'suresh0@gmail.com', '$2y$10$2MUJ3TcThCJs3SQnfX4oTer0csFSfGMnwk5JjLfHtCb2Gx5b0EieK', 'teacher', '2025-10-27 05:23:36'),
(26, 'Ajay kumar', 'sanjay@gmal.com', '$2y$10$J/eazKzlDGyWE0nfJTWD7.BcVHIEdz2oscHatlG/sIKdc4OeyNbB.', 'teacher', '2025-11-03 07:22:25'),
(27, 'Narsi ram ', 'narsimathsnps@gmail.com', '$2y$10$5VO2cHFTC5xeCekd86oFCOhfLlt1pJ5g6q9dM3RZoLL1g1C1axLya', 'teacher', '2025-11-03 07:23:15'),
(28, 'Sanju choudhary ', 'sanjubajiya129@gmail.com', '$2y$10$BDdAIFcR9giyoBKVAAtC5..UUpe9aZFvJmXzX57xsVeVMG1YkK4Mq', 'teacher', '2025-11-04 12:24:42'),
(29, 'Sunita Choudhary ', 'sunuchoudhary15@gmail.com', '$2y$10$1Dcg8QigTX4rYIsXthXOXuXfSmqXAjkZStyNphk9sQpUt/H8DmjiG', 'teacher', '2025-11-05 15:50:57'),
(30, 'Suresh Kumar Meghwal', 'suvansharya@gmail.com', '$2y$10$Meopon8KnMxLuyimLqp4TOj5jIl.hxTWmxxyU3CiwaYRHq4PHyKRG', 'teacher', '2025-11-07 03:48:34'),
(31, 'Anamika', 'sanjaypareek783@gmail.com', '$2y$10$tma1ulliiA.sObEtdaBn9u1URIsefc2PhwxsKiiG9kmPbcG/uzlcC', 'teacher', '2025-11-07 11:18:56'),
(32, 'Shimbhu Sir', 'shimbhuskt@gmail.com', '$2y$10$W9QU4qDBkNdhcxoEANt9zue6lLb3w5j1AJlHxK4GG6j.Oq75Js.ZS', 'teacher', '2025-11-08 07:23:37'),
(33, 'Ramkishan Yadav ', 'Khandela@gmail.com', '$2y$10$MpFRZgFRvj8oow5FC.kI9OCjW6OqTavZGbatXjGylZ4qUw5lWuTc2', 'teacher', '2025-11-08 10:18:33'),
(35, 'Saroj yadav ', 'Neemkathana@gmail.Com', '$2y$10$t4zGIdFbep6BO1V5TGaggeotIDM9QZL7c2BDnTfca2cFd2Rkmmctu', 'teacher', '2025-11-10 16:39:23'),
(36, 'Sarojyadav', 'Neemkathana@gimal.com', '$2y$10$/AJMfArwyEDk8tPlGF384Oqojg5QNb8fcfpyHcfcUsKQv1oa7u/lC', 'teacher', '2025-11-28 02:54:41');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_papers`
--
ALTER TABLE `admin_papers`
  ADD PRIMARY KEY (`paper_id`),
  ADD KEY `exam_id` (`exam_id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `answer_keys`
--
ALTER TABLE `answer_keys`
  ADD PRIMARY KEY (`answer_key_id`),
  ADD UNIQUE KEY `paper_id` (`paper_id`) COMMENT 'Only one key per paper',
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `assignments`
--
ALTER TABLE `assignments`
  ADD PRIMARY KEY (`assignment_id`),
  ADD UNIQUE KEY `teacher_class_subject_unique` (`teacher_id`,`class_id`,`subject_id`),
  ADD KEY `fk_assignment_teacher` (`teacher_id`),
  ADD KEY `fk_assignment_class` (`class_id`),
  ADD KEY `fk_assignment_subject` (`subject_id`);

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`class_id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `correction_papers`
--
ALTER TABLE `correction_papers`
  ADD PRIMARY KEY (`correction_paper_id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `exams`
--
ALTER TABLE `exams`
  ADD PRIMARY KEY (`exam_id`);

--
-- Indexes for table `mcq_answer_keys`
--
ALTER TABLE `mcq_answer_keys`
  ADD PRIMARY KEY (`key_id`),
  ADD UNIQUE KEY `unique_key_assignment` (`assignment_id`,`question_number`);

--
-- Indexes for table `mcq_exams`
--
ALTER TABLE `mcq_exams`
  ADD PRIMARY KEY (`mcq_exam_id`);

--
-- Indexes for table `mcq_exam_assignments`
--
ALTER TABLE `mcq_exam_assignments`
  ADD PRIMARY KEY (`assignment_id`),
  ADD KEY `mcq_exam_id` (`mcq_exam_id`),
  ADD KEY `class_id` (`class_id`);

--
-- Indexes for table `mcq_subject_ranges`
--
ALTER TABLE `mcq_subject_ranges`
  ADD PRIMARY KEY (`range_id`),
  ADD KEY `assignment_id` (`assignment_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `papers`
--
ALTER TABLE `papers`
  ADD PRIMARY KEY (`paper_id`),
  ADD UNIQUE KEY `stored_filename` (`stored_filename`),
  ADD KEY `fk_paper_teacher` (`teacher_id`),
  ADD KEY `fk_paper_exam` (`exam_id`),
  ADD KEY `fk_paper_assignment` (`assignment_id`);

--
-- Indexes for table `paper_corrections`
--
ALTER TABLE `paper_corrections`
  ADD PRIMARY KEY (`correction_id`),
  ADD KEY `correction_paper_id` (`correction_paper_id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `student_responses`
--
ALTER TABLE `student_responses`
  ADD PRIMARY KEY (`response_id`),
  ADD KEY `result_id` (`result_id`);

--
-- Indexes for table `student_results`
--
ALTER TABLE `student_results`
  ADD PRIMARY KEY (`result_id`),
  ADD KEY `assignment_id` (`assignment_id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`subject_id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `teacher_queries`
--
ALTER TABLE `teacher_queries`
  ADD PRIMARY KEY (`query_id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `paper_id` (`paper_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_papers`
--
ALTER TABLE `admin_papers`
  MODIFY `paper_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `answer_keys`
--
ALTER TABLE `answer_keys`
  MODIFY `answer_key_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assignments`
--
ALTER TABLE `assignments`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `class_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `correction_papers`
--
ALTER TABLE `correction_papers`
  MODIFY `correction_paper_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `exams`
--
ALTER TABLE `exams`
  MODIFY `exam_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mcq_answer_keys`
--
ALTER TABLE `mcq_answer_keys`
  MODIFY `key_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mcq_exams`
--
ALTER TABLE `mcq_exams`
  MODIFY `mcq_exam_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mcq_exam_assignments`
--
ALTER TABLE `mcq_exam_assignments`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mcq_subject_ranges`
--
ALTER TABLE `mcq_subject_ranges`
  MODIFY `range_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `papers`
--
ALTER TABLE `papers`
  MODIFY `paper_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `paper_corrections`
--
ALTER TABLE `paper_corrections`
  MODIFY `correction_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_responses`
--
ALTER TABLE `student_responses`
  MODIFY `response_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_results`
--
ALTER TABLE `student_results`
  MODIFY `result_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `subject_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `teacher_queries`
--
ALTER TABLE `teacher_queries`
  MODIFY `query_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_papers`
--
ALTER TABLE `admin_papers`
  ADD CONSTRAINT `admin_papers_fk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`exam_id`),
  ADD CONSTRAINT `admin_papers_fk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`class_id`),
  ADD CONSTRAINT `admin_papers_fk_3` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`),
  ADD CONSTRAINT `admin_papers_fk_4` FOREIGN KEY (`admin_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `answer_keys`
--
ALTER TABLE `answer_keys`
  ADD CONSTRAINT `answer_keys_fk_1` FOREIGN KEY (`paper_id`) REFERENCES `admin_papers` (`paper_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `answer_keys_fk_2` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `assignments`
--
ALTER TABLE `assignments`
  ADD CONSTRAINT `fk_assignment_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`class_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_assignment_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_assignment_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `correction_papers`
--
ALTER TABLE `correction_papers`
  ADD CONSTRAINT `fk_correction_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_correction_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`class_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_correction_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE CASCADE;

--
-- Constraints for table `mcq_answer_keys`
--
ALTER TABLE `mcq_answer_keys`
  ADD CONSTRAINT `mcq_answer_keys_ibfk_1` FOREIGN KEY (`assignment_id`) REFERENCES `mcq_exam_assignments` (`assignment_id`) ON DELETE CASCADE;

--
-- Constraints for table `mcq_exam_assignments`
--
ALTER TABLE `mcq_exam_assignments`
  ADD CONSTRAINT `mcq_exam_assignments_ibfk_1` FOREIGN KEY (`mcq_exam_id`) REFERENCES `mcq_exams` (`mcq_exam_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `mcq_exam_assignments_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`class_id`) ON DELETE CASCADE;

--
-- Constraints for table `mcq_subject_ranges`
--
ALTER TABLE `mcq_subject_ranges`
  ADD CONSTRAINT `mcq_subject_ranges_ibfk_1` FOREIGN KEY (`assignment_id`) REFERENCES `mcq_exam_assignments` (`assignment_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `mcq_subject_ranges_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_fk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `papers`
--
ALTER TABLE `papers`
  ADD CONSTRAINT `fk_paper_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`assignment_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_paper_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`exam_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_paper_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `paper_corrections`
--
ALTER TABLE `paper_corrections`
  ADD CONSTRAINT `fk_correction_paper` FOREIGN KEY (`correction_paper_id`) REFERENCES `correction_papers` (`correction_paper_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_correction_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `student_responses`
--
ALTER TABLE `student_responses`
  ADD CONSTRAINT `student_responses_ibfk_1` FOREIGN KEY (`result_id`) REFERENCES `student_results` (`result_id`) ON DELETE CASCADE;

--
-- Constraints for table `student_results`
--
ALTER TABLE `student_results`
  ADD CONSTRAINT `student_results_ibfk_1` FOREIGN KEY (`assignment_id`) REFERENCES `mcq_exam_assignments` (`assignment_id`) ON DELETE CASCADE;

--
-- Constraints for table `teacher_queries`
--
ALTER TABLE `teacher_queries`
  ADD CONSTRAINT `teacher_queries_fk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `teacher_queries_fk_2` FOREIGN KEY (`admin_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `teacher_queries_fk_3` FOREIGN KEY (`paper_id`) REFERENCES `admin_papers` (`paper_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
