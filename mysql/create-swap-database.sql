CREATE DATABASE IF NOT EXISTS `swap_db`;
USE `swap_db`;

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: 27 أبريل 2026 الساعة 21:12
-- إصدار الخادم: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `swap_db`
--

-- --------------------------------------------------------

--
-- بنية الجدول `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL COMMENT 'الاسم الثلاثي',
  `university_id` varchar(50) NOT NULL COMMENT 'الرقم الجامعي',
  `year` int(11) NOT NULL COMMENT 'السنة الدراسية (1-5)',
  `email` varchar(255) NOT NULL COMMENT 'البريد الإلكتروني',
  `password` varchar(255) NOT NULL COMMENT 'كلمة المرور مشفرة',
  `phone` varchar(20) DEFAULT NULL COMMENT 'رقم الهاتف',
  `facebook` varchar(255) DEFAULT NULL COMMENT 'حساب الفيسبوك',
  `profile_image` mediumtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'تاريخ التسجيل',
  `role` enum('student','admin') DEFAULT 'student',
  `verification_code` varchar(64) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `verification_expires` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `swap_requests`
--

CREATE TABLE `swap_requests` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL COMMENT 'رقم الطالب',
  `current_section` varchar(10) NOT NULL COMMENT 'الفئة الحالية',
  `desired_section` varchar(10) NOT NULL COMMENT 'الفئة المطلوبة',
  `status` enum('pending','matched','completed') DEFAULT 'pending' COMMENT 'حالة الطلب',
  `match_type` enum('binary','triple') DEFAULT NULL COMMENT 'نوع التطابق',
  `triple_with` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'معرفات الطلاب في التبديل الثلاثي' CHECK (json_valid(`triple_with`)),
  `request_date` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'تاريخ الطلب',
  `year` int(11) NOT NULL COMMENT 'السنة الدراسية'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `university_id` (`university_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_students_year` (`year`),
  ADD KEY `idx_students_university_id` (`university_id`);

--
-- Indexes for table `swap_requests`
--
ALTER TABLE `swap_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_requests_status` (`status`),
  ADD KEY `idx_requests_year` (`year`),
  ADD KEY `idx_requests_student` (`student_id`),
  ADD KEY `idx_requests_date` (`request_date`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `swap_requests`
--
ALTER TABLE `swap_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- قيود الجداول المُلقاة.
--

--
-- قيود الجداول `swap_requests`
--
ALTER TABLE `swap_requests`
  ADD CONSTRAINT `swap_requests_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

COMMIT;


/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;