-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:8889
-- Generation Time: Sep 02, 2026 at 05:05 AM
-- Server version: 8.0.40
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ehs_auditor_planner`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int UNSIGNED DEFAULT NULL,
  `details` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `details`, `created_at`) VALUES
(1, 1, 'seed_import', 'system', NULL, 'Initial schema + seed data loaded from 2026 Auditor Plan.xlsx', '2026-07-13 06:18:19'),
(2, 1, 'login', 'user', 1, 'User logged in', '2026-07-13 11:59:09'),
(3, 1, 'logout', 'user', 1, 'User logged out', '2026-07-13 17:39:45'),
(4, 5, 'login', 'user', 5, 'User logged in', '2026-07-13 17:40:21'),
(5, 5, 'logout', 'user', 5, 'User logged out', '2026-07-13 17:43:20'),
(6, 4, 'login', 'user', 4, 'User logged in', '2026-07-13 17:43:37'),
(7, 4, 'bulk_update_availability', 'availability', NULL, 'AM/available set for 5 date(s)', '2026-07-13 17:45:07'),
(8, 4, 'bulk_update_availability', 'availability', NULL, 'AM/available set for 10 date(s)', '2026-07-13 17:45:50'),
(9, 4, 'logout', 'user', 4, 'User logged out', '2026-07-13 17:45:55'),
(10, 1, 'login', 'user', 1, 'User logged in', '2026-07-13 17:48:44'),
(11, 1, 'create_audit', 'audit', 6, '2026-08-01 AM', '2026-07-13 18:05:44'),
(12, 1, 'logout', 'user', 1, 'User logged out', '2026-07-13 18:06:35'),
(13, 4, 'login', 'user', 4, 'User logged in', '2026-07-13 18:06:42'),
(14, 4, 'logout', 'user', 4, 'User logged out', '2026-07-13 18:17:10'),
(15, 1, 'login', 'user', 1, 'User logged in', '2026-07-13 18:17:20'),
(16, 1, 'create_audit', 'audit', 7, '2026-07-31 AM', '2026-07-13 18:18:05'),
(17, 1, 'create_audit', 'audit', 8, '2026-07-30 FULL_DAY', '2026-07-13 18:19:09'),
(18, 1, 'login', 'user', 1, 'User logged in', '2026-07-14 05:11:11'),
(19, 1, 'logout', 'user', 1, 'User logged out', '2026-07-14 05:11:31'),
(20, 4, 'login', 'user', 4, 'User logged in', '2026-07-14 05:11:41'),
(21, 4, 'logout', 'user', 4, 'User logged out', '2026-07-14 06:07:22'),
(22, 1, 'login', 'user', 1, 'User logged in', '2026-07-14 06:07:34'),
(23, 1, 'logout', 'user', 1, 'User logged out', '2026-07-14 06:25:05'),
(24, 4, 'login', 'user', 4, 'User logged in', '2026-07-14 06:25:10'),
(25, 4, 'bulk_update_availability', 'availability', NULL, 'FULL_DAY/unavailable set for 1 date(s)', '2026-07-14 06:32:24'),
(26, 4, 'logout', 'user', 4, 'User logged out', '2026-07-14 06:42:18'),
(27, 1, 'login', 'user', 1, 'User logged in', '2026-07-14 06:42:24'),
(28, 1, 'logout', 'user', 1, 'User logged out', '2026-07-14 06:44:28'),
(29, 4, 'login', 'user', 4, 'User logged in', '2026-07-14 06:48:08'),
(30, 4, 'update_audit_status', 'audit', 7, 'status=confirmed', '2026-07-14 06:48:27'),
(31, 4, 'update_audit_status', 'audit', 7, 'status=completed', '2026-07-14 06:48:30'),
(32, 4, 'logout', 'user', 4, 'User logged out', '2026-07-14 06:48:45'),
(33, 1, 'login', 'user', 1, 'User logged in', '2026-07-14 06:48:51'),
(34, 1, 'logout', 'user', 1, 'User logged out', '2026-07-14 10:11:16'),
(35, 4, 'login', 'user', 4, 'User logged in', '2026-07-14 10:11:28'),
(36, 4, 'logout', 'user', 4, 'User logged out', '2026-07-14 10:11:42'),
(37, 1, 'login', 'user', 1, 'User logged in', '2026-07-14 10:11:46'),
(38, 1, 'logout', 'user', 1, 'User logged out', '2026-07-14 10:19:33'),
(39, 4, 'login', 'user', 4, 'User logged in', '2026-07-14 10:19:38'),
(40, 4, 'logout', 'user', 4, 'User logged out', '2026-07-14 10:21:44'),
(41, 4, 'login', 'user', 4, 'User logged in', '2026-07-14 10:22:00'),
(42, 4, 'logout', 'user', 4, 'User logged out', '2026-07-14 10:32:21'),
(43, 1, 'login', 'user', 1, 'User logged in', '2026-07-14 10:32:27'),
(44, 1, 'logout', 'user', 1, 'User logged out', '2026-07-14 10:33:15'),
(45, 5, 'login', 'user', 5, 'User logged in', '2026-07-14 10:33:24'),
(46, 5, 'change_own_password', 'user', 5, 'Password changed by user', '2026-07-14 10:34:04'),
(47, 5, 'change_own_password', 'user', 5, 'Password changed by user', '2026-07-14 10:35:31'),
(48, 5, 'logout', 'user', 5, 'User logged out', '2026-07-14 10:35:37'),
(49, 5, 'login', 'user', 5, 'User logged in', '2026-07-14 10:36:00'),
(50, 5, 'change_own_password', 'user', 5, 'Password changed by user', '2026-07-14 10:36:29'),
(51, 5, 'logout', 'user', 5, 'User logged out', '2026-07-14 10:37:05'),
(52, 5, 'login', 'user', 5, 'User logged in', '2026-07-14 10:38:56'),
(53, 5, 'change_own_password', 'user', 5, 'Password changed by user', '2026-07-14 10:39:34'),
(54, 5, 'logout', 'user', 5, 'User logged out', '2026-07-14 11:00:01'),
(55, 1, 'login', 'user', 1, 'User logged in', '2026-07-14 11:00:37'),
(56, 1, 'logout', 'user', 1, 'User logged out', '2026-07-14 11:01:09'),
(57, 4, 'login', 'user', 4, 'User logged in', '2026-07-14 11:01:14'),
(58, 4, 'logout', 'user', 4, 'User logged out', '2026-07-14 11:01:17'),
(59, 1, 'login', 'user', 1, 'User logged in', '2026-07-14 11:01:30'),
(60, 1, 'logout', 'user', 1, 'User logged out', '2026-07-14 11:03:28'),
(61, 4, 'login', 'user', 4, 'User logged in', '2026-07-14 11:03:35'),
(62, 4, 'bulk_update_availability', 'availability', NULL, 'FULL_DAY/unavailable set for 10 date(s)', '2026-07-14 11:04:14'),
(63, 4, 'bulk_update_availability', 'availability', NULL, 'AM/unavailable set for 1 date(s)', '2026-07-14 11:24:26'),
(64, 4, 'bulk_update_availability', 'availability', NULL, 'AM/available set for 1 date(s)', '2026-07-14 11:24:34'),
(65, 4, 'logout', 'user', 4, 'User logged out', '2026-07-14 12:44:30'),
(66, 1, 'login', 'user', 1, 'User logged in', '2026-07-14 12:44:40'),
(67, 1, 'logout', 'user', 1, 'User logged out', '2026-07-14 12:45:05'),
(68, 4, 'login', 'user', 4, 'User logged in', '2026-07-14 12:45:11'),
(69, 4, 'logout', 'user', 4, 'User logged out', '2026-07-14 12:48:48'),
(70, 5, 'login', 'user', 5, 'User logged in', '2026-07-14 12:48:56'),
(71, 5, 'logout', 'user', 5, 'User logged out', '2026-07-14 12:49:18'),
(72, 4, 'login', 'user', 4, 'User logged in', '2026-07-14 12:49:23'),
(73, 4, 'logout', 'user', 4, 'User logged out', '2026-07-14 12:51:20'),
(74, 1, 'login', 'user', 1, 'User logged in', '2026-07-14 12:51:28'),
(75, 1, 'logout', 'user', 1, 'User logged out', '2026-07-14 12:52:50'),
(76, 4, 'login', 'user', 4, 'User logged in', '2026-07-14 12:54:27'),
(77, 1, 'login', 'user', 1, 'User logged in', '2026-07-15 13:36:45'),
(78, 1, 'create_scheme', 'scheme', 8, 'Scafolding Audit', '2026-07-15 13:41:46'),
(79, 1, 'logout', 'user', 1, 'User logged out', '2026-07-15 13:46:54'),
(80, 4, 'login', 'user', 4, 'User logged in', '2026-07-15 13:47:00'),
(81, 4, 'update_audit_status', 'audit', 6, 'status=cancelled', '2026-07-15 13:48:16'),
(82, 4, 'logout', 'user', 4, 'User logged out', '2026-07-15 13:48:20'),
(83, 1, 'login', 'user', 1, 'User logged in', '2026-07-15 13:48:25'),
(84, 1, 'update_audit', 'audit', 6, '2026-08-01 AM', '2026-07-15 13:51:19'),
(85, 1, 'update_audit', 'audit', 7, '2026-07-31 AM', '2026-07-15 13:51:38'),
(86, 1, 'login', 'user', 1, 'User logged in', '2026-07-16 04:34:02'),
(87, 1, 'login', 'user', 1, 'User logged in', '2026-07-16 13:18:13'),
(88, 1, 'update_audit', 'audit', 8, '2026-07-30 AM', '2026-07-16 13:43:39'),
(89, 1, 'create_audit', 'audit', 9, '2026-07-30 PM', '2026-07-16 13:44:03'),
(90, 1, 'logout', 'user', 1, 'User logged out', '2026-07-16 13:44:42'),
(91, 1, 'login', 'user', 1, 'User logged in', '2026-07-17 01:32:25'),
(92, 1, 'create_personal_schedule_item', 'personal_schedule_item', 1, '2026-07-17: Tech team meeting', '2026-07-17 01:33:14'),
(93, 1, 'create_audit', 'audit', 10, '2026-07-31 FULL_DAY', '2026-07-17 04:37:57'),
(94, 1, 'logout', 'user', 1, 'User logged out', '2026-07-17 04:38:40'),
(95, 5, 'login', 'user', 5, 'User logged in', '2026-07-17 04:38:47'),
(96, 5, 'bulk_update_availability', 'availability', NULL, 'FULL_DAY/available set for 5 date(s)', '2026-07-17 04:39:21'),
(97, 5, 'logout', 'user', 5, 'User logged out', '2026-07-17 04:39:30'),
(98, 5, 'login', 'user', 5, 'User logged in', '2026-07-17 04:39:40'),
(99, 1, 'login', 'user', 1, 'User logged in', '2026-07-17 04:40:06'),
(100, 5, 'bulk_update_availability', 'availability', NULL, 'AM/available set for 1 date(s)', '2026-07-17 06:10:59'),
(101, 5, 'logout', 'user', 5, 'User logged out', '2026-07-17 06:47:18'),
(102, 1, 'login', 'user', 1, 'User logged in', '2026-07-17 06:47:27'),
(103, 1, 'admin_override_availability', 'availability', 6, 'Set AM/available for 22 date(s) on behalf of Eddie (user #6)', '2026-07-17 10:27:36'),
(104, 1, 'export_pdf', 'system', NULL, '2026-07', '2026-07-17 11:10:30'),
(105, 1, 'export_pdf', 'system', NULL, '2026-07', '2026-07-17 11:10:31'),
(106, 1, 'export_pdf', 'system', NULL, '2026-08', '2026-07-17 11:10:52'),
(107, 1, 'export_pdf', 'system', NULL, '2026-08', '2026-07-17 11:10:53'),
(108, 1, 'login', 'user', 1, 'User logged in', '2026-07-20 12:25:59'),
(109, 1, 'login', 'user', 1, 'User logged in', '2026-07-21 05:41:56'),
(110, 1, 'login', 'user', 1, 'User logged in', '2026-07-23 09:47:14'),
(111, 1, 'update_auditor_profile', 'user', 16, 'status=active', '2026-07-23 09:48:03'),
(112, 1, 'login', 'user', 1, 'User logged in', '2026-07-29 11:35:45'),
(113, 1, 'login', 'user', 1, 'User logged in', '2026-07-30 01:46:48'),
(114, 1, 'login', 'user', 1, 'User logged in', '2026-08-06 07:48:22'),
(115, 1, 'login', 'user', 1, 'User logged in', '2026-08-06 09:18:00'),
(116, 1, 'login', 'user', 1, 'User logged in', '2026-08-07 09:12:21'),
(117, 1, 'login', 'user', 1, 'User logged in', '2026-08-25 13:34:36'),
(118, 1, 'login', 'user', 1, 'User logged in', '2026-08-26 05:10:31'),
(120, 1, 'logout', 'user', 1, 'User logged out', '2026-08-26 08:58:13'),
(121, 1, 'login', 'user', 1, 'User logged in', '2026-08-28 10:19:40'),
(122, 1, 'login', 'user', 1, 'User logged in', '2026-09-01 11:53:14');

-- --------------------------------------------------------

--
-- Table structure for table `auditor_schemes`
--

CREATE TABLE `auditor_schemes` (
  `auditor_id` int UNSIGNED NOT NULL,
  `scheme_id` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `auditor_schemes`
--

INSERT INTO `auditor_schemes` (`auditor_id`, `scheme_id`) VALUES
(1, 1),
(4, 1),
(5, 1),
(6, 1),
(7, 1),
(8, 1),
(9, 1),
(12, 1),
(13, 1),
(14, 1),
(1, 2),
(4, 2),
(5, 2),
(6, 2),
(7, 2),
(8, 2),
(12, 2),
(13, 2),
(14, 2),
(16, 2),
(1, 3),
(4, 3),
(5, 3),
(6, 3),
(7, 3),
(8, 3),
(9, 3),
(10, 3),
(11, 3),
(12, 3),
(13, 3),
(14, 3),
(15, 3),
(1, 4),
(5, 4),
(8, 4),
(9, 4),
(10, 4),
(11, 4),
(15, 4),
(1, 5),
(4, 5),
(5, 5),
(6, 5),
(16, 5),
(5, 6),
(8, 6),
(9, 6),
(10, 6);

-- --------------------------------------------------------

--
-- Table structure for table `audits`
--

CREATE TABLE `audits` (
  `id` int UNSIGNED NOT NULL,
  `client_id` int UNSIGNED NOT NULL,
  `audit_date` date NOT NULL,
  `session` enum('AM','PM','FULL_DAY') NOT NULL,
  `status` enum('scheduled','confirmed','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  `location` varchar(255) DEFAULT NULL,
  `notes` text,
  `created_by` int UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `audits`
--

INSERT INTO `audits` (`id`, `client_id`, `audit_date`, `session`, `status`, `location`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, '2026-01-02', 'FULL_DAY', 'completed', NULL, 'AM Aspire Food NTUC CK Transport PM', 1, '2026-07-13 06:18:19', '2026-07-13 06:18:19'),
(2, 4, '2026-01-07', 'AM', 'completed', NULL, 'Green Cosmos', 1, '2026-07-13 06:18:19', '2026-07-13 06:18:19'),
(3, 6, '2026-01-16', 'FULL_DAY', 'completed', NULL, 'Soverus Kingdom MXT RM Audit', 1, '2026-07-13 06:18:19', '2026-07-13 06:18:19'),
(4, 13, '2026-01-23', 'FULL_DAY', 'confirmed', NULL, 'ROBERT BOSCH RM Audit / 45001 Stage 2', 1, '2026-07-13 06:18:19', '2026-07-13 06:18:19'),
(5, 8, '2026-01-29', 'FULL_DAY', 'confirmed', NULL, 'All Best', 1, '2026-07-13 06:18:19', '2026-07-13 06:18:19'),
(6, 16, '2026-08-01', 'AM', 'cancelled', NULL, NULL, 1, '2026-07-13 18:05:44', '2026-07-15 13:48:16'),
(7, 5, '2026-07-31', 'AM', 'completed', NULL, NULL, 1, '2026-07-13 18:18:05', '2026-07-14 06:48:30'),
(8, 8, '2026-07-30', 'AM', 'scheduled', NULL, NULL, 1, '2026-07-13 18:19:09', '2026-07-16 13:43:39'),
(9, 18, '2026-07-30', 'PM', 'scheduled', NULL, NULL, 1, '2026-07-16 13:44:03', '2026-07-16 13:44:03'),
(10, 18, '2026-07-31', 'FULL_DAY', 'scheduled', NULL, NULL, 1, '2026-07-17 04:37:57', '2026-07-17 04:37:57');

-- --------------------------------------------------------

--
-- Table structure for table `audit_auditors`
--

CREATE TABLE `audit_auditors` (
  `audit_id` int UNSIGNED NOT NULL,
  `auditor_id` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `audit_auditors`
--

INSERT INTO `audit_auditors` (`audit_id`, `auditor_id`) VALUES
(1, 1),
(2, 1),
(3, 1),
(4, 1),
(5, 1),
(4, 4),
(5, 4),
(6, 5),
(7, 5),
(2, 6),
(3, 6),
(5, 6),
(8, 9),
(9, 9),
(5, 12),
(10, 12);

-- --------------------------------------------------------

--
-- Table structure for table `audit_schemes`
--

CREATE TABLE `audit_schemes` (
  `audit_id` int UNSIGNED NOT NULL,
  `scheme_id` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `audit_schemes`
--

INSERT INTO `audit_schemes` (`audit_id`, `scheme_id`) VALUES
(6, 1),
(8, 1),
(6, 2),
(7, 2),
(8, 2),
(9, 2),
(10, 2),
(4, 3),
(6, 3),
(8, 3),
(9, 3),
(3, 6),
(4, 6);

-- --------------------------------------------------------

--
-- Table structure for table `availability`
--

CREATE TABLE `availability` (
  `id` int UNSIGNED NOT NULL,
  `auditor_id` int UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `session` enum('AM','PM','FULL_DAY') NOT NULL,
  `status` enum('available','unavailable','tentative') NOT NULL DEFAULT 'available',
  `note` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `availability`
--

INSERT INTO `availability` (`id`, `auditor_id`, `date`, `session`, `status`, `note`, `updated_at`) VALUES
(1, 1, '2026-01-03', 'FULL_DAY', 'tentative', 'Personal work 11AM SP', '2026-07-13 06:18:19'),
(2, 1, '2026-01-05', 'AM', 'unavailable', 'Personal work 11am BYD', '2026-07-13 06:18:19'),
(3, 1, '2026-01-12', 'PM', 'tentative', 'IAS Meeting 6 to 6:30PM', '2026-07-13 06:18:19'),
(4, 1, '2026-01-13', 'AM', 'unavailable', '3:30 to 4:30 NUS', '2026-07-13 06:18:19'),
(5, 4, '2026-01-09', 'FULL_DAY', 'available', NULL, '2026-07-13 06:18:19'),
(6, 5, '2026-01-09', 'FULL_DAY', 'available', NULL, '2026-07-13 06:18:19'),
(7, 4, '2026-07-20', 'AM', 'available', NULL, '2026-07-13 17:45:07'),
(8, 4, '2026-07-21', 'AM', 'available', NULL, '2026-07-13 17:45:07'),
(9, 4, '2026-07-22', 'AM', 'available', NULL, '2026-07-13 17:45:07'),
(10, 4, '2026-07-23', 'AM', 'available', NULL, '2026-07-13 17:45:07'),
(11, 4, '2026-07-24', 'AM', 'available', NULL, '2026-07-13 17:45:07'),
(12, 4, '2026-08-03', 'AM', 'available', NULL, '2026-07-13 17:45:50'),
(13, 4, '2026-08-04', 'AM', 'available', NULL, '2026-07-13 17:45:50'),
(14, 4, '2026-08-05', 'AM', 'available', NULL, '2026-07-13 17:45:50'),
(15, 4, '2026-08-06', 'AM', 'available', NULL, '2026-07-13 17:45:50'),
(16, 4, '2026-08-07', 'AM', 'available', NULL, '2026-07-13 17:45:50'),
(17, 4, '2026-08-10', 'AM', 'available', NULL, '2026-07-13 17:45:50'),
(18, 4, '2026-08-11', 'AM', 'available', NULL, '2026-07-13 17:45:50'),
(19, 4, '2026-08-12', 'AM', 'available', NULL, '2026-07-13 17:45:50'),
(20, 4, '2026-08-13', 'AM', 'available', NULL, '2026-07-13 17:45:50'),
(21, 4, '2026-08-14', 'AM', 'available', NULL, '2026-07-13 17:45:50'),
(22, 4, '2026-07-15', 'FULL_DAY', 'unavailable', NULL, '2026-07-14 06:32:24'),
(23, 4, '2026-07-01', 'FULL_DAY', 'unavailable', NULL, '2026-07-14 11:04:14'),
(25, 4, '2026-07-03', 'FULL_DAY', 'unavailable', NULL, '2026-07-14 11:04:14'),
(26, 4, '2026-07-04', 'FULL_DAY', 'unavailable', NULL, '2026-07-14 11:04:14'),
(27, 4, '2026-07-05', 'FULL_DAY', 'unavailable', NULL, '2026-07-14 11:04:14'),
(28, 4, '2026-07-06', 'FULL_DAY', 'unavailable', NULL, '2026-07-14 11:04:14'),
(29, 4, '2026-07-07', 'FULL_DAY', 'unavailable', NULL, '2026-07-14 11:04:14'),
(30, 4, '2026-07-08', 'FULL_DAY', 'unavailable', NULL, '2026-07-14 11:04:14'),
(31, 4, '2026-07-09', 'FULL_DAY', 'unavailable', NULL, '2026-07-14 11:04:14'),
(32, 4, '2026-07-10', 'FULL_DAY', 'unavailable', NULL, '2026-07-14 11:04:14'),
(33, 4, '2026-07-02', 'AM', 'available', NULL, '2026-07-14 11:24:34'),
(35, 5, '2026-07-20', 'FULL_DAY', 'available', NULL, '2026-07-17 04:39:21'),
(36, 5, '2026-07-21', 'FULL_DAY', 'available', NULL, '2026-07-17 04:39:21'),
(38, 5, '2026-07-23', 'FULL_DAY', 'available', NULL, '2026-07-17 04:39:21'),
(39, 5, '2026-07-24', 'FULL_DAY', 'available', NULL, '2026-07-17 04:39:21'),
(40, 5, '2026-07-22', 'AM', 'available', NULL, '2026-07-17 06:10:59'),
(41, 6, '2026-07-01', 'AM', 'available', NULL, '2026-07-17 10:27:36'),
(42, 6, '2026-07-02', 'AM', 'available', NULL, '2026-07-17 10:27:36'),
(43, 6, '2026-07-03', 'AM', 'available', NULL, '2026-07-17 10:27:36'),
(44, 6, '2026-07-06', 'AM', 'available', NULL, '2026-07-17 10:27:36'),
(45, 6, '2026-07-07', 'AM', 'available', NULL, '2026-07-17 10:27:36'),
(46, 6, '2026-07-08', 'AM', 'available', NULL, '2026-07-17 10:27:36'),
(47, 6, '2026-07-09', 'AM', 'available', NULL, '2026-07-17 10:27:36'),
(48, 6, '2026-07-10', 'AM', 'available', NULL, '2026-07-17 10:27:36'),
(49, 6, '2026-07-13', 'AM', 'available', NULL, '2026-07-17 10:27:36'),
(50, 6, '2026-07-14', 'AM', 'available', NULL, '2026-07-17 10:27:36'),
(51, 6, '2026-07-15', 'AM', 'available', NULL, '2026-07-17 10:27:36'),
(52, 6, '2026-07-16', 'AM', 'available', NULL, '2026-07-17 10:27:36'),
(53, 6, '2026-07-24', 'AM', 'available', NULL, '2026-07-17 10:27:36'),
(54, 6, '2026-07-23', 'AM', 'available', NULL, '2026-07-17 10:27:36'),
(55, 6, '2026-07-22', 'AM', 'available', NULL, '2026-07-17 10:27:36'),
(56, 6, '2026-07-21', 'AM', 'available', NULL, '2026-07-17 10:27:36'),
(57, 6, '2026-07-20', 'AM', 'available', NULL, '2026-07-17 10:27:36'),
(58, 6, '2026-07-27', 'AM', 'available', NULL, '2026-07-17 10:27:36'),
(59, 6, '2026-07-28', 'AM', 'available', NULL, '2026-07-17 10:27:36'),
(60, 6, '2026-07-29', 'AM', 'available', NULL, '2026-07-17 10:27:36'),
(61, 6, '2026-07-30', 'AM', 'available', NULL, '2026-07-17 10:27:36'),
(62, 6, '2026-07-31', 'AM', 'available', NULL, '2026-07-17 10:27:36');

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `notes` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`id`, `name`, `notes`) VALUES
(1, 'Aspire Food', NULL),
(2, 'NTUC', NULL),
(3, 'CK Transport', NULL),
(4, 'Green Cosmos', NULL),
(5, 'AKTIO', NULL),
(6, 'Soverus Kingdom', NULL),
(7, 'YJME', NULL),
(8, 'ALLBEST', NULL),
(9, 'LDC', NULL),
(10, 'Practical Analyzer', NULL),
(11, 'CHCT', NULL),
(12, 'BEST NDT', NULL),
(13, 'Robert Bosch', NULL),
(14, 'Zublin', NULL),
(15, 'Silver Seal Construction', NULL),
(16, 'Thong Hup', NULL),
(17, 'Gliderol', NULL),
(18, 'LYC Hardware', NULL),
(19, 'ALTROCKS', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `cm_activity_log`
--

CREATE TABLE `cm_activity_log` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int UNSIGNED DEFAULT NULL,
  `details` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `cm_activity_log`
--

INSERT INTO `cm_activity_log` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `details`, `created_at`) VALUES
(1, 1, 'export_xlsx', 'system', NULL, 'Exported 0 row(s)', '2026-07-29 12:22:06'),
(2, 1, 'export_xlsx', 'system', NULL, 'Exported 0 row(s)', '2026-07-29 12:22:06'),
(3, 1, 'bulk_import_client', 'cm_client', 1, 'N Grace Builders Pte Ltd', '2026-08-06 09:11:30'),
(4, 1, 'bulk_import_certification', 'cm_certification', 1, 'N Grace Builders Pte Ltd', '2026-08-06 09:11:30'),
(5, 1, 'bulk_import_client', 'cm_client', 2, 'AI Analytical Pte Ltd', '2026-08-06 09:11:30'),
(6, 1, 'bulk_import_certification', 'cm_certification', 2, 'AI Analytical Pte Ltd', '2026-08-06 09:11:30'),
(7, 1, 'bulk_import_client', 'cm_client', 3, 'BSHK Logistics & Shipping Pte Ltd', '2026-08-06 09:11:30'),
(8, 1, 'bulk_import_certification', 'cm_certification', 3, 'BSHK Logistics & Shipping Pte Ltd', '2026-08-06 09:11:30'),
(9, 1, 'bulk_import_certification', 'cm_certification', 4, 'BSHK Logistics & Shipping Pte Ltd', '2026-08-06 09:11:30'),
(10, 1, 'bulk_import_client', 'cm_client', 4, 'Chin Siong Electrical Engineering Pte Ltd', '2026-08-06 09:11:30'),
(11, 1, 'bulk_import_certification', 'cm_certification', 5, 'Chin Siong Electrical Engineering Pte Ltd', '2026-08-06 09:11:30'),
(12, 1, 'bulk_import_client', 'cm_client', 5, 'Y&G System IntegrationPte Ltd', '2026-08-06 09:11:30'),
(13, 1, 'bulk_import_certification', 'cm_certification', 6, 'Y&G System IntegrationPte Ltd', '2026-08-06 09:11:30'),
(14, 1, 'bulk_import_client', 'cm_client', 6, 'A+ Officers Security Pte Ltd', '2026-08-06 09:11:30'),
(15, 1, 'bulk_import_certification', 'cm_certification', 7, 'A+ Officers Security Pte Ltd', '2026-08-06 09:11:30'),
(16, 1, 'bulk_import_client', 'cm_client', 7, 'M2M Construction Pte Ltd', '2026-08-06 09:11:30'),
(17, 1, 'bulk_import_certification', 'cm_certification', 8, 'M2M Construction Pte Ltd', '2026-08-06 09:11:30'),
(18, 1, 'bulk_import_client', 'cm_client', 8, 'Heng Xin Construction Pte Ltd', '2026-08-06 09:11:30'),
(19, 1, 'bulk_import_certification', 'cm_certification', 9, 'Heng Xin Construction Pte Ltd', '2026-08-06 09:11:30'),
(20, 1, 'bulk_import_client', 'cm_client', 9, 'Acqual Engineering LLP', '2026-08-06 09:11:30'),
(21, 1, 'bulk_import_certification', 'cm_certification', 10, 'Acqual Engineering LLP', '2026-08-06 09:11:30'),
(22, 1, 'bulk_import_client', 'cm_client', 10, 'WLK Engineering Pte Ltd', '2026-08-06 09:11:30'),
(23, 1, 'bulk_import_certification', 'cm_certification', 11, 'WLK Engineering Pte Ltd', '2026-08-06 09:11:30'),
(24, 1, 'bulk_import_certification', 'cm_certification', 12, 'WLK Engineering Pte Ltd', '2026-08-06 09:11:30'),
(25, 1, 'bulk_import_client', 'cm_client', 11, 'VE Coat Pte Ltd', '2026-08-06 09:11:30'),
(26, 1, 'bulk_import_certification', 'cm_certification', 13, 'VE Coat Pte Ltd', '2026-08-06 09:11:30'),
(27, 1, 'bulk_import_certification', 'cm_certification', 14, 'VE Coat Pte Ltd', '2026-08-06 09:11:30'),
(28, 1, 'bulk_import_client', 'cm_client', 12, 'PRM Engineering Pte Ltd', '2026-08-06 09:11:30'),
(29, 1, 'bulk_import_certification', 'cm_certification', 15, 'PRM Engineering Pte Ltd', '2026-08-06 09:11:30'),
(30, 1, 'bulk_import_certification', 'cm_certification', 16, 'PRM Engineering Pte Ltd', '2026-08-06 09:11:30'),
(31, 1, 'bulk_import_certification', 'cm_certification', 17, 'PRM Engineering Pte Ltd', '2026-08-06 09:11:30'),
(32, 1, 'bulk_import_client', 'cm_client', 13, 'Ngee Hong Metal Engineering (S) Pte Ltd', '2026-08-06 09:11:30'),
(33, 1, 'bulk_import_certification', 'cm_certification', 18, 'Ngee Hong Metal Engineering (S) Pte Ltd', '2026-08-06 09:11:30'),
(34, 1, 'bulk_import_client', 'cm_client', 14, '8ptitude Private Limited', '2026-08-06 09:11:30'),
(35, 1, 'bulk_import_certification', 'cm_certification', 19, '8ptitude Private Limited', '2026-08-06 09:11:30'),
(36, 1, 'bulk_import_client', 'cm_client', 15, 'RMS Integrated Pte Ltd', '2026-08-06 09:11:30'),
(37, 1, 'bulk_import_certification', 'cm_certification', 20, 'RMS Integrated Pte Ltd', '2026-08-06 09:11:30'),
(38, 1, 'bulk_import_client', 'cm_client', 16, 'Metro Plastic Manufacturer Sdn Bhd', '2026-08-06 09:11:30'),
(39, 1, 'bulk_import_certification', 'cm_certification', 21, 'Metro Plastic Manufacturer Sdn Bhd', '2026-08-06 09:11:30'),
(40, 1, 'bulk_import_certification', 'cm_certification', 22, 'Metro Plastic Manufacturer Sdn Bhd', '2026-08-06 09:11:30'),
(41, 1, 'bulk_import_client', 'cm_client', 17, 'Ma Yi Group Pte Ltd', '2026-08-06 09:11:30'),
(42, 1, 'bulk_import_certification', 'cm_certification', 23, 'Ma Yi Group Pte Ltd', '2026-08-06 09:11:30'),
(43, 1, 'bulk_import_client', 'cm_client', 18, 'Kingsley Interior Pte Ltd', '2026-08-06 09:11:30'),
(44, 1, 'bulk_import_certification', 'cm_certification', 24, 'Kingsley Interior Pte Ltd', '2026-08-06 09:11:30'),
(45, 1, 'bulk_import_client', 'cm_client', 19, 'Kee Safety Singapore Pte Ltd', '2026-08-06 09:11:30'),
(46, 1, 'bulk_import_certification', 'cm_certification', 25, 'Kee Safety Singapore Pte Ltd', '2026-08-06 09:11:30'),
(47, 1, 'bulk_import_client', 'cm_client', 20, 'Kah Teck Hardware Trading Pte Ltd', '2026-08-06 09:11:30'),
(48, 1, 'bulk_import_certification', 'cm_certification', 26, 'Kah Teck Hardware Trading Pte Ltd', '2026-08-06 09:11:30'),
(49, 1, 'bulk_import_certification', 'cm_certification', 27, 'Kah Teck Hardware Trading Pte Ltd', '2026-08-06 09:11:30'),
(50, 1, 'bulk_import_certification', 'cm_certification', 28, 'Kah Teck Hardware Trading Pte Ltd', '2026-08-06 09:11:30'),
(51, 1, 'bulk_import_client', 'cm_client', 21, 'ID Haus Pte Ltd', '2026-08-06 09:11:30'),
(52, 1, 'bulk_import_certification', 'cm_certification', 29, 'ID Haus Pte Ltd', '2026-08-06 09:11:30'),
(53, 1, 'bulk_import_client', 'cm_client', 22, 'Elitesia Forwarders Pte Ltd', '2026-08-06 09:11:30'),
(54, 1, 'bulk_import_certification', 'cm_certification', 30, 'Elitesia Forwarders Pte Ltd', '2026-08-06 09:11:30'),
(55, 1, 'bulk_import_certification', 'cm_certification', 31, 'Elitesia Forwarders Pte Ltd', '2026-08-06 09:11:30'),
(56, 1, 'bulk_import_client', 'cm_client', 23, 'Asia Tec Services Pte Ltd', '2026-08-06 09:11:30'),
(57, 1, 'bulk_import_certification', 'cm_certification', 32, 'Asia Tec Services Pte Ltd', '2026-08-06 09:11:30'),
(58, 1, 'bulk_import_client', 'cm_client', 24, 'Allied Kinsmen Facility Management Pte Ltd', '2026-08-06 09:11:30'),
(59, 1, 'bulk_import_certification', 'cm_certification', 33, 'Allied Kinsmen Facility Management Pte Ltd', '2026-08-06 09:11:30'),
(60, 1, 'bulk_import_client', 'cm_client', 25, 'YGS Energy Engineering Pte Ltd', '2026-08-06 09:11:30'),
(61, 1, 'bulk_import_certification', 'cm_certification', 34, 'YGS Energy Engineering Pte Ltd', '2026-08-06 09:11:30'),
(62, 1, 'bulk_import_certification', 'cm_certification', 35, 'YGS Energy Engineering Pte Ltd', '2026-08-06 09:11:30'),
(63, 1, 'bulk_import_client', 'cm_client', 26, 'The Akron (S) Pte Ltd', '2026-08-06 09:11:30'),
(64, 1, 'bulk_import_certification', 'cm_certification', 36, 'The Akron (S) Pte Ltd', '2026-08-06 09:11:30'),
(65, 1, 'bulk_import_certification', 'cm_certification', 37, 'The Akron (S) Pte Ltd', '2026-08-06 09:11:30'),
(66, 1, 'bulk_import_client', 'cm_client', 27, 'Mecgro Engineering & Construction Pte Ltd', '2026-08-06 09:11:30'),
(67, 1, 'bulk_import_certification', 'cm_certification', 38, 'Mecgro Engineering & Construction Pte Ltd', '2026-08-06 09:11:30'),
(68, 1, 'bulk_import_client', 'cm_client', 28, 'Mastron Pte Ltd', '2026-08-06 09:11:30'),
(69, 1, 'bulk_import_certification', 'cm_certification', 39, 'Mastron Pte Ltd', '2026-08-06 09:11:30'),
(70, 1, 'bulk_import_client', 'cm_client', 29, 'Lisa Engineering Pte Ltd', '2026-08-06 09:11:30'),
(71, 1, 'bulk_import_certification', 'cm_certification', 40, 'Lisa Engineering Pte Ltd', '2026-08-06 09:11:30'),
(72, 1, 'bulk_import_client', 'cm_client', 30, 'AKK Th3X Pte Ltd', '2026-08-06 09:11:30'),
(73, 1, 'bulk_import_certification', 'cm_certification', 41, 'AKK Th3X Pte Ltd', '2026-08-06 09:11:30'),
(74, 1, 'bulk_import_certification', 'cm_certification', 42, 'AKK Th3X Pte Ltd', '2026-08-06 09:11:30'),
(75, 1, 'bulk_import_certification', 'cm_certification', 43, 'AKK Th3X Pte Ltd', '2026-08-06 09:11:30'),
(76, 1, 'bulk_import_client', 'cm_client', 31, 'Unisteel Engineering Pte Ltd', '2026-08-06 09:11:30'),
(77, 1, 'bulk_import_certification', 'cm_certification', 44, 'Unisteel Engineering Pte Ltd', '2026-08-06 09:11:30'),
(78, 1, 'bulk_import_client', 'cm_client', 32, 'Tata Builders (S) Pte Ltd', '2026-08-06 09:11:30'),
(79, 1, 'bulk_import_certification', 'cm_certification', 45, 'Tata Builders (S) Pte Ltd', '2026-08-06 09:11:30'),
(80, 1, 'bulk_import_client', 'cm_client', 33, 'On Time Engineering Pte Ltd', '2026-08-06 09:11:30'),
(81, 1, 'bulk_import_certification', 'cm_certification', 46, 'On Time Engineering Pte Ltd', '2026-08-06 09:11:30'),
(82, 1, 'bulk_import_certification', 'cm_certification', 47, 'On Time Engineering Pte Ltd', '2026-08-06 09:11:30'),
(83, 1, 'bulk_import_client', 'cm_client', 34, 'LV Engineering Pte Ltd', '2026-08-06 09:11:30'),
(84, 1, 'bulk_import_certification', 'cm_certification', 48, 'LV Engineering Pte Ltd', '2026-08-06 09:11:30'),
(85, 1, 'bulk_import_client', 'cm_client', 35, 'Imperial Services Pte Ltd', '2026-08-06 09:11:30'),
(86, 1, 'bulk_import_certification', 'cm_certification', 49, 'Imperial Services Pte Ltd', '2026-08-06 09:11:30'),
(87, 1, 'bulk_import_client', 'cm_client', 36, 'GRK Marine Services Pte Ltd', '2026-08-06 09:11:30'),
(88, 1, 'bulk_import_certification', 'cm_certification', 50, 'GRK Marine Services Pte Ltd', '2026-08-06 09:11:30'),
(89, 1, 'bulk_import_client', 'cm_client', 37, 'Archilite Engineering Pte Ltd', '2026-08-06 09:11:30'),
(90, 1, 'bulk_import_certification', 'cm_certification', 51, 'Archilite Engineering Pte Ltd', '2026-08-06 09:11:30'),
(91, 1, 'bulk_import_client', 'cm_client', 38, 'ACS Process Control Pte Ltd', '2026-08-06 09:11:30'),
(92, 1, 'bulk_import_certification', 'cm_certification', 52, 'ACS Process Control Pte Ltd', '2026-08-06 09:11:30'),
(93, 1, 'bulk_import_client', 'cm_client', 39, 'HUP HIN TRANSPORT CO PTE LTD 9k 45k Surv', '2026-08-06 09:11:30'),
(94, 1, 'bulk_import_certification', 'cm_certification', 53, 'HUP HIN TRANSPORT CO PTE LTD 9k 45k Surv', '2026-08-06 09:11:30'),
(95, 1, 'bulk_import_certification', 'cm_certification', 54, 'HUP HIN TRANSPORT CO PTE LTD 9k 45k Surv', '2026-08-06 09:11:30'),
(96, 1, 'bulk_import_client', 'cm_client', 40, 'HUP HIN HEAVY EQUIPMENT PTE LTD 9k 45 Surv', '2026-08-06 09:11:30'),
(97, 1, 'bulk_import_certification', 'cm_certification', 55, 'HUP HIN HEAVY EQUIPMENT PTE LTD 9k 45 Surv', '2026-08-06 09:11:30'),
(98, 1, 'bulk_import_certification', 'cm_certification', 56, 'HUP HIN HEAVY EQUIPMENT PTE LTD 9k 45 Surv', '2026-08-06 09:11:30'),
(99, 1, 'bulk_import_client', 'cm_client', 41, 'GREENSAFE INTERNATIONAL - ISO 37001 New', '2026-08-06 09:11:30'),
(100, 1, 'bulk_import_certification', 'cm_certification', 57, 'GREENSAFE INTERNATIONAL - ISO 37001 New', '2026-08-06 09:11:30'),
(101, 1, 'bulk_import_client', 'cm_client', 42, 'BESCO BUILDING SUPPLIES - ISO 37001 New', '2026-08-06 09:11:30'),
(102, 1, 'bulk_import_certification', 'cm_certification', 58, 'BESCO BUILDING SUPPLIES - ISO 37001 New', '2026-08-06 09:11:30'),
(103, 1, 'bulk_import_client', 'cm_client', 43, 'BENKEL INTERNATIONAL - ISO 37001 New', '2026-08-06 09:11:30'),
(104, 1, 'bulk_import_certification', 'cm_certification', 59, 'BENKEL INTERNATIONAL - ISO 37001 New', '2026-08-06 09:11:30'),
(105, 1, 'bulk_import_client', 'cm_client', 44, 'YEW HOCK MARINE ENGINEERING PTE LTD 45k Surv', '2026-08-06 09:11:30'),
(106, 1, 'bulk_import_certification', 'cm_certification', 60, 'YEW HOCK MARINE ENGINEERING PTE LTD 45k Surv', '2026-08-06 09:11:30'),
(107, 1, 'bulk_import_client', 'cm_client', 45, 'YSH ENGINEERING PTE. LTD 45k Surv', '2026-08-06 09:11:30'),
(108, 1, 'bulk_import_certification', 'cm_certification', 61, 'YSH ENGINEERING PTE. LTD 45k Surv', '2026-08-06 09:11:30'),
(109, 1, 'bulk_import_client', 'cm_client', 46, 'CABLETRONIC SYSTEM PTE LTD ISO 45k Surv', '2026-08-06 09:11:30'),
(110, 1, 'bulk_import_certification', 'cm_certification', 62, 'CABLETRONIC SYSTEM PTE LTD ISO 45k Surv', '2026-08-06 09:11:30'),
(111, 1, 'bulk_import_client', 'cm_client', 47, 'Jian Bang Construction Surv 1 45k', '2026-08-06 09:11:30'),
(112, 1, 'bulk_import_certification', 'cm_certification', 63, 'Jian Bang Construction Surv 1 45k', '2026-08-06 09:11:30'),
(113, 1, 'bulk_import_client', 'cm_client', 48, 'SAAMCO MAINTENANCE PTE. LTD. 45k Surv', '2026-08-06 09:11:30'),
(114, 1, 'bulk_import_certification', 'cm_certification', 64, 'SAAMCO MAINTENANCE PTE. LTD. 45k Surv', '2026-08-06 09:11:30'),
(115, 1, 'bulk_import_client', 'cm_client', 49, 'Ngee Hong Metal 45k New', '2026-08-06 09:11:30'),
(116, 1, 'bulk_import_certification', 'cm_certification', 65, 'Ngee Hong Metal 45k New', '2026-08-06 09:11:30'),
(117, 1, 'bulk_import_client', 'cm_client', 50, 'Digitalbuild ISO 27001 Sur', '2026-08-06 09:11:30'),
(118, 1, 'bulk_import_certification', 'cm_certification', 66, 'Digitalbuild ISO 27001 Sur', '2026-08-06 09:11:30'),
(119, 1, 'bulk_import_client', 'cm_client', 51, 'SRIRAM ENGINEERING & Construction Pte Ltd', '2026-08-06 09:11:30'),
(120, 1, 'bulk_import_certification', 'cm_certification', 67, 'SRIRAM ENGINEERING & Construction Pte Ltd', '2026-08-06 09:11:30'),
(121, 1, 'bulk_import_client', 'cm_client', 52, 'VPM Engineering Pte Ltd 45k Initial Audit', '2026-08-06 09:11:30'),
(122, 1, 'bulk_import_certification', 'cm_certification', 68, 'VPM Engineering Pte Ltd 45k Initial Audit', '2026-08-06 09:11:30'),
(123, 1, 'bulk_import_client', 'cm_client', 53, 'Kee Safety Singapore - 45k Surv', '2026-08-06 09:11:30'),
(124, 1, 'bulk_import_certification', 'cm_certification', 69, 'Kee Safety Singapore - 45k Surv', '2026-08-06 09:11:30'),
(125, 1, 'bulk_import_client', 'cm_client', 54, 'Eversafe Academy Pte Ltd - 45k Surv', '2026-08-06 09:11:30'),
(126, 1, 'bulk_import_certification', 'cm_certification', 70, 'Eversafe Academy Pte Ltd - 45k Surv', '2026-08-06 09:11:30'),
(127, 1, 'bulk_import_client', 'cm_client', 55, 'LSN Engineering 45k Initial Year', '2026-08-06 09:11:30'),
(128, 1, 'bulk_import_certification', 'cm_certification', 71, 'LSN Engineering 45k Initial Year', '2026-08-06 09:11:30'),
(129, 1, 'bulk_import_client', 'cm_client', 56, 'Ma Yi Group 45k Surv', '2026-08-06 09:11:30'),
(130, 1, 'bulk_import_certification', 'cm_certification', 72, 'Ma Yi Group 45k Surv', '2026-08-06 09:11:30'),
(131, 1, 'bulk_import_client', 'cm_client', 57, 'HG Technologies 9k Surv', '2026-08-06 09:11:30'),
(132, 1, 'bulk_import_certification', 'cm_certification', 73, 'HG Technologies 9k Surv', '2026-08-06 09:11:30'),
(133, 1, 'bulk_import_client', 'cm_client', 58, 'Kah Teck Hardware Trading Pte Ltd ISO 9k 14k 45k Surv', '2026-08-06 09:11:30'),
(134, 1, 'bulk_import_certification', 'cm_certification', 74, 'Kah Teck Hardware Trading Pte Ltd ISO 9k 14k 45k Surv', '2026-08-06 09:11:30'),
(135, 1, 'bulk_import_certification', 'cm_certification', 75, 'Kah Teck Hardware Trading Pte Ltd ISO 9k 14k 45k Surv', '2026-08-06 09:11:30'),
(136, 1, 'bulk_import_certification', 'cm_certification', 76, 'Kah Teck Hardware Trading Pte Ltd ISO 9k 14k 45k Surv', '2026-08-06 09:11:30'),
(137, 1, 'bulk_import_client', 'cm_client', 59, 'THE AKRON 14k 45k Surv', '2026-08-06 09:11:30'),
(138, 1, 'bulk_import_certification', 'cm_certification', 77, 'THE AKRON 14k 45k Surv', '2026-08-06 09:11:30'),
(139, 1, 'bulk_import_certification', 'cm_certification', 78, 'THE AKRON 14k 45k Surv', '2026-08-06 09:11:30'),
(140, 1, 'bulk_import_client', 'cm_client', 60, 'MW Trading & Recycling Pte Ltd 14k Surv', '2026-08-06 09:11:30'),
(141, 1, 'bulk_import_certification', 'cm_certification', 79, 'MW Trading & Recycling Pte Ltd 14k Surv', '2026-08-06 09:11:30'),
(142, 1, 'bulk_import_client', 'cm_client', 61, 'MNL SOLUTIONS PTE. LTD. 45k Surv', '2026-08-06 09:11:30'),
(143, 1, 'bulk_import_certification', 'cm_certification', 80, 'MNL SOLUTIONS PTE. LTD. 45k Surv', '2026-08-06 09:11:30'),
(144, 1, 'bulk_import_client', 'cm_client', 62, 'BSHK Logistics & Shipping Pte Ltd 9k 45k Surv', '2026-08-06 09:11:30'),
(145, 1, 'bulk_import_certification', 'cm_certification', 81, 'BSHK Logistics & Shipping Pte Ltd 9k 45k Surv', '2026-08-06 09:11:30'),
(146, 1, 'bulk_import_certification', 'cm_certification', 82, 'BSHK Logistics & Shipping Pte Ltd 9k 45k Surv', '2026-08-06 09:11:30'),
(147, 1, 'bulk_import_client', 'cm_client', 63, 'International Cleaning & Building Services 9k 45k Surv', '2026-08-06 09:11:30'),
(148, 1, 'bulk_import_certification', 'cm_certification', 83, 'International Cleaning & Building Services 9k 45k Surv', '2026-08-06 09:11:30'),
(149, 1, 'bulk_import_certification', 'cm_certification', 84, 'International Cleaning & Building Services 9k 45k Surv', '2026-08-06 09:11:30'),
(150, 1, 'bulk_import_client', 'cm_client', 64, 'Wide Wings Pte Ltd 37 Surv', '2026-08-06 09:11:30'),
(151, 1, 'bulk_import_certification', 'cm_certification', 85, 'Wide Wings Pte Ltd 37 Surv', '2026-08-06 09:11:30'),
(152, 1, 'bulk_import_client', 'cm_client', 65, 'QUALITY M&E PTE. LTD. 45k Surv', '2026-08-06 09:11:30'),
(153, 1, 'bulk_import_certification', 'cm_certification', 86, 'QUALITY M&E PTE. LTD. 45k Surv', '2026-08-06 09:11:30'),
(154, 1, 'bulk_import_client', 'cm_client', 66, 'Tata Builders (S) Pte Ltd 45k Surv', '2026-08-06 09:11:30'),
(155, 1, 'bulk_import_certification', 'cm_certification', 87, 'Tata Builders (S) Pte Ltd 45k Surv', '2026-08-06 09:11:30'),
(156, 1, 'bulk_import_client', 'cm_client', 67, 'ARCHILITE ENGINEERING PTE LTD 45k Surv', '2026-08-06 09:11:30'),
(157, 1, 'bulk_import_certification', 'cm_certification', 88, 'ARCHILITE ENGINEERING PTE LTD 45k Surv', '2026-08-06 09:11:30'),
(158, 1, 'bulk_import_client', 'cm_client', 68, 'ADRI OFFSHORE AND MARINE 45k Surv', '2026-08-06 09:11:30'),
(159, 1, 'bulk_import_certification', 'cm_certification', 89, 'ADRI OFFSHORE AND MARINE 45k Surv', '2026-08-06 09:11:30'),
(160, 1, 'bulk_import_client', 'cm_client', 69, 'LISA Engineering Pte Ltd ISO 45k Surv', '2026-08-06 09:11:30'),
(161, 1, 'bulk_import_certification', 'cm_certification', 90, 'LISA Engineering Pte Ltd ISO 45k Surv', '2026-08-06 09:11:30'),
(162, 1, 'bulk_import_client', 'cm_client', 70, 'SG Building Contractors Pte Ltd ISO 45k Sur', '2026-08-06 09:11:30'),
(163, 1, 'bulk_import_certification', 'cm_certification', 91, 'SG Building Contractors Pte Ltd ISO 45k Sur', '2026-08-06 09:11:30'),
(164, 1, 'bulk_import_client', 'cm_client', 71, 'Ai Analytical Pte Ltd ISO 45k Surv', '2026-08-06 09:11:30'),
(165, 1, 'bulk_import_certification', 'cm_certification', 92, 'Ai Analytical Pte Ltd ISO 45k Surv', '2026-08-06 09:11:30'),
(166, 1, 'bulk_import_client', 'cm_client', 72, 'A+ Officers Security Pte Ltd ISO 45K Surv', '2026-08-06 09:11:30'),
(167, 1, 'bulk_import_certification', 'cm_certification', 93, 'A+ Officers Security Pte Ltd ISO 45K Surv', '2026-08-06 09:11:30'),
(168, 1, 'bulk_import_client', 'cm_client', 73, 'KAI LUN ENGINEERING PTE LTD ISO 45k Surv', '2026-08-06 09:11:30'),
(169, 1, 'bulk_import_certification', 'cm_certification', 94, 'KAI LUN ENGINEERING PTE LTD ISO 45k Surv', '2026-08-06 09:11:30'),
(170, 1, 'bulk_import_client', 'cm_client', 74, 'MH Engineering Pte Ltd 45k Surv', '2026-08-06 09:11:30'),
(171, 1, 'bulk_import_certification', 'cm_certification', 95, 'MH Engineering Pte Ltd 45k Surv', '2026-08-06 09:11:30'),
(172, 1, 'bulk_import_client', 'cm_client', 75, 'VERSION 20 PTE LTD ISO 9k and 45k Surv', '2026-08-06 09:11:30'),
(173, 1, 'bulk_import_certification', 'cm_certification', 96, 'VERSION 20 PTE LTD ISO 9k and 45k Surv', '2026-08-06 09:11:30'),
(174, 1, 'bulk_import_certification', 'cm_certification', 97, 'VERSION 20 PTE LTD ISO 9k and 45k Surv', '2026-08-06 09:11:30'),
(175, 1, 'bulk_import_client', 'cm_client', 76, 'GRK MARINE SERVICES PTE. LTD ISO 45001 Surv', '2026-08-06 09:11:30'),
(176, 1, 'bulk_import_certification', 'cm_certification', 98, 'GRK MARINE SERVICES PTE. LTD ISO 45001 Surv', '2026-08-06 09:11:30'),
(177, 1, 'bulk_import_client', 'cm_client', 77, 'UNISTEEL ENGINEERING PTE. LTD. ISO 45001 Surv', '2026-08-06 09:11:30'),
(178, 1, 'bulk_import_certification', 'cm_certification', 99, 'UNISTEEL ENGINEERING PTE. LTD. ISO 45001 Surv', '2026-08-06 09:11:30'),
(179, 1, 'bulk_import_client', 'cm_client', 78, 'Ji Tai Maritime Pte Ltd ISO 9k Surv', '2026-08-06 09:11:30'),
(180, 1, 'bulk_import_certification', 'cm_certification', 100, 'Ji Tai Maritime Pte Ltd ISO 9k Surv', '2026-08-06 09:11:30'),
(181, 1, 'bulk_import_certification', 'cm_certification', 101, 'Elitesia Forwarders Pte Ltd', '2026-08-06 09:11:30'),
(182, 1, 'bulk_import_certification', 'cm_certification', 102, 'Elitesia Forwarders Pte Ltd', '2026-08-06 09:11:30'),
(183, 1, 'bulk_import_client', 'cm_client', 79, 'HI-GREEN LANDSCAPE & CONSTRUCTION PTE. LTD 45k', '2026-08-06 09:11:30'),
(184, 1, 'bulk_import_certification', 'cm_certification', 103, 'HI-GREEN LANDSCAPE & CONSTRUCTION PTE. LTD 45k', '2026-08-06 09:11:30'),
(185, 1, 'bulk_import_client', 'cm_client', 80, 'JSBROD PRIVATE LIMITED ISO 27k', '2026-08-06 09:11:30'),
(186, 1, 'bulk_import_certification', 'cm_certification', 104, 'JSBROD PRIVATE LIMITED ISO 27k', '2026-08-06 09:11:30'),
(187, 1, 'bulk_import_client', 'cm_client', 81, 'COSMO SPACE PTE. LTD. 45K Surv', '2026-08-06 09:11:30'),
(188, 1, 'bulk_import_certification', 'cm_certification', 105, 'COSMO SPACE PTE. LTD. 45K Surv', '2026-08-06 09:11:30'),
(189, 1, 'bulk_import_client', 'cm_client', 82, 'TH3X Construction Consultancy Pte Ltd 45001 Surv', '2026-08-06 09:11:30'),
(190, 1, 'bulk_import_certification', 'cm_certification', 106, 'TH3X Construction Consultancy Pte Ltd 45001 Surv', '2026-08-06 09:11:30'),
(191, 1, 'bulk_import_client', 'cm_client', 83, 'BENKEL INTERNATIONAL PTE. LTD. 27001', '2026-08-06 09:11:30'),
(192, 1, 'bulk_import_certification', 'cm_certification', 107, 'BENKEL INTERNATIONAL PTE. LTD. 27001', '2026-08-06 09:11:30'),
(193, 1, 'bulk_import_client', 'cm_client', 84, 'S & M GLOBAL LOGISTICS PTE. LTD. 9k', '2026-08-06 09:11:30'),
(194, 1, 'bulk_import_certification', 'cm_certification', 108, 'S & M GLOBAL LOGISTICS PTE. LTD. 9k', '2026-08-06 09:11:30'),
(195, 1, 'bulk_import_client', 'cm_client', 85, 'G2 Engineering 45001 Surv', '2026-08-06 09:11:30'),
(196, 1, 'bulk_import_certification', 'cm_certification', 109, 'G2 Engineering 45001 Surv', '2026-08-06 09:11:30'),
(197, 1, 'bulk_import_client', 'cm_client', 86, 'Green Cosmos 45001 Surv', '2026-08-06 09:11:30'),
(198, 1, 'bulk_import_certification', 'cm_certification', 110, 'Green Cosmos 45001 Surv', '2026-08-06 09:11:30'),
(199, 1, 'bulk_import_client', 'cm_client', 87, 'NST Technology Pte Ltd _9001 New', '2026-08-06 09:11:30'),
(200, 1, 'bulk_import_certification', 'cm_certification', 111, 'NST Technology Pte Ltd _9001 New', '2026-08-06 09:11:30'),
(201, 1, 'bulk_import_client', 'cm_client', 88, 'S Power 2 system Surv', '2026-08-06 09:11:30'),
(202, 1, 'bulk_import_certification', 'cm_certification', 112, 'S Power 2 system Surv', '2026-08-06 09:11:30'),
(203, 1, 'bulk_import_certification', 'cm_certification', 113, 'S Power 2 system Surv', '2026-08-06 09:11:30'),
(204, 1, 'bulk_import_client', 'cm_client', 89, 'UNITEK ENGG 2 system Surveillance', '2026-08-06 09:11:30'),
(205, 1, 'bulk_import_certification', 'cm_certification', 114, 'UNITEK ENGG 2 system Surveillance', '2026-08-06 09:11:30'),
(206, 1, 'bulk_import_certification', 'cm_certification', 115, 'UNITEK ENGG 2 system Surveillance', '2026-08-06 09:11:30'),
(207, 1, 'bulk_import_client', 'cm_client', 90, 'Lee cycle Resources 9001 surv 1', '2026-08-06 09:11:30'),
(208, 1, 'bulk_import_certification', 'cm_certification', 116, 'Lee cycle Resources 9001 surv 1', '2026-08-06 09:11:30'),
(209, 1, 'bulk_import_client', 'cm_client', 91, 'YS Construction Pte Ltd 45001 Surv', '2026-08-06 09:11:30'),
(210, 1, 'bulk_import_certification', 'cm_certification', 117, 'YS Construction Pte Ltd 45001 Surv', '2026-08-06 09:11:30'),
(211, 1, 'bulk_import_client', 'cm_client', 92, 'Alampanai 45001 Surv', '2026-08-06 09:11:30'),
(212, 1, 'bulk_import_certification', 'cm_certification', 118, 'Alampanai 45001 Surv', '2026-08-06 09:11:30'),
(213, 1, 'bulk_import_client', 'cm_client', 93, 'Thong HUP Gardens 45001 Surv', '2026-08-06 09:11:30'),
(214, 1, 'bulk_import_certification', 'cm_certification', 119, 'Thong HUP Gardens 45001 Surv', '2026-08-06 09:11:30'),
(215, 1, 'bulk_import_client', 'cm_client', 94, 'Reedy Engineering 45001 Surv', '2026-08-06 09:11:30'),
(216, 1, 'bulk_import_certification', 'cm_certification', 120, 'Reedy Engineering 45001 Surv', '2026-08-06 09:11:30'),
(217, 1, 'bulk_import_client', 'cm_client', 95, 'Fukuyama 45001 Surv', '2026-08-06 09:11:30'),
(218, 1, 'bulk_import_certification', 'cm_certification', 121, 'Fukuyama 45001 Surv', '2026-08-06 09:11:30'),
(219, 1, 'bulk_import_client', 'cm_client', 96, 'Trans Engineering 2 system Surv', '2026-08-06 09:11:30'),
(220, 1, 'bulk_import_certification', 'cm_certification', 122, 'Trans Engineering 2 system Surv', '2026-08-06 09:11:30'),
(221, 1, 'bulk_import_certification', 'cm_certification', 123, 'Trans Engineering 2 system Surv', '2026-08-06 09:11:30'),
(222, 1, 'bulk_import_client', 'cm_client', 97, 'SIN TRANS ENGINEERING 2 system Surv', '2026-08-06 09:11:30'),
(223, 1, 'bulk_import_certification', 'cm_certification', 124, 'SIN TRANS ENGINEERING 2 system Surv', '2026-08-06 09:11:30'),
(224, 1, 'bulk_import_certification', 'cm_certification', 125, 'SIN TRANS ENGINEERING 2 system Surv', '2026-08-06 09:11:30'),
(225, 1, 'bulk_import_client', 'cm_client', 98, 'Everpeak Engineering 2 System Surv', '2026-08-06 09:11:30'),
(226, 1, 'bulk_import_certification', 'cm_certification', 126, 'Everpeak Engineering 2 System Surv', '2026-08-06 09:11:30'),
(227, 1, 'bulk_import_certification', 'cm_certification', 127, 'Everpeak Engineering 2 System Surv', '2026-08-06 09:11:30'),
(228, 1, 'bulk_import_client', 'cm_client', 99, 'T STORE 2 System Surv', '2026-08-06 09:11:30'),
(229, 1, 'bulk_import_certification', 'cm_certification', 128, 'T STORE 2 System Surv', '2026-08-06 09:11:30'),
(230, 1, 'bulk_import_certification', 'cm_certification', 129, 'T STORE 2 System Surv', '2026-08-06 09:11:30'),
(231, 1, 'bulk_import_client', 'cm_client', 100, 'Good Tyre Pte Ltd 2 system Surv', '2026-08-06 09:11:30'),
(232, 1, 'bulk_import_certification', 'cm_certification', 130, 'Good Tyre Pte Ltd 2 system Surv', '2026-08-06 09:11:30'),
(233, 1, 'bulk_import_certification', 'cm_certification', 131, 'Good Tyre Pte Ltd 2 system Surv', '2026-08-06 09:11:30'),
(234, 1, 'bulk_import_client', 'cm_client', 101, 'Horsol 3 system Surv', '2026-08-06 09:11:30'),
(235, 1, 'bulk_import_certification', 'cm_certification', 132, 'Horsol 3 system Surv', '2026-08-06 09:11:30'),
(236, 1, 'bulk_import_certification', 'cm_certification', 133, 'Horsol 3 system Surv', '2026-08-06 09:11:30'),
(237, 1, 'bulk_import_certification', 'cm_certification', 134, 'Horsol 3 system Surv', '2026-08-06 09:11:30'),
(238, 1, 'bulk_import_client', 'cm_client', 102, 'Nexvision 3 system Surv', '2026-08-06 09:11:30'),
(239, 1, 'bulk_import_certification', 'cm_certification', 135, 'Nexvision 3 system Surv', '2026-08-06 09:11:30'),
(240, 1, 'bulk_import_certification', 'cm_certification', 136, 'Nexvision 3 system Surv', '2026-08-06 09:11:30'),
(241, 1, 'bulk_import_certification', 'cm_certification', 137, 'Nexvision 3 system Surv', '2026-08-06 09:11:30'),
(242, 1, 'bulk_import_client', 'cm_client', 103, 'AE Model 3 system Surveillance', '2026-08-06 09:11:30'),
(243, 1, 'bulk_import_certification', 'cm_certification', 138, 'AE Model 3 system Surveillance', '2026-08-06 09:11:30'),
(244, 1, 'bulk_import_certification', 'cm_certification', 139, 'AE Model 3 system Surveillance', '2026-08-06 09:11:30'),
(245, 1, 'bulk_import_certification', 'cm_certification', 140, 'AE Model 3 system Surveillance', '2026-08-06 09:11:30'),
(246, 1, 'bulk_import_client', 'cm_client', 104, 'HENG SENG HIN CONSTRUCTION PTE. LTD. ISO 45001_surveillance', '2026-08-06 09:11:30'),
(247, 1, 'bulk_import_certification', 'cm_certification', 141, 'HENG SENG HIN CONSTRUCTION PTE. LTD. ISO 45001_surveillance', '2026-08-06 09:11:30'),
(248, 1, 'bulk_import_client', 'cm_client', 105, 'LYC HARDWARE & ENGINEERING PTE LTD _SURV', '2026-08-06 09:11:30'),
(249, 1, 'bulk_import_certification', 'cm_certification', 142, 'LYC HARDWARE & ENGINEERING PTE LTD _SURV', '2026-08-06 09:11:30'),
(250, 1, 'bulk_import_client', 'cm_client', 106, 'Vinayaka scaffold & Services Pte Ltd Easan', '2026-08-06 09:11:30'),
(251, 1, 'bulk_import_certification', 'cm_certification', 143, 'Vinayaka scaffold & Services Pte Ltd Easan', '2026-08-06 09:11:30'),
(252, 1, 'bulk_import_client', 'cm_client', 107, 'Silver Seal and Construction pte Ltd_45K ZAck_Ruth', '2026-08-06 09:11:30'),
(253, 1, 'bulk_import_certification', 'cm_certification', 144, 'Silver Seal and Construction pte Ltd_45K ZAck_Ruth', '2026-08-06 09:11:30'),
(254, 1, 'bulk_import_client', 'cm_client', 108, 'PEARL Engineering Sitha IMS', '2026-08-06 09:11:30'),
(255, 1, 'bulk_import_certification', 'cm_certification', 145, 'PEARL Engineering Sitha IMS', '2026-08-06 09:11:30'),
(256, 1, 'bulk_import_certification', 'cm_certification', 146, 'PEARL Engineering Sitha IMS', '2026-08-06 09:11:30'),
(257, 1, 'bulk_import_client', 'cm_client', 109, 'SPINNET ASIA PTE. LTD. Sitha', '2026-08-06 09:11:30'),
(258, 1, 'bulk_import_certification', 'cm_certification', 147, 'SPINNET ASIA PTE. LTD. Sitha', '2026-08-06 09:11:30'),
(259, 1, 'bulk_import_certification', 'cm_certification', 148, 'SPINNET ASIA PTE. LTD. Sitha', '2026-08-06 09:11:30'),
(260, 1, 'bulk_import_client', 'cm_client', 110, 'ELITE ENGINEERING 2 system 9 & 45 NEW', '2026-08-06 09:11:30'),
(261, 1, 'bulk_import_certification', 'cm_certification', 149, 'ELITE ENGINEERING 2 system 9 & 45 NEW', '2026-08-06 09:11:30'),
(262, 1, 'bulk_import_certification', 'cm_certification', 150, 'ELITE ENGINEERING 2 system 9 & 45 NEW', '2026-08-06 09:11:30'),
(263, 1, 'bulk_import_client', 'cm_client', 111, 'HH DESIGN PTE LTD', '2026-08-06 09:11:30'),
(264, 1, 'bulk_import_certification', 'cm_certification', 151, 'HH DESIGN PTE LTD', '2026-08-06 09:11:30'),
(265, 1, 'bulk_import_certification', 'cm_certification', 152, 'HH DESIGN PTE LTD', '2026-08-06 09:11:30'),
(266, 1, 'bulk_import_client', 'cm_client', 112, 'RCY 37001 Own', '2026-08-06 09:11:30'),
(267, 1, 'bulk_import_certification', 'cm_certification', 153, 'RCY 37001 Own', '2026-08-06 09:11:30'),
(268, 1, 'bulk_import_client', 'cm_client', 113, 'ASIA TEC SERVICES PTE. LTD. Philip 45K', '2026-08-06 09:11:30'),
(269, 1, 'bulk_import_certification', 'cm_certification', 154, 'ASIA TEC SERVICES PTE. LTD. Philip 45K', '2026-08-06 09:11:30'),
(270, 1, 'bulk_import_client', 'cm_client', 114, 'RMS Integrated Pte Ltd 45k WSH', '2026-08-06 09:11:30'),
(271, 1, 'bulk_import_certification', 'cm_certification', 155, 'RMS Integrated Pte Ltd 45k WSH', '2026-08-06 09:11:30'),
(272, 1, 'bulk_import_client', 'cm_client', 115, 'KEE Safety Singapore Pte Ltd ISO 45001 Easan', '2026-08-06 09:11:30'),
(273, 1, 'bulk_import_certification', 'cm_certification', 156, 'KEE Safety Singapore Pte Ltd ISO 45001 Easan', '2026-08-06 09:11:30'),
(274, 1, 'bulk_import_client', 'cm_client', 116, 'YGS ENERGY 9001 and 45001 Pandian', '2026-08-06 09:11:30'),
(275, 1, 'bulk_import_certification', 'cm_certification', 157, 'YGS ENERGY 9001 and 45001 Pandian', '2026-08-06 09:11:30'),
(276, 1, 'bulk_import_certification', 'cm_certification', 158, 'YGS ENERGY 9001 and 45001 Pandian', '2026-08-06 09:11:30'),
(277, 1, 'bulk_import_client', 'cm_client', 117, 'MASTRON ISO 45001 Zack', '2026-08-06 09:11:30'),
(278, 1, 'bulk_import_certification', 'cm_certification', 159, 'MASTRON ISO 45001 Zack', '2026-08-06 09:11:30'),
(279, 1, 'bulk_import_client', 'cm_client', 118, 'ACS PRocess Control Pte Ltd ISO 9001 Zack', '2026-08-06 09:11:30'),
(280, 1, 'bulk_import_certification', 'cm_certification', 160, 'ACS PRocess Control Pte Ltd ISO 9001 Zack', '2026-08-06 09:11:30'),
(281, 1, 'bulk_import_client', 'cm_client', 119, 'IMPERIAL 45001 Expert', '2026-08-06 09:11:30'),
(282, 1, 'bulk_import_certification', 'cm_certification', 161, 'IMPERIAL 45001 Expert', '2026-08-06 09:11:30'),
(283, 1, 'bulk_import_client', 'cm_client', 120, 'VPM ENGINEERING 45k own', '2026-08-06 09:11:30'),
(284, 1, 'bulk_import_certification', 'cm_certification', 162, 'VPM ENGINEERING 45k own', '2026-08-06 09:11:30'),
(285, 1, 'bulk_import_client', 'cm_client', 121, 'Long Sheng ISO 45001 Sharon', '2026-08-06 09:11:30'),
(286, 1, 'bulk_import_certification', 'cm_certification', 163, 'Long Sheng ISO 45001 Sharon', '2026-08-06 09:11:30'),
(287, 1, 'bulk_import_client', 'cm_client', 122, 'TWIN STAR ENGINEERING & OFFSHORE PTE. LTD', '2026-08-06 09:11:30'),
(288, 1, 'bulk_import_certification', 'cm_certification', 164, 'TWIN STAR ENGINEERING & OFFSHORE PTE. LTD', '2026-08-06 09:11:30'),
(289, 1, 'bulk_import_client', 'cm_client', 123, 'SAI ENGINEERING PTE. LTD', '2026-08-06 09:11:30'),
(290, 1, 'bulk_import_certification', 'cm_certification', 165, 'SAI ENGINEERING PTE. LTD', '2026-08-06 09:11:30'),
(291, 1, 'bulk_import_client', 'cm_client', 124, 'JACIN SECURITY SERVICES 9 and 45001 11 20 and 21 April SIS', '2026-08-06 09:11:30'),
(292, 1, 'bulk_import_client', 'cm_client', 125, 'Palace Builder ISO 45K IAS', '2026-08-06 09:11:30'),
(293, 1, 'bulk_import_certification', 'cm_certification', 166, 'Palace Builder ISO 45K IAS', '2026-08-06 09:11:30'),
(294, 1, 'bulk_import_client', 'cm_client', 126, 'S Tech Engineering 45001 Own', '2026-08-06 09:11:30'),
(295, 1, 'bulk_import_certification', 'cm_certification', 167, 'S Tech Engineering 45001 Own', '2026-08-06 09:11:30'),
(296, 1, 'bulk_import_client', 'cm_client', 127, 'POWERPOINT Marine Services 45001 Own', '2026-08-06 09:11:30'),
(297, 1, 'bulk_import_certification', 'cm_certification', 168, 'POWERPOINT Marine Services 45001 Own', '2026-08-06 09:11:30'),
(298, 1, 'bulk_import_client', 'cm_client', 128, 'BESTPOINT Marine Services 45001 Own', '2026-08-06 09:11:30'),
(299, 1, 'bulk_import_certification', 'cm_certification', 169, 'BESTPOINT Marine Services 45001 Own', '2026-08-06 09:11:30'),
(300, 1, 'bulk_import_client', 'cm_client', 129, 'ENERGREEN TECHNOLOGIES PTE. LTD 45001 Zack', '2026-08-06 09:11:30'),
(301, 1, 'bulk_import_certification', 'cm_certification', 170, 'ENERGREEN TECHNOLOGIES PTE. LTD 45001 Zack', '2026-08-06 09:11:30'),
(302, 1, 'bulk_import_client', 'cm_client', 130, 'S POWER Global', '2026-08-06 09:11:30'),
(303, 1, 'bulk_import_certification', 'cm_certification', 171, 'S POWER Global', '2026-08-06 09:11:30'),
(304, 1, 'bulk_import_client', 'cm_client', 131, 'AE Models', '2026-08-06 09:11:30'),
(305, 1, 'bulk_import_certification', 'cm_certification', 172, 'AE Models', '2026-08-06 09:11:30'),
(306, 1, 'bulk_import_certification', 'cm_certification', 173, 'AE Models', '2026-08-06 09:11:30'),
(307, 1, 'bulk_import_certification', 'cm_certification', 174, 'AE Models', '2026-08-06 09:11:30'),
(308, 1, 'bulk_import_client', 'cm_client', 132, 'SAMMAR AUTOMATION IMS system Bala sir', '2026-08-06 09:11:30'),
(309, 1, 'bulk_import_certification', 'cm_certification', 175, 'SAMMAR AUTOMATION IMS system Bala sir', '2026-08-06 09:11:30'),
(310, 1, 'bulk_import_certification', 'cm_certification', 176, 'SAMMAR AUTOMATION IMS system Bala sir', '2026-08-06 09:11:30'),
(311, 1, 'bulk_import_certification', 'cm_certification', 177, 'SAMMAR AUTOMATION IMS system Bala sir', '2026-08-06 09:11:30'),
(312, 1, 'bulk_import_client', 'cm_client', 133, 'NEXUS MANAGEMENT SERVICES – ISO 9001 Easan', '2026-08-06 09:11:30'),
(313, 1, 'bulk_import_certification', 'cm_certification', 178, 'NEXUS MANAGEMENT SERVICES – ISO 9001 Easan', '2026-08-06 09:11:30'),
(314, 1, 'bulk_import_client', 'cm_client', 134, 'SUZI ISO 45001 WSH Expert', '2026-08-06 09:11:30'),
(315, 1, 'bulk_import_certification', 'cm_certification', 179, 'SUZI ISO 45001 WSH Expert', '2026-08-06 09:11:30'),
(316, 1, 'bulk_import_client', 'cm_client', 135, 'LDC GENERAL CONSTRUCTION PTE LTD', '2026-08-06 09:11:30'),
(317, 1, 'bulk_import_certification', 'cm_certification', 180, 'LDC GENERAL CONSTRUCTION PTE LTD', '2026-08-06 09:11:30'),
(318, 1, 'bulk_import_certification', 'cm_certification', 181, 'LDC GENERAL CONSTRUCTION PTE LTD', '2026-08-06 09:11:30'),
(319, 1, 'bulk_import_certification', 'cm_certification', 182, 'LDC GENERAL CONSTRUCTION PTE LTD', '2026-08-06 09:11:30'),
(320, 1, 'bulk_import_client', 'cm_client', 136, 'SHINCON INDUSTRIAL PTE LTD', '2026-08-06 09:11:30'),
(321, 1, 'bulk_import_certification', 'cm_certification', 183, 'SHINCON INDUSTRIAL PTE LTD', '2026-08-06 09:11:30'),
(322, 1, 'bulk_import_certification', 'cm_certification', 184, 'SHINCON INDUSTRIAL PTE LTD', '2026-08-06 09:11:30'),
(323, 1, 'bulk_import_certification', 'cm_certification', 185, 'SHINCON INDUSTRIAL PTE LTD', '2026-08-06 09:11:30'),
(324, 1, 'bulk_import_client', 'cm_client', 137, 'HONG AN ENGINEERING PTE LTD', '2026-08-06 09:11:30'),
(325, 1, 'bulk_import_client', 'cm_client', 138, 'HUA RONG ENGINEERING PTE. LTD.', '2026-08-06 09:11:30'),
(326, 1, 'bulk_import_client', 'cm_client', 139, 'KEMVET COMMERCIAL BUILDERS PTE. LTD.', '2026-08-06 09:11:30'),
(327, 1, 'bulk_import_client', 'cm_client', 140, 'PERMA-LINER INDUSTRIES (SINGAPORE) PTE LTD. 45K', '2026-08-06 09:11:30'),
(328, 1, 'bulk_import_certification', 'cm_certification', 186, 'PERMA-LINER INDUSTRIES (SINGAPORE) PTE LTD. 45K', '2026-08-06 09:11:30'),
(329, 1, 'bulk_import_client', 'cm_client', 141, 'BUILDING ASSOCIATES (S) PTE. LTD.', '2026-08-06 09:11:30'),
(330, 1, 'bulk_import_client', 'cm_client', 142, 'BEST NDT INSPECTION TECHNOLOGIES PTE. LTD.', '2026-08-06 09:11:30'),
(331, 1, 'bulk_import_client', 'cm_client', 143, 'JJGL VENTURES PTE LTD', '2026-08-06 09:11:30'),
(332, 1, 'bulk_import_client', 'cm_client', 144, 'BN SOLUTIONS (S) PTE LTD', '2026-08-06 09:11:30'),
(333, 1, 'bulk_import_client', 'cm_client', 145, 'HUA CHANG CONSTRUCTION PTE LTD', '2026-08-06 09:11:30'),
(334, 1, 'bulk_import_client', 'cm_client', 146, 'WIDE WINGS PTE LTD', '2026-08-06 09:11:30'),
(335, 1, 'bulk_import_client', 'cm_client', 147, 'BUILTMECH PTE. LTD.', '2026-08-06 09:11:30'),
(336, 1, 'bulk_import_client', 'cm_client', 148, 'VJ CONSTRUCTION PTE LTD', '2026-08-06 09:11:30'),
(337, 1, 'bulk_import_client', 'cm_client', 149, 'F R R CONSTRUCTION PTE LTD', '2026-08-06 09:11:30'),
(338, 1, 'bulk_import_client', 'cm_client', 150, 'PERMA-LINER INDUSTRIES (SINGAPORE) PTE LTD.', '2026-08-06 09:11:30'),
(339, 1, 'bulk_import_client', 'cm_client', 151, 'RCY PTE LTD', '2026-08-06 09:11:30'),
(340, 1, 'bulk_import_client', 'cm_client', 152, 'ASIABUILD ENTERPRISES PTE LTD.', '2026-08-06 09:11:30'),
(341, 1, 'bulk_import_client', 'cm_client', 153, 'HACELY (SINGAPORE) PTE. LTD.', '2026-08-06 09:11:30'),
(342, 1, 'bulk_import_client', 'cm_client', 154, 'EI CORPORATION PTE. LTD.', '2026-08-06 09:11:30'),
(343, 1, 'bulk_import_client', 'cm_client', 155, 'BSM STEEL CONSTRUCTION PTE. LTD.', '2026-08-06 09:11:30'),
(344, 1, 'bulk_import_client', 'cm_client', 156, 'YONGYANG LIFT ENGINEERING PTE. LTD.', '2026-08-06 09:11:30'),
(345, 1, 'bulk_import_client', 'cm_client', 157, 'SUPERSKETCH DESIGNERS PTE. LTD.', '2026-08-06 09:11:30'),
(346, 1, 'bulk_import_client', 'cm_client', 158, 'STAMFORD POWER ENGINEERING PTE LTD', '2026-08-06 09:11:30'),
(347, 1, 'bulk_import_client', 'cm_client', 159, 'HUP GAY CIVIL ENGINEERING PTE LTD', '2026-08-06 09:11:30'),
(348, 1, 'bulk_import_client', 'cm_client', 160, 'FEBA TECHNOLOGIES PTE. LTD.', '2026-08-06 09:11:30'),
(349, 1, 'bulk_import_client', 'cm_client', 161, 'JETSEN DESIGN PTE LTD', '2026-08-06 09:11:30'),
(350, 1, 'bulk_import_client', 'cm_client', 162, 'DIVINE N\' DYNAMIC PTE. LTD.', '2026-08-06 09:11:30'),
(351, 1, 'bulk_import_client', 'cm_client', 163, 'HOBBY CONSTRUCTION PTE LTD', '2026-08-06 09:11:30'),
(352, 1, 'bulk_import_client', 'cm_client', 164, 'LUM CHANG BRANDSBRIDGE PTE LTD', '2026-08-06 09:11:30'),
(353, 1, 'bulk_import_client', 'cm_client', 165, 'NEW TECHNOLOGY SOLUTIONS PTE LTD', '2026-08-06 09:11:30'),
(354, 1, 'bulk_import_client', 'cm_client', 166, 'BEST AEROSPACE NDT & INSPECTION SERVICE PTE LTD.', '2026-08-06 09:11:30'),
(355, 1, 'bulk_import_client', 'cm_client', 167, 'BEST NDT CONSTRUCTIONS & ENGINEERING PTE LTD.', '2026-08-06 09:11:30'),
(356, 1, 'bulk_import_client', 'cm_client', 168, 'INSPIRE ID GROUP PTE. LTD.', '2026-08-06 09:11:30'),
(357, 1, 'bulk_import_client', 'cm_client', 169, 'KL E&C PROJECTS PTE. LTD.', '2026-08-06 09:11:30'),
(358, 1, 'bulk_import_client', 'cm_client', 170, 'POWERCORP PTE LTD', '2026-08-06 09:11:30'),
(359, 1, 'bulk_import_client', 'cm_client', 171, 'HUAY ARCHITECTS PTE LTD', '2026-08-06 09:11:30'),
(360, 1, 'bulk_import_client', 'cm_client', 172, 'HONGZE CONSTRUCTION BUILDERS PTE LTD', '2026-08-06 09:11:30'),
(361, 1, 'bulk_import_client', 'cm_client', 173, 'ACQUAL ENGINEERING LLP 45k', '2026-08-06 09:11:30'),
(362, 1, 'bulk_import_client', 'cm_client', 174, 'YJME ENGINEERING PTE LTD', '2026-08-06 09:11:30'),
(363, 1, 'bulk_import_client', 'cm_client', 175, 'M&S MANAGEMENT & CONTRACTS SERVICES PTE LTD', '2026-08-06 09:11:30'),
(364, 1, 'bulk_import_client', 'cm_client', 176, 'SAM WOO (S.E.A.) PTE LTD', '2026-08-06 09:11:30'),
(365, 1, 'bulk_import_client', 'cm_client', 177, 'SANDHU_MAN CONTRACTS SERVICES PTE. LTD.', '2026-08-06 09:11:30'),
(366, 1, 'bulk_import_client', 'cm_client', 178, 'SIN GUAN TECK PTE LTD', '2026-08-06 09:11:30'),
(367, 1, 'bulk_import_client', 'cm_client', 179, 'ASTON AIR CONTROL PTE LTD', '2026-08-06 09:11:30'),
(368, 1, 'bulk_import_client', 'cm_client', 180, 'AIRVERCLEAN PTE LTD', '2026-08-06 09:11:30'),
(369, 1, 'bulk_import_client', 'cm_client', 181, 'SHIN-ECON CORPORATION PTE LTD', '2026-08-06 09:11:30'),
(370, 1, 'bulk_import_client', 'cm_client', 182, 'LV AUTOMATION PTE LTD', '2026-08-06 09:11:30'),
(371, 1, 'bulk_import_client', 'cm_client', 183, 'ELECTRICAL PRODUCT INTERNATIONAL PTE LTD (SAC)', '2026-08-06 09:11:30'),
(372, 1, 'bulk_import_client', 'cm_client', 184, 'YSG ELECTRICAL & ENGINEERING LLP', '2026-08-06 09:11:30'),
(373, 1, 'bulk_import_client', 'cm_client', 185, 'TECHNO CE PTE LTD', '2026-08-06 09:11:30'),
(374, 1, 'bulk_import_client', 'cm_client', 186, 'ARS MANUFACTURER PTE LTD', '2026-08-06 09:11:30'),
(375, 1, 'bulk_import_client', 'cm_client', 187, 'CHINA RAILWAY CONSTRUCTION GROUP CORPORATION LIMITED', '2026-08-06 09:11:30'),
(376, 1, 'bulk_import_client', 'cm_client', 188, 'ANACHEM TECHNOLOGIES (S) PTE LTD', '2026-08-06 09:11:30'),
(377, 1, 'bulk_import_client', 'cm_client', 189, 'LIMELIGHT ATELIER PTE LTD', '2026-08-06 09:11:30'),
(378, 1, 'bulk_import_client', 'cm_client', 190, 'NJNCC CHEMICAL CONSTRUCTION PTE LTD', '2026-08-06 09:11:30'),
(379, 1, 'bulk_import_client', 'cm_client', 191, 'JMJ CONSULTANTS PTE LTD', '2026-08-06 09:11:30'),
(380, 1, 'bulk_import_client', 'cm_client', 192, 'B4 WATER LEAKAGE SPECIALIST PRIVATE LTD', '2026-08-06 09:11:30'),
(381, 1, 'bulk_import_client', 'cm_client', 193, 'LN ART ID STUDIO PTE LTD 45k', '2026-08-06 09:11:30'),
(382, 1, 'bulk_import_client', 'cm_client', 194, 'PLS PILING PTE LTD', '2026-08-06 09:11:30'),
(383, 1, 'bulk_import_client', 'cm_client', 195, 'ASTA VENTURES PTE LTD', '2026-08-06 09:11:30'),
(384, 1, 'bulk_import_client', 'cm_client', 196, 'TEC SQUARE PTE LTD', '2026-08-06 09:11:30'),
(385, 1, 'bulk_import_client', 'cm_client', 197, 'NCE CORPORATION (S) PTE. LTD.', '2026-08-06 09:11:30'),
(386, 1, 'bulk_import_client', 'cm_client', 198, 'A-POWER PROJECT & ENGRG PTE LTD', '2026-08-06 09:11:30'),
(387, 1, 'bulk_import_client', 'cm_client', 199, 'CITIWALL PTE LTD', '2026-08-06 09:11:30'),
(388, 1, 'bulk_import_client', 'cm_client', 200, 'CITIWALL ENGINEERING PTE LTD', '2026-08-06 09:11:30'),
(389, 1, 'bulk_import_client', 'cm_client', 201, 'PSC FREYSSINET (SINGAPORE) PTE LTD', '2026-08-06 09:11:30'),
(390, 1, 'bulk_import_client', 'cm_client', 202, 'AJ SIXTY ONE PTE LTD', '2026-08-06 09:11:30'),
(391, 1, 'bulk_import_client', 'cm_client', 203, 'WU YI BUILDING CONSTRUCTION PTE. LTD.', '2026-08-06 09:11:30'),
(392, 1, 'bulk_import_client', 'cm_client', 204, 'SPECTRUM GLOBAL ENGINEERING PTE. LTD.', '2026-08-06 09:11:30'),
(393, 1, 'bulk_import_certification', 'cm_certification', 187, 'SPECTRUM GLOBAL ENGINEERING PTE. LTD.', '2026-08-06 09:11:30'),
(394, 1, 'bulk_import_client', 'cm_client', 205, 'CHINA RAILWAY ENGINEERING EQUIPMENT GROUP CO., LTD.\n\nSINGAPORE BRANCH', '2026-08-06 09:11:30'),
(395, 1, 'bulk_import_certification', 'cm_certification', 188, 'CHINA RAILWAY ENGINEERING EQUIPMENT GROUP CO., LTD.\n\nSINGAPORE BRANCH', '2026-08-06 09:11:30'),
(396, 1, 'bulk_import_client', 'cm_client', 206, 'IET PTE LTD', '2026-08-06 09:11:30'),
(397, 1, 'bulk_import_certification', 'cm_certification', 189, 'IET PTE LTD', '2026-08-06 09:11:30'),
(398, 1, 'bulk_import_certification', 'cm_certification', 190, 'IET PTE LTD', '2026-08-06 09:11:30'),
(399, 1, 'bulk_import_certification', 'cm_certification', 191, 'IET PTE LTD', '2026-08-06 09:11:30'),
(400, 1, 'bulk_import_client', 'cm_client', 207, 'VSK CONSTRUCTION PTE LTD', '2026-08-06 09:11:30'),
(401, 1, 'bulk_import_certification', 'cm_certification', 192, 'VSK CONSTRUCTION PTE LTD', '2026-08-06 09:11:30'),
(402, 1, 'bulk_import_certification', 'cm_certification', 193, 'VSK CONSTRUCTION PTE LTD', '2026-08-06 09:11:30'),
(403, 1, 'bulk_import_certification', 'cm_certification', 194, 'N Grace Builders Pte Ltd', '2026-08-06 09:11:30'),
(404, 1, 'bulk_import_certification', 'cm_certification', 195, 'HH Design Pte Ltd', '2026-08-06 09:11:30'),
(405, 1, 'bulk_import_certification', 'cm_certification', 196, 'HH Design Pte Ltd', '2026-08-06 09:11:30'),
(406, 1, 'bulk_import_certification', 'cm_certification', 197, 'HH Design Pte Ltd', '2026-08-06 09:11:30'),
(407, 1, 'bulk_import_client', 'cm_client', 208, 'CHIN SIONG ELECTRICAL ENGINEERING PTE LTD.', '2026-08-06 09:11:30'),
(408, 1, 'bulk_import_certification', 'cm_certification', 198, 'CHIN SIONG ELECTRICAL ENGINEERING PTE LTD.', '2026-08-06 09:11:30'),
(409, 1, 'bulk_import_client', 'cm_client', 209, 'IA Builders & Engineering Pte Ltd', '2026-08-06 09:11:30'),
(410, 1, 'bulk_import_certification', 'cm_certification', 199, 'IA Builders & Engineering Pte Ltd', '2026-08-06 09:11:30'),
(411, 1, 'bulk_import_certification', 'cm_certification', 200, 'IA Builders & Engineering Pte Ltd', '2026-08-06 09:11:30'),
(412, 1, 'bulk_import_client', 'cm_client', 210, '5 MASONS PTE LTD.', '2026-08-06 09:11:30'),
(413, 1, 'bulk_import_certification', 'cm_certification', 201, '5 MASONS PTE LTD.', '2026-08-06 09:11:30'),
(414, 1, 'bulk_import_client', 'cm_client', 211, 'EDMUND TIE & COMPANY PROPERTY MANAGEMENT SERVICES PTE. LTD', '2026-08-06 09:11:30'),
(415, 1, 'bulk_import_certification', 'cm_certification', 202, 'EDMUND TIE & COMPANY PROPERTY MANAGEMENT SERVICES PTE. LTD', '2026-08-06 09:11:30'),
(416, 1, 'bulk_import_client', 'cm_client', 212, 'FORMAX VENTURES PTE LTD', '2026-08-06 09:11:30'),
(417, 1, 'bulk_import_certification', 'cm_certification', 203, 'FORMAX VENTURES PTE LTD', '2026-08-06 09:11:30'),
(418, 1, 'bulk_import_certification', 'cm_certification', 204, 'FORMAX VENTURES PTE LTD', '2026-08-06 09:11:30'),
(419, 1, 'bulk_import_certification', 'cm_certification', 205, 'FORMAX VENTURES PTE LTD', '2026-08-06 09:11:30'),
(420, 1, 'bulk_import_client', 'cm_client', 213, 'NRMF PTE. LTD.', '2026-08-06 09:11:30'),
(421, 1, 'bulk_import_certification', 'cm_certification', 206, 'NRMF PTE. LTD.', '2026-08-06 09:11:30'),
(422, 1, 'bulk_import_client', 'cm_client', 214, 'JD HDD PTE LTD', '2026-08-06 09:11:30'),
(423, 1, 'bulk_import_certification', 'cm_certification', 207, 'JD HDD PTE LTD', '2026-08-06 09:11:30'),
(424, 1, 'bulk_import_client', 'cm_client', 215, 'ON TIME ENGINEERING PTE. LTD.', '2026-08-06 09:11:30'),
(425, 1, 'bulk_import_certification', 'cm_certification', 208, 'ON TIME ENGINEERING PTE. LTD.', '2026-08-06 09:11:30'),
(426, 1, 'bulk_import_certification', 'cm_certification', 209, 'ON TIME ENGINEERING PTE. LTD.', '2026-08-06 09:11:30'),
(427, 1, 'bulk_import_client', 'cm_client', 216, 'CEG INDUSTRIES PTE LTD', '2026-08-06 09:11:30'),
(428, 1, 'bulk_import_certification', 'cm_certification', 210, 'CEG INDUSTRIES PTE LTD', '2026-08-06 09:11:30'),
(429, 1, 'bulk_import_certification', 'cm_certification', 211, 'CEG INDUSTRIES PTE LTD', '2026-08-06 09:11:30'),
(430, 1, 'bulk_import_certification', 'cm_certification', 212, 'CEG INDUSTRIES PTE LTD', '2026-08-06 09:11:30'),
(431, 1, 'bulk_import_client', 'cm_client', 217, 'PRM ENGINEERING PTE. LTD.', '2026-08-06 09:11:30'),
(432, 1, 'bulk_import_certification', 'cm_certification', 213, 'PRM ENGINEERING PTE. LTD.', '2026-08-06 09:11:30'),
(433, 1, 'bulk_import_certification', 'cm_certification', 214, 'PRM ENGINEERING PTE. LTD.', '2026-08-06 09:11:30'),
(434, 1, 'bulk_import_certification', 'cm_certification', 215, 'PRM ENGINEERING PTE. LTD.', '2026-08-06 09:11:30'),
(435, 1, 'bulk_import_client', 'cm_client', 218, 'WANG SHENG DESIGN & BUILD PTE. LTD.', '2026-08-06 09:11:30'),
(436, 1, 'bulk_import_certification', 'cm_certification', 216, 'WANG SHENG DESIGN & BUILD PTE. LTD.', '2026-08-06 09:11:30'),
(437, 1, 'bulk_import_certification', 'cm_certification', 217, 'WANG SHENG DESIGN & BUILD PTE. LTD.', '2026-08-06 09:11:30'),
(438, 1, 'bulk_import_client', 'cm_client', 219, 'ROBOTPACK FLEXIBLE AUTOMATION SYSTEM(S) PTE LTD', '2026-08-06 09:11:30'),
(439, 1, 'bulk_import_certification', 'cm_certification', 218, 'ROBOTPACK FLEXIBLE AUTOMATION SYSTEM(S) PTE LTD', '2026-08-06 09:11:30'),
(440, 1, 'bulk_import_client', 'cm_client', 220, 'GREEN CARE SERVICES PTE. LTD.', '2026-08-06 09:11:30'),
(441, 1, 'bulk_import_certification', 'cm_certification', 219, 'GREEN CARE SERVICES PTE. LTD.', '2026-08-06 09:11:30'),
(442, 1, 'bulk_import_client', 'cm_client', 221, 'PERFECT STEEL PTE. LTD', '2026-08-06 09:11:30'),
(443, 1, 'bulk_import_certification', 'cm_certification', 220, 'PERFECT STEEL PTE. LTD', '2026-08-06 09:11:30'),
(444, 1, 'bulk_import_certification', 'cm_certification', 221, 'PERFECT STEEL PTE. LTD', '2026-08-06 09:11:30'),
(445, 1, 'bulk_import_client', 'cm_client', 222, 'CAN CAN M&E PTE. LTD.', '2026-08-06 09:11:30'),
(446, 1, 'bulk_import_certification', 'cm_certification', 222, 'CAN CAN M&E PTE. LTD.', '2026-08-06 09:11:30'),
(447, 1, 'bulk_import_certification', 'cm_certification', 223, 'CAN CAN M&E PTE. LTD.', '2026-08-06 09:11:30');
INSERT INTO `cm_activity_log` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `details`, `created_at`) VALUES
(448, 1, 'bulk_import_client', 'cm_client', 223, 'Best cool air con Pte ltd (Need to update', '2026-08-06 09:11:30'),
(449, 1, 'bulk_import_client', 'cm_client', 224, 'IET PTE LTD - IMS & ISO 27001 AX', '2026-08-06 09:11:30'),
(450, 1, 'bulk_import_certification', 'cm_certification', 224, 'IET PTE LTD - IMS & ISO 27001 AX', '2026-08-06 09:11:30'),
(451, 1, 'bulk_import_certification', 'cm_certification', 225, 'IET PTE LTD - IMS & ISO 27001 AX', '2026-08-06 09:11:30'),
(452, 1, 'bulk_import_certification', 'cm_certification', 226, 'IET PTE LTD - IMS & ISO 27001 AX', '2026-08-06 09:11:30'),
(453, 1, 'bulk_import_certification', 'cm_certification', 227, 'IET PTE LTD - IMS & ISO 27001 AX', '2026-08-06 09:11:30'),
(454, 1, 'bulk_import_client', 'cm_client', 225, 'URC PTE LTD - IMS GSI', '2026-08-06 09:11:30'),
(455, 1, 'bulk_import_certification', 'cm_certification', 228, 'URC PTE LTD - IMS GSI', '2026-08-06 09:11:30'),
(456, 1, 'bulk_import_certification', 'cm_certification', 229, 'URC PTE LTD - IMS GSI', '2026-08-06 09:11:30'),
(457, 1, 'bulk_import_certification', 'cm_certification', 230, 'URC PTE LTD - IMS GSI', '2026-08-06 09:11:30'),
(458, 1, 'bulk_import_client', 'cm_client', 226, 'UMA MARINE - IMS AX', '2026-08-06 09:11:30'),
(459, 1, 'bulk_import_certification', 'cm_certification', 231, 'UMA MARINE - IMS AX', '2026-08-06 09:11:30'),
(460, 1, 'bulk_import_certification', 'cm_certification', 232, 'UMA MARINE - IMS AX', '2026-08-06 09:11:30'),
(461, 1, 'bulk_import_certification', 'cm_certification', 233, 'UMA MARINE - IMS AX', '2026-08-06 09:11:30'),
(462, 1, 'bulk_import_client', 'cm_client', 227, 'JSBROD IMS ISMS MOCKUP _ VOID', '2026-08-06 09:11:30'),
(463, 1, 'bulk_import_certification', 'cm_certification', 234, 'JSBROD IMS ISMS MOCKUP _ VOID', '2026-08-06 09:11:30'),
(464, 1, 'bulk_import_certification', 'cm_certification', 235, 'JSBROD IMS ISMS MOCKUP _ VOID', '2026-08-06 09:11:30'),
(465, 1, 'bulk_import_certification', 'cm_certification', 236, 'JSBROD IMS ISMS MOCKUP _ VOID', '2026-08-06 09:11:30'),
(466, 1, 'bulk_import_certification', 'cm_certification', 237, 'JSBROD IMS ISMS MOCKUP _ VOID', '2026-08-06 09:11:30'),
(467, 1, 'bulk_import_client', 'cm_client', 228, 'Shaftrise Engineering & Services GSI', '2026-08-06 09:11:30'),
(468, 1, 'bulk_import_certification', 'cm_certification', 238, 'Shaftrise Engineering & Services GSI', '2026-08-06 09:11:30'),
(469, 1, 'bulk_import_certification', 'cm_certification', 239, 'Shaftrise Engineering & Services GSI', '2026-08-06 09:11:30'),
(470, 1, 'bulk_import_client', 'cm_client', 229, 'ADROITZ 45k AX', '2026-08-06 09:11:30'),
(471, 1, 'bulk_import_certification', 'cm_certification', 240, 'ADROITZ 45k AX', '2026-08-06 09:11:30'),
(472, 1, 'bulk_import_certification', 'cm_certification', 241, 'ADROITZ 45k AX', '2026-08-06 09:11:30'),
(473, 1, 'bulk_import_certification', 'cm_certification', 242, 'ADROITZ 45k AX', '2026-08-06 09:11:30'),
(474, 1, 'bulk_import_certification', 'cm_certification', 243, 'ADROITZ 45k AX', '2026-08-06 09:11:30'),
(475, 1, 'bulk_import_client', 'cm_client', 230, 'ALTROCKS ISO 9k ISO 27k # AX', '2026-08-06 09:11:30'),
(476, 1, 'bulk_import_client', 'cm_client', 231, 'ROBERT BORSH 45k GSI', '2026-08-06 09:11:30'),
(477, 1, 'bulk_import_certification', 'cm_certification', 244, 'ROBERT BORSH 45k GSI', '2026-08-06 09:11:30'),
(478, 1, 'bulk_import_client', 'cm_client', 232, 'Practical Analyzer Solutions Pte Ltd 45k GSI', '2026-08-06 09:11:30'),
(479, 1, 'bulk_import_certification', 'cm_certification', 245, 'Practical Analyzer Solutions Pte Ltd 45k GSI', '2026-08-06 09:11:30'),
(480, 1, 'bulk_import_client', 'cm_client', 233, 'SHOWTEC INTERNATIONAL 45K GSI', '2026-08-06 09:11:30'),
(481, 1, 'bulk_import_certification', 'cm_certification', 246, 'SHOWTEC INTERNATIONAL 45K GSI', '2026-08-06 09:11:30'),
(482, 1, 'bulk_import_client', 'cm_client', 234, 'ALL BEST GROUP - IMS_ GSI', '2026-08-06 09:11:30'),
(483, 1, 'bulk_import_certification', 'cm_certification', 247, 'ALL BEST GROUP - IMS_ GSI', '2026-08-06 09:11:30'),
(484, 1, 'bulk_import_certification', 'cm_certification', 248, 'ALL BEST GROUP - IMS_ GSI', '2026-08-06 09:11:30'),
(485, 1, 'bulk_import_certification', 'cm_certification', 249, 'ALL BEST GROUP - IMS_ GSI', '2026-08-06 09:11:30'),
(486, 1, 'bulk_import_certification', 'cm_certification', 250, 'ALL BEST GROUP - IMS_ GSI', '2026-08-06 09:11:30'),
(487, 1, 'bulk_import_client', 'cm_client', 235, 'GREEN COSMOS MARKETING 45k GSI', '2026-08-06 09:11:30'),
(488, 1, 'bulk_import_certification', 'cm_certification', 251, 'GREEN COSMOS MARKETING 45k GSI', '2026-08-06 09:11:30'),
(489, 1, 'bulk_import_client', 'cm_client', 236, 'CHCT IMS GSI', '2026-08-06 09:11:30'),
(490, 1, 'bulk_import_certification', 'cm_certification', 252, 'CHCT IMS GSI', '2026-08-06 09:11:30'),
(491, 1, 'bulk_import_certification', 'cm_certification', 253, 'CHCT IMS GSI', '2026-08-06 09:11:30'),
(492, 1, 'bulk_import_certification', 'cm_certification', 254, 'CHCT IMS GSI', '2026-08-06 09:11:30'),
(493, 1, 'bulk_import_client', 'cm_client', 237, 'AKTIO PACIFIC - 9k 45k GSI', '2026-08-06 09:11:30'),
(494, 1, 'bulk_import_certification', 'cm_certification', 255, 'AKTIO PACIFIC - 9k 45k GSI', '2026-08-06 09:11:30'),
(495, 1, 'bulk_import_certification', 'cm_certification', 256, 'AKTIO PACIFIC - 9k 45k GSI', '2026-08-06 09:11:30'),
(496, 1, 'bulk_import_client', 'cm_client', 238, 'RMJ CONSTRUCTION & ENGINEERING  3 system _RMJ', '2026-08-06 09:11:30'),
(497, 1, 'bulk_import_certification', 'cm_certification', 257, 'RMJ CONSTRUCTION & ENGINEERING  3 system _RMJ', '2026-08-06 09:11:30'),
(498, 1, 'bulk_import_certification', 'cm_certification', 258, 'RMJ CONSTRUCTION & ENGINEERING  3 system _RMJ', '2026-08-06 09:11:30'),
(499, 1, 'bulk_import_certification', 'cm_certification', 259, 'RMJ CONSTRUCTION & ENGINEERING  3 system _RMJ', '2026-08-06 09:11:30'),
(500, 1, 'bulk_import_client', 'cm_client', 239, 'SOVERUS KINGDOM IMS GSI', '2026-08-06 09:11:30'),
(501, 1, 'bulk_import_certification', 'cm_certification', 260, 'SOVERUS KINGDOM IMS GSI', '2026-08-06 09:11:30'),
(502, 1, 'bulk_import_certification', 'cm_certification', 261, 'SOVERUS KINGDOM IMS GSI', '2026-08-06 09:11:30'),
(503, 1, 'bulk_import_certification', 'cm_certification', 262, 'SOVERUS KINGDOM IMS GSI', '2026-08-06 09:11:30'),
(504, 1, 'bulk_import_client', 'cm_client', 240, 'HI-GREEN LANDSCAPE_ ISO 45 TFR AX CCC', '2026-08-06 09:11:30'),
(505, 1, 'bulk_import_certification', 'cm_certification', 263, 'HI-GREEN LANDSCAPE_ ISO 45 TFR AX CCC', '2026-08-06 09:11:30'),
(506, 1, 'bulk_import_certification', 'cm_certification', 264, 'HI-GREEN LANDSCAPE_ ISO 45 TFR AX CCC', '2026-08-06 09:11:30'),
(507, 1, 'bulk_import_certification', 'cm_certification', 265, 'HI-GREEN LANDSCAPE_ ISO 45 TFR AX CCC', '2026-08-06 09:11:30'),
(508, 1, 'bulk_import_client', 'cm_client', 241, 'GEOLUTIONS PTE LTD - ISO 9k ISO 45k_ Paul', '2026-08-06 09:11:30'),
(509, 1, 'bulk_import_certification', 'cm_certification', 266, 'GEOLUTIONS PTE LTD - ISO 9k ISO 45k_ Paul', '2026-08-06 09:11:30'),
(510, 1, 'bulk_import_certification', 'cm_certification', 267, 'GEOLUTIONS PTE LTD - ISO 9k ISO 45k_ Paul', '2026-08-06 09:11:30'),
(511, 1, 'bulk_import_client', 'cm_client', 242, 'ELECTROMECH TECHNOLOGIES PTE LTD ISO 27001 AX', '2026-08-06 09:11:30'),
(512, 1, 'bulk_import_certification', 'cm_certification', 268, 'ELECTROMECH TECHNOLOGIES PTE LTD ISO 27001 AX', '2026-08-06 09:11:30'),
(513, 1, 'bulk_import_client', 'cm_client', 243, 'GLOBAL PTE LTD 9k 45k AX', '2026-08-06 09:11:30'),
(514, 1, 'bulk_import_certification', 'cm_certification', 269, 'GLOBAL PTE LTD 9k 45k AX', '2026-08-06 09:11:30'),
(515, 1, 'bulk_import_certification', 'cm_certification', 270, 'GLOBAL PTE LTD 9k 45k AX', '2026-08-06 09:11:30'),
(516, 1, 'bulk_import_client', 'cm_client', 244, 'FJ TECHNICAL PTE. LTD. 9k 45k AX', '2026-08-06 09:11:30'),
(517, 1, 'bulk_import_certification', 'cm_certification', 271, 'FJ TECHNICAL PTE. LTD. 9k 45k AX', '2026-08-06 09:11:30'),
(518, 1, 'bulk_import_certification', 'cm_certification', 272, 'FJ TECHNICAL PTE. LTD. 9k 45k AX', '2026-08-06 09:11:30'),
(519, 1, 'bulk_import_client', 'cm_client', 245, 'URK ENGINEERING PL 45k -AX', '2026-08-06 09:11:30'),
(520, 1, 'bulk_import_certification', 'cm_certification', 273, 'URK ENGINEERING PL 45k -AX', '2026-08-06 09:11:30'),
(521, 1, 'bulk_import_client', 'cm_client', 246, 'JACIN Security Services 9&45 AX', '2026-08-06 09:11:30'),
(522, 1, 'bulk_import_certification', 'cm_certification', 274, 'JACIN Security Services 9&45 AX', '2026-08-06 09:11:30'),
(523, 1, 'bulk_import_certification', 'cm_certification', 275, 'JACIN Security Services 9&45 AX', '2026-08-06 09:11:30'),
(524, 1, 'bulk_import_client', 'cm_client', 247, 'STALWART ENGINEERING IMS AX', '2026-08-06 09:11:30'),
(525, 1, 'bulk_import_certification', 'cm_certification', 276, 'STALWART ENGINEERING IMS AX', '2026-08-06 09:11:30'),
(526, 1, 'bulk_import_certification', 'cm_certification', 277, 'STALWART ENGINEERING IMS AX', '2026-08-06 09:11:30'),
(527, 1, 'bulk_import_certification', 'cm_certification', 278, 'STALWART ENGINEERING IMS AX', '2026-08-06 09:11:30'),
(528, 1, 'bulk_import_client', 'cm_client', 248, 'CL Flooring 9k 45k # Ivy GSI', '2026-08-06 09:11:30'),
(529, 1, 'bulk_import_client', 'cm_client', 249, 'SIXES XIOLIFT PTE LTD IMS # GSI', '2026-08-06 09:11:30'),
(530, 1, 'bulk_import_certification', 'cm_certification', 279, 'SIXES XIOLIFT PTE LTD IMS # GSI', '2026-08-06 09:11:30'),
(531, 1, 'bulk_import_certification', 'cm_certification', 280, 'SIXES XIOLIFT PTE LTD IMS # GSI', '2026-08-06 09:11:30'),
(532, 1, 'bulk_import_certification', 'cm_certification', 281, 'SIXES XIOLIFT PTE LTD IMS # GSI', '2026-08-06 09:11:30'),
(533, 1, 'bulk_import_client', 'cm_client', 250, 'GOLDEN TRINITY CONSTRUCTION 45k HELMI', '2026-08-06 09:11:30'),
(534, 1, 'bulk_import_certification', 'cm_certification', 282, 'GOLDEN TRINITY CONSTRUCTION 45k HELMI', '2026-08-06 09:11:30'),
(535, 1, 'bulk_import_client', 'cm_client', 251, 'UNITEK ENGINEERING PTE. LTD. 9K 45k AX', '2026-08-06 09:11:30'),
(536, 1, 'bulk_import_certification', 'cm_certification', 283, 'UNITEK ENGINEERING PTE. LTD. 9K 45k AX', '2026-08-06 09:11:30'),
(537, 1, 'bulk_import_certification', 'cm_certification', 284, 'UNITEK ENGINEERING PTE. LTD. 9K 45k AX', '2026-08-06 09:11:30'),
(538, 1, 'bulk_import_client', 'cm_client', 252, 'SMART DOORS 9k 45k Zack/Tricia GSI', '2026-08-06 09:11:30'),
(539, 1, 'bulk_import_certification', 'cm_certification', 285, 'SMART DOORS 9k 45k Zack/Tricia GSI', '2026-08-06 09:11:30'),
(540, 1, 'bulk_import_certification', 'cm_certification', 286, 'SMART DOORS 9k 45k Zack/Tricia GSI', '2026-08-06 09:11:30'),
(541, 1, 'bulk_import_client', 'cm_client', 253, 'D CRAFT PTE. LTD. 9k 45k Zack/Vincent GSI', '2026-08-06 09:11:30'),
(542, 1, 'bulk_import_certification', 'cm_certification', 287, 'D CRAFT PTE. LTD. 9k 45k Zack/Vincent GSI', '2026-08-06 09:11:30'),
(543, 1, 'bulk_import_certification', 'cm_certification', 288, 'D CRAFT PTE. LTD. 9k 45k Zack/Vincent GSI', '2026-08-06 09:11:30'),
(544, 1, 'bulk_import_client', 'cm_client', 254, 'HONGDA ENGINEERING PTE. LTD. 45k Paul', '2026-08-06 09:11:30'),
(545, 1, 'bulk_import_certification', 'cm_certification', 289, 'HONGDA ENGINEERING PTE. LTD. 45k Paul', '2026-08-06 09:11:30'),
(546, 1, 'bulk_import_client', 'cm_client', 255, 'Anergy Solutions Pte Ltd 45K GSI Ruthu', '2026-08-06 09:11:30'),
(547, 1, 'bulk_import_certification', 'cm_certification', 290, 'Anergy Solutions Pte Ltd 45K GSI Ruthu', '2026-08-06 09:11:30'),
(548, 1, 'bulk_import_client', 'cm_client', 256, 'REGIUS BUILDER PTE. LTD. 45k #  On going', '2026-08-06 09:11:30'),
(549, 1, 'bulk_import_certification', 'cm_certification', 291, 'REGIUS BUILDER PTE. LTD. 45k #  On going', '2026-08-06 09:11:30'),
(550, 1, 'bulk_import_client', 'cm_client', 257, 'APE Engineering Pte Ltd 45k Ruthu GSI', '2026-08-06 09:11:30'),
(551, 1, 'bulk_import_certification', 'cm_certification', 292, 'APE Engineering Pte Ltd 45k Ruthu GSI', '2026-08-06 09:11:30'),
(552, 1, 'bulk_import_client', 'cm_client', 258, 'STEP-UP ENGINEERING 9k 14k 45k GSI Ruthu', '2026-08-06 09:11:30'),
(553, 1, 'bulk_import_certification', 'cm_certification', 293, 'STEP-UP ENGINEERING 9k 14k 45k GSI Ruthu', '2026-08-06 09:11:30'),
(554, 1, 'bulk_import_certification', 'cm_certification', 294, 'STEP-UP ENGINEERING 9k 14k 45k GSI Ruthu', '2026-08-06 09:11:30'),
(555, 1, 'bulk_import_certification', 'cm_certification', 295, 'STEP-UP ENGINEERING 9k 14k 45k GSI Ruthu', '2026-08-06 09:11:30'),
(556, 1, 'bulk_import_client', 'cm_client', 259, 'KIM SOON HUAT CONSTRUCTION 9k 14k 45k GSI Ruthu', '2026-08-06 09:11:30'),
(557, 1, 'bulk_import_certification', 'cm_certification', 296, 'KIM SOON HUAT CONSTRUCTION 9k 14k 45k GSI Ruthu', '2026-08-06 09:11:30'),
(558, 1, 'bulk_import_certification', 'cm_certification', 297, 'KIM SOON HUAT CONSTRUCTION 9k 14k 45k GSI Ruthu', '2026-08-06 09:11:30'),
(559, 1, 'bulk_import_certification', 'cm_certification', 298, 'KIM SOON HUAT CONSTRUCTION 9k 14k 45k GSI Ruthu', '2026-08-06 09:11:30'),
(560, 1, 'bulk_import_client', 'cm_client', 260, 'Singapore Island Country Club  45k  GSI Easan', '2026-08-06 09:11:30'),
(561, 1, 'bulk_import_certification', 'cm_certification', 299, 'Singapore Island Country Club  45k  GSI Easan', '2026-08-06 09:11:30'),
(562, 1, 'bulk_import_client', 'cm_client', 261, 'KRISE SOLUTIONS PTE LTD 9&27 Sitha', '2026-08-06 09:11:30'),
(563, 1, 'bulk_import_certification', 'cm_certification', 300, 'KRISE SOLUTIONS PTE LTD 9&27 Sitha', '2026-08-06 09:11:30'),
(564, 1, 'bulk_import_certification', 'cm_certification', 301, 'KRISE SOLUTIONS PTE LTD 9&27 Sitha', '2026-08-06 09:11:30'),
(565, 1, 'bulk_import_client', 'cm_client', 262, 'TOPWELD ENGINEERING & CONSTRUCTION 9K # AX', '2026-08-06 09:11:30'),
(566, 1, 'bulk_import_client', 'cm_client', 263, 'APEC GROUP GSI Ruthu', '2026-08-06 09:11:30'),
(567, 1, 'bulk_import_certification', 'cm_certification', 302, 'APEC GROUP GSI Ruthu', '2026-08-06 09:11:30'),
(568, 1, 'bulk_import_client', 'cm_client', 264, 'JS Door Interior IMS  AX', '2026-08-06 09:11:30'),
(569, 1, 'bulk_import_certification', 'cm_certification', 303, 'JS Door Interior IMS  AX', '2026-08-06 09:11:30'),
(570, 1, 'bulk_import_certification', 'cm_certification', 304, 'JS Door Interior IMS  AX', '2026-08-06 09:11:30'),
(571, 1, 'bulk_import_certification', 'cm_certification', 305, 'JS Door Interior IMS  AX', '2026-08-06 09:11:30'),
(572, 1, 'bulk_import_client', 'cm_client', 265, 'LUMINARY SERVICES 45K  Easan', '2026-08-06 09:11:30'),
(573, 1, 'bulk_import_certification', 'cm_certification', 306, 'LUMINARY SERVICES 45K  Easan', '2026-08-06 09:11:30'),
(574, 1, 'bulk_import_client', 'cm_client', 266, 'CHEN DA CONSTRUCTION 45k # ZACK/Vincent  GSI', '2026-08-06 09:11:30'),
(575, 1, 'bulk_import_certification', 'cm_certification', 307, 'CHEN DA CONSTRUCTION 45k # ZACK/Vincent  GSI', '2026-08-06 09:11:30'),
(576, 1, 'bulk_import_client', 'cm_client', 267, 'AC TESLA PTE. LTD.  45k # AX', '2026-08-06 09:11:30'),
(577, 1, 'bulk_import_client', 'cm_client', 268, 'KSV CONSTRUCTION IMS # AX', '2026-08-06 09:11:30'),
(578, 1, 'bulk_import_certification', 'cm_certification', 308, 'KSV CONSTRUCTION IMS # AX', '2026-08-06 09:11:30'),
(579, 1, 'bulk_import_certification', 'cm_certification', 309, 'KSV CONSTRUCTION IMS # AX', '2026-08-06 09:11:30'),
(580, 1, 'bulk_import_certification', 'cm_certification', 310, 'KSV CONSTRUCTION IMS # AX', '2026-08-06 09:11:30'),
(581, 1, 'bulk_import_client', 'cm_client', 269, 'ZHE JIANG 45k  GSI Zack', '2026-08-06 09:11:30'),
(582, 1, 'bulk_import_certification', 'cm_certification', 311, 'ZHE JIANG 45k  GSI Zack', '2026-08-06 09:11:30'),
(583, 1, 'bulk_import_client', 'cm_client', 270, 'HESTIA ENGINEERING PTE. LTD 45k Helmi', '2026-08-06 09:11:30'),
(584, 1, 'bulk_import_certification', 'cm_certification', 312, 'HESTIA ENGINEERING PTE. LTD 45k Helmi', '2026-08-06 09:11:30'),
(585, 1, 'bulk_import_client', 'cm_client', 271, 'JY EnergyGrid Pte Ltd # GSI Anita', '2026-08-06 09:11:30'),
(586, 1, 'bulk_import_client', 'cm_client', 272, 'TWINSTAR 45k AX', '2026-08-06 09:11:30'),
(587, 1, 'bulk_import_certification', 'cm_certification', 313, 'TWINSTAR 45k AX', '2026-08-06 09:11:30'),
(588, 1, 'bulk_import_client', 'cm_client', 273, 'LEE CYCLE # 9k  GSI Zack', '2026-08-06 09:11:30'),
(589, 1, 'bulk_import_certification', 'cm_certification', 314, 'LEE CYCLE # 9k  GSI Zack', '2026-08-06 09:11:30'),
(590, 1, 'bulk_import_client', 'cm_client', 274, 'WKS INDUSTRIAL GAS PTE LTD #GSI Anita', '2026-08-06 09:11:30'),
(591, 1, 'bulk_import_certification', 'cm_certification', 315, 'WKS INDUSTRIAL GAS PTE LTD #GSI Anita', '2026-08-06 09:11:30'),
(592, 1, 'bulk_import_client', 'cm_client', 275, 'YS Construction Services Pte Ltd 45k IVY GSI', '2026-08-06 09:11:30'),
(593, 1, 'bulk_import_certification', 'cm_certification', 316, 'YS Construction Services Pte Ltd 45k IVY GSI', '2026-08-06 09:11:30'),
(594, 1, 'bulk_import_client', 'cm_client', 276, 'S POWER Global IMS AX', '2026-08-06 09:11:30'),
(595, 1, 'bulk_import_certification', 'cm_certification', 317, 'S POWER Global IMS AX', '2026-08-06 09:11:30'),
(596, 1, 'bulk_import_certification', 'cm_certification', 318, 'S POWER Global IMS AX', '2026-08-06 09:11:30'),
(597, 1, 'bulk_import_certification', 'cm_certification', 319, 'S POWER Global IMS AX', '2026-08-06 09:11:30'),
(598, 1, 'bulk_import_client', 'cm_client', 277, 'JG Builders  45k 14k Easan', '2026-08-06 09:11:30'),
(599, 1, 'bulk_import_certification', 'cm_certification', 320, 'JG Builders  45k 14k Easan', '2026-08-06 09:11:30'),
(600, 1, 'bulk_import_certification', 'cm_certification', 321, 'JG Builders  45k 14k Easan', '2026-08-06 09:11:30'),
(601, 1, 'bulk_import_certification', 'cm_certification', 322, 'JG Builders  45k 14k Easan', '2026-08-06 09:11:30'),
(602, 1, 'bulk_import_client', 'cm_client', 278, 'ARUN Electrical  Solutions Pte Ltd 9and 45_Sitha', '2026-08-06 09:11:30'),
(603, 1, 'bulk_import_certification', 'cm_certification', 323, 'ARUN Electrical  Solutions Pte Ltd 9and 45_Sitha', '2026-08-06 09:11:30'),
(604, 1, 'bulk_import_certification', 'cm_certification', 324, 'ARUN Electrical  Solutions Pte Ltd 9and 45_Sitha', '2026-08-06 09:11:30'),
(605, 1, 'bulk_import_client', 'cm_client', 279, 'GLOBAL ACE CONSTRUCTION _ RMJ 45k , 9k SAC', '2026-08-06 09:11:30'),
(606, 1, 'bulk_import_certification', 'cm_certification', 325, 'GLOBAL ACE CONSTRUCTION _ RMJ 45k , 9k SAC', '2026-08-06 09:11:30'),
(607, 1, 'bulk_import_client', 'cm_client', 280, 'CHINA STAR BUILDING CONSTRUC_RMJ 9 & 14', '2026-08-06 09:11:30'),
(608, 1, 'bulk_import_certification', 'cm_certification', 326, 'CHINA STAR BUILDING CONSTRUC_RMJ 9 & 14', '2026-08-06 09:11:30'),
(609, 1, 'bulk_import_certification', 'cm_certification', 327, 'CHINA STAR BUILDING CONSTRUC_RMJ 9 & 14', '2026-08-06 09:11:30'),
(610, 1, 'bulk_import_client', 'cm_client', 281, 'CL CONSTRUCTION 9k 45k GSI Ivy #', '2026-08-06 09:11:30'),
(611, 1, 'bulk_import_certification', 'cm_certification', 328, 'CL CONSTRUCTION 9k 45k GSI Ivy #', '2026-08-06 09:11:30'),
(612, 1, 'bulk_import_client', 'cm_client', 282, 'HENG SENG HIN 45K Zack', '2026-08-06 09:11:30'),
(613, 1, 'bulk_import_certification', 'cm_certification', 329, 'HENG SENG HIN 45K Zack', '2026-08-06 09:11:30'),
(614, 1, 'bulk_import_client', 'cm_client', 283, 'SKN GALAXY (S) Pte Ltd 9k &45K # AX', '2026-08-06 09:11:30'),
(615, 1, 'bulk_import_certification', 'cm_certification', 330, 'SKN GALAXY (S) Pte Ltd 9k &45K # AX', '2026-08-06 09:11:30'),
(616, 1, 'bulk_import_client', 'cm_client', 284, 'BOLTTECH INSURANCE BROKERS PTE. LTD. 9k&45k # Jason', '2026-08-06 09:11:30'),
(617, 1, 'bulk_import_certification', 'cm_certification', 331, 'BOLTTECH INSURANCE BROKERS PTE. LTD. 9k&45k # Jason', '2026-08-06 09:11:30'),
(618, 1, 'bulk_import_certification', 'cm_certification', 332, 'BOLTTECH INSURANCE BROKERS PTE. LTD. 9k&45k # Jason', '2026-08-06 09:11:30'),
(619, 1, 'add_followup_note', 'cm_client', 62, '\"test\"', '2026-09-01 11:58:57'),
(620, 1, 'log_activity', 'cm_client', 34, 'email: Email sent', '2026-09-01 13:00:06');

-- --------------------------------------------------------

--
-- Table structure for table `cm_certifications`
--

CREATE TABLE `cm_certifications` (
  `id` int UNSIGNED NOT NULL,
  `cm_client_id` int UNSIGNED NOT NULL,
  `cm_scheme_type_id` int UNSIGNED NOT NULL,
  `accreditation_body` varchar(100) DEFAULT NULL,
  `certificate_number` varchar(100) DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `surveillance_1_date` date DEFAULT NULL,
  `surveillance_2_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `cycle_stage` enum('initial','surveillance_1','surveillance_2','recertification') NOT NULL DEFAULT 'initial',
  `status` enum('active','expired','suspended','withdrawn','pending') NOT NULL DEFAULT 'pending',
  `responsible_person_id` int UNSIGNED DEFAULT NULL,
  `responsible_person_name` varchar(150) DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `cm_certifications`
--

INSERT INTO `cm_certifications` (`id`, `cm_client_id`, `cm_scheme_type_id`, `accreditation_body`, `certificate_number`, `issue_date`, `surveillance_1_date`, `surveillance_2_date`, `expiry_date`, `cycle_stage`, `status`, `responsible_person_id`, `responsible_person_name`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 3, NULL, NULL, '2021-10-07', '2022-09-08', '2023-09-07', '2024-09-07', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(2, 2, 3, NULL, NULL, '2021-09-29', '2022-09-16', '2023-08-29', '2024-08-29', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(3, 3, 1, NULL, NULL, '2021-10-07', '2022-10-06', '2023-10-06', '2024-10-06', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(4, 3, 3, NULL, NULL, '2021-10-07', '2022-10-06', '2023-10-06', '2024-10-06', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(5, 4, 3, NULL, NULL, '2021-11-26', '2022-10-26', '2023-10-26', '2024-10-26', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(6, 5, 3, NULL, NULL, '2021-09-29', '2022-08-29', '2023-08-29', '2024-08-29', 'initial', 'active', NULL, NULL, 'Consultant: Tricia', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(7, 6, 3, NULL, NULL, '2023-08-25', '2024-07-25', '2025-07-25', '2026-07-25', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(8, 7, 3, NULL, NULL, '2021-12-14', '2022-11-14', '2023-11-14', '2024-11-14', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(9, 8, 3, NULL, NULL, '2023-12-27', '2024-11-27', '2025-11-27', '2026-11-27', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(10, 9, 3, NULL, NULL, '2023-12-19', '2024-11-19', '2025-11-19', '2026-11-19', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(11, 10, 1, NULL, NULL, '2023-12-01', '2024-11-01', '2025-11-01', '2026-11-01', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(12, 10, 3, NULL, NULL, '2023-12-01', '2024-11-01', '2025-11-01', '2026-11-01', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(13, 11, 1, NULL, NULL, '2022-11-10', '2023-10-10', '2024-10-10', '2025-10-10', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(14, 11, 2, NULL, NULL, '2022-11-10', '2023-10-10', '2024-10-10', '2025-10-10', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(15, 12, 1, NULL, NULL, '2023-11-21', '2024-10-21', '2025-10-21', '2026-10-21', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(16, 12, 2, NULL, NULL, '2023-11-21', '2024-10-21', '2025-10-21', '2026-10-21', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(17, 12, 3, NULL, NULL, '2023-11-21', '2024-10-21', '2025-10-21', '2026-10-21', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(18, 13, 3, NULL, NULL, '2023-12-18', '2024-11-18', '2025-11-18', '2026-11-18', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(19, 14, 3, NULL, NULL, '2024-02-27', '2025-01-27', '2026-01-27', '2027-01-27', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(20, 15, 3, NULL, NULL, '2023-11-06', '2024-10-06', '2025-10-06', '2026-10-06', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(21, 16, 2, NULL, NULL, '2023-10-26', '2024-09-26', '2025-09-26', '2026-09-26', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(22, 16, 3, NULL, NULL, '2023-10-26', '2024-09-26', '2025-09-26', '2026-09-26', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(23, 17, 3, NULL, NULL, '2023-11-02', '2024-10-02', '2025-10-02', '2026-10-02', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(24, 18, 3, NULL, NULL, '2022-10-14', '2023-09-14', '2024-09-14', '2025-09-14', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(25, 19, 3, NULL, NULL, '2023-11-06', '2024-10-06', '2025-10-06', '2026-10-06', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(26, 20, 1, NULL, NULL, '2023-10-26', '2024-09-26', '2025-09-26', '2026-09-26', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(27, 20, 2, NULL, NULL, '2023-10-26', '2024-09-26', '2025-09-26', '2026-09-26', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(28, 20, 3, NULL, NULL, '2023-10-26', '2024-09-26', '2025-09-26', '2026-09-26', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(29, 21, 3, NULL, NULL, '2023-10-27', '2024-09-27', '2025-09-27', '2026-09-27', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(30, 22, 1, NULL, NULL, '2023-11-06', '2024-10-06', '2025-10-06', '2026-10-06', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(31, 22, 3, NULL, NULL, '2023-11-06', '2024-10-06', '2025-10-06', '2026-10-06', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(32, 23, 3, NULL, NULL, '2024-02-27', '2025-01-27', '2026-01-27', '2027-01-27', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(33, 24, 3, NULL, NULL, '2023-11-14', '2024-10-14', '2025-10-14', '2026-10-14', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(34, 25, 1, NULL, NULL, '2023-10-09', '2024-09-09', '2025-09-09', '2026-09-09', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(35, 25, 3, NULL, NULL, '2023-10-09', '2024-09-09', '2025-09-09', '2026-09-09', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(36, 26, 2, NULL, NULL, '2023-10-19', '2024-09-19', '2025-09-19', '2026-09-19', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(37, 26, 3, NULL, NULL, '2023-10-19', '2024-09-19', '2025-09-19', '2026-09-19', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(38, 27, 3, NULL, NULL, '2022-09-16', '2023-08-16', '2024-08-16', '2025-08-16', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(39, 28, 3, NULL, NULL, '2023-10-04', '2024-09-04', '2025-09-04', '2026-09-04', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(40, 29, 3, NULL, NULL, '2023-10-18', '2024-09-18', '2025-09-18', '2026-09-18', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(41, 30, 1, NULL, NULL, '2023-09-27', '2024-08-27', '2025-08-27', '2026-08-27', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(42, 30, 2, NULL, NULL, '2023-09-27', '2024-08-27', '2025-08-27', '2026-08-27', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(43, 30, 3, NULL, NULL, '2023-09-27', '2024-08-27', '2025-08-27', '2026-08-27', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(44, 31, 3, NULL, NULL, '2023-08-19', '2024-07-19', '2025-07-19', '2026-07-19', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(45, 32, 3, NULL, NULL, '2023-08-17', '2024-07-17', '2025-07-17', '2026-07-17', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(46, 33, 1, NULL, NULL, '2022-09-28', '2023-08-28', '2024-08-28', '2025-08-28', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(47, 33, 3, NULL, NULL, '2022-09-28', '2023-08-28', '2024-08-28', '2025-08-28', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(48, 34, 3, NULL, NULL, '2021-09-14', '2022-08-14', '2023-08-14', '2024-08-14', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(49, 35, 3, NULL, NULL, '2023-08-19', '2024-07-19', '2025-07-19', '2026-07-19', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(50, 36, 3, NULL, NULL, '2023-08-18', '2024-07-18', '2025-07-18', '2026-07-18', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(51, 37, 3, NULL, NULL, '2023-08-29', '2024-07-29', '2025-07-29', '2026-07-29', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(52, 38, 1, NULL, NULL, '2023-08-17', '2024-07-17', '2025-07-17', '2026-07-17', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(53, 39, 1, 'SIS IAS', NULL, '2024-02-13', '2025-01-13', '2026-01-13', '2027-02-12', 'initial', 'active', NULL, NULL, 'Consultant: RUTHU', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(54, 39, 3, 'SIS IAS', NULL, '2024-02-13', '2025-01-13', '2026-01-13', '2027-02-12', 'initial', 'active', NULL, NULL, 'Consultant: RUTHU', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(55, 40, 1, 'SIS IAS', NULL, '2024-02-13', '2025-01-13', '2026-01-13', '2027-02-12', 'initial', 'active', NULL, NULL, 'Consultant: RUTHU', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(56, 40, 3, 'SIS IAS', NULL, '2024-02-13', '2025-01-13', '2026-01-13', '2027-02-12', 'initial', 'active', NULL, NULL, 'Consultant: RUTHU', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(57, 41, 8, 'SIS IAS', NULL, '2025-12-20', '2026-11-20', '2027-11-20', '2028-12-19', 'initial', 'active', NULL, NULL, 'Consultant: EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(58, 42, 8, 'SIS IAS', NULL, '2026-04-22', '2028-04-22', '2028-04-22', '2029-04-21', 'initial', 'active', NULL, NULL, 'Consultant: Others/Tricia', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(59, 43, 8, 'SIS IAS', NULL, '2025-12-16', '2026-11-16', '2027-11-16', '2028-12-15', 'initial', 'active', NULL, NULL, 'Consultant: RUTHU/ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(60, 44, 3, 'SIS IAS', NULL, '2025-01-09', '2026-01-08', '2027-01-08', '2028-01-08', 'initial', 'active', NULL, NULL, 'Consultant: ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(61, 45, 3, 'SIS IAS', NULL, '2025-01-09', '2026-01-09', '2026-01-09', '2028-01-08', 'initial', 'active', NULL, NULL, 'Consultant: ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(62, 46, 3, 'SIS IAS', NULL, '2025-12-03', '2026-12-03', '2027-12-03', '2028-12-02', 'initial', 'active', NULL, NULL, 'Consultant: BIZMEDIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(63, 47, 3, 'SIS IAS', NULL, '2024-10-01', '2025-10-01', '2026-10-01', '2027-09-30', 'initial', 'active', NULL, NULL, 'Consultant: EASAN/ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(64, 48, 3, 'SIS IAS', NULL, '2024-07-08', '2025-07-08', '2026-07-08', '2027-07-07', 'initial', 'active', NULL, NULL, 'Consultant: WSH EXPERT | Source Client Status: Not Reply', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(65, 49, 3, 'SIS IAS', NULL, '2023-12-18', '2024-12-18', '2025-12-18', '2026-12-17', 'initial', 'active', NULL, NULL, 'Consultant: ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(66, 50, 4, 'SIS IAS', NULL, '2024-10-29', '2025-10-29', '2026-10-29', '2027-10-28', 'initial', 'active', NULL, NULL, 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(67, 51, 3, 'SIS IAS', NULL, '2025-12-03', '2026-12-03', '2027-12-03', '2028-12-03', 'initial', 'active', NULL, NULL, 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(68, 52, 3, 'SIS IAS', NULL, '2025-12-01', '2026-12-01', '2027-12-01', '2028-12-02', 'initial', 'active', NULL, NULL, 'Consultant: EASAN/PANDIAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(69, 53, 3, 'SIS IAS', NULL, '2023-11-05', '2024-11-05', '2025-11-05', '2026-11-05', 'initial', 'active', NULL, NULL, 'Consultant: EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(70, 54, 3, 'SIS IAS', NULL, '2024-12-02', '2025-12-02', '2026-12-02', '2027-12-01', 'initial', 'active', NULL, NULL, 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(71, 55, 3, 'SIS IAS', NULL, '2025-11-10', '2026-11-10', '2027-11-10', '2028-11-09', 'initial', 'active', NULL, NULL, 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(72, 56, 3, 'SIS IAS', NULL, '2023-10-30', '2024-10-30', '2025-10-30', '2026-11-01', 'initial', 'active', NULL, NULL, 'Consultant: ZACK/RUTHU', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(73, 57, 1, 'SIS IAS', NULL, '2024-09-10', '2025-09-10', '2026-09-10', '2027-09-11', 'initial', 'active', NULL, NULL, 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(74, 58, 1, 'SIS IAS', NULL, '2023-10-24', '2024-10-24', '2025-10-24', '2026-10-25', 'initial', 'active', NULL, NULL, 'Consultant: ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(75, 58, 2, 'SIS IAS', NULL, '2023-10-24', '2024-10-24', '2025-10-24', '2026-10-25', 'initial', 'active', NULL, NULL, 'Consultant: ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(76, 58, 3, 'SIS IAS', NULL, '2023-10-24', '2024-10-24', '2025-10-24', '2026-10-25', 'initial', 'active', NULL, NULL, 'Consultant: ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(77, 59, 2, 'SIS IAS', NULL, '2023-10-17', '2024-10-17', '2025-10-17', '2026-10-18', 'initial', 'active', NULL, NULL, 'Consultant: WSH EXPERT', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(78, 59, 3, 'SIS IAS', NULL, '2023-10-17', '2024-10-17', '2025-10-17', '2026-10-18', 'initial', 'active', NULL, NULL, 'Consultant: WSH EXPERT', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(79, 60, 2, 'SIS IAS', NULL, '2024-10-22', '2025-10-22', '2026-10-22', '2027-10-23', 'initial', 'active', NULL, NULL, 'Consultant: RUTHU', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(80, 61, 3, 'SIS IAS', NULL, '2024-08-05', '2025-08-05', '2026-07-05', '2027-08-06', 'initial', 'active', NULL, NULL, 'Consultant: EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(81, 62, 1, 'SIS IAS', NULL, '2024-09-03', '2025-09-03', '2026-09-03', '2027-09-04', 'initial', 'active', NULL, NULL, 'Consultant: ZACK/RUTHU', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(82, 62, 3, 'SIS IAS', NULL, '2024-09-03', '2025-09-03', '2026-09-03', '2027-09-04', 'initial', 'active', NULL, NULL, 'Consultant: ZACK/RUTHU', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(83, 63, 1, 'SIS IAS', NULL, '2024-08-21', '2025-08-21', '2026-08-21', '2027-08-20', 'initial', 'active', NULL, NULL, 'Consultant: ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(84, 63, 3, 'SIS IAS', NULL, '2024-08-21', '2025-08-21', '2026-08-21', '2027-08-20', 'initial', 'active', NULL, NULL, 'Consultant: ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(85, 64, 8, 'SIS IAS', NULL, '2024-12-29', '2025-12-29', '2026-12-29', '2027-12-29', 'initial', 'active', NULL, NULL, 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(86, 65, 3, 'SIS IAS', NULL, '2024-08-13', '2025-08-13', '2026-07-13', '2027-08-12', 'initial', 'active', NULL, NULL, 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(87, 66, 3, 'SIS IAS', NULL, '2023-08-17', '2024-08-17', '2025-08-17', '2026-08-16', 'initial', 'active', NULL, NULL, 'Consultant: WSH EXPERT', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(88, 67, 3, 'SIS IAS', NULL, '2023-08-29', '2024-08-29', '2025-08-29', '2026-08-28', 'initial', 'active', NULL, NULL, 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(89, 68, 3, 'SIS IAS', NULL, '2023-06-18', '2024-06-18', '2025-06-18', '2026-07-17', 'initial', 'active', NULL, NULL, 'Consultant: OWN | Source Client Status: Not Reply', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(90, 69, 3, 'SIS IAS', NULL, '2023-10-18', '2024-10-18', '2025-10-18', '2026-10-17', 'initial', 'active', NULL, NULL, 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(91, 70, 3, 'SIS IAS', NULL, '2024-09-05', '2025-09-05', '2026-09-05', '2027-09-04', 'initial', 'active', NULL, NULL, 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(92, 71, 3, 'SIS IAS', NULL, '2024-08-16', '2025-08-16', '2026-07-16', '2027-08-15', 'initial', 'active', NULL, NULL, 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(93, 72, 3, 'SIS IAS', NULL, '2023-08-25', '2024-08-25', '2025-08-25', '2026-08-24', 'initial', 'active', NULL, NULL, 'Consultant: EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(94, 73, 3, 'SIS IAS', NULL, '2024-07-11', '2025-07-11', '2026-06-11', '2027-07-10', 'initial', 'active', NULL, NULL, 'Consultant: void', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(95, 74, 3, 'SIS IAS', NULL, '2024-07-08', '2025-07-08', '2026-06-08', '2027-07-07', 'initial', 'active', NULL, NULL, 'Consultant: WSH EXPERT | Source Client Status: Not Reply', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(96, 75, 1, 'SIS IAS', NULL, '2024-08-07', '2025-08-07', '2026-08-07', '2027-08-06', 'initial', 'active', NULL, NULL, 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(97, 75, 3, 'SIS IAS', NULL, '2024-08-07', '2025-08-07', '2026-08-07', '2027-08-06', 'initial', 'active', NULL, NULL, 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(98, 76, 3, 'SIS IAS', NULL, '2023-08-18', '2024-08-18', '2025-08-18', '2026-08-17', 'initial', 'active', NULL, NULL, 'Consultant: WSH EXPERT', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(99, 77, 3, 'SIS IAS', NULL, '2023-08-19', '2024-08-19', '2025-08-19', '2026-08-18', 'initial', 'active', NULL, NULL, 'Consultant: WSH EXPERT', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(100, 78, 1, 'SIS IAS', NULL, '2024-07-08', '2025-07-08', '2026-07-08', '2027-07-07', 'initial', 'active', NULL, NULL, 'Consultant: ZACK | Source Client Status: Not Reply', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(101, 22, 1, 'SIS IAS', NULL, '2023-11-06', '2024-11-06', '2025-11-06', '2026-11-05', 'initial', 'active', NULL, NULL, 'Consultant: ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(102, 22, 3, 'SIS IAS', NULL, '2023-11-06', '2024-11-06', '2025-11-06', '2026-11-05', 'initial', 'active', NULL, NULL, 'Consultant: ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(103, 79, 3, 'SIS IAS', NULL, '2025-06-28', '2026-06-28', '2027-06-28', '2028-06-27', 'initial', 'active', NULL, NULL, 'Consultant: OWN | Source Client Status: Invoice - Send need to chase payment', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(104, 80, 4, 'SIS IAS', NULL, '2025-06-07', '2026-06-07', '2027-06-07', '2028-06-06', 'initial', 'active', NULL, NULL, 'Consultant: OWN | Source Client Status: Void', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(105, 81, 3, 'SIS IAS', NULL, '2024-07-08', '2025-07-08', '2026-07-08', '2027-07-07', 'initial', 'active', NULL, NULL, 'Consultant: ZACK | Source Client Status: Not Reply', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(106, 82, 3, 'SIS IAS', NULL, '2023-05-15', '2024-05-15', '2025-05-15', '2026-05-14', 'initial', 'active', NULL, NULL, 'Consultant: OWN | Source Client Status: Maybe will signed back', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(107, 83, 4, 'SIS IAS', NULL, '2025-08-02', '2026-08-02', '2027-08-02', '2028-08-01', 'initial', 'active', NULL, NULL, 'Consultant: RUTHU/TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(108, 84, 1, 'SIS IAS', NULL, '2025-06-05', '2026-06-05', '2027-06-05', '2028-06-04', 'initial', 'active', NULL, NULL, 'Consultant: RUTHU / TRICIA | Source Client Status: Need to wait GSI', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(109, 85, 3, 'SIS IAS', NULL, '2024-05-11', '2025-05-11', '2026-05-11', '2027-05-10', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(110, 86, 3, 'SIS IAS', NULL, '2026-01-23', '2027-01-23', '2028-01-23', '2029-01-22', 'initial', 'active', NULL, NULL, 'Consultant: TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(111, 87, 3, 'SIS IAS', NULL, '2024-02-15', '2025-02-15', '2026-02-15', '2027-02-14', 'initial', 'active', NULL, NULL, 'Consultant: RUTHU', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(112, 88, 1, 'SIS IAS', NULL, '2024-02-13', '2025-02-13', '2026-02-13', '2027-02-12', 'initial', 'active', NULL, NULL, 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(113, 88, 3, 'SIS IAS', NULL, '2024-02-13', '2025-02-13', '2026-02-13', '2027-02-12', 'initial', 'active', NULL, NULL, 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(114, 89, 1, 'SIS IAS', NULL, '2026-03-04', '2027-03-04', '2028-03-04', '2029-03-03', 'initial', 'active', NULL, NULL, 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(115, 89, 3, 'SIS IAS', NULL, '2026-03-04', '2027-03-04', '2028-03-04', '2029-03-03', 'initial', 'active', NULL, NULL, 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(116, 90, 1, 'SIS IAS', NULL, '2024-05-13', '2025-05-13', '2026-05-13', '2027-05-12', 'initial', 'active', NULL, NULL, 'Consultant: TRICIA | Source Client Status: Done', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(117, 91, 3, 'SIS IAS', NULL, '2024-04-01', '2025-04-01', '2026-04-01', '2027-03-31', 'initial', 'active', NULL, NULL, 'Consultant: TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(118, 92, 3, 'SIS IAS', NULL, '2024-02-15', '2025-02-15', '2026-02-15', '2027-02-14', 'initial', 'active', NULL, NULL, 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(119, 93, 3, 'SIS IAS', NULL, '2024-02-13', '2025-02-13', '2026-02-13', '2027-02-12', 'initial', 'active', NULL, NULL, 'Consultant: TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(120, 94, 3, 'SIS IAS', NULL, '2024-03-13', '2025-03-13', '2026-03-13', '2027-03-12', 'initial', 'active', NULL, NULL, 'Consultant: EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(121, 95, 3, 'SIS IAS', NULL, '2024-02-13', '2025-02-13', '2026-02-13', '2027-02-12', 'initial', 'active', NULL, NULL, 'Consultant: EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(122, 96, 1, 'SIS IAS', NULL, '2024-03-28', '2025-03-28', '2026-03-28', '2027-03-27', 'initial', 'active', NULL, NULL, 'Consultant: TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(123, 96, 3, 'SIS IAS', NULL, '2024-03-28', '2025-03-28', '2026-03-28', '2027-03-27', 'initial', 'active', NULL, NULL, 'Consultant: TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(124, 97, 1, 'SIS IAS', NULL, '2024-03-26', '2025-03-26', '2026-03-26', '2027-03-25', 'initial', 'active', NULL, NULL, 'Consultant: TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(125, 97, 3, 'SIS IAS', NULL, '2024-03-26', '2025-03-26', '2026-03-26', '2027-03-25', 'initial', 'active', NULL, NULL, 'Consultant: TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(126, 98, 1, 'SIS IAS', NULL, '2024-03-26', '2025-03-26', '2026-03-26', '2027-03-25', 'initial', 'active', NULL, NULL, 'Consultant: TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(127, 98, 3, 'SIS IAS', NULL, '2024-03-26', '2025-03-26', '2026-03-26', '2027-03-25', 'initial', 'active', NULL, NULL, 'Consultant: TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(128, 99, 1, 'SIS IAS', NULL, '2024-03-26', '2025-03-26', '2026-03-26', '2027-03-25', 'initial', 'active', NULL, NULL, 'Consultant: TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(129, 99, 3, 'SIS IAS', NULL, '2024-03-26', '2025-03-26', '2026-03-26', '2027-03-25', 'initial', 'active', NULL, NULL, 'Consultant: TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(130, 100, 1, 'SIS IAS', NULL, '2024-03-26', '2025-03-26', '2026-03-26', '2027-03-25', 'initial', 'active', NULL, NULL, 'Consultant: TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(131, 100, 3, 'SIS IAS', NULL, '2024-03-26', '2025-03-26', '2026-03-26', '2027-03-25', 'initial', 'active', NULL, NULL, 'Consultant: TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(132, 101, 1, 'SIS IAS', NULL, '2024-05-09', '2025-05-09', '2026-05-09', '2027-05-08', 'initial', 'active', NULL, NULL, 'Consultant: TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(133, 101, 2, 'SIS IAS', NULL, '2024-05-09', '2025-05-09', '2026-05-09', '2027-05-08', 'initial', 'active', NULL, NULL, 'Consultant: TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(134, 101, 3, 'SIS IAS', NULL, '2024-05-09', '2025-05-09', '2026-05-09', '2027-05-08', 'initial', 'active', NULL, NULL, 'Consultant: TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(135, 102, 1, 'SIS IAS', NULL, '2024-05-13', '2025-05-13', '2026-05-13', '2027-05-12', 'initial', 'active', NULL, NULL, 'Consultant: TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(136, 102, 2, 'SIS IAS', NULL, '2024-05-13', '2025-05-13', '2026-05-13', '2027-05-12', 'initial', 'active', NULL, NULL, 'Consultant: TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(137, 102, 3, 'SIS IAS', NULL, '2024-05-13', '2025-05-13', '2026-05-13', '2027-05-12', 'initial', 'active', NULL, NULL, 'Consultant: TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(138, 103, 1, 'SIS IAS', NULL, '2024-02-19', '2025-02-19', '2026-02-19', '2027-02-18', 'initial', 'active', NULL, NULL, 'Consultant: TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(139, 103, 2, 'SIS IAS', NULL, '2024-02-19', '2025-02-19', '2026-02-19', '2027-02-18', 'initial', 'active', NULL, NULL, 'Consultant: TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(140, 103, 3, 'SIS IAS', NULL, '2024-02-19', '2025-02-19', '2026-02-19', '2027-02-18', 'initial', 'active', NULL, NULL, 'Consultant: TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(141, 104, 3, 'SIS IAS', NULL, '2024-05-11', '2025-05-11', '2026-05-11', '2027-05-10', 'initial', 'active', NULL, NULL, 'Consultant: TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(142, 105, 1, 'SIS IAS', NULL, '2024-02-19', '2025-02-19', '2026-02-19', '2027-02-18', 'initial', 'active', NULL, NULL, 'Consultant: RUTHU', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(143, 106, 3, 'SIS IAS', NULL, '2025-04-11', '2026-04-11', '2027-04-11', '2028-04-10', 'initial', 'active', NULL, NULL, 'Consultant: EASAN | Source Client Status: By Mr.Pandian', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(144, 107, 3, 'SIS IAS', NULL, '2025-02-11', '2026-02-11', '2027-02-11', '2028-02-10', 'initial', 'active', NULL, NULL, 'Consultant: RUTHU', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(145, 108, 1, 'SIS IAS', NULL, '2025-04-12', '2026-04-12', '2027-04-12', '2028-02-11', 'initial', 'active', NULL, NULL, 'Consultant: SITHA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(146, 108, 3, 'SIS IAS', NULL, '2025-04-12', '2026-04-12', '2027-04-12', '2028-02-11', 'initial', 'active', NULL, NULL, 'Consultant: SITHA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(147, 109, 1, 'SIS IAS', NULL, '2025-04-12', '2026-04-12', '2027-04-12', '2028-04-11', 'initial', 'active', NULL, NULL, 'Consultant: SITHA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(148, 109, 3, 'SIS IAS', NULL, '2025-04-12', '2026-04-12', '2027-04-12', '2028-04-11', 'initial', 'active', NULL, NULL, 'Consultant: SITHA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(149, 110, 1, 'SIS IAS', NULL, '2025-03-26', '2026-03-26', '2027-03-26', '2028-03-25', 'initial', 'active', NULL, NULL, 'Consultant: EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(150, 110, 3, 'SIS IAS', NULL, '2025-03-26', '2026-03-26', '2027-03-26', '2028-03-25', 'initial', 'active', NULL, NULL, 'Consultant: EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(151, 111, 1, 'SIS IAS', NULL, '2024-11-05', '2025-11-05', '2026-11-05', '2027-11-04', 'initial', 'active', NULL, NULL, 'Consultant: ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(152, 111, 3, 'SIS IAS', NULL, '2024-11-05', '2025-11-05', '2026-11-05', '2027-11-04', 'initial', 'active', NULL, NULL, 'Consultant: ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(153, 112, 8, 'SIS IAS', NULL, '2025-12-15', '2026-12-15', '2027-12-15', '2028-12-14', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(154, 113, 3, 'SIS IAS', NULL, '2024-02-27', '2025-02-27', '2026-02-27', '2027-02-26', 'initial', 'active', NULL, NULL, 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(155, 114, 3, 'SIS IAS', NULL, '2023-11-06', '2024-11-06', '2024-11-06', '2026-11-05', 'initial', 'active', NULL, NULL, 'Consultant: WSH EXPERT', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(156, 115, 3, 'SIS IAS', NULL, '2023-11-06', '2024-11-06', '2024-11-06', '2026-11-05', 'initial', 'active', NULL, NULL, 'Consultant: EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(157, 116, 1, 'SIS IAS', NULL, '2023-10-09', '2024-10-09', '2024-10-09', '2026-10-08', 'initial', 'active', NULL, NULL, 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(158, 116, 3, 'SIS IAS', NULL, '2023-10-09', '2024-10-09', '2024-10-09', '2026-10-08', 'initial', 'active', NULL, NULL, 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(159, 117, 3, 'SIS IAS', NULL, '2023-10-04', '2024-10-04', '2024-10-04', '2026-10-03', 'initial', 'active', NULL, NULL, 'Consultant: ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(160, 118, 1, 'SIS IAS', NULL, '2022-07-18', '2023-07-18', '2024-07-18', '2025-07-17', 'initial', 'active', NULL, NULL, 'Consultant: ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(161, 119, 3, 'SIS IAS', NULL, '2023-08-19', '2024-08-19', '2025-08-19', '2026-08-18', 'initial', 'active', NULL, NULL, 'Consultant: WSH EXPERT', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(162, 120, 3, 'SIS IAS', NULL, '2025-12-03', '2026-12-03', '2027-12-03', '2028-12-02', 'initial', 'active', NULL, NULL, 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(163, 121, 3, 'SIS IAS', NULL, '2024-07-12', '2025-07-12', '2026-07-12', '2027-07-11', 'initial', 'active', NULL, NULL, 'Consultant: ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(164, 122, 3, 'SIS IAS', NULL, '2023-06-13', '2024-06-13', '2025-06-13', '2026-06-12', 'initial', 'active', NULL, NULL, 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(165, 123, 3, 'SIS IAS', NULL, '2023-06-13', '2024-06-13', '2025-06-13', '2026-06-12', 'initial', 'active', NULL, NULL, 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(166, 125, 3, 'SIS IAS', NULL, '2024-06-04', '2025-06-04', '2026-06-04', '2027-06-03', 'initial', 'active', NULL, NULL, 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(167, 126, 3, 'SIS IAS', NULL, '2024-05-21', '2025-05-21', '2026-05-21', '2027-05-20', 'initial', 'active', NULL, NULL, 'Consultant: OWN | Source Client Status: Check', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(168, 127, 3, 'SIS IAS', NULL, '2024-04-29', '2025-04-29', '2026-04-29', '2027-04-28', 'initial', 'active', NULL, NULL, 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(169, 128, 3, 'SIS IAS', NULL, '2024-04-29', '2025-04-29', '2026-04-29', '2027-04-28', 'initial', 'active', NULL, NULL, 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(170, 129, 3, 'SIS IAS', NULL, '2024-03-14', '2025-03-14', '2026-03-14', '2027-03-13', 'initial', 'active', NULL, NULL, 'Consultant: ZACK/TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(171, 130, 3, 'SIS IAS', NULL, '2024-02-13', '2025-02-13', '2026-02-13', '2027-02-12', 'initial', 'active', NULL, NULL, 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(172, 131, 1, 'SIS IAS', NULL, '2024-02-19', '2025-02-19', '2026-02-19', '2027-02-18', 'initial', 'active', NULL, NULL, 'Consultant: TRICIA/ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(173, 131, 2, 'SIS IAS', NULL, '2024-02-19', '2025-02-19', '2026-02-19', '2027-02-18', 'initial', 'active', NULL, NULL, 'Consultant: TRICIA/ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(174, 131, 3, 'SIS IAS', NULL, '2024-02-19', '2025-02-19', '2026-02-19', '2027-02-18', 'initial', 'active', NULL, NULL, 'Consultant: TRICIA/ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(175, 132, 1, 'SIS IAS', NULL, '2024-02-03', '2025-02-03', '2026-02-03', '2027-01-03', 'initial', 'active', NULL, NULL, 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(176, 132, 2, 'SIS IAS', NULL, '2024-02-03', '2025-02-03', '2026-02-03', '2027-01-03', 'initial', 'active', NULL, NULL, 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(177, 132, 3, 'SIS IAS', NULL, '2024-02-03', '2025-02-03', '2026-02-03', '2027-01-03', 'initial', 'active', NULL, NULL, 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(178, 133, 1, 'SIS IAS', NULL, '2024-03-23', '2025-03-23', '2026-03-23', '2027-03-22', 'initial', 'active', NULL, NULL, 'Consultant: EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(179, 134, 3, 'SIS IAS', NULL, '2024-02-21', '2025-02-21', '2026-02-21', '2027-02-20', 'initial', 'active', NULL, NULL, 'Consultant: WSH EXPERT', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(180, 135, 1, 'SAC', NULL, '2024-02-16', '2025-02-16', '2026-02-16', '2027-02-15', 'initial', 'active', NULL, NULL, 'Consultant: EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(181, 135, 2, 'SAC', NULL, '2024-02-16', '2025-02-16', '2026-02-16', '2027-02-15', 'initial', 'active', NULL, NULL, 'Consultant: EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(182, 135, 3, 'SAC', NULL, '2024-02-16', '2025-02-16', '2026-02-16', '2027-02-15', 'initial', 'active', NULL, NULL, 'Consultant: EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(183, 136, 1, 'SAC', NULL, '2024-04-04', '2025-04-04', '2026-04-04', '2027-05-24', 'initial', 'active', NULL, NULL, 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(184, 136, 2, 'SAC', NULL, '2024-04-04', '2025-04-04', '2026-04-04', '2027-05-24', 'initial', 'active', NULL, NULL, 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(185, 136, 3, 'SAC', NULL, '2024-04-04', '2025-04-04', '2026-04-04', '2027-05-24', 'initial', 'active', NULL, NULL, 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(186, 140, 3, 'SAC', NULL, '2024-08-12', '2025-08-12', '2026-08-12', '2027-08-31', 'initial', 'active', NULL, NULL, 'Consultant: EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(187, 204, 3, 'JAS-ANZ', 'EUCA2434- OSH001-1', '2024-11-29', NULL, NULL, '2027-11-28', 'initial', 'active', NULL, NULL, 'ISIC 41001 | EHS Client No: EUCA2423', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(188, 205, 3, 'JAS-ANZ', 'EUCA2424- OSH001-1', '2024-11-22', NULL, NULL, '2027-11-21', 'initial', 'active', NULL, NULL, 'ISIC 46549 | EHS Client No: EUCA2433', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(189, 206, 1, 'JAS-ANZ', 'EUCA2025-QS001', '2024-10-03', NULL, NULL, '2027-10-02', 'initial', 'active', NULL, NULL, 'ISIC 41001 | EHS Client No: EUCA2425', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(190, 206, 2, 'JAS-ANZ', 'EUCA2025- ES001', '2024-10-03', NULL, NULL, '2027-10-02', 'initial', 'active', NULL, NULL, 'ISIC 41001 | EHS Client No: EUCA2425', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(191, 206, 3, 'JAS-ANZ', 'EUCA2025- OSH001', '2024-10-03', NULL, NULL, '2027-10-02', 'initial', 'active', NULL, NULL, 'ISIC 41001 | EHS Client No: EUCA2425', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(192, 207, 1, 'JAS-ANZ', 'EUCA2428-QS001', '2024-10-15', NULL, NULL, '2027-10-14', 'initial', 'active', NULL, NULL, 'ISIC 41009 | EHS Client No: EUCA2428', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(193, 207, 3, 'JAS-ANZ', 'EUCA2428- OSH01', '2024-10-15', NULL, NULL, '2027-10-14', 'initial', 'active', NULL, NULL, 'ISIC 41009 | EHS Client No: EUCA2428', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(194, 1, 3, 'JAS-ANZ', 'EUCA2429- OSH001-1', '2024-10-25', NULL, NULL, '2027-10-24', 'initial', 'active', NULL, NULL, 'ISIC 41001 | EHS Client No: EUCA2429', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(195, 111, 1, 'JAS-ANZ', 'EUCA2430-QS001', '2024-11-05', NULL, NULL, '2027-11-04', 'initial', 'active', NULL, NULL, 'ISIC 16291 | EHS Client No: EUCA2430', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(196, 111, 2, 'JAS-ANZ', 'EUCA2430- ES001', '2024-11-05', NULL, NULL, '2027-11-04', 'initial', 'active', NULL, NULL, 'ISIC 16291 | EHS Client No: EUCA2430', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(197, 111, 3, 'JAS-ANZ', 'EUCA2430- OSH001-1', '2024-11-05', NULL, NULL, '2027-11-04', 'initial', 'active', NULL, NULL, 'ISIC 16291 | EHS Client No: EUCA2430', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(198, 208, 3, 'JAS-ANZ', 'EUCA2431- OSH001-1', '2024-11-05', NULL, NULL, '2027-11-04', 'initial', 'active', NULL, NULL, 'ISIC 43210 | EHS Client No: EUCA2431', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(199, 209, 1, 'JAS-ANZ', 'EUCA2432-QS001', '2024-12-14', NULL, NULL, '2027-12-13', 'initial', 'active', NULL, NULL, 'ISIC 41001 | EHS Client No: EUCA2432', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(200, 209, 3, 'JAS-ANZ', 'EUCA2432- OSH001-1', '2024-12-14', NULL, NULL, '2027-12-13', 'initial', 'active', NULL, NULL, 'ISIC 41001 | EHS Client No: EUCA2432', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(201, 210, 3, 'JAS-ANZ', 'EUCA2515- OSH001-1', '2025-05-17', NULL, NULL, '2028-05-16', 'initial', 'active', NULL, NULL, 'Consultant: Eddie | ISIC 41001 | EHS Client No: EUCA2515', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(202, 211, 3, 'JAS-ANZ', 'EUCA2518- OSH001-1', '2025-06-16', NULL, NULL, '2028-06-15', 'initial', 'active', NULL, NULL, 'Consultant: ruthu | ISIC 41001 | EHS Client No: EUCA2518', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(203, 212, 3, 'JAS-ANZ', 'EUCA2519- OSH001-1', '2025-07-16', NULL, NULL, '2028-07-15', 'initial', 'active', NULL, NULL, 'Consultant: Own | ISIC 41001 | EHS Client No: EUCA2519', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(204, 212, 2, 'JAS-ANZ', 'EUCA2519- ES001', '2025-07-16', NULL, NULL, '2028-07-15', 'initial', 'active', NULL, NULL, 'Consultant: own | ISIC 41001 | EHS Client No: EUCA2519', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(205, 212, 1, 'JAS-ANZ', 'EUCA2519-QS001', '2025-07-16', NULL, NULL, '2028-07-15', 'initial', 'active', NULL, NULL, 'Consultant: Own | ISIC 41001 | EHS Client No: EUCA2519', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(206, 213, 3, 'JAS-ANZ', 'EUCA2525- OSH001-1', '2025-08-16', NULL, NULL, '2028-08-15', 'initial', 'active', NULL, NULL, 'Consultant: Gsi | ISIC 43293 | EHS Client No: EUCA2525', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(207, 214, 3, 'JAS-ANZ', 'EUCA2529- OSH001-1', '2025-09-10', NULL, NULL, '2028-09-09', 'initial', 'active', NULL, NULL, 'ISIC 42101 | EHS Client No: EUCA2529', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(208, 215, 1, 'JAS-ANZ', 'EUCA2535-QS001', '2025-10-15', NULL, NULL, '2028-10-14', 'initial', 'active', NULL, NULL, 'ISIC 41001 | EHS Client No: EUCA2535', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(209, 215, 1, 'JAS-ANZ', 'EUCA2535- OSH001-1', '2025-10-15', NULL, NULL, '2028-10-14', 'initial', 'active', NULL, NULL, 'ISIC 41001 | EHS Client No: EUCA2535', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(210, 216, 1, 'JAS-ANZ', 'EUCA2536-QS001', '2025-12-08', NULL, NULL, '2028-12-07', 'initial', 'active', NULL, NULL, 'ISIC 41001 | EHS Client No: EUCA2536', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(211, 216, 2, 'JAS-ANZ', 'EUCA2536- ES001', '2025-12-08', NULL, NULL, '2028-12-07', 'initial', 'active', NULL, NULL, 'ISIC 41001 | EHS Client No: EUCA2536', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(212, 216, 3, 'JAS-ANZ', 'EUCA2536- OSH001-1', '2025-12-08', NULL, NULL, '2028-12-07', 'initial', 'active', NULL, NULL, 'ISIC 41001 | EHS Client No: EUCA2536', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(213, 217, 1, 'JAS-ANZ', 'EUCA2537-QS001', '2025-10-20', NULL, NULL, '2028-10-19', 'initial', 'active', NULL, NULL, 'ISIC 41001 | EHS Client No: EUCA2537', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(214, 217, 2, 'JAS-ANZ', 'EUCA2537- ES001', '2025-10-20', NULL, NULL, '2028-10-19', 'initial', 'active', NULL, NULL, 'ISIC 41001 | EHS Client No: EUCA2537', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(215, 217, 3, 'JAS-ANZ', 'EUCA2537- OSH001-1', '2025-10-20', NULL, NULL, '2028-10-19', 'initial', 'active', NULL, NULL, 'ISIC 41001 | EHS Client No: EUCA2537', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(216, 218, 1, 'JAS-ANZ', 'EUCA2540-QS001', '2025-11-18', NULL, NULL, '2028-11-17', 'initial', 'active', NULL, NULL, 'ISIC 46639 | EHS Client No: EUCA2540', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(217, 218, 3, 'JAS-ANZ', 'EUCA2540- OSH001-1', '2025-11-18', NULL, NULL, '2028-11-17', 'initial', 'active', NULL, NULL, 'ISIC 46639 | EHS Client No: EUCA2540', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(218, 219, 1, 'JAS-ANZ', 'EUCA2541-QS001', '2025-10-25', NULL, NULL, '2028-10-24', 'initial', 'active', NULL, NULL, 'ISIC 28169 | EHS Client No: EUCA2541', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(219, 220, 1, 'JAS-ANZ', 'EUCA2543-QS001', '2025-11-26', NULL, NULL, '2028-11-25', 'initial', 'active', NULL, NULL, 'ISIC 81211 | EHS Client No: EUCA2543', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(220, 221, 1, 'JAS-ANZ', 'EUCA2545-QS001', '2025-12-27', NULL, NULL, '2028-12-26', 'initial', 'active', NULL, NULL, 'ISIC 42101 | EHS Client No: EUCA2545', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(221, 221, 3, 'JAS-ANZ', 'EUCA2545- OSH001-1', '2025-12-27', NULL, NULL, '2028-12-26', 'initial', 'active', NULL, NULL, 'ISIC 42101 | EHS Client No: EUCA2545', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(222, 222, 1, 'JAS-ANZ', 'EUCA2547-QS001', '2025-12-05', NULL, NULL, '2028-12-04', 'initial', 'active', NULL, NULL, 'ISIC 43220 | EHS Client No: EUCA2547', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(223, 222, 3, 'JAS-ANZ', 'EUCA2547- OSH001-1', '2025-12-05', NULL, NULL, '2028-12-04', 'initial', 'active', NULL, NULL, 'ISIC 43220 | EHS Client No: EUCA2547', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(224, 224, 1, 'AxisCert', NULL, '2025-12-09', '2026-11-09', '2027-11-09', '2028-11-09', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(225, 224, 2, 'AxisCert', NULL, '2025-12-09', '2026-11-09', '2027-11-09', '2028-11-09', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(226, 224, 3, 'AxisCert', NULL, '2025-12-09', '2026-11-09', '2027-11-09', '2028-11-09', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(227, 224, 4, 'AxisCert', NULL, '2025-12-09', '2026-11-09', '2027-11-09', '2028-11-09', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(228, 225, 1, 'AxisCert', NULL, '2025-12-09', '2026-11-09', '2027-11-09', '2028-11-09', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(229, 225, 2, 'AxisCert', NULL, '2025-12-09', '2026-11-09', '2027-11-09', '2028-11-09', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(230, 225, 3, 'AxisCert', NULL, '2025-12-09', '2026-11-09', '2027-11-09', '2028-11-09', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(231, 226, 1, 'AxisCert', NULL, '2025-12-09', '2026-11-09', '2027-11-09', '2028-11-09', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(232, 226, 2, 'AxisCert', NULL, '2025-12-09', '2026-11-09', '2027-11-09', '2028-11-09', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(233, 226, 3, 'AxisCert', NULL, '2025-12-09', '2026-11-09', '2027-11-09', '2028-11-09', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(234, 227, 1, 'AxisCert', NULL, NULL, NULL, NULL, NULL, 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(235, 227, 2, 'AxisCert', NULL, NULL, NULL, NULL, NULL, 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(236, 227, 3, 'AxisCert', NULL, NULL, NULL, NULL, NULL, 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(237, 227, 4, 'AxisCert', NULL, NULL, NULL, NULL, NULL, 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(238, 228, 1, 'AxisCert', NULL, '2025-12-15', '2026-11-15', '2027-11-15', '2028-11-15', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(239, 228, 3, 'AxisCert', NULL, '2025-12-15', '2026-11-15', '2027-11-15', '2028-11-15', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(240, 229, 1, 'AxisCert', NULL, '2025-12-26', '2026-11-25', '2027-11-25', '2028-11-25', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(241, 229, 2, 'AxisCert', NULL, '2025-12-26', '2026-11-25', '2027-11-25', '2028-11-25', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(242, 229, 3, 'AxisCert', NULL, '2025-12-26', '2026-11-25', '2027-11-25', '2028-11-25', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(243, 229, 4, 'AxisCert', NULL, '2025-12-26', '2026-11-25', '2027-11-25', '2028-11-25', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(244, 231, 3, 'AxisCert', NULL, '2026-01-29', '2026-12-28', '2027-12-28', '2028-12-28', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(245, 232, 3, 'AxisCert', NULL, '2025-01-28', '2026-12-27', '2027-12-27', '2028-12-27', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(246, 233, 3, 'AxisCert', NULL, '2025-01-05', '2026-12-04', '2027-12-04', '2028-11-04', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(247, 234, 1, 'AxisCert', NULL, '2026-02-15', '2027-01-15', '2028-01-15', '2029-01-15', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(248, 234, 2, 'AxisCert', NULL, '2026-02-15', '2027-01-15', '2028-01-15', '2029-01-15', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(249, 234, 3, 'AxisCert', NULL, '2026-02-15', '2027-01-15', '2028-01-15', '2029-01-15', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(250, 234, 4, 'AxisCert', NULL, '2026-02-15', '2027-01-15', '2028-01-15', '2029-01-15', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(251, 235, 3, 'AxisCert', NULL, '2026-01-23', '2027-12-22', '2028-11-22', '2029-11-22', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(252, 236, 1, 'AxisCert', NULL, '2026-03-15', '2027-02-15', '2028-02-15', '2029-02-15', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(253, 236, 2, 'AxisCert', NULL, '2026-03-15', '2027-02-15', '2028-02-15', '2029-02-15', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(254, 236, 3, 'AxisCert', NULL, '2026-03-15', '2027-02-15', '2028-02-15', '2029-02-15', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(255, 237, 1, 'AxisCert', NULL, '2026-01-27', '2027-12-26', '2028-12-26', '2029-12-26', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(256, 237, 3, 'AxisCert', NULL, '2026-01-27', '2027-12-26', '2028-12-26', '2029-12-26', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(257, 238, 1, 'AxisCert', NULL, '2026-05-18', '2027-04-18', '2028-04-18', '2029-04-18', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(258, 238, 2, 'AxisCert', NULL, '2026-05-18', '2027-04-18', '2028-04-18', '2029-04-18', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(259, 238, 3, 'AxisCert', NULL, '2026-05-18', '2027-04-18', '2028-04-18', '2029-04-18', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(260, 239, 1, 'AxisCert', NULL, '2026-01-26', '2027-12-25', '2028-12-25', '2029-12-25', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(261, 239, 2, 'AxisCert', NULL, '2026-01-26', '2027-12-25', '2028-12-25', '2029-12-25', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(262, 239, 3, 'AxisCert', NULL, '2026-01-26', '2027-12-25', '2028-12-25', '2029-12-25', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(263, 240, 1, 'AxisCert', NULL, NULL, NULL, NULL, NULL, 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(264, 240, 2, 'AxisCert', NULL, NULL, NULL, NULL, NULL, 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(265, 240, 3, 'AxisCert', NULL, NULL, NULL, NULL, NULL, 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(266, 241, 1, 'AxisCert', NULL, '2026-02-23', '2027-01-22', '2028-01-22', '2029-01-22', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(267, 241, 3, 'AxisCert', NULL, '2026-02-23', '2027-01-22', '2028-01-22', '2029-01-22', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(268, 242, 4, 'AxisCert', NULL, '2026-04-20', '2027-03-20', '2028-03-20', '2029-03-20', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(269, 243, 1, 'AxisCert', NULL, '2026-04-20', '2027-03-20', '2028-03-20', '2029-03-20', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(270, 243, 3, 'AxisCert', NULL, '2026-04-20', '2027-03-20', '2028-03-20', '2029-03-20', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(271, 244, 1, 'AxisCert', NULL, '2026-04-20', '2027-03-20', '2028-03-20', '2029-03-20', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(272, 244, 3, 'AxisCert', NULL, '2026-04-20', '2027-03-20', '2028-03-20', '2029-03-20', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30');
INSERT INTO `cm_certifications` (`id`, `cm_client_id`, `cm_scheme_type_id`, `accreditation_body`, `certificate_number`, `issue_date`, `surveillance_1_date`, `surveillance_2_date`, `expiry_date`, `cycle_stage`, `status`, `responsible_person_id`, `responsible_person_name`, `notes`, `created_at`, `updated_at`) VALUES
(273, 245, 3, 'AxisCert', NULL, '2026-04-25', '2027-03-24', '2028-03-24', '2029-03-24', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(274, 246, 1, 'AxisCert', NULL, '2026-04-29', '2027-03-28', '2028-03-28', '2029-03-28', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(275, 246, 3, 'AxisCert', NULL, '2026-04-29', '2027-03-28', '2028-03-28', '2029-03-28', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(276, 247, 1, 'AxisCert', NULL, '2026-05-18', '2027-05-18', '2028-05-18', '2029-05-18', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(277, 247, 2, 'AxisCert', NULL, '2026-05-18', '2027-05-18', '2028-05-18', '2029-05-18', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(278, 247, 3, 'AxisCert', NULL, '2026-05-18', '2027-05-18', '2028-05-18', '2029-05-18', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(279, 249, 1, 'AxisCert', NULL, '2026-05-31', '2027-04-30', '2028-04-21', '2029-04-21', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(280, 249, 2, 'AxisCert', NULL, '2026-05-31', '2027-04-30', '2028-04-21', '2029-04-21', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(281, 249, 3, 'AxisCert', NULL, '2026-05-31', '2027-04-30', '2028-04-21', '2029-04-21', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(282, 250, 3, 'AxisCert', NULL, '2026-03-04', '2027-02-03', '2028-02-03', '2029-02-03', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(283, 251, 1, 'AxisCert', NULL, '2026-03-04', '2027-02-03', '2028-02-03', '2029-02-03', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(284, 251, 3, 'AxisCert', NULL, '2026-03-04', '2027-02-03', '2028-02-03', '2029-02-03', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(285, 252, 1, 'AxisCert', NULL, '2026-03-31', '2027-02-28', '2028-02-28', '2029-02-28', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(286, 252, 3, 'AxisCert', NULL, '2026-03-31', '2027-02-28', '2028-02-28', '2029-02-28', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(287, 253, 1, 'AxisCert', NULL, '2026-03-13', '2027-02-13', '2028-02-13', '2029-02-12', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(288, 253, 3, 'AxisCert', NULL, '2026-03-13', '2027-02-13', '2028-02-13', '2029-02-12', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(289, 254, 3, 'AxisCert', NULL, '2026-04-14', '2027-03-14', '2028-03-14', '2029-03-14', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(290, 255, 3, 'AxisCert', NULL, '2026-03-31', '2027-02-28', '2028-02-28', '2029-02-28', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(291, 256, 3, 'AxisCert', NULL, '2026-05-18', '2027-04-18', '2028-04-18', '2029-04-18', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(292, 257, 3, 'AxisCert', NULL, '2026-03-31', '2027-02-28', '2028-02-28', '2029-02-28', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(293, 258, 1, 'AxisCert', NULL, '2026-04-14', '2027-03-14', '2028-03-14', '2029-03-14', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(294, 258, 2, 'AxisCert', NULL, '2026-04-14', '2027-03-14', '2028-03-14', '2029-03-14', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(295, 258, 3, 'AxisCert', NULL, '2026-04-14', '2027-03-14', '2028-03-14', '2029-03-14', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(296, 259, 1, 'AxisCert', NULL, '2026-04-14', '2027-03-14', '2028-03-14', '2029-03-14', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(297, 259, 2, 'AxisCert', NULL, '2026-04-14', '2027-03-14', '2028-03-14', '2029-03-14', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(298, 259, 3, 'AxisCert', NULL, '2026-04-14', '2027-03-14', '2028-03-14', '2029-03-14', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(299, 260, 3, 'AxisCert', NULL, '2026-05-05', '2027-04-05', '2028-04-05', '2029-04-05', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(300, 261, 1, 'AxisCert', NULL, '2026-04-14', '2027-03-14', '2028-03-14', '2029-03-14', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(301, 261, 4, 'AxisCert', NULL, '2026-04-14', '2027-03-14', '2028-03-14', '2029-03-14', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(302, 263, 3, 'AxisCert', NULL, '2026-04-14', '2027-03-14', '2028-03-14', '2029-03-14', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(303, 264, 1, 'AxisCert', NULL, '2026-05-10', '2027-04-10', '2028-04-10', '2029-04-10', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(304, 264, 2, 'AxisCert', NULL, '2026-05-10', '2027-04-10', '2028-04-10', '2029-04-10', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(305, 264, 3, 'AxisCert', NULL, '2026-05-10', '2027-04-10', '2028-04-10', '2029-04-10', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(306, 265, 3, 'AxisCert', NULL, '2026-05-13', '2027-04-13', '2028-04-13', '2029-04-13', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(307, 266, 3, 'AxisCert', NULL, '2026-06-18', '2027-05-18', '2028-05-18', '2029-05-18', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(308, 268, 1, 'AxisCert', NULL, '2026-05-18', '2027-05-18', '2028-05-18', '2029-05-18', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(309, 268, 2, 'AxisCert', NULL, '2026-05-18', '2027-05-18', '2028-05-18', '2029-05-18', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(310, 268, 3, 'AxisCert', NULL, '2026-05-18', '2027-05-18', '2028-05-18', '2029-05-18', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(311, 269, 3, 'AxisCert', NULL, '2026-05-18', '2027-04-18', '2028-04-18', '2029-04-18', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(312, 270, 3, 'AxisCert', NULL, '2026-05-14', '2027-04-14', '2028-04-14', '2029-04-14', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(313, 272, 3, 'AxisCert', NULL, '2026-06-08', '2027-05-08', '2028-05-08', '2029-05-08', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(314, 273, 1, 'AxisCert', NULL, '2026-05-18', '2027-04-18', '2028-04-18', '2029-04-18', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(315, 274, 3, 'AxisCert', NULL, '2026-06-12', '2027-05-11', '2028-05-11', '2029-05-11', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(316, 275, 3, 'AxisCert', NULL, '2026-04-30', '2027-03-30', '2028-03-30', '2029-03-30', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(317, 276, 1, 'AxisCert', NULL, NULL, NULL, NULL, NULL, 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(318, 276, 2, 'AxisCert', NULL, NULL, NULL, NULL, NULL, 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(319, 276, 3, 'AxisCert', NULL, NULL, NULL, NULL, NULL, 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(320, 277, 1, 'AxisCert', NULL, '2026-05-18', '2027-04-18', '2028-04-18', '2029-04-18', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(321, 277, 2, 'AxisCert', NULL, '2026-05-18', '2027-04-18', '2028-04-18', '2029-04-18', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(322, 277, 3, 'AxisCert', NULL, '2026-05-18', '2027-04-18', '2028-04-18', '2029-04-18', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(323, 278, 1, 'AxisCert', NULL, '2026-05-05', '2027-04-04', '2028-04-04', '2029-04-04', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(324, 278, 3, 'AxisCert', NULL, '2026-05-05', '2027-04-04', '2028-04-04', '2029-04-04', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(325, 279, 3, 'AxisCert', NULL, '2026-05-04', '2027-04-04', '2028-04-04', '2029-04-04', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(326, 280, 1, 'AxisCert', NULL, '2026-05-18', '2027-04-18', '2028-04-18', '2029-04-18', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(327, 280, 2, 'AxisCert', NULL, '2026-05-18', '2027-04-18', '2028-04-18', '2029-04-18', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(328, 281, 3, 'AxisCert', NULL, '2026-05-18', '2027-04-18', '2028-04-18', '2029-04-18', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(329, 282, 3, 'AxisCert', NULL, '2026-05-18', '2027-05-18', '2028-05-18', '2029-05-18', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(330, 283, 3, 'AxisCert', NULL, NULL, NULL, NULL, NULL, 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(331, 284, 1, 'AxisCert', NULL, '2026-06-20', '2027-05-19', '2028-05-19', '2029-05-19', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(332, 284, 3, 'AxisCert', NULL, '2026-06-20', '2027-05-19', '2028-05-19', '2029-05-19', 'initial', 'active', NULL, NULL, NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30');

-- --------------------------------------------------------

--
-- Table structure for table `cm_certification_documents`
--

CREATE TABLE `cm_certification_documents` (
  `id` int UNSIGNED NOT NULL,
  `cm_certification_id` int UNSIGNED NOT NULL,
  `doc_type` enum('certificate','audit_report','application_form','other') NOT NULL DEFAULT 'other',
  `file_path` varchar(500) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `uploaded_by` int UNSIGNED NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cm_clients`
--

CREATE TABLE `cm_clients` (
  `id` int UNSIGNED NOT NULL,
  `company_name` varchar(200) NOT NULL,
  `uen_registration_no` varchar(50) DEFAULT NULL,
  `industry_sector` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `contact_designation` varchar(100) DEFAULT NULL,
  `consultant` varchar(150) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `status` enum('active','suspended','withdrawn','blacklisted') NOT NULL DEFAULT 'active',
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `cm_clients`
--

INSERT INTO `cm_clients` (`id`, `company_name`, `uen_registration_no`, `industry_sector`, `address`, `contact_person`, `contact_designation`, `consultant`, `phone`, `email`, `website`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'N Grace Builders Pte Ltd', NULL, 'General contractors (Building Construction including Major\nupgrading works)', '66 Tannery Lane #03-02A Sindo Industrial Building Singapore 347805', 'Shaun', NULL, NULL, '90679166', 'ngrace.builders@gmail.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(2, 'AI Analytical Pte Ltd', NULL, 'Providing Complete Solution in Water Analysis\nand Monitoring System Equipment', '30 Kallang Place #06-01 Singapore 339159', 'Lily Leow', NULL, NULL, '64590785', 'lily@ai-analytical.com.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(3, 'BSHK Logistics & Shipping Pte Ltd', NULL, 'Provision of Logistics & Shipping Services', '76 Geylang Bahru #01-2832 Geylang Bahru Industrial Estate Singapore 339684', 'Mei Leng', NULL, NULL, '67480106', 'bshktkc8@singnet.com.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(4, 'Chin Siong Electrical Engineering Pte Ltd', NULL, NULL, '4001 Ang Mo Kio Industrial Park 1 #01-05 Singapore 569622', 'Ms Chong', NULL, NULL, '65526646', 'cselect@singnet.com.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(5, 'Y&G System IntegrationPte Ltd', NULL, NULL, '36 Newton Road #03-23 Hotel Royal Singapore 307964', 'Jasmine Lee', NULL, NULL, '62555847', 'yoonkin02@yahoo.com.sg', NULL, 'active', 'Consultant: Tricia', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(6, 'A+ Officers Security Pte Ltd', NULL, NULL, '3 Ang Mo Kio Street 62 #02-20 Link @ AMK Singapore 569139', 'Ms Kay Kay', NULL, NULL, '65 8738 8939', 'kaykay@apo-security.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(7, 'M2M Construction Pte Ltd', NULL, NULL, '60 Kaki Bukit Place #07-16 Eunos Techpark Singapore 415979', 'Manikandan', NULL, NULL, '67412161', 'm2mcon2017@gmail.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(8, 'Heng Xin Construction Pte Ltd', NULL, NULL, '10 Jalan Besar #15-01 Sim Lim Tower Singapore 208787', 'Xu Caihong', NULL, NULL, '98458008', 'vicky.hengxin@yahoo.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(9, 'Acqual Engineering LLP', NULL, NULL, '9005 Tampines Street 93 #02-236 Tampines Industrial Park A Singapore 528839', 'Wilson Tan Chee Wee', NULL, NULL, '65918685', 'leeying@acqualengineering.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(10, 'WLK Engineering Pte Ltd', NULL, NULL, '1023 Yishun Industrial Park A #01-11 Singapore 768762', 'Ng Kok Keong', NULL, NULL, '63635575', 'wlkengrg17@gmail.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(11, 'VE Coat Pte Ltd', NULL, NULL, '19A Tech Park Crescent Singapore 637846', 'Deepan', NULL, NULL, '91521444', 'mgmt@ve-coat.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(12, 'PRM Engineering Pte Ltd', NULL, NULL, '304B Anchorvale Link #05-02 Anchorvale Court Singapore 542304', 'Poovudaiyur Ramalakshmi', NULL, NULL, '63884423', 'raj@prmscaffold.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(13, 'Ngee Hong Metal Engineering (S) Pte Ltd', NULL, NULL, '10 Admiralty Street #02-17 North Link Building Singapore 757695', 'Lim Bao', NULL, NULL, '67530217', 'ngeehongmetal@gmail.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(14, '8ptitude Private Limited', NULL, NULL, '20 Bukit Batok Crescent #09-20 Enterprise Centre Singapore 658080', 'Terrance Huang', NULL, NULL, '65147805', 'admin@8ptitude.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(15, 'RMS Integrated Pte Ltd', NULL, NULL, '27 Woodlands Industrial Park E1 #03-08D Hiangkie Industrial Building Singapore 757718', 'Dulam Nageshwara Rao', NULL, NULL, '87291772', 'rao@rms-integrated.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(16, 'Metro Plastic Manufacturer Sdn Bhd', NULL, NULL, 'Plot 73 Jalan Perusahaan Tupai 3 Kawasan Perindustrian Ringan Tupai 3400 Taiping Perak Malaysia', 'Lawrence Gan', NULL, NULL, '+60 12-593 8816', 'lawrence@metroplastic.com.my', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(17, 'Ma Yi Group Pte Ltd', NULL, NULL, '2 Yishun Industrial Street 1 #06-13 Northpoint Bizhub Singapore 768159', 'Tan Boon Yih', NULL, NULL, '66352665', 'mayigroups@gmail.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(18, 'Kingsley Interior Pte Ltd', NULL, NULL, '21 Woodlands Cloase #01-09 Primz Bizhub Singapore 737854', 'Marcus Goh', NULL, NULL, '6957 1596', 'marcus@kingsleyinterior.com.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(19, 'Kee Safety Singapore Pte Ltd', NULL, NULL, '38 Ang Mo Kio Industrial Park 2 #01-03 Singapore 569511', 'Lucas', NULL, NULL, '63854166', 'ikalaivanan@keesafety.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(20, 'Kah Teck Hardware Trading Pte Ltd', NULL, NULL, '66 Senoko Road Singapore 758127', 'Ang Siew Cheng', NULL, NULL, '62578856', 'siewcheng@kahteck.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(21, 'ID Haus Pte Ltd', NULL, NULL, '33 Ubi Avenue 3 #08-43 Vertex Singapore 408868', 'Mohammed Firdaus Bin Jumat', NULL, NULL, '69800766', 'enquiry@idhaus.me', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(22, 'Elitesia Forwarders Pte Ltd', NULL, NULL, '61 Bukit Batok Crescent #07-07 Heng Loong Building Singapore 658078', 'Dalvinder Singh S/O surjit Singh', NULL, NULL, '90901534', 'admin@elitesia.com.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(23, 'Asia Tec Services Pte Ltd', NULL, NULL, '43 Changi South Avenue 2 #01-03 Singapore 486164', 'Chandran Koori Prasad', NULL, NULL, '84200410', 'admin@asiatecservices.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(24, 'Allied Kinsmen Facility Management Pte Ltd', NULL, NULL, '10 Ubi Crescent #07-30 Ubi Techpark Singapore 408564', 'Anand Kumar', NULL, NULL, '63298830', 'anand@alliedkinsmen.com.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(25, 'YGS Energy Engineering Pte Ltd', NULL, NULL, '35 Selegie Road #09-02 Parklane Shopping Mall Singapore 188307', 'M Venkatesan', NULL, NULL, '93866450', 'ygsenergyengineering@gmail.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(26, 'The Akron (S) Pte Ltd', NULL, NULL, '30 Loyang Way #04-19 Singapore 508769', 'Kendrick Tan', NULL, NULL, '62913300', 'kendrick@akron.com.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(27, 'Mecgro Engineering & Construction Pte Ltd', NULL, NULL, '1093 Lower Delta Road #07-16/17 Singapore 169204', 'Joel Shen Pei Wen', NULL, NULL, '97704096', 'joelshen@mecgro.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(28, 'Mastron Pte Ltd', NULL, NULL, '5 Ang Mo Kio Industrial Park 2A #05-37 AMK Tech II Singapore 567760', 'Tan Chik Lee', NULL, NULL, '64812917', 'mastronpteltd@singnet.com.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(29, 'Lisa Engineering Pte Ltd', NULL, NULL, '35 Selegie Road #09-02 Parklane Shopping Mall Singapore 188307', 'Ramasamy Arumugam', NULL, NULL, '83327679', 'lisaengineering06@gmail.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(30, 'AKK Th3X Pte Ltd', NULL, NULL, '50 Gambas Crescent #03-08 Proxima @ Gambas Singapore 757022', 'Eric Ng', NULL, NULL, '67950267', 'sl@akkth3x.com.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(31, 'Unisteel Engineering Pte Ltd', NULL, NULL, '10 Buroh Street #03-34 West Connect Building Singapore 627564', 'C. Bhuvaneshwari', NULL, NULL, '62646752', 'admin@grkgroup.biz', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(32, 'Tata Builders (S) Pte Ltd', NULL, NULL, '7 Gambas Crescent #03-12 ARK @ Gambas Singapore 757087', 'Ramadoss Murgados', NULL, NULL, '65279012', 'tatabuilders2015@gmail.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(33, 'On Time Engineering Pte Ltd', NULL, NULL, '19A Tech Park Crescent Singapore 637846', 'Michael Fernando Nancy', NULL, NULL, '91521444', 'ontimeengg@gmail.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(34, 'LV Engineering Pte Ltd', NULL, NULL, '144 Teck Whye Lane #01-201 Singapore 680144', 'Bala', NULL, NULL, '92720706', 'bala.lvengineering@gmail.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(35, 'Imperial Services Pte Ltd', NULL, NULL, '60 Paya Lebar Road #06-33 Paya Lebar Square Singapore 409051', 'Loh Siang Yan', NULL, NULL, '94598595', 'kelvin@imperisalservices.com.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(36, 'GRK Marine Services Pte Ltd', NULL, NULL, '10 Buroh Street #03-04 West Connect Building Singapore 627564', 'C. Bhuvaneshwari', NULL, NULL, '62646752', 'admin@grkgroup.biz', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(37, 'Archilite Engineering Pte Ltd', NULL, NULL, '13 Lorong 8 Toa Payoh #07-05 Braddell Tech Singapore 319261', 'Chan Kok Yeow', NULL, NULL, '63565555', 'kychan@archilite.com.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(38, 'ACS Process Control Pte Ltd', NULL, NULL, '21 Woodlands Industrial Park E1 #02-07 Singapore 757720', 'Derek Soh', NULL, NULL, '83235058', 'derek@acsprocess.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(39, 'HUP HIN TRANSPORT CO PTE LTD 9k 45k Surv', NULL, NULL, '5 TUAS AVE 5 SINGAPORE 639344', 'TAN LAI LEE', NULL, NULL, '62621525', 'alextan@huphin.com.sg', NULL, 'active', 'Consultant: RUTHU', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(40, 'HUP HIN HEAVY EQUIPMENT PTE LTD 9k 45 Surv', NULL, NULL, '5 TUAS AVE 5 SINGAPORE 639344', 'TAN LAI LEE', NULL, NULL, '62621525', 'alextan@huphin.com.sg', NULL, 'active', 'Consultant: RUTHU', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(41, 'GREENSAFE INTERNATIONAL - ISO 37001 New', NULL, NULL, '175A BENCOOLEN STREET #08-11 BURLINGTON SQUARE SINGAPORE (189650)', 'Kuppan Karuppiah', NULL, NULL, '8168 5141', 'easan@greensafe.com.sg', NULL, 'active', 'Consultant: EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(42, 'BESCO BUILDING SUPPLIES - ISO 37001 New', NULL, NULL, '26, UBI ROAD 4, SDG CENTER, SINGAPORE 408613', 'Nani', NULL, NULL, '84367551', 'nani.sukarman@besco.sg', NULL, 'active', 'Consultant: Others/Tricia', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(43, 'BENKEL INTERNATIONAL - ISO 37001 New', NULL, NULL, '2, BUKIT MERAH CENTRAL, #05-08, SINGAPORE 159835', 'Angeline', NULL, NULL, '97630304', 'pa@benkel-international.com', NULL, 'active', 'Consultant: RUTHU/ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(44, 'YEW HOCK MARINE ENGINEERING PTE LTD 45k Surv', NULL, NULL, '1 Kaki Bukit Avenue 3 #02-17 KB-1', 'Sim Chun Siang', NULL, NULL, '64482181', 'briansim@yhme.com.sg', NULL, 'active', 'Consultant: ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(45, 'YSH ENGINEERING PTE. LTD 45k Surv', NULL, NULL, '1 Kaki Bukit Avenue 3 #02-17 KB-1', 'Sim Chun Siang', NULL, NULL, '64482181', 'briansim@yhme.com.sg', NULL, 'active', 'Consultant: ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(46, 'CABLETRONIC SYSTEM PTE LTD ISO 45k Surv', NULL, NULL, '72 Eunos Avenue 7 #04-05 Singapore Handicrafts Building', 'Lee Kay Ping', NULL, NULL, '69080885', 'justin@cabletronicsystem.com', NULL, 'active', 'Consultant: BIZMEDIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(47, 'Jian Bang Construction Surv 1 45k', NULL, NULL, '7 Mandai Link #05-10 Mandai Connection', 'Chris Lin Wanhua', NULL, NULL, '63223975', 'sales@jianbang.sg', NULL, 'active', 'Consultant: EASAN/ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(48, 'SAAMCO MAINTENANCE PTE. LTD. 45k Surv', NULL, NULL, '19 SENOKO LOOP, #03-02, SINGAPORE 758169', 'Chandra S/O Selvarajoo', NULL, NULL, '6513 9234', 'admin@saamco.sg', NULL, 'active', 'Consultant: WSH EXPERT | Source Client Status: Not Reply', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(49, 'Ngee Hong Metal 45k New', NULL, NULL, 'No. 10 Admiralty Street #02-17 Northlink Building Singapore 757695', 'So Kuan Ming', NULL, NULL, '6753 0217', 'ngeehongmetal@gmail.com', NULL, 'active', 'Consultant: ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(50, 'Digitalbuild ISO 27001 Sur', NULL, NULL, '627A Aljunied Road #08-10 Biztech Centre', 'Ramamoorthy  Rajendran', NULL, NULL, '65179726', 'info@digitalbuild.com.sg', NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(51, 'SRIRAM ENGINEERING & Construction Pte Ltd', NULL, NULL, 'No. 10 Admiralty Street #02-17 Northlink Building Singapore 757695', 'CHINNAKARUPPAN VISWANATHAN', NULL, NULL, '6753 0217', 'ngeehongmetal@gmail.com', NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(52, 'VPM Engineering Pte Ltd 45k Initial Audit', NULL, NULL, '3 Soon Lee Street #04-28 Pioneer Junction', 'G Muthukumareswaran', NULL, NULL, '65-63846797', 'vpmengg@gmail.com', NULL, 'active', 'Consultant: EASAN/PANDIAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(53, 'Kee Safety Singapore - 45k Surv', NULL, NULL, '38 ANG MO KIO INDUSTRIAL PARK 2, #01-03, SINGAPORE 569511', 'Lucas', NULL, NULL, '6385 4166', 'ikalaivanan@keesafety.com', NULL, 'active', 'Consultant: EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(54, 'Eversafe Academy Pte Ltd - 45k Surv', NULL, NULL, '2 Kampong Kapor Road', 'Verghese Monish', NULL, NULL, '62978417', 'eversafeconsultant@gmail.com', NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(55, 'LSN Engineering 45k Initial Year', NULL, NULL, '39 WOODLANDS CLOSE, #05-63, MEGA@WOODLANDS, SINGAPORE 737856', 'So Kuan Ming', NULL, NULL, '69084473', 'operation@lsn-engineering.com', NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(56, 'Ma Yi Group 45k Surv', NULL, NULL, '2 Yishun Industrial Street 1 #06-13', 'Tan Boon Yih', NULL, NULL, '6635 2665', 'mayigroup@gmail.com', NULL, 'active', 'Consultant: ZACK/RUTHU', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(57, 'HG Technologies 9k Surv', NULL, NULL, '280 Woodlands Industrial Park E5 #08-02 Harvest Woodlands', 'Eng Kiat Hoe', NULL, NULL, '8065 0193', NULL, NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(58, 'Kah Teck Hardware Trading Pte Ltd ISO 9k 14k 45k Surv', NULL, NULL, '66 Senoko Road', 'Ang Siew Cheng', NULL, NULL, '6257 8856', 'siewcheng@kahteck.com', NULL, 'active', 'Consultant: ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(59, 'THE AKRON 14k 45k Surv', NULL, NULL, '30 LOYANG WAY, #04-19, SINGAPORE 508769', 'Kendrick Tan', NULL, NULL, '6291 3300', 'Kendrick@akron.com.sg', NULL, 'active', 'Consultant: WSH EXPERT', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(60, 'MW Trading & Recycling Pte Ltd 14k Surv', NULL, NULL, '38 Joo Koon Circle', 'Teo Chern Peng', NULL, NULL, '82260046', 'mwtradingrecycling@gmail.com', NULL, 'active', 'Consultant: RUTHU', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(61, 'MNL SOLUTIONS PTE. LTD. 45k Surv', NULL, NULL, '21 TOH GUAN ROAD EAST, #05-22, TOH GUAN CENTRE, SINGAPORE', 'Lai Yuan Weng', NULL, NULL, '94551451', 'projects@mnlasia.com', NULL, 'active', 'Consultant: EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(62, 'BSHK Logistics & Shipping Pte Ltd 9k 45k Surv', NULL, NULL, '76 Geyland Bahru #01-2832 Geylang Bahru Industrial Estate', 'Tan Kim Chuan', NULL, NULL, '6748 0106', 'meileng@binseng.com', NULL, 'active', 'Consultant: ZACK/RUTHU', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(63, 'International Cleaning & Building Services 9k 45k Surv', NULL, NULL, '19 Burn Road #09-03 Advance Building', 'Sabrina Hemala Joseph Dass', NULL, NULL, '65-6291 1416', 'icnbs.sabrina@gmail.com', NULL, 'active', 'Consultant: ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(64, 'Wide Wings Pte Ltd 37 Surv', NULL, NULL, '101 Spottiswoode Park Road, #07-90, Spottiswoode Park', 'Kaviarasu Balakumar', NULL, NULL, '91074050', 'hr@widewings.com.sg', NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(65, 'QUALITY M&E PTE. LTD. 45k Surv', NULL, NULL, '10 UBI CRESCENT, #05-32A, UBI TECHPARK, SINGAPORE 408564', 'Vellappan Krishnakumar', NULL, NULL, '85697250', 'qualityeng15@gmail.com', NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(66, 'Tata Builders (S) Pte Ltd 45k Surv', NULL, NULL, '23 Woodlands Industrial Park E2, #01-23, Nordix, Singapore 757458', 'RAMADOSS MURGADOS', NULL, NULL, '65279012', 'tatabuilders2015@gmail.com', NULL, 'active', 'Consultant: WSH EXPERT', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(67, 'ARCHILITE ENGINEERING PTE LTD 45k Surv', NULL, NULL, '13 LORONG 8 TOA PAYOH, #07-05, BRADDELL TECH, SINGAPORE 319261', 'Chan Kok Yeow', NULL, NULL, '63565555', 'kychan@archilite.com.sg', NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(68, 'ADRI OFFSHORE AND MARINE 45k Surv', NULL, NULL, '2 Venture Drive #14-02 Vision Exchange', 'Rajagopal Vengates', NULL, NULL, '65-90698870', 'contact@adrimarine.com', NULL, 'active', 'Consultant: OWN | Source Client Status: Not Reply', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(69, 'LISA Engineering Pte Ltd ISO 45k Surv', NULL, NULL, '2 Kallang avenue, #08-16, CT hub, Singapore 339407', 'RAMASAMY ARUMUGAM', NULL, NULL, '65 83327679', 'lisaengineering06@gmail.Com', NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(70, 'SG Building Contractors Pte Ltd ISO 45k Sur', NULL, NULL, '53 Ubi Avenue 1 #05-24 Paya Ubi Industrial Park', 'Senthil Rajeswari Nandakishor', NULL, NULL, '6518 9418', 'visalatchi.sge@gmail.com', NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(71, 'Ai Analytical Pte Ltd ISO 45k Surv', NULL, NULL, '30, Kallang Place #06-01, Singapore', 'Lily Leow', NULL, NULL, '6459 0785', 'lily@ai-analytical.com.sg', NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(72, 'A+ Officers Security Pte Ltd ISO 45K Surv', NULL, NULL, '3 Ang Mo Kio Street 62 #02-20 Link @ AMK', 'Shi Hong Sheng', NULL, NULL, '6261 2122', 'kaykay@apo-security.sg', NULL, 'active', 'Consultant: EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(73, 'KAI LUN ENGINEERING PTE LTD ISO 45k Surv', NULL, NULL, '60 Kaki Bukit Place #05-13 Eunos Techpark', 'Zheng Ming Zhen', NULL, NULL, '65-68464248', 'kailun.azim@gmail.com', NULL, 'active', 'Consultant: void', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(74, 'MH Engineering Pte Ltd 45k Surv', NULL, NULL, '1 SOON LEE STREET, #06-26, PIONEER CENTRE, SINGAPORE 627605', 'MUNIYANDI KANNAN', NULL, NULL, '65665359', 'admin@mhengrg.com.sg', NULL, 'active', 'Consultant: WSH EXPERT | Source Client Status: Not Reply', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(75, 'VERSION 20 PTE LTD ISO 9k and 45k Surv', NULL, NULL, '28 Balmoral Park, #04-02, Pinetree Condominium, Singapore 259856', 'DIVYA SHARMA VUKKADALA', NULL, NULL, '8200 2550', 'enquiry@version20.biz', NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(76, 'GRK MARINE SERVICES PTE. LTD ISO 45001 Surv', NULL, NULL, '10 BUROH STREET, #03-30, WEST CONNECT BUILDING, SINGAPORE 627564', 'G Ravikumar', NULL, NULL, '6264 6752', 'admin@grkgroup.biz', NULL, 'active', 'Consultant: WSH EXPERT', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(77, 'UNISTEEL ENGINEERING PTE. LTD. ISO 45001 Surv', NULL, NULL, '10 BUROH STREET, #03-30, WEST CONNECT BUILDING, SINGAPORE 627564', 'G Ravikumar', NULL, NULL, '6264 6752', 'admin@grkgroup.biz', NULL, 'active', 'Consultant: WSH EXPERT', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(78, 'Ji Tai Maritime Pte Ltd ISO 9k Surv', NULL, NULL, 'Ji Tai Maritime Pte Ltd ISO 9k Surv', 'Koh Wee Seng', NULL, NULL, '65-83331416', 'admin@jtmaritime.com', NULL, 'active', 'Consultant: ZACK | Source Client Status: Not Reply', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(79, 'HI-GREEN LANDSCAPE & CONSTRUCTION PTE. LTD 45k', NULL, NULL, '11 WOODLANDS CLOSE, #03-24, WOODLANDS 11, SINGAPORE 737853', 'Bennet', NULL, NULL, '9811 0434', 'landscape@higreen.sg', NULL, 'active', 'Consultant: OWN | Source Client Status: Invoice - Send need to chase payment', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(80, 'JSBROD PRIVATE LIMITED ISO 27k', NULL, NULL, '10 Ubi Crescent, #4-35, Ubi Techpark, Singapore 408564', 'S Davin', NULL, NULL, '69937393', 's.davin@jsbrod.com', NULL, 'active', 'Consultant: OWN | Source Client Status: Void', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(81, 'COSMO SPACE PTE. LTD. 45K Surv', NULL, NULL, '38 WOODLANDS INDUSTRIAL PARK E1, #07-16, SINGAPORE 757700', 'YAP CHUNG SANG', NULL, NULL, '87996272', 'admin@cosmospace.com.sg', NULL, 'active', 'Consultant: ZACK | Source Client Status: Not Reply', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(82, 'TH3X Construction Consultancy Pte Ltd 45001 Surv', NULL, NULL, '50 Gambas Crescent, #03-08, Proxima@Gambas', 'Mr Eric Ng Shyang Long', NULL, NULL, '86562598', 'admin@th3x.com.sg', NULL, 'active', 'Consultant: OWN | Source Client Status: Maybe will signed back', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(83, 'BENKEL INTERNATIONAL PTE. LTD. 27001', NULL, NULL, '2, BUKIT MERAH CENTRAL, #05-08, SINGAPORE 159835', 'Angeline', NULL, NULL, '66977340', 'pa@benkel-international.com', NULL, 'active', 'Consultant: RUTHU/TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(84, 'S & M GLOBAL LOGISTICS PTE. LTD. 9k', NULL, NULL, '125 Bukit Merah Lane 1, #04-176, Singapore 150125', 'Jocelyn', NULL, NULL, '9768 2323', 'jocelyn@at-cfs.com', NULL, 'active', 'Consultant: RUTHU / TRICIA | Source Client Status: Need to wait GSI', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(85, 'G2 Engineering 45001 Surv', NULL, NULL, '7 SOON LEE STREET, #02-13, ISPACE, SINGAPORE 627608', 'Mr GOVINDASAMY GUBER', NULL, NULL, '62646955', 'g2enggnconstpteltd@gmail.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(86, 'Green Cosmos 45001 Surv', NULL, NULL, '23 WOODLANDS INDUSTRIAL PARK E1, #05-05, ADMIRALTY INDUSTRIAL PARK, SINGAPORE 757741', 'Mdm Yin Ling Fong', NULL, NULL, '6853 3835', 'greencosmos.hq@gmail.com', NULL, 'active', 'Consultant: TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(87, 'NST Technology Pte Ltd _9001 New', NULL, NULL, 'TOH GUAN ROAD EAST , #01-17, Singapore 608579 34,', 'Mr NOVA SARAN RAJ', NULL, NULL, '67740656', 'projects@honglip.com.sg', NULL, 'active', 'Consultant: RUTHU', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(88, 'S Power 2 system Surv', NULL, NULL, '5 SUNGEI KADUT STREET 2, #06-04, TRENDSPACE, SINGAPORE 729227', 'Mr Karuppiah Vadive', NULL, NULL, '63621392', 'spower7171@gmail.com', NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(89, 'UNITEK ENGG 2 system Surveillance', NULL, NULL, '10 BUROH STREET, #06-45, WEST CONNECT BUILDING, SINGAPORE 627564', 'Mr Thangavelu Manikandan', NULL, NULL, '98531975', 'maniunitec@gmail.com', NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(90, 'Lee cycle Resources 9001 surv 1', NULL, NULL, '28 Kranji Loop #06-05 Kranji Green, Singapore 739571', 'Ms.Meng Yee', NULL, NULL, NULL, 'meng_yee@leecycleresources.sg', NULL, 'active', 'Consultant: TRICIA | Source Client Status: Done', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(91, 'YS Construction Pte Ltd 45001 Surv', NULL, NULL, '11 Woodlands Close #07-07 Woodlands 11 Singapore 737853', 'Mr Eric Ye Xian Rong', NULL, NULL, '6710 5532', 'elaine@ysconstruction.com.sg', NULL, 'active', 'Consultant: TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(92, 'Alampanai 45001 Surv', NULL, NULL, '7 GAMBAS CRESCENT #02-22 ARK@GAMBAS, SINGAPORE 757087', 'Mr CHINNAIYAN RAJA', NULL, NULL, '91872857', 'alampanai@gmail.com', NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(93, 'Thong HUP Gardens 45001 Surv', NULL, NULL, '21 BATH ROAD NEE SOON CAMP 9 (SCDF) SINGAPORE 779914', 'Mr Darren Toh', NULL, NULL, '6454 5055', 'joey.toh@thonghup.com.sg', NULL, 'active', 'Consultant: TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(94, 'Reedy Engineering 45001 Surv', NULL, NULL, '421 TAGORE INDUSTRIAL AVENUE, #01-18 SINGAPORE (787805)', 'Mr YEO KOK LEONG', NULL, NULL, '96916978', 'reedyengineering.office@gmail.com', NULL, 'active', 'Consultant: EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(95, 'Fukuyama 45001 Surv', NULL, NULL, '2 SIMS CLOSE, #05-08, GEMINI @ SIMS, SINGAPORE 387298', 'Mr Desmond Toh', NULL, NULL, '67470159', 'desmond@fukuyama.com.sg', NULL, 'active', 'Consultant: EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(96, 'Trans Engineering 2 system Surv', NULL, NULL, '1 Soon Lee Street #04-65, Pioneer Centre Singapore 627605', 'Puang Chey Seng', NULL, NULL, '98531975', 'jennifer@sttrailers.com.sg', NULL, 'active', 'Consultant: TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(97, 'SIN TRANS ENGINEERING 2 system Surv', NULL, NULL, '4 BENOI RD, SINGAPORE 629878', 'Puang Chey Seng', NULL, NULL, '98531975', 'jennifer@sttrailers.com.sg', NULL, 'active', 'Consultant: TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(98, 'Everpeak Engineering 2 System Surv', NULL, NULL, '1 Soon Lee Street #04-65, Pioneer Centre Singapore 627605', 'Puang Chey Seng', NULL, NULL, '98531975', 'jennifer@sttrailers.com.sg', NULL, 'active', 'Consultant: TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(99, 'T STORE 2 System Surv', NULL, NULL, '140 PAYA LEBAR ROAD, #09-24, AZ @ PAYA LEBAR SINGAPORE (409015)', 'Advin Zhu', NULL, NULL, '97223387', 'jennifer@sttrailers.com.sg', NULL, 'active', 'Consultant: TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(100, 'Good Tyre Pte Ltd 2 system Surv', NULL, NULL, '140 PAYA LEBAR ROAD, #09-24, AZ @ PAYA LEBAR SINGAPORE (409015)', 'PUANG CHEY SENG', NULL, NULL, '97223387', 'jennifer@sttrailers.com.sg', NULL, 'active', 'Consultant: TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(101, 'Horsol 3 system Surv', NULL, NULL, '22 CHANGI BUSINESS PARK CENTRAL 2, #02-01, THE KINGSMEN EXPERIENCE, SINGAPORE 486032', 'Ms Ashley', NULL, NULL, '6989 7769', 'a.chong@horsolengg.com', NULL, 'active', 'Consultant: TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(102, 'Nexvision 3 system Surv', NULL, NULL, '22 CHANGI BUSINESS PARK CENTRAL 2, #02-01, THE KINGSMEN EXPERIENCE, SINGAPORE 486032', 'Ms Ashley', NULL, NULL, '6989 7769', 'a.chong@horsolengg.com', NULL, 'active', 'Consultant: TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(103, 'AE Model 3 system Surveillance', NULL, NULL, 'Tampines Street 93, #02-206 Blk 9006, Industrial Park A, Singapore 528840', 'Mr Lewis Chan', NULL, NULL, '67861314', 'inquiry@aemodels.com', NULL, 'active', 'Consultant: TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(104, 'HENG SENG HIN CONSTRUCTION PTE. LTD. ISO 45001_surveillance', NULL, NULL, '051, SIMS AVENUE, #02-08, CHANCERLODGE COMPLEX SINGAPORE 387429', 'Ms Jean', NULL, NULL, '6846 4301', 'jean@clerical.com.sg', NULL, 'active', 'Consultant: TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(105, 'LYC HARDWARE & ENGINEERING PTE LTD _SURV', NULL, NULL, 'Block 9 Pioneer Rd North, #01-57, Singapore 628461', 'EDWIN CHAN', NULL, NULL, '97922240', 'accounts@lyc.com.sg', NULL, 'active', 'Consultant: RUTHU', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(106, 'Vinayaka scaffold & Services Pte Ltd Easan', NULL, NULL, '51 Bukit Batok Crescent, #07-02 Unity Centre, Singapore 658077.', 'Mr Balasubramanian Senthilkumaran', NULL, NULL, '6899 4072', 'bala@vinayak.com.sg', NULL, 'active', 'Consultant: EASAN | Source Client Status: By Mr.Pandian', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(107, 'Silver Seal and Construction pte Ltd_45K ZAck_Ruth', NULL, NULL, '1 Kaki Bukit Avenue 3 #02-17 KB-1', 'Sim Chun Siang', NULL, NULL, '64482181', 'briansim@yhme.com.sg', NULL, 'active', 'Consultant: RUTHU', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(108, 'PEARL Engineering Sitha IMS', NULL, NULL, '10 Buroh Street, #04-05 West Connect Building, Singapore 627564', 'Meiyappan Venkatesan', NULL, NULL, '69080974', 'Sastha.88@gmail.com', NULL, 'active', 'Consultant: SITHA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(109, 'SPINNET ASIA PTE. LTD. Sitha', NULL, NULL, '#02-07, Tannery House, 37 Tannery Lane,Singapore 347790', 'Ms. Rohini Velayuthan', NULL, NULL, '63584767', 'ENQUIRY@SPINNETASIA.COM.SG', NULL, 'active', 'Consultant: SITHA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(110, 'ELITE ENGINEERING 2 system 9 & 45 NEW', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 'Consultant: EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(111, 'HH DESIGN PTE LTD', NULL, NULL, '5 TUAS AVE 5 SINGAPORE 639344', 'TAN LAI LEE', NULL, NULL, '62621525', 'alextan@huphin.com.sg', NULL, 'active', 'Consultant: ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(112, 'RCY 37001 Own', NULL, NULL, '1 Harvey Road #05-00 Tan Heng Lee Building Singapore 369610', 'Sekar Chandrasekar', NULL, NULL, '65600523', 'hr@rcy.com.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(113, 'ASIA TEC SERVICES PTE. LTD. Philip 45K', NULL, NULL, '43 CHANGI SOUTH AVENUE 2 #01-03 SINGAPORE 486164', 'Chandran Koori Prasad', NULL, NULL, '65-84200410', 'admin@asiatecservices.com', NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(114, 'RMS Integrated Pte Ltd 45k WSH', NULL, NULL, '27 WOODLANDS INDUSTRIAL PARK E1, #03-08D, HIANGKIE INDUSTRIAL BUILDING, SINGAPORE 757718', 'Dulam Nageshwara Rao', NULL, NULL, '87291772', 'rao@rms-integrated.com', NULL, 'active', 'Consultant: WSH EXPERT', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(115, 'KEE Safety Singapore Pte Ltd ISO 45001 Easan', NULL, NULL, '38 ANG MO KIO INDUSTRIAL PARK 2, #01-03, SINGAPORE 569511', 'Lucas', NULL, NULL, '6385 4166', 'ikalaivanan@keesafety.com', NULL, 'active', 'Consultant: EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(116, 'YGS ENERGY 9001 and 45001 Pandian', NULL, NULL, '35 SELEGIE ROAD, #09-02, PARKLANE SHOPPING MALL, SINGAPORE 188307', 'M Venkatesan', NULL, NULL, '93866450', 'ygsenergyengineering@gmail.com', NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(117, 'MASTRON ISO 45001 Zack', NULL, NULL, '5 ANG MO KIO INDUSTRIAL PARK 2A#05-37 AMK TECH II Singapore 567760', 'Charli Tan', NULL, NULL, '65 64812917', 'mastronpteltd@singnet.com.sg', NULL, 'active', 'Consultant: ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(118, 'ACS PRocess Control Pte Ltd ISO 9001 Zack', NULL, NULL, 'Blk 21 Woodlands Industrial Park E1 #02-07, Singapore 757720', 'DEREK SOH', NULL, NULL, '83235058', 'derek@acsprocess.com', NULL, 'active', 'Consultant: ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(119, 'IMPERIAL 45001 Expert', NULL, NULL, '60 PAYA LEBAR ROAD, #06-33, PAYA LEBAR SQUARE, SINGAPORE 40905', 'LOH SIANG YAN', NULL, NULL, '94598595', 'kelvin@imperialservices.com.sg', NULL, 'active', 'Consultant: WSH EXPERT', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(120, 'VPM ENGINEERING 45k own', NULL, NULL, '3 SOON LEE STREET, #04-28, PIONEER JUNCTION, SINGAPORE 627606', 'Muthukumareswaran', NULL, NULL, '63846797', 'vpmengg@gmail.com', NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(121, 'Long Sheng ISO 45001 Sharon', NULL, NULL, '105 Sims Avenue #02-08 Chancerlodge Complex', 'Sim Kim Kam', NULL, NULL, '65-68464301', 'longshengconstructionpl@gmail.com', NULL, 'active', 'Consultant: ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(122, 'TWIN STAR ENGINEERING & OFFSHORE PTE. LTD', NULL, NULL, '51 Kerbau Road Singapore 219175', 'Mathavan Manikandan', NULL, NULL, '65- 62962728', 'manikandan@twinstarengg.com', NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(123, 'SAI ENGINEERING PTE. LTD', NULL, NULL, '51 Kerbau Road Singapore 219175', 'Mathavan Manikandan', NULL, NULL, '65- 62962728', 'saaiengg@gmail.com', NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(124, 'JACIN SECURITY SERVICES 9 and 45001 11 20 and 21 April SIS', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(125, 'Palace Builder ISO 45K IAS', NULL, NULL, NULL, 'Praba', NULL, NULL, '8120 0444', NULL, NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(126, 'S Tech Engineering 45001 Own', NULL, NULL, '280 Woodlands Industrial Park E5 #06-06 Harvest @ Woodlands', 'M Selvaraj', NULL, NULL, '65-84987231', 'stecheng55@gmail.com', NULL, 'active', 'Consultant: OWN | Source Client Status: Check', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(127, 'POWERPOINT Marine Services 45001 Own', NULL, NULL, '11 Tuas Bay Close #02-02 West Star', 'Annamalai Ramanathan', NULL, NULL, '65-6861 7251', 'Ramu.harvest@yahoo.com.sg', NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(128, 'BESTPOINT Marine Services 45001 Own', NULL, NULL, '11 Tuas Bay Close #02-02 West Star', 'Annamalai Ramanathan', NULL, NULL, '65-6861 7251', 'Ramu.harvest@yahoo.com.sg', NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(129, 'ENERGREEN TECHNOLOGIES PTE. LTD 45001 Zack', NULL, NULL, '39 WOODLANDS CLOSE, #08-70, MEGA@WOODLANDS, SINGAPORE 737856', 'TAN KIAN PENG', NULL, NULL, '65 81868338', 'joseph.tan@energreentechs.com', NULL, 'active', 'Consultant: ZACK/TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(130, 'S POWER Global', NULL, NULL, '5 SUNGEI KADUT STREET 2, #06-04, TRENDSPACE, SINGAPORE 729227', 'Mr Karuppiah Vadivel', NULL, NULL, NULL, NULL, NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(131, 'AE Models', NULL, NULL, 'Tampines Street 93, #02-206 Blk 9006, Industrial Park A, Singapore 528840', 'Mr Lewis Chan', NULL, NULL, '67861314', 'inquiry@aemodels.com', NULL, 'active', 'Consultant: TRICIA/ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(132, 'SAMMAR AUTOMATION IMS system Bala sir', NULL, NULL, '50 GENTING LANE, #01-03, CIDECO INDUSTRIAL COMPLEX, SINGAPORE 349558', 'G Sankar', NULL, NULL, '65-67442324', 'sankar@sanmarautomation.com', NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(133, 'NEXUS MANAGEMENT SERVICES – ISO 9001 Easan', NULL, NULL, 'BLOCK 9 PIONEER ROAD NORTH #01-57 SINGAPORE 628461', 'EDWIN CHAN', NULL, NULL, '65-97922240', 'accounts@lyc.com.sg', NULL, 'active', 'Consultant: EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(134, 'SUZI ISO 45001 WSH Expert', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 'Consultant: WSH EXPERT', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(135, 'LDC GENERAL CONSTRUCTION PTE LTD', NULL, NULL, '10 Kaki Bukit Road 1 #03-04 KB Industrial Building Singapore 416175', 'Mr. Ryan Ng', NULL, NULL, '62838608', 'Ryan.ng@ldc.com.sg', NULL, 'active', 'Consultant: EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(136, 'SHINCON INDUSTRIAL PTE LTD', NULL, NULL, NULL, 'Mr. Nakkeeran', NULL, NULL, '65130160', 'nakkeeeran@shinco.sg', NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(137, 'HONG AN ENGINEERING PTE LTD', NULL, NULL, NULL, 'Madam Daphne Chua', NULL, NULL, '65090990', 'daphne@haengrg.com.sg', NULL, 'active', 'Consultant: ZACK/EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(138, 'HUA RONG ENGINEERING PTE. LTD.', NULL, NULL, NULL, 'Ms.Angeline', NULL, NULL, '6339 1938', 'admin@huarong-eng.com', NULL, 'active', 'Consultant: TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(139, 'KEMVET COMMERCIAL BUILDERS PTE. LTD.', NULL, NULL, NULL, 'Edwin Wong', NULL, NULL, '9027 4801', 'catherine.cheong@kemvet.sg', NULL, 'active', 'Consultant: TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(140, 'PERMA-LINER INDUSTRIES (SINGAPORE) PTE LTD. 45K', NULL, NULL, NULL, 'Sriram Ganesan', NULL, NULL, '67104262', 'info@perma-liner.com.sg', NULL, 'active', 'Consultant: EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(141, 'BUILDING ASSOCIATES (S) PTE. LTD.', NULL, NULL, NULL, 'Tan Hock Han', NULL, NULL, '68533918', 'bldgasso@singnet.com.sg', NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(142, 'BEST NDT INSPECTION TECHNOLOGIES PTE. LTD.', NULL, NULL, NULL, 'Mr. Saravanan Sudakar', NULL, NULL, '65-84797783', 'sudakar@bestndtinspection.com', NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(143, 'JJGL VENTURES PTE LTD', NULL, NULL, NULL, 'Mr JAWAHAR', NULL, NULL, '65600523', 'jawaharlal@jjgl.com.sg', NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(144, 'BN SOLUTIONS (S) PTE LTD', NULL, NULL, NULL, 'Ms Lim Hwee Leng', NULL, NULL, '63393086', 'admin@bnsolutions.sg', NULL, 'active', 'Consultant: ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(145, 'HUA CHANG CONSTRUCTION PTE LTD', NULL, NULL, NULL, 'Wang Zhen Min Alex', NULL, NULL, '67453866', 'huachangcpl@gmail.com', NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(146, 'WIDE WINGS PTE LTD', NULL, NULL, NULL, 'Kaviarasu Balakumar', NULL, NULL, '91074050', 'hr@widewings.com.sg', NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(147, 'BUILTMECH PTE. LTD.', NULL, NULL, NULL, 'Ms Colleen Goh', NULL, NULL, '96861204', 'colleen@buliltmech.sg', NULL, 'active', 'Consultant: ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(148, 'VJ CONSTRUCTION PTE LTD', NULL, NULL, NULL, 'Mr. Samy', NULL, NULL, '91446990', 'vjcon19@gmail.com', NULL, 'active', 'Consultant: EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(149, 'F R R CONSTRUCTION PTE LTD', NULL, NULL, NULL, 'Mr. Periyasamy Ramar', NULL, NULL, '91816971', 'frrconstruction@frr.com.sg', NULL, 'active', 'Consultant: ZACK/EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(150, 'PERMA-LINER INDUSTRIES (SINGAPORE) PTE LTD.', NULL, NULL, NULL, 'Ravichandran Aranvindh', NULL, NULL, '97556604', 'info@perma-liner.com.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(151, 'RCY PTE LTD', NULL, NULL, NULL, 'Sekar Chandrasekar', NULL, NULL, '98515756', 'hr@rcy.com.sg', NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(152, 'ASIABUILD ENTERPRISES PTE LTD.', NULL, NULL, NULL, 'Krishnan Ravikumar', NULL, NULL, '9002 3514', 'project@asiabld.com', NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(153, 'HACELY (SINGAPORE) PTE. LTD.', NULL, NULL, NULL, 'Heng Hern', NULL, NULL, '62842998', 'hern.heng@hacely.com.sg', NULL, 'active', 'Consultant: ZACK/EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(154, 'EI CORPORATION PTE. LTD.', NULL, NULL, NULL, 'Collin Ng', NULL, NULL, '85884781', 'joanne@eicorp.com.sg', NULL, 'active', 'Consultant: Own', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(155, 'BSM STEEL CONSTRUCTION PTE. LTD.', NULL, NULL, NULL, 'Aminul Islam', NULL, NULL, '8105 5563', 'bsmsteelc@gmail.com', NULL, 'active', 'Consultant: EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(156, 'YONGYANG LIFT ENGINEERING PTE. LTD.', NULL, NULL, NULL, 'Ng Kah Yeow', NULL, NULL, '67485748', 'haikal@yongyang.com.sg', NULL, 'active', 'Consultant: EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(157, 'SUPERSKETCH DESIGNERS PTE. LTD.', NULL, NULL, NULL, 'Lau Chean Fung', NULL, NULL, '85357758', 'admin@supersketchdesigners.com.sg', NULL, 'active', 'Consultant: Own', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(158, 'STAMFORD POWER ENGINEERING PTE LTD', NULL, NULL, NULL, 'Phyllis Phua', NULL, NULL, '6842 3678', 'daryl_foo@spengrg.com', NULL, 'active', 'Consultant: ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(159, 'HUP GAY CIVIL ENGINEERING PTE LTD', NULL, NULL, NULL, 'CHEN CAI SHUANG', NULL, NULL, '66808123', 'hupgayhr@gmail.com', NULL, 'active', 'Consultant: ZACK/EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(160, 'FEBA TECHNOLOGIES PTE. LTD.', NULL, NULL, NULL, 'Mr. Daniel Yap', NULL, NULL, '98268788', 'danielyap@feba-asia.com', NULL, 'active', 'Consultant: EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(161, 'JETSEN DESIGN PTE LTD', NULL, NULL, NULL, 'Tan Chong Hong', NULL, NULL, '8448 7482', 'chtan@jdccgroup.com', NULL, 'active', 'Consultant: EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(162, 'DIVINE N\' DYNAMIC PTE. LTD.', NULL, NULL, NULL, 'Mr Tan Chaun Hee', NULL, NULL, '82989955', 'divinendynamic@gmail.com', NULL, 'active', 'Consultant: ZACK/EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(163, 'HOBBY CONSTRUCTION PTE LTD', NULL, NULL, NULL, 'Ms. Alice', NULL, NULL, '64822778', 'hobbycon@singnet.com.sg', NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(164, 'LUM CHANG BRANDSBRIDGE PTE LTD', NULL, NULL, NULL, 'Mr Victor Syn', NULL, NULL, '6259 3522', 'Daniel@lcbb.com.sg', NULL, 'active', 'Consultant: ZACK/EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(165, 'NEW TECHNOLOGY SOLUTIONS PTE LTD', NULL, NULL, NULL, 'Mdm Chan Chy', NULL, NULL, '92293595', 'enquiry@newtechnologysol.com', NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(166, 'BEST AEROSPACE NDT & INSPECTION SERVICE PTE LTD.', NULL, NULL, NULL, 'Mr Saravanan Sudhakar', NULL, NULL, '84797783', 'sudakar@bestndtinspection.com', NULL, 'active', 'Consultant: ZACK/EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(167, 'BEST NDT CONSTRUCTIONS & ENGINEERING PTE LTD.', NULL, NULL, NULL, 'Mr Saravanan Sudhakar', NULL, NULL, '84797783', 'sudakar@bestndtinspection.com', NULL, 'active', 'Consultant: ZACK/EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(168, 'INSPIRE ID GROUP PTE. LTD.', NULL, NULL, NULL, 'Ms Jasmine Ng', NULL, NULL, '6251 9300\n9001 9382', 'jasmine@inspireidgroup.com', NULL, 'active', 'Consultant: TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(169, 'KL E&C PROJECTS PTE. LTD.', NULL, NULL, NULL, 'Mr YAP KEE LIONG', NULL, NULL, '94579123', 'info@klec.sg', NULL, 'active', 'Consultant: ZACK/EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(170, 'POWERCORP PTE LTD', NULL, NULL, NULL, 'MR YAP HON KEONG', NULL, NULL, '97202319', 'info@powercorp.com.sg', NULL, 'active', 'Consultant: ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(171, 'HUAY ARCHITECTS PTE LTD', NULL, NULL, NULL, 'Ms Lim Teck Cheng', NULL, NULL, '6372 0183', 'teckcheng_lim@huayarchitects.com', NULL, 'active', 'Consultant: ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(172, 'HONGZE CONSTRUCTION BUILDERS PTE LTD', NULL, NULL, NULL, 'Lily Liu', NULL, NULL, '69705022', 'hongzecb@gmail.com', NULL, 'active', 'Consultant: EASAN/TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(173, 'ACQUAL ENGINEERING LLP 45k', NULL, NULL, NULL, 'MR WILSON', NULL, NULL, '98555579', 'Wilson@acqualengineering.com', NULL, 'active', 'Consultant: TRICIA', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(174, 'YJME ENGINEERING PTE LTD', NULL, NULL, NULL, 'Mr Zhang Lizhong', NULL, NULL, '62350418 \n 98199720', 'lizhong@yjme.com.sg', NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(175, 'M&S MANAGEMENT & CONTRACTS SERVICES PTE LTD', NULL, NULL, NULL, 'Mdm Maria Bte Abdullah', NULL, NULL, '97202319', 'maria@mnsmgmt.com.sg', NULL, 'active', 'Consultant: ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(176, 'SAM WOO (S.E.A.) PTE LTD', NULL, NULL, NULL, 'Mdm Maria Bte Abdullah', NULL, NULL, '97202319', 'maria@mnsmgmt.com.sg', NULL, 'active', 'Consultant: ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(177, 'SANDHU_MAN CONTRACTS SERVICES PTE. LTD.', NULL, NULL, NULL, 'Mdm Maria Bte Abdullah', NULL, NULL, '97202319', 'maria@mnsmgmt.com.sg', NULL, 'active', 'Consultant: ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(178, 'SIN GUAN TECK PTE LTD', NULL, NULL, NULL, 'Mr Ning Kai Wei', NULL, NULL, '8571 3585', 'admin@sgt.com.sg', NULL, 'active', 'Consultant: ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(179, 'ASTON AIR CONTROL PTE LTD', NULL, NULL, NULL, 'Mr. John Peter', NULL, NULL, '98347806', 'johnpeter@astonair.com', NULL, 'active', 'Consultant: OWN/ RMJ MANI', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(180, 'AIRVERCLEAN PTE LTD', NULL, NULL, NULL, 'Mr Devin', NULL, NULL, '8859 9303', 'devin@airverclean.com', NULL, 'active', 'Consultant: EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(181, 'SHIN-ECON CORPORATION PTE LTD', NULL, NULL, NULL, 'Mr Rajamanickam Nakkeeran', NULL, NULL, '65130160', 'nakkeeran@shincon.sg', NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(182, 'LV AUTOMATION PTE LTD', NULL, NULL, NULL, 'Ms Ai Leng', NULL, NULL, '91063551', 'aileng@lva.com.sg', NULL, 'active', 'Consultant: ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(183, 'ELECTRICAL PRODUCT INTERNATIONAL PTE LTD (SAC)', NULL, NULL, NULL, 'Chua LEE LEE', NULL, NULL, '96464101', 'jodineyam@gmail.com', NULL, 'active', 'Consultant: EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(184, 'YSG ELECTRICAL & ENGINEERING LLP', NULL, NULL, NULL, 'Mr You Voon Long', NULL, NULL, '6262 5581', 'admin@longgeec.com', NULL, 'active', 'Consultant: ZACK/IVY', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(185, 'TECHNO CE PTE LTD', NULL, NULL, NULL, 'Mr Yong Seow Wai', NULL, NULL, '6745 5725', 'yongsw@technoprise.com.sg', NULL, 'active', 'Consultant: PAUL', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(186, 'ARS MANUFACTURER PTE LTD', NULL, NULL, NULL, 'Mr Roger Tan', NULL, NULL, '6372 0183', 'sales@arsadmin.com', NULL, 'active', 'Consultant: PAUL', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(187, 'CHINA RAILWAY CONSTRUCTION GROUP CORPORATION LIMITED', NULL, NULL, NULL, 'Mr Lai Hai Bing', NULL, NULL, '91773006', 'laihaibing.sg@gmail.com', NULL, 'active', 'Consultant: ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(188, 'ANACHEM TECHNOLOGIES (S) PTE LTD', NULL, NULL, NULL, 'Daniel Soo', NULL, NULL, '63167542', 'yana@anachem.com.sg', NULL, 'active', 'Consultant: PAUL', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(189, 'LIMELIGHT ATELIER PTE LTD', NULL, NULL, NULL, 'Melvyn Law', NULL, NULL, '6702 3185', 'melvyn@limelightatelier.com', NULL, 'active', 'Consultant: EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(190, 'NJNCC CHEMICAL CONSTRUCTION PTE LTD', NULL, NULL, NULL, 'Zhang Sen', NULL, NULL, '9136 5615', 'zhangsen@njncc.com', NULL, 'active', 'Consultant: ZACK', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(191, 'JMJ CONSULTANTS PTE LTD', NULL, NULL, NULL, 'Mr Tommy Toh', NULL, NULL, '91456584', 'Tommy.jmjcrossroad@gmail.com', NULL, 'active', 'Consultant: EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(192, 'B4 WATER LEAKAGE SPECIALIST PRIVATE LTD', NULL, NULL, NULL, 'Mr Shawn Lim', NULL, NULL, '9298 8181', 'admin@b4waterleakage.com.sg', NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(193, 'LN ART ID STUDIO PTE LTD 45k', NULL, NULL, NULL, 'Ms Brenda Chan', NULL, NULL, '8866 1923', 'Brenda.chan@lnartid.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(194, 'PLS PILING PTE LTD', NULL, NULL, NULL, 'Liang Wee An', NULL, NULL, '69247595', 'pls.piling@gmail.com', NULL, 'active', 'Consultant: PAUL', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(195, 'ASTA VENTURES PTE LTD', NULL, NULL, NULL, 'Mr. Jawaharlal', NULL, NULL, '93531061', 'hr@astaventures.com.sg', NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(196, 'TEC SQUARE PTE LTD', NULL, NULL, NULL, 'Shermaine - MR', NULL, NULL, '9435 3468', 'shermaine@tecsquare.com.sg', NULL, 'active', 'Consultant: PANDIAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(197, 'NCE CORPORATION (S) PTE. LTD.', NULL, NULL, NULL, 'Victor', NULL, NULL, '80768345', 'admin@ncecorporation.sg', NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(198, 'A-POWER PROJECT & ENGRG PTE LTD', NULL, NULL, NULL, 'Eric Heng', NULL, NULL, '9171 9143', 'eric.heng@apowerpe.com', NULL, 'active', 'Consultant: PANDIAN -tbc', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(199, 'CITIWALL PTE LTD', NULL, NULL, NULL, 'Ms Fiona', NULL, NULL, '81631100', 'admin@citiwall.com.sg', NULL, 'active', 'Consultant: VINCENT', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(200, 'CITIWALL ENGINEERING PTE LTD', NULL, NULL, NULL, 'Ms Fiona', NULL, NULL, '81631100', 'admin@citiwall.com.sg', NULL, 'active', 'Consultant: VINCENT', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(201, 'PSC FREYSSINET (SINGAPORE) PTE LTD', NULL, NULL, NULL, 'Mr Sheik', NULL, NULL, '90964365', 'sheik.abdullah@freyssinet.com.sg', NULL, 'active', 'Consultant: ZACK/EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(202, 'AJ SIXTY ONE PTE LTD', NULL, NULL, NULL, 'Mr Abbas', NULL, NULL, '8363 4675', 'info.ajsixtyone@gmail.com', NULL, 'active', 'Consultant: OWN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(203, 'WU YI BUILDING CONSTRUCTION PTE. LTD.', NULL, NULL, NULL, 'Tang Jie', NULL, NULL, '97360231', 'tang.wuyibc@gmail.com', NULL, 'active', 'Consultant: ZACK/EASAN', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(204, 'SPECTRUM GLOBAL ENGINEERING PTE. LTD.', NULL, 'Provision of General Construction Works', '53 UBI AVENUE 1, #05-24, PAYA UBI INDUSTRIAL PARK, SINGAPORE, 408934, SINGAPORE', 'Mr Senthil', NULL, NULL, '94877586', 'Spectrum.global.eng@gmail.com', NULL, 'active', 'ISIC 41001 | EHS Client No: EUCA2423', '2026-08-06 09:11:30', '2026-08-06 09:11:30');
INSERT INTO `cm_clients` (`id`, `company_name`, `uen_registration_no`, `industry_sector`, `address`, `contact_person`, `contact_designation`, `consultant`, `phone`, `email`, `website`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(205, 'CHINA RAILWAY ENGINEERING EQUIPMENT GROUP CO., LTD.\n\nSINGAPORE BRANCH', NULL, 'Other specialised construction and related activities N.E.C,', '1004 TOA PAYOH NORTH, #06-15/17,, SINGAPORE, 318995, SINGAPORE', 'Jade Wang', NULL, NULL, '6747 8091', 'jadewang@crectbm.com', NULL, 'active', 'ISIC 46549 | EHS Client No: EUCA2433', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(206, 'IET PTE LTD', NULL, 'Minor Construction works, fabrication of steel structure for\nbuilding construction and supply of ski', '212 Hougang Street 21, #04-345,, SINGAPORE, 530212, SINGAPORE', 'Mr Ravi K', NULL, NULL, '9190 2627', 'ravi@iet.com.sg', NULL, 'active', 'ISIC 41001 | EHS Client No: EUCA2425', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(207, 'VSK CONSTRUCTION PTE LTD', NULL, 'Provision of General Building Construction works', '01 BUKIT BATOK CRESCENT 07-01 WEGA PLAZA,, SINGAPORE, 658064, SINGAPORE', 'Mr. B Ravichandran', NULL, NULL, '6776 0701', 'vskconstruction@ymail.com', NULL, 'active', 'ISIC 41009 | EHS Client No: EUCA2428', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(208, 'CHIN SIONG ELECTRICAL ENGINEERING PTE LTD.', NULL, 'Provision of Electrical Works', '4001 ANG MO KIO INDUSTRIAL PARK 1 #01-05, SINGAPORE, 569622, SINGAPORE', 'Ms Chong', NULL, NULL, '6552-6646', 'cselect@singnet.com.sg', NULL, 'active', 'ISIC 43210 | EHS Client No: EUCA2431', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(209, 'IA Builders & Engineering Pte Ltd', NULL, 'Provision of Interior Fit-Out and Reinstatement Works', '138 CECIL STREET, #07-01,\nCECIL COURT, SINGAPORE, 069538, SINGAPORE', 'Alan Chong', NULL, NULL, '63280870', 'sazzad.hossain@iab.com.sg', NULL, 'active', 'ISIC 41001 | EHS Client No: EUCA2432', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(210, '5 MASONS PTE LTD.', NULL, 'Builder Works including Partition, Flooring, Tiling, Carpentry and Painting', '61 Ubi Road 1 #01-28 Oxley BizHub Singapore 408727, SINGAPORE, 408727, SINGAPORE', 'Chong Lee Ling', NULL, NULL, '65882668', 'admin@5m.com.sg', NULL, 'active', 'Consultant: Eddie | ISIC 41001 | EHS Client No: EUCA2515', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(211, 'EDMUND TIE & COMPANY PROPERTY MANAGEMENT SERVICES PTE. LTD', NULL, 'Property Management', 'Blk 750 Chai Chee Road #03-09 Viva Business Park \nSingapore, SINGAPORE, 469000, SINGAPORE', 'Yee Peng Koh', NULL, NULL, '6417 9222', 'yeepeng.koh@etcsea.com', NULL, 'active', 'Consultant: ruthu | ISIC 41001 | EHS Client No: EUCA2518', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(212, 'FORMAX VENTURES PTE LTD', NULL, 'Provision of Project Management and Supervision Services', '1 MACTAGGART ROAD, #04-01A1, INVEST HO BUILDING,\n\nSINGAPORE, SINGAPORE, 368089, SINGAPORE', 'Prabhu', NULL, NULL, '91558331', 'hr@formax.com.sg', NULL, 'active', 'Consultant: Own | ISIC 41001 | EHS Client No: EUCA2519', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(213, 'NRMF PTE. LTD.', NULL, 'Design, Installation & Maintenance of Building Automation System', '1 Bukit Batok Crescent, Wcega Plaza, #02-30, SINGAPORE, 658064, SINGAPORE', 'Sean Chen', NULL, NULL, '9867 5999', 'sean.chen@nrmf-eng.com', NULL, 'active', 'Consultant: Gsi | ISIC 43293 | EHS Client No: EUCA2525', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(214, 'JD HDD PTE LTD', NULL, 'Provision of Cable, Pipe Laying and Road Reinstatement Services', '22 Sin Ming Lane, #06-76 Midview City,, SINGAPORE, 573969, SINGAPORE', 'Helmi', NULL, NULL, '80602724', 'hdd@jdhdd.org', NULL, 'active', 'ISIC 42101 | EHS Client No: EUCA2529', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(215, 'ON TIME ENGINEERING PTE. LTD.', NULL, 'GENERAL CONTRACTORS (BUILDING CONSTRUCTION INCLUDING MAJOR UPGRADING WORKS)', '71 BUKIT BATOK CRESCENT #11-03 PRESTIGE CENTRE, SINGAPORE, 658071, SINGAPORE', 'Vedha.S', NULL, NULL, '8227 4243', 'ontimeengg@gmail.com', NULL, 'active', 'ISIC 41001 | EHS Client No: EUCA2535', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(216, 'CEG INDUSTRIES PTE LTD', NULL, 'Provision of General Contractors', 'Blk 77 Geylang Bahru #01-2830, Singapore, SINGAPORE, 339685, SINGAPORE', 'Jimmy Lim', NULL, NULL, '9850 6633', 'cegindustries18@gmail.com', NULL, 'active', 'ISIC 41001 | EHS Client No: EUCA2536', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(217, 'PRM ENGINEERING PTE. LTD.', NULL, 'GENERAL CONTRACTORS (SCAFFOLDINGS)', '304B ANCHORVALE LINK, #05-02, ANCHORVALE COURT, SINGAPORE, SINGAPORE, 542304, SINGAPORE', 'Prabha', NULL, NULL, '90736756', 'prabha@prmscaffold.com', NULL, 'active', 'ISIC 41001 | EHS Client No: EUCA2537', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(218, 'WANG SHENG DESIGN & BUILD PTE. LTD.', NULL, 'General Construction', '1 Tampines North Dr. 1, #06-31 T-Space, Singapore, SINGAPORE, 528559, SINGAPORE', 'Jacky', NULL, NULL, '97158282', 'wangsheng09@hotmail.com', NULL, 'active', 'ISIC 46639 | EHS Client No: EUCA2540', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(219, 'ROBOTPACK FLEXIBLE AUTOMATION SYSTEM(S) PTE LTD', NULL, 'Provision of total material handling systems including robotic\nintegration services', '9, YISHUN INDUSTRIAL STREET 1, #02-79, NORTH SPRING BIZHUB, SINGAPORE, SINGAPORE, 768163, SINGAPORE', 'Jacky Pui', NULL, NULL, '8817 9551', 'jackypui@robotpack.com.sg', NULL, 'active', 'ISIC 28169 | EHS Client No: EUCA2541', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(220, 'GREEN CARE SERVICES PTE. LTD.', NULL, 'Cleaning Works', '101 KITCHENER ROAD #02-32 JALAN BESAR PLAZA SINGAPORE, SINGAPORE, 208511, SINGAPORE', 'Stacy', NULL, NULL, '8183 8686', 'stacy@greencare.sg', NULL, 'active', 'ISIC 81211 | EHS Client No: EUCA2543', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(221, 'PERFECT STEEL PTE. LTD', NULL, 'Fabrication of Steel Reinforcement Products', '8 TUAS SOUTH STREET 11 Singapore, SINGAPORE, 637093, SINGAPORE', 'Corene', NULL, NULL, '8308 4738', 'corene@perfectsteel.com.sg', NULL, 'active', 'ISIC 42101 | EHS Client No: EUCA2545', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(222, 'CAN CAN M&E PTE. LTD.', NULL, 'M&E Works, Duct Works, Aircon Installation & Servicing Works', '31 BUKIT BATOK CRESCENT, #01-49, THE SPLENDOUR, SINGAPORE, SINGAPORE, 658070, SINGAPORE', 'Elaine', NULL, NULL, '9730 2535', 'admin@cancan.com.sg', NULL, 'active', 'ISIC 43220 | EHS Client No: EUCA2547', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(223, 'Best cool air con Pte ltd (Need to update', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 'INCOMPLETE — needs update', '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(224, 'IET PTE LTD - IMS & ISO 27001 AX', NULL, NULL, NULL, 'Ravi Kumar', NULL, NULL, '69705539', 'admin@iet.com.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(225, 'URC PTE LTD - IMS GSI', NULL, NULL, NULL, 'Kumaresan Rajkumar', NULL, NULL, '9731 9162', 'rajkumar.kumaresan@urcc.in', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(226, 'UMA MARINE - IMS AX', NULL, NULL, NULL, 'MAILAPALLI VARAPRASAD CHIRANJEEV', NULL, NULL, '8346 0961', 'operations-sng@umamarine.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(227, 'JSBROD IMS ISMS MOCKUP _ VOID', NULL, NULL, NULL, 'S. Davin', NULL, NULL, '69049923', 's.davin@jsbrod.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(228, 'Shaftrise Engineering & Services GSI', NULL, NULL, NULL, 'SIVANANTHA MURTHEE', NULL, NULL, '94463116', 'sales@shaftrise.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(229, 'ADROITZ 45k AX', NULL, NULL, NULL, 'Zeon Loh', NULL, NULL, '8727 0782', 'enquiry@adroitz.com.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(230, 'ALTROCKS ISO 9k ISO 27k # AX', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(231, 'ROBERT BORSH 45k GSI', NULL, NULL, NULL, 'Gabriel', NULL, NULL, '91192047', 'alflovicgabrielHidalgo.Ordonez@sg.bosch.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(232, 'Practical Analyzer Solutions Pte Ltd 45k GSI', NULL, NULL, NULL, 'Mr Ben Lukito', NULL, NULL, '6836 8383', 'ben.lukito@paspl.com.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(233, 'SHOWTEC INTERNATIONAL 45K GSI', NULL, NULL, NULL, 'Ms Cynthia Ow', NULL, NULL, '87547543', 'cynthia_ow@showtecgroup.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(234, 'ALL BEST GROUP - IMS_ GSI', NULL, NULL, NULL, 'Mr Nagaraju', NULL, NULL, '82936062', 'nagaraju@allbestmarine.com.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(235, 'GREEN COSMOS MARKETING 45k GSI', NULL, NULL, NULL, 'Gerald', NULL, NULL, '96311017', 'greencosmos.hq@gmail.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(236, 'CHCT IMS GSI', NULL, NULL, NULL, 'Felix Wong', NULL, NULL, '84981854', 'wong.weijie.60002@cicahuntek.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(237, 'AKTIO PACIFIC - 9k 45k GSI', NULL, NULL, NULL, 'Goh Meng Hoon', NULL, NULL, '62311662', 'goh_meng_hoon@aktio.com.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(238, 'RMJ CONSTRUCTION & ENGINEERING  3 system _RMJ', NULL, NULL, NULL, 'MANIKKAM RAJA', NULL, NULL, '93752434', 'contactrmjce@gmail.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(239, 'SOVERUS KINGDOM IMS GSI', NULL, NULL, NULL, 'Mr Win Cha', NULL, NULL, '9102 9643', 'win.cha@soverus.com.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(240, 'HI-GREEN LANDSCAPE_ ISO 45 TFR AX CCC', NULL, NULL, NULL, 'Sagayanathan', NULL, NULL, '9811 0434', 'landscape@higreen.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(241, 'GEOLUTIONS PTE LTD - ISO 9k ISO 45k_ Paul', NULL, NULL, NULL, 'Mr Chris Cheng', NULL, NULL, '8788 2727', 'chris@geolutions.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(242, 'ELECTROMECH TECHNOLOGIES PTE LTD ISO 27001 AX', NULL, NULL, NULL, 'Ms.Bella Zeng', NULL, NULL, '8298 0592', 'bella@apmglobal.com.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(243, 'GLOBAL PTE LTD 9k 45k AX', NULL, NULL, NULL, 'Ms.Bella Zeng', NULL, NULL, '8298 0592', 'bella@apmglobal.com.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(244, 'FJ TECHNICAL PTE. LTD. 9k 45k AX', NULL, NULL, NULL, 'Ms.Bella Zeng', NULL, NULL, '8298 0592', 'bella@apmglobal.com.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(245, 'URK ENGINEERING PL 45k -AX', NULL, NULL, NULL, 'Vasanthi', NULL, NULL, '9787 5697', 'urkengineering@gmail.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(246, 'JACIN Security Services 9&45 AX', NULL, NULL, NULL, 'Ramani', NULL, NULL, '9191 4413', 'jacinsecure@live.com.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(247, 'STALWART ENGINEERING IMS AX', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(248, 'CL Flooring 9k 45k # Ivy GSI', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'finance@clgroup.com.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(249, 'SIXES XIOLIFT PTE LTD IMS # GSI', NULL, NULL, NULL, 'Sisilia', NULL, NULL, '93468828', 'Sisilia@xiolift.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(250, 'GOLDEN TRINITY CONSTRUCTION 45k HELMI', NULL, NULL, NULL, 'Chai Boon Kee', NULL, NULL, '96269872', 'Goldentrinity2022@gmail.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(251, 'UNITEK ENGINEERING PTE. LTD. 9K 45k AX', NULL, NULL, NULL, 'Thangavelu Manikandan', NULL, NULL, '98531975', 'maniunitec@gmail.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(252, 'SMART DOORS 9k 45k Zack/Tricia GSI', NULL, NULL, NULL, 'Mr CP Low', NULL, NULL, '9776 3821', 'cplowchanpong@yahoo.com.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(253, 'D CRAFT PTE. LTD. 9k 45k Zack/Vincent GSI', NULL, NULL, NULL, 'Desmond Koh', NULL, NULL, '9646 9559', 'desmondkoh@dcraft.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(254, 'HONGDA ENGINEERING PTE. LTD. 45k Paul', NULL, NULL, NULL, 'Kok Cin Hau', NULL, NULL, '8768 5099', 'Hongda.engineering.sg@gmail.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(255, 'Anergy Solutions Pte Ltd 45K GSI Ruthu', NULL, NULL, NULL, 'Ian Tan', NULL, NULL, '9478 2865', 'ian@anergysolutions.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(256, 'REGIUS BUILDER PTE. LTD. 45k #  On going', NULL, NULL, NULL, 'Ms Amy', NULL, NULL, '8872 0396', 'regius.builder@yahoo.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(257, 'APE Engineering Pte Ltd 45k Ruthu GSI', NULL, NULL, NULL, 'Sivarajasai', NULL, NULL, '8398 6258', 'r.siva@ape.com.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(258, 'STEP-UP ENGINEERING 9k 14k 45k GSI Ruthu', NULL, NULL, NULL, 'Jason', NULL, NULL, '9351 6144', 'stepupecpl@gmail.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(259, 'KIM SOON HUAT CONSTRUCTION 9k 14k 45k GSI Ruthu', NULL, NULL, NULL, 'Royston Chai', NULL, NULL, '9223 9889', 'kshconstructionpl@gmail.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(260, 'Singapore Island Country Club  45k  GSI Easan', NULL, NULL, NULL, 'Kenneth Teo', NULL, NULL, '6431 8258', 'kenneth.teo@sicc.org.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(261, 'KRISE SOLUTIONS PTE LTD 9&27 Sitha', NULL, NULL, NULL, 'SIBASHIS', NULL, NULL, '85113734', 'hr@krise.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(262, 'TOPWELD ENGINEERING & CONSTRUCTION 9K # AX', NULL, NULL, NULL, 'Sakthivel', NULL, NULL, '98919119', 'sales@topweld.com.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(263, 'APEC GROUP GSI Ruthu', NULL, NULL, NULL, 'Ms Jamie', NULL, NULL, '8611 5566', 'Jamie.lim@apecequip.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(264, 'JS Door Interior IMS  AX', NULL, NULL, NULL, 'Delwin Ong', NULL, NULL, '96664919', 'delwin@jsgroup.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(265, 'LUMINARY SERVICES 45K  Easan', NULL, NULL, NULL, 'Eric', NULL, NULL, '9728 2852', 'eric@luminary.com.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(266, 'CHEN DA CONSTRUCTION 45k # ZACK/Vincent  GSI', NULL, NULL, NULL, 'LAI HO', NULL, NULL, '82688093', 'chenda9188@gmail.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(267, 'AC TESLA PTE. LTD.  45k # AX', NULL, NULL, NULL, 'Ms.Genee', NULL, NULL, NULL, 'jenee.chong@ac-tesla.com.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(268, 'KSV CONSTRUCTION IMS # AX', NULL, NULL, NULL, 'Kumaresan', NULL, NULL, '91049646', 'enquiry@kspartners.com.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(269, 'ZHE JIANG 45k  GSI Zack', NULL, NULL, NULL, 'Morgan Yuen', NULL, NULL, '80677837', 'morganyuen@znjs.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(270, 'HESTIA ENGINEERING PTE. LTD 45k Helmi', NULL, NULL, NULL, 'Kvin Teo Chuan Hui', NULL, NULL, '83110669', 'Kvin.hestia@gmail.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(271, 'JY EnergyGrid Pte Ltd # GSI Anita', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(272, 'TWINSTAR 45k AX', NULL, NULL, NULL, 'MANIKANDAN', NULL, NULL, '85786574', 'manikandan@twinstarengg.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(273, 'LEE CYCLE # 9k  GSI Zack', NULL, NULL, NULL, 'Ms Meng Yee', NULL, NULL, '63850831', 'meng_yee@leecycleresources.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(274, 'WKS INDUSTRIAL GAS PTE LTD #GSI Anita', NULL, NULL, NULL, 'VINCENT WEE', NULL, NULL, '96246839', 'vincent@wks.com.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(275, 'YS Construction Services Pte Ltd 45k IVY GSI', NULL, NULL, NULL, 'Eric', NULL, NULL, '93859448', 'eric@ysconstruction.com.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(276, 'S POWER Global IMS AX', NULL, NULL, NULL, 'Karuppiah Vadivel', NULL, NULL, NULL, 'spower7171@gmail.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(277, 'JG Builders  45k 14k Easan', NULL, NULL, NULL, 'Mr N.Sudha', NULL, NULL, '83551216', 'nara.sudha@jgbuilders.com.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(278, 'ARUN Electrical  Solutions Pte Ltd 9and 45_Sitha', NULL, NULL, NULL, 'Karthik', NULL, NULL, '+65 6425 0726', 'karthik@arungroup.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(279, 'GLOBAL ACE CONSTRUCTION _ RMJ 45k , 9k SAC', NULL, NULL, NULL, 'Suresh', NULL, NULL, '83909633', 'mrskumar@globalacecons.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(280, 'CHINA STAR BUILDING CONSTRUC_RMJ 9 & 14', NULL, NULL, NULL, 'Ms Meng Yee', NULL, NULL, '63850831', 'meng_yee@leecycleresources.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(281, 'CL CONSTRUCTION 9k 45k GSI Ivy #', NULL, NULL, NULL, 'Ms. Ivy', NULL, NULL, '67104270', 'finance@clgroup.com.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(282, 'HENG SENG HIN 45K Zack', NULL, NULL, NULL, 'Jean', NULL, NULL, '6846 4301', 'jean@clerical.com.sg', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(283, 'SKN GALAXY (S) Pte Ltd 9k &45K # AX', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'skngalaxy73@gmail.com', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30'),
(284, 'BOLTTECH INSURANCE BROKERS PTE. LTD. 9k&45k # Jason', NULL, NULL, NULL, 'James', NULL, NULL, '8332 3782', 'james.tan@bolttech.io', NULL, 'active', NULL, '2026-08-06 09:11:30', '2026-08-06 09:11:30');

-- --------------------------------------------------------

--
-- Table structure for table `cm_client_followup_notes`
--

CREATE TABLE `cm_client_followup_notes` (
  `id` int UNSIGNED NOT NULL,
  `cm_client_id` int UNSIGNED NOT NULL,
  `cm_certification_id` int UNSIGNED DEFAULT NULL,
  `activity_type` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `note` text COLLATE utf8mb4_general_ci NOT NULL,
  `outcome` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status_changed_to` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_by_name` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cm_client_followup_notes`
--

INSERT INTO `cm_client_followup_notes` (`id`, `cm_client_id`, `cm_certification_id`, `activity_type`, `note`, `outcome`, `status_changed_to`, `created_by`, `created_by_name`, `created_at`) VALUES
(1, 62, NULL, NULL, '\"test\"', NULL, NULL, 1, 'Pandian', '2026-09-01 11:58:57'),
(2, 34, NULL, 'email', 'Email sent', NULL, NULL, 1, 'Pandian', '2026-09-01 13:00:06');

-- --------------------------------------------------------

--
-- Table structure for table `cm_renewal_alerts`
--

CREATE TABLE `cm_renewal_alerts` (
  `id` int UNSIGNED NOT NULL,
  `cm_certification_id` int UNSIGNED NOT NULL,
  `alert_threshold_days` smallint UNSIGNED NOT NULL,
  `status` enum('pending','sent','acknowledged') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cm_scheme_types`
--

CREATE TABLE `cm_scheme_types` (
  `id` int UNSIGNED NOT NULL,
  `category` enum('ISO','BizSafe','JASANZ','Other') NOT NULL,
  `name` varchar(100) NOT NULL,
  `default_cycle_years` tinyint UNSIGNED NOT NULL DEFAULT '3',
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `cm_scheme_types`
--

INSERT INTO `cm_scheme_types` (`id`, `category`, `name`, `default_cycle_years`, `description`, `created_at`) VALUES
(1, 'ISO', 'ISO 9001', 3, 'Quality Management System', '2026-07-29 12:13:30'),
(2, 'ISO', 'ISO 14001', 3, 'Environmental Management System', '2026-07-29 12:13:30'),
(3, 'ISO', 'ISO 45001', 3, 'Occupational Health & Safety Management System', '2026-07-29 12:13:30'),
(4, 'ISO', 'ISO 27001', 3, 'Information Security Management System', '2026-07-29 12:13:30'),
(5, 'BizSafe', 'BizSafe Star', 2, 'WSH Council BizSafe Star certification', '2026-07-29 12:13:30'),
(6, 'BizSafe', 'BizSafe Level 3', 2, 'WSH Council BizSafe Level 3 certification', '2026-07-29 12:13:30'),
(7, 'BizSafe', 'BizSafe Level 4', 2, 'WSH Council BizSafe Level 4 certification', '2026-07-29 12:13:30'),
(8, 'ISO', 'ISO 37001', 3, 'Anti-Bribery Management System', '2026-08-06 09:07:52');

-- --------------------------------------------------------

--
-- Table structure for table `cm_settings`
--

CREATE TABLE `cm_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `cm_settings`
--

INSERT INTO `cm_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('renewal_alert_thresholds', '30,45,60', '2026-08-28 10:20:40');

-- --------------------------------------------------------

--
-- Table structure for table `crm_activity_log`
--

CREATE TABLE `crm_activity_log` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `action` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `entity_type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `entity_id` int UNSIGNED DEFAULT NULL,
  `details` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `crm_activity_log`
--

INSERT INTO `crm_activity_log` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `details`, `created_at`) VALUES
(2, 1, 'create_lead', 'crm_lead', 2, 'ABC Pte Ltd', '2026-08-26 08:27:24');

-- --------------------------------------------------------

--
-- Table structure for table `crm_followups`
--

CREATE TABLE `crm_followups` (
  `id` int UNSIGNED NOT NULL,
  `crm_lead_id` int UNSIGNED NOT NULL,
  `due_date` date NOT NULL,
  `type` enum('call','email','meeting','whatsapp','other') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'call',
  `owner_id` int UNSIGNED DEFAULT NULL,
  `owner_name` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `note` text COLLATE utf8mb4_general_ci,
  `done` tinyint(1) NOT NULL DEFAULT '0',
  `done_at` timestamp NULL DEFAULT NULL,
  `reminder_sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crm_leads`
--

CREATE TABLE `crm_leads` (
  `id` int UNSIGNED NOT NULL,
  `company_name` varchar(200) COLLATE utf8mb4_general_ci NOT NULL,
  `contact_person` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `contact_designation` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `normalized_phone` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `normalized_email` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `normalized_company` varchar(200) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `industry_sector` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `source` enum('whatsapp','referral','website','cold_call','exhibition','other') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'other',
  `stage` enum('enquiry','lead','quotation','negotiation','awarded','lost','on_hold') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'enquiry',
  `owner_id` int UNSIGNED DEFAULT NULL,
  `owner_name` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `lost_reason` text COLLATE utf8mb4_general_ci,
  `on_hold_reason` text COLLATE utf8mb4_general_ci,
  `converted_client_id` int UNSIGNED DEFAULT NULL,
  `converted_at` timestamp NULL DEFAULT NULL,
  `notes` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `crm_leads`
--

INSERT INTO `crm_leads` (`id`, `company_name`, `contact_person`, `contact_designation`, `phone`, `email`, `normalized_phone`, `normalized_email`, `normalized_company`, `industry_sector`, `source`, `stage`, `owner_id`, `owner_name`, `lost_reason`, `on_hold_reason`, `converted_client_id`, `converted_at`, `notes`, `created_at`, `updated_at`) VALUES
(2, 'ABC Pte Ltd', 'Samuel', NULL, '7339431221', 'samsameie13@gmail.com', '7339431221', 'samsameie13@gmail.com', 'abc', 'Software', 'whatsapp', 'enquiry', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-26 08:27:24', '2026-08-26 08:27:24');

-- --------------------------------------------------------

--
-- Table structure for table `crm_lead_stage_history`
--

CREATE TABLE `crm_lead_stage_history` (
  `id` int UNSIGNED NOT NULL,
  `crm_lead_id` int UNSIGNED NOT NULL,
  `from_stage` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `to_stage` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `reason` text COLLATE utf8mb4_general_ci,
  `changed_by` int UNSIGNED DEFAULT NULL,
  `changed_by_name` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `crm_lead_stage_history`
--

INSERT INTO `crm_lead_stage_history` (`id`, `crm_lead_id`, `from_stage`, `to_stage`, `reason`, `changed_by`, `changed_by_name`, `changed_at`) VALUES
(2, 2, NULL, 'enquiry', NULL, 1, 'Pandian', '2026-08-26 08:27:24');

-- --------------------------------------------------------

--
-- Table structure for table `crm_quotations`
--

CREATE TABLE `crm_quotations` (
  `id` int UNSIGNED NOT NULL,
  `crm_lead_id` int UNSIGNED NOT NULL,
  `version` int UNSIGNED NOT NULL DEFAULT '1',
  `quote_number` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('draft','sent','accepted','rejected','expired') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'draft',
  `valid_until` date DEFAULT NULL,
  `currency` varchar(10) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'SGD',
  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `tax_percent` decimal(5,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `notes` text COLLATE utf8mb4_general_ci,
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_by_name` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crm_quotation_items`
--

CREATE TABLE `crm_quotation_items` (
  `id` int UNSIGNED NOT NULL,
  `crm_quotation_id` int UNSIGNED NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `qty` decimal(10,2) NOT NULL DEFAULT '1.00',
  `unit_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `line_total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `sort_order` int UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `holidays`
--

CREATE TABLE `holidays` (
  `id` int UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `name` varchar(150) NOT NULL,
  `type` enum('public_holiday','company_holiday') NOT NULL DEFAULT 'public_holiday'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `holidays`
--

INSERT INTO `holidays` (`id`, `date`, `name`, `type`) VALUES
(1, '2026-01-01', 'New Year\'s Day', 'public_holiday'),
(2, '2026-02-17', 'Chinese New Year', 'public_holiday'),
(3, '2026-02-18', 'Chinese New Year', 'public_holiday'),
(4, '2026-03-21', 'Hari Raya Puasa', 'public_holiday'),
(5, '2026-04-03', 'Good Friday', 'public_holiday'),
(6, '2026-05-01', 'Labour Day', 'public_holiday'),
(7, '2026-05-27', 'Hari Raya Haji', 'public_holiday'),
(8, '2026-05-31', 'Vesak Day', 'public_holiday'),
(9, '2026-08-09', 'National Day', 'public_holiday'),
(10, '2026-11-08', 'Deepavali', 'public_holiday'),
(11, '2026-12-25', 'Christmas Day', 'public_holiday');

-- --------------------------------------------------------

--
-- Table structure for table `personal_schedule_items`
--

CREATE TABLE `personal_schedule_items` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `time_label` varchar(50) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `personal_schedule_items`
--

INSERT INTO `personal_schedule_items` (`id`, `user_id`, `date`, `time_label`, `title`, `created_at`) VALUES
(1, 1, '2026-07-17', '9:30 AM', 'Tech team meeting', '2026-07-17 01:33:14');

-- --------------------------------------------------------

--
-- Table structure for table `schemes`
--

CREATE TABLE `schemes` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `schemes`
--

INSERT INTO `schemes` (`id`, `name`, `code`) VALUES
(1, 'ISO 9001', '9001'),
(2, 'ISO 14001', '14001'),
(3, 'ISO 45001', '45001'),
(4, 'ConSASS', 'CONSASS'),
(5, 'ISO 27001', '27001'),
(6, 'RM', 'RM'),
(8, 'Scafolding Audit', '12');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('super_admin','admin','auditor') NOT NULL,
  `color_hex` char(7) NOT NULL DEFAULT '#3788d8',
  `phone` varchar(30) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `username`, `password_hash`, `role`, `color_hex`, `phone`, `status`, `created_at`) VALUES
(1, 'Pandian', 'pandian@ehsuniversal.com', 'pandian', '$2b$12$w.9Tacob6cy5dsH9EqMFPOvXxWOS8yHiEt97o8NOM/RoS6oEgKwZC', 'super_admin', '#8e44ad', NULL, 'active', '2026-07-13 06:18:19'),
(2, 'System Admin', 'owner2@ehsuniversal.com', 'sysadmin', '$2b$12$w.9Tacob6cy5dsH9EqMFPOvXxWOS8yHiEt97o8NOM/RoS6oEgKwZC', 'super_admin', '#2c3e50', NULL, 'active', '2026-07-13 06:18:19'),
(3, 'Admin', 'admin@ehsuniversal.com', 'admin', '$2b$12$w.9Tacob6cy5dsH9EqMFPOvXxWOS8yHiEt97o8NOM/RoS6oEgKwZC', 'admin', '#7f8c8d', NULL, 'active', '2026-07-13 06:18:19'),
(4, 'Sitha', 'sitha@ehsuniversal.com', 'sitha', '$2b$12$w.9Tacob6cy5dsH9EqMFPOvXxWOS8yHiEt97o8NOM/RoS6oEgKwZC', 'auditor', '#e74c3c', NULL, 'active', '2026-07-13 06:18:19'),
(5, 'Raja', 'raja@ehsuniversal.com', 'raja', '$2y$10$XcSCVdkUz5AoE0t2/Pqj3OhIHk65MnxHUb8WUVySji7A5UG4ss1xO', 'auditor', '#3498db', NULL, 'active', '2026-07-13 06:18:19'),
(6, 'Eddie', 'eddie@ehsuniversal.com', 'eddie', '$2b$12$w.9Tacob6cy5dsH9EqMFPOvXxWOS8yHiEt97o8NOM/RoS6oEgKwZC', 'auditor', '#2ecc71', NULL, 'active', '2026-07-13 06:18:19'),
(7, 'Philip', 'philip@ehsuniversal.com', 'philip', '$2b$12$w.9Tacob6cy5dsH9EqMFPOvXxWOS8yHiEt97o8NOM/RoS6oEgKwZC', 'auditor', '#f39c12', NULL, 'active', '2026-07-13 06:18:19'),
(8, 'Mak', 'mak@ehsuniversal.com', 'mak', '$2b$12$w.9Tacob6cy5dsH9EqMFPOvXxWOS8yHiEt97o8NOM/RoS6oEgKwZC', 'auditor', '#1abc9c', NULL, 'inactive', '2026-07-13 06:18:19'),
(9, 'Lee Chin Eng', 'leechineng@ehsuniversal.com', 'leechineng', '$2b$12$w.9Tacob6cy5dsH9EqMFPOvXxWOS8yHiEt97o8NOM/RoS6oEgKwZC', 'auditor', '#9b59b6', NULL, 'active', '2026-07-13 06:18:19'),
(10, 'Pugal', 'pugal@ehsuniversal.com', 'pugal', '$2b$12$w.9Tacob6cy5dsH9EqMFPOvXxWOS8yHiEt97o8NOM/RoS6oEgKwZC', 'auditor', '#34495e', NULL, 'active', '2026-07-13 06:18:19'),
(11, 'Wong Teng Wah', 'wongtengwah@ehsuniversal.com', 'wongtengwah', '$2b$12$w.9Tacob6cy5dsH9EqMFPOvXxWOS8yHiEt97o8NOM/RoS6oEgKwZC', 'auditor', '#16a085', NULL, 'active', '2026-07-13 06:18:19'),
(12, 'Paul', 'paul@ehsuniversal.com', 'paul', '$2b$12$w.9Tacob6cy5dsH9EqMFPOvXxWOS8yHiEt97o8NOM/RoS6oEgKwZC', 'auditor', '#d35400', NULL, 'active', '2026-07-13 06:18:19'),
(13, 'Raghu', 'raghu@ehsuniversal.com', 'raghu', '$2b$12$w.9Tacob6cy5dsH9EqMFPOvXxWOS8yHiEt97o8NOM/RoS6oEgKwZC', 'auditor', '#c0392b', NULL, 'active', '2026-07-13 06:18:19'),
(14, 'VT Murugan', 'vtmurugan@ehsuniversal.com', 'vtmurugan', '$2b$12$w.9Tacob6cy5dsH9EqMFPOvXxWOS8yHiEt97o8NOM/RoS6oEgKwZC', 'auditor', '#27ae60', NULL, 'active', '2026-07-13 06:18:19'),
(15, 'Travis', 'travis@ehsuniversal.com', 'travis', '$2b$12$w.9Tacob6cy5dsH9EqMFPOvXxWOS8yHiEt97o8NOM/RoS6oEgKwZC', 'auditor', '#2980b9', NULL, 'active', '2026-07-13 06:18:19'),
(16, 'Kanan', 'kanan@ehsuniversal.com', 'kanan', '$2b$12$w.9Tacob6cy5dsH9EqMFPOvXxWOS8yHiEt97o8NOM/RoS6oEgKwZC', 'auditor', '#8e44ad', NULL, 'active', '2026-07-13 06:18:19'),
(17, 'Roslan', 'roslan@ehsuniversal.com', 'roslan', '$2b$12$w.9Tacob6cy5dsH9EqMFPOvXxWOS8yHiEt97o8NOM/RoS6oEgKwZC', 'auditor', '#f1c40f', NULL, 'active', '2026-07-13 06:18:19');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_log_user` (`user_id`),
  ADD KEY `idx_log_created` (`created_at`),
  ADD KEY `idx_log_entity` (`entity_type`,`entity_id`);

--
-- Indexes for table `auditor_schemes`
--
ALTER TABLE `auditor_schemes`
  ADD PRIMARY KEY (`auditor_id`,`scheme_id`),
  ADD KEY `fk_as_scheme` (`scheme_id`);

--
-- Indexes for table `audits`
--
ALTER TABLE `audits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_audits_client` (`client_id`),
  ADD KEY `fk_audits_creator` (`created_by`),
  ADD KEY `idx_audits_date` (`audit_date`),
  ADD KEY `idx_audits_status` (`status`);

--
-- Indexes for table `audit_auditors`
--
ALTER TABLE `audit_auditors`
  ADD PRIMARY KEY (`audit_id`,`auditor_id`),
  ADD KEY `idx_aa_auditor` (`auditor_id`);

--
-- Indexes for table `audit_schemes`
--
ALTER TABLE `audit_schemes`
  ADD PRIMARY KEY (`audit_id`,`scheme_id`),
  ADD KEY `fk_asch_scheme` (`scheme_id`);

--
-- Indexes for table `availability`
--
ALTER TABLE `availability`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_avail_auditor_date_session` (`auditor_id`,`date`,`session`),
  ADD KEY `idx_avail_date` (`date`);

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_clients_name` (`name`);

--
-- Indexes for table `cm_activity_log`
--
ALTER TABLE `cm_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cmlog_user` (`user_id`),
  ADD KEY `idx_cmlog_created` (`created_at`),
  ADD KEY `idx_cmlog_entity` (`entity_type`,`entity_id`);

--
-- Indexes for table `cm_certifications`
--
ALTER TABLE `cm_certifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cm_cert_number` (`certificate_number`),
  ADD KEY `fk_cmcert_resp_user` (`responsible_person_id`),
  ADD KEY `idx_cmcert_client` (`cm_client_id`),
  ADD KEY `idx_cmcert_scheme` (`cm_scheme_type_id`),
  ADD KEY `idx_cmcert_status` (`status`),
  ADD KEY `idx_cmcert_expiry` (`expiry_date`);

--
-- Indexes for table `cm_certification_documents`
--
ALTER TABLE `cm_certification_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cmdoc_uploader` (`uploaded_by`),
  ADD KEY `idx_cmdoc_cert` (`cm_certification_id`);

--
-- Indexes for table `cm_clients`
--
ALTER TABLE `cm_clients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cm_clients_uen` (`uen_registration_no`),
  ADD KEY `idx_cm_clients_name` (`company_name`),
  ADD KEY `idx_cm_clients_status` (`status`),
  ADD KEY `idx_cm_clients_industry` (`industry_sector`);

--
-- Indexes for table `cm_client_followup_notes`
--
ALTER TABLE `cm_client_followup_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_followup_notes_client` (`cm_client_id`),
  ADD KEY `idx_followup_notes_cert` (`cm_certification_id`);

--
-- Indexes for table `cm_renewal_alerts`
--
ALTER TABLE `cm_renewal_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cmalert_cert` (`cm_certification_id`),
  ADD KEY `idx_cmalert_status` (`status`);

--
-- Indexes for table `cm_scheme_types`
--
ALTER TABLE `cm_scheme_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cm_scheme_types_name` (`name`),
  ADD KEY `idx_cm_scheme_types_category` (`category`);

--
-- Indexes for table `cm_settings`
--
ALTER TABLE `cm_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `crm_activity_log`
--
ALTER TABLE `crm_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_crm_activity_entity` (`entity_type`,`entity_id`);

--
-- Indexes for table `crm_followups`
--
ALTER TABLE `crm_followups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_followups_lead` (`crm_lead_id`),
  ADD KEY `idx_followups_due` (`due_date`,`done`);

--
-- Indexes for table `crm_leads`
--
ALTER TABLE `crm_leads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_crm_leads_stage` (`stage`),
  ADD KEY `idx_crm_leads_owner` (`owner_id`),
  ADD KEY `idx_crm_leads_norm_email` (`normalized_email`),
  ADD KEY `idx_crm_leads_norm_phone` (`normalized_phone`),
  ADD KEY `idx_crm_leads_norm_company` (`normalized_company`);

--
-- Indexes for table `crm_lead_stage_history`
--
ALTER TABLE `crm_lead_stage_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_stage_history_lead` (`crm_lead_id`);

--
-- Indexes for table `crm_quotations`
--
ALTER TABLE `crm_quotations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_lead_version` (`crm_lead_id`,`version`),
  ADD KEY `idx_quotations_lead` (`crm_lead_id`);

--
-- Indexes for table `crm_quotation_items`
--
ALTER TABLE `crm_quotation_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_items_quotation` (`crm_quotation_id`);

--
-- Indexes for table `holidays`
--
ALTER TABLE `holidays`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_holidays_date` (`date`),
  ADD KEY `idx_holidays_date` (`date`);

--
-- Indexes for table `personal_schedule_items`
--
ALTER TABLE `personal_schedule_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_psi_user_date` (`user_id`,`date`);

--
-- Indexes for table `schemes`
--
ALTER TABLE `schemes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_schemes_name` (`name`),
  ADD UNIQUE KEY `uq_schemes_code` (`code`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD UNIQUE KEY `uq_users_username` (`username`),
  ADD KEY `idx_users_role` (`role`),
  ADD KEY `idx_users_status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=123;

--
-- AUTO_INCREMENT for table `audits`
--
ALTER TABLE `audits`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `availability`
--
ALTER TABLE `availability`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `cm_activity_log`
--
ALTER TABLE `cm_activity_log`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=621;

--
-- AUTO_INCREMENT for table `cm_certifications`
--
ALTER TABLE `cm_certifications`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=333;

--
-- AUTO_INCREMENT for table `cm_certification_documents`
--
ALTER TABLE `cm_certification_documents`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cm_clients`
--
ALTER TABLE `cm_clients`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=285;

--
-- AUTO_INCREMENT for table `cm_client_followup_notes`
--
ALTER TABLE `cm_client_followup_notes`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `cm_renewal_alerts`
--
ALTER TABLE `cm_renewal_alerts`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cm_scheme_types`
--
ALTER TABLE `cm_scheme_types`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `crm_activity_log`
--
ALTER TABLE `crm_activity_log`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `crm_followups`
--
ALTER TABLE `crm_followups`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crm_leads`
--
ALTER TABLE `crm_leads`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `crm_lead_stage_history`
--
ALTER TABLE `crm_lead_stage_history`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `crm_quotations`
--
ALTER TABLE `crm_quotations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crm_quotation_items`
--
ALTER TABLE `crm_quotation_items`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `holidays`
--
ALTER TABLE `holidays`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `personal_schedule_items`
--
ALTER TABLE `personal_schedule_items`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `schemes`
--
ALTER TABLE `schemes`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `auditor_schemes`
--
ALTER TABLE `auditor_schemes`
  ADD CONSTRAINT `fk_as_auditor` FOREIGN KEY (`auditor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_as_scheme` FOREIGN KEY (`scheme_id`) REFERENCES `schemes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `audits`
--
ALTER TABLE `audits`
  ADD CONSTRAINT `fk_audits_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  ADD CONSTRAINT `fk_audits_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `audit_auditors`
--
ALTER TABLE `audit_auditors`
  ADD CONSTRAINT `fk_aa_audit` FOREIGN KEY (`audit_id`) REFERENCES `audits` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_aa_auditor` FOREIGN KEY (`auditor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `audit_schemes`
--
ALTER TABLE `audit_schemes`
  ADD CONSTRAINT `fk_asch_audit` FOREIGN KEY (`audit_id`) REFERENCES `audits` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_asch_scheme` FOREIGN KEY (`scheme_id`) REFERENCES `schemes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `availability`
--
ALTER TABLE `availability`
  ADD CONSTRAINT `fk_avail_auditor` FOREIGN KEY (`auditor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cm_activity_log`
--
ALTER TABLE `cm_activity_log`
  ADD CONSTRAINT `fk_cmlog_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `cm_certifications`
--
ALTER TABLE `cm_certifications`
  ADD CONSTRAINT `fk_cmcert_client` FOREIGN KEY (`cm_client_id`) REFERENCES `cm_clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cmcert_resp_user` FOREIGN KEY (`responsible_person_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_cmcert_scheme_type` FOREIGN KEY (`cm_scheme_type_id`) REFERENCES `cm_scheme_types` (`id`);

--
-- Constraints for table `cm_certification_documents`
--
ALTER TABLE `cm_certification_documents`
  ADD CONSTRAINT `fk_cmdoc_cert` FOREIGN KEY (`cm_certification_id`) REFERENCES `cm_certifications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cmdoc_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `cm_client_followup_notes`
--
ALTER TABLE `cm_client_followup_notes`
  ADD CONSTRAINT `fk_followup_notes_cert` FOREIGN KEY (`cm_certification_id`) REFERENCES `cm_certifications` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_followup_notes_client` FOREIGN KEY (`cm_client_id`) REFERENCES `cm_clients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cm_renewal_alerts`
--
ALTER TABLE `cm_renewal_alerts`
  ADD CONSTRAINT `fk_cmalert_cert` FOREIGN KEY (`cm_certification_id`) REFERENCES `cm_certifications` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `crm_followups`
--
ALTER TABLE `crm_followups`
  ADD CONSTRAINT `fk_followups_lead` FOREIGN KEY (`crm_lead_id`) REFERENCES `crm_leads` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `crm_lead_stage_history`
--
ALTER TABLE `crm_lead_stage_history`
  ADD CONSTRAINT `fk_stage_history_lead` FOREIGN KEY (`crm_lead_id`) REFERENCES `crm_leads` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `crm_quotations`
--
ALTER TABLE `crm_quotations`
  ADD CONSTRAINT `fk_quotations_lead` FOREIGN KEY (`crm_lead_id`) REFERENCES `crm_leads` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `crm_quotation_items`
--
ALTER TABLE `crm_quotation_items`
  ADD CONSTRAINT `fk_items_quotation` FOREIGN KEY (`crm_quotation_id`) REFERENCES `crm_quotations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `personal_schedule_items`
--
ALTER TABLE `personal_schedule_items`
  ADD CONSTRAINT `fk_psi_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
