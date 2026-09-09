-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 09, 2026 at 08:53 PM
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
-- Database: `dispensary_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `branch_id`, `patient_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`, `updated_at`) VALUES
(1, 13, 1, NULL, 'user_login', 'User logged in: Lab Technician Dodoma (Role: laboratory)', NULL, NULL, '2026-08-23 22:46:16', '2026-08-23 22:46:16'),
(2, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 1', NULL, NULL, '2026-08-23 22:48:52', '2026-08-23 22:48:52'),
(3, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 2', NULL, NULL, '2026-08-23 22:49:01', '2026-08-23 22:49:01'),
(4, 4, 1, NULL, 'user_login', 'User logged in: Dr. ERICK (Role: doctor)', NULL, NULL, '2026-08-24 08:14:51', '2026-08-24 08:14:51'),
(5, 7, 1, NULL, 'user_login', 'User logged in: GRACE MUSSA (Role: pharmacy)', NULL, NULL, '2026-08-24 10:48:17', '2026-08-24 10:48:17'),
(6, 4, 1, NULL, 'document_downloaded', 'Downloaded document #1: sick_sheet_SS-20260824-5402.html | Patient: JACKSON MYULA | Uploaded by: Dr. ERICK', NULL, NULL, '2026-08-24 11:39:59', '2026-08-24 11:39:59'),
(7, 10, 1, NULL, 'user_login', 'User logged in: Reception SALOME (Role: reception)', NULL, NULL, '2026-08-24 11:49:56', '2026-08-24 11:49:56'),
(8, 10, 1, NULL, 'user_logout', 'User logged out: Reception SALOME (Role: reception)', NULL, NULL, '2026-08-24 13:01:59', '2026-08-24 13:01:59'),
(9, 10, 1, NULL, 'user_login', 'User logged in: Reception SALOME (Role: reception)', NULL, NULL, '2026-08-24 13:02:11', '2026-08-24 13:02:11'),
(10, 10, 1, NULL, 'user_logout', 'User logged out: Reception SALOME (Role: reception)', NULL, NULL, '2026-08-24 13:09:35', '2026-08-24 13:09:35'),
(11, 7, 1, NULL, 'user_login', 'User logged in: GRACE MUSSA (Role: pharmacy)', NULL, NULL, '2026-08-24 13:09:37', '2026-08-24 13:09:37'),
(12, 4, 1, NULL, 'user_login', 'User logged in: Dr. ERICK (Role: doctor)', NULL, NULL, '2026-08-24 20:14:06', '2026-08-24 20:14:06'),
(13, 13, 1, NULL, 'user_login', 'User logged in: Lab Technician Dodoma (Role: laboratory)', NULL, NULL, '2026-08-24 21:51:39', '2026-08-24 21:51:39'),
(14, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 3', NULL, NULL, '2026-08-24 21:51:53', '2026-08-24 21:51:53'),
(15, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 4', NULL, NULL, '2026-08-24 21:51:59', '2026-08-24 21:51:59'),
(16, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 8', NULL, NULL, '2026-08-24 21:52:03', '2026-08-24 21:52:03'),
(17, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 9', NULL, NULL, '2026-08-24 21:52:08', '2026-08-24 21:52:08'),
(18, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 10', NULL, NULL, '2026-08-24 21:52:12', '2026-08-24 21:52:12'),
(19, 13, 1, NULL, 'user_logout', 'User logged out: Lab Technician Dodoma (Role: laboratory)', NULL, NULL, '2026-08-24 22:10:13', '2026-08-24 22:10:13'),
(20, 10, 1, NULL, 'user_login', 'User logged in: Reception SALOME (Role: reception)', NULL, NULL, '2026-08-24 22:10:36', '2026-08-24 22:10:36'),
(21, 10, 1, NULL, 'user_logout', 'User logged out: Reception SALOME (Role: reception)', NULL, NULL, '2026-08-24 23:16:37', '2026-08-24 23:16:37'),
(22, 13, 1, NULL, 'user_login', 'User logged in: Lab Technician Dodoma (Role: laboratory)', NULL, NULL, '2026-08-24 23:16:47', '2026-08-24 23:16:47'),
(23, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 11', NULL, NULL, '2026-08-24 23:17:00', '2026-08-24 23:17:00'),
(24, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 12', NULL, NULL, '2026-08-24 23:17:05', '2026-08-24 23:17:05'),
(25, 13, 1, NULL, 'user_logout', 'User logged out: Lab Technician Dodoma (Role: laboratory)', NULL, NULL, '2026-08-24 23:32:16', '2026-08-24 23:32:16'),
(26, 10, 1, NULL, 'user_login', 'User logged in: Reception SALOME (Role: reception)', NULL, NULL, '2026-08-24 23:32:22', '2026-08-24 23:32:22'),
(27, 10, 1, NULL, 'user_logout', 'User logged out: Reception SALOME (Role: reception)', NULL, NULL, '2026-08-24 23:39:39', '2026-08-24 23:39:39'),
(28, 13, 1, NULL, 'user_login', 'User logged in: Lab Technician Dodoma (Role: laboratory)', NULL, NULL, '2026-08-24 23:39:44', '2026-08-24 23:39:44'),
(29, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 13', NULL, NULL, '2026-08-24 23:39:52', '2026-08-24 23:39:52'),
(30, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 14', NULL, NULL, '2026-08-24 23:40:28', '2026-08-24 23:40:28'),
(31, 4, 1, NULL, 'user_login', 'User logged in: Dr. ERICK (Role: doctor)', NULL, NULL, '2026-08-25 08:07:50', '2026-08-25 08:07:50'),
(32, 10, 1, NULL, 'user_login', 'User logged in: Reception SALOME (Role: reception)', NULL, NULL, '2026-08-25 08:09:20', '2026-08-25 08:09:20'),
(33, 10, 1, NULL, 'user_logout', 'User logged out: Reception SALOME (Role: reception)', NULL, NULL, '2026-08-25 08:18:42', '2026-08-25 08:18:42'),
(34, 13, 1, NULL, 'user_login', 'User logged in: Lab Technician Dodoma (Role: laboratory)', NULL, NULL, '2026-08-25 08:18:49', '2026-08-25 08:18:49'),
(35, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 15', NULL, NULL, '2026-08-25 08:18:58', '2026-08-25 08:18:58'),
(36, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 16', NULL, NULL, '2026-08-25 08:19:02', '2026-08-25 08:19:02'),
(37, 7, 1, NULL, 'user_login', 'User logged in: GRACE MUSSA (Role: pharmacy)', NULL, NULL, '2026-08-25 08:30:19', '2026-08-25 08:30:19'),
(38, 13, 1, NULL, 'user_logout', 'User logged out: Lab Technician Dodoma (Role: laboratory)', NULL, NULL, '2026-08-25 08:40:41', '2026-08-25 08:40:41'),
(39, 13, 1, NULL, 'user_login', 'User logged in: Lab Technician Dodoma (Role: laboratory)', NULL, NULL, '2026-08-25 08:40:49', '2026-08-25 08:40:49'),
(40, 13, 1, NULL, 'user_logout', 'User logged out: Lab Technician Dodoma (Role: laboratory)', NULL, NULL, '2026-08-25 08:40:57', '2026-08-25 08:40:57'),
(41, 10, 1, NULL, 'user_login', 'User logged in: Reception SALOME (Role: reception)', NULL, NULL, '2026-08-25 08:41:01', '2026-08-25 08:41:01'),
(42, 10, 1, NULL, 'user_login', 'User logged in: Reception SALOME (Role: reception)', NULL, NULL, '2026-08-25 09:50:44', '2026-08-25 09:50:44'),
(43, 13, 1, NULL, 'user_login', 'User logged in: Lab Technician Dodoma (Role: laboratory)', NULL, NULL, '2026-08-25 09:56:18', '2026-08-25 09:56:18'),
(44, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 17', NULL, NULL, '2026-08-25 09:56:27', '2026-08-25 09:56:27'),
(45, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 18', NULL, NULL, '2026-08-25 09:56:30', '2026-08-25 09:56:30'),
(46, 4, 1, NULL, 'user_login', 'User logged in: Dr. ERICK (Role: doctor)', NULL, NULL, '2026-08-25 14:05:55', '2026-08-25 14:05:55'),
(47, 13, 1, NULL, 'user_login', 'User logged in: Lab Technician Dodoma (Role: laboratory)', NULL, NULL, '2026-08-25 14:13:53', '2026-08-25 14:13:53'),
(48, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 19', NULL, NULL, '2026-08-25 14:22:48', '2026-08-25 14:22:48'),
(49, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 20', NULL, NULL, '2026-08-25 14:22:51', '2026-08-25 14:22:51'),
(50, 13, 1, NULL, 'user_login', 'User logged in: Lab Technician Dodoma (Role: laboratory)', NULL, NULL, '2026-08-25 16:07:50', '2026-08-25 16:07:50'),
(51, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 21', NULL, NULL, '2026-08-25 16:07:59', '2026-08-25 16:07:59'),
(52, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 22', NULL, NULL, '2026-08-25 16:08:02', '2026-08-25 16:08:02'),
(53, 4, 1, NULL, 'user_login', 'User logged in: Dr. ERICK (Role: doctor)', NULL, NULL, '2026-08-25 20:15:06', '2026-08-25 20:15:06'),
(54, 10, 1, NULL, 'user_login', 'User logged in: Reception SALOME (Role: reception)', NULL, NULL, '2026-08-25 20:15:21', '2026-08-25 20:15:21'),
(55, 13, 1, NULL, 'user_login', 'User logged in: Lab Technician Dodoma (Role: laboratory)', NULL, NULL, '2026-08-25 20:15:37', '2026-08-25 20:15:37'),
(56, 13, 1, NULL, 'user_logout', 'User logged out: Lab Technician Dodoma (Role: laboratory)', NULL, NULL, '2026-08-25 20:27:49', '2026-08-25 20:27:49'),
(57, 13, 1, NULL, 'user_login', 'User logged in: Lab Technician Dodoma (Role: laboratory)', NULL, NULL, '2026-08-25 20:28:18', '2026-08-25 20:28:18'),
(58, 4, NULL, NULL, 'doctor_status_changed', 'Dr. Dr. ERICK changed status to: offline', NULL, NULL, '2026-08-25 20:28:33', '2026-08-25 20:28:33'),
(59, 4, NULL, NULL, 'doctor_status_changed', 'Dr. Dr. ERICK changed status to: online', NULL, NULL, '2026-08-25 20:28:34', '2026-08-25 20:28:34'),
(60, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 23', NULL, NULL, '2026-08-25 20:29:56', '2026-08-25 20:29:56'),
(61, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 24', NULL, NULL, '2026-08-25 20:30:00', '2026-08-25 20:30:00'),
(62, 10, 1, NULL, 'user_logout', 'User logged out: Reception SALOME (Role: reception)', NULL, NULL, '2026-08-25 21:49:14', '2026-08-25 21:49:14'),
(63, 13, 1, NULL, 'user_login', 'User logged in: Lab Technician Dodoma (Role: laboratory)', NULL, NULL, '2026-08-25 21:49:18', '2026-08-25 21:49:18'),
(64, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 30', NULL, NULL, '2026-08-25 21:49:25', '2026-08-25 21:49:25'),
(65, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 31', NULL, NULL, '2026-08-25 21:49:30', '2026-08-25 21:49:30'),
(66, 13, 1, NULL, 'user_logout', 'User logged out: Lab Technician Dodoma (Role: laboratory)', NULL, NULL, '2026-08-25 21:58:40', '2026-08-25 21:58:40'),
(67, 10, 1, NULL, 'user_login', 'User logged in: Reception SALOME (Role: reception)', NULL, NULL, '2026-08-25 21:58:44', '2026-08-25 21:58:44'),
(68, 10, 1, NULL, 'user_logout', 'User logged out: Reception SALOME (Role: reception)', NULL, NULL, '2026-08-25 22:03:48', '2026-08-25 22:03:48'),
(69, 13, 1, NULL, 'user_login', 'User logged in: Lab Technician Dodoma (Role: laboratory)', NULL, NULL, '2026-08-25 22:03:52', '2026-08-25 22:03:52'),
(70, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 32', NULL, NULL, '2026-08-25 22:03:57', '2026-08-25 22:03:57'),
(71, 4, 1, NULL, 'profile_picture_updated', 'Profile picture updated for: Dr. ERICK', NULL, NULL, '2026-08-25 22:45:56', '2026-08-25 22:45:56'),
(72, 13, 1, NULL, 'user_login', 'User logged in: Lab Technician Dodoma (Role: laboratory)', NULL, NULL, '2026-08-25 23:46:32', '2026-08-25 23:46:32'),
(73, 13, 1, NULL, 'user_logout', 'User logged out: Lab Technician Dodoma (Role: laboratory)', NULL, NULL, '2026-08-25 23:47:48', '2026-08-25 23:47:48'),
(74, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-08-25 23:47:50', '2026-08-25 23:47:50'),
(75, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-08-25 23:48:39', '2026-08-25 23:48:39'),
(76, 14, 1, NULL, 'user_login', 'User logged in: Peter Lema (Role: laboratory)', NULL, NULL, '2026-08-25 23:48:46', '2026-08-25 23:48:46'),
(77, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-08-26 07:02:48', '2026-08-26 07:02:48'),
(78, 10, 1, NULL, 'user_login', 'User logged in: Reception SALOME (Role: reception)', NULL, NULL, '2026-08-26 08:36:17', '2026-08-26 08:36:17'),
(79, 14, 1, NULL, 'user_login', 'User logged in: Peter Lema (Role: laboratory)', NULL, NULL, '2026-08-26 08:50:32', '2026-08-26 08:50:32'),
(80, 14, 1, NULL, 'lab_test_started', 'Started lab test ID: 33', NULL, NULL, '2026-08-26 08:50:42', '2026-08-26 08:50:42'),
(81, 14, 1, NULL, 'lab_test_started', 'Started lab test ID: 34', NULL, NULL, '2026-08-26 08:50:56', '2026-08-26 08:50:56'),
(82, 14, 1, NULL, 'lab_test_started', 'Started lab test ID: 35', NULL, NULL, '2026-08-26 09:29:51', '2026-08-26 09:29:51'),
(83, 14, 1, NULL, 'lab_test_started', 'Started lab test ID: 36', NULL, NULL, '2026-08-26 09:30:06', '2026-08-26 09:30:06'),
(84, 4, 1, 48, 'referral_created', 'Patient referred: IBRAHIM DOUMBIA (#REF-20260826-0048-492) - Type: external', NULL, NULL, '2026-08-26 09:51:45', '2026-08-26 09:51:45'),
(85, 14, 1, NULL, 'lab_test_started', 'Started lab test ID: 37', NULL, NULL, '2026-08-26 09:56:39', '2026-08-26 09:56:39'),
(86, 14, 1, NULL, 'lab_test_started', 'Started lab test ID: 38', NULL, NULL, '2026-08-26 09:56:43', '2026-08-26 09:56:43'),
(87, 14, 1, NULL, 'lab_test_started', 'Started lab test ID: 39', NULL, NULL, '2026-08-26 10:10:38', '2026-08-26 10:10:38'),
(88, 14, 1, NULL, 'lab_test_started', 'Started lab test ID: 40', NULL, NULL, '2026-08-26 10:10:41', '2026-08-26 10:10:41'),
(89, 14, 1, NULL, 'lab_test_started', 'Started lab test ID: 41', NULL, NULL, '2026-08-26 11:06:13', '2026-08-26 11:06:13'),
(90, 10, 1, NULL, 'user_login', 'User logged in: Reception SALOME (Role: reception)', NULL, NULL, '2026-08-26 12:22:37', '2026-08-26 12:22:37'),
(91, 14, 1, NULL, 'lab_test_started', 'Started lab test ID: 42', NULL, NULL, '2026-08-26 12:41:50', '2026-08-26 12:41:50'),
(92, 14, 1, NULL, 'lab_test_started', 'Started lab test ID: 43', NULL, NULL, '2026-08-26 12:48:42', '2026-08-26 12:48:42'),
(93, 14, 1, NULL, 'lab_test_started', 'Started lab test ID: 44', NULL, NULL, '2026-08-26 13:22:02', '2026-08-26 13:22:02'),
(94, 14, 1, NULL, 'lab_test_started', 'Started lab test ID: 45', NULL, NULL, '2026-08-26 13:43:39', '2026-08-26 13:43:39'),
(95, 14, 1, NULL, 'lab_test_started', 'Started lab test ID: 46', NULL, NULL, '2026-08-26 13:43:42', '2026-08-26 13:43:42'),
(96, 14, 1, NULL, 'lab_test_started', 'Started lab test ID: 47', NULL, NULL, '2026-08-26 13:45:26', '2026-08-26 13:45:26'),
(97, 14, 1, NULL, 'user_logout', 'User logged out: Peter Lema (Role: laboratory)', NULL, NULL, '2026-08-26 13:51:03', '2026-08-26 13:51:03'),
(98, 9, 1, NULL, 'user_login', 'User logged in: James Mwangi (Role: pharmacy)', NULL, NULL, '2026-08-26 13:51:11', '2026-08-26 13:51:11'),
(99, 9, 1, NULL, 'user_logout', 'User logged out: James Mwangi (Role: pharmacy)', NULL, NULL, '2026-08-26 13:55:58', '2026-08-26 13:55:58'),
(100, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-08-26 13:56:05', '2026-08-26 13:56:05'),
(101, 10, 1, NULL, 'user_logout', 'User logged out: Reception SALOME (Role: reception)', NULL, NULL, '2026-08-26 14:10:30', '2026-08-26 14:10:30'),
(102, 3, 1, NULL, 'user_login', 'User logged in: BRAICK (Role: admin)', NULL, NULL, '2026-08-26 14:10:40', '2026-08-26 14:10:40'),
(103, 3, 1, NULL, 'lab_test_started', 'Started lab test ID: 49', NULL, NULL, '2026-08-26 14:15:40', '2026-08-26 14:15:40'),
(104, 3, 1, NULL, 'lab_test_started', 'Started lab test ID: 50', NULL, NULL, '2026-08-26 14:15:45', '2026-08-26 14:15:45'),
(105, 3, 1, NULL, 'lab_test_started', 'Started lab test ID: 51', NULL, NULL, '2026-08-26 14:35:15', '2026-08-26 14:35:15'),
(106, 3, 1, NULL, 'lab_test_started', 'Started lab test ID: 52', NULL, NULL, '2026-08-26 14:35:21', '2026-08-26 14:35:21'),
(107, 3, 1, NULL, 'lab_test_started', 'Started lab test ID: 53', NULL, NULL, '2026-08-26 14:56:55', '2026-08-26 14:56:55'),
(108, 3, 1, NULL, 'lab_test_started', 'Started lab test ID: 54', NULL, NULL, '2026-08-26 14:57:07', '2026-08-26 14:57:07'),
(109, 3, 1, NULL, 'user_logout', 'User logged out: BRAICK (Role: admin)', NULL, NULL, '2026-08-26 15:13:39', '2026-08-26 15:13:39'),
(110, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Role: reception)', NULL, NULL, '2026-08-26 15:13:58', '2026-08-26 15:13:58'),
(111, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 55', NULL, NULL, '2026-08-26 15:19:02', '2026-08-26 15:19:02'),
(112, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 56', NULL, NULL, '2026-08-26 15:22:30', '2026-08-26 15:22:30'),
(113, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 58', NULL, NULL, '2026-08-26 15:43:02', '2026-08-26 15:43:02'),
(114, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 57', NULL, NULL, '2026-08-26 15:43:06', '2026-08-26 15:43:06'),
(115, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 59', NULL, NULL, '2026-08-26 15:56:56', '2026-08-26 15:56:56'),
(116, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 60', NULL, NULL, '2026-08-26 15:56:59', '2026-08-26 15:56:59'),
(117, 4, 1, 49, 'referral_created', 'Patient referred: AGUSTINO VALENTINE (#REF-20260826-0049-548) - Type: external', NULL, NULL, '2026-08-26 16:05:07', '2026-08-26 16:05:07'),
(118, 4, 1, 50, 'referral_created', 'Patient referred: KELVIN MSAFIRI (#REF-20260826-0050-492) - Type: internal - Status: referred', NULL, NULL, '2026-08-26 16:34:19', '2026-08-26 16:34:19'),
(119, 4, 1, 49, 'referral_created', 'Patient referred: AGUSTINO VALENTINE (#REF-20260826-0049-370) - Type: internal - Status: referred', NULL, NULL, '2026-08-26 16:52:15', '2026-08-26 16:52:15'),
(120, 4, 1, 49, 'referral_created', 'Patient referred: AGUSTINO VALENTINE (#REF-20260826-0049-554) - Type: internal - Status: referred', NULL, NULL, '2026-08-26 16:58:22', '2026-08-26 16:58:22'),
(121, 4, 1, 49, 'referral_created', 'Patient referred: AGUSTINO VALENTINE (#REF-20260826-0049-441) - Type: internal - Status: referred', NULL, NULL, '2026-08-26 16:59:36', '2026-08-26 16:59:36'),
(122, 4, 1, 49, 'referral_created', 'Patient referred: AGUSTINO VALENTINE (#REF-20260826-0049-382) - Type: internal - Status: referred', NULL, NULL, '2026-08-26 17:01:35', '2026-08-26 17:01:35'),
(123, 4, 1, 49, 'referral_created', 'Patient referred: AGUSTINO VALENTINE (#REF-20260826-0049-982) - Type: internal - Status: NULL', NULL, NULL, '2026-08-26 17:06:21', '2026-08-26 17:06:21'),
(124, 4, 1, 49, 'referral_created', 'Patient referred: AGUSTINO VALENTINE (#REF-20260826-0049-890) - Type: internal - Status: referred', NULL, NULL, '2026-08-26 19:06:50', '2026-08-26 19:06:50'),
(125, 4, 1, 51, 'referral_created', 'Patient referred: CLEOFAS WILLIUM (#REF-20260826-0051-239) - Type: internal - Status: referred', NULL, NULL, '2026-08-26 19:06:50', '2026-08-26 19:06:50'),
(126, 4, 1, 49, 'referral_created', 'Patient referred: AGUSTINO VALENTINE (#REF-20260826-0049-476) - Type: external - Status: referred', NULL, NULL, '2026-08-26 19:31:55', '2026-08-26 19:31:55'),
(127, 4, 1, 48, 'referral_created', 'Patient referred: IBRAHIM DOUMBIA (#REF-20260826-0048-666) - Type: internal - Status: referred', NULL, NULL, '2026-08-26 19:32:56', '2026-08-26 19:32:56'),
(128, 4, 1, 49, 'referral_created', 'Patient referred: AGUSTINO VALENTINE (#REF-20260826-0049-175) - Type: internal - Status: referred', NULL, NULL, '2026-08-26 19:32:56', '2026-08-26 19:32:56'),
(129, 4, 1, 51, 'referral_created', 'Patient referred: CLEOFAS WILLIUM (#REF-20260826-0051-173) - Type: internal - Status: referred', NULL, NULL, '2026-08-26 19:32:56', '2026-08-26 19:32:56'),
(130, 4, 1, 52, 'referral_created', 'Patient referred: JUDITH SOLOMONI (#REF-20260826-0052-268) - Type: internal - Status: referred', NULL, NULL, '2026-08-26 19:32:56', '2026-08-26 19:32:56'),
(131, 4, 1, 53, 'referral_created', 'Patient referred: MAGRETH CHAKUPEWA (#REF-20260826-0053-973) - Type: internal - Status: referred', NULL, NULL, '2026-08-26 19:32:56', '2026-08-26 19:32:56'),
(132, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-08-26 20:45:09', '2026-08-26 20:45:09'),
(133, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 61', NULL, NULL, '2026-08-26 20:45:29', '2026-08-26 20:45:29'),
(134, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-08-26 21:40:42', '2026-08-26 21:40:42'),
(135, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 62', NULL, NULL, '2026-08-26 21:40:49', '2026-08-26 21:40:49'),
(136, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 63', NULL, NULL, '2026-08-26 21:40:52', '2026-08-26 21:40:52'),
(137, 11, 1, NULL, 'visit_status_updated', 'Visit ID: 59 status changed to completed', NULL, NULL, '2026-08-26 21:43:36', '2026-08-26 21:43:36'),
(138, 11, 1, NULL, 'visit_status_updated', 'Visit ID: 59 status changed to completed', NULL, NULL, '2026-08-26 21:43:40', '2026-08-26 21:43:40'),
(139, 11, 1, NULL, 'visit_status_updated', 'Visit ID: 59 status changed to completed', NULL, NULL, '2026-08-26 21:43:42', '2026-08-26 21:43:42'),
(140, 11, 1, NULL, 'visit_status_updated', 'Visit ID: 59 status changed to completed', NULL, NULL, '2026-08-26 21:43:45', '2026-08-26 21:43:45'),
(141, 11, 1, NULL, 'visit_status_updated', 'Visit ID: 60 status changed to completed', NULL, NULL, '2026-08-26 22:40:30', '2026-08-26 22:40:30'),
(142, 11, 1, NULL, 'visit_status_updated', 'Visit ID: 60 status changed to completed', NULL, NULL, '2026-08-26 22:40:34', '2026-08-26 22:40:34'),
(143, 11, 1, NULL, 'visit_status_updated', 'Visit ID: 60 status changed to completed', NULL, NULL, '2026-08-26 22:40:35', '2026-08-26 22:40:35'),
(144, 11, 1, NULL, 'visit_status_updated', 'Visit ID: 60 status changed to completed', NULL, NULL, '2026-08-26 22:40:38', '2026-08-26 22:40:38'),
(145, 11, 1, NULL, 'visit_status_updated', 'Visit ID: 60 status changed to completed', NULL, NULL, '2026-08-26 22:40:41', '2026-08-26 22:40:41'),
(146, 11, 1, NULL, 'visit_status_updated', 'Visit ID: 60 status changed to completed', NULL, NULL, '2026-08-26 22:40:45', '2026-08-26 22:40:45'),
(147, 11, 1, NULL, 'visit_status_updated', 'Visit ID: 60 status changed to completed', NULL, NULL, '2026-08-26 22:40:47', '2026-08-26 22:40:47'),
(148, 11, 1, NULL, 'visit_status_updated', 'Visit ID: 60 status changed to completed', NULL, NULL, '2026-08-26 22:40:51', '2026-08-26 22:40:51'),
(149, 11, 1, NULL, 'visit_status_updated', 'Visit ID: 60 status changed to completed', NULL, NULL, '2026-08-26 22:40:54', '2026-08-26 22:40:54'),
(150, 11, 1, NULL, 'visit_status_updated', 'Visit ID: 60 status changed to completed', NULL, NULL, '2026-08-26 22:42:09', '2026-08-26 22:42:09'),
(151, 5, 1, NULL, 'user_login', 'User logged in: Dr. Grace Peter (Role: doctor)', NULL, NULL, '2026-08-27 20:25:14', '2026-08-27 20:25:14'),
(152, 5, 1, NULL, 'user_logout', 'User logged out: Dr. Grace Peter (Role: doctor)', NULL, NULL, '2026-08-27 20:25:26', '2026-08-27 20:25:26'),
(153, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-08-27 20:25:31', '2026-08-27 20:25:31'),
(154, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-08-27 20:35:50', '2026-08-27 20:35:50'),
(155, 9, 1, NULL, 'user_login', 'User logged in: James Mwangi (Role: pharmacy)', NULL, NULL, '2026-08-27 20:36:10', '2026-08-27 20:36:10'),
(156, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 64', NULL, NULL, '2026-08-27 21:08:21', '2026-08-27 21:08:21'),
(157, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-08-27 21:11:28', '2026-08-27 21:11:28'),
(158, 10, 1, NULL, 'user_login', 'User logged in: Reception SALOME (Role: reception)', NULL, NULL, '2026-08-27 21:11:42', '2026-08-27 21:11:42'),
(159, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-08-28 09:21:04', '2026-08-28 09:21:04'),
(160, 10, 1, NULL, 'user_login', 'User logged in: Reception SALOME (Role: reception)', NULL, NULL, '2026-08-28 11:30:23', '2026-08-28 11:30:23'),
(161, 5, 1, NULL, 'user_login', 'User logged in: Dr. Grace Peter (Role: doctor)', NULL, NULL, '2026-08-28 11:47:17', '2026-08-28 11:47:17'),
(162, 4, 1, 60, 'referral_created', 'Patient referred: ANDREW VICENT CHIKUPE (#REF-20260828-0060-107) - Type: internal - Status: referred', NULL, NULL, '2026-08-28 11:48:16', '2026-08-28 11:48:16'),
(163, 4, 1, 61, 'referral_created', 'Patient referred internally: MUSSA MONGI MASNGI (#REF-20260828-0061-132) - Assigned to Dr. Dr. Grace Peter (Status: referred)', NULL, NULL, '2026-08-28 12:25:58', '2026-08-28 12:25:58'),
(164, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-08-28 22:24:36', '2026-08-28 22:24:36'),
(165, 10, 1, NULL, 'user_login', 'User logged in: Reception SALOME (Role: reception)', NULL, NULL, '2026-08-28 22:25:02', '2026-08-28 22:25:02'),
(166, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-08-28 22:30:54', '2026-08-28 22:30:54'),
(167, 4, 1, 61, 'referral_created', 'Patient referred internally: MUSSA MONGI MASNGI (#REF-20260829-0061-477) - From Dr. Dr.ERICK JOHN to Dr. Dr. Grace Peter (Status: assigned, doctor_id changed to receiver)', NULL, NULL, '2026-08-28 22:33:07', '2026-08-28 22:33:07'),
(168, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-08-28 22:33:26', '2026-08-28 22:33:26'),
(169, 5, 1, NULL, 'user_login', 'User logged in: Dr. Grace Peter (Role: doctor)', NULL, NULL, '2026-08-28 22:33:31', '2026-08-28 22:33:31'),
(170, 10, 1, NULL, 'user_logout', 'User logged out: Reception SALOME (Role: reception)', NULL, NULL, '2026-08-28 22:36:12', '2026-08-28 22:36:12'),
(171, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-08-28 22:36:29', '2026-08-28 22:36:29'),
(172, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-08-28 22:36:35', '2026-08-28 22:36:35'),
(173, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-08-28 22:36:47', '2026-08-28 22:36:47'),
(174, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 65', NULL, NULL, '2026-08-28 22:36:54', '2026-08-28 22:36:54'),
(175, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 66', NULL, NULL, '2026-08-28 22:36:58', '2026-08-28 22:36:58'),
(176, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-08-28 22:37:18', '2026-08-28 22:37:18'),
(177, 15, 1, NULL, 'user_login', 'User logged in: Sarah Mwamba (Role: laboratory)', NULL, NULL, '2026-08-28 22:37:29', '2026-08-28 22:37:29'),
(178, 15, 1, NULL, 'user_logout', 'User logged out: Sarah Mwamba (Role: laboratory)', NULL, NULL, '2026-08-28 22:39:39', '2026-08-28 22:39:39'),
(179, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-08-28 22:39:55', '2026-08-28 22:39:55'),
(180, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-08-28 22:45:18', '2026-08-28 22:45:18'),
(181, 10, 1, NULL, 'user_login', 'User logged in: Reception SALOME (Role: reception)', NULL, NULL, '2026-08-28 22:45:28', '2026-08-28 22:45:28'),
(182, 5, 1, NULL, 'user_logout', 'User logged out: Dr. Grace Peter (Role: doctor)', NULL, NULL, '2026-08-28 22:56:10', '2026-08-28 22:56:10'),
(183, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-08-28 22:56:17', '2026-08-28 22:56:17'),
(184, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-08-28 22:56:32', '2026-08-28 22:56:32'),
(185, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-08-28 22:56:36', '2026-08-28 22:56:36'),
(186, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 67', NULL, NULL, '2026-08-28 22:56:41', '2026-08-28 22:56:41'),
(187, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-08-28 22:56:51', '2026-08-28 22:56:51'),
(188, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-08-28 22:56:57', '2026-08-28 22:56:57'),
(189, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-08-28 22:58:35', '2026-08-28 22:58:35'),
(190, 10, 1, NULL, 'user_login', 'User logged in: Reception SALOME (Role: reception)', NULL, NULL, '2026-08-28 22:58:52', '2026-08-28 22:58:52'),
(191, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-08-28 23:04:04', '2026-08-28 23:04:04'),
(192, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-08-28 23:04:10', '2026-08-28 23:04:10'),
(193, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-08-28 23:04:37', '2026-08-28 23:04:37'),
(194, 9, 1, NULL, 'user_login', 'User logged in: James Mwangi (Role: pharmacy)', NULL, NULL, '2026-08-28 23:04:44', '2026-08-28 23:04:44'),
(195, 10, 1, NULL, 'user_logout', 'User logged out: Reception SALOME (Role: reception)', NULL, NULL, '2026-08-28 23:10:50', '2026-08-28 23:10:50'),
(196, 5, 1, NULL, 'user_login', 'User logged in: Dr. Grace Peter (Role: doctor)', NULL, NULL, '2026-08-28 23:10:56', '2026-08-28 23:10:56'),
(197, 10, 1, NULL, 'user_logout', 'User logged out: Reception SALOME (Role: reception)', NULL, NULL, '2026-08-28 23:23:46', '2026-08-28 23:23:46'),
(198, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-08-28 23:24:01', '2026-08-28 23:24:01'),
(199, 4, 1, 60, 'referral_created', 'Patient referred internally: ANDREW VICENT CHIKUPE (#REF-20260829-0060-726) - From Dr. Dr.ERICK JOHN to Dr. Dr. Grace Peter (Status: assigned, doctor_id changed from 4 to 5)', NULL, NULL, '2026-08-28 23:26:19', '2026-08-28 23:26:19'),
(200, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-08-28 23:27:50', '2026-08-28 23:27:50'),
(201, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-08-28 23:28:04', '2026-08-28 23:28:04'),
(202, 5, 1, NULL, 'user_logout', 'User logged out: Dr. Grace Peter (Role: doctor)', NULL, NULL, '2026-08-28 23:29:37', '2026-08-28 23:29:37'),
(203, 10, 1, NULL, 'user_login', 'User logged in: Reception SALOME (Role: reception)', NULL, NULL, '2026-08-28 23:29:46', '2026-08-28 23:29:46'),
(204, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-08-28 23:32:32', '2026-08-28 23:32:32'),
(205, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-08-28 23:32:57', '2026-08-28 23:32:57'),
(206, 4, 1, 59, 'referral_created', 'Patient referred internally: AMOSI NGOMENI (#REF-20260829-0059-579) - From Dr. Dr.ERICK JOHN to Dr. Dr. Grace Peter (Status: assigned, doctor_id changed from 4 to 5)', NULL, NULL, '2026-08-28 23:35:35', '2026-08-28 23:35:35'),
(207, 4, 1, 60, 'referral_created', 'Patient referred internally: ANDREW VICENT CHIKUPE (#REF-20260829-0060-945) - From Dr. Dr.ERICK JOHN to Dr. Dr. Grace Peter (Status: assigned, doctor_id changed from 4 to 5)', NULL, NULL, '2026-08-28 23:35:35', '2026-08-28 23:35:35'),
(208, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-08-28 23:37:32', '2026-08-28 23:37:32'),
(209, 5, 1, NULL, 'user_login', 'User logged in: Dr. Grace Peter (Role: doctor)', NULL, NULL, '2026-08-28 23:37:47', '2026-08-28 23:37:47'),
(210, 5, 1, NULL, 'user_logout', 'User logged out: Dr. Grace Peter (Role: doctor)', NULL, NULL, '2026-08-29 06:27:23', '2026-08-29 06:27:23'),
(211, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-08-29 06:27:35', '2026-08-29 06:27:35'),
(212, 9, 1, NULL, 'user_login', 'User logged in: James Mwangi (Role: pharmacy)', NULL, NULL, '2026-08-29 07:34:23', '2026-08-29 07:34:23'),
(213, 9, 1, NULL, 'user_login', 'User logged in: James Mwangi (Role: pharmacy)', NULL, NULL, '2026-08-29 13:02:12', '2026-08-29 13:02:12'),
(214, 9, 1, NULL, 'user_logout', 'User logged out: James Mwangi (Role: pharmacy)', NULL, NULL, '2026-08-29 13:02:47', '2026-08-29 13:02:47'),
(215, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-08-29 13:03:01', '2026-08-29 13:03:01'),
(216, 9, 1, NULL, 'user_logout', 'User logged out: James Mwangi (Role: pharmacy)', NULL, NULL, '2026-08-29 13:11:20', '2026-08-29 13:11:20'),
(217, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-08-29 13:11:34', '2026-08-29 13:11:34'),
(218, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-08-29 13:15:57', '2026-08-29 13:15:57'),
(219, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Role: reception)', NULL, NULL, '2026-08-29 13:16:12', '2026-08-29 13:16:12'),
(220, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-08-29 13:44:40', '2026-08-29 13:44:40'),
(221, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-08-29 13:44:55', '2026-08-29 13:44:55'),
(222, 4, 1, NULL, 'doctor_status_changed', 'Dr. Dr.ERICK JOHN changed status to: offline', NULL, NULL, '2026-08-29 16:55:47', '2026-08-29 16:55:47'),
(223, 4, 1, NULL, 'doctor_status_changed', 'Dr. Dr.ERICK JOHN changed status to: online', NULL, NULL, '2026-08-29 16:55:48', '2026-08-29 16:55:48'),
(224, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-08-29 16:59:46', '2026-08-29 16:59:46'),
(225, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Role: reception)', NULL, NULL, '2026-08-29 16:59:56', '2026-08-29 16:59:56'),
(226, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-08-29 17:10:08', '2026-08-29 17:10:08'),
(227, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-08-29 17:10:21', '2026-08-29 17:10:21'),
(228, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-08-29 17:49:57', '2026-08-29 17:49:57'),
(229, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Role: reception)', NULL, NULL, '2026-08-29 17:50:28', '2026-08-29 17:50:28'),
(230, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-08-29 19:59:43', '2026-08-29 19:59:43'),
(231, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Role: admin)', NULL, NULL, '2026-08-29 19:59:52', '2026-08-29 19:59:52'),
(232, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-08-29 20:25:09', '2026-08-29 20:25:09'),
(233, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-08-29 20:25:23', '2026-08-29 20:25:23'),
(234, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-08-29 20:26:38', '2026-08-29 20:26:38'),
(235, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-08-29 20:26:42', '2026-08-29 20:26:42'),
(236, 1, 1, NULL, 'doctor_status_changed', 'Dr. Dr. Anna Kivuyo changed status to: online', NULL, NULL, '2026-08-29 21:52:57', '2026-08-29 21:52:57'),
(237, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-08-31 14:08:08', '2026-08-31 14:08:08'),
(238, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 68', NULL, NULL, '2026-08-31 14:08:16', '2026-08-31 14:08:16'),
(239, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 69', NULL, NULL, '2026-08-31 14:08:19', '2026-08-31 14:08:19'),
(240, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 70', NULL, NULL, '2026-08-31 14:08:21', '2026-08-31 14:08:21'),
(241, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 71', NULL, NULL, '2026-08-31 15:11:17', '2026-08-31 15:11:17'),
(242, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 72', NULL, NULL, '2026-08-31 15:11:20', '2026-08-31 15:11:20'),
(243, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-08-31 15:15:17', '2026-08-31 15:15:17'),
(244, 9, 1, NULL, 'user_login', 'User logged in: James Mwangi (Role: pharmacy)', NULL, NULL, '2026-08-31 15:15:22', '2026-08-31 15:15:22'),
(245, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-08-31 15:23:48', '2026-08-31 15:23:48'),
(246, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-08-31 15:23:51', '2026-08-31 15:23:51'),
(247, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-08-31 15:23:56', '2026-08-31 15:23:56'),
(248, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-08-31 15:23:58', '2026-08-31 15:23:58'),
(249, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-08-31 15:28:22', '2026-08-31 15:28:22'),
(250, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-08-31 15:28:25', '2026-08-31 15:28:25'),
(251, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-08-31 15:29:43', '2026-08-31 15:29:43'),
(252, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-08-31 15:34:01', '2026-08-31 15:34:01'),
(253, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-08-31 15:34:08', '2026-08-31 15:34:08'),
(254, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-08-31 15:36:21', '2026-08-31 15:36:21'),
(255, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-08-31 15:36:25', '2026-08-31 15:36:25'),
(256, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Role: admin)', NULL, NULL, '2026-08-31 15:39:13', '2026-08-31 15:39:13'),
(257, 1, 1, NULL, 'employee_updated', 'Employee ANGERITHA KIMARO updated (Role: laboratory) - Password UPDATED and INVALIDATED old password', NULL, NULL, '2026-08-31 15:55:29', '2026-08-31 15:55:29'),
(258, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-08-31 15:55:32', '2026-08-31 15:55:32'),
(259, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-08-31 15:55:42', '2026-08-31 15:55:42'),
(260, 1, 1, NULL, 'profile_updated', 'Profile updated by: System Admin', NULL, NULL, '2026-09-01 19:34:37', '2026-09-01 19:34:37'),
(261, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Role: admin)', NULL, NULL, '2026-09-02 09:37:58', '2026-09-02 09:37:58'),
(262, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-02 09:39:08', '2026-09-02 09:39:08'),
(263, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-02 09:39:31', '2026-09-02 09:39:31'),
(264, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-02 09:39:39', '2026-09-02 09:39:39'),
(265, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-02 09:39:52', '2026-09-02 09:39:52'),
(266, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-02 09:40:50', '2026-09-02 09:40:50'),
(267, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-02 09:40:56', '2026-09-02 09:40:56'),
(268, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-02 09:41:25', '2026-09-02 09:41:25'),
(269, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-02 09:41:36', '2026-09-02 09:41:36'),
(270, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 73', NULL, NULL, '2026-09-02 09:41:44', '2026-09-02 09:41:44'),
(271, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 74', NULL, NULL, '2026-09-02 09:41:47', '2026-09-02 09:41:47'),
(272, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-02 09:42:04', '2026-09-02 09:42:04'),
(273, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-02 09:42:09', '2026-09-02 09:42:09'),
(274, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-02 09:43:34', '2026-09-02 09:43:34'),
(275, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-02 09:43:41', '2026-09-02 09:43:41'),
(276, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-02 09:44:25', '2026-09-02 09:44:25'),
(277, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-02 09:44:43', '2026-09-02 09:44:43'),
(278, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-02 09:44:55', '2026-09-02 09:44:55'),
(279, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-02 09:44:59', '2026-09-02 09:44:59'),
(280, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-02 09:46:06', '2026-09-02 09:46:06'),
(281, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-02 09:46:13', '2026-09-02 09:46:13'),
(282, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-02 09:47:02', '2026-09-02 09:47:02'),
(283, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-02 09:47:08', '2026-09-02 09:47:08'),
(284, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-02 09:49:52', '2026-09-02 09:49:52'),
(285, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-02 09:49:56', '2026-09-02 09:49:56'),
(286, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-02 09:55:37', '2026-09-02 09:55:37'),
(287, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-02 09:55:41', '2026-09-02 09:55:41'),
(288, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-02 09:55:58', '2026-09-02 09:55:58'),
(289, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-02 09:56:06', '2026-09-02 09:56:06'),
(290, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-02 09:56:31', '2026-09-02 09:56:31'),
(291, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-02 09:57:03', '2026-09-02 09:57:03'),
(292, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 75', NULL, NULL, '2026-09-02 09:57:15', '2026-09-02 09:57:15'),
(293, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 76', NULL, NULL, '2026-09-02 09:57:28', '2026-09-02 09:57:28'),
(294, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-02 09:57:36', '2026-09-02 09:57:36'),
(295, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-02 09:57:46', '2026-09-02 09:57:46'),
(296, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-02 09:57:55', '2026-09-02 09:57:55'),
(297, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-02 09:58:07', '2026-09-02 09:58:07'),
(298, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-02 10:02:45', '2026-09-02 10:02:45'),
(299, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-02 10:02:53', '2026-09-02 10:02:53'),
(300, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-02 10:27:04', '2026-09-02 10:27:04'),
(301, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-02 10:27:08', '2026-09-02 10:27:08'),
(302, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-02 10:27:37', '2026-09-02 10:27:37'),
(303, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-02 10:27:42', '2026-09-02 10:27:42'),
(304, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-02 20:14:19', '2026-09-02 20:14:19'),
(305, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-02 20:14:28', '2026-09-02 20:14:28'),
(306, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Role: admin)', NULL, NULL, '2026-09-02 20:14:51', '2026-09-02 20:14:51'),
(307, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-02 20:37:55', '2026-09-02 20:37:55'),
(308, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Role: admin)', NULL, NULL, '2026-09-02 20:40:46', '2026-09-02 20:40:46'),
(309, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-02 20:47:50', '2026-09-02 20:47:50'),
(310, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Role: admin)', NULL, NULL, '2026-09-02 20:55:50', '2026-09-02 20:55:50'),
(311, 1, 1, NULL, 'employee_updated', 'Employee ANGERITHA KIMARO updated (Roles: doctor)', NULL, NULL, '2026-09-02 20:56:53', '2026-09-02 20:56:53'),
(312, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-02 20:56:57', '2026-09-02 20:56:57'),
(313, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Role: doctor)', NULL, NULL, '2026-09-02 20:57:05', '2026-09-02 20:57:05'),
(314, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: doctor)', NULL, NULL, '2026-09-02 20:57:15', '2026-09-02 20:57:15'),
(315, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Role: admin)', NULL, NULL, '2026-09-02 20:57:21', '2026-09-02 20:57:21'),
(316, 1, 1, NULL, 'employee_updated', 'Employee ANGERITHA KIMARO updated (Roles: reception, laboratory)', NULL, NULL, '2026-09-02 20:58:06', '2026-09-02 20:58:06'),
(317, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-02 20:58:18', '2026-09-02 20:58:18'),
(318, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Role: reception)', NULL, NULL, '2026-09-02 20:58:40', '2026-09-02 20:58:40'),
(319, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: reception)', NULL, NULL, '2026-09-02 20:58:55', '2026-09-02 20:58:55'),
(320, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Role: admin)', NULL, NULL, '2026-09-02 21:04:29', '2026-09-02 21:04:29'),
(321, 1, 1, NULL, 'employee_added', 'Employee SARAFINA MHECHE added with roles: doctor, reception', NULL, NULL, '2026-09-02 21:06:05', '2026-09-02 21:06:05'),
(322, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-02 21:06:11', '2026-09-02 21:06:11'),
(323, 43, 1, NULL, 'user_login', 'User logged in: SARAFINA MHECHE (Role: doctor)', NULL, NULL, '2026-09-02 21:06:27', '2026-09-02 21:06:27'),
(324, 43, 1, NULL, 'user_logout', 'User logged out: SARAFINA MHECHE (Role: doctor)', NULL, NULL, '2026-09-02 21:06:38', '2026-09-02 21:06:38'),
(325, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Role: admin)', NULL, NULL, '2026-09-02 21:06:42', '2026-09-02 21:06:42'),
(326, 1, 1, NULL, 'employee_updated', 'Employee SARAFINA MHECHE updated (Roles: doctor, reception)', NULL, NULL, '2026-09-02 21:07:29', '2026-09-02 21:07:29'),
(327, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-02 21:08:00', '2026-09-02 21:08:00'),
(328, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Role: admin)', NULL, NULL, '2026-09-02 21:09:04', '2026-09-02 21:09:04'),
(329, 1, 1, NULL, 'employee_updated', 'Employee SARAFINA MHECHE updated (Roles: doctor, reception)', NULL, NULL, '2026-09-02 21:15:20', '2026-09-02 21:15:20'),
(330, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-02 21:15:24', '2026-09-02 21:15:24'),
(331, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Role: admin)', NULL, NULL, '2026-09-02 21:50:01', '2026-09-02 21:50:01'),
(332, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-02 21:50:09', '2026-09-02 21:50:09'),
(333, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Primary Role: admin)', NULL, NULL, '2026-09-02 21:56:54', '2026-09-02 21:56:54'),
(334, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-02 21:57:01', '2026-09-02 21:57:01'),
(335, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general, Role: admin)', NULL, NULL, '2026-09-02 22:01:39', '2026-09-02 22:01:39'),
(336, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-02 22:01:52', '2026-09-02 22:01:52'),
(337, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general, Role: admin)', NULL, NULL, '2026-09-02 22:09:54', '2026-09-02 22:09:54'),
(338, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-02 22:10:01', '2026-09-02 22:10:01'),
(339, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-02 22:10:05', '2026-09-02 22:10:05'),
(340, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-02 22:13:02', '2026-09-02 22:13:02'),
(341, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-02 22:13:12', '2026-09-02 22:13:12'),
(342, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-02 22:14:05', '2026-09-02 22:14:05'),
(343, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general, Role: pharmacy)', NULL, NULL, '2026-09-02 22:14:13', '2026-09-02 22:14:13'),
(344, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-02 22:17:41', '2026-09-02 22:17:41'),
(345, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-02 22:17:46', '2026-09-02 22:17:46'),
(346, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-02 22:18:14', '2026-09-02 22:18:14'),
(347, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general, Role: pharmacy)', NULL, NULL, '2026-09-02 22:18:24', '2026-09-02 22:18:24'),
(348, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-02 22:19:41', '2026-09-02 22:19:41'),
(349, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-02 22:19:47', '2026-09-02 22:19:47'),
(350, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-02 22:19:53', '2026-09-02 22:19:53');
INSERT INTO `activity_logs` (`id`, `user_id`, `branch_id`, `patient_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`, `updated_at`) VALUES
(351, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-02 22:20:00', '2026-09-02 22:20:00'),
(352, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-02 22:20:25', '2026-09-02 22:20:25'),
(353, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-02 22:20:33', '2026-09-02 22:20:33'),
(354, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-02 22:31:19', '2026-09-02 22:31:19'),
(355, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-02 22:31:23', '2026-09-02 22:31:23'),
(356, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-02 22:41:40', '2026-09-02 22:41:40'),
(357, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-02 22:41:48', '2026-09-02 22:41:48'),
(358, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-02 22:43:26', '2026-09-02 22:43:26'),
(359, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-02 22:43:32', '2026-09-02 22:43:32'),
(360, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-02 22:44:01', '2026-09-02 22:44:01'),
(361, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-02 22:44:06', '2026-09-02 22:44:06'),
(362, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-02 23:09:28', '2026-09-02 23:09:28'),
(363, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-02 23:09:36', '2026-09-02 23:09:36'),
(364, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-02 23:10:21', '2026-09-02 23:10:21'),
(365, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-02 23:10:27', '2026-09-02 23:10:27'),
(366, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-02 23:11:14', '2026-09-02 23:11:14'),
(367, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general, Role: reception)', NULL, NULL, '2026-09-02 23:11:21', '2026-09-02 23:11:21'),
(368, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: reception)', NULL, NULL, '2026-09-02 23:11:28', '2026-09-02 23:11:28'),
(369, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general, Role: reception)', NULL, NULL, '2026-09-02 23:11:31', '2026-09-02 23:11:31'),
(370, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: reception)', NULL, NULL, '2026-09-02 23:11:43', '2026-09-02 23:11:43'),
(371, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general, Role: admin)', NULL, NULL, '2026-09-02 23:11:49', '2026-09-02 23:11:49'),
(372, 1, 1, NULL, 'employee_updated', 'Employee ANGERITHA KIMARO updated (Roles: laboratory)', NULL, NULL, '2026-09-02 23:12:10', '2026-09-02 23:12:10'),
(373, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-02 23:12:17', '2026-09-02 23:12:17'),
(374, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general, Role: laboratory)', NULL, NULL, '2026-09-02 23:12:28', '2026-09-02 23:12:28'),
(375, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 77', NULL, NULL, '2026-09-02 23:12:35', '2026-09-02 23:12:35'),
(376, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 78', NULL, NULL, '2026-09-02 23:12:38', '2026-09-02 23:12:38'),
(377, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 79', NULL, NULL, '2026-09-02 23:12:41', '2026-09-02 23:12:41'),
(378, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 80', NULL, NULL, '2026-09-02 23:12:44', '2026-09-02 23:12:44'),
(379, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-02 23:13:45', '2026-09-02 23:13:45'),
(380, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-02 23:13:49', '2026-09-02 23:13:49'),
(381, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-02 23:16:45', '2026-09-02 23:16:45'),
(382, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general, Role: pharmacy)', NULL, NULL, '2026-09-02 23:16:57', '2026-09-02 23:16:57'),
(383, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-02 23:17:25', '2026-09-02 23:17:25'),
(384, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-02 23:17:31', '2026-09-02 23:17:31'),
(385, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-03 01:29:21', '2026-09-03 01:29:21'),
(386, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general, Role: admin)', NULL, NULL, '2026-09-03 01:29:25', '2026-09-03 01:29:25'),
(387, 1, 2, NULL, 'employee_added', 'Employee ANGERITHA KIMARO added with roles: reception', NULL, NULL, '2026-09-03 01:31:31', '2026-09-03 01:31:31'),
(388, 1, 2, NULL, 'employee_deactivated', 'Deactivated employee: ANGERITHA KIMARO (ID: 44) by System Admin', NULL, NULL, '2026-09-03 01:31:42', '2026-09-03 01:31:42'),
(389, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-03 13:24:46', '2026-09-03 13:24:46'),
(390, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-03 13:52:24', '2026-09-03 13:52:24'),
(391, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-03 13:59:49', '2026-09-03 13:59:49'),
(392, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-03 15:13:15', '2026-09-03 15:13:15'),
(393, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-03 15:13:19', '2026-09-03 15:13:19'),
(394, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general, Role: pharmacy)', NULL, NULL, '2026-09-03 16:07:27', '2026-09-03 16:07:27'),
(395, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-03 16:20:32', '2026-09-03 16:20:32'),
(396, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-03 16:20:39', '2026-09-03 16:20:39'),
(397, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-03 16:54:07', '2026-09-03 16:54:07'),
(398, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-03 16:54:11', '2026-09-03 16:54:11'),
(399, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-03 17:08:20', '2026-09-03 17:08:20'),
(400, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-03 17:08:26', '2026-09-03 17:08:26'),
(401, 4, 1, 60, 'referral_created', 'Patient referred externally: ANDREW VICENT CHIKUPE (#REF-20260903-0060-665) - Visit ID: 85 - To: MUHIMBILI', NULL, NULL, '2026-09-03 17:16:57', '2026-09-03 17:16:57'),
(402, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-03 17:27:18', '2026-09-03 17:27:18'),
(403, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general, Role: pharmacy)', NULL, NULL, '2026-09-03 17:27:24', '2026-09-03 17:27:24'),
(404, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-03 17:28:05', '2026-09-03 17:28:05'),
(405, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-03 17:28:11', '2026-09-03 17:28:11'),
(406, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-03 17:32:36', '2026-09-03 17:32:36'),
(407, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general, Role: pharmacy)', NULL, NULL, '2026-09-03 17:32:47', '2026-09-03 17:32:47'),
(408, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-03 17:55:20', '2026-09-03 17:55:20'),
(409, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general, Role: pharmacy)', NULL, NULL, '2026-09-03 17:55:24', '2026-09-03 17:55:24'),
(410, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-03 17:57:07', '2026-09-03 17:57:07'),
(411, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-03 17:57:14', '2026-09-03 17:57:14'),
(412, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-03 17:58:41', '2026-09-03 17:58:41'),
(413, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-03 17:58:43', '2026-09-03 17:58:43'),
(414, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-03 18:13:09', '2026-09-03 18:13:09'),
(415, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-03 18:13:16', '2026-09-03 18:13:16'),
(416, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-03 18:13:25', '2026-09-03 18:13:25'),
(417, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general, Role: pharmacy)', NULL, NULL, '2026-09-03 18:13:28', '2026-09-03 18:13:28'),
(418, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: reception, Role: reception)', NULL, NULL, '2026-09-03 20:39:02', '2026-09-03 20:39:02'),
(419, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-03 20:43:21', '2026-09-03 20:43:21'),
(420, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-03 20:43:30', '2026-09-03 20:43:30'),
(421, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-03 20:44:25', '2026-09-03 20:44:25'),
(422, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-03 20:44:32', '2026-09-03 20:44:32'),
(423, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-03 20:44:37', '2026-09-03 20:44:37'),
(424, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-03 20:44:44', '2026-09-03 20:44:44'),
(425, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-03 20:45:09', '2026-09-03 20:45:09'),
(426, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-03 20:45:13', '2026-09-03 20:45:13'),
(427, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-03 20:46:31', '2026-09-03 20:46:31'),
(428, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general, Role: laboratory)', NULL, NULL, '2026-09-03 20:46:37', '2026-09-03 20:46:37'),
(429, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 82', NULL, NULL, '2026-09-03 20:46:44', '2026-09-03 20:46:44'),
(430, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 83', NULL, NULL, '2026-09-03 20:46:48', '2026-09-03 20:46:48'),
(431, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-03 20:47:21', '2026-09-03 20:47:21'),
(432, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-03 20:47:25', '2026-09-03 20:47:25'),
(433, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-03 20:49:06', '2026-09-03 20:49:06'),
(434, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-03 20:49:11', '2026-09-03 20:49:11'),
(435, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-03 20:56:55', '2026-09-03 20:56:55'),
(436, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general, Role: pharmacy)', NULL, NULL, '2026-09-03 20:57:02', '2026-09-03 20:57:02'),
(437, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-03 20:58:21', '2026-09-03 20:58:21'),
(438, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-03 20:58:31', '2026-09-03 20:58:31'),
(439, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-03 21:00:00', '2026-09-03 21:00:00'),
(440, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general, Role: pharmacy)', NULL, NULL, '2026-09-03 21:00:04', '2026-09-03 21:00:04'),
(441, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-03 21:01:55', '2026-09-03 21:01:55'),
(442, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-03 21:01:59', '2026-09-03 21:01:59'),
(443, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-03 21:09:02', '2026-09-03 21:09:02'),
(444, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-03 21:09:08', '2026-09-03 21:09:08'),
(445, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-03 21:21:13', '2026-09-03 21:21:13'),
(446, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general, Role: pharmacy)', NULL, NULL, '2026-09-03 21:21:18', '2026-09-03 21:21:18'),
(447, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-03 21:21:28', '2026-09-03 21:21:28'),
(448, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-03 21:21:32', '2026-09-03 21:21:32'),
(449, 4, 1, NULL, 'prescription_created', 'Prescription #PRES-20260903-0061-201 created for patient ID: 61 with 1 medications', NULL, NULL, '2026-09-03 21:22:30', '2026-09-03 21:22:30'),
(450, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-03 21:22:35', '2026-09-03 21:22:35'),
(451, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general, Role: pharmacy)', NULL, NULL, '2026-09-03 21:22:38', '2026-09-03 21:22:38'),
(452, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-03 21:44:10', '2026-09-03 21:44:10'),
(453, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-03 21:44:15', '2026-09-03 21:44:15'),
(454, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-03 21:56:31', '2026-09-03 21:56:31'),
(455, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general, Role: pharmacy)', NULL, NULL, '2026-09-03 21:56:35', '2026-09-03 21:56:35'),
(456, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-03 22:00:35', '2026-09-03 22:00:35'),
(457, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general, Role: laboratory)', NULL, NULL, '2026-09-03 22:00:40', '2026-09-03 22:00:40'),
(458, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-03 22:00:49', '2026-09-03 22:00:49'),
(459, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general, Role: pharmacy)', NULL, NULL, '2026-09-03 22:04:12', '2026-09-03 22:04:12'),
(460, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-03 22:23:43', '2026-09-03 22:23:43'),
(461, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-03 22:23:49', '2026-09-03 22:23:49'),
(462, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-03 22:24:26', '2026-09-03 22:24:26'),
(463, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-03 22:24:32', '2026-09-03 22:24:32'),
(464, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-03 22:25:11', '2026-09-03 22:25:11'),
(465, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-03 22:25:15', '2026-09-03 22:25:15'),
(466, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-03 22:26:25', '2026-09-03 22:26:25'),
(467, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-03 22:26:30', '2026-09-03 22:26:30'),
(468, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-03 22:46:30', '2026-09-03 22:46:30'),
(469, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-03 22:46:33', '2026-09-03 22:46:33'),
(470, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-03 22:47:00', '2026-09-03 22:47:00'),
(471, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-03 22:47:08', '2026-09-03 22:47:08'),
(472, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-03 22:47:33', '2026-09-03 22:47:33'),
(473, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-03 22:47:36', '2026-09-03 22:47:36'),
(474, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-03 22:48:25', '2026-09-03 22:48:25'),
(475, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-03 22:48:31', '2026-09-03 22:48:31'),
(476, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-03 22:52:33', '2026-09-03 22:52:33'),
(477, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-03 22:52:41', '2026-09-03 22:52:41'),
(478, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-03 22:54:28', '2026-09-03 22:54:28'),
(479, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-03 22:54:30', '2026-09-03 22:54:30'),
(480, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-03 23:01:52', '2026-09-03 23:01:52'),
(481, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-03 23:01:56', '2026-09-03 23:01:56'),
(482, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-03 23:03:15', '2026-09-03 23:03:15'),
(483, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-03 23:03:19', '2026-09-03 23:03:19'),
(484, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-03 23:03:58', '2026-09-03 23:03:58'),
(485, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-03 23:04:04', '2026-09-03 23:04:04'),
(486, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-03 23:04:48', '2026-09-03 23:04:48'),
(487, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-03 23:04:54', '2026-09-03 23:04:54'),
(488, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-03 23:06:28', '2026-09-03 23:06:28'),
(489, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-03 23:06:33', '2026-09-03 23:06:33'),
(490, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-03 23:07:43', '2026-09-03 23:07:43'),
(491, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-03 23:07:46', '2026-09-03 23:07:46'),
(492, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-03 23:10:30', '2026-09-03 23:10:30'),
(493, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-03 23:10:31', '2026-09-03 23:10:31'),
(494, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-03 23:10:59', '2026-09-03 23:10:59'),
(495, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-03 23:11:44', '2026-09-03 23:11:44'),
(496, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-03 23:21:58', '2026-09-03 23:21:58'),
(497, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-03 23:22:02', '2026-09-03 23:22:02'),
(498, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-03 23:22:29', '2026-09-03 23:22:29'),
(499, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general, Role: laboratory)', NULL, NULL, '2026-09-03 23:22:33', '2026-09-03 23:22:33'),
(500, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 86', NULL, NULL, '2026-09-03 23:22:40', '2026-09-03 23:22:40'),
(501, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 87', NULL, NULL, '2026-09-03 23:22:49', '2026-09-03 23:22:49'),
(502, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-03 23:24:44', '2026-09-03 23:24:44'),
(503, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-03 23:24:52', '2026-09-03 23:24:52'),
(504, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-03 23:30:01', '2026-09-03 23:30:01'),
(505, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-03 23:30:06', '2026-09-03 23:30:06'),
(506, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-03 23:30:18', '2026-09-03 23:30:18'),
(507, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-03 23:30:23', '2026-09-03 23:30:23'),
(508, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-03 23:44:41', '2026-09-03 23:44:41'),
(509, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-03 23:44:47', '2026-09-03 23:44:47'),
(510, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-04 00:01:33', '2026-09-04 00:01:33'),
(511, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-04 00:01:38', '2026-09-04 00:01:38'),
(512, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-04 00:13:08', '2026-09-04 00:13:08'),
(513, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-04 00:13:13', '2026-09-04 00:13:13'),
(514, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-04 00:14:18', '2026-09-04 00:14:18'),
(515, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general, Role: laboratory)', NULL, NULL, '2026-09-04 00:15:04', '2026-09-04 00:15:04'),
(516, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 88', NULL, NULL, '2026-09-04 00:15:11', '2026-09-04 00:15:11'),
(517, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 89', NULL, NULL, '2026-09-04 00:15:18', '2026-09-04 00:15:18'),
(518, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-04 00:15:56', '2026-09-04 00:15:56'),
(519, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-04 00:16:00', '2026-09-04 00:16:00'),
(520, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-04 00:19:33', '2026-09-04 00:19:33'),
(521, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-04 00:19:42', '2026-09-04 00:19:42'),
(522, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-04 00:25:19', '2026-09-04 00:25:19'),
(523, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-04 00:25:22', '2026-09-04 00:25:22'),
(524, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-04 00:28:13', '2026-09-04 00:28:13'),
(525, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-04 00:28:21', '2026-09-04 00:28:21'),
(526, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-04 00:31:43', '2026-09-04 00:31:43'),
(527, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-04 00:31:48', '2026-09-04 00:31:48'),
(528, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-04 00:32:15', '2026-09-04 00:32:15'),
(529, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-04 00:32:21', '2026-09-04 00:32:21'),
(530, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-04 00:32:38', '2026-09-04 00:32:38'),
(531, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general, Role: laboratory)', NULL, NULL, '2026-09-04 00:32:47', '2026-09-04 00:32:47'),
(532, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 90', NULL, NULL, '2026-09-04 00:38:42', '2026-09-04 00:38:42'),
(533, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-04 00:39:08', '2026-09-04 00:39:08'),
(534, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-04 00:39:14', '2026-09-04 00:39:14'),
(535, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-04 00:40:23', '2026-09-04 00:40:23'),
(536, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-04 00:40:25', '2026-09-04 00:40:25'),
(537, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-04 00:40:29', '2026-09-04 00:40:29'),
(538, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-04 00:40:34', '2026-09-04 00:40:34'),
(539, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-04 00:50:51', '2026-09-04 00:50:51'),
(540, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-04 00:50:55', '2026-09-04 00:50:55'),
(541, 4, 1, NULL, 'consultation_auto_completed', 'Consultation #VIS-20260904-6485 auto-completed', NULL, NULL, '2026-09-04 00:50:59', '2026-09-04 00:50:59'),
(542, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-04 11:59:33', '2026-09-04 11:59:33'),
(543, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-04 12:00:21', '2026-09-04 12:00:21'),
(544, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-04 12:00:26', '2026-09-04 12:00:26'),
(545, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-04 12:01:07', '2026-09-04 12:01:07'),
(546, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-04 12:01:22', '2026-09-04 12:01:22'),
(547, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-04 12:02:15', '2026-09-04 12:02:15'),
(548, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-04 12:02:19', '2026-09-04 12:02:19'),
(549, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-04 12:45:02', '2026-09-04 12:45:02'),
(550, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-04 12:45:06', '2026-09-04 12:45:06'),
(551, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-04 12:45:27', '2026-09-04 12:45:27'),
(552, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general, Role: laboratory)', NULL, NULL, '2026-09-04 12:45:30', '2026-09-04 12:45:30'),
(553, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 91', NULL, NULL, '2026-09-04 12:45:37', '2026-09-04 12:45:37'),
(554, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-04 12:46:03', '2026-09-04 12:46:03'),
(555, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-04 12:46:08', '2026-09-04 12:46:08'),
(556, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-04 16:30:43', '2026-09-04 16:30:43'),
(557, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-04 16:32:32', '2026-09-04 16:32:32'),
(558, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-04 16:41:56', '2026-09-04 16:41:56'),
(559, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-04 16:41:59', '2026-09-04 16:41:59'),
(560, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-04 16:42:31', '2026-09-04 16:42:31'),
(561, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general, Role: laboratory)', NULL, NULL, '2026-09-04 16:42:36', '2026-09-04 16:42:36'),
(562, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 92', NULL, NULL, '2026-09-04 16:42:43', '2026-09-04 16:42:43'),
(563, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-04 16:42:55', '2026-09-04 16:42:55'),
(564, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-04 16:43:05', '2026-09-04 16:43:05'),
(565, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-04 16:43:14', '2026-09-04 16:43:14'),
(566, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-04 16:43:18', '2026-09-04 16:43:18'),
(567, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-04 16:45:20', '2026-09-04 16:45:20'),
(568, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-04 16:45:24', '2026-09-04 16:45:24'),
(569, 4, 1, NULL, 'consultation_auto_completed', 'Consultation #VIS-20260904-5225 auto-completed', NULL, NULL, '2026-09-04 16:45:30', '2026-09-04 16:45:30'),
(570, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-04 16:46:53', '2026-09-04 16:46:53'),
(571, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general, Role: laboratory)', NULL, NULL, '2026-09-04 16:46:56', '2026-09-04 16:46:56'),
(572, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 93', NULL, NULL, '2026-09-04 16:47:02', '2026-09-04 16:47:02'),
(573, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-04 16:47:15', '2026-09-04 16:47:15'),
(574, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-04 16:47:18', '2026-09-04 16:47:18'),
(575, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-04 16:53:42', '2026-09-04 16:53:42'),
(576, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-04 16:53:46', '2026-09-04 16:53:46'),
(577, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-04 16:54:08', '2026-09-04 16:54:08'),
(578, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-04 16:54:12', '2026-09-04 16:54:12'),
(579, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-04 17:05:13', '2026-09-04 17:05:13'),
(580, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-04 17:05:20', '2026-09-04 17:05:20'),
(581, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-04 17:11:36', '2026-09-04 17:11:36'),
(582, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-04 17:11:40', '2026-09-04 17:11:40'),
(583, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-04 17:12:41', '2026-09-04 17:12:41'),
(584, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general, Role: laboratory)', NULL, NULL, '2026-09-04 17:12:47', '2026-09-04 17:12:47'),
(585, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 94', NULL, NULL, '2026-09-04 17:12:54', '2026-09-04 17:12:54'),
(586, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-04 17:13:09', '2026-09-04 17:13:09'),
(587, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-04 17:13:15', '2026-09-04 17:13:15'),
(588, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-04 17:15:51', '2026-09-04 17:15:51'),
(589, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-04 17:15:55', '2026-09-04 17:15:55'),
(590, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-04 17:16:08', '2026-09-04 17:16:08'),
(591, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general, Role: pharmacy)', NULL, NULL, '2026-09-04 17:16:19', '2026-09-04 17:16:19'),
(592, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-04 17:18:21', '2026-09-04 17:18:21'),
(593, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-04 17:18:26', '2026-09-04 17:18:26'),
(594, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general, Role: laboratory)', NULL, NULL, '2026-09-04 17:49:28', '2026-09-04 17:49:28'),
(595, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 95', NULL, NULL, '2026-09-04 17:49:38', '2026-09-04 17:49:38'),
(596, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-04 17:51:06', '2026-09-04 17:51:06'),
(597, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general, Role: reception)', NULL, NULL, '2026-09-04 17:51:29', '2026-09-04 17:51:29'),
(598, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-04 18:12:37', '2026-09-04 18:12:37'),
(599, 9, 1, NULL, 'user_login', 'User logged in: James Mwangi (Mode: general, Role: pharmacy)', NULL, NULL, '2026-09-04 18:12:51', '2026-09-04 18:12:51'),
(600, 9, 1, NULL, 'user_logout', 'User logged out: James Mwangi (Role: pharmacy)', NULL, NULL, '2026-09-04 18:13:08', '2026-09-04 18:13:08'),
(601, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general, Role: reception)', NULL, NULL, '2026-09-04 18:13:14', '2026-09-04 18:13:14'),
(602, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-04 18:27:44', '2026-09-04 18:27:44'),
(603, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general, Role: admin)', NULL, NULL, '2026-09-04 18:27:55', '2026-09-04 18:27:55'),
(604, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-04 18:46:29', '2026-09-04 18:46:29'),
(605, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: reception, Role: reception)', NULL, NULL, '2026-09-04 18:46:45', '2026-09-04 18:46:45'),
(606, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-04 19:00:10', '2026-09-04 19:00:10'),
(607, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general, Role: laboratory)', NULL, NULL, '2026-09-04 19:00:17', '2026-09-04 19:00:17'),
(608, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 96', NULL, NULL, '2026-09-04 19:02:13', '2026-09-04 19:02:13'),
(609, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 97', NULL, NULL, '2026-09-04 19:02:17', '2026-09-04 19:02:17'),
(610, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-04 19:03:59', '2026-09-04 19:03:59'),
(611, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general, Role: reception)', NULL, NULL, '2026-09-04 19:04:11', '2026-09-04 19:04:11'),
(612, 4, 1, NULL, 'consultation_auto_completed', 'Consultation #VIS-20260904-9954 auto-completed', NULL, NULL, '2026-09-04 19:08:01', '2026-09-04 19:08:01'),
(613, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-04 19:17:25', '2026-09-04 19:17:25'),
(614, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: reception, Role: reception)', NULL, NULL, '2026-09-04 19:17:31', '2026-09-04 19:17:31'),
(615, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-04 19:18:44', '2026-09-04 19:18:44'),
(616, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general, Role: laboratory)', NULL, NULL, '2026-09-04 19:18:48', '2026-09-04 19:18:48'),
(617, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 98', NULL, NULL, '2026-09-04 19:18:55', '2026-09-04 19:18:55'),
(618, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 99', NULL, NULL, '2026-09-04 19:19:10', '2026-09-04 19:19:10'),
(619, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-04 19:19:32', '2026-09-04 19:19:32'),
(620, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general, Role: reception)', NULL, NULL, '2026-09-04 19:19:50', '2026-09-04 19:19:50'),
(621, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-04 19:58:17', '2026-09-04 19:58:17'),
(622, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general, Role: laboratory)', NULL, NULL, '2026-09-04 19:58:22', '2026-09-04 19:58:22'),
(623, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 100', NULL, NULL, '2026-09-04 19:58:30', '2026-09-04 19:58:30'),
(624, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-04 20:01:19', '2026-09-04 20:01:19'),
(625, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general, Role: reception)', NULL, NULL, '2026-09-04 20:01:50', '2026-09-04 20:01:50'),
(626, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-04 20:03:07', '2026-09-04 20:03:07'),
(627, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general, Role: laboratory)', NULL, NULL, '2026-09-04 20:03:12', '2026-09-04 20:03:12'),
(628, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 101', NULL, NULL, '2026-09-04 20:03:19', '2026-09-04 20:03:19'),
(629, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-04 20:04:07', '2026-09-04 20:04:07'),
(630, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general, Role: reception)', NULL, NULL, '2026-09-04 20:04:13', '2026-09-04 20:04:13'),
(631, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-04 20:35:31', '2026-09-04 20:35:31'),
(632, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general, Role: reception)', NULL, NULL, '2026-09-04 20:35:35', '2026-09-04 20:35:35'),
(633, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-04 20:41:33', '2026-09-04 20:41:33'),
(634, 9, 1, NULL, 'user_login', 'User logged in: James Mwangi (Mode: general, Role: pharmacy)', NULL, NULL, '2026-09-04 20:41:43', '2026-09-04 20:41:43'),
(635, 9, 1, NULL, 'user_logout', 'User logged out: James Mwangi (Role: pharmacy)', NULL, NULL, '2026-09-04 20:41:48', '2026-09-04 20:41:48'),
(636, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general, Role: laboratory)', NULL, NULL, '2026-09-04 20:41:52', '2026-09-04 20:41:52'),
(637, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 106', NULL, NULL, '2026-09-04 20:41:58', '2026-09-04 20:41:58'),
(638, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-04 20:47:53', '2026-09-04 20:47:53'),
(639, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general, Role: reception)', NULL, NULL, '2026-09-04 20:47:57', '2026-09-04 20:47:57'),
(640, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-04 21:22:31', '2026-09-04 21:22:31'),
(641, 9, 1, NULL, 'user_login', 'User logged in: James Mwangi (Mode: general, Role: pharmacy)', NULL, NULL, '2026-09-04 21:22:39', '2026-09-04 21:22:39'),
(642, 9, 1, NULL, 'user_logout', 'User logged out: James Mwangi (Role: pharmacy)', NULL, NULL, '2026-09-04 21:50:29', '2026-09-04 21:50:29'),
(643, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: reception, Role: reception)', NULL, NULL, '2026-09-04 21:50:38', '2026-09-04 21:50:38'),
(644, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-04 21:52:00', '2026-09-04 21:52:00'),
(645, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general, Role: laboratory)', NULL, NULL, '2026-09-04 21:52:05', '2026-09-04 21:52:05'),
(646, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 107', NULL, NULL, '2026-09-04 21:52:12', '2026-09-04 21:52:12'),
(647, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 108', NULL, NULL, '2026-09-04 21:52:19', '2026-09-04 21:52:19'),
(648, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-04 21:53:22', '2026-09-04 21:53:22'),
(649, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general, Role: reception)', NULL, NULL, '2026-09-04 21:53:38', '2026-09-04 21:53:38'),
(650, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-04 22:04:54', '2026-09-04 22:04:54'),
(651, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general, Role: reception)', NULL, NULL, '2026-09-04 22:04:57', '2026-09-04 22:04:57'),
(652, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-04 22:05:47', '2026-09-04 22:05:47'),
(653, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general, Role: laboratory)', NULL, NULL, '2026-09-04 22:06:00', '2026-09-04 22:06:00'),
(654, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 109', NULL, NULL, '2026-09-04 22:06:07', '2026-09-04 22:06:07'),
(655, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 110', NULL, NULL, '2026-09-04 22:06:14', '2026-09-04 22:06:14'),
(656, 4, 1, NULL, 'consultation_auto_completed', 'Consultation #VIS-20260905-3733 auto-completed', NULL, NULL, '2026-09-04 22:06:49', '2026-09-04 22:06:49'),
(657, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-04 22:07:45', '2026-09-04 22:07:45'),
(658, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general, Role: reception)', NULL, NULL, '2026-09-04 22:07:50', '2026-09-04 22:07:50'),
(659, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-04 22:08:42', '2026-09-04 22:08:42'),
(660, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-04 22:08:50', '2026-09-04 22:08:50'),
(661, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-04 22:09:23', '2026-09-04 22:09:23'),
(662, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general, Role: reception)', NULL, NULL, '2026-09-04 22:09:29', '2026-09-04 22:09:29'),
(663, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-04 22:10:04', '2026-09-04 22:10:04'),
(664, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general, Role: laboratory)', NULL, NULL, '2026-09-04 22:10:09', '2026-09-04 22:10:09'),
(665, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 111', NULL, NULL, '2026-09-04 22:10:16', '2026-09-04 22:10:16'),
(666, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 112', NULL, NULL, '2026-09-04 22:10:29', '2026-09-04 22:10:29'),
(667, 4, 1, NULL, 'consultation_auto_completed', 'Consultation #VIS-20260905-9846 auto-completed', NULL, NULL, '2026-09-04 22:12:14', '2026-09-04 22:12:14'),
(668, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-04 22:14:24', '2026-09-04 22:14:24'),
(669, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general, Role: reception)', NULL, NULL, '2026-09-04 22:14:28', '2026-09-04 22:14:28'),
(670, 4, 1, NULL, 'doctor_status_changed', 'Dr. Dr.ERICK JOHN changed status to: online', NULL, NULL, '2026-09-04 22:15:03', '2026-09-04 22:15:03'),
(671, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-04 22:15:28', '2026-09-04 22:15:28'),
(672, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general, Role: laboratory)', NULL, NULL, '2026-09-04 22:15:32', '2026-09-04 22:15:32'),
(673, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 113', NULL, NULL, '2026-09-04 22:15:38', '2026-09-04 22:15:38'),
(674, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-04 22:16:01', '2026-09-04 22:16:01'),
(675, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general, Role: reception)', NULL, NULL, '2026-09-04 22:16:07', '2026-09-04 22:16:07'),
(676, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-04 22:22:55', '2026-09-04 22:22:55'),
(677, 9, 1, NULL, 'user_login', 'User logged in: James Mwangi (Mode: general, Role: pharmacy)', NULL, NULL, '2026-09-04 22:23:06', '2026-09-04 22:23:06'),
(678, 9, 1, NULL, 'user_logout', 'User logged out: James Mwangi (Role: pharmacy)', NULL, NULL, '2026-09-04 22:25:49', '2026-09-04 22:25:49'),
(679, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general, Role: reception)', NULL, NULL, '2026-09-04 22:25:53', '2026-09-04 22:25:53'),
(680, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-04 23:18:35', '2026-09-04 23:18:35'),
(681, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general, Role: reception)', NULL, NULL, '2026-09-04 23:19:08', '2026-09-04 23:19:08'),
(682, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-04 23:28:33', '2026-09-04 23:28:33'),
(683, 14, 1, NULL, 'user_login', 'User logged in: Peter Lema (Mode: general, Role: laboratory)', NULL, NULL, '2026-09-04 23:29:02', '2026-09-04 23:29:02'),
(684, 14, 1, NULL, 'lab_test_started', 'Started lab test ID: 114', NULL, NULL, '2026-09-04 23:29:13', '2026-09-04 23:29:13'),
(685, 14, 1, NULL, 'user_logout', 'User logged out: Peter Lema (Role: laboratory)', NULL, NULL, '2026-09-04 23:35:34', '2026-09-04 23:35:34'),
(686, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general, Role: reception)', NULL, NULL, '2026-09-04 23:35:39', '2026-09-04 23:35:39'),
(687, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-04 23:36:11', '2026-09-04 23:36:11'),
(688, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general, Role: reception)', NULL, NULL, '2026-09-04 23:37:33', '2026-09-04 23:37:33'),
(689, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-04 23:40:54', '2026-09-04 23:40:54'),
(690, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general, Role: laboratory)', NULL, NULL, '2026-09-04 23:40:58', '2026-09-04 23:40:58'),
(691, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 115', NULL, NULL, '2026-09-04 23:41:05', '2026-09-04 23:41:05');
INSERT INTO `activity_logs` (`id`, `user_id`, `branch_id`, `patient_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`, `updated_at`) VALUES
(692, 4, 1, NULL, 'consultation_auto_completed', 'Consultation #VIS-20260905-1682 auto-completed', NULL, NULL, '2026-09-04 23:41:36', '2026-09-04 23:41:36'),
(693, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-05 07:32:05', '2026-09-05 07:32:05'),
(694, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general, Role: reception)', NULL, NULL, '2026-09-05 07:32:11', '2026-09-05 07:32:11'),
(695, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-05 07:33:49', '2026-09-05 07:33:49'),
(696, 9, 1, NULL, 'user_login', 'User logged in: James Mwangi (Mode: general, Role: pharmacy)', NULL, NULL, '2026-09-05 07:33:57', '2026-09-05 07:33:57'),
(697, 9, 1, NULL, 'user_logout', 'User logged out: James Mwangi (Role: pharmacy)', NULL, NULL, '2026-09-05 07:34:10', '2026-09-05 07:34:10'),
(698, 14, 1, NULL, 'user_login', 'User logged in: Peter Lema (Mode: general, Role: laboratory)', NULL, NULL, '2026-09-05 07:34:15', '2026-09-05 07:34:15'),
(699, 14, 1, NULL, 'lab_test_started', 'Started lab test ID: 116', NULL, NULL, '2026-09-05 07:34:24', '2026-09-05 07:34:24'),
(700, 14, 1, NULL, 'user_logout', 'User logged out: Peter Lema (Role: laboratory)', NULL, NULL, '2026-09-05 07:36:44', '2026-09-05 07:36:44'),
(701, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general, Role: reception)', NULL, NULL, '2026-09-05 07:36:49', '2026-09-05 07:36:49'),
(702, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-05 08:58:13', '2026-09-05 08:58:13'),
(703, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general, Role: pharmacy)', NULL, NULL, '2026-09-05 08:58:28', '2026-09-05 08:58:28'),
(704, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-05 08:59:06', '2026-09-05 08:59:06'),
(705, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general, Role: reception)', NULL, NULL, '2026-09-05 08:59:10', '2026-09-05 08:59:10'),
(706, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-05 09:04:48', '2026-09-05 09:04:48'),
(707, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general, Role: reception)', NULL, NULL, '2026-09-05 09:04:51', '2026-09-05 09:04:51'),
(708, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-05 09:12:26', '2026-09-05 09:12:26'),
(709, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general, Role: reception)', NULL, NULL, '2026-09-05 09:12:29', '2026-09-05 09:12:29'),
(710, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-05 09:16:06', '2026-09-05 09:16:06'),
(711, 14, 1, NULL, 'user_login', 'User logged in: Peter Lema (Mode: general, Role: laboratory)', NULL, NULL, '2026-09-05 09:16:09', '2026-09-05 09:16:09'),
(712, 14, 1, NULL, 'lab_test_started', 'Started lab test ID: 117', NULL, NULL, '2026-09-05 09:16:15', '2026-09-05 09:16:15'),
(713, 14, 1, NULL, 'user_logout', 'User logged out: Peter Lema (Role: laboratory)', NULL, NULL, '2026-09-05 09:18:53', '2026-09-05 09:18:53'),
(714, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general, Role: reception)', NULL, NULL, '2026-09-05 09:18:56', '2026-09-05 09:18:56'),
(715, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-05 10:07:22', '2026-09-05 10:07:22'),
(716, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general, Role: laboratory)', NULL, NULL, '2026-09-05 10:07:25', '2026-09-05 10:07:25'),
(717, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 118', NULL, NULL, '2026-09-05 10:07:32', '2026-09-05 10:07:32'),
(718, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-05 10:17:49', '2026-09-05 10:17:49'),
(719, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general, Role: reception)', NULL, NULL, '2026-09-05 10:17:54', '2026-09-05 10:17:54'),
(720, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-05 10:19:52', '2026-09-05 10:19:52'),
(721, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general, Role: laboratory)', NULL, NULL, '2026-09-05 10:20:00', '2026-09-05 10:20:00'),
(722, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-05 10:22:46', '2026-09-05 10:22:46'),
(723, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general, Role: reception)', NULL, NULL, '2026-09-05 10:22:50', '2026-09-05 10:22:50'),
(724, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-05 10:30:08', '2026-09-05 10:30:08'),
(725, 14, 1, NULL, 'user_login', 'User logged in: Peter Lema (Mode: general, Role: laboratory)', NULL, NULL, '2026-09-05 10:30:12', '2026-09-05 10:30:12'),
(726, 14, 1, NULL, 'user_logout', 'User logged out: Peter Lema (Role: laboratory)', NULL, NULL, '2026-09-05 11:12:20', '2026-09-05 11:12:20'),
(727, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general, Role: reception)', NULL, NULL, '2026-09-05 11:12:23', '2026-09-05 11:12:23'),
(728, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-05 11:41:33', '2026-09-05 11:41:33'),
(729, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general, Role: laboratory)', NULL, NULL, '2026-09-05 11:41:36', '2026-09-05 11:41:36'),
(730, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-05 11:55:02', '2026-09-05 11:55:02'),
(731, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general, Role: reception)', NULL, NULL, '2026-09-05 11:55:10', '2026-09-05 11:55:10'),
(732, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-05 13:04:00', '2026-09-05 13:04:00'),
(733, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general, Role: reception)', NULL, NULL, '2026-09-05 13:04:03', '2026-09-05 13:04:03'),
(734, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-05 13:11:02', '2026-09-05 13:11:02'),
(735, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-05 13:14:10', '2026-09-05 13:14:10'),
(736, 14, 1, NULL, 'user_login', 'User logged in: Peter Lema (Mode: general, Role: laboratory)', NULL, NULL, '2026-09-05 13:14:14', '2026-09-05 13:14:14'),
(737, 14, 1, NULL, 'lab_test_started', 'Started lab test ID: 119 - HIV ELISA', NULL, NULL, '2026-09-05 13:14:20', '2026-09-05 13:14:20'),
(738, 14, 1, NULL, 'user_logout', 'User logged out: Peter Lema (Role: laboratory)', NULL, NULL, '2026-09-05 13:24:02', '2026-09-05 13:24:02'),
(739, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general, Role: reception)', NULL, NULL, '2026-09-05 13:24:05', '2026-09-05 13:24:05'),
(740, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-05 13:24:22', '2026-09-05 13:24:22'),
(741, 9, 1, NULL, 'user_login', 'User logged in: James Mwangi (Mode: general, Role: pharmacy)', NULL, NULL, '2026-09-05 13:24:29', '2026-09-05 13:24:29'),
(742, 9, 1, NULL, 'user_logout', 'User logged out: James Mwangi (Role: pharmacy)', NULL, NULL, '2026-09-05 13:24:45', '2026-09-05 13:24:45'),
(743, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general, Role: reception)', NULL, NULL, '2026-09-05 13:24:50', '2026-09-05 13:24:50'),
(744, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-05 13:53:44', '2026-09-05 13:53:44'),
(745, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-05 13:54:22', '2026-09-05 13:54:22'),
(746, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-05 13:54:49', '2026-09-05 13:54:49'),
(747, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general, Role: doctor)', NULL, NULL, '2026-09-05 13:55:04', '2026-09-05 13:55:04'),
(748, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-05 13:56:16', '2026-09-05 13:56:16'),
(749, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general, Role: laboratory)', NULL, NULL, '2026-09-05 13:56:19', '2026-09-05 13:56:19'),
(750, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 120 - Echocardiogram', NULL, NULL, '2026-09-05 13:56:25', '2026-09-05 13:56:25'),
(751, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 121 - Ultrasound - 3D/4D Obstetric', NULL, NULL, '2026-09-05 13:56:28', '2026-09-05 13:56:28'),
(752, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-05 13:58:09', '2026-09-05 13:58:09'),
(753, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general, Role: reception)', NULL, NULL, '2026-09-05 13:58:12', '2026-09-05 13:58:12'),
(754, 4, 1, NULL, 'consultation_auto_completed', 'Consultation #VIS-20260905-3640 auto-completed', NULL, NULL, '2026-09-05 14:04:42', '2026-09-05 14:04:42'),
(755, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-05 14:19:13', '2026-09-05 14:19:13'),
(756, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general, Role: reception)', NULL, NULL, '2026-09-05 14:19:15', '2026-09-05 14:19:15'),
(757, 4, 1, NULL, 'doctor_status_changed', 'Dr. Dr.ERICK JOHN changed status to: online', NULL, NULL, '2026-09-05 14:19:48', '2026-09-05 14:19:48'),
(758, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-05 14:20:14', '2026-09-05 14:20:14'),
(759, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general, Role: laboratory)', NULL, NULL, '2026-09-05 14:20:17', '2026-09-05 14:20:17'),
(760, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 122 - Renal Function Test (RFT)', NULL, NULL, '2026-09-05 14:20:23', '2026-09-05 14:20:23'),
(761, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 123 - Ultrasound - Scrotal', NULL, NULL, '2026-09-05 14:20:26', '2026-09-05 14:20:26'),
(762, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-05 14:22:10', '2026-09-05 14:22:10'),
(763, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-05 14:22:13', '2026-09-05 14:22:13'),
(764, 4, 1, NULL, 'consultation_auto_completed', 'Consultation #VIS-20260905-3494 auto-completed', NULL, NULL, '2026-09-05 14:27:42', '2026-09-05 14:27:42'),
(765, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-05 14:40:08', '2026-09-05 14:40:08'),
(766, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-05 14:40:38', '2026-09-05 14:40:38'),
(767, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-05 14:47:00', '2026-09-05 14:47:00'),
(768, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general, Role: laboratory)', NULL, NULL, '2026-09-05 14:47:08', '2026-09-05 14:47:08'),
(769, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 125 - Thyroid Function Test (TFT)', NULL, NULL, '2026-09-05 14:47:14', '2026-09-05 14:47:14'),
(770, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 124 - Renal Function Test (RFT)', NULL, NULL, '2026-09-05 14:47:17', '2026-09-05 14:47:17'),
(771, 4, 1, NULL, 'consultation_auto_completed', 'Consultation #VIS-20260905-8965 auto-completed', NULL, NULL, '2026-09-05 14:47:55', '2026-09-05 14:47:55'),
(772, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-05 14:53:14', '2026-09-05 14:53:14'),
(773, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general, Role: laboratory)', NULL, NULL, '2026-09-05 14:53:17', '2026-09-05 14:53:17'),
(774, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-05 14:53:21', '2026-09-05 14:53:21'),
(775, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-05 14:53:24', '2026-09-05 14:53:24'),
(776, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-05 16:05:46', '2026-09-05 16:05:46'),
(777, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general, Role: laboratory)', NULL, NULL, '2026-09-05 16:06:00', '2026-09-05 16:06:00'),
(778, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 129 - Pregnancy Test (Blood - Beta HCG)', NULL, NULL, '2026-09-05 16:06:08', '2026-09-05 16:06:08'),
(779, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 127 - Echocardiogram', NULL, NULL, '2026-09-05 16:06:11', '2026-09-05 16:06:11'),
(780, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 128 - Renal Function Test (RFT)', NULL, NULL, '2026-09-05 16:06:13', '2026-09-05 16:06:13'),
(781, 4, 1, NULL, 'consultation_auto_completed', 'Consultation #VIS-20260905-8695 auto-completed', NULL, NULL, '2026-09-05 16:07:08', '2026-09-05 16:07:08'),
(782, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-05 19:52:35', '2026-09-05 19:52:35'),
(783, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general, Role: laboratory)', NULL, NULL, '2026-09-05 19:52:37', '2026-09-05 19:52:37'),
(784, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-05 19:53:02', '2026-09-05 19:53:02'),
(785, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general, Role: reception)', NULL, NULL, '2026-09-05 19:57:24', '2026-09-05 19:57:24'),
(786, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-05 20:05:55', '2026-09-05 20:05:55'),
(787, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general, Role: laboratory)', NULL, NULL, '2026-09-05 20:06:02', '2026-09-05 20:06:02'),
(788, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 130 - Viral Load HIV', NULL, NULL, '2026-09-05 20:06:18', '2026-09-05 20:06:18'),
(789, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 131 - Ultrasound - Liver/Biliary', NULL, NULL, '2026-09-05 20:06:21', '2026-09-05 20:06:21'),
(790, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-05 20:14:25', '2026-09-05 20:14:25'),
(791, 4, 1, NULL, 'consultation_auto_completed', 'Consultation #VIS-20260905-9392 auto-completed', NULL, NULL, '2026-09-05 20:16:24', '2026-09-05 20:16:24'),
(792, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-05 20:25:45', '2026-09-05 20:25:45'),
(793, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-05 20:25:57', '2026-09-05 20:25:57'),
(794, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-05 20:26:11', '2026-09-05 20:26:11'),
(795, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-05 20:26:41', '2026-09-05 20:26:41'),
(796, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-05 20:32:58', '2026-09-05 20:32:58'),
(797, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_green, Role: doctor)', NULL, NULL, '2026-09-05 20:33:07', '2026-09-05 20:33:07'),
(798, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-05 20:34:00', '2026-09-05 20:34:00'),
(799, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-05 20:43:20', '2026-09-05 20:43:20'),
(800, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-05 20:43:27', '2026-09-05 20:43:27'),
(801, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 132 - Thyroid Function Test (TFT)', NULL, NULL, '2026-09-05 20:43:33', '2026-09-05 20:43:33'),
(802, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 133 - Echocardiogram', NULL, NULL, '2026-09-05 20:43:39', '2026-09-05 20:43:39'),
(803, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-05 20:44:20', '2026-09-05 20:44:20'),
(804, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-05 20:44:26', '2026-09-05 20:44:26'),
(805, 4, 1, NULL, 'consultation_auto_completed', 'Consultation #VIS-20260905-5427 auto-completed', NULL, NULL, '2026-09-05 20:45:07', '2026-09-05 20:45:07'),
(806, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-05 20:56:34', '2026-09-05 20:56:34'),
(807, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-05 20:56:36', '2026-09-05 20:56:36'),
(808, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-05 21:03:03', '2026-09-05 21:03:03'),
(809, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-05 21:03:09', '2026-09-05 21:03:09'),
(810, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 134', NULL, NULL, '2026-09-05 21:03:18', '2026-09-05 21:03:18'),
(811, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-05 21:03:38', '2026-09-05 21:03:38'),
(812, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-05 21:03:42', '2026-09-05 21:03:42'),
(813, 4, 1, NULL, 'consultation_auto_completed', 'Consultation #VIS-20260905-3769 auto-completed', NULL, NULL, '2026-09-05 21:04:16', '2026-09-05 21:04:16'),
(814, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-05 21:07:45', '2026-09-05 21:07:45'),
(815, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-05 21:07:49', '2026-09-05 21:07:49'),
(816, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 135', NULL, NULL, '2026-09-05 21:07:55', '2026-09-05 21:07:55'),
(817, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-05 21:08:07', '2026-09-05 21:08:07'),
(818, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-05 21:08:11', '2026-09-05 21:08:11'),
(819, 4, 1, NULL, 'consultation_auto_completed', 'Consultation #VIS-20260905-7329 auto-completed', NULL, NULL, '2026-09-05 21:08:37', '2026-09-05 21:08:37'),
(820, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-05 21:13:36', '2026-09-05 21:13:36'),
(821, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-05 21:13:41', '2026-09-05 21:13:41'),
(822, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 136', NULL, NULL, '2026-09-05 21:13:47', '2026-09-05 21:13:47'),
(823, 4, 1, NULL, 'consultation_auto_completed', 'Consultation #VIS-20260905-5831 auto-completed', NULL, NULL, '2026-09-05 21:14:03', '2026-09-05 21:14:03'),
(824, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-05 21:15:44', '2026-09-05 21:15:44'),
(825, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-05 21:15:49', '2026-09-05 21:15:49'),
(826, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-05 21:22:48', '2026-09-05 21:22:48'),
(827, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general_green, Role: laboratory)', NULL, NULL, '2026-09-05 21:22:55', '2026-09-05 21:22:55'),
(828, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 137 - Complete Blood Count (CBC)', NULL, NULL, '2026-09-05 21:23:04', '2026-09-05 21:23:04'),
(829, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 138 - Echocardiogram', NULL, NULL, '2026-09-05 21:23:07', '2026-09-05 21:23:07'),
(830, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-05 21:23:26', '2026-09-05 21:23:26'),
(831, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-05 21:23:31', '2026-09-05 21:23:31'),
(832, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-05 21:24:53', '2026-09-05 21:24:53'),
(833, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-05 21:24:56', '2026-09-05 21:24:56'),
(834, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 139 - HIV Rapid Test', NULL, NULL, '2026-09-05 21:25:02', '2026-09-05 21:25:02'),
(835, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 140 - Helicobacter Pylori Test', NULL, NULL, '2026-09-05 21:25:05', '2026-09-05 21:25:05'),
(836, 4, 1, NULL, 'consultation_auto_completed', 'Consultation #VIS-20260905-0603 auto-completed', NULL, NULL, '2026-09-05 21:26:28', '2026-09-05 21:26:28'),
(837, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-05 21:30:42', '2026-09-05 21:30:42'),
(838, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-05 21:30:45', '2026-09-05 21:30:45'),
(839, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-05 22:03:37', '2026-09-05 22:03:37'),
(840, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-05 22:03:39', '2026-09-05 22:03:39'),
(841, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 141 - Complete Blood Count (CBC)', NULL, NULL, '2026-09-05 22:03:45', '2026-09-05 22:03:45'),
(842, 4, 1, NULL, 'consultation_auto_completed', 'Consultation #VIS-20260905-5145 auto-completed', NULL, NULL, '2026-09-05 22:04:09', '2026-09-05 22:04:09'),
(843, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-05 22:10:00', '2026-09-05 22:10:00'),
(844, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-05 22:10:34', '2026-09-05 22:10:34'),
(845, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-05 22:11:56', '2026-09-05 22:11:56'),
(846, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-05 22:11:59', '2026-09-05 22:11:59'),
(847, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 142 - Thyroid Function Test (TFT)', NULL, NULL, '2026-09-05 22:12:05', '2026-09-05 22:12:05'),
(848, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-05 22:13:32', '2026-09-05 22:13:32'),
(849, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general_green, Role: reception)', NULL, NULL, '2026-09-05 22:13:57', '2026-09-05 22:13:57'),
(850, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-05 22:14:15', '2026-09-05 22:14:15'),
(851, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-05 22:14:28', '2026-09-05 22:14:28'),
(852, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-05 22:15:06', '2026-09-05 22:15:06'),
(853, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-05 22:15:12', '2026-09-05 22:15:12'),
(854, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-06 01:11:53', '2026-09-06 01:11:53'),
(855, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-06 05:26:37', '2026-09-06 05:26:37'),
(856, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-06 06:00:28', '2026-09-06 06:00:28'),
(857, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-06 06:00:45', '2026-09-06 06:00:45'),
(858, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-06 06:03:05', '2026-09-06 06:03:05'),
(859, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-06 06:03:17', '2026-09-06 06:03:17'),
(860, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-06 06:03:40', '2026-09-06 06:03:40'),
(861, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-06 06:04:13', '2026-09-06 06:04:13'),
(862, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-06 06:04:18', '2026-09-06 06:04:18'),
(863, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 143 - Renal Function Test (RFT)', NULL, NULL, '2026-09-06 06:04:24', '2026-09-06 06:04:24'),
(864, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 144 - Liver Function Test (LFT)', NULL, NULL, '2026-09-06 06:04:26', '2026-09-06 06:04:26'),
(865, 4, 1, NULL, 'consultation_auto_completed', 'Consultation #VIS-20260906-6380 auto-completed', NULL, NULL, '2026-09-06 06:05:06', '2026-09-06 06:05:06'),
(866, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-06 06:24:21', '2026-09-06 06:24:21'),
(867, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-06 06:24:26', '2026-09-06 06:24:26'),
(868, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-06 06:26:37', '2026-09-06 06:26:37'),
(869, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-06 06:26:44', '2026-09-06 06:26:44'),
(870, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 146 - Dengue Test', NULL, NULL, '2026-09-06 06:26:54', '2026-09-06 06:26:54'),
(871, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 145 - HIV ELISA', NULL, NULL, '2026-09-06 06:26:57', '2026-09-06 06:26:57'),
(872, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 147 - Thyroid Function Test (TFT)', NULL, NULL, '2026-09-06 06:27:02', '2026-09-06 06:27:02'),
(873, 4, 1, NULL, 'consultation_auto_completed', 'Consultation #VIS-20260906-0053 auto-completed', NULL, NULL, '2026-09-06 06:28:14', '2026-09-06 06:28:14'),
(874, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-06 07:03:57', '2026-09-06 07:03:57'),
(875, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-06 07:04:00', '2026-09-06 07:04:00'),
(876, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-06 07:51:15', '2026-09-06 07:51:15'),
(877, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-06 07:51:25', '2026-09-06 07:51:25'),
(878, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-06 08:27:03', '2026-09-06 08:27:03'),
(879, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-06 08:27:07', '2026-09-06 08:27:07'),
(880, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-06 08:48:56', '2026-09-06 08:48:56'),
(881, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-06 08:50:18', '2026-09-06 08:50:18'),
(882, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-06 08:50:22', '2026-09-06 08:50:22'),
(883, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-06 08:52:54', '2026-09-06 08:52:54'),
(884, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-06 08:53:00', '2026-09-06 08:53:00'),
(885, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 148', NULL, NULL, '2026-09-06 08:53:11', '2026-09-06 08:53:11'),
(886, 4, 1, NULL, 'consultation_auto_completed', 'Consultation #VIS-20260906-5936 auto-completed', NULL, NULL, '2026-09-06 08:53:56', '2026-09-06 08:53:56'),
(887, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-06 09:07:50', '2026-09-06 09:07:50'),
(888, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-06 09:07:54', '2026-09-06 09:07:54'),
(889, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-06 11:22:01', '2026-09-06 11:22:01'),
(890, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-06 11:22:06', '2026-09-06 11:22:06'),
(891, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-06 11:22:43', '2026-09-06 11:22:43'),
(892, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-06 11:25:20', '2026-09-06 11:25:20'),
(893, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-06 11:25:37', '2026-09-06 11:25:37'),
(894, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-06 11:26:17', '2026-09-06 11:26:17'),
(895, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-06 11:26:24', '2026-09-06 11:26:24'),
(896, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-06 11:31:51', '2026-09-06 11:31:51'),
(897, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-06 11:31:55', '2026-09-06 11:31:55'),
(898, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-06 11:33:22', '2026-09-06 11:33:22'),
(899, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-06 11:33:28', '2026-09-06 11:33:28'),
(900, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-06 11:47:14', '2026-09-06 11:47:14'),
(901, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-06 11:47:22', '2026-09-06 11:47:22'),
(902, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-06 11:48:15', '2026-09-06 11:48:15'),
(903, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-06 11:48:19', '2026-09-06 11:48:19'),
(904, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-06 11:52:58', '2026-09-06 11:52:58'),
(905, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-06 11:53:02', '2026-09-06 11:53:02'),
(906, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-06 11:53:54', '2026-09-06 11:53:54'),
(907, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-06 11:53:59', '2026-09-06 11:53:59'),
(908, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-06 12:01:41', '2026-09-06 12:01:41'),
(909, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-06 12:01:43', '2026-09-06 12:01:43'),
(910, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-06 12:03:05', '2026-09-06 12:03:05'),
(911, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-06 12:03:16', '2026-09-06 12:03:16'),
(912, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-06 12:10:09', '2026-09-06 12:10:09'),
(913, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-06 12:10:12', '2026-09-06 12:10:12'),
(914, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 149 - Renal Function Test (RFT)', NULL, NULL, '2026-09-06 12:10:21', '2026-09-06 12:10:21'),
(915, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 150 - Pregnancy Test (Blood - Beta HCG)', NULL, NULL, '2026-09-06 12:10:23', '2026-09-06 12:10:23'),
(916, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-06 12:14:48', '2026-09-06 12:14:48'),
(917, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-06 12:14:51', '2026-09-06 12:14:51'),
(918, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-06 12:14:57', '2026-09-06 12:14:57'),
(919, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-06 12:15:00', '2026-09-06 12:15:00'),
(920, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-06 12:15:30', '2026-09-06 12:15:30'),
(921, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-06 12:15:37', '2026-09-06 12:15:37'),
(922, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-06 12:19:11', '2026-09-06 12:19:11'),
(923, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-06 12:19:14', '2026-09-06 12:19:14'),
(924, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-06 12:28:43', '2026-09-06 12:28:43'),
(925, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-06 12:28:49', '2026-09-06 12:28:49'),
(926, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-06 12:29:39', '2026-09-06 12:29:39'),
(927, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-06 12:29:43', '2026-09-06 12:29:43'),
(928, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-06 12:31:06', '2026-09-06 12:31:06'),
(929, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-06 12:31:10', '2026-09-06 12:31:10'),
(930, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-06 12:36:30', '2026-09-06 12:36:30'),
(931, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-06 12:36:35', '2026-09-06 12:36:35'),
(932, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-06 12:37:26', '2026-09-06 12:37:26'),
(933, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-06 12:37:29', '2026-09-06 12:37:29'),
(934, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 151 - HIV ELISA', NULL, NULL, '2026-09-06 12:37:36', '2026-09-06 12:37:36'),
(935, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-06 12:41:18', '2026-09-06 12:41:18'),
(936, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-06 12:41:25', '2026-09-06 12:41:25'),
(937, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-06 12:41:35', '2026-09-06 12:41:35'),
(938, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-06 12:41:39', '2026-09-06 12:41:39'),
(939, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-06 12:42:03', '2026-09-06 12:42:03'),
(940, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-06 12:42:06', '2026-09-06 12:42:06'),
(941, 4, 1, NULL, 'consultation_auto_completed', 'Consultation #VIS-20260906-4555 auto-completed', NULL, NULL, '2026-09-06 12:43:21', '2026-09-06 12:43:21'),
(942, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-06 12:52:50', '2026-09-06 12:52:50'),
(943, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-06 12:52:54', '2026-09-06 12:52:54'),
(944, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-06 12:53:47', '2026-09-06 12:53:47'),
(945, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-06 12:53:51', '2026-09-06 12:53:51'),
(946, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-06 12:56:24', '2026-09-06 12:56:24'),
(947, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-06 12:56:31', '2026-09-06 12:56:31'),
(948, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-06 13:03:04', '2026-09-06 13:03:04'),
(949, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-06 13:03:10', '2026-09-06 13:03:10'),
(950, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-06 13:38:30', '2026-09-06 13:38:30'),
(951, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-06 13:38:42', '2026-09-06 13:38:42'),
(952, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 153 - HIV ELISA', NULL, NULL, '2026-09-06 13:38:51', '2026-09-06 13:38:51'),
(953, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 154 - Dengue Test', NULL, NULL, '2026-09-06 13:38:54', '2026-09-06 13:38:54'),
(954, 4, 1, NULL, 'consultation_auto_completed', 'Consultation #VIS-20260906-2571 auto-completed', NULL, NULL, '2026-09-06 13:39:28', '2026-09-06 13:39:28'),
(955, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-06 14:07:45', '2026-09-06 14:07:45'),
(956, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-06 14:07:49', '2026-09-06 14:07:49'),
(957, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-06 14:10:20', '2026-09-06 14:10:20'),
(958, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-06 14:10:27', '2026-09-06 14:10:27'),
(959, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 155', NULL, NULL, '2026-09-06 14:10:36', '2026-09-06 14:10:36'),
(960, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-06 14:12:47', '2026-09-06 14:12:47'),
(961, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-06 14:13:02', '2026-09-06 14:13:02'),
(962, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-06 14:13:22', '2026-09-06 14:13:22'),
(963, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-06 14:13:27', '2026-09-06 14:13:27'),
(964, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-06 14:14:09', '2026-09-06 14:14:09'),
(965, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-06 14:14:14', '2026-09-06 14:14:14'),
(966, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-06 14:45:00', '2026-09-06 14:45:00'),
(967, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-06 14:45:10', '2026-09-06 14:45:10'),
(968, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-06 14:47:42', '2026-09-06 14:47:42'),
(969, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-06 14:47:46', '2026-09-06 14:47:46'),
(970, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 156 - Dengue Test', NULL, NULL, '2026-09-06 14:48:09', '2026-09-06 14:48:09'),
(971, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 157 - Pregnancy Test (Blood - Beta HCG)', NULL, NULL, '2026-09-06 14:48:14', '2026-09-06 14:48:14'),
(972, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-06 14:52:06', '2026-09-06 14:52:06'),
(973, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-06 14:52:10', '2026-09-06 14:52:10'),
(974, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-06 14:52:19', '2026-09-06 14:52:19'),
(975, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-06 14:52:24', '2026-09-06 14:52:24'),
(976, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-06 14:53:19', '2026-09-06 14:53:19'),
(977, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-06 14:53:24', '2026-09-06 14:53:24'),
(978, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-07 11:31:02', '2026-09-07 11:31:02'),
(979, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-07 11:31:16', '2026-09-07 11:31:16'),
(980, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-08 08:33:34', '2026-09-08 08:33:34'),
(981, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-08 08:36:05', '2026-09-08 08:36:05'),
(982, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-08 08:49:40', '2026-09-08 08:49:40'),
(983, 8, 1, NULL, 'user_login', 'User logged in: Mary John (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-08 08:50:08', '2026-09-08 08:50:08'),
(984, 8, 1, NULL, 'user_logout', 'User logged out: Mary John (Role: pharmacy)', NULL, NULL, '2026-09-08 09:10:10', '2026-09-08 09:10:10'),
(985, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-08 09:27:02', '2026-09-08 09:27:02'),
(986, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-08 09:27:20', '2026-09-08 09:27:20'),
(987, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-08 09:28:03', '2026-09-08 09:28:03'),
(988, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-08 09:28:08', '2026-09-08 09:28:08'),
(989, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-08 09:30:26', '2026-09-08 09:30:26'),
(990, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-08 09:30:31', '2026-09-08 09:30:31'),
(991, 8, 1, NULL, 'user_login', 'User logged in: Mary John (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-08 09:45:00', '2026-09-08 09:45:00'),
(992, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-08 09:54:54', '2026-09-08 09:54:54'),
(993, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-08 09:54:56', '2026-09-08 09:54:56'),
(994, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-08 12:07:51', '2026-09-08 12:07:51'),
(995, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-08 12:07:58', '2026-09-08 12:07:58'),
(996, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-08 12:08:44', '2026-09-08 12:08:44'),
(997, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-08 12:09:11', '2026-09-08 12:09:11'),
(998, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-08 13:05:57', '2026-09-08 13:05:57'),
(999, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-08 14:28:36', '2026-09-08 14:28:36'),
(1000, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-08 14:28:51', '2026-09-08 14:28:51'),
(1001, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-08 14:30:08', '2026-09-08 14:30:08'),
(1002, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-08 14:30:17', '2026-09-08 14:30:17'),
(1003, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-08 14:38:36', '2026-09-08 14:38:36'),
(1004, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-08 14:38:39', '2026-09-08 14:38:39'),
(1005, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-08 15:36:18', '2026-09-08 15:36:18'),
(1006, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-08 15:36:25', '2026-09-08 15:36:25'),
(1007, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-08 15:56:32', '2026-09-08 15:56:32'),
(1008, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-08 15:56:33', '2026-09-08 15:56:33'),
(1009, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-08 16:04:08', '2026-09-08 16:04:08'),
(1010, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-08 16:04:10', '2026-09-08 16:04:10'),
(1011, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-09 08:42:12', '2026-09-09 08:42:12'),
(1012, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-09 09:40:43', '2026-09-09 09:40:43'),
(1013, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-09 09:41:36', '2026-09-09 09:41:36'),
(1014, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-09 09:41:41', '2026-09-09 09:41:41'),
(1015, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-09 09:41:49', '2026-09-09 09:41:49'),
(1016, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-09 09:41:54', '2026-09-09 09:41:54'),
(1017, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-09 09:42:14', '2026-09-09 09:42:14'),
(1018, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-09 09:42:17', '2026-09-09 09:42:17'),
(1019, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-09 09:49:31', '2026-09-09 09:49:31'),
(1020, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-09 09:49:34', '2026-09-09 09:49:34'),
(1021, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-09 09:57:37', '2026-09-09 09:57:37'),
(1022, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-09 09:57:40', '2026-09-09 09:57:40'),
(1023, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-09 10:03:20', '2026-09-09 10:03:20');
INSERT INTO `activity_logs` (`id`, `user_id`, `branch_id`, `patient_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`, `updated_at`) VALUES
(1024, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-09 10:03:30', '2026-09-09 10:03:30'),
(1025, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-09 11:35:36', '2026-09-09 11:35:36'),
(1026, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-09 11:35:45', '2026-09-09 11:35:45'),
(1027, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-09 11:43:39', '2026-09-09 11:43:39'),
(1028, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-09 11:43:44', '2026-09-09 11:43:44'),
(1029, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-09 11:48:04', '2026-09-09 11:48:04'),
(1030, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-09 11:48:09', '2026-09-09 11:48:09'),
(1031, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-09 11:55:04', '2026-09-09 11:55:04'),
(1032, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-09 12:43:38', '2026-09-09 12:43:38'),
(1033, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-09 12:43:43', '2026-09-09 12:43:43'),
(1034, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-09 12:51:21', '2026-09-09 12:51:21'),
(1035, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-09 12:51:26', '2026-09-09 12:51:26'),
(1036, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-09 13:15:52', '2026-09-09 13:15:52'),
(1037, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-09 13:15:56', '2026-09-09 13:15:56'),
(1038, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-09 13:22:04', '2026-09-09 13:22:04'),
(1039, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-09 13:22:07', '2026-09-09 13:22:07'),
(1040, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-09 13:33:32', '2026-09-09 13:33:32'),
(1041, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-09 13:33:35', '2026-09-09 13:33:35'),
(1042, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-09 13:35:49', '2026-09-09 13:35:49'),
(1043, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-09 13:35:55', '2026-09-09 13:35:55'),
(1044, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-09 13:36:56', '2026-09-09 13:36:56'),
(1045, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-09 13:37:00', '2026-09-09 13:37:00'),
(1046, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-09 13:51:41', '2026-09-09 13:51:41'),
(1047, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-09 13:55:12', '2026-09-09 13:55:12'),
(1048, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-09 13:55:15', '2026-09-09 13:55:15'),
(1049, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-09 13:59:03', '2026-09-09 13:59:03'),
(1050, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-09 13:59:09', '2026-09-09 13:59:09'),
(1051, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-09 14:00:16', '2026-09-09 14:00:16'),
(1052, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-09 14:00:36', '2026-09-09 14:00:36'),
(1053, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 158 - Blood Glucose (Random)', NULL, NULL, '2026-09-09 14:00:43', '2026-09-09 14:00:43'),
(1054, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 159 - Dengue Test', NULL, NULL, '2026-09-09 14:00:46', '2026-09-09 14:00:46'),
(1055, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-09 14:01:04', '2026-09-09 14:01:04'),
(1056, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-09 14:01:08', '2026-09-09 14:01:08'),
(1057, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-09 14:23:23', '2026-09-09 14:23:23'),
(1058, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-09 14:23:29', '2026-09-09 14:23:29'),
(1059, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-09 14:24:36', '2026-09-09 14:24:36'),
(1060, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-09 14:24:40', '2026-09-09 14:24:40'),
(1061, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-09 14:26:16', '2026-09-09 14:26:16'),
(1062, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-09 14:26:19', '2026-09-09 14:26:19'),
(1063, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-09 14:32:04', '2026-09-09 14:32:04'),
(1064, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-09 14:32:08', '2026-09-09 14:32:08'),
(1065, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-09 14:32:31', '2026-09-09 14:32:31'),
(1066, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-09 14:32:35', '2026-09-09 14:32:35'),
(1067, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-09 14:40:17', '2026-09-09 14:40:17'),
(1068, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-09 14:40:23', '2026-09-09 14:40:23'),
(1069, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-09 14:40:40', '2026-09-09 14:40:40'),
(1070, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-09 14:40:47', '2026-09-09 14:40:47'),
(1071, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-09 15:07:47', '2026-09-09 15:07:47'),
(1072, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-09 15:07:53', '2026-09-09 15:07:53'),
(1073, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-09 15:08:15', '2026-09-09 15:08:15'),
(1074, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-09 15:08:20', '2026-09-09 15:08:20'),
(1075, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-09 15:13:46', '2026-09-09 15:13:46'),
(1076, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-09 15:13:54', '2026-09-09 15:13:54'),
(1077, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-09 15:17:43', '2026-09-09 15:17:43'),
(1078, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-09 15:17:46', '2026-09-09 15:17:46'),
(1079, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-09 15:21:29', '2026-09-09 15:21:29'),
(1080, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-09 15:21:33', '2026-09-09 15:21:33'),
(1081, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-09 15:21:45', '2026-09-09 15:21:45'),
(1082, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-09 15:21:48', '2026-09-09 15:21:48'),
(1083, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-09 15:46:27', '2026-09-09 15:46:27'),
(1084, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-09 15:46:29', '2026-09-09 15:46:29'),
(1085, 4, 1, NULL, 'doctor_status_changed', 'Dr. Dr.ERICK JOHN changed status to: offline', NULL, NULL, '2026-09-09 15:46:46', '2026-09-09 15:46:46'),
(1086, 4, 1, NULL, 'doctor_status_changed', 'Dr. Dr.ERICK JOHN changed status to: online', NULL, NULL, '2026-09-09 15:46:47', '2026-09-09 15:46:47'),
(1087, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-09 16:30:34', '2026-09-09 16:30:34'),
(1088, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-09 16:30:38', '2026-09-09 16:30:38'),
(1089, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-09 17:20:13', '2026-09-09 17:20:13'),
(1090, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-09 17:20:17', '2026-09-09 17:20:17'),
(1091, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-09 17:26:45', '2026-09-09 17:26:45'),
(1092, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-09 17:26:48', '2026-09-09 17:26:48'),
(1093, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-09 17:43:24', '2026-09-09 17:43:24'),
(1094, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-09 17:43:29', '2026-09-09 17:43:29'),
(1095, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-09 18:29:03', '2026-09-09 18:29:03'),
(1096, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-09 18:29:14', '2026-09-09 18:29:14'),
(1097, 13, 1, NULL, 'lab_test_started', 'Started lab test ID: 163 - KICHOCHO', NULL, NULL, '2026-09-09 18:29:22', '2026-09-09 18:29:22'),
(1098, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO (Role: laboratory)', NULL, NULL, '2026-09-09 18:29:37', '2026-09-09 18:29:37'),
(1099, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-09 18:29:40', '2026-09-09 18:29:40');

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `visit_id` int(11) DEFAULT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `assigned_at` timestamp NULL DEFAULT NULL,
  `appointment_date` datetime NOT NULL,
  `purpose` text DEFAULT NULL,
  `visit_type` enum('new','follow-up','emergency') DEFAULT 'new',
  `status` enum('scheduled','confirmed','completed','cancelled') DEFAULT 'scheduled',
  `notes` text DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bills`
--

CREATE TABLE `bills` (
  `id` int(11) NOT NULL,
  `bill_number` varchar(50) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `visit_id` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `subtotal` decimal(12,2) DEFAULT 0.00,
  `discount_percent` decimal(5,2) DEFAULT 0.00,
  `discount_amount` decimal(12,2) DEFAULT 0.00,
  `pharmacy_discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cashier_discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `paid_amount` decimal(12,2) DEFAULT 0.00,
  `balance` decimal(12,2) DEFAULT 0.00,
  `status` enum('pending','partial','paid','cancelled') DEFAULT 'pending',
  `payment_method` enum('cash','m-pesa','airtel_money','tigo_pesa','halopesa','bank','card','insurance','other') DEFAULT 'cash',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `bills`
--
DELIMITER $$
CREATE TRIGGER `before_insert_bills_discount` BEFORE INSERT ON `bills` FOR EACH ROW BEGIN
    SET NEW.total_discount = NEW.pharmacy_discount + NEW.cashier_discount;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_update_bills_discount` BEFORE UPDATE ON `bills` FOR EACH ROW BEGIN
    SET NEW.total_discount = NEW.pharmacy_discount + NEW.cashier_discount;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `bill_items`
--

CREATE TABLE `bill_items` (
  `id` int(11) NOT NULL,
  `bill_id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `item_type` enum('registration','consultation','lab_test','medication','procedure','equipment','tool','other') NOT NULL DEFAULT 'other',
  `item_id` int(11) DEFAULT NULL,
  `item_name` varchar(255) NOT NULL,
  `item_code` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `unit_price` decimal(12,2) NOT NULL,
  `total_price` decimal(12,2) NOT NULL,
  `discount_amount` decimal(12,2) DEFAULT 0.00,
  `tax_amount` decimal(12,2) DEFAULT 0.00,
  `final_price` decimal(12,2) DEFAULT 0.00,
  `reference_id` int(11) DEFAULT NULL,
  `reference_type` enum('prescription','lab_request','procedure','otc_sale','inventory','equipment') DEFAULT NULL,
  `status` enum('pending','paid','cancelled','refunded') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `location` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`id`, `name`, `location`, `phone`, `email`, `logo`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Dodoma', 'Dodoma City, Tanzania', '+255 700 000 001', 'dodoma@braick.com', NULL, 'active', '2026-08-23 12:26:09', '2026-08-23 12:26:09'),
(2, 'Arusha', 'Arusha City, Tanzania', '+255 700 000 002', 'arusha@braick.com', NULL, 'active', '2026-08-23 12:26:09', '2026-08-23 12:26:09'),
(3, 'Dar es Salaam', 'Dar es Salaam, Tanzania', '+255 700 000 003', 'dar@braick.com', NULL, 'active', '2026-08-23 12:26:09', '2026-08-23 12:26:09');

-- --------------------------------------------------------

--
-- Table structure for table `diseases`
--

CREATE TABLE `diseases` (
  `id` int(11) NOT NULL,
  `disease_code` varchar(50) DEFAULT NULL,
  `disease_name` varchar(255) NOT NULL,
  `icd_code` varchar(20) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `treatment` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `diseases`
--

INSERT INTO `diseases` (`id`, `disease_code`, `disease_name`, `icd_code`, `category`, `description`, `treatment`, `is_active`, `created_by`, `branch_id`, `created_at`, `updated_at`) VALUES
(20, 'D-SAFURA-833', 'SAFURA', NULL, NULL, NULL, NULL, 1, NULL, 1, '2026-09-06 12:39:21', '2026-09-06 12:39:21'),
(21, 'D-KISUKA-570', 'KISUKARI', NULL, NULL, NULL, NULL, 1, NULL, 1, '2026-09-06 14:12:08', '2026-09-06 14:12:08'),
(22, '13BRT9_BTC8', 'ANTENCIK 104', NULL, NULL, NULL, '-MAZIWA NA MATUNDA KWA WINGI\r\n-VYAKULA VYA PROTEINS', 1, NULL, 1, '2026-09-06 14:50:38', '2026-09-06 14:51:05');

-- --------------------------------------------------------

--
-- Table structure for table `employee_departments`
--

CREATE TABLE `employee_departments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee_departments`
--

INSERT INTO `employee_departments` (`id`, `user_id`, `department_id`, `assigned_by`, `assigned_at`) VALUES
(7, 43, 4, 1, '2026-09-02 21:15:20'),
(8, 43, 5, 1, '2026-09-02 21:15:20'),
(9, 43, 1, 1, '2026-09-02 21:15:20');

-- --------------------------------------------------------

--
-- Table structure for table `employee_roles`
--

CREATE TABLE `employee_roles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee_roles`
--

INSERT INTO `employee_roles` (`id`, `user_id`, `role_name`, `assigned_by`, `assigned_at`) VALUES
(5, 43, 'doctor', 1, '2026-09-02 21:15:20'),
(6, 43, 'reception', 1, '2026-09-02 21:15:20'),
(7, 13, 'laboratory', 1, '2026-09-02 23:12:10'),
(8, 44, 'reception', 1, '2026-09-03 01:31:31');

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `expense_number` varchar(50) NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','m-pesa','airtel_money','tigo_pesa','bank','card','other') DEFAULT 'cash',
  `payment_date` date NOT NULL,
  `status` enum('pending','paid','cancelled') DEFAULT 'paid',
  `receipt_number` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `expense_number`, `category`, `description`, `amount`, `payment_method`, `payment_date`, `status`, `receipt_number`, `notes`, `created_by`, `branch_id`, `created_at`, `updated_at`) VALUES
(2, 'EXP-20260903-2839', 'Rent', 'chakula', 60000.00, 'cash', '2026-09-03', 'paid', '', '', 10, 1, '2026-09-03 20:54:24', '2026-09-03 20:54:24');

-- --------------------------------------------------------

--
-- Table structure for table `external_sick_sheets`
--

CREATE TABLE `external_sick_sheets` (
  `id` int(11) NOT NULL,
  `document_number` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `patient_id` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `blood_group` varchar(5) DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `symptoms` text DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `treatment` text DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `temperature` decimal(4,1) DEFAULT NULL,
  `bp_systolic` int(11) DEFAULT NULL,
  `bp_diastolic` int(11) DEFAULT NULL,
  `pulse_rate` int(11) DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `height` decimal(5,2) DEFAULT NULL,
  `lab_results` text DEFAULT NULL,
  `medications` text DEFAULT NULL,
  `procedures` text DEFAULT NULL,
  `sick_days` int(11) NOT NULL DEFAULT 0,
  `sick_from` date DEFAULT NULL,
  `sick_to` date DEFAULT NULL,
  `sick_reason` text DEFAULT NULL,
  `sick_restrictions` text DEFAULT NULL,
  `doctor_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('active','archived') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `external_sick_sheets`
--

INSERT INTO `external_sick_sheets` (`id`, `document_number`, `full_name`, `patient_id`, `phone`, `gender`, `date_of_birth`, `address`, `blood_group`, `allergies`, `symptoms`, `diagnosis`, `treatment`, `instructions`, `temperature`, `bp_systolic`, `bp_diastolic`, `pulse_rate`, `weight`, `height`, `lab_results`, `medications`, `procedures`, `sick_days`, `sick_from`, `sick_to`, `sick_reason`, `sick_restrictions`, `doctor_id`, `branch_id`, `file_name`, `file_path`, `file_type`, `created_at`, `updated_at`, `status`) VALUES
(1, 'SS-20260824-3276', 'KELVIN', 'EXT-2026-3526', '0623693303', 'Male', '2001-09-10', 'TANZANIA', 'AB-', '', 'DIZZ', 'MALARIA', '', '', 30.0, 129, 78, 70, 68.00, 172.80, '', '', '', 3, '2026-08-24', '2026-08-27', 'Medical condition requiring rest', 'No heavy lifting, complete rest', 4, 1, 'sick_sheet_SS-20260824-3276.html', '/dispensary_system/frontend/assets/uploads/sick_sheets/sick_sheet_SS-20260824-3276.html', 'text/html', '2026-08-24 12:20:29', '2026-08-24 12:20:29', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `lab_result_templates`
--

CREATE TABLE `lab_result_templates` (
  `id` int(11) NOT NULL,
  `template_name` varchar(100) NOT NULL,
  `test_type` varchar(50) NOT NULL,
  `category` enum('ultrasound','blood_test','urinalysis','radiology','microbiology','other') DEFAULT 'other',
  `template_html` longtext NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lab_result_templates`
--

INSERT INTO `lab_result_templates` (`id`, `template_name`, `test_type`, `category`, `template_html`, `is_active`, `created_at`, `updated_at`) VALUES
(6, 'Obstetric Ultrasound (Twin - 2/3 Trimester)', 'Obstetric Ultrasound - Twin', 'ultrasound', '<div class=\"ultrasound-report\">\r\n    <div class=\"report-header\">\r\n        <div style=\"display:flex;align-items:center;justify-content:center;gap:15px;margin-bottom:10px;\">\r\n            <img src=\"/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png\" alt=\"Braick Dispensary\" style=\"height:60px;width:auto;max-height:60px;\" onerror=\"this.style.display=none\">\r\n            <div>\r\n                <h2 style=\"color:#0B5ED7;font-size:22px;margin:0;\">BRAICK DISPENSARY</h2>\r\n                <p style=\"font-size:12px;color:#666;margin:0;\">Quality Healthcare Services</p>\r\n            </div>\r\n        </div>\r\n        <h3 style=\"font-size:16px;color:#333;margin:0;\">ULTRASOUND REPORT – ABDOMEN AND PELVIS</h3>\r\n        <p style=\"font-size:11px;color:#888;margin:2px 0 0 0;\">Twin Pregnancy – 2nd/3rd Trimester</p>\r\n    </div>\r\n    \r\n    <div class=\"patient-info\" style=\"margin:10px 0;padding:8px 12px;background:#f8f9fa;border-radius:6px;border-left:4px solid #0B5ED7;\">\r\n        <div style=\"display:grid;grid-template-columns:1fr 1fr;gap:4px 20px;\">\r\n            <p style=\"margin:2px 0;\"><strong>Patient Name:</strong> {patient_name}</p>\r\n            <p style=\"margin:2px 0;\"><strong>Age/Sex:</strong> {age} yrs / {gender}</p>\r\n            <p style=\"margin:2px 0;\"><strong>Date of Exam:</strong> {exam_date}</p>\r\n            <p style=\"margin:2px 0;\"><strong>Patient ID:</strong> {patient_id}</p>\r\n            <p style=\"margin:2px 0;\"><strong>Report Date:</strong> {report_date}</p>\r\n        </div>\r\n    </div>\r\n    \r\n    <div class=\"findings\" style=\"margin:10px 0;\">\r\n        <h4 style=\"color:#0B5ED7;border-bottom:2px solid #0B5ED7;padding-bottom:4px;margin-bottom:8px;\">FINDINGS</h4>\r\n        <table style=\"width:100%;border-collapse:collapse;font-size:14px;\">\r\n            <tbody>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;width:35%;font-weight:600;\">Liver</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"liver\" placeholder=\"e.g. Appeared normal in size, shape, homogeneous echo pattern\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">Gallbladder</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"gallbladder\" placeholder=\"e.g. Appears normal, well distended, no stones\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">Pancreas</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"pancreas\" placeholder=\"e.g. Appeared normal in size and shape\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">Spleen</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"spleen\" placeholder=\"e.g. Appeared normal in size, shape and echotexture\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">Peritoneum</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"peritoneum\" placeholder=\"e.g. No free fluid noted\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">Kidneys</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"kidneys\" placeholder=\"e.g. Both kidneys normal\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">Urinary Bladder</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"bladder\" placeholder=\"e.g. Appears normal, well-distended\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">Uterus</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"uterus\" placeholder=\"e.g. Appears normal in size and homogeneous echo pattern\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">Right Ovary</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"right_ovary\" placeholder=\"e.g. Appears normal in size and appearance\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">Left Ovary</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"left_ovary\" placeholder=\"e.g. Appears normal in size and appearance\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">Pouch of Douglas</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"pouch_douglas\" placeholder=\"e.g. Free fluid seen\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n            </tbody>\r\n        </table>\r\n    </div>\r\n    \r\n    <div class=\"conclusion\" style=\"margin:10px 0;\">\r\n        <h4 style=\"color:#0B5ED7;border-bottom:2px solid #0B5ED7;padding-bottom:4px;margin-bottom:8px;\">IMPRESSION</h4>\r\n        <textarea class=\"form-control conclusion-field\" rows=\"2\" placeholder=\"Enter impression...\" style=\"width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;\"></textarea>\r\n    </div>\r\n    \r\n    <div class=\"report-footer\" style=\"margin-top:15px;padding-top:10px;border-top:2px solid #0B5ED7;\">\r\n        <div style=\"display:flex;justify-content:space-between;align-items:center;width:100%;flex-wrap:wrap;\">\r\n            <div>\r\n                <span>Technician: <input type=\"text\" class=\"form-control\" style=\"display:inline-block;width:auto;border:none;border-bottom:1px solid #ddd;padding:0 8px;\" placeholder=\"Technician Name\"></span>\r\n                <span style=\"margin-left:20px;\">Date: {report_date}</span>\r\n            </div>\r\n            <div style=\"text-align:right;padding:8px 16px;border:2px solid #0B5ED7;border-radius:8px;background:#f0f7ff;min-width:150px;\">\r\n                <div style=\"font-size:10px;color:#666;text-transform:uppercase;letter-spacing:1px;font-weight:bold;\">Official Stamp</div>\r\n                <div style=\"font-size:14px;font-weight:bold;color:#0B5ED7;margin-top:4px;\">BRAICK DISPENSARY</div>\r\n                <div style=\"font-size:10px;color:#888;border-top:1px dashed #ccc;padding-top:4px;margin-top:4px;\">\r\n                    <span>Approved By: _________________</span>\r\n                </div>\r\n                <div style=\"font-size:9px;color:#999;margin-top:2px;\">Date: {report_date}</div>\r\n            </div>\r\n        </div>\r\n    </div>\r\n</div>', 1, '2026-08-23 15:54:44', '2026-09-05 09:33:10'),
(7, 'Obstetric Ultrasound (Single - 2/3 Trimester)', 'Obstetric Ultrasound - Single', 'ultrasound', '<div class=\"ultrasound-report\">\r\n    <div class=\"report-header\">\r\n        <div style=\"display:flex;align-items:center;justify-content:center;gap:15px;margin-bottom:10px;\">\r\n            <img src=\"/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png\" alt=\"Braick Dispensary\" style=\"height:60px;width:auto;max-height:60px;\" onerror=\"this.style.display=none\">\r\n            <div>\r\n                <h2 style=\"color:#0B5ED7;font-size:22px;margin:0;\">BRAICK DISPENSARY</h2>\r\n                <p style=\"font-size:12px;color:#666;margin:0;\">Quality Healthcare Services</p>\r\n            </div>\r\n        </div>\r\n        <h3 style=\"font-size:16px;color:#333;margin:0;\">OBSTETRIC ULTRASOUND REPORT</h3>\r\n        <p style=\"font-size:11px;color:#888;margin:2px 0 0 0;\">Single Pregnancy – 2nd/3rd Trimester</p>\r\n    </div>\r\n    \r\n    <div class=\"patient-info\" style=\"margin:10px 0;padding:8px 12px;background:#f8f9fa;border-radius:6px;border-left:4px solid #0B5ED7;\">\r\n        <div style=\"display:grid;grid-template-columns:1fr 1fr;gap:4px 20px;\">\r\n            <p style=\"margin:2px 0;\"><strong>Patient Name:</strong> {patient_name}</p>\r\n            <p style=\"margin:2px 0;\"><strong>Age/Sex:</strong> {age} yrs / {gender}</p>\r\n            <p style=\"margin:2px 0;\"><strong>Date of Exam:</strong> {exam_date}</p>\r\n            <p style=\"margin:2px 0;\"><strong>Patient ID:</strong> {patient_id}</p>\r\n            <p style=\"margin:2px 0;\"><strong>Report Date:</strong> {report_date}</p>\r\n        </div>\r\n    </div>\r\n    \r\n    <div class=\"findings\" style=\"margin:10px 0;\">\r\n        <h4 style=\"color:#0B5ED7;border-bottom:2px solid #0B5ED7;padding-bottom:4px;margin-bottom:8px;\">FINDINGS</h4>\r\n        <table style=\"width:100%;border-collapse:collapse;font-size:14px;\">\r\n            <tbody>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;width:35%;font-weight:600;\">Presentation and Lie</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"presentation\" placeholder=\"e.g. single viable intrauterine fetus, in cephalic presentation\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">Placenta</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"placenta\" placeholder=\"e.g. placenta is posterior, placenta calcification\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">Fetal Activity</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"fetal_activity\" placeholder=\"e.g. seen\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">Amniotic Fluid</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"amniotic_fluid\" placeholder=\"e.g. adequate\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">Anatomical Structures</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><textarea class=\"form-control placeholder-field\" data-placeholder=\"anatomical_structures\" rows=\"2\" placeholder=\"Describe anatomical structures...\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></textarea></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">Maternal Kidney</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"maternal_kidney\" placeholder=\"e.g. appeared normal\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n            </tbody>\r\n        </table>\r\n    </div>\r\n    \r\n    <div class=\"biometry\" style=\"margin:10px 0;\">\r\n        <h4 style=\"color:#0B5ED7;border-bottom:2px solid #0B5ED7;padding-bottom:4px;margin-bottom:8px;\">BIOMETRY</h4>\r\n        <table style=\"width:100%;border-collapse:collapse;font-size:14px;\">\r\n            <tbody>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;width:35%;font-weight:600;\">BPD</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control table-field\" placeholder=\"mm\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">HC</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control table-field\" placeholder=\"mm\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">AC</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control table-field\" placeholder=\"mm\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">FL</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control table-field\" placeholder=\"mm\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">GA</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control table-field\" placeholder=\"e.g. 39W+3D\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">EDD</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control table-field\" placeholder=\"DD/MM/YYYY\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n            </tbody>\r\n        </table>\r\n    </div>\r\n    \r\n    <div class=\"conclusion\" style=\"margin:10px 0;\">\r\n        <h4 style=\"color:#0B5ED7;border-bottom:2px solid #0B5ED7;padding-bottom:4px;margin-bottom:8px;\">CONCLUSION</h4>\r\n        <textarea class=\"form-control conclusion-field\" rows=\"2\" placeholder=\"Enter conclusion...\" style=\"width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;\"></textarea>\r\n    </div>\r\n    \r\n    <div class=\"report-footer\" style=\"margin-top:15px;padding-top:10px;border-top:2px solid #0B5ED7;\">\r\n        <div style=\"display:flex;justify-content:space-between;align-items:center;width:100%;flex-wrap:wrap;\">\r\n            <div>\r\n                <span>Technician: <input type=\"text\" class=\"form-control\" style=\"display:inline-block;width:auto;border:none;border-bottom:1px solid #ddd;padding:0 8px;\" placeholder=\"Technician Name\"></span>\r\n                <span style=\"margin-left:20px;\">Date: {report_date}</span>\r\n            </div>\r\n            <div style=\"text-align:right;padding:8px 16px;border:2px solid #0B5ED7;border-radius:8px;background:#f0f7ff;min-width:150px;\">\r\n                <div style=\"font-size:10px;color:#666;text-transform:uppercase;letter-spacing:1px;font-weight:bold;\">Official Stamp</div>\r\n                <div style=\"font-size:14px;font-weight:bold;color:#0B5ED7;margin-top:4px;\">BRAICK DISPENSARY</div>\r\n                <div style=\"font-size:10px;color:#888;border-top:1px dashed #ccc;padding-top:4px;margin-top:4px;\">\r\n                    <span>Approved By: _________________</span>\r\n                </div>\r\n                <div style=\"font-size:9px;color:#999;margin-top:2px;\">Date: {report_date}</div>\r\n            </div>\r\n        </div>\r\n    </div>\r\n</div>', 1, '2026-08-23 15:54:44', '2026-09-05 09:33:10'),
(8, 'Obstetric Ultrasound (Early Pregnancy)', 'Obstetric Ultrasound - Early', 'ultrasound', '<div class=\"ultrasound-report\">\r\n    <div class=\"report-header\">\r\n        <div style=\"display:flex;align-items:center;justify-content:center;gap:15px;margin-bottom:10px;\">\r\n            <img src=\"/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png\" alt=\"Braick Dispensary\" style=\"height:60px;width:auto;max-height:60px;\" onerror=\"this.style.display=none\">\r\n            <div>\r\n                <h2 style=\"color:#0B5ED7;font-size:22px;margin:0;\">BRAICK DISPENSARY</h2>\r\n                <p style=\"font-size:12px;color:#666;margin:0;\">Quality Healthcare Services</p>\r\n            </div>\r\n        </div>\r\n        <h3 style=\"font-size:16px;color:#333;margin:0;\">OBSTETRIC ULTRASOUND REPORT</h3>\r\n        <p style=\"font-size:11px;color:#888;margin:2px 0 0 0;\">Early Pregnancy Scan</p>\r\n    </div>\r\n    \r\n    <div class=\"patient-info\" style=\"margin:10px 0;padding:8px 12px;background:#f8f9fa;border-radius:6px;border-left:4px solid #0B5ED7;\">\r\n        <div style=\"display:grid;grid-template-columns:1fr 1fr;gap:4px 20px;\">\r\n            <p style=\"margin:2px 0;\"><strong>Patient Name:</strong> {patient_name}</p>\r\n            <p style=\"margin:2px 0;\"><strong>Age/Sex:</strong> {age} yrs / {gender}</p>\r\n            <p style=\"margin:2px 0;\"><strong>Date of Exam:</strong> {exam_date}</p>\r\n            <p style=\"margin:2px 0;\"><strong>Patient ID:</strong> {patient_id}</p>\r\n            <p style=\"margin:2px 0;\"><strong>Report Date:</strong> {report_date}</p>\r\n        </div>\r\n    </div>\r\n    \r\n    <div class=\"findings\" style=\"margin:10px 0;\">\r\n        <h4 style=\"color:#0B5ED7;border-bottom:2px solid #0B5ED7;padding-bottom:4px;margin-bottom:8px;\">FINDINGS</h4>\r\n        <table style=\"width:100%;border-collapse:collapse;font-size:14px;\">\r\n            <tbody>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;width:35%;font-weight:600;\">Embryo</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"embryo\" placeholder=\"e.g. single viable intrauterine embryo\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">CRL (Crown Rump Length)</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"crl\" placeholder=\"e.g. 31.57mm\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">Gestational Age (GA)</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"ga\" placeholder=\"e.g. 10W+2D\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">Fetal Pole</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"fetal_pole\" placeholder=\"e.g. seen\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">Yolk Sac</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"yolk_sac\" placeholder=\"e.g. seen\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">Myometrium</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"myometrium\" placeholder=\"e.g. no myometrial masses seen\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">Cervix</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"cervix\" placeholder=\"e.g. normal and closed\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">Adnexal Areas</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"adnexa\" placeholder=\"e.g. looked normal\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">Pouch of Douglas</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"pouch_douglas\" placeholder=\"e.g. no fluid seen\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">Maternal Organs</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"maternal_organs\" placeholder=\"e.g. normal\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n            </tbody>\r\n        </table>\r\n    </div>\r\n    \r\n    <div class=\"conclusion\" style=\"margin:10px 0;\">\r\n        <h4 style=\"color:#0B5ED7;border-bottom:2px solid #0B5ED7;padding-bottom:4px;margin-bottom:8px;\">CONCLUSION</h4>\r\n        <textarea class=\"form-control conclusion-field\" rows=\"2\" placeholder=\"Enter conclusion...\" style=\"width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;\"></textarea>\r\n    </div>\r\n    \r\n    <div class=\"report-footer\" style=\"margin-top:15px;padding-top:10px;border-top:2px solid #0B5ED7;\">\r\n        <div style=\"display:flex;justify-content:space-between;align-items:center;width:100%;flex-wrap:wrap;\">\r\n            <div>\r\n                <span>Technician: <input type=\"text\" class=\"form-control\" style=\"display:inline-block;width:auto;border:none;border-bottom:1px solid #ddd;padding:0 8px;\" placeholder=\"Technician Name\"></span>\r\n                <span style=\"margin-left:20px;\">Date: {report_date}</span>\r\n            </div>\r\n            <div style=\"text-align:right;padding:8px 16px;border:2px solid #0B5ED7;border-radius:8px;background:#f0f7ff;min-width:150px;\">\r\n                <div style=\"font-size:10px;color:#666;text-transform:uppercase;letter-spacing:1px;font-weight:bold;\">Official Stamp</div>\r\n                <div style=\"font-size:14px;font-weight:bold;color:#0B5ED7;margin-top:4px;\">BRAICK DISPENSARY</div>\r\n                <div style=\"font-size:10px;color:#888;border-top:1px dashed #ccc;padding-top:4px;margin-top:4px;\">\r\n                    <span>Approved By: _________________</span>\r\n                </div>\r\n                <div style=\"font-size:9px;color:#999;margin-top:2px;\">Date: {report_date}</div>\r\n            </div>\r\n        </div>\r\n    </div>\r\n</div>', 1, '2026-08-23 15:54:44', '2026-09-05 09:33:10'),
(9, 'Abdominal Ultrasound (Male)', 'Abdominal Ultrasound - Male', 'ultrasound', '<div class=\"ultrasound-report\">\r\n    <div class=\"report-header\">\r\n        <div style=\"display:flex;align-items:center;justify-content:center;gap:15px;margin-bottom:10px;\">\r\n            <img src=\"/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png\" alt=\"Braick Dispensary\" style=\"height:60px;width:auto;max-height:60px;\" onerror=\"this.style.display=none\">\r\n            <div>\r\n                <h2 style=\"color:#0B5ED7;font-size:22px;margin:0;\">BRAICK DISPENSARY</h2>\r\n                <p style=\"font-size:12px;color:#666;margin:0;\">Quality Healthcare Services</p>\r\n            </div>\r\n        </div>\r\n        <h3 style=\"font-size:16px;color:#333;margin:0;\">ULTRASOUND REPORT – ABDOMEN AND PELVIS</h3>\r\n        <p style=\"font-size:11px;color:#888;margin:2px 0 0 0;\">Male Abdomen</p>\r\n    </div>\r\n    \r\n    <div class=\"patient-info\" style=\"margin:10px 0;padding:8px 12px;background:#f8f9fa;border-radius:6px;border-left:4px solid #0B5ED7;\">\r\n        <div style=\"display:grid;grid-template-columns:1fr 1fr;gap:4px 20px;\">\r\n            <p style=\"margin:2px 0;\"><strong>Patient Name:</strong> {patient_name}</p>\r\n            <p style=\"margin:2px 0;\"><strong>Age/Sex:</strong> {age} yrs / {gender}</p>\r\n            <p style=\"margin:2px 0;\"><strong>Date of Exam:</strong> {exam_date}</p>\r\n            <p style=\"margin:2px 0;\"><strong>Patient ID:</strong> {patient_id}</p>\r\n            <p style=\"margin:2px 0;\"><strong>Report Date:</strong> {report_date}</p>\r\n        </div>\r\n    </div>\r\n    \r\n    <div class=\"findings\" style=\"margin:10px 0;\">\r\n        <h4 style=\"color:#0B5ED7;border-bottom:2px solid #0B5ED7;padding-bottom:4px;margin-bottom:8px;\">FINDINGS</h4>\r\n        <table style=\"width:100%;border-collapse:collapse;font-size:14px;\">\r\n            <tbody>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;width:35%;font-weight:600;\">Liver</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"liver\" placeholder=\"e.g. Appeared normal in size, shape, homogeneous echo pattern\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">Gallbladder</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"gallbladder\" placeholder=\"e.g. Appears normal, well distended, no stones\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">Pancreas</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"pancreas\" placeholder=\"e.g. Appeared normal in size and shape\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">Spleen</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"spleen\" placeholder=\"e.g. Appeared normal in size, shape and echotexture\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">Peritoneum</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"peritoneum\" placeholder=\"e.g. No free fluid noted\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">Kidneys</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"kidneys\" placeholder=\"e.g. Both kidneys normal\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">Urinary Bladder</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"bladder\" placeholder=\"e.g. Appears normal, well-distended\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">Prostate</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"prostate\" placeholder=\"e.g. Appears normal in size, shape and echotexture\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n            </tbody>\r\n        </table>\r\n    </div>\r\n    \r\n    <div class=\"conclusion\" style=\"margin:10px 0;\">\r\n        <h4 style=\"color:#0B5ED7;border-bottom:2px solid #0B5ED7;padding-bottom:4px;margin-bottom:8px;\">IMPRESSION/CONCLUSION</h4>\r\n        <textarea class=\"form-control conclusion-field\" rows=\"2\" placeholder=\"Enter impression...\" style=\"width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;\"></textarea>\r\n    </div>\r\n    \r\n    <div class=\"report-footer\" style=\"margin-top:15px;padding-top:10px;border-top:2px solid #0B5ED7;\">\r\n        <div style=\"display:flex;justify-content:space-between;align-items:center;width:100%;flex-wrap:wrap;\">\r\n            <div>\r\n                <span>Technician: <input type=\"text\" class=\"form-control\" style=\"display:inline-block;width:auto;border:none;border-bottom:1px solid #ddd;padding:0 8px;\" placeholder=\"Technician Name\"></span>\r\n                <span style=\"margin-left:20px;\">Date: {report_date}</span>\r\n            </div>\r\n            <div style=\"text-align:right;padding:8px 16px;border:2px solid #0B5ED7;border-radius:8px;background:#f0f7ff;min-width:150px;\">\r\n                <div style=\"font-size:10px;color:#666;text-transform:uppercase;letter-spacing:1px;font-weight:bold;\">Official Stamp</div>\r\n                <div style=\"font-size:14px;font-weight:bold;color:#0B5ED7;margin-top:4px;\">BRAICK DISPENSARY</div>\r\n                <div style=\"font-size:10px;color:#888;border-top:1px dashed #ccc;padding-top:4px;margin-top:4px;\">\r\n                    <span>Approved By: _________________</span>\r\n                </div>\r\n                <div style=\"font-size:9px;color:#999;margin-top:2px;\">Date: {report_date}</div>\r\n            </div>\r\n        </div>\r\n    </div>\r\n</div>', 1, '2026-08-23 15:54:44', '2026-09-05 09:33:10'),
(10, 'Abdominal Ultrasound (Female)', 'Abdominal Ultrasound - Female', 'ultrasound', '<div class=\"ultrasound-report\">\r\n    <div class=\"report-header\">\r\n        <div style=\"display:flex;align-items:center;justify-content:center;gap:15px;margin-bottom:10px;\">\r\n            <img src=\"/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png\" alt=\"Braick Dispensary\" style=\"height:60px;width:auto;max-height:60px;\" onerror=\"this.style.display=none\">\r\n            <div>\r\n                <h2 style=\"color:#0B5ED7;font-size:22px;margin:0;\">BRAICK DISPENSARY</h2>\r\n                <p style=\"font-size:12px;color:#666;margin:0;\">Quality Healthcare Services</p>\r\n            </div>\r\n        </div>\r\n        <h3 style=\"font-size:16px;color:#333;margin:0;\">ULTRASOUND REPORT – ABDOMEN AND PELVIS</h3>\r\n        <p style=\"font-size:11px;color:#888;margin:2px 0 0 0;\">Female Abdomen</p>\r\n    </div>\r\n    \r\n    <div class=\"patient-info\" style=\"margin:10px 0;padding:8px 12px;background:#f8f9fa;border-radius:6px;border-left:4px solid #0B5ED7;\">\r\n        <div style=\"display:grid;grid-template-columns:1fr 1fr;gap:4px 20px;\">\r\n            <p style=\"margin:2px 0;\"><strong>Patient Name:</strong> {patient_name}</p>\r\n            <p style=\"margin:2px 0;\"><strong>Age/Sex:</strong> {age} yrs / {gender}</p>\r\n            <p style=\"margin:2px 0;\"><strong>Date of Exam:</strong> {exam_date}</p>\r\n            <p style=\"margin:2px 0;\"><strong>Patient ID:</strong> {patient_id}</p>\r\n            <p style=\"margin:2px 0;\"><strong>Report Date:</strong> {report_date}</p>\r\n        </div>\r\n    </div>\r\n    \r\n    <div class=\"findings\" style=\"margin:10px 0;\">\r\n        <h4 style=\"color:#0B5ED7;border-bottom:2px solid #0B5ED7;padding-bottom:4px;margin-bottom:8px;\">FINDINGS</h4>\r\n        <table style=\"width:100%;border-collapse:collapse;font-size:14px;\">\r\n            <tbody>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;width:35%;font-weight:600;\">Liver</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"liver\" placeholder=\"e.g. Appeared normal in size, shape, homogeneous echo pattern\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">Gallbladder</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"gallbladder\" placeholder=\"e.g. Appears normal, well distended, no stones\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">Pancreas</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"pancreas\" placeholder=\"e.g. Appeared normal in size and shape\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">Spleen</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"spleen\" placeholder=\"e.g. Appeared normal in size, shape and echotexture\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">Peritoneum</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"peritoneum\" placeholder=\"e.g. No free fluid noted\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">Kidneys</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"kidneys\" placeholder=\"e.g. Both kidneys normal\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">Urinary Bladder</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"bladder\" placeholder=\"e.g. Appears normal, well-distended\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">Uterus</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"uterus\" placeholder=\"e.g. Appears normal in size and homogeneous echo pattern\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">Right Ovary</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"right_ovary\" placeholder=\"e.g. Appears normal in size and appearance\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">Left Ovary</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"left_ovary\" placeholder=\"e.g. Appears normal in size and appearance\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n                <tr><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;\">Pouch of Douglas</td><td style=\"padding:4px 8px;border-bottom:1px solid #ddd;\"><input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"pouch_douglas\" placeholder=\"e.g. Free fluid seen\" style=\"width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;\"></td></tr>\r\n            </tbody>\r\n        </table>\r\n    </div>\r\n    \r\n    <div class=\"conclusion\" style=\"margin:10px 0;\">\r\n        <h4 style=\"color:#0B5ED7;border-bottom:2px solid #0B5ED7;padding-bottom:4px;margin-bottom:8px;\">IMPRESSION</h4>\r\n        <textarea class=\"form-control conclusion-field\" rows=\"2\" placeholder=\"Enter impression...\" style=\"width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;\"></textarea>\r\n    </div>\r\n    \r\n    <div class=\"report-footer\" style=\"margin-top:15px;padding-top:10px;border-top:2px solid #0B5ED7;\">\r\n        <div style=\"display:flex;justify-content:space-between;align-items:center;width:100%;flex-wrap:wrap;\">\r\n            <div>\r\n                <span>Technician: <input type=\"text\" class=\"form-control\" style=\"display:inline-block;width:auto;border:none;border-bottom:1px solid #ddd;padding:0 8px;\" placeholder=\"Technician Name\"></span>\r\n                <span style=\"margin-left:20px;\">Date: {report_date}</span>\r\n            </div>\r\n            <div style=\"text-align:right;padding:8px 16px;border:2px solid #0B5ED7;border-radius:8px;background:#f0f7ff;min-width:150px;\">\r\n                <div style=\"font-size:10px;color:#666;text-transform:uppercase;letter-spacing:1px;font-weight:bold;\">Official Stamp</div>\r\n                <div style=\"font-size:14px;font-weight:bold;color:#0B5ED7;margin-top:4px;\">BRAICK DISPENSARY</div>\r\n                <div style=\"font-size:10px;color:#888;border-top:1px dashed #ccc;padding-top:4px;margin-top:4px;\">\r\n                    <span>Approved By: _________________</span>\r\n                </div>\r\n                <div style=\"font-size:9px;color:#999;margin-top:2px;\">Date: {report_date}</div>\r\n            </div>\r\n        </div>\r\n    </div>\r\n</div>', 1, '2026-08-23 15:54:44', '2026-09-05 09:33:10');

-- --------------------------------------------------------

--
-- Table structure for table `lab_tests`
--

CREATE TABLE `lab_tests` (
  `id` int(11) NOT NULL,
  `visit_id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `lab_technician_id` int(11) DEFAULT NULL,
  `technician_id` int(11) DEFAULT NULL,
  `test_id` int(11) DEFAULT NULL,
  `test_name` varchar(100) NOT NULL,
  `test_price` decimal(12,2) DEFAULT 0.00,
  `equipment_used` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`equipment_used`)),
  `batch_number` varchar(50) DEFAULT NULL,
  `test_type` varchar(50) DEFAULT NULL,
  `sample_type` varchar(50) DEFAULT NULL,
  `test_date` date DEFAULT NULL,
  `results` text DEFAULT NULL,
  `formatted_result` text DEFAULT NULL,
  `reference_range` varchar(100) DEFAULT NULL,
  `interpretation` text DEFAULT NULL,
  `performed_by` int(11) DEFAULT NULL,
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `started_at` timestamp NULL DEFAULT NULL,
  `bill_created` tinyint(1) DEFAULT 0,
  `branch_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `printed_at` timestamp NULL DEFAULT NULL,
  `printed_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lab_tests_catalog`
--

CREATE TABLE `lab_tests_catalog` (
  `id` int(11) NOT NULL,
  `test_name` varchar(100) NOT NULL,
  `test_code` varchar(20) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `reference_range` varchar(100) DEFAULT NULL,
  `required_equipment_id` int(11) DEFAULT NULL,
  `equipment_quantity_used` int(11) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `branch_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lab_tests_catalog`
--

INSERT INTO `lab_tests_catalog` (`id`, `test_name`, `test_code`, `category`, `price`, `description`, `reference_range`, `required_equipment_id`, `equipment_quantity_used`, `is_active`, `branch_id`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Complete Blood Count (CBC)', 'CBC-001', 'Hematology', 15000.00, 'Full blood count including RBC, WBC, hemoglobin, platelets', 'RBC: 4.5-5.5M, WBC: 4.5-11K, HGB: 13-17g/dL, PLT: 150-400K', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(2, 'Blood Glucose (Fasting)', 'GLU-001', 'Biochemistry', 8000.00, 'Fasting blood sugar test', '70-100 mg/dL (Fasting)', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(3, 'Blood Glucose (Random)', 'GLU-002', 'Biochemistry', 8000.00, 'Random blood sugar test', '70-140 mg/dL', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(4, 'Lipid Profile', 'LIP-001', 'Biochemistry', 20000.00, 'Total cholesterol, HDL, LDL, Triglycerides', 'Total: <200, LDL: <100, HDL: >40, TG: <150', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(5, 'Liver Function Test (LFT)', 'LFT-001', 'Biochemistry', 25000.00, 'AST, ALT, ALP, Total Bilirubin, Direct Bilirubin', 'AST: 10-40, ALT: 7-56, ALP: 44-147, T.Bili: 0.1-1.2', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(6, 'Renal Function Test (RFT)', 'RFT-001', 'Biochemistry', 20000.00, 'Creatinine, BUN, Uric Acid, Electrolytes', 'Creatinine: 0.6-1.2, BUN: 7-20, Uric Acid: 3.5-7.2', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(7, 'Urinalysis', 'UNA-001', 'Urinalysis', 10000.00, 'Complete urine analysis with microscopy', 'pH: 4.5-8.0, Protein: Negative, Glucose: Negative', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(8, 'Malaria Rapid Test', 'MAL-001', 'Infectious Diseases', 5000.00, 'Rapid diagnostic test for malaria (Pf/Pv)', 'Negative/Positive', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(9, 'Malaria Microscopy', 'MAL-002', 'Infectious Diseases', 10000.00, 'Microscopic examination for malaria parasites', 'Negative/Positive', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(10, 'COVID-19 Rapid Antigen Test', 'COV-001', 'Infectious Diseases', 15000.00, 'Rapid antigen test for COVID-19', 'Negative/Positive', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(11, 'COVID-19 PCR Test', 'COV-002', 'Molecular', 50000.00, 'RT-PCR test for COVID-19', 'Negative/Positive', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(12, 'Typhoid Test (Widal)', 'TYPH-001', 'Infectious Diseases', 12000.00, 'Widal test for typhoid fever', 'O: <1:80, H: <1:160', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(13, 'Dengue Test', 'DEN-001', 'Infectious Diseases', 15000.00, 'Dengue NS1 antigen test', 'Negative/Positive', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(14, 'HIV Rapid Test', 'HIV-001', 'Infectious Diseases', 8000.00, 'Rapid HIV antibody test', 'Negative/Positive', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(15, 'HIV ELISA', 'HIV-002', 'Infectious Diseases', 25000.00, 'ELISA test for HIV', 'Negative/Positive', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(16, 'Pregnancy Test (Urine)', 'PRE-001', 'Urinalysis', 5000.00, 'Urine pregnancy test (HCG)', 'Negative/Positive', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(17, 'Pregnancy Test (Blood - Beta HCG)', 'PRE-002', 'Hormone', 20000.00, 'Quantitative blood HCG test', 'Negative: <5 mIU/mL', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(18, 'Tuberculosis (TB) Skin Test', 'TB-001', 'Infectious Diseases', 10000.00, 'Mantoux tuberculin skin test', '0-4mm: Negative, 5-9mm: Indeterminate', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(19, 'Tuberculosis (TB) GeneXpert', 'TB-002', 'Molecular', 45000.00, 'GeneXpert MTB/RIF test', 'Negative/Positive/RIF Resistance', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(20, 'Sputum AFB', 'SPUT-001', 'Microbiology', 12000.00, 'Acid-fast bacilli smear test', 'Negative/Positive', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(21, 'Blood Culture', 'CULT-001', 'Microbiology', 30000.00, 'Blood culture and sensitivity', 'No growth/Pathogen isolated', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(22, 'Urine Culture', 'CULT-002', 'Microbiology', 25000.00, 'Urine culture and sensitivity', 'No growth/Pathogen isolated', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(23, 'Stool Analysis', 'STL-001', 'Parasitology', 10000.00, 'Complete stool examination', 'Normal/Abnormal', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(24, 'Helicobacter Pylori Test', 'HP-001', 'Infectious Diseases', 15000.00, 'H. pylori antigen test', 'Negative/Positive', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(25, 'Thyroid Function Test (TFT)', 'THY-001', 'Hormone', 30000.00, 'TSH, T3, T4', 'TSH: 0.4-4.0, Free T4: 0.8-1.8', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(26, 'Vitamin D Test', 'VITD-001', 'Nutrition', 25000.00, '25-Hydroxy Vitamin D test', 'Deficient: <20, Insufficient: 20-29, Sufficient: >30', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(27, 'Vitamin B12 Test', 'VITB12-001', 'Nutrition', 20000.00, 'Vitamin B12 level', '200-900 pg/mL', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(28, 'Ferritin Test', 'FERR-001', 'Nutrition', 18000.00, 'Ferritin iron stores test', 'Male: 24-336, Female: 11-307 ng/mL', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(29, 'Hepatitis B Surface Antigen (HBsAg)', 'HEP-001', 'Infectious Diseases', 15000.00, 'Hepatitis B surface antigen test', 'Negative/Positive', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(30, 'Hepatitis C Antibody (Anti-HCV)', 'HEP-002', 'Infectious Diseases', 15000.00, 'Hepatitis C antibody test', 'Negative/Positive', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(31, 'STI Panel', 'STI-001', 'Infectious Diseases', 35000.00, 'Syphilis (RPR/VDRL), Chlamydia, Gonorrhea', 'Negative/Positive', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(32, 'Syphilis RPR/VDRL', 'SYPH-001', 'Infectious Diseases', 10000.00, 'RPR/VDRL test for syphilis', 'Non-reactive/Reactive', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(33, 'Influenza Rapid Test', 'FLU-001', 'Infectious Diseases', 12000.00, 'Rapid influenza A/B test', 'Negative/Positive', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(34, 'CD4 Count', 'CD4-001', 'Immunology', 30000.00, 'CD4 T-cell count (HIV monitoring)', '>500 cells/mm³ (Normal)', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(35, 'Viral Load HIV', 'VL-001', 'Molecular', 50000.00, 'HIV viral load quantification', '<20 copies/mL (Undetectable)', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(36, 'Chest X-Ray', 'XRAY-001', 'Radiology', 35000.00, 'Chest X-Ray imaging', 'Normal/Abnormal', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(37, 'Abdominal Ultrasound', 'US-001', 'Radiology', 50000.00, 'Abdominal ultrasound scan', 'Normal/Abnormal', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(38, 'Pelvic Ultrasound', 'US-002', 'Radiology', 45000.00, 'Pelvic ultrasound', 'Normal/Abnormal', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(39, 'Obstetric Ultrasound', 'US-003', 'Radiology', 55000.00, 'Obstetric ultrasound scan', 'Normal/Abnormal', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(40, 'ECG (Electrocardiogram)', 'ECG-001', 'Cardiology', 15000.00, '12-lead ECG', 'Normal/Abnormal', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(41, 'Echocardiogram', 'ECHO-001', 'Cardiology', 60000.00, 'Cardiac ultrasound', 'Normal/Abnormal', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(42, 'Pulmonary Function Test (PFT)', 'PFT-001', 'Pulmonology', 25000.00, 'Spirometry/Lung function test', 'Normal/Obstructive/Restrictive', NULL, 1, 1, 1, NULL, '2026-07-18 13:18:37', '2026-07-27 10:14:25'),
(43, 'Ultrasound - Abdomen & Pelvis', 'US-004', 'Radiology', 65000.00, 'Combined abdominal and pelvic ultrasound', 'Normal/Abnormal', NULL, 1, 1, 1, NULL, '2026-07-27 10:13:54', '2026-07-27 10:14:25'),
(44, 'Ultrasound - Thyroid', 'US-005', 'Radiology', 40000.00, 'Thyroid ultrasound scan', 'Normal/Abnormal', NULL, 1, 1, 1, NULL, '2026-07-27 10:13:54', '2026-07-27 10:14:25'),
(45, 'Ultrasound - Breast', 'US-006', 'Radiology', 45000.00, 'Breast ultrasound examination', 'Normal/Abnormal', NULL, 1, 1, 1, NULL, '2026-07-27 10:13:54', '2026-07-27 10:14:25'),
(46, 'Ultrasound - Scrotal', 'US-007', 'Radiology', 40000.00, 'Scrotal/testicular ultrasound', 'Normal/Abnormal', NULL, 1, 1, 1, NULL, '2026-07-27 10:13:54', '2026-07-27 10:14:25'),
(47, 'Ultrasound - Renal', 'US-008', 'Radiology', 45000.00, 'Renal/kidney ultrasound', 'Normal/Abnormal', NULL, 1, 1, 1, NULL, '2026-07-27 10:13:54', '2026-07-27 10:14:25'),
(48, 'Ultrasound - Liver/Biliary', 'US-009', 'Radiology', 40000.00, 'Liver and biliary tree ultrasound', 'Normal/Abnormal', NULL, 1, 1, 1, NULL, '2026-07-27 10:13:54', '2026-07-27 10:14:25'),
(49, 'Ultrasound - Musculoskeletal', 'US-010', 'Radiology', 50000.00, 'MSK ultrasound for joints and soft tissue', 'Normal/Abnormal', NULL, 1, 1, 1, NULL, '2026-07-27 10:13:54', '2026-07-27 10:14:25'),
(50, 'Ultrasound - Doppler', 'US-011', 'Radiology', 55000.00, 'Doppler ultrasound for vascular assessment', 'Normal/Abnormal', NULL, 1, 1, 1, NULL, '2026-07-27 10:13:54', '2026-07-27 10:14:25'),
(51, 'Ultrasound - 3D/4D Obstetric', 'US-012', 'Radiology', 80000.00, '3D/4D obstetric ultrasound', 'Normal/Abnormal', NULL, 1, 1, 1, NULL, '2026-07-27 10:13:54', '2026-07-27 10:14:25'),
(52, 'Ultrasound - Transvaginal', 'US-013', 'Radiology', 50000.00, 'Transvaginal pelvic ultrasound', 'Normal/Abnormal', NULL, 1, 1, 1, NULL, '2026-07-27 10:13:54', '2026-07-27 10:14:25'),
(53, 'Abdominal Ultrasound', NULL, 'Lab Tests', 45000.00, '', NULL, NULL, 1, 1, 1, 4, '2026-08-24 13:06:45', '2026-08-24 13:06:45'),
(55, 'Chest X-Ray', NULL, 'Radiology', 65000.00, '', NULL, NULL, 2, 1, 1, 4, '2026-08-25 08:33:29', '2026-08-25 08:45:38'),
(57, 'Blood Check', NULL, 'Lab Tests', 25000.00, '', NULL, NULL, 1, 1, 2, 1, '2026-08-29 23:23:51', '2026-08-29 23:23:51'),
(58, 'Blood Check', 'BLO-20260908-5331', 'Action', 7000.00, '', '70-140 mg/dL', NULL, 1, 1, 1, 13, '2026-09-08 12:50:35', '2026-09-08 12:50:35'),
(59, 'Blood Check', 'BLO-20260908-4982', 'Radiology', 10000.00, '', '70-140 mg/dL', NULL, 1, 1, 1, 13, '2026-09-08 12:51:25', '2026-09-08 12:51:25'),
(60, 'Blood Check', 'BLO-20260908-5685', 'Radiology', 10000.00, '', '70-140 mg/dL', NULL, 1, 1, 1, 13, '2026-09-08 12:59:02', '2026-09-08 12:59:02'),
(61, 'BLOOD', 'BLO-20260908-3061', 'Wound Care', 4000.00, '', '70-140 mg/dL', NULL, 1, 1, 1, 13, '2026-09-08 13:00:02', '2026-09-08 13:00:02'),
(62, 'BLOOD', 'BLO-20260908-9768', 'ring', 7000.00, '', '70-140 mg/dL', NULL, 1, 1, 1, 13, '2026-09-08 14:07:08', '2026-09-08 14:07:08'),
(63, 'SS', NULL, 'AS', 3000.00, '', NULL, NULL, 1, 1, 1, 4, '2026-09-08 14:43:24', '2026-09-08 14:43:24'),
(64, 'SS', NULL, 'AS', 3000.00, '', NULL, NULL, 1, 1, 1, 4, '2026-09-08 14:46:32', '2026-09-08 14:46:32'),
(65, 'ELISA', NULL, 'Lab Tests', 25000.00, '', NULL, NULL, 1, 1, 1, 4, '2026-09-09 09:48:26', '2026-09-09 09:48:26'),
(66, 'sampleee test with equipments', NULL, 'Lab Tests', 55000.00, '', NULL, NULL, 1, 1, 1, 4, '2026-09-09 12:55:36', '2026-09-09 12:55:36'),
(67, 'KICHOCHO', NULL, 'Lab Tests', 2000.00, '', NULL, NULL, 1, 1, 1, 4, '2026-09-09 14:17:55', '2026-09-09 14:17:55'),
(68, 'KFADURO', NULL, 'Lab Tests', 10000.00, '', NULL, NULL, 1, 1, 1, 4, '2026-09-09 14:27:04', '2026-09-09 14:27:04');

-- --------------------------------------------------------

--
-- Table structure for table `lab_test_equipment`
--

CREATE TABLE `lab_test_equipment` (
  `id` int(11) NOT NULL,
  `lab_test_id` int(11) NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lab_test_equipment`
--

INSERT INTO `lab_test_equipment` (`id`, `lab_test_id`, `equipment_id`, `branch_id`, `created_at`) VALUES
(14, 67, 41, 1, '2026-09-09 14:17:55'),
(15, 68, 41, 1, '2026-09-09 14:27:04');

-- --------------------------------------------------------

--
-- Table structure for table `medical_equipment`
--

CREATE TABLE `medical_equipment` (
  `id` int(11) NOT NULL,
  `equipment_name` varchar(100) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `unit` varchar(20) DEFAULT 'pcs',
  `quantity` int(11) DEFAULT 0,
  `reorder_level` int(11) DEFAULT 5,
  `unit_cost` decimal(12,2) DEFAULT 0.00,
  `selling_price` decimal(12,2) DEFAULT 0.00,
  `supplier` varchar(100) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `batch_number` varchar(50) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `added_by` int(11) DEFAULT NULL,
  `added_by_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `medical_equipment`
--

INSERT INTO `medical_equipment` (`id`, `equipment_name`, `category`, `unit`, `quantity`, `reorder_level`, `unit_cost`, `selling_price`, `supplier`, `expiry_date`, `batch_number`, `branch_id`, `created_by`, `status`, `added_by`, `added_by_name`, `created_at`, `updated_at`) VALUES
(1, 'ECG Machine (12-Lead)', 'Cardiology', 'pcs', 3, 2, 50000.00, 15000.00, 'GE Healthcare', NULL, 'EQP-20260909-ECG-001', 1, 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-08 21:00:00'),
(2, 'Ultrasound Machine', 'Radiology', 'pcs', 2, 1, 80000.00, 25000.00, 'Siemens', NULL, 'EQP-20260909-US-001', 1, 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-08 21:00:00'),
(3, 'Blood Pressure Monitor', 'Diagnostic', 'pcs', 10, 3, 10000.00, 5000.00, 'Omron', NULL, 'EQP-20260909-BP-001', 1, 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-08 21:00:00'),
(4, 'Stethoscope', 'Diagnostic', 'pcs', 15, 5, 8000.00, 3000.00, '3M Littmann', NULL, 'EQP-20260909-ST-001', 1, 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-08 21:00:00'),
(5, 'Surgical Scalpel Set', 'Surgery', 'set', 8, 3, 15000.00, 5000.00, 'Medical Supplies Co', NULL, 'EQP-20260909-SS-001', 1, 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-08 21:00:00'),
(6, 'Bandage Roll', 'Wound Care', 'roll', 100, 20, 500.00, 1500.00, 'MediCare', '2027-12-31', 'EQP-20260909-BAND-001', 1, 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-08 21:00:00'),
(7, 'Gauze Swabs (Sterile)', 'Wound Care', 'pack', 50, 10, 300.00, 1000.00, 'MediCare', '2027-06-30', 'EQP-20260909-GAUZE-001', 1, 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-08 21:00:00'),
(8, 'Surgical Gloves (Sterile)', 'Surgery', 'box', 30, 5, 2000.00, 5000.00, 'Ansell', '2027-09-30', 'EQP-20260909-GLOVE-001', 1, 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-08 21:00:00'),
(9, 'Suture Kit', 'Surgery', 'kit', 12, 4, 25000.00, 10000.00, 'Ethicon', NULL, 'EQP-20260909-SUT-001', 1, 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-08 21:00:00'),
(40, 'BANDAGE', 'Lab Equipment', 'box', 300, 50, 500.00, 1500.00, 'WAKATI', '0000-00-00', 'EQP-20260909-8495C2', 1, NULL, 'active', 1, 'System Admin', '2026-09-09 13:35:41', '2026-09-09 13:37:29'),
(41, 'SINDANO', 'Wound Care', 'set', 299, 50, 500.00, 1200.00, 'AMANA', '0000-00-00', 'EQP-20260909-40E8C8', 1, NULL, 'active', 7, 'LUCY MUSSA', '2026-09-09 13:36:49', '2026-09-09 18:26:53');

-- --------------------------------------------------------

--
-- Table structure for table `medications_inventory`
--

CREATE TABLE `medications_inventory` (
  `id` int(11) NOT NULL,
  `medication_name` varchar(100) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `quantity` int(11) DEFAULT 0,
  `reorder_level` int(11) DEFAULT 10,
  `unit_cost` decimal(12,2) DEFAULT 0.00,
  `selling_price` decimal(12,2) DEFAULT 0.00,
  `supplier` varchar(100) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `batch_number` varchar(50) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `added_by` int(11) DEFAULT NULL,
  `added_by_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `medications_inventory`
--

INSERT INTO `medications_inventory` (`id`, `medication_name`, `category`, `unit`, `quantity`, `reorder_level`, `unit_cost`, `selling_price`, `supplier`, `expiry_date`, `batch_number`, `branch_id`, `status`, `added_by`, `added_by_name`, `created_at`, `updated_at`) VALUES
(1, 'Paracetamol 500mg', 'Analgesics', 'tablets', 500, 50, 50.00, 200.00, 'Medical Supplies Ltd', '2027-12-31', 'BATCH-PCM-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-08 21:00:00'),
(2, 'Amoxicillin 500mg', 'Antibiotics', 'capsules', 300, 30, 150.00, 500.00, 'PharmaPlus Ltd', '2027-10-15', 'BATCH-AMOX-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-08 21:00:00'),
(3, 'Ciprofloxacin 500mg', 'Antibiotics', 'tablets', 200, 20, 200.00, 800.00, 'PharmaPlus Ltd', '2027-11-30', 'BATCH-CIPRO-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-08 21:00:00'),
(4, 'Metronidazole 400mg', 'Antibiotics', 'tablets', 250, 25, 100.00, 400.00, 'Medical Supplies Ltd', '2027-09-20', 'BATCH-METRO-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-08 21:00:00'),
(5, 'Omeprazole 20mg', 'Antacids', 'capsules', 150, 15, 80.00, 350.00, 'HealthCare Ltd', '2028-01-15', 'BATCH-OME-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-08 21:00:00'),
(6, 'Ibuprofen 400mg', 'Analgesics', 'tablets', 200, 20, 60.00, 300.00, 'Medical Supplies Ltd', '2027-08-30', 'BATCH-IBU-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-08 21:00:00'),
(7, 'Diclofenac 50mg', 'Analgesics', 'tablets', 180, 18, 70.00, 350.00, 'PharmaPlus Ltd', '2027-07-25', 'BATCH-DICL-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-08 21:00:00'),
(8, 'Cetirizine 10mg', 'Antihistamines', 'tablets', 300, 30, 30.00, 150.00, 'HealthCare Ltd', '2028-02-28', 'BATCH-CET-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-08 21:00:00'),
(9, 'Loratadine 10mg', 'Antihistamines', 'tablets', 250, 25, 40.00, 200.00, 'Medical Supplies Ltd', '2027-12-15', 'BATCH-LORA-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-08 21:00:00'),
(10, 'Salbutamol Inhaler', 'Respiratory', 'inhaler', 80, 10, 2500.00, 5000.00, 'PharmaPlus Ltd', '2028-03-01', 'BATCH-SALB-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-08 21:00:00'),
(11, 'Beclomethasone Inhaler', 'Respiratory', 'inhaler', 60, 10, 3000.00, 6500.00, 'HealthCare Ltd', '2028-04-15', 'BATCH-BECLO-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-08 21:00:00'),
(12, 'Amlodipine 5mg', 'Cardiovascular', 'tablets', 120, 15, 120.00, 450.00, 'Medical Supplies Ltd', '2027-11-30', 'BATCH-AML-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-08 21:00:00'),
(13, 'Enalapril 10mg', 'Cardiovascular', 'tablets', 100, 10, 130.00, 500.00, 'PharmaPlus Ltd', '2027-10-20', 'BATCH-ENAL-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-08 21:00:00'),
(14, 'Hydrochlorothiazide 25mg', 'Cardiovascular', 'tablets', 130, 15, 80.00, 300.00, 'HealthCare Ltd', '2027-09-10', 'BATCH-HCTZ-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-08 21:00:00'),
(15, 'Metformin 500mg', 'Antidiabetic', 'tablets', 200, 20, 100.00, 400.00, 'Medical Supplies Ltd', '2028-01-20', 'BATCH-MET-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-08 21:00:00'),
(16, 'Glibenclamide 5mg', 'Antidiabetic', 'tablets', 150, 15, 90.00, 350.00, 'PharmaPlus Ltd', '2027-12-05', 'BATCH-GLIB-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-08 21:00:00'),
(17, 'Furosemide 40mg', 'Diuretics', 'tablets', 100, 10, 70.00, 250.00, 'HealthCare Ltd', '2027-08-15', 'BATCH-FURO-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-08 21:00:00'),
(18, 'Diazepam 5mg', 'Sedatives', 'tablets', 80, 10, 150.00, 600.00, 'Medical Supplies Ltd', '2028-02-10', 'BATCH-DIAZ-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-08 21:00:00'),
(19, 'Amitriptyline 25mg', 'Antidepressants', 'tablets', 90, 10, 120.00, 450.00, 'PharmaPlus Ltd', '2027-09-25', 'BATCH-AMIT-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-08 21:00:00'),
(20, 'Multivitamin Tablets', 'Vitamins', 'tablets', 400, 40, 30.00, 150.00, 'HealthCare Ltd', '2028-06-30', 'BATCH-MVIT-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-08 21:00:00'),
(25, 'ALBENDAZOLE', 'Antibiotics', 'pcs', 400, 100, 1300.00, 3000.00, 'AVANA MEDICS', '2028-09-08', 'BATCH-20260908-A196B7', 1, 'active', 8, 'Mary John', '2026-09-08 13:38:44', '2026-09-09 17:44:25'),
(26, 'AMOXILINE', 'Antacids', 'pcs', 200, 20, 1000.00, 2500.00, 'AVANA MEDICS', '2027-05-08', 'BATCH-20260908-49302D', 1, 'active', 8, 'Mary John', '2026-09-08 13:40:09', '2026-09-09 17:44:29');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','danger') DEFAULT 'info',
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `branch_id`, `patient_id`, `title`, `message`, `type`, `link`, `is_read`, `created_at`, `updated_at`) VALUES
(11, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260823-0005-5369 (TSh 10,000) for patient JACKSON MYULA', '', 'cashier_dashboard.php', 0, '2026-08-23 18:05:29', '2026-08-23 18:05:29'),
(12, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260823-0006-1768 (TSh 10,000) for patient ID #6', '', 'cashier_dashboard.php', 0, '2026-08-23 18:31:31', '2026-08-23 18:31:31'),
(13, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260823-0008-6790 (TSh 10,000) for patient JACKSON MYULA', '', 'cashier_dashboard.php', 0, '2026-08-23 21:07:18', '2026-08-23 21:07:18'),
(14, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260823-0019-7575 (TSh 10,000) for patient JACKSON MYULA', '', 'cashier_dashboard.php', 0, '2026-08-23 21:56:51', '2026-08-23 21:56:51'),
(15, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260824-0002-8218 (TSh 10,000) for patient ID #2', '', 'cashier_dashboard.php', 0, '2026-08-23 22:32:04', '2026-08-23 22:32:04'),
(16, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260824-0003-7155 (TSh 10,000) for patient MUSSA MONGI', '', 'cashier_dashboard.php', 0, '2026-08-23 23:07:17', '2026-08-23 23:07:17'),
(17, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260825-0004-4461 (TSh 30,000) for patient ID #4', '', 'cashier_dashboard.php', 0, '2026-08-24 22:11:50', '2026-08-24 22:11:50'),
(18, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260825-0038-1439 (TSh 10,000) for patient ID #38', '', 'cashier_dashboard.php', 0, '2026-08-24 23:09:37', '2026-08-24 23:09:37'),
(19, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260825-0039-9656 (TSh 10,000) for patient ID #39', '', 'cashier_dashboard.php', 0, '2026-08-24 23:33:52', '2026-08-24 23:33:52'),
(20, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260825-0040-3617 (TSh 10,000) for patient ID #40', '', 'cashier_dashboard.php', 0, '2026-08-25 08:11:57', '2026-08-25 08:11:57'),
(21, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260825-0041-5712 (TSh 10,000) for patient ID #41', '', 'cashier_dashboard.php', 0, '2026-08-25 08:42:21', '2026-08-25 08:42:21'),
(22, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260825-0042-7005 (TSh 10,000) for patient ID #42', '', 'cashier_dashboard.php', 0, '2026-08-25 09:00:57', '2026-08-25 09:00:57'),
(23, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260825-0043-9194 (TSh 10,000) for patient ID #43', '', 'cashier_dashboard.php', 0, '2026-08-25 09:52:25', '2026-08-25 09:52:25'),
(24, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260825-0044-2905 (TSh 10,000) for patient ID #44', '', 'cashier_dashboard.php', 0, '2026-08-25 14:13:18', '2026-08-25 14:13:18'),
(25, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260825-0045-7270 (TSh 10,000) for patient ID #45', '', 'cashier_dashboard.php', 0, '2026-08-25 14:41:37', '2026-08-25 14:41:37'),
(26, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260825-0046-2475 (TSh 10,000) for patient ID #46', '', 'cashier_dashboard.php', 0, '2026-08-25 20:27:15', '2026-08-25 20:27:15'),
(27, 10, NULL, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. ERICK is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 1, '2026-08-25 20:28:33', '2026-08-29 12:56:51'),
(28, 11, NULL, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. ERICK is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-08-25 20:28:33', '2026-08-25 20:28:33'),
(29, 12, NULL, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. ERICK is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-08-25 20:28:33', '2026-08-25 20:28:33'),
(30, 1, NULL, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. ERICK is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-08-25 20:28:33', '2026-08-25 20:28:33'),
(31, 3, NULL, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. ERICK is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-08-25 20:28:33', '2026-08-25 20:28:33'),
(32, 10, NULL, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. ERICK is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 1, '2026-08-25 20:28:34', '2026-08-29 12:56:45'),
(33, 11, NULL, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. ERICK is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-08-25 20:28:34', '2026-08-25 20:28:34'),
(34, 12, NULL, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. ERICK is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-08-25 20:28:34', '2026-08-25 20:28:34'),
(35, 1, NULL, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. ERICK is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-08-25 20:28:34', '2026-08-25 20:28:34'),
(36, 3, NULL, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. ERICK is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-08-25 20:28:34', '2026-08-25 20:28:34'),
(37, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260825-0047-2995 (TSh 10,000) for patient ID #47', '', 'cashier_dashboard.php', 0, '2026-08-25 21:20:23', '2026-08-25 21:20:23'),
(38, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0048-7157 (TSh 10,000) for patient ID #48', '', 'cashier_dashboard.php', 0, '2026-08-25 22:01:03', '2026-08-25 22:01:03'),
(39, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0048-1355 (TSh 10,000) for patient ID #48', '', 'cashier_dashboard.php', 0, '2026-08-26 08:37:01', '2026-08-26 08:37:01'),
(40, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0047-3446 (TSh 10,000) for patient ID #47', '', 'cashier_dashboard.php', 0, '2026-08-26 08:37:54', '2026-08-26 08:37:54'),
(41, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0048-2328 (TSh 10,000) for patient ID #48', '', 'cashier_dashboard.php', 0, '2026-08-26 09:49:07', '2026-08-26 09:49:07'),
(42, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0047-8669 (TSh 10,000) for patient ID #47', '', 'cashier_dashboard.php', 0, '2026-08-26 10:08:41', '2026-08-26 10:08:41'),
(43, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0048-7143 (TSh 10,000) for patient ID #48', '', 'cashier_dashboard.php', 0, '2026-08-26 10:28:56', '2026-08-26 10:28:56'),
(44, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0047-9054 (TSh 10,000) for patient ID #47', '', 'cashier_dashboard.php', 0, '2026-08-26 12:23:28', '2026-08-26 12:23:28'),
(45, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0049-5983 (TSh 10,000) for patient ID #49', '', 'cashier_dashboard.php', 0, '2026-08-26 12:30:15', '2026-08-26 12:30:15'),
(46, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0048-2126 (TSh 10,000) for patient ID #48', '', 'cashier_dashboard.php', 0, '2026-08-26 12:31:09', '2026-08-26 12:31:09'),
(47, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0049-3070 (TSh 10,000) for patient ID #49', '', 'cashier_dashboard.php', 0, '2026-08-26 12:45:41', '2026-08-26 12:45:41'),
(48, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0047-9584 (TSh 10,000) for patient ID #47', '', 'cashier_dashboard.php', 0, '2026-08-26 12:47:57', '2026-08-26 12:47:57'),
(49, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0050-7489 (TSh 10,000) for patient KELVIN MSAFIRI', '', 'cashier_dashboard.php', 0, '2026-08-26 13:03:37', '2026-08-26 13:03:37'),
(50, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0050-2699 (TSh 10,000) for patient ID #50', '', 'cashier_dashboard.php', 0, '2026-08-26 13:59:28', '2026-08-26 13:59:28'),
(51, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0050-3285 (TSh 10,000) for patient ID #50', '', 'cashier_dashboard.php', 0, '2026-08-26 14:08:04', '2026-08-26 14:08:04'),
(52, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0049-1966 (TSh 10,000) for patient ID #49', '', 'cashier_dashboard.php', 0, '2026-08-26 14:08:25', '2026-08-26 14:08:25'),
(53, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0048-8072 (TSh 10,000) for patient ID #48', '', 'cashier_dashboard.php', 0, '2026-08-26 14:08:42', '2026-08-26 14:08:42'),
(54, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0047-5125 (TSh 10,000) for patient ID #47', '', 'cashier_dashboard.php', 0, '2026-08-26 14:09:04', '2026-08-26 14:09:04'),
(55, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0050-9116 (TSh 10,000) for patient ID #50', '', 'cashier_dashboard.php', 0, '2026-08-26 15:16:50', '2026-08-26 15:16:50'),
(56, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0049-1329 (TSh 10,000) for patient ID #49', '', 'cashier_dashboard.php', 0, '2026-08-26 15:17:23', '2026-08-26 15:17:23'),
(57, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0048-8453 (TSh 10,000) for patient ID #48', '', 'cashier_dashboard.php', 0, '2026-08-26 15:17:51', '2026-08-26 15:17:51'),
(58, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0047-4542 (TSh 10,000) for patient ID #47', '', 'cashier_dashboard.php', 0, '2026-08-26 15:18:12', '2026-08-26 15:18:12'),
(59, 5, 1, 50, '📋 New Referral Received', 'New referral from Dr. Dr.ERICK JOHN for patient KELVIN MSAFIRI', 'info', 'my_patients.php', 0, '2026-08-26 16:34:19', '2026-08-26 16:34:19'),
(60, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0050-4585 (TSh 10,000) for patient ID #50', '', 'cashier_dashboard.php', 0, '2026-08-26 16:49:37', '2026-08-26 16:49:37'),
(61, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0049-9030 (TSh 10,000) for patient ID #49', '', 'cashier_dashboard.php', 0, '2026-08-26 16:49:57', '2026-08-26 16:49:57'),
(62, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0048-9353 (TSh 10,000) for patient ID #48', '', 'cashier_dashboard.php', 0, '2026-08-26 16:50:13', '2026-08-26 16:50:13'),
(63, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0047-2472 (TSh 10,000) for patient ID #47', '', 'cashier_dashboard.php', 0, '2026-08-26 16:50:39', '2026-08-26 16:50:39'),
(64, 6, 1, 49, '📋 New Referral Received', 'New referral from Dr. Dr.ERICK JOHN for patient AGUSTINO VALENTINE', 'info', 'my_patients.php', 0, '2026-08-26 16:52:15', '2026-08-26 16:52:15'),
(65, 6, 1, 49, '📋 New Referral Received', 'New referral from Dr. Dr.ERICK JOHN for patient AGUSTINO VALENTINE', 'info', 'my_patients.php', 0, '2026-08-26 16:58:22', '2026-08-26 16:58:22'),
(66, 6, 1, 49, '📋 New Referral Received', 'New referral from Dr. Dr.ERICK JOHN for patient AGUSTINO VALENTINE', 'info', 'my_patients.php', 0, '2026-08-26 16:59:36', '2026-08-26 16:59:36'),
(67, 6, 1, 49, '📋 New Referral Received', 'New referral from Dr. Dr.ERICK JOHN for patient AGUSTINO VALENTINE', 'info', 'my_patients.php', 0, '2026-08-26 17:01:35', '2026-08-26 17:01:35'),
(68, 6, 1, 49, '📋 New Referral Received', 'New referral from Dr. Dr.ERICK JOHN for patient AGUSTINO VALENTINE', 'info', 'my_patients.php', 0, '2026-08-26 17:06:21', '2026-08-26 17:06:21'),
(69, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0051-2947 (TSh 10,000) for patient CLEOFAS WILLIUM', '', 'cashier_dashboard.php', 0, '2026-08-26 18:36:46', '2026-08-26 18:36:46'),
(70, 6, 1, 49, '📋 New Referral Received', 'New referral from Dr. Dr.ERICK JOHN for patient AGUSTINO VALENTINE', 'info', 'my_patients.php', 0, '2026-08-26 19:06:50', '2026-08-26 19:06:50'),
(71, 6, 1, 51, '📋 New Referral Received', 'New referral from Dr. Dr.ERICK JOHN for patient CLEOFAS WILLIUM', 'info', 'my_patients.php', 0, '2026-08-26 19:06:50', '2026-08-26 19:06:50'),
(72, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0052-4137 (TSh 10,000) for patient JUDITH SOLOMONI - New Patient', '', 'cashier_dashboard.php', 0, '2026-08-26 19:23:28', '2026-08-26 19:23:28'),
(73, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0053-7988 (TSh 10,000) for patient ID #53', '', 'cashier_dashboard.php', 0, '2026-08-26 19:25:47', '2026-08-26 19:25:47'),
(74, 5, 1, 48, '📋 New Referral Received', 'New referral from Dr. Dr.ERICK JOHN for patient IBRAHIM DOUMBIA', 'info', 'my_patients.php', 0, '2026-08-26 19:32:56', '2026-08-26 19:32:56'),
(75, 5, 1, 49, '📋 New Referral Received', 'New referral from Dr. Dr.ERICK JOHN for patient AGUSTINO VALENTINE', 'info', 'my_patients.php', 0, '2026-08-26 19:32:56', '2026-08-26 19:32:56'),
(76, 5, 1, 51, '📋 New Referral Received', 'New referral from Dr. Dr.ERICK JOHN for patient CLEOFAS WILLIUM', 'info', 'my_patients.php', 0, '2026-08-26 19:32:56', '2026-08-26 19:32:56'),
(77, 5, 1, 52, '📋 New Referral Received', 'New referral from Dr. Dr.ERICK JOHN for patient JUDITH SOLOMONI', 'info', 'my_patients.php', 0, '2026-08-26 19:32:56', '2026-08-26 19:32:56'),
(78, 5, 1, 53, '📋 New Referral Received', 'New referral from Dr. Dr.ERICK JOHN for patient MAGRETH CHAKUPEWA', 'info', 'my_patients.php', 0, '2026-08-26 19:32:56', '2026-08-26 19:32:56'),
(79, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0052-6103 (TSh 25,000) for patient ID #52', '', 'cashier_dashboard.php', 0, '2026-08-26 19:49:56', '2026-08-26 19:49:56'),
(80, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0054-6261 (TSh 10,000) for patient CLEMENCY MTUKA - New Patient', '', 'cashier_dashboard.php', 0, '2026-08-26 19:55:59', '2026-08-26 19:55:59'),
(81, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0055-7995 (TSh 25,000) for patient ALPHONSE MABULA - Consultation-B', '', 'cashier_dashboard.php', 0, '2026-08-26 19:59:21', '2026-08-26 19:59:21'),
(82, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0056-8122 (TSh 30,000) for patient julieth kalinde - Specialist Consultation', '', 'cashier_dashboard.php', 0, '2026-08-26 20:07:21', '2026-08-26 20:07:21'),
(83, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0054-4629 (TSh 30,000) for patient ID #54', '', 'cashier_dashboard.php', 0, '2026-08-26 20:08:34', '2026-08-26 20:08:34'),
(84, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0053-2492 (TSh 100,000) for patient ID #53', '', 'cashier_dashboard.php', 0, '2026-08-26 20:13:25', '2026-08-26 20:13:25'),
(85, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0057-6417 (TSh 30,000) for patient VICTORIA SALINGO - Specialist Consultation', '', 'cashier_dashboard.php', 0, '2026-08-26 20:22:10', '2026-08-26 20:22:10'),
(86, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0058-3361 (TSh 30,000) for patient AYUBU NZAL - Specialist Consultation', '', 'cashier_dashboard.php', 0, '2026-08-26 20:35:50', '2026-08-26 20:35:50'),
(87, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0058-9973 (TSh 30,000) for patient ID #58 - Specialist Consultation', '', 'cashier_dashboard.php', 0, '2026-08-26 20:42:37', '2026-08-26 20:42:37'),
(88, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0059-4431 (TSh 25,000) for patient AMOSI NGOMENI - Consultation-B', '', 'cashier_dashboard.php', 0, '2026-08-26 20:52:13', '2026-08-26 20:52:13'),
(89, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0057-1456 (TSh 15,000) for patient ID #57 - General Consultation', '', 'cashier_dashboard.php', 0, '2026-08-26 20:53:12', '2026-08-26 20:53:12'),
(90, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0060-7865 (TSh 25,000) for patient ANDREW VICENT CHIKUPE - Consultation-B', '', 'cashier_dashboard.php', 0, '2026-08-26 21:00:03', '2026-08-26 21:00:03'),
(91, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0060-8927 (TSh 100,000) for patient ID #60 - visit_mpya', '', 'cashier_dashboard.php', 0, '2026-08-26 21:09:25', '2026-08-26 21:09:25'),
(92, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0061-3065 (TSh 30,000) for patient MUSSA MONGI MASNGI - Specialist Consultation', '', 'cashier_dashboard.php', 0, '2026-08-26 21:11:23', '2026-08-26 21:11:23'),
(93, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260826-0061-1887 (TSh 30,000) for patient ID #61 - Specialist Consultation', '', 'cashier_dashboard.php', 0, '2026-08-26 21:39:23', '2026-08-26 21:39:23'),
(94, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260827-0055-5480 (TSh 100,000) for patient ID #55 - visit_mpya', '', 'cashier_dashboard.php', 0, '2026-08-26 22:37:54', '2026-08-26 22:37:54'),
(95, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260827-0060-5575 (TSh 10,000) for patient ID #60 - New Patient', '', 'cashier_dashboard.php', 0, '2026-08-26 22:43:03', '2026-08-26 22:43:03'),
(96, 5, 1, 60, '📋 New Referral Received', 'New referral from Dr. Dr.ERICK JOHN for patient ANDREW VICENT CHIKUPE', 'info', 'my_patients.php', 0, '2026-08-28 11:48:16', '2026-08-28 11:48:16'),
(97, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260828-0061-1655 (TSh 30,000) for patient ID #61 - Specialist Consultation', '', 'cashier_dashboard.php', 0, '2026-08-28 12:25:19', '2026-08-28 12:25:19'),
(98, 5, 1, 61, '📋 New Referral Received', 'New referral from Dr. Dr.ERICK JOHN for patient MUSSA MONGI MASNGI. Patient has been assigned to you.', 'info', 'my_patients.php', 0, '2026-08-28 12:25:57', '2026-08-28 12:25:57'),
(99, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260829-0061-1889 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-08-28 22:31:23', '2026-08-28 22:31:23'),
(100, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260829-0060-8014 (TSh 10,000) for patient ID #60 - New Patient', '', 'cashier_dashboard.php', 0, '2026-08-28 22:31:50', '2026-08-28 22:31:50'),
(101, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260829-0059-6454 (TSh 10,000) for patient ID #59 - New Patient', '', 'cashier_dashboard.php', 0, '2026-08-28 22:32:09', '2026-08-28 22:32:09'),
(102, 5, 1, 61, '📋 New Referral Received', 'New referral from Dr. Dr.ERICK JOHN for patient MUSSA MONGI MASNGI. Patient has been assigned to you.', 'info', 'consultations.php', 0, '2026-08-28 22:33:07', '2026-08-28 22:33:07'),
(103, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260829-0061-5325 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-08-28 22:55:58', '2026-08-28 22:55:58'),
(104, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260829-0060-9332 (TSh 10,000) for patient ID #60 - New Patient', '', 'cashier_dashboard.php', 0, '2026-08-28 23:23:35', '2026-08-28 23:23:35'),
(105, 5, 1, 60, '📋 New Referral Received', 'New referral from Dr. Dr.ERICK JOHN for patient ANDREW VICENT CHIKUPE. Patient has been assigned to you.', 'info', 'consultations.php', 0, '2026-08-28 23:26:19', '2026-08-28 23:26:19'),
(106, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260829-0061-9577 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-08-28 23:33:51', '2026-08-28 23:33:51'),
(107, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260829-0060-7317 (TSh 10,000) for patient ID #60 - New Patient', '', 'cashier_dashboard.php', 0, '2026-08-28 23:34:03', '2026-08-28 23:34:03'),
(108, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260829-0059-7288 (TSh 10,000) for patient ID #59 - New Patient', '', 'cashier_dashboard.php', 0, '2026-08-28 23:34:14', '2026-08-28 23:34:14'),
(109, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260829-0058-3140 (TSh 10,000) for patient ID #58 - New Patient', '', 'cashier_dashboard.php', 0, '2026-08-28 23:34:24', '2026-08-28 23:34:24'),
(110, 5, 1, 59, '📋 New Referral Received', 'New referral from Dr. Dr.ERICK JOHN for patient AMOSI NGOMENI. Patient has been assigned to you.', 'info', 'consultations.php', 0, '2026-08-28 23:35:35', '2026-08-28 23:35:35'),
(111, 5, 1, 60, '📋 New Referral Received', 'New referral from Dr. Dr.ERICK JOHN for patient ANDREW VICENT CHIKUPE. Patient has been assigned to you.', 'info', 'consultations.php', 0, '2026-08-28 23:35:35', '2026-08-28 23:35:35'),
(112, 10, 1, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr.ERICK JOHN is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-08-29 16:55:47', '2026-08-29 16:55:47'),
(113, 11, 1, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr.ERICK JOHN is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-08-29 16:55:47', '2026-08-29 16:55:47'),
(114, 12, 1, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr.ERICK JOHN is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-08-29 16:55:47', '2026-08-29 16:55:47'),
(115, 1, 1, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr.ERICK JOHN is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-08-29 16:55:47', '2026-08-29 16:55:47'),
(116, 3, 1, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr.ERICK JOHN is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-08-29 16:55:47', '2026-08-29 16:55:47'),
(117, 10, 1, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr.ERICK JOHN is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-08-29 16:55:48', '2026-08-29 16:55:48'),
(118, 11, 1, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr.ERICK JOHN is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-08-29 16:55:48', '2026-08-29 16:55:48'),
(119, 12, 1, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr.ERICK JOHN is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-08-29 16:55:48', '2026-08-29 16:55:48'),
(120, 1, 1, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr.ERICK JOHN is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-08-29 16:55:48', '2026-08-29 16:55:48'),
(121, 3, 1, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr.ERICK JOHN is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-08-29 16:55:48', '2026-08-29 16:55:48'),
(122, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260829-0061-8931 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-08-29 17:51:46', '2026-08-29 17:51:46'),
(123, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260829-0060-9642 (TSh 30,000) for patient ID #60 - Specialist Consultation', '', 'cashier_dashboard.php', 0, '2026-08-29 17:52:57', '2026-08-29 17:52:57'),
(124, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260829-0059-9511 (TSh 10,000) for patient ID #59 - New Patient', '', 'cashier_dashboard.php', 0, '2026-08-29 17:53:52', '2026-08-29 17:53:52'),
(125, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260829-0058-1722 (TSh 100,000) for patient ID #58 - visit_mpya', '', 'cashier_dashboard.php', 0, '2026-08-29 17:55:12', '2026-08-29 17:55:12'),
(126, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260829-0056-7503 (TSh 15,000) for patient ID #56 - General Consultation', '', 'cashier_dashboard.php', 0, '2026-08-29 17:57:27', '2026-08-29 17:57:27'),
(127, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260829-0055-6384 (TSh 25,000) for patient ID #55 - Consultation-B', '', 'cashier_dashboard.php', 0, '2026-08-29 17:59:00', '2026-08-29 17:59:00'),
(128, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260829-0054-9975 (TSh 10,000) for patient ID #54 - New Patient', '', 'cashier_dashboard.php', 0, '2026-08-29 17:59:59', '2026-08-29 17:59:59'),
(129, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260829-0053-5799 (TSh 30,000) for patient ID #53 - Specialist Consultation', '', 'cashier_dashboard.php', 0, '2026-08-29 18:01:12', '2026-08-29 18:01:12'),
(130, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260829-0052-9068 (TSh 15,000) for patient ID #52 - General Consultation', '', 'cashier_dashboard.php', 0, '2026-08-29 18:02:22', '2026-08-29 18:02:22'),
(131, 10, 1, NULL, 'Doctor Status: ONLINE', 'Dr. Dr. Anna Kivuyo is now ONLINE', 'info', 'assign_doctor.php', 0, '2026-08-29 21:52:57', '2026-08-29 21:52:57'),
(132, 11, 1, NULL, 'Doctor Status: ONLINE', 'Dr. Dr. Anna Kivuyo is now ONLINE', 'info', 'assign_doctor.php', 0, '2026-08-29 21:52:57', '2026-08-29 21:52:57'),
(133, 12, 1, NULL, 'Doctor Status: ONLINE', 'Dr. Dr. Anna Kivuyo is now ONLINE', 'info', 'assign_doctor.php', 0, '2026-08-29 21:52:57', '2026-08-29 21:52:57'),
(134, 23, 1, NULL, 'Doctor Status: ONLINE', 'Dr. Dr. Anna Kivuyo is now ONLINE', 'info', 'assign_doctor.php', 0, '2026-08-29 21:52:57', '2026-08-29 21:52:57'),
(135, 24, 1, NULL, 'Doctor Status: ONLINE', 'Dr. Dr. Anna Kivuyo is now ONLINE', 'info', 'assign_doctor.php', 0, '2026-08-29 21:52:57', '2026-08-29 21:52:57'),
(136, 25, 1, NULL, 'Doctor Status: ONLINE', 'Dr. Dr. Anna Kivuyo is now ONLINE', 'info', 'assign_doctor.php', 0, '2026-08-29 21:52:57', '2026-08-29 21:52:57'),
(137, 36, 1, NULL, 'Doctor Status: ONLINE', 'Dr. Dr. Anna Kivuyo is now ONLINE', 'info', 'assign_doctor.php', 0, '2026-08-29 21:52:57', '2026-08-29 21:52:57'),
(138, 37, 1, NULL, 'Doctor Status: ONLINE', 'Dr. Dr. Anna Kivuyo is now ONLINE', 'info', 'assign_doctor.php', 0, '2026-08-29 21:52:57', '2026-08-29 21:52:57'),
(139, 38, 1, NULL, 'Doctor Status: ONLINE', 'Dr. Dr. Anna Kivuyo is now ONLINE', 'info', 'assign_doctor.php', 0, '2026-08-29 21:52:57', '2026-08-29 21:52:57'),
(140, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260902-0061-9460 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-02 09:40:17', '2026-09-02 09:40:17'),
(141, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260902-0061-8839 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-02 09:55:56', '2026-09-02 09:55:56'),
(142, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260903-0061-3277 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-02 22:10:31', '2026-09-02 22:10:31'),
(143, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260903-0060-5085 (TSh 10,000) for patient ID #60 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-02 22:11:18', '2026-09-02 22:11:18'),
(144, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260903-0059-7260 (TSh 10,000) for patient ID #59 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-02 22:12:55', '2026-09-02 22:12:55'),
(145, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260903-0058-2199 (TSh 10,000) for patient ID #58 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-02 22:32:58', '2026-09-02 22:32:58'),
(146, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260903-0057-3020 (TSh 10,000) for patient ID #57 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-02 22:33:39', '2026-09-02 22:33:39'),
(147, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260903-0056-5563 (TSh 10,000) for patient ID #56 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-02 22:34:17', '2026-09-02 22:34:17'),
(148, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260903-0052-3518 (TSh 10,000) for patient ID #52 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-02 22:35:06', '2026-09-02 22:35:06'),
(149, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260903-0061-3637 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-03 20:43:16', '2026-09-03 20:43:16'),
(150, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260904-0061-7488 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-03 22:24:25', '2026-09-03 22:24:25'),
(151, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260904-0061-9548 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-03 22:46:52', '2026-09-03 22:46:52'),
(152, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260904-0061-9288 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-03 22:53:08', '2026-09-03 22:53:08'),
(153, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260904-0061-3682 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-03 23:03:53', '2026-09-03 23:03:53'),
(154, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260904-0060-1054 (TSh 10,000) for patient ID #60 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-03 23:10:51', '2026-09-03 23:10:51'),
(155, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260904-0061-3087 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-03 23:29:51', '2026-09-03 23:29:51'),
(156, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260904-0061-5669 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-04 00:01:54', '2026-09-04 00:01:54'),
(157, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260904-0061-6809 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-04 00:28:42', '2026-09-04 00:28:42'),
(158, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260904-0061-1837 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-04 12:00:52', '2026-09-04 12:00:52'),
(159, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260904-0061-2449 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-04 16:31:27', '2026-09-04 16:31:27'),
(160, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260904-0060-1517 (TSh 10,000) for patient ID #60 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-04 16:32:05', '2026-09-04 16:32:05'),
(161, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260904-0059-7916 (TSh 10,000) for patient ID #59 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-04 16:41:54', '2026-09-04 16:41:54'),
(162, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260904-0051-6523 (TSh 10,000) for patient ID #51 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-04 16:54:06', '2026-09-04 16:54:06'),
(163, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260904-0061-8469 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-04 17:05:44', '2026-09-04 17:05:44'),
(164, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260904-0060-7669 (TSh 10,000) for patient ID #60 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-04 17:06:02', '2026-09-04 17:06:02'),
(165, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260904-0059-7011 (TSh 10,000) for patient ID #59 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-04 17:06:14', '2026-09-04 17:06:14'),
(166, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260904-0061-2707 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-04 18:47:44', '2026-09-04 18:47:44'),
(167, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260904-0061-8652 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-04 19:56:53', '2026-09-04 19:56:53'),
(168, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260904-0061-6787 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-04 20:02:37', '2026-09-04 20:02:37'),
(169, 16, 1, NULL, '🧪 Lab Test Bill Created', 'Lab Test bill #BILL-LAB-20260904-0060-7366 (TSh 23,000) for patient ID #60 - Blood Glucose (Random), COVID-19 Rapid Antigen Test', '', 'cashier_dashboard.php', 0, '2026-09-04 20:06:23', '2026-09-04 20:06:23'),
(170, 16, 1, NULL, '🧪 Lab Test Bill Created', 'Lab Test bill #BILL-LAB-20260904-0059-8623 (TSh 28,000) for patient ID #59 - Blood Glucose (Random), Lipid Profile', '', 'cashier_dashboard.php', 0, '2026-09-04 20:08:51', '2026-09-04 20:08:51'),
(171, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260904-0061-7319 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-04 20:36:07', '2026-09-04 20:36:07'),
(172, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260904-0060-2983 (TSh 10,000) for patient ID #60 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-04 21:51:00', '2026-09-04 21:51:00'),
(173, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260905-0061-7338 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-04 22:02:33', '2026-09-04 22:02:33'),
(174, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260905-0061-6634 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-04 22:08:06', '2026-09-04 22:08:06'),
(175, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260905-0061-3908 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-04 22:14:58', '2026-09-04 22:14:58'),
(176, 10, 1, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr.ERICK JOHN is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-09-04 22:15:03', '2026-09-04 22:15:03'),
(177, 11, 1, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr.ERICK JOHN is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-09-04 22:15:03', '2026-09-04 22:15:03'),
(178, 12, 1, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr.ERICK JOHN is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-09-04 22:15:03', '2026-09-04 22:15:03'),
(179, 1, 1, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr.ERICK JOHN is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-09-04 22:15:03', '2026-09-04 22:15:03'),
(180, 3, 1, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr.ERICK JOHN is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-09-04 22:15:03', '2026-09-04 22:15:03'),
(181, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260905-0061-7628 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-04 23:10:35', '2026-09-04 23:10:35'),
(182, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260905-0061-6404 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-04 23:36:01', '2026-09-04 23:36:01'),
(183, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260905-0061-8941 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-05 07:32:35', '2026-09-05 07:32:35'),
(184, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260905-0061-4566 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-05 09:05:14', '2026-09-05 09:05:14'),
(185, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260905-0061-3768 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-05 09:37:27', '2026-09-05 09:37:27'),
(186, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260905-0061-5131 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-05 11:55:37', '2026-09-05 11:55:37'),
(187, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260905-0061-6963 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-05 13:10:44', '2026-09-05 13:10:44'),
(188, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260905-0061-2282 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-05 13:54:42', '2026-09-05 13:54:42'),
(189, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260905-0061-1270 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-05 14:19:32', '2026-09-05 14:19:32'),
(190, 10, 1, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr.ERICK JOHN is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-09-05 14:19:48', '2026-09-05 14:19:48'),
(191, 11, 1, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr.ERICK JOHN is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-09-05 14:19:48', '2026-09-05 14:19:48'),
(192, 12, 1, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr.ERICK JOHN is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-09-05 14:19:48', '2026-09-05 14:19:48'),
(193, 1, 1, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr.ERICK JOHN is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-09-05 14:19:48', '2026-09-05 14:19:48'),
(194, 3, 1, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr.ERICK JOHN is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-09-05 14:19:48', '2026-09-05 14:19:48'),
(195, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260905-0061-2474 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-05 14:41:00', '2026-09-05 14:41:00'),
(196, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260905-0061-1807 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-05 14:53:57', '2026-09-05 14:53:57'),
(197, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260905-0058-1624 (TSh 10,000) for patient ID #58 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-05 15:05:58', '2026-09-05 15:05:58'),
(198, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260905-0061-1874 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-05 19:57:44', '2026-09-05 19:57:44'),
(199, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260905-0061-2734 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-05 20:35:17', '2026-09-05 20:35:17'),
(200, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260905-0061-1572 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-05 20:57:41', '2026-09-05 20:57:41'),
(201, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260905-0061-5709 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-05 21:07:24', '2026-09-05 21:07:24'),
(202, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260905-0061-8116 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-05 21:12:25', '2026-09-05 21:12:25'),
(203, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260905-0047-8488 (TSh 10,000) for patient ID #47 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-05 21:20:05', '2026-09-05 21:20:05'),
(204, 16, 1, NULL, '🧪 Lab Test Bill Created', 'Lab Test bill #BILL-LAB-20260905-0060-7835 (TSh 75,000) for patient ID #60 - Complete Blood Count (CBC), Echocardiogram', '', 'cashier_dashboard.php', 0, '2026-09-05 21:22:40', '2026-09-05 21:22:40'),
(205, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260905-0061-2248 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-05 21:31:11', '2026-09-05 21:31:11'),
(206, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260906-0047-3162 (TSh 10,000) for patient ID #47 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-05 22:10:52', '2026-09-05 22:10:52'),
(207, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260906-0061-5836 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-06 06:02:59', '2026-09-06 06:02:59'),
(208, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260906-0049-5875 (TSh 10,000) for patient ID #49 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-06 06:25:09', '2026-09-06 06:25:09'),
(209, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260906-0060-6689 (TSh 10,000) for patient ID #60 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-06 08:49:26', '2026-09-06 08:49:26'),
(210, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260906-0061-6135 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-06 11:22:29', '2026-09-06 11:22:29'),
(211, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260906-0061-2927 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-06 12:36:46', '2026-09-06 12:36:46'),
(212, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260906-0058-7827 (TSh 10,000) for patient ID #58 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-06 12:44:40', '2026-09-06 12:44:40'),
(213, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260906-0061-9635 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-06 13:03:45', '2026-09-06 13:03:45'),
(214, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260906-0061-7149 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-06 14:08:36', '2026-09-06 14:08:36'),
(215, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260906-0061-3508 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-06 14:45:42', '2026-09-06 14:45:42'),
(216, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260909-0059-9908 (TSh 10,000) for patient ID #59 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-09 09:42:09', '2026-09-09 09:42:09'),
(217, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260909-0061-1567 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-09 14:32:23', '2026-09-09 14:32:23'),
(218, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260909-0061-6773 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-09 15:17:20', '2026-09-09 15:17:20'),
(219, 10, 1, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr.ERICK JOHN is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-09-09 15:46:46', '2026-09-09 15:46:46'),
(220, 11, 1, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr.ERICK JOHN is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-09-09 15:46:46', '2026-09-09 15:46:46'),
(221, 12, 1, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr.ERICK JOHN is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-09-09 15:46:46', '2026-09-09 15:46:46'),
(222, 1, 1, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr.ERICK JOHN is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-09-09 15:46:46', '2026-09-09 15:46:46'),
(223, 3, 1, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr.ERICK JOHN is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-09-09 15:46:46', '2026-09-09 15:46:46'),
(224, 10, 1, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr.ERICK JOHN is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-09-09 15:46:47', '2026-09-09 15:46:47'),
(225, 11, 1, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr.ERICK JOHN is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-09-09 15:46:47', '2026-09-09 15:46:47'),
(226, 12, 1, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr.ERICK JOHN is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-09-09 15:46:47', '2026-09-09 15:46:47'),
(227, 1, 1, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr.ERICK JOHN is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-09-09 15:46:47', '2026-09-09 15:46:47'),
(228, 3, 1, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr.ERICK JOHN is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-09-09 15:46:47', '2026-09-09 15:46:47');

-- --------------------------------------------------------

--
-- Table structure for table `otc_sales`
--

CREATE TABLE `otc_sales` (
  `id` int(11) NOT NULL,
  `sale_number` varchar(50) NOT NULL,
  `customer_name` varchar(100) DEFAULT 'Walk-in Customer',
  `customer_phone` varchar(20) DEFAULT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `subtotal` decimal(12,2) DEFAULT 0.00,
  `discount_amount` decimal(12,2) DEFAULT 0.00,
  `premium_amount` decimal(10,2) DEFAULT 0.00,
  `premium_note` varchar(255) DEFAULT NULL,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `bill_id` int(11) DEFAULT NULL,
  `payment_method` enum('cash','card','m-pesa','airtel_money','tigo_pesa','other') DEFAULT 'cash',
  `payment_status` enum('pending','paid','partial','cancelled') DEFAULT 'pending',
  `sold_by` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `otc_sales`
--

INSERT INTO `otc_sales` (`id`, `sale_number`, `customer_name`, `customer_phone`, `patient_id`, `subtotal`, `discount_amount`, `premium_amount`, `premium_note`, `total_amount`, `bill_id`, `payment_method`, `payment_status`, `sold_by`, `branch_id`, `notes`, `created_at`, `updated_at`) VALUES
(18, 'OTC-20260906-7628', 'HANIFA', '0710111212', NULL, 15000.00, 5000.00, 0.00, NULL, 10000.00, NULL, 'cash', 'paid', 10, 1, 'OTC Sale - Bill sent to Cashier - Customer: HANIFA', '2026-09-06 12:53:35', '2026-09-06 12:54:07'),
(19, 'OTC-20260908-3495', 'kelvin', '0746526243', NULL, 10000.00, 0.00, 0.00, NULL, 10000.00, NULL, 'cash', 'paid', 7, 1, 'Paid by Pharmacy (Self) - Customer: kelvin', '2026-09-08 08:36:03', '2026-09-08 08:36:03'),
(20, 'OTC-20260908-0455', 'Walk-in Customer', '', NULL, 20000.00, 0.00, 5000.00, 'Premium added', 25000.00, NULL, 'cash', 'paid', 7, 1, 'Paid by Pharmacy (Self) - Customer: Walk-in Customer | Premium: TSh 5,000 - Premium added', '2026-09-08 09:26:54', '2026-09-08 09:26:54');

-- --------------------------------------------------------

--
-- Table structure for table `otc_sale_items`
--

CREATE TABLE `otc_sale_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `inventory_id` int(11) DEFAULT NULL,
  `medicine_name` varchar(100) DEFAULT NULL,
  `item_name` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `total_price` decimal(12,2) NOT NULL,
  `instructions` text DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `otc_sale_items`
--

INSERT INTO `otc_sale_items` (`id`, `sale_id`, `patient_id`, `inventory_id`, `medicine_name`, `item_name`, `quantity`, `unit_price`, `total_price`, `instructions`, `branch_id`, `created_at`) VALUES
(17, 18, NULL, NULL, NULL, 'ALBENDAZOLE', 10, 1500.00, 15000.00, 'Before meals', 1, '2026-09-06 12:53:35'),
(18, 19, NULL, NULL, NULL, 'AMOXILINE', 20, 500.00, 10000.00, '', 1, '2026-09-08 08:36:03'),
(19, 20, NULL, NULL, NULL, 'ALBEDAZOLE', 10, 2000.00, 20000.00, 'Before meals', 1, '2026-09-08 09:26:54');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `token` varchar(255) NOT NULL,
  `otp` varchar(10) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `patient_id` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `marital_status` varchar(20) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `emergency_contact` varchar(20) DEFAULT NULL,
  `blood_group` varchar(5) DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `assigned_doctor_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `patient_id`, `full_name`, `date_of_birth`, `gender`, `marital_status`, `phone`, `email`, `address`, `emergency_contact`, `blood_group`, `allergies`, `branch_id`, `created_by`, `assigned_doctor_id`, `created_at`, `updated_at`) VALUES
(47, 'P-2026-01-0001', 'MARTHA KIMAMALA', '2003-09-23', 'Female', 'Single', '0616171819', 'marthakimamala@gmail.com', 'DODOMA - KISASA SHELI', '0623693303', 'AB+', 'Penicillin, Sulfa Drugs', 1, 10, 4, '2026-08-25 21:18:56', '2026-09-05 21:20:05'),
(48, 'P-2026-01-0002', 'IBRAHIM DOUMBIA', '2003-09-10', 'Male', 'Single', '0746512183', 'doumbia@gmail.com', 'KISASA SHELI', '0622682202', 'O+', 'Sulfa Drugs, Aspirin', 1, 10, 4, '2026-08-25 22:00:07', '2026-08-26 19:26:39'),
(49, 'P-2026-01-0003', 'AGUSTINO VALENTINE', '2003-02-15', 'Male', 'Single', '0678552288', 'augustino@gmail.com', 'kiasa', '0678723', 'AB-', 'Sulfa Drugs, Soy', 1, 10, 4, '2026-08-26 12:29:24', '2026-09-06 06:25:09'),
(50, 'P-2026-01-0004', 'KELVIN MSAFIRI', '2001-09-12', 'Male', '', '09876525', 'kelvin@gmail.com', 'kisasa', '0678723123', 'AB-', 'Penicillin, Sulfa Drugs', 1, 10, NULL, '2026-08-26 13:03:37', '2026-09-04 19:18:32'),
(51, 'P-2026-01-0005', 'CLEOFAS WILLIUM', '2001-07-18', 'Male', 'Single', '0746526253', 'jacksonmyula3@gmail.com', 'mtakumbuka', '067872311', 'AB-', 'Penicillin, Milk', 1, 11, 4, '2026-08-26 18:36:46', '2026-08-26 18:36:46'),
(52, 'P-2026-01-0006', 'JUDITH SOLOMONI', '2002-04-09', 'Female', 'Single', '0678176542', 'judithsolomoni@gmail.com', '', '', 'O+', 'Penicillin, Milk', 1, 11, 4, '2026-08-26 19:23:28', '2026-08-29 18:02:22'),
(53, 'P-2026-01-0007', 'MAGRETH CHAKUPEWA', '2002-05-19', 'Female', 'Married', '0987536818', 'magreth@gmail.com', '', '', 'B-', 'Penicillin, Milk', 1, 11, 5, '2026-08-26 19:24:36', '2026-08-29 18:01:12'),
(54, 'P-2026-01-0008', 'CLEMENCY MTUKA', '2001-10-10', 'Male', 'Single', '0746526111', 'clemecy@gmail.com', 'mtakumbuka', '', 'B-', 'Ibuprofen', 1, 11, 5, '2026-08-26 19:55:59', '2026-08-29 17:59:59'),
(55, 'P-2026-01-0009', 'ALPHONSE MABULA', '1998-02-12', 'Male', '', '0787615242', 'alphonce@gmail.com', '', '0678723133', 'AB-', 'Sulfa Drugs', 1, 11, 6, '2026-08-26 19:59:21', '2026-08-29 17:59:00'),
(56, 'P-2026-01-0010', 'julieth kalinde', '2001-09-13', 'Male', '', '0789189123', 'juliath@gmail.com', '', '', 'AB+', 'Penicillin', 1, 11, 4, '2026-08-26 20:07:21', '2026-09-02 22:34:17'),
(57, 'P-2026-01-0011', 'VICTORIA SALINGO', '2008-03-12', 'Male', 'Single', '074671827361', 'victoria@gmail.com', '', '', '', '', 1, 11, 6, '2026-08-26 20:22:10', '2026-09-02 22:33:39'),
(58, 'P-2026-01-0012', 'AYUBU NZAL', '1992-08-12', 'Male', 'Married', '0765457899', 'ayubunzali@gmail.com', '', '', 'A+', '', 1, 11, 4, '2026-08-26 20:35:50', '2026-08-26 20:35:50'),
(59, 'P-2026-01-0013', 'AMOSI NGOMENI', '2000-12-12', 'Male', 'Single', '0756176210', 'amosi@gmail.com', '', '', 'A+', '', 1, 11, 4, '2026-08-26 20:52:13', '2026-09-09 09:42:09'),
(60, 'P-2026-01-0014', 'ANDREW VICENT CHIKUPE', '1993-07-10', 'Male', '', '0746826243', 'endrew@gmail.com', 'mtakumbuka', '0678723129', 'B-', 'Aspirin', 1, 11, 4, '2026-08-26 21:00:03', '2026-09-06 08:49:26'),
(61, 'P-2026-01-0015', 'MUSSA MONGI MASNGI', '2003-08-01', 'Male', 'Single', '0789878980', 'musa@gmail.com', '', '', '', 'Sulfa Drugs', 1, 11, 4, '2026-08-26 21:11:23', '2026-08-28 22:55:58');

-- --------------------------------------------------------

--
-- Table structure for table `patient_documents`
--

CREATE TABLE `patient_documents` (
  `id` int(11) NOT NULL,
  `document_number` varchar(50) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `visit_id` int(11) DEFAULT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `document_type` enum('medical_record','referral_letter','lab_result','prescription','x_ray','scan','ultrasound','insurance','id_document','sick_sheet','consent_form','other') NOT NULL DEFAULT 'other',
  `document_name` varchar(255) NOT NULL,
  `document_title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `sick_sheet_days` int(11) DEFAULT NULL,
  `sick_sheet_from_date` date DEFAULT NULL,
  `sick_sheet_to_date` date DEFAULT NULL,
  `sick_sheet_diagnosis` text DEFAULT NULL,
  `sick_sheet_recommendations` text DEFAULT NULL,
  `sick_sheet_restrictions` text DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `verified_by` int(11) DEFAULT NULL,
  `verified_date` timestamp NULL DEFAULT NULL,
  `status` enum('active','archived','deleted') DEFAULT 'active',
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `receipt_number` varchar(50) NOT NULL,
  `bill_id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_method` enum('cash','m-pesa','airtel_money','tigo_pesa','halopesa','bank','card','insurance','other') DEFAULT 'cash',
  `reference_number` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `received_by` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `received_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL,
  `prescription_number` varchar(50) NOT NULL,
  `visit_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `pharmacy_id` int(11) DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','confirmed','dispensed','cancelled') DEFAULT 'pending',
  `branch_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `dispensed_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prescription_items`
--

CREATE TABLE `prescription_items` (
  `id` int(11) NOT NULL,
  `prescription_id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `inventory_id` int(11) DEFAULT NULL,
  `medication_name` varchar(100) NOT NULL,
  `dosage` varchar(50) DEFAULT NULL,
  `frequency` varchar(50) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `duration` varchar(50) DEFAULT NULL,
  `route` varchar(50) DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `pharmacy_instructions` text DEFAULT NULL,
  `pharmacy_instruction_mode` varchar(20) DEFAULT 'manual',
  `pharmacy_instruction_updated_at` timestamp NULL DEFAULT NULL,
  `pharmacy_instruction_updated_by` int(11) DEFAULT NULL,
  `unit_price` decimal(12,2) DEFAULT 0.00,
  `total_price` decimal(12,2) DEFAULT 0.00,
  `branch_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `dispensed_at` timestamp NULL DEFAULT NULL,
  `dispensed_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `procedures`
--

CREATE TABLE `procedures` (
  `id` int(11) NOT NULL,
  `visit_id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `procedure_id` int(11) DEFAULT NULL,
  `procedure_name` varchar(100) NOT NULL,
  `procedure_category` varchar(100) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `procedure_code` varchar(50) DEFAULT NULL,
  `procedure_price` decimal(12,2) DEFAULT 0.00,
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `branch_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `performed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `procedures_catalog`
--

CREATE TABLE `procedures_catalog` (
  `id` int(11) NOT NULL,
  `procedure_name` varchar(100) NOT NULL,
  `procedure_code` varchar(20) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `required_equipment_id` int(11) DEFAULT NULL,
  `equipment_quantity_used` int(11) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `branch_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `procedures_catalog`
--

INSERT INTO `procedures_catalog` (`id`, `procedure_name`, `procedure_code`, `category`, `price`, `description`, `required_equipment_id`, `equipment_quantity_used`, `is_active`, `branch_id`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'WOUND DRESSING', 'PROC-20260824-9357', 'Procedures', 45000.00, '', NULL, 1, 1, 1, 4, '2026-08-24 09:57:23', '2026-08-24 09:57:23'),
(12, 'Wound Dressing', 'PROC-WD-001', 'Wound Care', 25000.00, 'Cleaning and dressing of wounds', NULL, 1, 1, 1, 1, '2026-08-24 14:43:09', '2026-08-24 14:43:09'),
(13, 'Suture Removal', 'PROC-SR-001', 'Wound Care', 15000.00, 'Removal of surgical sutures', NULL, 1, 1, 1, 1, '2026-08-24 14:43:09', '2026-08-24 14:43:09'),
(14, 'Incision and Drainage', 'PROC-ID-001', 'Surgery', 35000.00, 'Incision and drainage of abscess', NULL, 1, 1, 1, 1, '2026-08-24 14:43:09', '2026-08-24 14:43:09'),
(15, 'ECG - Electrocardiogram', 'PROC-ECG-001', 'Cardiology', 15000.00, '12-lead ECG recording', NULL, 1, 1, 1, 1, '2026-08-24 14:43:09', '2026-08-24 14:43:09'),
(16, 'Spirometry - Lung Function', 'PROC-SPI-001', 'Pulmonology', 25000.00, 'Pulmonary function test', NULL, 1, 1, 1, 1, '2026-08-24 14:43:09', '2026-08-24 14:43:09'),
(17, 'Minor Surgery - Excision', 'PROC-MS-001', 'Surgery', 50000.00, 'Excision of small lesions/tumors', NULL, 1, 1, 1, 1, '2026-08-24 14:43:09', '2026-08-24 14:43:09'),
(18, 'Cryotherapy', 'PROC-CRY-001', 'Dermatology', 20000.00, 'Cryotherapy for skin lesions', NULL, 1, 1, 1, 1, '2026-08-24 14:43:09', '2026-08-24 14:43:09'),
(19, 'Free - Health Education', 'PROC-FREE-001', 'Education', 0.00, 'Patient health education session', NULL, 1, 1, 1, 1, '2026-08-24 14:43:09', '2026-08-24 14:43:09'),
(20, 'Free - Nutrition Counseling', 'PROC-FREE-002', 'Nutrition', 0.00, 'Nutrition and dietary counseling', NULL, 1, 1, 1, 1, '2026-08-24 14:43:09', '2026-08-24 14:43:09'),
(21, 'Free - Post-operative Check', 'PROC-FREE-003', 'Post-op Care', 0.00, 'Post-operative follow-up examination', NULL, 1, 1, 1, 1, '2026-08-24 14:43:09', '2026-08-24 14:43:09'),
(22, 'wound dressig', 'PROC-2026-001', 'Procedures', 45000.00, '', NULL, 1, 1, 2, 1, '2026-08-29 23:22:11', '2026-08-29 23:22:11');

-- --------------------------------------------------------

--
-- Table structure for table `purchases`
--

CREATE TABLE `purchases` (
  `id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `purchase_type` enum('medicine','equipment') NOT NULL DEFAULT 'medicine',
  `created_by` int(11) NOT NULL,
  `joined_users` text DEFAULT NULL,
  `created_by_name` varchar(100) NOT NULL,
  `status` enum('IN_PROGRESS','COMPLETED','CANCELLED') DEFAULT 'IN_PROGRESS',
  `cancelled_reason` text DEFAULT NULL,
  `cancelled_by` int(11) DEFAULT NULL,
  `total_items` int(11) DEFAULT 0,
  `total_quantity` int(11) DEFAULT 0,
  `total_buying_cost` decimal(15,2) DEFAULT 0.00,
  `total_selling_value` decimal(15,2) DEFAULT 0.00,
  `total_cost` decimal(15,2) DEFAULT 0.00,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchases`
--

INSERT INTO `purchases` (`id`, `invoice_number`, `purchase_type`, `created_by`, `joined_users`, `created_by_name`, `status`, `cancelled_reason`, `cancelled_by`, `total_items`, `total_quantity`, `total_buying_cost`, `total_selling_value`, `total_cost`, `completed_at`, `created_at`, `updated_at`) VALUES
(5, 'INV-MED-20260908-0001', 'medicine', 8, NULL, 'Mary John', 'COMPLETED', NULL, NULL, 2, 600, 720000.00, 1700000.00, 0.00, '2026-09-08 16:42:37', '2026-09-08 16:38:02', '2026-09-08 16:42:37'),
(6, 'INV-EQP-20260908-0001', 'equipment', 7, NULL, 'LUCY MUSSA', 'COMPLETED', NULL, NULL, 1, 90, 90000.00, 225000.00, 0.00, '2026-09-08 20:02:40', '2026-09-08 17:27:53', '2026-09-08 20:02:40'),
(7, 'INV-EQP-20260908-0002', 'equipment', 4, NULL, 'Dr.ERICK JOHN', 'COMPLETED', NULL, NULL, 1, 100, 100000.00, 200000.00, 0.00, '2026-09-09 16:16:29', '2026-09-08 17:29:50', '2026-09-09 16:16:29'),
(8, 'INV-MED-20260908-0002', 'medicine', 1, NULL, 'System Admin', 'IN_PROGRESS', NULL, NULL, 0, 0, 0.00, 0.00, 0.00, NULL, '2026-09-08 18:48:29', '2026-09-08 18:48:29'),
(9, 'INV-EQP-20260909-0001', 'equipment', 1, NULL, 'System Admin', 'COMPLETED', NULL, NULL, 2, 500, 350000.00, 850000.00, 0.00, '2026-09-09 16:37:29', '2026-09-09 16:34:48', '2026-09-09 16:37:29'),
(10, 'INV-EQP-20260909-0002', 'equipment', 1, NULL, 'System Admin', 'COMPLETED', NULL, NULL, 1, 100, 50000.00, 120000.00, 0.00, '2026-09-09 16:52:29', '2026-09-09 16:51:51', '2026-09-09 16:52:29');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_items`
--

CREATE TABLE `purchase_items` (
  `id` int(11) NOT NULL,
  `purchase_id` int(11) NOT NULL,
  `item_type` enum('medicine','equipment') NOT NULL DEFAULT 'medicine',
  `medicine_id` int(11) DEFAULT NULL,
  `equipment_id` int(11) DEFAULT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `buying_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `selling_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `buying_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_buying_cost` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_selling_value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `added_by` int(11) NOT NULL,
  `added_by_name` varchar(100) NOT NULL,
  `added_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_items`
--

INSERT INTO `purchase_items` (`id`, `purchase_id`, `item_type`, `medicine_id`, `equipment_id`, `item_id`, `quantity`, `unit_price`, `buying_price`, `selling_price`, `buying_total`, `total_price`, `total_buying_cost`, `total_selling_value`, `added_by`, `added_by_name`, `added_at`) VALUES
(11, 5, 'medicine', NULL, NULL, 25, 400, 0.00, 1300.00, 3000.00, 0.00, 0.00, 520000.00, 1200000.00, 8, 'Mary John', '2026-09-08 16:38:44'),
(12, 5, 'medicine', NULL, NULL, 26, 200, 0.00, 1000.00, 2500.00, 0.00, 0.00, 200000.00, 500000.00, 8, 'Mary John', '2026-09-08 16:40:09'),
(13, 6, 'equipment', NULL, NULL, 38, 90, 0.00, 1000.00, 2500.00, 0.00, 0.00, 90000.00, 225000.00, 7, 'LUCY MUSSA', '2026-09-08 17:28:33'),
(14, 7, 'equipment', NULL, NULL, 39, 100, 0.00, 1000.00, 2000.00, 0.00, 0.00, 100000.00, 200000.00, 4, 'Dr.ERICK JOHN', '2026-09-08 17:29:50'),
(15, 9, 'equipment', NULL, NULL, 40, 300, 0.00, 500.00, 1500.00, 0.00, 0.00, 150000.00, 450000.00, 1, 'System Admin', '2026-09-09 16:35:41'),
(16, 9, 'equipment', NULL, NULL, 41, 200, 0.00, 1000.00, 2000.00, 0.00, 0.00, 200000.00, 400000.00, 7, 'LUCY MUSSA', '2026-09-09 16:36:49'),
(17, 10, 'equipment', NULL, NULL, 41, 100, 0.00, 500.00, 1200.00, 0.00, 0.00, 50000.00, 120000.00, 1, 'System Admin', '2026-09-09 16:52:22');

-- --------------------------------------------------------

--
-- Table structure for table `receipts`
--

CREATE TABLE `receipts` (
  `id` int(11) NOT NULL,
  `receipt_number` varchar(50) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `bill_id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `receipt_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`receipt_data`)),
  `printed_by` int(11) DEFAULT NULL,
  `printed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `downloaded_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `referrals`
--

CREATE TABLE `referrals` (
  `id` int(11) NOT NULL,
  `referral_number` varchar(50) NOT NULL,
  `visit_id` int(11) DEFAULT NULL,
  `patient_id` int(11) NOT NULL,
  `from_doctor_id` int(11) NOT NULL,
  `referral_type` enum('internal','external') NOT NULL DEFAULT 'internal',
  `to_doctor_id` int(11) DEFAULT NULL,
  `to_hospital_name` varchar(255) DEFAULT NULL,
  `to_hospital_address` text DEFAULT NULL,
  `to_hospital_phone` varchar(20) DEFAULT NULL,
  `to_hospital_email` varchar(100) DEFAULT NULL,
  `reason` text NOT NULL,
  `clinical_notes` text DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `treatment_given` text DEFAULT NULL,
  `expert_type` varchar(100) DEFAULT NULL,
  `urgency` enum('routine','urgent','emergency') DEFAULT 'routine',
  `status` enum('pending','referred','accepted','rejected','completed','cancelled') NOT NULL DEFAULT 'referred',
  `notes` text DEFAULT NULL,
  `internal_notes` text DEFAULT NULL,
  `external_notes` text DEFAULT NULL,
  `referral_date` datetime NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `accepted_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `referrals`
--

INSERT INTO `referrals` (`id`, `referral_number`, `visit_id`, `patient_id`, `from_doctor_id`, `referral_type`, `to_doctor_id`, `to_hospital_name`, `to_hospital_address`, `to_hospital_phone`, `to_hospital_email`, `reason`, `clinical_notes`, `diagnosis`, `treatment_given`, `expert_type`, `urgency`, `status`, `notes`, `internal_notes`, `external_notes`, `referral_date`, `created_by`, `branch_id`, `created_at`, `updated_at`, `accepted_at`, `completed_at`, `cancelled_at`) VALUES
(21, 'REF-20260829-0059-579', NULL, 59, 4, 'internal', 5, NULL, NULL, NULL, NULL, 'LONG QUE', '', '', '', NULL, 'routine', 'referred', NULL, NULL, NULL, '2026-08-29 02:35:35', 4, 1, '2026-08-28 23:35:35', '2026-08-28 23:35:35', NULL, NULL, NULL),
(22, 'REF-20260829-0060-945', NULL, 60, 4, 'internal', 5, NULL, NULL, NULL, NULL, 'LONG QUE', '', '', '', NULL, 'routine', 'referred', NULL, NULL, NULL, '2026-08-29 02:35:35', 4, 1, '2026-08-28 23:35:35', '2026-08-28 23:35:35', NULL, NULL, NULL),
(23, 'REF-20260903-0060-665', NULL, 60, 4, 'external', NULL, 'MUHIMBILI', '', '', NULL, 'ttt', 'Expert Type: Other (Specify)\n\n', '', '', 'Other (Specify)', 'routine', 'referred', NULL, NULL, NULL, '2026-09-03 20:16:57', 4, 1, '2026-09-03 17:16:57', '2026-09-03 17:16:57', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `is_custom` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `description`, `is_custom`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'System Administrator - Full access', 0, '2026-08-23 12:26:09', '2026-08-23 12:26:09'),
(2, 'reception', 'Receptionist - Patient registration and appointments', 0, '2026-08-23 12:26:09', '2026-08-23 12:26:09'),
(3, 'cashier', 'Cashier - Handle payments and billing', 0, '2026-08-23 12:26:09', '2026-08-23 12:26:09'),
(4, 'doctor', 'Doctor - Patient consultation and prescriptions', 0, '2026-08-23 12:26:09', '2026-08-23 12:26:09'),
(5, 'laboratory', 'Laboratory Technician - Lab tests and results', 0, '2026-08-23 12:26:09', '2026-08-23 12:26:09'),
(6, 'pharmacy', 'Pharmacist - Medicine dispensing and inventory', 0, '2026-08-23 12:26:09', '2026-08-23 12:26:09');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `service_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `unit` varchar(50) DEFAULT 'each',
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `category_id`, `service_name`, `description`, `branch_id`, `price`, `unit`, `is_active`, `display_order`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'Registration Fee', 'New patient registration', 1, 10000.00, 'each', 1, 0, NULL, '2026-07-16 11:31:11', '2026-08-10 14:27:04'),
(2, 1, 'Re-registration', 'Existing patient re-registration', 1, 5000.00, 'each', 1, 0, NULL, '2026-07-16 11:31:11', '2026-08-10 14:27:04'),
(3, 2, 'General Consultation', 'Standard doctor consultation', 1, 15000.00, 'each', 1, 0, NULL, '2026-07-16 11:31:11', '2026-08-10 14:27:04'),
(4, 2, 'Follow-up Consultation', 'Follow-up visit', 1, 10000.00, 'each', 1, 0, NULL, '2026-07-16 11:31:11', '2026-08-10 14:27:04'),
(5, 2, 'Consultation-B', 'Emergency visit', 1, 25000.00, 'each', 1, 0, NULL, '2026-07-16 11:31:11', '2026-08-10 14:27:04'),
(6, 2, 'Specialist Consultation', 'Specialist doctor visit', 1, 30000.00, 'each', 1, 0, NULL, '2026-07-16 11:31:11', '2026-08-10 14:27:04'),
(7, 3, 'Blood Test - Full', 'Complete blood count', 1, 15000.00, 'each', 1, 0, NULL, '2026-07-16 11:31:11', '2026-08-10 14:27:04'),
(8, 3, 'Blood Test - Basic', 'Basic blood test', 1, 8000.00, 'each', 1, 0, NULL, '2026-07-16 11:31:11', '2026-08-10 14:27:04'),
(9, 3, 'Urine Test', 'Urinalysis', 1, 10000.00, 'each', 1, 0, NULL, '2026-07-16 11:31:11', '2026-08-10 14:27:04'),
(10, 3, 'Malaria Test', 'Malaria rapid test', 1, 5000.00, 'each', 1, 0, NULL, '2026-07-16 11:31:11', '2026-08-10 14:27:04'),
(11, 3, 'COVID-19 Test', 'COVID-19 rapid test', 1, 15000.00, 'each', 1, 0, NULL, '2026-07-16 11:31:11', '2026-08-10 14:27:04'),
(12, 3, 'X-Ray', 'X-Ray imaging', 1, 35000.00, 'each', 1, 0, NULL, '2026-07-16 11:31:11', '2026-08-10 14:27:04'),
(13, 3, 'Ultrasound', 'Ultrasound scan', 1, 50000.00, 'each', 1, 0, NULL, '2026-07-16 11:31:11', '2026-08-10 14:27:04'),
(14, 4, 'Prescription Charge', 'Prescription handling fee', 1, 5000.00, 'each', 1, 0, NULL, '2026-07-16 11:31:11', '2026-08-10 14:27:04'),
(15, 5, 'Minor Procedure', 'Minor medical procedure', 1, 20000.00, 'each', 1, 0, NULL, '2026-07-16 11:31:11', '2026-08-10 14:27:04'),
(16, 5, 'Major Procedure', 'Major medical procedure', 1, 50000.00, 'each', 1, 0, NULL, '2026-07-16 11:31:11', '2026-08-10 14:27:04'),
(17, 2, 'New Patient', '', 1, 10000.00, 'each', 1, 0, 8, '2026-07-29 09:00:39', '2026-07-29 09:00:39'),
(18, 2, 'FREE OF CHARDE', '', 1, 0.00, 'each', 1, 0, 6, '2026-08-01 13:34:14', '2026-08-01 13:34:14'),
(20, 2, 'New Patient', 'All New Patients', 2, 10000.00, 'each', 1, 0, 17, '2026-08-13 15:26:04', '2026-08-13 15:26:04'),
(21, 2, 'visit_mpya', '', 1, 100000.00, 'each', 1, 0, 11, '2026-08-19 15:31:52', '2026-08-19 15:31:52'),
(22, 2, 'Major Consultation', '', 2, 25000.00, 'each', 1, 0, 1, '2026-08-29 23:21:45', '2026-08-29 23:21:45');

-- --------------------------------------------------------

--
-- Table structure for table `service_categories`
--

CREATE TABLE `service_categories` (
  `id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT 'fa-file-medical',
  `color` varchar(20) DEFAULT '#0B5ED7',
  `branch_id` int(11) DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `service_categories`
--

INSERT INTO `service_categories` (`id`, `category_name`, `description`, `icon`, `color`, `branch_id`, `display_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Registration', 'Patient registration services', 'fa-file-medical', '#0B5ED7', NULL, 0, 1, '2026-07-16 11:31:11', '2026-07-16 11:31:11'),
(2, 'Consultation', 'Doctor consultation services', 'fa-file-medical', '#059669', NULL, 0, 1, '2026-07-16 11:31:11', '2026-07-16 11:31:11'),
(3, 'Lab Tests', 'Laboratory tests', 'fa-file-medical', '#7C3AED', NULL, 0, 1, '2026-07-16 11:31:11', '2026-07-16 11:31:11'),
(4, 'Medications', 'Pharmacy medications', 'fa-file-medical', '#D97706', NULL, 0, 1, '2026-07-16 11:31:11', '2026-07-16 11:31:11'),
(5, 'Procedures', 'Medical procedures', 'fa-file-medical', '#0D9488', NULL, 0, 1, '2026-07-16 11:31:11', '2026-07-16 11:31:11');

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL,
  `inventory_id` int(11) DEFAULT NULL,
  `equipment_id` int(11) DEFAULT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `movement_type` enum('in','out','adjustment') DEFAULT 'out',
  `quantity` int(11) NOT NULL,
  `previous_stock` int(11) NOT NULL,
  `new_stock` int(11) NOT NULL,
  `reference_type` enum('prescription','otc','lab_test','procedure','adjustment') DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `performed_by` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `inventory_id`, `equipment_id`, `patient_id`, `movement_type`, `quantity`, `previous_stock`, `new_stock`, `reference_type`, `reference_id`, `performed_by`, `branch_id`, `notes`, `created_at`) VALUES
(20, NULL, NULL, 44, 'out', 10, 280, 270, 'prescription', 18, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716', '2026-08-25 14:24:59'),
(26, NULL, NULL, 44, 'out', 2, 3, 1, '', NULL, 4, 1, 'Equipment: ECG Machine (12-Lead) | Batch: BATCH-ECG-001 | Patient: JOHN BOCCO | Visit: VIS-20260825-0044', '2026-08-25 15:03:35'),
(27, NULL, NULL, 44, 'out', 2, 20, 18, '', NULL, 4, 1, 'Equipment: Forceps (Tissue) | Batch: BATCH-FORCEP-001 | Patient: JOHN BOCCO | Visit: VIS-20260825-0044', '2026-08-25 15:04:04'),
(29, NULL, NULL, 45, 'out', 10, 270, 260, 'prescription', 19, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716 | Patient: JOHN CARTER | Visit: VIS-20260825-0045', '2026-08-25 16:11:20'),
(30, NULL, NULL, 45, 'out', 10, 90, 80, 'prescription', 20, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-F8F23A | Patient: JOHN CARTER | Visit: VIS-20260825-0045', '2026-08-25 16:11:50'),
(33, NULL, NULL, 45, 'out', 10, 40, 30, '', NULL, 4, 1, 'Equipment: Surgical Blades (Scalpel) | Batch: BATCH-BLADE-001 | Patient: JOHN CARTER | Visit: VIS-20260825-0045', '2026-08-25 16:13:08'),
(34, NULL, NULL, 45, 'out', 10, 78, 68, '', NULL, 4, 1, 'Equipment: Bandage (Elastic) | Batch: BATCH-BANDAGE-001 | Patient: JOHN CARTER | Visit: VIS-20260825-0045', '2026-08-25 20:24:47'),
(35, NULL, NULL, 46, 'out', 60, 260, 200, 'prescription', 21, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716 | Patient: AMINA ALLY MSANGI | Visit: VIS-20260825-0046', '2026-08-25 20:31:41'),
(36, NULL, NULL, 46, 'out', 79, 80, 1, 'prescription', 22, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-F8F23A | Patient: AMINA ALLY MSANGI | Visit: VIS-20260825-0046', '2026-08-25 20:32:12'),
(37, NULL, NULL, 46, 'out', 38, 68, 30, '', NULL, 4, 1, 'Equipment: Bandage (Elastic) | Batch: BATCH-BANDAGE-001 | Patient: AMINA ALLY MSANGI | Visit: VIS-20260825-0046', '2026-08-25 20:32:54'),
(38, NULL, NULL, 47, 'out', 1, 1, 0, 'prescription', 23, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-F8F23A | Patient: MARTHA KIMAMALA | Visit: VIS-20260825-0047', '2026-08-25 21:52:29'),
(39, NULL, NULL, 47, 'out', 10, 200, 190, 'prescription', 24, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716 | Patient: MARTHA KIMAMALA | Visit: VIS-20260825-0047', '2026-08-25 21:52:45'),
(40, NULL, NULL, 47, 'out', 10, 150, 140, '', NULL, 4, 1, 'Equipment: Adhesive Tape (Roll) | Batch: BATCH-TAPE-001 | Patient: MARTHA KIMAMALA | Visit: VIS-20260825-0047', '2026-08-25 21:53:11'),
(41, NULL, NULL, 47, 'out', 10, 30, 20, '', NULL, 4, 1, 'Equipment: Bandage (Elastic) | Batch: BATCH-BANDAGE-001 | Patient: MARTHA KIMAMALA | Visit: VIS-20260825-0047', '2026-08-25 21:53:26'),
(42, NULL, NULL, 47, 'out', 10, 30, 20, '', NULL, 4, 1, 'Equipment: Surgical Blades (Scalpel) | Batch: BATCH-BLADE-001 | Patient: MARTHA KIMAMALA | Visit: VIS-20260825-0047', '2026-08-25 21:53:26'),
(43, NULL, NULL, 48, 'out', 70, 370, 300, 'prescription', 25, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-914E09 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-0048', '2026-08-25 22:05:16'),
(44, NULL, NULL, 48, 'out', 1, 20, 19, '', NULL, 4, 1, 'Equipment: Bandage (Elastic) | Batch: BATCH-BANDAGE-001 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-0048', '2026-08-25 22:05:37'),
(45, NULL, NULL, 47, 'out', 10, 300, 290, 'prescription', 26, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-914E09 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-2681', '2026-08-26 08:53:43'),
(46, NULL, NULL, 47, 'out', 3, 20, 17, '', NULL, 4, 1, 'Equipment: Surgical Blades (Scalpel) | Batch: BATCH-BLADE-001 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-2681', '2026-08-26 08:54:05'),
(47, NULL, NULL, 48, 'out', 10, 290, 280, 'prescription', 27, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-914E09 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-6522', '2026-08-26 09:32:12'),
(48, NULL, NULL, 48, 'out', 10, 19, 9, '', NULL, 4, 1, 'Equipment: Bandage (Elastic) | Batch: BATCH-BANDAGE-001 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-6522', '2026-08-26 09:32:44'),
(49, NULL, NULL, 48, 'out', 20, 280, 260, 'prescription', 28, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-914E09 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-4037', '2026-08-26 10:00:51'),
(50, NULL, NULL, 48, 'out', 5, 9, 4, '', NULL, 4, 1, 'Equipment: Bandage (Elastic) | Batch: BATCH-BANDAGE-001 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-4037', '2026-08-26 10:01:23'),
(51, NULL, NULL, 48, 'out', 5, 17, 12, '', NULL, 4, 1, 'Equipment: Surgical Blades (Scalpel) | Batch: BATCH-BLADE-001 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-4037', '2026-08-26 10:01:23'),
(52, NULL, NULL, 48, 'out', 5, 1000, 995, '', NULL, 4, 1, 'Equipment: Gloves (Surgical - Sterile) | Batch: BATCH-GLOVES-001 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-4037', '2026-08-26 10:01:23'),
(53, NULL, NULL, 47, 'out', 10, 260, 250, 'prescription', 29, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-914E09 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-9634', '2026-08-26 10:11:16'),
(54, NULL, NULL, 47, 'out', 5, 12, 7, '', NULL, 4, 1, 'Equipment: Surgical Blades (Scalpel) | Batch: BATCH-BLADE-001 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-9634', '2026-08-26 10:11:48'),
(55, NULL, NULL, 47, 'out', 5, 995, 990, '', NULL, 4, 1, 'Equipment: Gloves (Surgical - Sterile) | Batch: BATCH-GLOVES-001 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-9634', '2026-08-26 10:11:48'),
(56, NULL, NULL, 48, 'out', 1, 250, 249, 'prescription', 30, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-914E09 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-6810', '2026-08-26 11:06:59'),
(57, NULL, NULL, 48, 'out', 10, 250, 240, 'prescription', 31, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-914E09 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-6810', '2026-08-26 11:07:20'),
(58, NULL, NULL, 48, 'out', 10, 500, 490, '', NULL, 4, 1, 'Equipment: Gauze Swabs (Sterile) | Batch: BATCH-GAUZE-001 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-6810', '2026-08-26 11:07:45'),
(59, NULL, NULL, 48, 'out', 10, 990, 980, '', NULL, 4, 1, 'Equipment: Gloves (Surgical - Sterile) | Batch: BATCH-GLOVES-001 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-6810', '2026-08-26 11:07:45'),
(60, NULL, NULL, 47, 'out', 10, 240, 230, 'prescription', 32, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-914E09 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-5412', '2026-08-26 12:42:45'),
(61, NULL, NULL, 47, 'out', 10, 190, 180, 'prescription', 33, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-5412', '2026-08-26 12:42:58'),
(62, NULL, NULL, 47, 'out', 3, 140, 137, '', NULL, 4, 1, 'Equipment: Adhesive Tape (Roll) | Batch: BATCH-TAPE-001 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-5412', '2026-08-26 12:43:27'),
(63, NULL, NULL, 47, 'out', 3, 4, 1, '', NULL, 4, 1, 'Equipment: Bandage (Elastic) | Batch: BATCH-BANDAGE-001 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-5412', '2026-08-26 12:43:27'),
(64, NULL, NULL, 47, 'out', 5, 18, 13, '', NULL, 4, 1, 'Equipment: Forceps (Tissue) | Batch: BATCH-FORCEP-001 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-5412', '2026-08-26 12:43:52'),
(65, NULL, NULL, 47, 'out', 1, 180, 179, 'prescription', 34, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-5712', '2026-08-26 12:49:37'),
(66, NULL, NULL, 47, 'out', 1, 230, 229, 'prescription', 35, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-914E09 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-5712', '2026-08-26 12:49:43'),
(67, NULL, NULL, 47, 'out', 1, 15, 14, '', NULL, 4, 1, 'Equipment: Blood Pressure Cuff (Manual) | Batch: BATCH-BP-001 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-5712', '2026-08-26 12:50:15'),
(68, NULL, NULL, 47, 'out', 1, 8, 7, '', NULL, 4, 1, 'Equipment: Infusion Pump | Batch: BATCH-INFUSE-001 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-5712', '2026-08-26 12:50:15'),
(69, NULL, NULL, 47, 'out', 1, 20, 19, '', NULL, 4, 1, 'Equipment: Pulse Oximeter | Batch: BATCH-OXI-001 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-5712', '2026-08-26 12:50:15'),
(70, NULL, NULL, 47, 'out', 1, 10, 9, '', NULL, 4, 1, 'Equipment: Retractor (Surgical) | Batch: BATCH-RETRACT-001 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-5712', '2026-08-26 12:50:15'),
(71, NULL, NULL, 49, 'out', 1, 179, 178, 'prescription', 36, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716 | Patient: AGUSTINO VALENTINE | Visit: VIS-20260826-5485', '2026-08-26 13:22:44'),
(72, NULL, NULL, 49, 'out', 1, 229, 228, 'prescription', 37, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-914E09 | Patient: AGUSTINO VALENTINE | Visit: VIS-20260826-5485', '2026-08-26 13:22:51'),
(73, NULL, NULL, 49, 'out', 1, 137, 136, '', NULL, 4, 1, 'Equipment: Adhesive Tape (Roll) | Batch: BATCH-TAPE-001 | Patient: AGUSTINO VALENTINE | Visit: VIS-20260826-5485', '2026-08-26 13:23:22'),
(74, NULL, NULL, 49, 'out', 1, 15, 14, '', NULL, 4, 1, 'Equipment: Needle Holder (Surgical) | Batch: BATCH-NEEDLE-001 | Patient: AGUSTINO VALENTINE | Visit: VIS-20260826-5485', '2026-08-26 13:23:22'),
(75, NULL, NULL, 50, 'out', 1, 228, 227, 'prescription', 38, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-914E09 | Patient: KELVIN MSAFIRI | Visit: VIS-20260826-0050', '2026-08-26 13:44:18'),
(76, NULL, NULL, 50, 'out', 1, 14, 13, '', NULL, 4, 1, 'Equipment: Blood Pressure Cuff (Manual) | Batch: BATCH-BP-001 | Patient: KELVIN MSAFIRI | Visit: VIS-20260826-0050', '2026-08-26 13:44:32'),
(77, NULL, NULL, 50, 'out', 1, 13, 12, '', NULL, 4, 1, 'Equipment: Forceps (Tissue) | Batch: BATCH-FORCEP-001 | Patient: KELVIN MSAFIRI | Visit: VIS-20260826-0050', '2026-08-26 13:44:32'),
(78, NULL, NULL, 48, 'out', 1, 227, 226, 'prescription', 39, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-914E09 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-4859', '2026-08-26 13:45:52'),
(79, NULL, NULL, 48, 'out', 1, 136, 135, '', NULL, 4, 1, 'Equipment: Adhesive Tape (Roll) | Batch: BATCH-TAPE-001 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-4859', '2026-08-26 13:45:57'),
(80, NULL, NULL, 47, 'out', 1, 178, 177, 'prescription', 40, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-7218', '2026-08-26 14:16:28'),
(81, NULL, NULL, 47, 'out', 3, 13, 10, '', NULL, 4, 1, 'Equipment: Blood Pressure Cuff (Manual) | Batch: BATCH-BP-001 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-7218', '2026-08-26 14:16:48'),
(82, NULL, NULL, 48, 'out', 1, 177, 176, 'prescription', 41, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-7053', '2026-08-26 14:36:28'),
(83, NULL, NULL, 48, 'out', 1, 7, 6, '', NULL, 4, 1, 'Equipment: Surgical Blades (Scalpel) | Batch: BATCH-BLADE-001 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-7053', '2026-08-26 14:36:57'),
(84, NULL, NULL, 48, 'out', 1, 980, 979, '', NULL, 4, 1, 'Equipment: Gloves (Surgical - Sterile) | Batch: BATCH-GLOVES-001 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-7053', '2026-08-26 14:36:57'),
(85, NULL, NULL, 48, 'out', 1, 12, 11, '', NULL, 4, 1, 'Equipment: Forceps (Tissue) | Batch: BATCH-FORCEP-001 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-7053', '2026-08-26 14:36:57'),
(86, NULL, NULL, 49, 'out', 1, 176, 175, 'prescription', 42, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716 | Patient: AGUSTINO VALENTINE | Visit: VIS-20260826-4039', '2026-08-26 14:57:32'),
(87, NULL, NULL, 49, 'out', 1, 135, 134, '', NULL, 4, 1, 'Equipment: Adhesive Tape (Roll) | Batch: BATCH-TAPE-001 | Patient: AGUSTINO VALENTINE | Visit: VIS-20260826-4039', '2026-08-26 14:57:47'),
(88, NULL, NULL, 49, 'out', 1, 4, 3, '', NULL, 4, 1, 'Equipment: Spirometer (Digital) | Batch: BATCH-SPIRO-001 | Patient: AGUSTINO VALENTINE | Visit: VIS-20260826-4039', '2026-08-26 14:57:47'),
(89, NULL, NULL, 47, 'out', 1, 175, 174, 'prescription', 43, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-3053', '2026-08-26 15:19:44'),
(90, NULL, NULL, 47, 'out', 1, 134, 133, '', NULL, 4, 1, 'Equipment: Adhesive Tape (Roll) | Batch: BATCH-TAPE-001 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-3053', '2026-08-26 15:19:54'),
(91, NULL, NULL, 47, 'out', 1, 7, 6, '', NULL, 4, 1, 'Equipment: Infusion Pump | Batch: BATCH-INFUSE-001 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-3053', '2026-08-26 15:19:54'),
(92, NULL, NULL, 48, 'out', 1, 174, 173, 'prescription', 44, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-2717', '2026-08-26 15:25:05'),
(93, NULL, NULL, 48, 'out', 1, 226, 225, 'prescription', 45, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-914E09 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-2717', '2026-08-26 15:25:18'),
(94, NULL, NULL, 48, 'out', 1, 490, 489, '', NULL, 4, 1, 'Equipment: Gauze Swabs (Sterile) | Batch: BATCH-GAUZE-001 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-2717', '2026-08-26 15:25:35'),
(95, NULL, NULL, 48, 'out', 1, 11, 10, '', NULL, 4, 1, 'Equipment: Forceps (Tissue) | Batch: BATCH-FORCEP-001 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-2717', '2026-08-26 15:25:35'),
(96, NULL, NULL, 49, 'out', 1, 173, 172, 'prescription', 46, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716 | Patient: AGUSTINO VALENTINE | Visit: VIS-20260826-8674', '2026-08-26 15:44:13'),
(97, NULL, NULL, 49, 'out', 1, 14, 13, '', NULL, 4, 1, 'Equipment: Needle Holder (Surgical) | Batch: BATCH-NEEDLE-001 | Patient: AGUSTINO VALENTINE | Visit: VIS-20260826-8674', '2026-08-26 15:44:39'),
(98, NULL, NULL, 49, 'out', 1, 3, 2, '', NULL, 4, 1, 'Equipment: Spirometer (Digital) | Batch: BATCH-SPIRO-001 | Patient: AGUSTINO VALENTINE | Visit: VIS-20260826-8674', '2026-08-26 15:44:39'),
(99, NULL, NULL, 49, 'out', 1, 25, 24, '', NULL, 4, 1, 'Equipment: Surgical Scissors (Mayo) | Batch: BATCH-SCISSOR-001 | Patient: AGUSTINO VALENTINE | Visit: VIS-20260826-8674', '2026-08-26 15:44:39'),
(100, NULL, NULL, 49, 'out', 1, 5, 4, '', NULL, 4, 1, 'Equipment: Weighing Scale (Medical) | Batch: BATCH-SCALE-001 | Patient: AGUSTINO VALENTINE | Visit: VIS-20260826-8674', '2026-08-26 15:44:39'),
(101, NULL, NULL, 50, 'out', 1, 172, 171, 'prescription', 47, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716 | Patient: KELVIN MSAFIRI | Visit: VIS-20260826-0824', '2026-08-26 15:58:09'),
(102, NULL, NULL, 50, 'out', 33, 133, 100, '', NULL, 4, 1, 'Equipment: Adhesive Tape (Roll) | Batch: BATCH-TAPE-001 | Patient: KELVIN MSAFIRI | Visit: VIS-20260826-0824', '2026-08-26 15:58:30'),
(103, NULL, NULL, 58, 'out', 1, 171, 170, 'prescription', 48, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716 | Patient: AYUBU NZAL | Visit: VIS-20260826-3483', '2026-08-26 20:46:05'),
(104, NULL, NULL, 58, 'out', 1, 100, 99, '', NULL, 4, 1, 'Equipment: Adhesive Tape (Roll) | Batch: BATCH-TAPE-001 | Patient: AYUBU NZAL | Visit: VIS-20260826-3483', '2026-08-26 20:46:25'),
(105, NULL, NULL, 58, 'out', 1, 6, 5, '', NULL, 4, 1, 'Equipment: Surgical Blades (Scalpel) | Batch: BATCH-BLADE-001 | Patient: AYUBU NZAL | Visit: VIS-20260826-3483', '2026-08-26 20:46:25'),
(106, NULL, NULL, 61, 'out', 10, 170, 160, 'prescription', 49, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716 | Patient: MUSSA MONGI MASNGI | Visit: VIS-20260826-0112', '2026-08-26 21:42:31'),
(107, NULL, NULL, 61, 'out', 5, 99, 94, '', NULL, 4, 1, 'Equipment: Adhesive Tape (Roll) | Batch: BATCH-TAPE-001 | Patient: MUSSA MONGI MASNGI | Visit: VIS-20260826-0112', '2026-08-26 21:42:59'),
(108, NULL, NULL, 61, 'out', 5, 13, 8, '', NULL, 4, 1, 'Equipment: Needle Holder (Surgical) | Batch: BATCH-NEEDLE-001 | Patient: MUSSA MONGI MASNGI | Visit: VIS-20260826-0112', '2026-08-26 21:42:59'),
(109, NULL, NULL, 60, 'out', 1, 160, 159, 'prescription', 50, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716 | Patient: ANDREW VICENT CHIKUPE | Visit: VIS-20260827-3977', '2026-08-27 21:09:21'),
(110, NULL, NULL, 60, 'out', 1, 489, 488, '', NULL, 4, 1, 'Equipment: Gauze Swabs (Sterile) | Batch: BATCH-GAUZE-001 | Patient: ANDREW VICENT CHIKUPE | Visit: VIS-20260827-3977', '2026-08-27 21:09:46'),
(111, NULL, NULL, 60, 'out', 1, 5, 4, '', NULL, 4, 1, 'Equipment: Surgical Blades (Scalpel) | Batch: BATCH-BLADE-001 | Patient: ANDREW VICENT CHIKUPE | Visit: VIS-20260827-3977', '2026-08-27 21:09:46'),
(112, NULL, NULL, 60, 'out', 1, 8, 7, '', NULL, 4, 1, 'Equipment: Needle Holder (Surgical) | Batch: BATCH-NEEDLE-001 | Patient: ANDREW VICENT CHIKUPE | Visit: VIS-20260827-3977', '2026-08-27 21:09:46'),
(113, NULL, NULL, 61, 'out', 10, 159, 149, 'prescription', 49, 9, 1, 'Auto-dispensed from batch BATCH-20260825-AEB716 - Prescription #PRES-20260826-0061-397', '2026-08-27 22:07:15'),
(114, NULL, NULL, 60, 'out', 9, 149, 140, 'prescription', 51, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716 | Patient: ANDREW VICENT CHIKUPE | Visit: VIS-20260827-3977', '2026-08-27 22:19:00'),
(115, NULL, NULL, 60, 'out', 5, 225, 220, 'prescription', 52, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-914E09 | Patient: ANDREW VICENT CHIKUPE | Visit: VIS-20260827-3977', '2026-08-27 22:19:17'),
(116, NULL, NULL, 60, 'out', 10, 140, 130, 'prescription', 53, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716 | Patient: ANDREW VICENT CHIKUPE | Visit: VIS-20260827-3977', '2026-08-27 22:22:26'),
(117, NULL, NULL, 60, 'out', 10, 220, 210, 'prescription', 54, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-914E09 | Patient: ANDREW VICENT CHIKUPE | Visit: VIS-20260827-3977', '2026-08-27 22:22:47'),
(118, NULL, NULL, 61, 'out', 20, 130, 110, 'prescription', 55, 5, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716 | Patient: MUSSA MONGI MASNGI | Visit: VIS-20260829-9279', '2026-08-28 22:38:09'),
(119, NULL, NULL, 61, 'out', 10, 210, 200, 'prescription', 56, 5, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-914E09 | Patient: MUSSA MONGI MASNGI | Visit: VIS-20260829-9279', '2026-08-28 22:38:23'),
(120, NULL, NULL, 61, 'out', 1, 10, 9, '', NULL, 5, 1, 'Equipment: Blood Pressure Cuff (Manual) | Batch: BATCH-BP-001 | Patient: MUSSA MONGI MASNGI | Visit: VIS-20260829-9279', '2026-08-28 22:38:42'),
(121, NULL, NULL, 61, 'out', 1, 1, 0, '', NULL, 5, 1, 'Equipment: ECG Machine (12-Lead) | Batch: BATCH-ECG-001 | Patient: MUSSA MONGI MASNGI | Visit: VIS-20260829-9279', '2026-08-28 22:38:42'),
(122, NULL, NULL, 59, 'out', 30, 200, 170, 'prescription', 57, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-914E09 | Patient: AMOSI NGOMENI | Visit: VIS-20260829-2623', '2026-08-28 22:39:08'),
(123, NULL, NULL, 59, 'out', 4, 12, 8, '', NULL, 4, 1, 'Equipment: X-Ray Film Cassette | Batch: BATCH-XRAY-001 | Patient: AMOSI NGOMENI | Visit: VIS-20260829-2623', '2026-08-28 22:39:29'),
(124, NULL, NULL, 61, 'out', 10, 110, 100, 'prescription', 58, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716 | Patient: MUSSA MONGI MASNGI | Visit: VIS-20260829-9496', '2026-08-28 22:57:33'),
(125, NULL, NULL, 61, 'out', 10, 170, 160, 'prescription', 59, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-914E09 | Patient: MUSSA MONGI MASNGI | Visit: VIS-20260829-9496', '2026-08-28 22:57:47'),
(126, NULL, NULL, 61, 'out', 1, 979, 978, '', NULL, 4, 1, 'Equipment: Gloves (Surgical - Sterile) | Batch: BATCH-GLOVES-001 | Patient: MUSSA MONGI MASNGI | Visit: VIS-20260829-9496', '2026-08-28 22:58:01'),
(127, NULL, NULL, 60, 'out', 20, 160, 140, 'prescription', 60, 5, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-914E09 | Patient: ANDREW VICENT CHIKUPE | Visit: VIS-20260829-1998', '2026-08-28 23:27:06'),
(128, NULL, NULL, 58, 'out', 20, 140, 120, 'prescription', 61, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-914E09 | Patient: AYUBU NZAL | Visit: VIS-20260829-6743', '2026-08-28 23:36:07'),
(129, NULL, NULL, 61, 'out', 10, 100, 90, 'prescription', 62, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716 | Patient: MUSSA MONGI MASNGI | Visit: VIS-20260829-4167', '2026-08-28 23:36:59'),
(130, NULL, NULL, 61, 'out', 10, 120, 110, 'prescription', 63, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-914E09 | Patient: MUSSA MONGI MASNGI | Visit: VIS-20260829-4167', '2026-08-28 23:37:13'),
(131, NULL, NULL, 59, 'out', 3, 90, 87, 'prescription', 64, 5, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716 | Patient: AMOSI NGOMENI | Visit: VIS-20260829-0678', '2026-08-28 23:38:15'),
(132, NULL, NULL, 59, 'out', 7, 110, 103, 'prescription', 65, 5, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-914E09 | Patient: AMOSI NGOMENI | Visit: VIS-20260829-0678', '2026-08-28 23:38:33'),
(133, NULL, NULL, 60, 'out', 3, 103, 100, 'prescription', 66, 5, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-914E09 | Patient: ANDREW VICENT CHIKUPE | Visit: VIS-20260829-3302', '2026-08-28 23:39:09'),
(134, NULL, NULL, 60, 'out', 7, 87, 80, 'prescription', 67, 5, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716 | Patient: ANDREW VICENT CHIKUPE | Visit: VIS-20260829-3302', '2026-08-28 23:39:32'),
(135, NULL, NULL, NULL, '', 10, 0, 0, '', 7, 7, 1, 'OTC Sale - Pending Payment: OTC-20260829-4392 - Customer: AGATHA MUSSA', '2026-08-29 06:29:00'),
(136, NULL, NULL, NULL, '', 10, 0, 0, '', 8, 9, 1, 'OTC Sale - Pending Payment: OTC-20260829-3814 - Customer: MUSSA YOHANA', '2026-08-29 07:44:54'),
(137, NULL, NULL, NULL, 'out', 10, 0, 0, 'otc', 9, 9, 1, 'OTC Sale - Paid: OTC-20260829-5093 - Customer: JOSEPHINE LUEMBA', '2026-08-29 10:39:41'),
(138, NULL, NULL, NULL, 'out', 10, 0, 0, 'otc', 10, 7, 1, 'OTC Sale - Paid: OTC-20260829-9925 - Customer: MAGRETH SANGA', '2026-08-29 20:26:08'),
(139, NULL, NULL, 57, 'out', 8, 80, 72, 'prescription', 68, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716', '2026-08-31 14:09:06'),
(140, NULL, NULL, 57, 'out', 1, 10, 9, '', NULL, 4, 1, 'Equipment: Forceps (Tissue)', '2026-08-31 14:09:28'),
(141, NULL, NULL, 57, 'out', 1, 6, 5, '', NULL, 4, 1, 'Equipment: Infusion Pump', '2026-08-31 14:09:28'),
(142, NULL, NULL, 58, 'out', 5, 80, 75, 'prescription', 69, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-914E09', '2026-08-31 15:12:45'),
(143, NULL, NULL, 58, 'out', 1, 1, 0, '', NULL, 4, 1, 'Equipment: Bandage (Elastic)', '2026-08-31 15:13:08'),
(144, NULL, NULL, 58, 'out', 1, 9, 8, '', NULL, 4, 1, 'Equipment: Forceps (Tissue)', '2026-08-31 15:13:08'),
(145, NULL, NULL, 58, 'out', 5, 75, 70, 'prescription', 69, 9, 1, 'Auto-dispensed from batch BATCH-20260824-914E09 - Prescription #PRES-20260831-0058-119', '2026-08-31 15:15:41'),
(146, NULL, NULL, 57, 'out', 8, 72, 64, 'prescription', 68, 9, 1, 'Auto-dispensed from batch BATCH-20260825-AEB716 - Prescription #PRES-20260831-0057-374', '2026-08-31 15:15:49'),
(147, NULL, NULL, 61, 'out', 10, 64, 54, 'prescription', 70, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716', '2026-09-02 09:42:49'),
(148, NULL, NULL, 61, 'out', 30, 1000, 970, 'prescription', 71, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260901-D1469C', '2026-09-02 09:43:08'),
(149, NULL, NULL, 61, 'out', 1, 4, 3, '', NULL, 4, 1, 'Equipment: Surgical Blades (Scalpel)', '2026-09-02 09:43:27'),
(150, NULL, NULL, 61, 'out', 1, 5, 4, '', NULL, 4, 1, 'Equipment: Infusion Pump', '2026-09-02 09:43:27'),
(151, NULL, NULL, 61, 'out', 1, 7, 6, '', NULL, 4, 1, 'Equipment: Needle Holder (Surgical)', '2026-09-02 09:43:27'),
(152, NULL, NULL, 61, 'out', 10, 54, 44, 'prescription', 70, 7, 1, 'Auto-dispensed from batch BATCH-20260825-AEB716 - Prescription #PRES-20260902-0061-925', '2026-09-02 09:46:30'),
(153, NULL, NULL, 61, 'out', 30, 44, 14, 'prescription', 71, 7, 1, 'Auto-dispensed from batch BATCH-20260825-AEB716 - Prescription #PRES-20260902-0061-264', '2026-09-02 09:46:30'),
(154, NULL, NULL, 61, 'out', 69, 970, 901, 'prescription', 72, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260901-D1469C', '2026-09-02 09:58:38'),
(155, NULL, NULL, 61, 'out', 1, 6, 5, '', NULL, 4, 1, 'Equipment: Needle Holder (Surgical)', '2026-09-02 09:58:56'),
(156, NULL, NULL, 61, 'out', 1, 2, 1, '', NULL, 4, 1, 'Equipment: Spirometer (Digital)', '2026-09-02 09:58:56'),
(157, NULL, NULL, 59, 'out', 100, 901, 801, 'prescription', 73, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260901-D1469C', '2026-09-02 22:13:37'),
(158, NULL, NULL, 61, 'out', 14, 14, 0, 'prescription', 72, 7, 1, 'Auto-dispensed from batch BATCH-20260825-AEB716 - Prescription #PRES-20260902-0061-542', '2026-09-02 22:14:17'),
(159, NULL, NULL, 61, 'out', 55, 801, 746, 'prescription', 72, 7, 1, 'Auto-dispensed from batch BATCH-20260901-D1469C - Prescription #PRES-20260902-0061-542', '2026-09-02 22:14:17'),
(160, NULL, NULL, 52, 'out', 100, 746, 646, 'prescription', 74, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260901-D1469C', '2026-09-02 23:15:22'),
(161, NULL, NULL, 52, 'out', 10, 70, 60, 'prescription', 75, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-914E09', '2026-09-02 23:15:40'),
(162, NULL, NULL, 52, 'out', 3, 3, 0, '', NULL, 4, 1, 'Equipment: Surgical Blades (Scalpel)', '2026-09-02 23:16:13'),
(163, NULL, NULL, 52, 'out', 3, 9, 6, '', NULL, 4, 1, 'Equipment: Blood Pressure Cuff (Manual)', '2026-09-02 23:16:13'),
(164, NULL, NULL, 52, 'out', 3, 4, 1, '', NULL, 4, 1, 'Equipment: Infusion Pump', '2026-09-02 23:16:13'),
(165, NULL, NULL, 52, 'out', 3, 5, 2, '', NULL, 4, 1, 'Equipment: Needle Holder (Surgical)', '2026-09-02 23:16:13'),
(166, NULL, NULL, 59, 'out', 100, 646, 546, 'prescription', 73, 7, 1, 'Auto-dispensed from batch BATCH-20260901-D1469C - Prescription #PRES-20260903-0059-608', '2026-09-02 23:17:01'),
(167, NULL, NULL, 56, 'out', 10, 546, 536, 'prescription', 76, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260901-D1469C', '2026-09-03 17:02:56'),
(168, NULL, NULL, 52, 'out', 100, 536, 436, 'prescription', 74, 7, 1, 'Auto-dispensed from batch BATCH-20260901-D1469C - Prescription #PRES-20260903-0052-830', '2026-09-03 17:33:22'),
(169, NULL, NULL, 52, 'out', 10, 60, 50, 'prescription', 75, 7, 1, 'Auto-dispensed from batch BATCH-20260824-914E09 - Prescription #PRES-20260903-0052-935', '2026-09-03 17:33:22'),
(170, NULL, NULL, NULL, 'out', 10, 0, 0, 'otc', 11, 7, 1, 'OTC Sale - Paid: OTC-20260903-9826 - Customer: HH', '2026-09-03 17:45:40'),
(171, NULL, NULL, 61, 'out', 26, 426, 400, 'prescription', 77, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260901-D1469C', '2026-09-03 20:48:19'),
(172, NULL, NULL, 61, 'out', 10, 50, 40, 'prescription', 78, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-914E09', '2026-09-03 20:48:39'),
(173, NULL, NULL, 61, 'out', 10, 400, 390, 'prescription', 79, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260901-D1469C', '2026-09-03 21:22:30'),
(174, NULL, NULL, 61, 'out', 90, 390, 300, 'prescription', 80, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260901-D1469C', '2026-09-04 00:17:11'),
(175, NULL, NULL, 61, 'out', 1, 8, 7, '', NULL, 4, 1, 'Equipment: Forceps (Tissue)', '2026-09-04 00:17:58'),
(176, NULL, NULL, 61, 'out', 1, 2, 1, '', NULL, 4, 1, 'Equipment: Needle Holder (Surgical)', '2026-09-04 00:17:58'),
(177, NULL, NULL, 61, 'out', 1, 300, 299, 'prescription', 81, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260901-D1469C', '2026-09-04 00:40:08'),
(178, NULL, NULL, 59, 'out', 9, 299, 290, 'prescription', 82, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260901-D1469C', '2026-09-04 17:14:29'),
(179, NULL, NULL, 59, 'out', 1, 1, 0, '', NULL, 4, 1, 'Equipment: Needle Holder (Surgical)', '2026-09-04 17:15:00'),
(180, NULL, NULL, 59, 'out', 1, 1, 0, '', NULL, 4, 1, 'Equipment: Spirometer (Digital)', '2026-09-04 17:15:00'),
(181, NULL, NULL, 60, 'out', 10, 290, 280, 'prescription', 83, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260901-D1469C', '2026-09-04 17:56:20'),
(182, NULL, NULL, 61, 'out', 10, 280, 270, 'prescription', 84, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260901-D1469C', '2026-09-04 20:45:02'),
(183, NULL, NULL, 61, 'out', 1, 5, 4, '', NULL, 4, 1, 'Equipment: Suction Machine (Portable)', '2026-09-04 20:45:20'),
(184, NULL, NULL, 61, 'out', 20, 270, 250, 'prescription', 85, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260901-D1469C', '2026-09-04 22:20:59'),
(185, NULL, NULL, 61, 'out', 1, 8, 7, '', NULL, 4, 1, 'Equipment: X-Ray Film Cassette', '2026-09-04 22:21:14'),
(186, NULL, NULL, NULL, 'out', 10, 0, 0, 'otc', 12, 7, 1, 'OTC Sale - Paid: OTC-20260906-8457 - Customer: MICHAEK TYSON', '2026-09-06 11:32:36'),
(187, NULL, NULL, NULL, '', 5, 0, 0, '', 13, 7, 1, 'OTC Sale - Pending Payment: OTC-20260906-8174 - Customer: KELVIN', '2026-09-06 11:48:09'),
(188, NULL, NULL, NULL, '', 5, 0, 0, '', 14, 7, 1, 'OTC Sale - Pending Payment: OTC-20260906-9916 - Customer: Walk-in Customer', '2026-09-06 11:53:40'),
(189, NULL, NULL, NULL, '', 10, 0, 0, '', 15, 7, 1, 'OTC Sale - Pending Payment: OTC-20260906-1145 - Customer: MGIMBA', '2026-09-06 12:02:54'),
(190, NULL, NULL, NULL, '', 10, 0, 0, '', 16, 7, 1, 'OTC Sale - Pending Payment: OTC-20260906-6926 - Customer: Walk-in Customer', '2026-09-06 12:15:26'),
(191, NULL, NULL, NULL, '', 10, 0, 0, '', 17, 7, 1, 'OTC Sale - PENDING: OTC-20260906-0204 - Customer: MUSAA', '2026-09-06 12:28:19'),
(192, NULL, NULL, NULL, '', 10, 0, 0, '', 18, 7, 1, 'OTC Sale - PENDING: OTC-20260906-7628 - Customer: HANIFA', '2026-09-06 12:53:35'),
(193, NULL, NULL, NULL, 'out', 20, 0, 0, 'otc', 19, 7, 1, 'OTC Sale - PAID: OTC-20260908-3495 - Customer: kelvin', '2026-09-08 08:36:03'),
(194, NULL, NULL, NULL, 'out', 10, 0, 0, 'otc', 20, 7, 1, 'OTC Sale - PAID: OTC-20260908-0455 - Customer: Walk-in Customer | Premium: TSh 5,000', '2026-09-08 09:26:54'),
(195, NULL, 41, 61, 'out', 1, 300, 299, 'lab_test', 67, 4, 1, 'Lab test: KICHOCHO - Equipment used', '2026-09-09 18:26:53');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `category` varchar(50) DEFAULT 'general',
  `description` text DEFAULT NULL,
  `is_editable` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `category`, `description`, `is_editable`, `created_at`, `updated_at`) VALUES
(1, 'site_name', 'Braick Dispensary', 'general', 'Name of the facility', 1, '2026-08-23 12:26:10', '2026-08-23 12:26:10'),
(2, 'currency', 'TSh', 'general', 'Currency symbol', 1, '2026-08-23 12:26:10', '2026-08-23 12:26:10'),
(3, 'business_hours_start', '08:00', 'general', 'Business hours start', 1, '2026-08-23 12:26:10', '2026-08-23 12:26:10'),
(4, 'business_hours_end', '18:00', 'general', 'Business hours end', 1, '2026-08-23 12:26:10', '2026-08-23 12:26:10');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `password_changed_at` timestamp NULL DEFAULT NULL,
  `is_default_password` tinyint(1) DEFAULT 1,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('admin','reception','doctor','laboratory','pharmacy','cashier') NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `specialty` varchar(100) DEFAULT NULL,
  `is_online` tinyint(1) DEFAULT 0,
  `last_online` timestamp NULL DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `password_changed_at`, `is_default_password`, `full_name`, `email`, `phone`, `role`, `branch_id`, `specialty`, `is_online`, `last_online`, `profile_pic`, `status`, `created_at`, `updated_at`) VALUES
(1, 'admin1', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'System Admin', 'admin@braick.com', '+255 613 234 123', 'admin', 1, NULL, 0, '2026-09-09 16:30:38', 'user_1_1788291272.png', 'active', '2026-08-23 12:26:10', '2026-09-09 16:30:38'),
(3, 'admin2', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'BRAICK', 'braick.admin@braick.com', '+255 732 123 030', 'admin', 1, NULL, 0, '2026-08-26 14:10:40', NULL, 'active', '2026-08-23 12:41:40', '2026-09-04 17:59:45'),
(4, 'Dr.Dodoma1', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Dr.ERICK JOHN', 'erick.dodoma@braick.com', '+255 700 000 011', 'doctor', 1, 'General Medicine', 1, '2026-09-09 18:29:40', 'user_4_1787697956.png', 'active', '2026-08-23 12:41:40', '2026-09-09 18:29:40'),
(5, 'Dr.Dodoma2', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Dr. Grace Peter', 'grace.dodoma@braick.com', '+255 700 000 012', 'doctor', 1, 'Pediatrics', 0, '2026-08-29 06:27:23', NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(6, 'Dr.Dodoma3', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Dr. John Mushi', 'john.dodoma@braick.com', '+255 700 000 013', 'doctor', 1, 'Cardiology', 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(7, 'Pharm.Dodoma3', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'LUCY MUSSA', 'pharm.dodoma@braick.com', '+255 700 000 014', 'pharmacy', 1, NULL, 0, '2026-09-09 17:26:48', 'user_7_1787493390.png', 'active', '2026-08-23 12:41:40', '2026-09-09 17:26:48'),
(8, 'Pharm.Dodoma2', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Mary John', 'mary.dodoma@braick.com', '+255 700 000 015', 'pharmacy', 1, NULL, 0, '2026-09-08 09:45:00', NULL, 'active', '2026-08-23 12:41:40', '2026-09-08 09:45:00'),
(9, 'Pharm.Dodoma1', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'James Mwangi', 'james.dodoma@braick.com', '+255 700 000 016', 'pharmacy', 1, NULL, 0, '2026-09-05 13:24:29', NULL, 'active', '2026-08-23 12:41:40', '2026-09-05 13:24:29'),
(10, 'Recpt.Dodoma1', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'SALOME SANGA', 'salome.dodoma@braick.com', '+255 700 000 017', 'reception', 1, NULL, 0, '2026-09-06 14:53:24', 'reception_10_1787518197.png', 'active', '2026-08-23 12:41:40', '2026-09-06 14:53:24'),
(11, 'Recpt.Dodoma2', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Rose Mwangi', 'rose.dodoma@braick.com', '+255 700 000 018', 'reception', 1, NULL, 0, '2026-09-05 22:13:57', NULL, 'active', '2026-08-23 12:41:40', '2026-09-05 22:13:57'),
(12, 'Recpt.Dodoma3', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'JUDITH SOLOMONI', 'anna.dodoma@braick.com', '+255 700 000 019', 'reception', 1, NULL, 0, '2026-09-09 15:21:33', NULL, 'active', '2026-08-23 12:41:40', '2026-09-09 15:21:33'),
(13, 'Lab.Dodoma1', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'ANGERITHA KIMARO', 'lab.dodoma@braick.com', '+255 700 000 020', 'laboratory', 1, '', 0, '2026-09-09 18:29:14', 'user_13_1787502536.png', 'active', '2026-08-23 12:41:40', '2026-09-09 18:29:14'),
(14, 'Lab.Dodoma2', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Peter Lema', 'peter.dodoma@braick.com', '+255 700 000 021', 'laboratory', 1, NULL, 0, '2026-09-05 13:14:13', NULL, 'active', '2026-08-23 12:41:40', '2026-09-05 13:14:13'),
(15, 'Lab.Dodoma3', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Sarah Mwamba', 'sarah.dodoma@braick.com', '+255 700 000 022', 'laboratory', 1, NULL, 0, '2026-08-28 22:37:29', NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(16, 'cashier.dodoma', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Cashier Dodoma', 'cashier.dodoma@braick.com', '+255 700 000 023', 'cashier', 1, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(17, 'Dr.Arusha1', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Dr. David Mwanga', 'david.arusha@braick.com', '+255 700 000 024', 'doctor', 2, 'General Medicine', 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(18, 'Dr.Arusha2', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Dr. Anna Kivuyo', 'anna.arusha@braick.com', '+255 700 000 025', 'doctor', 2, 'Obstetrics', 1, '2026-08-29 21:52:57', NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(19, 'Dr.Arusha3', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Dr. Peter Lema', 'peter.arusha@braick.com', '+255 700 000 026', 'doctor', 2, 'Surgery', 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(20, 'Pharm.Arusha1', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Pharmacy Arusha', 'pharm.arusha@braick.com', '+255 700 000 027', 'pharmacy', 2, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(21, 'Pharm.Arusha2', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Juma Mussa', 'juma.arusha@braick.com', '+255 700 000 028', 'pharmacy', 2, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(22, 'Pharm.Arusha3', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Catherine M', 'catherine.arusha@braick.com', '+255 700 000 029', 'pharmacy', 2, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(23, 'Recpt.Arusha1', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'NYANSAEL NZILU', 'reception.arusha@braick.com', '+255 700 000 030', 'reception', 2, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(24, 'Recpt.Arusha2', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'GRACE MUSHI', 'grace.arusha@braick.com', '+255 700 000 031', 'reception', 2, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(25, 'Recpt.Arusha3', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'LUCY PETER', 'lucy.arusha@braick.com', '+255 700 000 032', 'reception', 2, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(26, 'Lab.Arusha1', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'MARIA MSANGI', 'lab.arusha@braick.com', '+255 700 000 033', 'laboratory', 2, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(27, 'lab.arusha2', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Moses Paul', 'moses.arusha@braick.com', '+255 700 000 034', 'laboratory', 2, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(28, 'lab.arusha3', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Hellen John', 'hellen.arusha@braick.com', '+255 700 000 035', 'laboratory', 2, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(29, 'cashier.arusha', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Cashier Arusha', 'cashier.arusha@braick.com', '+255 700 000 036', 'cashier', 2, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(30, 'dr.dar1', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Dr. James Kato', 'james.dar@braick.com', '+255 700 000 037', 'doctor', 3, 'Neurology', 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(31, 'dr.dar2', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Dr. Sarah Mwamba', 'sarah.dar@braick.com', '+255 700 000 038', 'doctor', 3, 'Cardiology', 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(32, 'dr.dar3', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Dr. Mary Ndugu', 'mary.dar@braick.com', '+255 700 000 039', 'doctor', 3, 'Pediatrics', 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(33, 'pharm.dar1', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Pharmacy Dar', 'pharm.dar@braick.com', '+255 700 000 040', 'pharmacy', 3, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(34, 'pharm.dar2', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'William M', 'william.dar@braick.com', '+255 700 000 041', 'pharmacy', 3, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(35, 'pharm.dar3', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Diana K', 'diana.dar@braick.com', '+255 700 000 042', 'pharmacy', 3, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(36, 'reception.dar1', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Reception Dar', 'reception.dar@braick.com', '+255 700 000 043', 'reception', 3, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(37, 'reception.dar2', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Flora M', 'flora.dar@braick.com', '+255 700 000 044', 'reception', 3, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(38, 'reception.dar3', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Paul L', 'paul.dar@braick.com', '+255 700 000 045', 'reception', 3, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(39, 'lab.dar1', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Lab Technician Dar', 'lab.dar@braick.com', '+255 700 000 046', 'laboratory', 3, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(40, 'lab.dar2', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Thomas N', 'thomas.dar@braick.com', '+255 700 000 047', 'laboratory', 3, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(41, 'lab.dar3', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Jane K', 'jane.dar@braick.com', '+255 700 000 048', 'laboratory', 3, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(42, 'cashier.dar', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Cashier Dar', 'cashier.dar@braick.com', '+255 700 000 049', 'cashier', 3, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(43, 'Dr.dodoma4', '$2y$10$kM2BXne4K607PIvNGibY9e1TYiL.DWW4TWoVb91F0BKw5gArXgcPy', '2026-09-02 21:06:05', 1, 'SARAFINA MHECHE', 'sarah@braick.com', '0623693303', 'doctor', 1, 'Reception', 0, '2026-09-02 21:06:38', NULL, 'active', '2026-09-02 21:06:05', '2026-09-02 21:15:20'),
(44, 'R.angerith', '$2y$10$uWAY/jylf2vW4kUfBKVx6.7WOOEqJ/MDHZNYv170sMOLcIPKHZZPi', '2026-09-03 01:31:31', 1, 'ANGERITHA KIMARO', 'receptiondodoma@braick.com', '0746526243', 'reception', 2, '', 0, NULL, NULL, 'inactive', '2026-09-03 01:31:31', '2026-09-03 01:31:42');

-- --------------------------------------------------------

--
-- Table structure for table `visits`
--

CREATE TABLE `visits` (
  `id` int(11) NOT NULL,
  `visit_number` varchar(50) NOT NULL,
  `visit_date` datetime NOT NULL DEFAULT current_timestamp(),
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `assigned_at` timestamp NULL DEFAULT NULL,
  `receptionist_id` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `visit_type` varchar(255) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `consultation_fee` decimal(15,2) DEFAULT 0.00,
  `status` enum('pending','assigned','with_doctor','lab_test','lab_completed','prescribed','waiting','completed','cancelled') DEFAULT 'pending',
  `symptoms` text DEFAULT NULL,
  `hpi` text DEFAULT NULL,
  `physical_exam` text DEFAULT NULL,
  `complaint` text DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `disease_id` int(11) DEFAULT NULL,
  `disease_code` varchar(50) DEFAULT NULL,
  `treatment` text DEFAULT NULL,
  `follow_up_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_referred` tinyint(1) DEFAULT 0,
  `referred_by_doctor_id` int(11) DEFAULT NULL,
  `referred_to_doctor_id` int(11) DEFAULT NULL,
  `referral_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_completed` tinyint(1) DEFAULT 0,
  `completed_at` timestamp NULL DEFAULT NULL,
  `lab_fees_total` decimal(15,2) DEFAULT 0.00,
  `pharmacy_fees_total` decimal(15,2) DEFAULT 0.00,
  `other_fees_total` decimal(15,2) DEFAULT 0.00,
  `visit_total` decimal(15,2) DEFAULT 0.00,
  `payment_status` enum('pending','partial','paid','cancelled') DEFAULT 'pending',
  `total_discount` decimal(15,2) DEFAULT 0.00,
  `discount_percent` decimal(5,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vital_signs`
--

CREATE TABLE `vital_signs` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `visit_id` int(11) DEFAULT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `recorded_by` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `temperature` decimal(4,1) DEFAULT NULL,
  `blood_pressure_systolic` int(11) DEFAULT NULL,
  `blood_pressure_diastolic` int(11) DEFAULT NULL,
  `pulse_rate` int(11) DEFAULT NULL,
  `respiratory_rate` int(11) DEFAULT NULL,
  `oxygen_saturation` int(11) DEFAULT NULL,
  `blood_glucose` decimal(5,1) DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `height` decimal(5,2) DEFAULT NULL,
  `bmi` decimal(4,1) DEFAULT NULL,
  `muac` decimal(4,1) DEFAULT NULL,
  `pain_score` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vital_signs`
--

INSERT INTO `vital_signs` (`id`, `patient_id`, `visit_id`, `appointment_id`, `recorded_by`, `branch_id`, `temperature`, `blood_pressure_systolic`, `blood_pressure_diastolic`, `pulse_rate`, `respiratory_rate`, `oxygen_saturation`, `blood_glucose`, `weight`, `height`, `bmi`, `muac`, `pain_score`, `notes`, `recorded_at`, `created_at`, `updated_at`) VALUES
(44, 61, NULL, NULL, 10, 1, 39.0, 122, 81, 73, NULL, NULL, NULL, 73.00, 178.00, 23.0, NULL, NULL, NULL, '2026-08-29 17:51:46', '2026-08-29 17:51:46', '2026-08-29 17:51:46'),
(45, 60, NULL, NULL, 10, 1, 34.0, 122, 78, 71, NULL, NULL, NULL, 76.00, 171.00, 26.0, NULL, NULL, NULL, '2026-08-29 17:52:57', '2026-08-29 17:52:57', '2026-08-29 17:52:57'),
(46, 59, NULL, NULL, 10, 1, 37.0, 159, 90, 67, NULL, NULL, NULL, 75.80, 181.00, 23.1, NULL, NULL, NULL, '2026-08-29 17:53:52', '2026-08-29 17:53:52', '2026-08-29 17:53:52'),
(47, 58, NULL, NULL, 10, 1, 33.0, 139, 94, 98, NULL, NULL, NULL, 69.00, 175.00, 22.5, NULL, NULL, NULL, '2026-08-29 17:55:12', '2026-08-29 17:55:12', '2026-08-29 17:55:12'),
(48, 57, NULL, NULL, 10, 1, 37.0, 119, 78, 67, NULL, NULL, NULL, 68.00, 189.00, 19.0, NULL, NULL, NULL, '2026-08-29 17:56:15', '2026-08-29 17:56:15', '2026-08-29 17:56:15'),
(49, 56, NULL, NULL, 10, 1, 35.0, 122, 80, 72, NULL, NULL, NULL, 65.00, 170.00, 22.5, NULL, NULL, NULL, '2026-08-29 17:57:27', '2026-08-29 17:57:27', '2026-08-29 17:57:27'),
(50, 55, NULL, NULL, 10, 1, 32.0, 127, 91, 70, NULL, NULL, NULL, 78.00, 175.00, 25.5, NULL, NULL, NULL, '2026-08-29 17:59:00', '2026-08-29 17:59:00', '2026-08-29 17:59:00'),
(51, 54, NULL, NULL, 10, 1, 40.0, 189, 102, 80, NULL, NULL, NULL, 77.60, 178.00, 24.5, NULL, NULL, NULL, '2026-08-29 17:59:59', '2026-08-29 17:59:59', '2026-08-29 17:59:59'),
(52, 53, NULL, NULL, 10, 1, 40.0, 122, 75, 70, NULL, NULL, NULL, 71.00, 179.70, 22.0, NULL, NULL, NULL, '2026-08-29 18:01:12', '2026-08-29 18:01:12', '2026-08-29 18:01:12'),
(53, 52, NULL, NULL, 10, 1, 30.0, 129, 90, 70, NULL, NULL, NULL, 60.00, 180.00, 18.5, NULL, NULL, NULL, '2026-08-29 18:02:22', '2026-08-29 18:02:22', '2026-08-29 18:02:22'),
(54, 61, NULL, NULL, 10, 1, 39.0, 122, 81, 73, NULL, NULL, NULL, 73.00, 178.00, 23.0, NULL, NULL, NULL, '2026-09-02 09:40:17', '2026-09-02 09:40:17', '2026-09-02 09:40:17'),
(55, 60, NULL, NULL, 10, 1, 30.0, 120, 80, 70, NULL, NULL, NULL, 67.00, 180.00, 20.7, NULL, NULL, NULL, '2026-09-02 22:11:18', '2026-09-02 22:11:18', '2026-09-02 22:11:18'),
(56, 60, NULL, NULL, 10, 1, 29.0, 119, 89, 68, NULL, NULL, NULL, 78.00, 180.00, 24.1, NULL, NULL, NULL, '2026-09-02 22:12:03', '2026-09-02 22:12:03', '2026-09-02 22:12:03'),
(57, 59, NULL, NULL, 10, 1, 38.0, 120, 89, 79, NULL, NULL, NULL, 78.00, 185.70, 22.6, NULL, NULL, NULL, '2026-09-02 22:12:55', '2026-09-02 22:12:55', '2026-09-02 22:12:55'),
(58, 58, NULL, NULL, 10, 1, 36.0, 123, 89, 75, NULL, NULL, NULL, 89.00, 79.00, 142.6, NULL, NULL, NULL, '2026-09-02 22:32:58', '2026-09-02 22:32:58', '2026-09-02 22:32:58'),
(59, 57, NULL, NULL, 10, 1, 35.0, 120, 80, 65, NULL, NULL, NULL, 65.00, 167.00, 23.3, NULL, NULL, NULL, '2026-09-02 22:33:39', '2026-09-02 22:33:39', '2026-09-02 22:33:39'),
(60, 56, NULL, NULL, 10, 1, 35.0, 122, 80, 72, NULL, NULL, NULL, 65.00, 170.00, 22.5, NULL, NULL, NULL, '2026-09-02 22:34:17', '2026-09-02 22:34:17', '2026-09-02 22:34:17'),
(61, 52, NULL, NULL, 10, 1, 30.0, 129, 90, 70, NULL, NULL, NULL, 60.00, 180.00, 18.5, NULL, NULL, NULL, '2026-09-02 22:35:06', '2026-09-02 22:35:06', '2026-09-02 22:35:06'),
(62, 61, NULL, NULL, 10, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-03 20:43:16', '2026-09-03 20:43:16', '2026-09-03 20:43:16'),
(63, 61, NULL, NULL, 10, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-03 22:24:25', '2026-09-03 22:24:25', '2026-09-03 22:24:25'),
(64, 61, NULL, NULL, 10, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-03 22:46:52', '2026-09-03 22:46:52', '2026-09-03 22:46:52'),
(65, 61, NULL, NULL, 10, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-03 22:53:08', '2026-09-03 22:53:08', '2026-09-03 22:53:08'),
(66, 61, NULL, NULL, 10, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-03 23:03:53', '2026-09-03 23:03:53', '2026-09-03 23:03:53'),
(67, 61, NULL, NULL, 10, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-03 23:29:51', '2026-09-03 23:29:51', '2026-09-03 23:29:51'),
(68, 61, NULL, NULL, 10, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-04 00:01:54', '2026-09-04 00:01:54', '2026-09-04 00:01:54'),
(69, 61, NULL, NULL, 10, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-04 00:28:42', '2026-09-04 00:28:42', '2026-09-04 00:28:42'),
(70, 61, NULL, NULL, 10, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-04 12:00:52', '2026-09-04 12:00:52', '2026-09-04 12:00:52'),
(71, 61, NULL, NULL, 10, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-04 16:31:27', '2026-09-04 16:31:27', '2026-09-04 16:31:27'),
(72, 60, NULL, NULL, 10, 1, 29.0, 119, 89, 68, NULL, NULL, NULL, 78.00, 180.00, 24.1, NULL, NULL, NULL, '2026-09-04 16:32:05', '2026-09-04 16:32:05', '2026-09-04 16:32:05'),
(73, 59, NULL, NULL, 10, 1, 38.0, 120, 89, 79, NULL, NULL, NULL, 78.00, 185.70, 22.6, NULL, NULL, NULL, '2026-09-04 16:41:54', '2026-09-04 16:41:54', '2026-09-04 16:41:54'),
(74, 61, NULL, NULL, 10, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-04 17:05:44', '2026-09-04 17:05:44', '2026-09-04 17:05:44'),
(75, 60, NULL, NULL, 10, 1, 29.0, 119, 89, 68, NULL, NULL, NULL, 78.00, 180.00, 24.1, NULL, NULL, NULL, '2026-09-04 17:06:02', '2026-09-04 17:06:02', '2026-09-04 17:06:02'),
(76, 59, NULL, NULL, 10, 1, 38.0, 120, 89, 79, NULL, NULL, NULL, 78.00, 185.70, 22.6, NULL, NULL, NULL, '2026-09-04 17:06:14', '2026-09-04 17:06:14', '2026-09-04 17:06:14'),
(77, 61, NULL, NULL, 11, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-04 18:47:44', '2026-09-04 18:47:44', '2026-09-04 18:47:44'),
(78, 61, NULL, NULL, 11, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-04 19:56:53', '2026-09-04 19:56:53', '2026-09-04 19:56:53'),
(79, 61, NULL, NULL, 11, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-04 20:02:37', '2026-09-04 20:02:37', '2026-09-04 20:02:37'),
(80, 60, NULL, NULL, 11, 1, 29.0, 119, 89, 68, NULL, NULL, NULL, 78.00, 180.00, 24.1, NULL, NULL, NULL, '2026-09-04 20:06:23', '2026-09-04 20:06:23', '2026-09-04 20:06:23'),
(81, 59, NULL, NULL, 11, 1, 38.0, 120, 89, 79, NULL, NULL, NULL, 78.00, 185.70, 22.6, NULL, NULL, NULL, '2026-09-04 20:08:51', '2026-09-04 20:08:51', '2026-09-04 20:08:51'),
(82, 61, NULL, NULL, 11, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-04 20:36:07', '2026-09-04 20:36:07', '2026-09-04 20:36:07'),
(83, 61, NULL, NULL, 11, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-04 22:02:33', '2026-09-04 22:02:33', '2026-09-04 22:02:33'),
(84, 61, NULL, NULL, 11, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-04 22:08:06', '2026-09-04 22:08:06', '2026-09-04 22:08:06'),
(85, 61, NULL, NULL, 11, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-04 22:14:58', '2026-09-04 22:14:58', '2026-09-04 22:14:58'),
(86, 61, NULL, NULL, 11, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-04 23:10:35', '2026-09-04 23:10:35', '2026-09-04 23:10:35'),
(87, 61, NULL, NULL, 11, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-04 23:36:01', '2026-09-04 23:36:01', '2026-09-04 23:36:01'),
(88, 61, NULL, NULL, 11, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-05 07:32:35', '2026-09-05 07:32:35', '2026-09-05 07:32:35'),
(89, 61, NULL, NULL, 11, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-05 09:05:14', '2026-09-05 09:05:14', '2026-09-05 09:05:14'),
(90, 61, NULL, NULL, 11, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-05 09:37:27', '2026-09-05 09:37:27', '2026-09-05 09:37:27'),
(91, 61, NULL, NULL, 11, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-05 11:55:37', '2026-09-05 11:55:37', '2026-09-05 11:55:37'),
(92, 61, NULL, NULL, 11, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-05 13:10:44', '2026-09-05 13:10:44', '2026-09-05 13:10:44'),
(93, 61, NULL, NULL, 10, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-05 13:54:42', '2026-09-05 13:54:42', '2026-09-05 13:54:42'),
(94, 61, NULL, NULL, 11, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-05 14:19:32', '2026-09-05 14:19:32', '2026-09-05 14:19:32'),
(95, 61, NULL, NULL, 10, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-05 14:41:00', '2026-09-05 14:41:00', '2026-09-05 14:41:00'),
(96, 61, NULL, NULL, 10, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-05 14:53:57', '2026-09-05 14:53:57', '2026-09-05 14:53:57'),
(97, 58, NULL, NULL, 10, 1, 36.0, 123, 89, 75, NULL, NULL, NULL, 89.00, 79.00, 142.6, NULL, NULL, NULL, '2026-09-05 15:05:58', '2026-09-05 15:05:58', '2026-09-05 15:05:58'),
(98, 61, NULL, NULL, 10, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-05 19:57:44', '2026-09-05 19:57:44', '2026-09-05 19:57:44'),
(99, 61, NULL, NULL, 10, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-05 20:35:17', '2026-09-05 20:35:17', '2026-09-05 20:35:17'),
(100, 61, NULL, NULL, 10, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-05 20:57:41', '2026-09-05 20:57:41', '2026-09-05 20:57:41'),
(101, 61, NULL, NULL, 10, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-05 21:07:24', '2026-09-05 21:07:24', '2026-09-05 21:07:24'),
(102, 61, NULL, NULL, 10, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-05 21:12:25', '2026-09-05 21:12:25', '2026-09-05 21:12:25'),
(103, 47, NULL, NULL, 10, 1, 34.0, 128, 89, 68, NULL, NULL, NULL, 67.00, 167.00, 24.0, NULL, NULL, NULL, '2026-09-05 21:20:05', '2026-09-05 21:20:05', '2026-09-05 21:20:05'),
(104, 61, NULL, NULL, 10, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-05 21:31:11', '2026-09-05 21:31:11', '2026-09-05 21:31:11'),
(105, 61, NULL, NULL, 10, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-06 06:02:59', '2026-09-06 06:02:59', '2026-09-06 06:02:59'),
(106, 49, NULL, NULL, 10, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-06 06:25:09', '2026-09-06 06:25:09', '2026-09-06 06:25:09'),
(107, 60, NULL, NULL, 10, 1, 29.0, 119, 89, 68, NULL, NULL, NULL, 78.00, 180.00, 24.1, NULL, NULL, NULL, '2026-09-06 08:49:26', '2026-09-06 08:49:26', '2026-09-06 08:49:26'),
(108, 61, NULL, NULL, 10, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-06 11:22:29', '2026-09-06 11:22:29', '2026-09-06 11:22:29'),
(109, 61, NULL, NULL, 10, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-06 12:36:46', '2026-09-06 12:36:46', '2026-09-06 12:36:46'),
(110, 58, NULL, NULL, 10, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-06 12:44:40', '2026-09-06 12:44:40', '2026-09-06 12:44:40'),
(111, 61, NULL, NULL, 10, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-06 13:03:45', '2026-09-06 13:03:45', '2026-09-06 13:03:45'),
(112, 61, NULL, NULL, 10, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-06 14:08:36', '2026-09-06 14:08:36', '2026-09-06 14:08:36'),
(113, 61, NULL, NULL, 10, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-06 14:45:42', '2026-09-06 14:45:42', '2026-09-06 14:45:42'),
(114, 61, NULL, NULL, 12, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-09 14:32:23', '2026-09-09 14:32:23', '2026-09-09 14:32:23'),
(115, 61, NULL, NULL, 12, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-09 15:08:13', '2026-09-09 15:08:13', '2026-09-09 15:08:13'),
(116, 61, NULL, NULL, 12, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-09 15:17:20', '2026-09-09 15:17:20', '2026-09-09 15:17:20');

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_bill_items_summary`
-- (See below for the actual view)
--
CREATE TABLE `v_bill_items_summary` (
`bill_id` int(11)
,`bill_number` varchar(50)
,`patient_id` int(11)
,`patient_name` varchar(100)
,`item_type` enum('registration','consultation','lab_test','medication','procedure','equipment','tool','other')
,`item_name` varchar(255)
,`quantity` int(11)
,`unit_price` decimal(12,2)
,`total_price` decimal(12,2)
,`final_price` decimal(12,2)
,`item_status` enum('pending','paid','cancelled','refunded')
,`branch_name` varchar(100)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_patient_visit_summary`
-- (See below for the actual view)
--
CREATE TABLE `v_patient_visit_summary` (
`patient_id` int(11)
,`patient_code` varchar(50)
,`full_name` varchar(100)
,`phone` varchar(20)
,`visit_id` int(11)
,`visit_number` varchar(50)
,`visit_date` datetime
,`visit_type` varchar(255)
,`visit_status` enum('pending','assigned','with_doctor','lab_test','lab_completed','prescribed','waiting','completed','cancelled')
,`bill_id` int(11)
,`bill_number` varchar(50)
,`subtotal` decimal(12,2)
,`discount_amount` decimal(12,2)
,`total_amount` decimal(12,2)
,`paid_amount` decimal(12,2)
,`balance` decimal(12,2)
,`bill_status` enum('pending','partial','paid','cancelled')
,`doctor_name` varchar(100)
,`branch_name` varchar(100)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_prescription_stats`
-- (See below for the actual view)
--
CREATE TABLE `v_prescription_stats` (
`branch_id` int(11)
,`branch_name` varchar(100)
,`total_prescriptions` bigint(21)
,`pending_count` decimal(22,0)
,`confirmed_count` decimal(22,0)
,`dispensed_count` decimal(22,0)
,`cancelled_count` decimal(22,0)
,`total_value` decimal(34,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_revenue_summary`
-- (See below for the actual view)
--
CREATE TABLE `v_revenue_summary` (
`sale_date` date
,`branch_id` int(11)
,`branch_name` varchar(100)
,`total_bills` bigint(21)
,`total_revenue` decimal(34,2)
,`total_paid` decimal(34,2)
,`total_balance` decimal(34,2)
,`cash_revenue` decimal(34,2)
,`mobile_revenue` decimal(34,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_stock_summary`
-- (See below for the actual view)
--
CREATE TABLE `v_stock_summary` (
`branch_id` int(11)
,`stock_type` varchar(9)
,`total_items` bigint(21)
,`total_quantity` decimal(32,0)
,`low_stock` decimal(22,0)
,`out_of_stock` decimal(22,0)
,`expired_quantity` decimal(32,0)
);

-- --------------------------------------------------------

--
-- Structure for view `v_bill_items_summary`
--
DROP TABLE IF EXISTS `v_bill_items_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_bill_items_summary`  AS SELECT `b`.`id` AS `bill_id`, `b`.`bill_number` AS `bill_number`, `b`.`patient_id` AS `patient_id`, `p`.`full_name` AS `patient_name`, `bi`.`item_type` AS `item_type`, `bi`.`item_name` AS `item_name`, `bi`.`quantity` AS `quantity`, `bi`.`unit_price` AS `unit_price`, `bi`.`total_price` AS `total_price`, `bi`.`final_price` AS `final_price`, `bi`.`status` AS `item_status`, `br`.`name` AS `branch_name` FROM (((`bills` `b` join `bill_items` `bi` on(`b`.`id` = `bi`.`bill_id`)) join `patients` `p` on(`b`.`patient_id` = `p`.`id`)) left join `branches` `br` on(`b`.`branch_id` = `br`.`id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `v_patient_visit_summary`
--
DROP TABLE IF EXISTS `v_patient_visit_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_patient_visit_summary`  AS SELECT `p`.`id` AS `patient_id`, `p`.`patient_id` AS `patient_code`, `p`.`full_name` AS `full_name`, `p`.`phone` AS `phone`, `v`.`id` AS `visit_id`, `v`.`visit_number` AS `visit_number`, `v`.`visit_date` AS `visit_date`, `v`.`visit_type` AS `visit_type`, `v`.`status` AS `visit_status`, `b`.`id` AS `bill_id`, `b`.`bill_number` AS `bill_number`, `b`.`subtotal` AS `subtotal`, `b`.`discount_amount` AS `discount_amount`, `b`.`total_amount` AS `total_amount`, `b`.`paid_amount` AS `paid_amount`, `b`.`balance` AS `balance`, `b`.`status` AS `bill_status`, `u`.`full_name` AS `doctor_name`, `br`.`name` AS `branch_name` FROM ((((`patients` `p` left join `visits` `v` on(`p`.`id` = `v`.`patient_id`)) left join `bills` `b` on(`v`.`id` = `b`.`visit_id`)) left join `users` `u` on(`v`.`doctor_id` = `u`.`id`)) left join `branches` `br` on(`v`.`branch_id` = `br`.`id`)) WHERE `v`.`status` <> 'cancelled' ;

-- --------------------------------------------------------

--
-- Structure for view `v_prescription_stats`
--
DROP TABLE IF EXISTS `v_prescription_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prescription_stats`  AS SELECT `p`.`branch_id` AS `branch_id`, `br`.`name` AS `branch_name`, count(distinct `p`.`id`) AS `total_prescriptions`, sum(case when `p`.`status` = 'pending' then 1 else 0 end) AS `pending_count`, sum(case when `p`.`status` = 'confirmed' then 1 else 0 end) AS `confirmed_count`, sum(case when `p`.`status` = 'dispensed' then 1 else 0 end) AS `dispensed_count`, sum(case when `p`.`status` = 'cancelled' then 1 else 0 end) AS `cancelled_count`, sum(`pi`.`total_price`) AS `total_value` FROM ((`prescriptions` `p` join `branches` `br` on(`p`.`branch_id` = `br`.`id`)) left join `prescription_items` `pi` on(`p`.`id` = `pi`.`prescription_id`)) GROUP BY `p`.`branch_id` ;

-- --------------------------------------------------------

--
-- Structure for view `v_revenue_summary`
--
DROP TABLE IF EXISTS `v_revenue_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_revenue_summary`  AS SELECT cast(`b`.`created_at` as date) AS `sale_date`, `b`.`branch_id` AS `branch_id`, `br`.`name` AS `branch_name`, count(distinct `b`.`id`) AS `total_bills`, sum(`b`.`total_amount`) AS `total_revenue`, sum(`b`.`paid_amount`) AS `total_paid`, sum(`b`.`balance`) AS `total_balance`, sum(case when `b`.`payment_method` = 'cash' then `b`.`paid_amount` else 0 end) AS `cash_revenue`, sum(case when `b`.`payment_method` in ('m-pesa','airtel_money','tigo_pesa','halopesa') then `b`.`paid_amount` else 0 end) AS `mobile_revenue` FROM (`bills` `b` join `branches` `br` on(`b`.`branch_id` = `br`.`id`)) WHERE `b`.`status` in ('paid','partial') GROUP BY cast(`b`.`created_at` as date), `b`.`branch_id` ;

-- --------------------------------------------------------

--
-- Structure for view `v_stock_summary`
--
DROP TABLE IF EXISTS `v_stock_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_stock_summary`  AS SELECT `medications_inventory`.`branch_id` AS `branch_id`, 'Medicine' AS `stock_type`, count(distinct `medications_inventory`.`medication_name`) AS `total_items`, sum(`medications_inventory`.`quantity`) AS `total_quantity`, sum(case when `medications_inventory`.`quantity` <= `medications_inventory`.`reorder_level` and `medications_inventory`.`quantity` > 0 then 1 else 0 end) AS `low_stock`, sum(case when `medications_inventory`.`quantity` = 0 then 1 else 0 end) AS `out_of_stock`, sum(case when `medications_inventory`.`expiry_date` < curdate() then `medications_inventory`.`quantity` else 0 end) AS `expired_quantity` FROM `medications_inventory` WHERE `medications_inventory`.`status` = 'active' GROUP BY `medications_inventory`.`branch_id`union all select `medical_equipment`.`branch_id` AS `branch_id`,'Equipment' AS `stock_type`,count(distinct `medical_equipment`.`equipment_name`) AS `total_items`,sum(`medical_equipment`.`quantity`) AS `total_quantity`,sum(case when `medical_equipment`.`quantity` <= `medical_equipment`.`reorder_level` and `medical_equipment`.`quantity` > 0 then 1 else 0 end) AS `low_stock`,sum(case when `medical_equipment`.`quantity` = 0 then 1 else 0 end) AS `out_of_stock`,sum(case when `medical_equipment`.`expiry_date` < curdate() then `medical_equipment`.`quantity` else 0 end) AS `expired_quantity` from `medical_equipment` where `medical_equipment`.`status` = 'active' group by `medical_equipment`.`branch_id`  ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `visit_id` (`visit_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `idx_appointments_date` (`appointment_date`);

--
-- Indexes for table `bills`
--
ALTER TABLE `bills`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bill_number` (`bill_number`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `visit_id` (`visit_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_bills_patient_status` (`patient_id`,`status`);

--
-- Indexes for table `bill_items`
--
ALTER TABLE `bill_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bill_id` (`bill_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `item_type` (`item_type`),
  ADD KEY `idx_bill_items_bill_type` (`bill_id`,`item_type`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `diseases`
--
ALTER TABLE `diseases`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `disease_code` (`disease_code`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `idx_disease_name` (`disease_name`);

--
-- Indexes for table `employee_departments`
--
ALTER TABLE `employee_departments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `assigned_by` (`assigned_by`);

--
-- Indexes for table `employee_roles`
--
ALTER TABLE `employee_roles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `assigned_by` (`assigned_by`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_branch` (`branch_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_payment_date` (`payment_date`);

--
-- Indexes for table `external_sick_sheets`
--
ALTER TABLE `external_sick_sheets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `document_number` (`document_number`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `lab_result_templates`
--
ALTER TABLE `lab_result_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_test_type` (`test_type`);

--
-- Indexes for table `lab_tests`
--
ALTER TABLE `lab_tests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `visit_id` (`visit_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `test_id` (`test_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `idx_lab_tests_status` (`status`);

--
-- Indexes for table `lab_tests_catalog`
--
ALTER TABLE `lab_tests_catalog`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `test_code` (`test_code`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `required_equipment_id` (`required_equipment_id`);

--
-- Indexes for table `lab_test_equipment`
--
ALTER TABLE `lab_test_equipment`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lab_test_id` (`lab_test_id`),
  ADD KEY `equipment_id` (`equipment_id`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `medical_equipment`
--
ALTER TABLE `medical_equipment`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_equipment_batch` (`equipment_name`,`batch_number`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `equipment_name` (`equipment_name`),
  ADD KEY `batch_number` (`batch_number`);

--
-- Indexes for table `medications_inventory`
--
ALTER TABLE `medications_inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_medication_batch` (`medication_name`,`batch_number`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `medication_name` (`medication_name`),
  ADD KEY `batch_number` (`batch_number`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indexes for table `otc_sales`
--
ALTER TABLE `otc_sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sale_number` (`sale_number`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `bill_id` (`bill_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `idx_otc_sales_date` (`created_at`);

--
-- Indexes for table `otc_sale_items`
--
ALTER TABLE `otc_sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `inventory_id` (`inventory_id`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email` (`email`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `patient_id` (`patient_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `assigned_doctor_id` (`assigned_doctor_id`),
  ADD KEY `idx_patients_name` (`full_name`),
  ADD KEY `idx_patients_phone` (`phone`);

--
-- Indexes for table `patient_documents`
--
ALTER TABLE `patient_documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `document_number` (`document_number`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `visit_id` (`visit_id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `uploaded_by` (`uploaded_by`),
  ADD KEY `verified_by` (`verified_by`),
  ADD KEY `document_type` (`document_type`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `receipt_number` (`receipt_number`),
  ADD KEY `bill_id` (`bill_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `idx_payments_bill` (`bill_id`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `prescription_number` (`prescription_number`),
  ADD KEY `visit_id` (`visit_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `pharmacy_id` (`pharmacy_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `idx_prescriptions_patient` (`patient_id`),
  ADD KEY `idx_prescriptions_status` (`status`);

--
-- Indexes for table `prescription_items`
--
ALTER TABLE `prescription_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `prescription_id` (`prescription_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `inventory_id` (`inventory_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `idx_prescription_items_prescription` (`prescription_id`);

--
-- Indexes for table `procedures`
--
ALTER TABLE `procedures`
  ADD PRIMARY KEY (`id`),
  ADD KEY `visit_id` (`visit_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `procedure_id` (`procedure_id`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `procedures_catalog`
--
ALTER TABLE `procedures_catalog`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `procedure_code` (`procedure_code`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `required_equipment_id` (`required_equipment_id`);

--
-- Indexes for table `purchases`
--
ALTER TABLE `purchases`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `purchase_items`
--
ALTER TABLE `purchase_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_id` (`purchase_id`),
  ADD KEY `added_by` (`added_by`),
  ADD KEY `fk_purchase_items_medicine` (`medicine_id`),
  ADD KEY `fk_purchase_items_equipment` (`equipment_id`);

--
-- Indexes for table `receipts`
--
ALTER TABLE `receipts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `receipt_number` (`receipt_number`),
  ADD KEY `payment_id` (`payment_id`),
  ADD KEY `bill_id` (`bill_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `referrals`
--
ALTER TABLE `referrals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `referral_number` (`referral_number`),
  ADD KEY `visit_id` (`visit_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `from_doctor_id` (`from_doctor_id`),
  ADD KEY `to_doctor_id` (`to_doctor_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `idx_referrals_status` (`status`),
  ADD KEY `idx_referrals_date` (`referral_date`),
  ADD KEY `referrals_ibfk_6` (`created_by`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `service_categories`
--
ALTER TABLE `service_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `category_name` (`category_name`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inventory_id` (`inventory_id`),
  ADD KEY `equipment_id` (`equipment_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `idx_stock_movements_date` (`created_at`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `visits`
--
ALTER TABLE `visits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `visit_number` (`visit_number`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `receptionist_id` (`receptionist_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `idx_visits_patient_status` (`patient_id`,`status`),
  ADD KEY `idx_visits_doctor_date` (`doctor_id`,`visit_date`),
  ADD KEY `service_id` (`service_id`);

--
-- Indexes for table `vital_signs`
--
ALTER TABLE `vital_signs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `visit_id` (`visit_id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `recorded_by` (`recorded_by`),
  ADD KEY `branch_id` (`branch_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1100;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bills`
--
ALTER TABLE `bills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=313;

--
-- AUTO_INCREMENT for table `bill_items`
--
ALTER TABLE `bill_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=626;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `diseases`
--
ALTER TABLE `diseases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `employee_departments`
--
ALTER TABLE `employee_departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `employee_roles`
--
ALTER TABLE `employee_roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `external_sick_sheets`
--
ALTER TABLE `external_sick_sheets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `lab_result_templates`
--
ALTER TABLE `lab_result_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `lab_tests`
--
ALTER TABLE `lab_tests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=165;

--
-- AUTO_INCREMENT for table `lab_tests_catalog`
--
ALTER TABLE `lab_tests_catalog`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `lab_test_equipment`
--
ALTER TABLE `lab_test_equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `medical_equipment`
--
ALTER TABLE `medical_equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `medications_inventory`
--
ALTER TABLE `medications_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=229;

--
-- AUTO_INCREMENT for table `otc_sales`
--
ALTER TABLE `otc_sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `otc_sale_items`
--
ALTER TABLE `otc_sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `patient_documents`
--
ALTER TABLE `patient_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=108;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=97;

--
-- AUTO_INCREMENT for table `prescription_items`
--
ALTER TABLE `prescription_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=97;

--
-- AUTO_INCREMENT for table `procedures`
--
ALTER TABLE `procedures`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=132;

--
-- AUTO_INCREMENT for table `procedures_catalog`
--
ALTER TABLE `procedures_catalog`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `purchases`
--
ALTER TABLE `purchases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `purchase_items`
--
ALTER TABLE `purchase_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `receipts`
--
ALTER TABLE `receipts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `referrals`
--
ALTER TABLE `referrals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `service_categories`
--
ALTER TABLE `service_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=196;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `visits`
--
ALTER TABLE `visits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=153;

--
-- AUTO_INCREMENT for table `vital_signs`
--
ALTER TABLE `vital_signs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=117;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `activity_logs_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `activity_logs_ibfk_3` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `appointments_ibfk_4` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `bills`
--
ALTER TABLE `bills`
  ADD CONSTRAINT `bills_ibfk_2` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `bills_ibfk_3` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `bills_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `bill_items`
--
ALTER TABLE `bill_items`
  ADD CONSTRAINT `bill_items_ibfk_1` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bill_items_ibfk_3` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `employee_departments`
--
ALTER TABLE `employee_departments`
  ADD CONSTRAINT `employee_departments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_departments_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `service_categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_departments_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `employee_roles`
--
ALTER TABLE `employee_roles`
  ADD CONSTRAINT `employee_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_roles_ibfk_2` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `lab_tests`
--
ALTER TABLE `lab_tests`
  ADD CONSTRAINT `lab_tests_ibfk_1` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lab_tests_ibfk_3` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `lab_tests_ibfk_4` FOREIGN KEY (`test_id`) REFERENCES `lab_tests_catalog` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `lab_tests_ibfk_5` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `lab_tests_catalog`
--
ALTER TABLE `lab_tests_catalog`
  ADD CONSTRAINT `lab_tests_catalog_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `lab_tests_catalog_ibfk_2` FOREIGN KEY (`required_equipment_id`) REFERENCES `medical_equipment` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `medical_equipment`
--
ALTER TABLE `medical_equipment`
  ADD CONSTRAINT `medical_equipment_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `medications_inventory`
--
ALTER TABLE `medications_inventory`
  ADD CONSTRAINT `medications_inventory_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `otc_sales`
--
ALTER TABLE `otc_sales`
  ADD CONSTRAINT `otc_sales_ibfk_2` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `otc_sales_ibfk_3` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `otc_sale_items`
--
ALTER TABLE `otc_sale_items`
  ADD CONSTRAINT `otc_sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `otc_sales` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `otc_sale_items_ibfk_3` FOREIGN KEY (`inventory_id`) REFERENCES `medications_inventory` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `otc_sale_items_ibfk_4` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `patients`
--
ALTER TABLE `patients`
  ADD CONSTRAINT `patients_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `patients_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `patients_ibfk_3` FOREIGN KEY (`assigned_doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `patient_documents`
--
ALTER TABLE `patient_documents`
  ADD CONSTRAINT `patient_documents_ibfk_2` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `patient_documents_ibfk_3` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `patient_documents_ibfk_4` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `patient_documents_ibfk_5` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `patient_documents_ibfk_6` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `prescriptions_ibfk_3` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `prescriptions_ibfk_4` FOREIGN KEY (`pharmacy_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `prescriptions_ibfk_5` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `prescription_items`
--
ALTER TABLE `prescription_items`
  ADD CONSTRAINT `prescription_items_ibfk_1` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `prescription_items_ibfk_3` FOREIGN KEY (`inventory_id`) REFERENCES `medications_inventory` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `prescription_items_ibfk_4` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `procedures`
--
ALTER TABLE `procedures`
  ADD CONSTRAINT `procedures_ibfk_1` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `procedures_ibfk_3` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `procedures_ibfk_4` FOREIGN KEY (`procedure_id`) REFERENCES `procedures_catalog` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `procedures_ibfk_5` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `procedures_catalog`
--
ALTER TABLE `procedures_catalog`
  ADD CONSTRAINT `procedures_catalog_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `procedures_catalog_ibfk_2` FOREIGN KEY (`required_equipment_id`) REFERENCES `medical_equipment` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `purchase_items`
--
ALTER TABLE `purchase_items`
  ADD CONSTRAINT `fk_purchase_items_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `medical_equipment` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_purchase_items_medicine` FOREIGN KEY (`medicine_id`) REFERENCES `medications_inventory` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `purchase_items_ibfk_1` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `receipts`
--
ALTER TABLE `receipts`
  ADD CONSTRAINT `receipts_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`),
  ADD CONSTRAINT `receipts_ibfk_2` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `receipts_ibfk_3` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `receipts_ibfk_4` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `referrals`
--
ALTER TABLE `referrals`
  ADD CONSTRAINT `referrals_ibfk_1` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `referrals_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `referrals_ibfk_3` FOREIGN KEY (`from_doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `referrals_ibfk_4` FOREIGN KEY (`to_doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `referrals_ibfk_5` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `referrals_ibfk_6` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `service_categories`
--
ALTER TABLE `service_categories`
  ADD CONSTRAINT `fk_service_categories_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`inventory_id`) REFERENCES `medications_inventory` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `stock_movements_ibfk_2` FOREIGN KEY (`equipment_id`) REFERENCES `medical_equipment` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `stock_movements_ibfk_4` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `visits`
--
ALTER TABLE `visits`
  ADD CONSTRAINT `visits_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `visits_ibfk_3` FOREIGN KEY (`receptionist_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `visits_ibfk_4` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `vital_signs`
--
ALTER TABLE `vital_signs`
  ADD CONSTRAINT `vital_signs_ibfk_2` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `vital_signs_ibfk_3` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `vital_signs_ibfk_4` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vital_signs_ibfk_5` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
