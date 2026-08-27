-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 27, 2026 at 11:33 PM
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
(158, 10, 1, NULL, 'user_login', 'User logged in: Reception SALOME (Role: reception)', NULL, NULL, '2026-08-27 21:11:42', '2026-08-27 21:11:42');

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
-- Dumping data for table `bills`
--

INSERT INTO `bills` (`id`, `bill_number`, `patient_id`, `visit_id`, `branch_id`, `created_by`, `subtotal`, `discount_percent`, `discount_amount`, `total_amount`, `paid_amount`, `balance`, `status`, `payment_method`, `notes`, `created_at`, `updated_at`) VALUES
(183, 'BILL-20260826-0052-6103', 52, NULL, 1, 11, 25000.00, 0.00, 0.00, 25000.00, 0.00, 25000.00, 'pending', 'cash', NULL, '2026-08-26 19:49:56', '2026-08-26 19:49:56'),
(184, 'BILL-20260826-0054-6261', 54, NULL, 1, 11, 10000.00, 0.00, 0.00, 10000.00, 0.00, 10000.00, 'pending', 'cash', NULL, '2026-08-26 19:55:59', '2026-08-26 19:55:59'),
(185, 'BILL-20260826-0055-7995', 55, NULL, 1, 11, 25000.00, 0.00, 0.00, 25000.00, 0.00, 25000.00, 'pending', 'cash', NULL, '2026-08-26 19:59:21', '2026-08-26 19:59:21'),
(186, 'BILL-20260826-0056-8122', 56, NULL, 1, 11, 30000.00, 0.00, 0.00, 30000.00, 0.00, 30000.00, 'pending', 'cash', NULL, '2026-08-26 20:07:21', '2026-08-26 20:07:21'),
(187, 'BILL-20260826-0054-4629', 54, NULL, 1, 11, 30000.00, 0.00, 0.00, 30000.00, 0.00, 30000.00, 'pending', 'cash', NULL, '2026-08-26 20:08:34', '2026-08-26 20:08:34'),
(188, 'BILL-20260826-0053-2492', 53, NULL, 1, 11, 100000.00, 0.00, 0.00, 100000.00, 0.00, 100000.00, 'pending', 'cash', NULL, '2026-08-26 20:13:25', '2026-08-26 20:13:25'),
(189, 'BILL-20260826-0057-6417', 57, NULL, 1, 11, 30000.00, 0.00, 0.00, 30000.00, 0.00, 30000.00, 'pending', 'cash', NULL, '2026-08-26 20:22:10', '2026-08-26 20:22:10'),
(190, 'BILL-20260826-0058-3361', 58, NULL, 1, 11, 30000.00, 0.00, 0.00, 30000.00, 0.00, 30000.00, 'pending', 'cash', NULL, '2026-08-26 20:35:50', '2026-08-26 20:35:50'),
(191, 'BILL-20260826-0058-9973', 58, NULL, 1, 11, 75000.00, 0.00, 0.00, 75000.00, 0.00, 75000.00, 'pending', 'cash', NULL, '2026-08-26 20:42:37', '2026-08-26 20:46:25'),
(192, 'BILL-20260826-0059-4431', 59, NULL, 1, 11, 25000.00, 0.00, 0.00, 25000.00, 0.00, 25000.00, 'pending', 'cash', NULL, '2026-08-26 20:52:13', '2026-08-26 20:52:13'),
(193, 'BILL-20260826-0057-1456', 57, NULL, 1, 11, 15000.00, 0.00, 0.00, 15000.00, 0.00, 15000.00, 'pending', 'cash', NULL, '2026-08-26 20:53:12', '2026-08-26 20:53:12'),
(194, 'BILL-20260826-0060-7865', 60, NULL, 1, 11, 25000.00, 0.00, 0.00, 25000.00, 0.00, 25000.00, 'pending', 'cash', NULL, '2026-08-26 21:00:03', '2026-08-26 21:00:03'),
(195, 'BILL-20260826-0060-8927', 60, NULL, 1, 11, 100000.00, 0.00, 0.00, 100000.00, 0.00, 100000.00, 'pending', 'cash', NULL, '2026-08-26 21:09:25', '2026-08-26 21:09:25'),
(196, 'BILL-20260826-0061-3065', 61, NULL, 1, 11, 30000.00, 0.00, 0.00, 30000.00, 0.00, 30000.00, 'pending', 'cash', NULL, '2026-08-26 21:11:23', '2026-08-26 21:11:23'),
(197, 'BILL-20260826-0061-1887', 61, 59, 1, 11, 255000.00, 0.00, 0.00, 255000.00, 0.00, 255000.00, 'paid', 'cash', NULL, '2026-08-26 21:39:23', '2026-08-26 21:43:36'),
(198, 'BILL-20260827-0055-5480', 55, 60, 1, 11, 100000.00, 0.00, 0.00, 100000.00, 0.00, 100000.00, 'paid', 'cash', NULL, '2026-08-26 22:37:54', '2026-08-26 22:40:30'),
(199, 'BILL-20260827-0060-5575', 60, 61, 1, 11, 92000.00, 0.00, 0.00, 92000.00, 0.00, 92000.00, 'pending', 'cash', NULL, '2026-08-26 22:43:03', '2026-08-27 21:09:46');

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

--
-- Dumping data for table `bill_items`
--

INSERT INTO `bill_items` (`id`, `bill_id`, `patient_id`, `branch_id`, `item_type`, `item_id`, `item_name`, `item_code`, `description`, `quantity`, `unit_price`, `total_price`, `discount_amount`, `tax_amount`, `final_price`, `reference_id`, `reference_type`, `status`, `created_at`, `updated_at`) VALUES
(223, 183, 52, 1, 'consultation', NULL, 'Consultation (Consultation b)', NULL, NULL, 1, 25000.00, 25000.00, 0.00, 0.00, 0.00, NULL, NULL, 'pending', '2026-08-26 19:49:56', '2026-08-26 19:49:56'),
(224, 184, 54, 1, 'consultation', NULL, 'New Patient', NULL, NULL, 1, 10000.00, 10000.00, 0.00, 0.00, 0.00, NULL, NULL, 'pending', '2026-08-26 19:55:59', '2026-08-26 19:55:59'),
(225, 185, 55, 1, 'consultation', NULL, 'Consultation-B', NULL, NULL, 1, 25000.00, 25000.00, 0.00, 0.00, 0.00, NULL, NULL, 'pending', '2026-08-26 19:59:21', '2026-08-26 19:59:21'),
(226, 186, 56, 1, 'consultation', NULL, 'Specialist Consultation', NULL, NULL, 1, 30000.00, 30000.00, 0.00, 0.00, 0.00, NULL, NULL, 'pending', '2026-08-26 20:07:21', '2026-08-26 20:07:21'),
(227, 187, 54, 1, 'consultation', NULL, 'Consultation (Specialist consultation)', NULL, NULL, 1, 30000.00, 30000.00, 0.00, 0.00, 0.00, NULL, NULL, 'pending', '2026-08-26 20:08:34', '2026-08-26 20:08:34'),
(228, 188, 53, 1, 'consultation', NULL, 'Consultation (Visit mpya)', NULL, NULL, 1, 100000.00, 100000.00, 0.00, 0.00, 0.00, NULL, NULL, 'pending', '2026-08-26 20:13:25', '2026-08-26 20:13:25'),
(229, 189, 57, 1, 'consultation', NULL, 'Specialist Consultation', NULL, NULL, 1, 30000.00, 30000.00, 0.00, 0.00, 0.00, NULL, NULL, 'pending', '2026-08-26 20:22:10', '2026-08-26 20:22:10'),
(230, 190, 58, 1, 'consultation', NULL, 'Specialist Consultation', NULL, NULL, 1, 30000.00, 30000.00, 0.00, 0.00, 0.00, NULL, NULL, 'pending', '2026-08-26 20:35:50', '2026-08-26 20:35:50'),
(231, 191, 58, 1, 'consultation', NULL, 'Consultation: Specialist Consultation', NULL, NULL, 1, 30000.00, 30000.00, 0.00, 0.00, 0.00, NULL, NULL, 'pending', '2026-08-26 20:42:37', '2026-08-26 20:42:37'),
(232, 191, 58, 1, 'lab_test', 61, 'Blood Glucose (Random)', NULL, NULL, 1, 8000.00, 8000.00, 0.00, 0.00, 0.00, 61, '', 'pending', '2026-08-26 20:45:03', '2026-08-26 20:45:03'),
(233, 191, 58, 1, 'medication', NULL, 'ALBENDAZOLE (Batch: BATCH-20260825-AEB716)', NULL, NULL, 1, 2000.00, 2000.00, 0.00, 0.00, 0.00, 48, 'prescription', 'pending', '2026-08-26 20:46:05', '2026-08-26 20:46:05'),
(234, 191, 58, 1, 'procedure', 18, 'Cryotherapy', NULL, NULL, 1, 20000.00, 20000.00, 0.00, 0.00, 0.00, 50, 'procedure', 'pending', '2026-08-26 20:46:14', '2026-08-26 20:46:14'),
(235, 191, 58, 1, 'procedure', 19, 'Free - Health Education (FREE)', NULL, 'FREE - No charge', 1, 0.00, 0.00, 0.00, 0.00, 0.00, 51, 'procedure', 'pending', '2026-08-26 20:46:14', '2026-08-26 20:46:14'),
(236, 191, 58, 1, 'equipment', 17, 'Adhesive Tape (Roll) (FREE)', NULL, 'FREE - No charge', 1, 0.00, 0.00, 0.00, 0.00, 0.00, 17, 'equipment', 'pending', '2026-08-26 20:46:25', '2026-08-26 20:46:25'),
(237, 191, 58, 1, 'equipment', 27, 'Surgical Blades (Scalpel) (Batch: BATCH-BLADE-001)', NULL, NULL, 1, 15000.00, 15000.00, 0.00, 0.00, 0.00, 27, 'equipment', 'pending', '2026-08-26 20:46:25', '2026-08-26 20:46:25'),
(238, 192, 59, 1, 'consultation', NULL, 'Consultation-B', NULL, NULL, 1, 25000.00, 25000.00, 0.00, 0.00, 0.00, NULL, NULL, 'pending', '2026-08-26 20:52:13', '2026-08-26 20:52:13'),
(239, 193, 57, 1, 'consultation', NULL, 'Consultation: General Consultation', NULL, NULL, 1, 15000.00, 15000.00, 0.00, 0.00, 0.00, NULL, NULL, 'pending', '2026-08-26 20:53:12', '2026-08-26 20:53:12'),
(240, 194, 60, 1, 'consultation', NULL, 'Consultation-B', NULL, NULL, 1, 25000.00, 25000.00, 0.00, 0.00, 0.00, NULL, NULL, 'pending', '2026-08-26 21:00:03', '2026-08-26 21:00:03'),
(241, 195, 60, 1, 'consultation', NULL, 'Consultation: visit_mpya', NULL, NULL, 1, 100000.00, 100000.00, 0.00, 0.00, 0.00, NULL, NULL, 'pending', '2026-08-26 21:09:25', '2026-08-26 21:09:25'),
(242, 196, 61, 1, 'consultation', NULL, 'Specialist Consultation', NULL, NULL, 1, 30000.00, 30000.00, 0.00, 0.00, 0.00, NULL, NULL, 'pending', '2026-08-26 21:11:23', '2026-08-26 21:11:23'),
(243, 197, 61, 1, 'consultation', NULL, 'Consultation: Specialist Consultation', NULL, NULL, 1, 30000.00, 30000.00, 0.00, 0.00, 0.00, NULL, NULL, 'pending', '2026-08-26 21:39:23', '2026-08-26 21:39:23'),
(244, 197, 61, 1, 'lab_test', 62, 'Helicobacter Pylori Test', NULL, NULL, 1, 15000.00, 15000.00, 0.00, 0.00, 0.00, 62, '', 'pending', '2026-08-26 21:40:35', '2026-08-26 21:40:35'),
(245, 197, 61, 1, 'lab_test', 63, 'Pregnancy Test (Blood - Beta HCG)', NULL, NULL, 1, 20000.00, 20000.00, 0.00, 0.00, 0.00, 63, '', 'pending', '2026-08-26 21:40:35', '2026-08-26 21:40:35'),
(246, 197, 61, 1, 'medication', NULL, 'ALBENDAZOLE (Batch: BATCH-20260825-AEB716)', NULL, NULL, 10, 2000.00, 20000.00, 0.00, 0.00, 0.00, 49, 'prescription', 'pending', '2026-08-26 21:42:31', '2026-08-26 21:42:31'),
(247, 197, 61, 1, 'procedure', 18, 'Cryotherapy', NULL, NULL, 1, 20000.00, 20000.00, 0.00, 0.00, 0.00, 52, 'procedure', 'pending', '2026-08-26 21:42:36', '2026-08-26 21:42:36'),
(248, 197, 61, 1, 'procedure', 20, 'Free - Nutrition Counseling (FREE)', NULL, 'FREE - No charge', 1, 0.00, 0.00, 0.00, 0.00, 0.00, 53, 'procedure', 'pending', '2026-08-26 21:42:36', '2026-08-26 21:42:36'),
(249, 197, 61, 1, 'equipment', 17, 'Adhesive Tape (Roll) (FREE)', NULL, 'FREE - No charge', 5, 0.00, 0.00, 0.00, 0.00, 0.00, 17, 'equipment', 'pending', '2026-08-26 21:42:59', '2026-08-26 21:42:59'),
(250, 197, 61, 1, 'equipment', 28, 'Needle Holder (Surgical) (Batch: BATCH-NEEDLE-001)', NULL, NULL, 5, 30000.00, 150000.00, 0.00, 0.00, 0.00, 28, 'equipment', 'pending', '2026-08-26 21:42:59', '2026-08-26 21:42:59'),
(251, 198, 55, 1, 'consultation', NULL, 'Consultation: visit_mpya', NULL, NULL, 1, 100000.00, 100000.00, 0.00, 0.00, 0.00, NULL, NULL, 'pending', '2026-08-26 22:37:54', '2026-08-26 22:37:54'),
(252, 199, 60, 1, 'consultation', NULL, 'Consultation: New Patient', NULL, NULL, 1, 10000.00, 10000.00, 0.00, 0.00, 0.00, NULL, NULL, 'pending', '2026-08-26 22:43:03', '2026-08-26 22:43:03'),
(253, 199, 60, 1, 'lab_test', 64, 'COVID-19 Rapid Antigen Test', NULL, NULL, 1, 15000.00, 15000.00, 0.00, 0.00, 0.00, 64, '', 'pending', '2026-08-27 20:34:21', '2026-08-27 20:34:21'),
(254, 199, 60, 1, 'medication', NULL, 'ALBENDAZOLE (Batch: BATCH-20260825-AEB716)', NULL, NULL, 1, 2000.00, 2000.00, 0.00, 0.00, 0.00, 50, 'prescription', 'pending', '2026-08-27 21:09:21', '2026-08-27 21:09:21'),
(255, 199, 60, 1, 'procedure', 18, 'Cryotherapy', NULL, NULL, 1, 20000.00, 20000.00, 0.00, 0.00, 0.00, 54, 'procedure', 'pending', '2026-08-27 21:09:33', '2026-08-27 21:09:33'),
(256, 199, 60, 1, 'procedure', 20, 'Free - Nutrition Counseling (FREE)', NULL, 'FREE - No charge', 1, 0.00, 0.00, 0.00, 0.00, 0.00, 55, 'procedure', 'pending', '2026-08-27 21:09:33', '2026-08-27 21:09:33'),
(257, 199, 60, 1, 'equipment', 15, 'Gauze Swabs (Sterile) (FREE)', NULL, 'FREE - No charge', 1, 0.00, 0.00, 0.00, 0.00, 0.00, 15, 'equipment', 'pending', '2026-08-27 21:09:46', '2026-08-27 21:09:46'),
(258, 199, 60, 1, 'equipment', 27, 'Surgical Blades (Scalpel) (Batch: BATCH-BLADE-001)', NULL, NULL, 1, 15000.00, 15000.00, 0.00, 0.00, 0.00, 27, 'equipment', 'pending', '2026-08-27 21:09:46', '2026-08-27 21:09:46'),
(259, 199, 60, 1, 'equipment', 28, 'Needle Holder (Surgical) (Batch: BATCH-NEEDLE-001)', NULL, NULL, 1, 30000.00, 30000.00, 0.00, 0.00, 0.00, 28, 'equipment', 'pending', '2026-08-27 21:09:46', '2026-08-27 21:09:46');

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
(1, 'DM-001', 'Diabetes Mellitus Type 2', 'E11.9', 'Endocrine', 'Chronic condition affecting blood sugar regulation', 'Metformin, Lifestyle modification, Diet control', 1, NULL, NULL, '2026-08-24 09:08:38', '2026-08-24 09:08:38'),
(2, 'HTN-001', 'Hypertension', 'I10', 'Cardiovascular', 'High blood pressure condition', 'Lifestyle changes, Antihypertensive medications', 1, NULL, NULL, '2026-08-24 09:08:38', '2026-08-24 09:08:38'),
(3, 'URTI-001', 'Upper Respiratory Tract Infection', 'J06.9', 'Respiratory', 'Infection of the upper respiratory tract', 'Rest, Fluids, Symptomatic treatment', 1, NULL, NULL, '2026-08-24 09:08:38', '2026-08-24 09:08:38'),
(4, 'PNEU-001', 'Pneumonia', 'J18.9', 'Respiratory', 'Inflammation of the lungs', 'Antibiotics, Supportive care', 1, NULL, NULL, '2026-08-24 09:08:38', '2026-08-24 09:08:38'),
(5, 'MA-001', 'Malaria', 'B54', 'Infectious', 'Parasitic infection transmitted by mosquitoes', 'Antimalarial drugs', 1, NULL, NULL, '2026-08-24 09:08:38', '2026-08-24 09:08:38'),
(6, 'TY-001', 'Typhoid Fever', 'A01.0', 'Infectious', 'Bacterial infection caused by Salmonella typhi', 'Antibiotics, Hydration', 1, NULL, NULL, '2026-08-24 09:08:38', '2026-08-24 09:08:38'),
(7, 'DEN-001', 'Dengue Fever', 'A90', 'Infectious', 'Mosquito-borne viral infection', 'Supportive care, Hydration', 1, NULL, NULL, '2026-08-24 09:08:38', '2026-08-24 09:08:38'),
(8, 'TUB-001', 'Tuberculosis', 'A16.9', 'Infectious', 'Bacterial infection affecting lungs', 'Anti-TB medications', 1, NULL, NULL, '2026-08-24 09:08:38', '2026-08-24 09:08:38'),
(9, 'CHF-001', 'Congestive Heart Failure', 'I50.9', 'Cardiovascular', 'Heart unable to pump blood effectively', 'Diuretics, ACE inhibitors, Beta-blockers', 1, NULL, NULL, '2026-08-24 09:08:38', '2026-08-24 09:08:38'),
(10, 'ANEM-001', 'Anemia', 'D64.9', 'Hematology', 'Low red blood cell count', 'Iron supplements, Dietary changes', 1, NULL, NULL, '2026-08-24 09:08:38', '2026-08-24 09:08:38'),
(11, '', 'MALARIA', NULL, NULL, NULL, NULL, 1, NULL, 1, '2026-08-24 10:13:03', '2026-08-24 10:13:03');

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
(6, 'Obstetric Ultrasound (Twin - 2/3 Trimester)', 'Obstetric Ultrasound - Twin', 'ultrasound', '<div class=\"ultrasound-report\">\r\n    <div class=\"report-header\">\r\n        <div style=\"display:flex;align-items:center;justify-content:center;gap:15px;margin-bottom:10px;\">\r\n            <img src=\"/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png\" alt=\"Braick Dispensary\" style=\"height:60px;width:auto;max-height:60px;\" onerror=\"this.style.display=none\">\r\n            <div>\r\n                <h2 style=\"color:#0B5ED7;font-size:22px;margin:0;\">BRAICK DISPENSARY</h2>\r\n                <p style=\"font-size:12px;color:#666;margin:0;\">Quality Healthcare Services</p>\r\n            </div>\r\n        </div>\r\n        <h3 style=\"font-size:16px;color:#333;margin:0;\">ULTRASOUND REPORT – ABDOMEN AND PELVIS</h3>\r\n        <p style=\"font-size:11px;color:#888;margin:2px 0 0 0;\">Twin Pregnancy – 2nd/3rd Trimester</p>\r\n    </div>\r\n    \r\n    <div class=\"patient-info\">\r\n        <p><strong>Patient Name:</strong> {patient_name}</p>\r\n        <p><strong>Age/Sex:</strong> {age} yrs / {gender}</p>\r\n        <p><strong>Date of Exam:</strong> {exam_date}</p>\r\n        <p><strong>Patient ID:</strong> {patient_id}</p>\r\n        <p><strong>Report Date:</strong> {report_date}</p>\r\n    </div>\r\n    \r\n    <div class=\"findings\">\r\n        <h4>FINDINGS</h4>\r\n        <p><strong>Liver:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"liver\" placeholder=\"e.g. Appeared normal in size, shape, homogeneous echo pattern\"></p>\r\n        <p><strong>Gallbladder:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"gallbladder\" placeholder=\"e.g. Appears normal, well distended, no stones\"></p>\r\n        <p><strong>Pancreas:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"pancreas\" placeholder=\"e.g. Appeared normal in size and shape\"></p>\r\n        <p><strong>Spleen:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"spleen\" placeholder=\"e.g. Appeared normal in size, shape and echotexture\"></p>\r\n        <p><strong>Peritoneum:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"peritoneum\" placeholder=\"e.g. No free fluid noted\"></p>\r\n        <p><strong>Kidneys:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"kidneys\" placeholder=\"e.g. Both kidneys normal\"></p>\r\n        <p><strong>Urinary Bladder:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"bladder\" placeholder=\"e.g. Appears normal, well-distended\"></p>\r\n        <p><strong>Uterus:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"uterus\" placeholder=\"e.g. Appears normal in size and homogeneous echo pattern\"></p>\r\n        <p><strong>Right Ovary:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"right_ovary\" placeholder=\"e.g. Appears normal in size and appearance\"></p>\r\n        <p><strong>Left Ovary:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"left_ovary\" placeholder=\"e.g. Appears normal in size and appearance\"></p>\r\n        <p><strong>Pouch of Douglas:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"pouch_douglas\" placeholder=\"e.g. Free fluid seen\"></p>\r\n    </div>\r\n    \r\n    <div class=\"conclusion\">\r\n        <h4>IMPRESSION</h4>\r\n        <textarea class=\"form-control conclusion-field\" rows=\"2\" placeholder=\"Enter impression...\"></textarea>\r\n    </div>\r\n    \r\n    <div class=\"report-footer\">\r\n        <div style=\"display:flex;justify-content:space-between;align-items:center;width:100%;flex-wrap:wrap;\">\r\n            <div>\r\n                <span>Technician: <input type=\"text\" class=\"form-control\" style=\"display:inline-block;width:auto;border:none;border-bottom:1px solid #ddd;padding:0 8px;\" placeholder=\"Technician Name\"></span>\r\n                <span style=\"margin-left:20px;\">Date: {report_date}</span>\r\n            </div>\r\n            <div style=\"text-align:right;padding:8px 16px;border:2px solid #0B5ED7;border-radius:8px;background:#f0f7ff;min-width:150px;\">\r\n                <div style=\"font-size:10px;color:#666;text-transform:uppercase;letter-spacing:1px;font-weight:bold;\">Official Stamp</div>\r\n                <div style=\"font-size:14px;font-weight:bold;color:#0B5ED7;margin-top:4px;\">BRAICK DISPENSARY</div>\r\n                <div style=\"font-size:10px;color:#888;border-top:1px dashed #ccc;padding-top:4px;margin-top:4px;\">\r\n                    <span>Approved By: _________________</span>\r\n                </div>\r\n                <div style=\"font-size:9px;color:#999;margin-top:2px;\">Date: {report_date}</div>\r\n            </div>\r\n        </div>\r\n    </div>\r\n</div>', 1, '2026-08-23 15:54:44', '2026-08-23 15:54:44'),
(7, 'Obstetric Ultrasound (Single - 2/3 Trimester)', 'Obstetric Ultrasound - Single', 'ultrasound', '<div class=\"ultrasound-report\">\r\n    <div class=\"report-header\">\r\n        <div style=\"display:flex;align-items:center;justify-content:center;gap:15px;margin-bottom:10px;\">\r\n            <img src=\"/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png\" alt=\"Braick Dispensary\" style=\"height:60px;width:auto;max-height:60px;\" onerror=\"this.style.display=none\">\r\n            <div>\r\n                <h2 style=\"color:#0B5ED7;font-size:22px;margin:0;\">BRAICK DISPENSARY</h2>\r\n                <p style=\"font-size:12px;color:#666;margin:0;\">Quality Healthcare Services</p>\r\n            </div>\r\n        </div>\r\n        <h3 style=\"font-size:16px;color:#333;margin:0;\">OBSTETRIC ULTRASOUND REPORT</h3>\r\n        <p style=\"font-size:11px;color:#888;margin:2px 0 0 0;\">Single Pregnancy – 2nd/3rd Trimester</p>\r\n    </div>\r\n    \r\n    <div class=\"patient-info\">\r\n        <p><strong>Patient Name:</strong> {patient_name}</p>\r\n        <p><strong>Age/Sex:</strong> {age} yrs / {gender}</p>\r\n        <p><strong>Date of Exam:</strong> {exam_date}</p>\r\n        <p><strong>Patient ID:</strong> {patient_id}</p>\r\n        <p><strong>Report Date:</strong> {report_date}</p>\r\n    </div>\r\n    \r\n    <div class=\"findings\">\r\n        <h4>FINDINGS</h4>\r\n        <p><strong>Presentation and Lie:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"presentation\" placeholder=\"e.g. single viable intrauterine fetus, in cephalic presentation\"></p>\r\n        <p><strong>Placenta:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"placenta\" placeholder=\"e.g. placenta is posterior, placenta calcification\"></p>\r\n        <p><strong>Fetal Activity:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"fetal_activity\" placeholder=\"e.g. seen\"></p>\r\n        <p><strong>Amniotic Fluid:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"amniotic_fluid\" placeholder=\"e.g. adequate\"></p>\r\n        <p><strong>Anatomical Structures:</strong> <textarea class=\"form-control placeholder-field\" data-placeholder=\"anatomical_structures\" rows=\"2\" placeholder=\"Describe anatomical structures...\"></textarea></p>\r\n        <p><strong>Maternal Kidney:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"maternal_kidney\" placeholder=\"e.g. appeared normal\"></p>\r\n    </div>\r\n    \r\n    <div class=\"biometry\">\r\n        <h4>BIOMETRY</h4>\r\n        <div style=\"overflow-x:auto;\">\r\n            <table>\r\n                <thead>\r\n                    <tr>\r\n                        <th>Parameter</th>\r\n                        <th>Measurement</th>\r\n                    </tr>\r\n                </thead>\r\n                <tbody>\r\n                    <tr><td><strong>BPD</strong></td><td><input type=\"text\" class=\"form-control table-field\" placeholder=\"mm\" style=\"width:150px;\"></td></tr>\r\n                    <tr><td><strong>HC</strong></td><td><input type=\"text\" class=\"form-control table-field\" placeholder=\"mm\" style=\"width:150px;\"></td></tr>\r\n                    <tr><td><strong>AC</strong></td><td><input type=\"text\" class=\"form-control table-field\" placeholder=\"mm\" style=\"width:150px;\"></td></tr>\r\n                    <tr><td><strong>FL</strong></td><td><input type=\"text\" class=\"form-control table-field\" placeholder=\"mm\" style=\"width:150px;\"></td></tr>\r\n                    <tr><td><strong>GA</strong></td><td><input type=\"text\" class=\"form-control table-field\" placeholder=\"e.g. 39W+3D\" style=\"width:150px;\"></td></tr>\r\n                    <tr><td><strong>EDD</strong></td><td><input type=\"text\" class=\"form-control table-field\" placeholder=\"DD/MM/YYYY\" style=\"width:150px;\"></td></tr>\r\n                </tbody>\r\n            </table>\r\n        </div>\r\n    </div>\r\n    \r\n    <div class=\"conclusion\">\r\n        <h4>CONCLUSION</h4>\r\n        <textarea class=\"form-control conclusion-field\" rows=\"2\" placeholder=\"Enter conclusion...\"></textarea>\r\n    </div>\r\n    \r\n    <div class=\"report-footer\">\r\n        <div style=\"display:flex;justify-content:space-between;align-items:center;width:100%;flex-wrap:wrap;\">\r\n            <div>\r\n                <span>Technician: <input type=\"text\" class=\"form-control\" style=\"display:inline-block;width:auto;border:none;border-bottom:1px solid #ddd;padding:0 8px;\" placeholder=\"Technician Name\"></span>\r\n                <span style=\"margin-left:20px;\">Date: {report_date}</span>\r\n            </div>\r\n            <div style=\"text-align:right;padding:8px 16px;border:2px solid #0B5ED7;border-radius:8px;background:#f0f7ff;min-width:150px;\">\r\n                <div style=\"font-size:10px;color:#666;text-transform:uppercase;letter-spacing:1px;font-weight:bold;\">Official Stamp</div>\r\n                <div style=\"font-size:14px;font-weight:bold;color:#0B5ED7;margin-top:4px;\">BRAICK DISPENSARY</div>\r\n                <div style=\"font-size:10px;color:#888;border-top:1px dashed #ccc;padding-top:4px;margin-top:4px;\">\r\n                    <span>Approved By: _________________</span>\r\n                </div>\r\n                <div style=\"font-size:9px;color:#999;margin-top:2px;\">Date: {report_date}</div>\r\n            </div>\r\n        </div>\r\n    </div>\r\n</div>', 1, '2026-08-23 15:54:44', '2026-08-23 15:54:44'),
(8, 'Obstetric Ultrasound (Early Pregnancy)', 'Obstetric Ultrasound - Early', 'ultrasound', '<div class=\"ultrasound-report\">\r\n    <div class=\"report-header\">\r\n        <div style=\"display:flex;align-items:center;justify-content:center;gap:15px;margin-bottom:10px;\">\r\n            <img src=\"/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png\" alt=\"Braick Dispensary\" style=\"height:60px;width:auto;max-height:60px;\" onerror=\"this.style.display=none\">\r\n            <div>\r\n                <h2 style=\"color:#0B5ED7;font-size:22px;margin:0;\">BRAICK DISPENSARY</h2>\r\n                <p style=\"font-size:12px;color:#666;margin:0;\">Quality Healthcare Services</p>\r\n            </div>\r\n        </div>\r\n        <h3 style=\"font-size:16px;color:#333;margin:0;\">OBSTETRIC ULTRASOUND REPORT</h3>\r\n        <p style=\"font-size:11px;color:#888;margin:2px 0 0 0;\">Early Pregnancy Scan</p>\r\n    </div>\r\n    \r\n    <div class=\"patient-info\">\r\n        <p><strong>Patient Name:</strong> {patient_name}</p>\r\n        <p><strong>Age/Sex:</strong> {age} yrs / {gender}</p>\r\n        <p><strong>Date of Exam:</strong> {exam_date}</p>\r\n        <p><strong>Patient ID:</strong> {patient_id}</p>\r\n        <p><strong>Report Date:</strong> {report_date}</p>\r\n    </div>\r\n    \r\n    <div class=\"findings\">\r\n        <h4>FINDINGS</h4>\r\n        <p><strong>Embryo:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"embryo\" placeholder=\"e.g. single viable intrauterine embryo\"></p>\r\n        <p><strong>CRL (Crown Rump Length):</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"crl\" placeholder=\"e.g. 31.57mm\"></p>\r\n        <p><strong>Gestational Age (GA):</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"ga\" placeholder=\"e.g. 10W+2D\"></p>\r\n        <p><strong>Fetal Pole:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"fetal_pole\" placeholder=\"e.g. seen\"></p>\r\n        <p><strong>Yolk Sac:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"yolk_sac\" placeholder=\"e.g. seen\"></p>\r\n        <p><strong>Myometrium:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"myometrium\" placeholder=\"e.g. no myometrial masses seen\"></p>\r\n        <p><strong>Cervix:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"cervix\" placeholder=\"e.g. normal and closed\"></p>\r\n        <p><strong>Adnexal Areas:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"adnexa\" placeholder=\"e.g. looked normal\"></p>\r\n        <p><strong>Pouch of Douglas:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"pouch_douglas\" placeholder=\"e.g. no fluid seen\"></p>\r\n        <p><strong>Maternal Organs:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"maternal_organs\" placeholder=\"e.g. normal\"></p>\r\n    </div>\r\n    \r\n    <div class=\"conclusion\">\r\n        <h4>CONCLUSION</h4>\r\n        <textarea class=\"form-control conclusion-field\" rows=\"2\" placeholder=\"Enter conclusion...\"></textarea>\r\n    </div>\r\n    \r\n    <div class=\"report-footer\">\r\n        <div style=\"display:flex;justify-content:space-between;align-items:center;width:100%;flex-wrap:wrap;\">\r\n            <div>\r\n                <span>Technician: <input type=\"text\" class=\"form-control\" style=\"display:inline-block;width:auto;border:none;border-bottom:1px solid #ddd;padding:0 8px;\" placeholder=\"Technician Name\"></span>\r\n                <span style=\"margin-left:20px;\">Date: {report_date}</span>\r\n            </div>\r\n            <div style=\"text-align:right;padding:8px 16px;border:2px solid #0B5ED7;border-radius:8px;background:#f0f7ff;min-width:150px;\">\r\n                <div style=\"font-size:10px;color:#666;text-transform:uppercase;letter-spacing:1px;font-weight:bold;\">Official Stamp</div>\r\n                <div style=\"font-size:14px;font-weight:bold;color:#0B5ED7;margin-top:4px;\">BRAICK DISPENSARY</div>\r\n                <div style=\"font-size:10px;color:#888;border-top:1px dashed #ccc;padding-top:4px;margin-top:4px;\">\r\n                    <span>Approved By: _________________</span>\r\n                </div>\r\n                <div style=\"font-size:9px;color:#999;margin-top:2px;\">Date: {report_date}</div>\r\n            </div>\r\n        </div>\r\n    </div>\r\n</div>', 1, '2026-08-23 15:54:44', '2026-08-23 15:54:44'),
(9, 'Abdominal Ultrasound (Male)', 'Abdominal Ultrasound - Male', 'ultrasound', '<div class=\"ultrasound-report\">\r\n    <div class=\"report-header\">\r\n        <div style=\"display:flex;align-items:center;justify-content:center;gap:15px;margin-bottom:10px;\">\r\n            <img src=\"/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png\" alt=\"Braick Dispensary\" style=\"height:60px;width:auto;max-height:60px;\" onerror=\"this.style.display=none\">\r\n            <div>\r\n                <h2 style=\"color:#0B5ED7;font-size:22px;margin:0;\">BRAICK DISPENSARY</h2>\r\n                <p style=\"font-size:12px;color:#666;margin:0;\">Quality Healthcare Services</p>\r\n            </div>\r\n        </div>\r\n        <h3 style=\"font-size:16px;color:#333;margin:0;\">ULTRASOUND REPORT – ABDOMEN AND PELVIS</h3>\r\n        <p style=\"font-size:11px;color:#888;margin:2px 0 0 0;\">Male Abdomen</p>\r\n    </div>\r\n    \r\n    <div class=\"patient-info\">\r\n        <p><strong>Patient Name:</strong> {patient_name}</p>\r\n        <p><strong>Age/Sex:</strong> {age} yrs / {gender}</p>\r\n        <p><strong>Date of Exam:</strong> {exam_date}</p>\r\n        <p><strong>Patient ID:</strong> {patient_id}</p>\r\n        <p><strong>Report Date:</strong> {report_date}</p>\r\n    </div>\r\n    \r\n    <div class=\"findings\">\r\n        <h4>FINDINGS</h4>\r\n        <p><strong>Liver:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"liver\" placeholder=\"e.g. Appears normal in size, shape, homogeneous echo pattern\"></p>\r\n        <p><strong>Gallbladder:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"gallbladder\" placeholder=\"e.g. Appears normal, well distended, no stones\"></p>\r\n        <p><strong>Pancreas:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"pancreas\" placeholder=\"e.g. Appears normal in size and shape\"></p>\r\n        <p><strong>Spleen:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"spleen\" placeholder=\"e.g. Appears normal in size, shape and echotexture\"></p>\r\n        <p><strong>Peritoneum:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"peritoneum\" placeholder=\"e.g. No free fluid noted\"></p>\r\n        <p><strong>Kidneys:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"kidneys\" placeholder=\"e.g. Both kidneys normal\"></p>\r\n        <p><strong>Urinary Bladder:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"bladder\" placeholder=\"e.g. Appears normal, well-distended\"></p>\r\n        <p><strong>Prostate:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"prostate\" placeholder=\"e.g. Appears normal in size, shape and echotexture\"></p>\r\n    </div>\r\n    \r\n    <div class=\"conclusion\">\r\n        <h4>IMPRESSION/CONCLUSION</h4>\r\n        <textarea class=\"form-control conclusion-field\" rows=\"2\" placeholder=\"Enter impression...\"></textarea>\r\n    </div>\r\n    \r\n    <div class=\"report-footer\">\r\n        <div style=\"display:flex;justify-content:space-between;align-items:center;width:100%;flex-wrap:wrap;\">\r\n            <div>\r\n                <span>Technician: <input type=\"text\" class=\"form-control\" style=\"display:inline-block;width:auto;border:none;border-bottom:1px solid #ddd;padding:0 8px;\" placeholder=\"Technician Name\"></span>\r\n                <span style=\"margin-left:20px;\">Date: {report_date}</span>\r\n            </div>\r\n            <div style=\"text-align:right;padding:8px 16px;border:2px solid #0B5ED7;border-radius:8px;background:#f0f7ff;min-width:150px;\">\r\n                <div style=\"font-size:10px;color:#666;text-transform:uppercase;letter-spacing:1px;font-weight:bold;\">Official Stamp</div>\r\n                <div style=\"font-size:14px;font-weight:bold;color:#0B5ED7;margin-top:4px;\">BRAICK DISPENSARY</div>\r\n                <div style=\"font-size:10px;color:#888;border-top:1px dashed #ccc;padding-top:4px;margin-top:4px;\">\r\n                    <span>Approved By: _________________</span>\r\n                </div>\r\n                <div style=\"font-size:9px;color:#999;margin-top:2px;\">Date: {report_date}</div>\r\n            </div>\r\n        </div>\r\n    </div>\r\n</div>', 1, '2026-08-23 15:54:44', '2026-08-23 15:54:44'),
(10, 'Abdominal Ultrasound (Female)', 'Abdominal Ultrasound - Female', 'ultrasound', '<div class=\"ultrasound-report\">\r\n    <div class=\"report-header\">\r\n        <div style=\"display:flex;align-items:center;justify-content:center;gap:15px;margin-bottom:10px;\">\r\n            <img src=\"/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png\" alt=\"Braick Dispensary\" style=\"height:60px;width:auto;max-height:60px;\" onerror=\"this.style.display=none\">\r\n            <div>\r\n                <h2 style=\"color:#0B5ED7;font-size:22px;margin:0;\">BRAICK DISPENSARY</h2>\r\n                <p style=\"font-size:12px;color:#666;margin:0;\">Quality Healthcare Services</p>\r\n            </div>\r\n        </div>\r\n        <h3 style=\"font-size:16px;color:#333;margin:0;\">ULTRASOUND REPORT – ABDOMEN AND PELVIS</h3>\r\n        <p style=\"font-size:11px;color:#888;margin:2px 0 0 0;\">Female Abdomen</p>\r\n    </div>\r\n    \r\n    <div class=\"patient-info\">\r\n        <p><strong>Patient Name:</strong> {patient_name}</p>\r\n        <p><strong>Age/Sex:</strong> {age} yrs / {gender}</p>\r\n        <p><strong>Date of Exam:</strong> {exam_date}</p>\r\n        <p><strong>Patient ID:</strong> {patient_id}</p>\r\n        <p><strong>Report Date:</strong> {report_date}</p>\r\n    </div>\r\n    \r\n    <div class=\"findings\">\r\n        <h4>FINDINGS</h4>\r\n        <p><strong>Liver:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"liver\" placeholder=\"e.g. Appeared normal in size, shape, homogeneous echo pattern\"></p>\r\n        <p><strong>Gallbladder:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"gallbladder\" placeholder=\"e.g. Appears normal, well distended, no stones\"></p>\r\n        <p><strong>Pancreas:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"pancreas\" placeholder=\"e.g. Appeared normal in size and shape\"></p>\r\n        <p><strong>Spleen:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"spleen\" placeholder=\"e.g. Appeared normal in size, shape and echotexture\"></p>\r\n        <p><strong>Peritoneum:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"peritoneum\" placeholder=\"e.g. No free fluid noted\"></p>\r\n        <p><strong>Kidneys:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"kidneys\" placeholder=\"e.g. Both kidneys normal\"></p>\r\n        <p><strong>Urinary Bladder:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"bladder\" placeholder=\"e.g. Appears normal, well-distended\"></p>\r\n        <p><strong>Uterus:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"uterus\" placeholder=\"e.g. Appears normal in size and homogeneous echo pattern\"></p>\r\n        <p><strong>Right Ovary:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"right_ovary\" placeholder=\"e.g. Appears normal in size and appearance\"></p>\r\n        <p><strong>Left Ovary:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"left_ovary\" placeholder=\"e.g. Appears normal in size and appearance\"></p>\r\n        <p><strong>Pouch of Douglas:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"pouch_douglas\" placeholder=\"e.g. Free fluid seen\"></p>\r\n    </div>\r\n    \r\n    <div class=\"conclusion\">\r\n        <h4>IMPRESSION</h4>\r\n        <textarea class=\"form-control conclusion-field\" rows=\"2\" placeholder=\"Enter impression...\"></textarea>\r\n    </div>\r\n    \r\n    <div class=\"report-footer\">\r\n        <div style=\"display:flex;justify-content:space-between;align-items:center;width:100%;flex-wrap:wrap;\">\r\n            <div>\r\n                <span>Technician: <input type=\"text\" class=\"form-control\" style=\"display:inline-block;width:auto;border:none;border-bottom:1px solid #ddd;padding:0 8px;\" placeholder=\"Technician Name\"></span>\r\n                <span style=\"margin-left:20px;\">Date: {report_date}</span>\r\n            </div>\r\n            <div style=\"text-align:right;padding:8px 16px;border:2px solid #0B5ED7;border-radius:8px;background:#f0f7ff;min-width:150px;\">\r\n                <div style=\"font-size:10px;color:#666;text-transform:uppercase;letter-spacing:1px;font-weight:bold;\">Official Stamp</div>\r\n                <div style=\"font-size:14px;font-weight:bold;color:#0B5ED7;margin-top:4px;\">BRAICK DISPENSARY</div>\r\n                <div style=\"font-size:10px;color:#888;border-top:1px dashed #ccc;padding-top:4px;margin-top:4px;\">\r\n                    <span>Approved By: _________________</span>\r\n                </div>\r\n                <div style=\"font-size:9px;color:#999;margin-top:2px;\">Date: {report_date}</div>\r\n            </div>\r\n        </div>\r\n    </div>\r\n</div>', 1, '2026-08-23 15:54:44', '2026-08-23 15:54:44');

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

--
-- Dumping data for table `lab_tests`
--

INSERT INTO `lab_tests` (`id`, `visit_id`, `patient_id`, `doctor_id`, `lab_technician_id`, `technician_id`, `test_id`, `test_name`, `test_price`, `equipment_used`, `batch_number`, `test_type`, `sample_type`, `test_date`, `results`, `formatted_result`, `reference_range`, `interpretation`, `performed_by`, `status`, `started_at`, `bill_created`, `branch_id`, `notes`, `created_at`, `completed_at`, `printed_at`, `printed_by`, `updated_at`) VALUES
(62, 59, 61, 4, 13, NULL, 24, 'Helicobacter Pylori Test', 15000.00, NULL, NULL, NULL, NULL, NULL, 'POSITIVE', NULL, '1.2', 'Well Being', 13, 'completed', '2026-08-26 21:40:49', 0, 1, 'hana haja ya sindano', '2026-08-26 21:40:35', '2026-08-26 21:41:31', NULL, NULL, '2026-08-26 21:41:31'),
(63, 59, 61, 4, 13, NULL, 17, 'Pregnancy Test (Blood - Beta HCG)', 20000.00, NULL, NULL, NULL, NULL, NULL, 'Positive - HCG detected', NULL, '0.5', 'HII NI MBAYA SANA', 13, 'completed', '2026-08-26 21:40:52', 0, 1, 'ANAHITAJI SINDANO ZA MASAA 6', '2026-08-26 21:40:35', '2026-08-26 21:42:02', NULL, NULL, '2026-08-26 21:42:02'),
(64, 61, 60, 4, 13, NULL, 10, 'COVID-19 Rapid Antigen Test', 15000.00, NULL, NULL, NULL, NULL, NULL, 'Positive - SARS-CoV-2 antigen detected', NULL, '', '', 13, 'completed', '2026-08-27 21:08:21', 0, 1, '', '2026-08-27 20:34:21', '2026-08-27 21:08:30', NULL, NULL, '2026-08-27 21:08:30');

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
(54, 'Abdominal Ultrasound', NULL, 'Lab Tests', 45000.00, '', NULL, NULL, 1, 1, 1, 4, '2026-08-24 13:07:40', '2026-08-24 13:07:40'),
(55, 'Chest X-Ray', NULL, 'Radiology', 65000.00, '', NULL, 35, 2, 1, 1, 4, '2026-08-25 08:33:29', '2026-08-25 08:45:38'),
(56, 'Chest X-Ray', NULL, 'Radiology', 65000.00, '', NULL, NULL, 1, 1, 1, 4, '2026-08-25 08:42:35', '2026-08-25 08:42:35');

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
(3, 55, 35, 1, '2026-08-25 08:33:29'),
(4, 56, 35, 1, '2026-08-25 08:42:35');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `medical_equipment`
--

INSERT INTO `medical_equipment` (`id`, `equipment_name`, `category`, `unit`, `quantity`, `reorder_level`, `unit_cost`, `selling_price`, `supplier`, `expiry_date`, `batch_number`, `branch_id`, `created_by`, `status`, `created_at`, `updated_at`) VALUES
(15, 'Gauze Swabs (Sterile)', 'Consumables', 'pack', 488, 50, 0.00, 0.00, 'Medical Supplies Ltd', '2027-12-31', 'BATCH-GAUZE-001', 1, 1, 'active', '2026-08-24 14:42:45', '2026-08-27 21:09:46'),
(16, 'Cotton Wool (Roll)', 'Consumables', 'roll', 200, 20, 0.00, 0.00, 'Medical Supplies Ltd', '2027-12-31', 'BATCH-COTTON-001', 1, 1, 'active', '2026-08-24 14:42:45', '2026-08-24 14:42:45'),
(17, 'Adhesive Tape (Roll)', 'Consumables', 'roll', 94, 15, 0.00, 0.00, 'Healthcare Distributors', '2027-11-30', 'BATCH-TAPE-001', 1, 1, 'active', '2026-08-24 14:42:45', '2026-08-26 21:42:59'),
(18, 'Bandage (Elastic)', 'Consumables', 'piece', 1, 20, 0.00, 0.00, 'Healthcare Distributors', '2027-10-31', 'BATCH-BANDAGE-001', 1, 1, 'active', '2026-08-24 14:42:45', '2026-08-26 12:43:27'),
(19, 'Gloves (Surgical - Sterile)', 'Consumables', 'pair', 979, 100, 0.00, 0.00, 'Medical Supplies Ltd', '2028-06-30', 'BATCH-GLOVES-001', 1, 1, 'active', '2026-08-24 14:42:45', '2026-08-26 14:36:57'),
(20, 'Stethoscope (Basic)', 'Diagnostic', 'piece', 10, 2, 0.00, 0.00, 'Medical Equipment Co', NULL, 'BATCH-STETH-001', 1, 1, 'active', '2026-08-24 14:42:45', '2026-08-24 14:42:45'),
(21, 'Thermometer (Digital)', 'Diagnostic', 'piece', 25, 5, 0.00, 0.00, 'Medical Equipment Co', NULL, 'BATCH-THERM-001', 1, 1, 'active', '2026-08-24 14:42:45', '2026-08-24 14:42:45'),
(22, 'Blood Pressure Cuff (Manual)', 'Diagnostic', 'piece', 10, 3, 0.00, 0.00, 'Medical Equipment Co', NULL, 'BATCH-BP-001', 1, 1, 'active', '2026-08-24 14:42:45', '2026-08-26 14:16:48'),
(23, 'Pulse Oximeter', 'Diagnostic', 'piece', 19, 5, 0.00, 0.00, 'Medical Equipment Co', NULL, 'BATCH-OXI-001', 1, 1, 'active', '2026-08-24 14:42:45', '2026-08-26 12:50:15'),
(24, 'Weighing Scale (Medical)', 'Diagnostic', 'piece', 4, 1, 0.00, 0.00, 'Medical Equipment Co', NULL, 'BATCH-SCALE-001', 1, 1, 'active', '2026-08-24 14:42:45', '2026-08-26 15:44:39'),
(25, 'Surgical Scissors (Mayo)', 'Surgical Instruments', 'piece', 24, 5, 15000.00, 25000.00, 'Surgical Supplies Ltd', NULL, 'BATCH-SCISSOR-001', 1, 1, 'active', '2026-08-24 14:42:45', '2026-08-26 15:44:39'),
(26, 'Forceps (Tissue)', 'Surgical Instruments', 'piece', 10, 5, 12000.00, 20000.00, 'Surgical Supplies Ltd', NULL, 'BATCH-FORCEP-001', 1, 1, 'active', '2026-08-24 14:42:45', '2026-08-26 15:25:35'),
(27, 'Surgical Blades (Scalpel)', 'Surgical Instruments', 'pack', 4, 10, 8000.00, 15000.00, 'Surgical Supplies Ltd', '2027-12-31', 'BATCH-BLADE-001', 1, 1, 'active', '2026-08-24 14:42:45', '2026-08-27 21:09:46'),
(28, 'Needle Holder (Surgical)', 'Surgical Instruments', 'piece', 7, 3, 18000.00, 30000.00, 'Surgical Supplies Ltd', NULL, 'BATCH-NEEDLE-001', 1, 1, 'active', '2026-08-24 14:42:45', '2026-08-27 21:09:46'),
(29, 'Retractor (Surgical)', 'Surgical Instruments', 'piece', 9, 2, 25000.00, 40000.00, 'Surgical Supplies Ltd', NULL, 'BATCH-RETRACT-001', 1, 1, 'active', '2026-08-24 14:42:45', '2026-08-26 12:50:15'),
(30, 'ECG Machine (12-Lead)', 'Diagnostic Equipment', 'piece', 1, 1, 350000.00, 500000.00, 'Diagnostic Systems Ltd', NULL, 'BATCH-ECG-001', 1, 1, 'active', '2026-08-24 14:42:45', '2026-08-25 15:03:35'),
(31, 'Ultrasound Probe (General)', 'Diagnostic Equipment', 'piece', 5, 1, 250000.00, 350000.00, 'Ultrasound Technologies', NULL, 'BATCH-US-001', 1, 1, 'active', '2026-08-24 14:42:45', '2026-08-24 14:42:45'),
(32, 'Spirometer (Digital)', 'Diagnostic Equipment', 'piece', 2, 1, 180000.00, 250000.00, 'Pulmonary Systems Ltd', NULL, 'BATCH-SPIRO-001', 1, 1, 'active', '2026-08-24 14:42:45', '2026-08-26 15:44:39'),
(33, 'Infusion Pump', 'Treatment Equipment', 'piece', 6, 2, 120000.00, 180000.00, 'Medical Equipment Co', NULL, 'BATCH-INFUSE-001', 1, 1, 'active', '2026-08-24 14:42:45', '2026-08-26 15:19:54'),
(34, 'Suction Machine (Portable)', 'Treatment Equipment', 'piece', 5, 1, 150000.00, 220000.00, 'Medical Equipment Co', NULL, 'BATCH-SUCTION-001', 1, 1, 'active', '2026-08-24 14:42:45', '2026-08-24 14:42:45'),
(35, 'X-Ray Film Cassette', 'Radiology Equipment', 'piece', 12, 3, 45000.00, 70000.00, 'Radiology Systems Ltd', NULL, 'BATCH-XRAY-001', 1, 1, 'active', '2026-08-24 14:42:45', '2026-08-24 14:42:45');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `medications_inventory`
--

INSERT INTO `medications_inventory` (`id`, `medication_name`, `category`, `unit`, `quantity`, `reorder_level`, `unit_cost`, `selling_price`, `supplier`, `expiry_date`, `batch_number`, `branch_id`, `status`, `created_at`, `updated_at`) VALUES
(4, 'AMOXILINE', 'Antibiotics', 'bottle', 225, 50, 1000.00, 2000.00, '', '2027-01-24', 'BATCH-20260824-914E09', 1, 'active', '2026-08-24 13:57:47', '2026-08-26 15:25:18'),
(5, 'AMOXILINE', 'Antibiotics', 'bottle', 0, 50, 1200.00, 2400.00, '', '2026-12-24', 'BATCH-20260824-F8F23A', 1, 'active', '2026-08-24 13:58:28', '2026-08-25 21:52:29'),
(6, 'ALBENDAZOLE', 'Antibiotics', 'pcs', 159, 50, 1000.00, 2000.00, 'AVANA MEDICS', '2027-03-25', 'BATCH-20260825-AEB716', 1, 'active', '2026-08-25 08:31:29', '2026-08-27 21:09:21');

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
(27, 10, NULL, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. ERICK is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-08-25 20:28:33', '2026-08-25 20:28:33'),
(28, 11, NULL, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. ERICK is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-08-25 20:28:33', '2026-08-25 20:28:33'),
(29, 12, NULL, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. ERICK is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-08-25 20:28:33', '2026-08-25 20:28:33'),
(30, 1, NULL, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. ERICK is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-08-25 20:28:33', '2026-08-25 20:28:33'),
(31, 3, NULL, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. ERICK is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-08-25 20:28:33', '2026-08-25 20:28:33'),
(32, 10, NULL, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. ERICK is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-08-25 20:28:34', '2026-08-25 20:28:34'),
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
(95, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260827-0060-5575 (TSh 10,000) for patient ID #60 - New Patient', '', 'cashier_dashboard.php', 0, '2026-08-26 22:43:03', '2026-08-26 22:43:03');

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

INSERT INTO `otc_sales` (`id`, `sale_number`, `customer_name`, `customer_phone`, `patient_id`, `subtotal`, `discount_amount`, `total_amount`, `bill_id`, `payment_method`, `payment_status`, `sold_by`, `branch_id`, `notes`, `created_at`, `updated_at`) VALUES
(6, 'OTC-20260823-4779', 'KELVIN NJIRO', '0746526243', NULL, 20000.00, 500.00, 19500.00, NULL, 'cash', 'paid', 7, 1, 'Paid by Pharmacy (Self) - Customer: KELVIN NJIRO', '2026-08-23 16:40:33', '2026-08-23 16:40:33');

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
(5, 6, NULL, NULL, NULL, 'ALBENDERZOL', 10, 2000.00, 20000.00, 'After breakfast', 1, '2026-08-23 16:40:33');

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
(47, 'P-2026-01-0001', 'MARTHA KIMAMALA', '2003-09-23', 'Female', 'Single', '0616171819', 'marthakimamala@gmail.com', 'DODOMA - KISASA SHELI', '0623693303', 'AB+', 'Penicillin, Sulfa Drugs', 1, 10, 6, '2026-08-25 21:18:56', '2026-08-26 16:50:39'),
(48, 'P-2026-01-0002', 'IBRAHIM DOUMBIA', '2003-09-10', 'Male', 'Single', '0746512183', 'doumbia@gmail.com', 'KISASA SHELI', '0622682202', 'O+', 'Sulfa Drugs, Aspirin', 1, 10, 4, '2026-08-25 22:00:07', '2026-08-26 19:26:39'),
(49, 'P-2026-01-0003', 'AGUSTINO VALENTINE', '2003-02-15', 'Male', 'Single', '0678552288', 'augustino@gmail.com', 'kiasa', '0678723', 'AB-', 'Sulfa Drugs, Soy', 1, 10, 4, '2026-08-26 12:29:24', '2026-08-26 12:30:15'),
(50, 'P-2026-01-0004', 'KELVIN MSAFIRI', '2001-09-12', 'Male', '', '09876525', 'kelvin@gmail.com', 'kisasa', '0678723123', 'AB-', 'Penicillin, Sulfa Drugs', 1, 10, 5, '2026-08-26 13:03:37', '2026-08-26 16:49:37'),
(51, 'P-2026-01-0005', 'CLEOFAS WILLIUM', '2001-07-18', 'Male', 'Single', '0746526253', 'jacksonmyula3@gmail.com', 'mtakumbuka', '067872311', 'AB-', 'Penicillin, Milk', 1, 11, 4, '2026-08-26 18:36:46', '2026-08-26 18:36:46'),
(52, 'P-2026-01-0006', 'JUDITH SOLOMONI', '2002-04-09', 'Female', 'Single', '0678176542', 'judithsolomoni@gmail.com', '', '', 'O+', 'Penicillin, Milk', 1, 11, 5, '2026-08-26 19:23:28', '2026-08-26 19:49:56'),
(53, 'P-2026-01-0007', 'MAGRETH CHAKUPEWA', '2002-05-19', 'Female', 'Married', '0987536818', 'magreth@gmail.com', '', '', 'B-', 'Penicillin, Milk', 1, 11, 4, '2026-08-26 19:24:36', '2026-08-26 20:13:25'),
(54, 'P-2026-01-0008', 'CLEMENCY MTUKA', '2001-10-10', 'Male', 'Single', '0746526111', 'clemecy@gmail.com', 'mtakumbuka', '', 'B-', 'Ibuprofen', 1, 11, 4, '2026-08-26 19:55:59', '2026-08-26 20:08:34'),
(55, 'P-2026-01-0009', 'ALPHONSE MABULA', '1998-02-12', 'Male', '', '0787615242', 'alphonce@gmail.com', '', '0678723133', 'AB-', 'Sulfa Drugs', 1, 11, 4, '2026-08-26 19:59:21', '2026-08-26 19:59:21'),
(56, 'P-2026-01-0010', 'julieth kalinde', '2001-09-13', 'Male', '', '0789189123', 'juliath@gmail.com', '', '', 'AB+', 'Penicillin', 1, 11, 4, '2026-08-26 20:07:21', '2026-08-26 20:07:21'),
(57, 'P-2026-01-0011', 'VICTORIA SALINGO', '2008-03-12', 'Male', 'Single', '074671827361', 'victoria@gmail.com', '', '', '', '', 1, 11, 4, '2026-08-26 20:22:10', '2026-08-26 20:22:10'),
(58, 'P-2026-01-0012', 'AYUBU NZAL', '1992-08-12', 'Male', 'Married', '0765457899', 'ayubunzali@gmail.com', '', '', 'A+', '', 1, 11, 4, '2026-08-26 20:35:50', '2026-08-26 20:35:50'),
(59, 'P-2026-01-0013', 'AMOSI NGOMENI', '2000-12-12', 'Male', 'Single', '0756176210', 'amosi@gmail.com', '', '', 'A+', '', 1, 11, 4, '2026-08-26 20:52:13', '2026-08-26 20:52:13'),
(60, 'P-2026-01-0014', 'ANDREW VICENT CHIKUPE', '1993-07-10', 'Male', '', '0746826243', 'endrew@gmail.com', 'mtakumbuka', '0678723129', 'B-', 'Aspirin', 1, 11, 4, '2026-08-26 21:00:03', '2026-08-26 21:00:03'),
(61, 'P-2026-01-0015', 'MUSSA MONGI MASNGI', '2003-08-01', 'Male', 'Single', '0789878980', 'musa@gmail.com', '', '', '', 'Sulfa Drugs', 1, 11, 4, '2026-08-26 21:11:23', '2026-08-26 21:11:23');

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

--
-- Dumping data for table `patient_documents`
--

INSERT INTO `patient_documents` (`id`, `document_number`, `patient_id`, `visit_id`, `doctor_id`, `branch_id`, `uploaded_by`, `document_type`, `document_name`, `document_title`, `description`, `file_name`, `file_path`, `file_size`, `file_type`, `sick_sheet_days`, `sick_sheet_from_date`, `sick_sheet_to_date`, `sick_sheet_diagnosis`, `sick_sheet_recommendations`, `sick_sheet_restrictions`, `is_verified`, `verified_by`, `verified_date`, `status`, `upload_date`, `updated_at`) VALUES
(2, 'SS-20260824-8358', 36, NULL, 4, 1, 4, 'sick_sheet', 'Sick Sheet - JACKSON MYULA - 2026-08-24', 'Sick Sheet', 'Sick Sheet for JACKSON MYULA - 3 days', 'sick_sheet_SS-20260824-8358.html', '/dispensary_system/frontend/assets/uploads/sick_sheets/sick_sheet_SS-20260824-8358.html', 9311, 'text/html', 3, '2026-08-24', '2026-08-27', 'TYPHOD', '', 'No heavy lifting, complete rest', 1, NULL, NULL, 'active', '2026-08-24 11:39:07', '2026-08-24 11:39:07'),
(3, 'SS-20260824-0024', 37, NULL, 4, 1, 4, 'sick_sheet', 'Sick Sheet - KELVIN P. NASHON - 2026-08-24', 'Sick Sheet', 'Sick Sheet for KELVIN P. NASHON - 3 days', 'sick_sheet_SS-20260824-0024.html', '/dispensary_system/frontend/assets/uploads/sick_sheets/sick_sheet_SS-20260824-0024.html', 9439, 'text/html', 3, '2026-08-24', '2026-08-27', 'TYPHOID', '', 'No heavy lifting, complete rest', 1, NULL, NULL, 'active', '2026-08-24 12:07:43', '2026-08-24 12:07:43'),
(4, 'DOC-20260824-0001-419', 1, NULL, 4, 1, 4, 'referral_letter', 'xray', NULL, '', 'doc_1_1787576255_6a8c3fbfe5938.pdf', '/dispensary_system/frontend/assets/uploads/documents/doc_1_1787576255_6a8c3fbfe5938.pdf', 105820, 'application/pdf', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 'active', '2026-08-24 12:57:35', '2026-08-24 12:57:35');

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

--
-- Dumping data for table `prescriptions`
--

INSERT INTO `prescriptions` (`id`, `prescription_number`, `visit_id`, `patient_id`, `doctor_id`, `pharmacy_id`, `diagnosis`, `instructions`, `notes`, `status`, `branch_id`, `created_at`, `dispensed_at`, `updated_at`) VALUES
(49, 'PRES-20260826-0061-397', 59, 61, 4, NULL, NULL, NULL, NULL, 'pending', 1, '2026-08-26 21:42:31', NULL, '2026-08-26 21:42:31'),
(50, 'PRES-20260827-0060-664', 61, 60, 4, NULL, NULL, NULL, NULL, 'pending', 1, '2026-08-27 21:09:21', NULL, '2026-08-27 21:09:21');

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

--
-- Dumping data for table `prescription_items`
--

INSERT INTO `prescription_items` (`id`, `prescription_id`, `patient_id`, `inventory_id`, `medication_name`, `dosage`, `frequency`, `quantity`, `duration`, `route`, `instructions`, `pharmacy_instructions`, `pharmacy_instruction_mode`, `pharmacy_instruction_updated_at`, `pharmacy_instruction_updated_by`, `unit_price`, `total_price`, `branch_id`, `created_at`, `dispensed_at`, `dispensed_by`) VALUES
(49, 49, 61, 6, 'ALBENDAZOLE', '300', 'On Empty Stomach', 10, '7', 'IM', 'Take before meals', NULL, 'manual', NULL, NULL, 2000.00, 20000.00, 1, '2026-08-26 21:42:31', NULL, NULL),
(50, 50, 60, 6, 'ALBENDAZOLE', '300', 'Once Daily', 1, '7', 'Injection', 'Take before meals', NULL, 'manual', NULL, NULL, 2000.00, 2000.00, 1, '2026-08-27 21:09:21', NULL, NULL);

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

--
-- Dumping data for table `procedures`
--

INSERT INTO `procedures` (`id`, `visit_id`, `patient_id`, `doctor_id`, `procedure_id`, `procedure_name`, `procedure_category`, `category`, `procedure_code`, `procedure_price`, `status`, `branch_id`, `notes`, `performed_at`, `created_at`, `updated_at`) VALUES
(52, 59, 61, 4, 18, 'Cryotherapy', NULL, 'Dermatology', NULL, 20000.00, 'pending', 1, NULL, NULL, '2026-08-26 21:42:36', '2026-08-26 21:42:36'),
(53, 59, 61, 4, 20, 'Free - Nutrition Counseling', NULL, 'Nutrition', NULL, 0.00, 'pending', 1, NULL, NULL, '2026-08-26 21:42:36', '2026-08-26 21:42:36'),
(54, 61, 60, 4, 18, 'Cryotherapy', NULL, 'Dermatology', NULL, 20000.00, 'pending', 1, NULL, NULL, '2026-08-27 21:09:33', '2026-08-27 21:09:33'),
(55, 61, 60, 4, 20, 'Free - Nutrition Counseling', NULL, 'Nutrition', NULL, 0.00, 'pending', 1, NULL, NULL, '2026-08-27 21:09:33', '2026-08-27 21:09:33');

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
(21, 'Free - Post-operative Check', 'PROC-FREE-003', 'Post-op Care', 0.00, 'Post-operative follow-up examination', NULL, 1, 1, 1, 1, '2026-08-24 14:43:09', '2026-08-24 14:43:09');

-- --------------------------------------------------------

--
-- Table structure for table `receipts`
--

CREATE TABLE `receipts` (
  `id` int(11) NOT NULL,
  `receipt_number` varchar(50) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `bill_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
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
(11, 'REF-20260826-0049-476', NULL, 49, 4, 'external', NULL, 'MUHIMBILI', '', '+255623693303', NULL, '', 'Expert Type: Cardiology Expert\n\n', '', '', 'Cardiology Expert', 'routine', 'referred', NULL, NULL, NULL, '2026-08-26 22:31:55', 4, 1, '2026-08-26 19:31:55', '2026-08-26 19:31:55', NULL, NULL, NULL),
(12, 'REF-20260826-0048-666', NULL, 48, 4, 'internal', 5, NULL, NULL, NULL, NULL, '', '', '', '', NULL, 'routine', 'referred', NULL, NULL, NULL, '2026-08-26 22:32:56', 4, 1, '2026-08-26 19:32:56', '2026-08-26 19:32:56', NULL, NULL, NULL),
(13, 'REF-20260826-0049-175', NULL, 49, 4, 'internal', 5, NULL, NULL, NULL, NULL, '', '', '', '', NULL, 'routine', 'referred', NULL, NULL, NULL, '2026-08-26 22:32:56', 4, 1, '2026-08-26 19:32:56', '2026-08-26 19:32:56', NULL, NULL, NULL),
(14, 'REF-20260826-0051-173', NULL, 51, 4, 'internal', 5, NULL, NULL, NULL, NULL, '', '', '', '', NULL, 'routine', 'referred', NULL, NULL, NULL, '2026-08-26 22:32:56', 4, 1, '2026-08-26 19:32:56', '2026-08-26 19:32:56', NULL, NULL, NULL),
(15, 'REF-20260826-0052-268', NULL, 52, 4, 'internal', 5, NULL, NULL, NULL, NULL, '', '', '', '', NULL, 'routine', 'referred', NULL, NULL, NULL, '2026-08-26 22:32:56', 4, 1, '2026-08-26 19:32:56', '2026-08-26 19:32:56', NULL, NULL, NULL),
(16, 'REF-20260826-0053-973', NULL, 53, 4, 'internal', 5, NULL, NULL, NULL, NULL, '', '', '', '', NULL, 'routine', 'referred', NULL, NULL, NULL, '2026-08-26 22:32:56', 4, 1, '2026-08-26 19:32:56', '2026-08-26 19:32:56', NULL, NULL, NULL);

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
(21, 2, 'visit_mpya', '', 1, 100000.00, 'each', 1, 0, 11, '2026-08-19 15:31:52', '2026-08-19 15:31:52');

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
(20, 6, NULL, 44, 'out', 10, 280, 270, 'prescription', 18, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716', '2026-08-25 14:24:59'),
(26, NULL, 30, 44, 'out', 2, 3, 1, '', NULL, 4, 1, 'Equipment: ECG Machine (12-Lead) | Batch: BATCH-ECG-001 | Patient: JOHN BOCCO | Visit: VIS-20260825-0044', '2026-08-25 15:03:35'),
(27, NULL, 26, 44, 'out', 2, 20, 18, '', NULL, 4, 1, 'Equipment: Forceps (Tissue) | Batch: BATCH-FORCEP-001 | Patient: JOHN BOCCO | Visit: VIS-20260825-0044', '2026-08-25 15:04:04'),
(29, 6, NULL, 45, 'out', 10, 270, 260, 'prescription', 19, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716 | Patient: JOHN CARTER | Visit: VIS-20260825-0045', '2026-08-25 16:11:20'),
(30, 5, NULL, 45, 'out', 10, 90, 80, 'prescription', 20, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-F8F23A | Patient: JOHN CARTER | Visit: VIS-20260825-0045', '2026-08-25 16:11:50'),
(33, NULL, 27, 45, 'out', 10, 40, 30, '', NULL, 4, 1, 'Equipment: Surgical Blades (Scalpel) | Batch: BATCH-BLADE-001 | Patient: JOHN CARTER | Visit: VIS-20260825-0045', '2026-08-25 16:13:08'),
(34, NULL, 18, 45, 'out', 10, 78, 68, '', NULL, 4, 1, 'Equipment: Bandage (Elastic) | Batch: BATCH-BANDAGE-001 | Patient: JOHN CARTER | Visit: VIS-20260825-0045', '2026-08-25 20:24:47'),
(35, 6, NULL, 46, 'out', 60, 260, 200, 'prescription', 21, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716 | Patient: AMINA ALLY MSANGI | Visit: VIS-20260825-0046', '2026-08-25 20:31:41'),
(36, 5, NULL, 46, 'out', 79, 80, 1, 'prescription', 22, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-F8F23A | Patient: AMINA ALLY MSANGI | Visit: VIS-20260825-0046', '2026-08-25 20:32:12'),
(37, NULL, 18, 46, 'out', 38, 68, 30, '', NULL, 4, 1, 'Equipment: Bandage (Elastic) | Batch: BATCH-BANDAGE-001 | Patient: AMINA ALLY MSANGI | Visit: VIS-20260825-0046', '2026-08-25 20:32:54'),
(38, 5, NULL, 47, 'out', 1, 1, 0, 'prescription', 23, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-F8F23A | Patient: MARTHA KIMAMALA | Visit: VIS-20260825-0047', '2026-08-25 21:52:29'),
(39, 6, NULL, 47, 'out', 10, 200, 190, 'prescription', 24, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716 | Patient: MARTHA KIMAMALA | Visit: VIS-20260825-0047', '2026-08-25 21:52:45'),
(40, NULL, 17, 47, 'out', 10, 150, 140, '', NULL, 4, 1, 'Equipment: Adhesive Tape (Roll) | Batch: BATCH-TAPE-001 | Patient: MARTHA KIMAMALA | Visit: VIS-20260825-0047', '2026-08-25 21:53:11'),
(41, NULL, 18, 47, 'out', 10, 30, 20, '', NULL, 4, 1, 'Equipment: Bandage (Elastic) | Batch: BATCH-BANDAGE-001 | Patient: MARTHA KIMAMALA | Visit: VIS-20260825-0047', '2026-08-25 21:53:26'),
(42, NULL, 27, 47, 'out', 10, 30, 20, '', NULL, 4, 1, 'Equipment: Surgical Blades (Scalpel) | Batch: BATCH-BLADE-001 | Patient: MARTHA KIMAMALA | Visit: VIS-20260825-0047', '2026-08-25 21:53:26'),
(43, 4, NULL, 48, 'out', 70, 370, 300, 'prescription', 25, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-914E09 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-0048', '2026-08-25 22:05:16'),
(44, NULL, 18, 48, 'out', 1, 20, 19, '', NULL, 4, 1, 'Equipment: Bandage (Elastic) | Batch: BATCH-BANDAGE-001 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-0048', '2026-08-25 22:05:37'),
(45, 4, NULL, 47, 'out', 10, 300, 290, 'prescription', 26, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-914E09 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-2681', '2026-08-26 08:53:43'),
(46, NULL, 27, 47, 'out', 3, 20, 17, '', NULL, 4, 1, 'Equipment: Surgical Blades (Scalpel) | Batch: BATCH-BLADE-001 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-2681', '2026-08-26 08:54:05'),
(47, 4, NULL, 48, 'out', 10, 290, 280, 'prescription', 27, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-914E09 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-6522', '2026-08-26 09:32:12'),
(48, NULL, 18, 48, 'out', 10, 19, 9, '', NULL, 4, 1, 'Equipment: Bandage (Elastic) | Batch: BATCH-BANDAGE-001 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-6522', '2026-08-26 09:32:44'),
(49, 4, NULL, 48, 'out', 20, 280, 260, 'prescription', 28, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-914E09 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-4037', '2026-08-26 10:00:51'),
(50, NULL, 18, 48, 'out', 5, 9, 4, '', NULL, 4, 1, 'Equipment: Bandage (Elastic) | Batch: BATCH-BANDAGE-001 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-4037', '2026-08-26 10:01:23'),
(51, NULL, 27, 48, 'out', 5, 17, 12, '', NULL, 4, 1, 'Equipment: Surgical Blades (Scalpel) | Batch: BATCH-BLADE-001 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-4037', '2026-08-26 10:01:23'),
(52, NULL, 19, 48, 'out', 5, 1000, 995, '', NULL, 4, 1, 'Equipment: Gloves (Surgical - Sterile) | Batch: BATCH-GLOVES-001 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-4037', '2026-08-26 10:01:23'),
(53, 4, NULL, 47, 'out', 10, 260, 250, 'prescription', 29, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-914E09 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-9634', '2026-08-26 10:11:16'),
(54, NULL, 27, 47, 'out', 5, 12, 7, '', NULL, 4, 1, 'Equipment: Surgical Blades (Scalpel) | Batch: BATCH-BLADE-001 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-9634', '2026-08-26 10:11:48'),
(55, NULL, 19, 47, 'out', 5, 995, 990, '', NULL, 4, 1, 'Equipment: Gloves (Surgical - Sterile) | Batch: BATCH-GLOVES-001 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-9634', '2026-08-26 10:11:48'),
(56, 4, NULL, 48, 'out', 1, 250, 249, 'prescription', 30, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-914E09 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-6810', '2026-08-26 11:06:59'),
(57, 4, NULL, 48, 'out', 10, 250, 240, 'prescription', 31, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-914E09 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-6810', '2026-08-26 11:07:20'),
(58, NULL, 15, 48, 'out', 10, 500, 490, '', NULL, 4, 1, 'Equipment: Gauze Swabs (Sterile) | Batch: BATCH-GAUZE-001 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-6810', '2026-08-26 11:07:45'),
(59, NULL, 19, 48, 'out', 10, 990, 980, '', NULL, 4, 1, 'Equipment: Gloves (Surgical - Sterile) | Batch: BATCH-GLOVES-001 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-6810', '2026-08-26 11:07:45'),
(60, 4, NULL, 47, 'out', 10, 240, 230, 'prescription', 32, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-914E09 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-5412', '2026-08-26 12:42:45'),
(61, 6, NULL, 47, 'out', 10, 190, 180, 'prescription', 33, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-5412', '2026-08-26 12:42:58'),
(62, NULL, 17, 47, 'out', 3, 140, 137, '', NULL, 4, 1, 'Equipment: Adhesive Tape (Roll) | Batch: BATCH-TAPE-001 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-5412', '2026-08-26 12:43:27'),
(63, NULL, 18, 47, 'out', 3, 4, 1, '', NULL, 4, 1, 'Equipment: Bandage (Elastic) | Batch: BATCH-BANDAGE-001 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-5412', '2026-08-26 12:43:27'),
(64, NULL, 26, 47, 'out', 5, 18, 13, '', NULL, 4, 1, 'Equipment: Forceps (Tissue) | Batch: BATCH-FORCEP-001 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-5412', '2026-08-26 12:43:52'),
(65, 6, NULL, 47, 'out', 1, 180, 179, 'prescription', 34, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-5712', '2026-08-26 12:49:37'),
(66, 4, NULL, 47, 'out', 1, 230, 229, 'prescription', 35, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-914E09 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-5712', '2026-08-26 12:49:43'),
(67, NULL, 22, 47, 'out', 1, 15, 14, '', NULL, 4, 1, 'Equipment: Blood Pressure Cuff (Manual) | Batch: BATCH-BP-001 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-5712', '2026-08-26 12:50:15'),
(68, NULL, 33, 47, 'out', 1, 8, 7, '', NULL, 4, 1, 'Equipment: Infusion Pump | Batch: BATCH-INFUSE-001 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-5712', '2026-08-26 12:50:15'),
(69, NULL, 23, 47, 'out', 1, 20, 19, '', NULL, 4, 1, 'Equipment: Pulse Oximeter | Batch: BATCH-OXI-001 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-5712', '2026-08-26 12:50:15'),
(70, NULL, 29, 47, 'out', 1, 10, 9, '', NULL, 4, 1, 'Equipment: Retractor (Surgical) | Batch: BATCH-RETRACT-001 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-5712', '2026-08-26 12:50:15'),
(71, 6, NULL, 49, 'out', 1, 179, 178, 'prescription', 36, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716 | Patient: AGUSTINO VALENTINE | Visit: VIS-20260826-5485', '2026-08-26 13:22:44'),
(72, 4, NULL, 49, 'out', 1, 229, 228, 'prescription', 37, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-914E09 | Patient: AGUSTINO VALENTINE | Visit: VIS-20260826-5485', '2026-08-26 13:22:51'),
(73, NULL, 17, 49, 'out', 1, 137, 136, '', NULL, 4, 1, 'Equipment: Adhesive Tape (Roll) | Batch: BATCH-TAPE-001 | Patient: AGUSTINO VALENTINE | Visit: VIS-20260826-5485', '2026-08-26 13:23:22'),
(74, NULL, 28, 49, 'out', 1, 15, 14, '', NULL, 4, 1, 'Equipment: Needle Holder (Surgical) | Batch: BATCH-NEEDLE-001 | Patient: AGUSTINO VALENTINE | Visit: VIS-20260826-5485', '2026-08-26 13:23:22'),
(75, 4, NULL, 50, 'out', 1, 228, 227, 'prescription', 38, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-914E09 | Patient: KELVIN MSAFIRI | Visit: VIS-20260826-0050', '2026-08-26 13:44:18'),
(76, NULL, 22, 50, 'out', 1, 14, 13, '', NULL, 4, 1, 'Equipment: Blood Pressure Cuff (Manual) | Batch: BATCH-BP-001 | Patient: KELVIN MSAFIRI | Visit: VIS-20260826-0050', '2026-08-26 13:44:32'),
(77, NULL, 26, 50, 'out', 1, 13, 12, '', NULL, 4, 1, 'Equipment: Forceps (Tissue) | Batch: BATCH-FORCEP-001 | Patient: KELVIN MSAFIRI | Visit: VIS-20260826-0050', '2026-08-26 13:44:32'),
(78, 4, NULL, 48, 'out', 1, 227, 226, 'prescription', 39, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-914E09 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-4859', '2026-08-26 13:45:52'),
(79, NULL, 17, 48, 'out', 1, 136, 135, '', NULL, 4, 1, 'Equipment: Adhesive Tape (Roll) | Batch: BATCH-TAPE-001 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-4859', '2026-08-26 13:45:57'),
(80, 6, NULL, 47, 'out', 1, 178, 177, 'prescription', 40, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-7218', '2026-08-26 14:16:28'),
(81, NULL, 22, 47, 'out', 3, 13, 10, '', NULL, 4, 1, 'Equipment: Blood Pressure Cuff (Manual) | Batch: BATCH-BP-001 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-7218', '2026-08-26 14:16:48'),
(82, 6, NULL, 48, 'out', 1, 177, 176, 'prescription', 41, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-7053', '2026-08-26 14:36:28'),
(83, NULL, 27, 48, 'out', 1, 7, 6, '', NULL, 4, 1, 'Equipment: Surgical Blades (Scalpel) | Batch: BATCH-BLADE-001 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-7053', '2026-08-26 14:36:57'),
(84, NULL, 19, 48, 'out', 1, 980, 979, '', NULL, 4, 1, 'Equipment: Gloves (Surgical - Sterile) | Batch: BATCH-GLOVES-001 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-7053', '2026-08-26 14:36:57'),
(85, NULL, 26, 48, 'out', 1, 12, 11, '', NULL, 4, 1, 'Equipment: Forceps (Tissue) | Batch: BATCH-FORCEP-001 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-7053', '2026-08-26 14:36:57'),
(86, 6, NULL, 49, 'out', 1, 176, 175, 'prescription', 42, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716 | Patient: AGUSTINO VALENTINE | Visit: VIS-20260826-4039', '2026-08-26 14:57:32'),
(87, NULL, 17, 49, 'out', 1, 135, 134, '', NULL, 4, 1, 'Equipment: Adhesive Tape (Roll) | Batch: BATCH-TAPE-001 | Patient: AGUSTINO VALENTINE | Visit: VIS-20260826-4039', '2026-08-26 14:57:47'),
(88, NULL, 32, 49, 'out', 1, 4, 3, '', NULL, 4, 1, 'Equipment: Spirometer (Digital) | Batch: BATCH-SPIRO-001 | Patient: AGUSTINO VALENTINE | Visit: VIS-20260826-4039', '2026-08-26 14:57:47'),
(89, 6, NULL, 47, 'out', 1, 175, 174, 'prescription', 43, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-3053', '2026-08-26 15:19:44'),
(90, NULL, 17, 47, 'out', 1, 134, 133, '', NULL, 4, 1, 'Equipment: Adhesive Tape (Roll) | Batch: BATCH-TAPE-001 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-3053', '2026-08-26 15:19:54'),
(91, NULL, 33, 47, 'out', 1, 7, 6, '', NULL, 4, 1, 'Equipment: Infusion Pump | Batch: BATCH-INFUSE-001 | Patient: MARTHA KIMAMALA | Visit: VIS-20260826-3053', '2026-08-26 15:19:54'),
(92, 6, NULL, 48, 'out', 1, 174, 173, 'prescription', 44, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-2717', '2026-08-26 15:25:05'),
(93, 4, NULL, 48, 'out', 1, 226, 225, 'prescription', 45, 4, 1, 'Prescription: AMOXILINE | Batch: BATCH-20260824-914E09 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-2717', '2026-08-26 15:25:18'),
(94, NULL, 15, 48, 'out', 1, 490, 489, '', NULL, 4, 1, 'Equipment: Gauze Swabs (Sterile) | Batch: BATCH-GAUZE-001 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-2717', '2026-08-26 15:25:35'),
(95, NULL, 26, 48, 'out', 1, 11, 10, '', NULL, 4, 1, 'Equipment: Forceps (Tissue) | Batch: BATCH-FORCEP-001 | Patient: IBRAHIM DOUMBIA | Visit: VIS-20260826-2717', '2026-08-26 15:25:35'),
(96, 6, NULL, 49, 'out', 1, 173, 172, 'prescription', 46, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716 | Patient: AGUSTINO VALENTINE | Visit: VIS-20260826-8674', '2026-08-26 15:44:13'),
(97, NULL, 28, 49, 'out', 1, 14, 13, '', NULL, 4, 1, 'Equipment: Needle Holder (Surgical) | Batch: BATCH-NEEDLE-001 | Patient: AGUSTINO VALENTINE | Visit: VIS-20260826-8674', '2026-08-26 15:44:39'),
(98, NULL, 32, 49, 'out', 1, 3, 2, '', NULL, 4, 1, 'Equipment: Spirometer (Digital) | Batch: BATCH-SPIRO-001 | Patient: AGUSTINO VALENTINE | Visit: VIS-20260826-8674', '2026-08-26 15:44:39'),
(99, NULL, 25, 49, 'out', 1, 25, 24, '', NULL, 4, 1, 'Equipment: Surgical Scissors (Mayo) | Batch: BATCH-SCISSOR-001 | Patient: AGUSTINO VALENTINE | Visit: VIS-20260826-8674', '2026-08-26 15:44:39'),
(100, NULL, 24, 49, 'out', 1, 5, 4, '', NULL, 4, 1, 'Equipment: Weighing Scale (Medical) | Batch: BATCH-SCALE-001 | Patient: AGUSTINO VALENTINE | Visit: VIS-20260826-8674', '2026-08-26 15:44:39'),
(101, 6, NULL, 50, 'out', 1, 172, 171, 'prescription', 47, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716 | Patient: KELVIN MSAFIRI | Visit: VIS-20260826-0824', '2026-08-26 15:58:09'),
(102, NULL, 17, 50, 'out', 33, 133, 100, '', NULL, 4, 1, 'Equipment: Adhesive Tape (Roll) | Batch: BATCH-TAPE-001 | Patient: KELVIN MSAFIRI | Visit: VIS-20260826-0824', '2026-08-26 15:58:30'),
(103, 6, NULL, 58, 'out', 1, 171, 170, 'prescription', 48, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716 | Patient: AYUBU NZAL | Visit: VIS-20260826-3483', '2026-08-26 20:46:05'),
(104, NULL, 17, 58, 'out', 1, 100, 99, '', NULL, 4, 1, 'Equipment: Adhesive Tape (Roll) | Batch: BATCH-TAPE-001 | Patient: AYUBU NZAL | Visit: VIS-20260826-3483', '2026-08-26 20:46:25'),
(105, NULL, 27, 58, 'out', 1, 6, 5, '', NULL, 4, 1, 'Equipment: Surgical Blades (Scalpel) | Batch: BATCH-BLADE-001 | Patient: AYUBU NZAL | Visit: VIS-20260826-3483', '2026-08-26 20:46:25'),
(106, 6, NULL, 61, 'out', 10, 170, 160, 'prescription', 49, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716 | Patient: MUSSA MONGI MASNGI | Visit: VIS-20260826-0112', '2026-08-26 21:42:31'),
(107, NULL, 17, 61, 'out', 5, 99, 94, '', NULL, 4, 1, 'Equipment: Adhesive Tape (Roll) | Batch: BATCH-TAPE-001 | Patient: MUSSA MONGI MASNGI | Visit: VIS-20260826-0112', '2026-08-26 21:42:59'),
(108, NULL, 28, 61, 'out', 5, 13, 8, '', NULL, 4, 1, 'Equipment: Needle Holder (Surgical) | Batch: BATCH-NEEDLE-001 | Patient: MUSSA MONGI MASNGI | Visit: VIS-20260826-0112', '2026-08-26 21:42:59'),
(109, 6, NULL, 60, 'out', 1, 160, 159, 'prescription', 50, 4, 1, 'Prescription: ALBENDAZOLE | Batch: BATCH-20260825-AEB716 | Patient: ANDREW VICENT CHIKUPE | Visit: VIS-20260827-3977', '2026-08-27 21:09:21'),
(110, NULL, 15, 60, 'out', 1, 489, 488, '', NULL, 4, 1, 'Equipment: Gauze Swabs (Sterile) | Batch: BATCH-GAUZE-001 | Patient: ANDREW VICENT CHIKUPE | Visit: VIS-20260827-3977', '2026-08-27 21:09:46'),
(111, NULL, 27, 60, 'out', 1, 5, 4, '', NULL, 4, 1, 'Equipment: Surgical Blades (Scalpel) | Batch: BATCH-BLADE-001 | Patient: ANDREW VICENT CHIKUPE | Visit: VIS-20260827-3977', '2026-08-27 21:09:46'),
(112, NULL, 28, 60, 'out', 1, 8, 7, '', NULL, 4, 1, 'Equipment: Needle Holder (Surgical) | Batch: BATCH-NEEDLE-001 | Patient: ANDREW VICENT CHIKUPE | Visit: VIS-20260827-3977', '2026-08-27 21:09:46');

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

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `phone`, `role`, `branch_id`, `specialty`, `is_online`, `last_online`, `profile_pic`, `status`, `created_at`, `updated_at`) VALUES
(1, 'admin1', '$2y$10$kFqjZ8k3Xx8Xx8Xx8Xx8uO8Xx8Xx8Xx8Xx8Xx8Xx8Xx8Xx8Xx8', 'System Admin', 'admin@braick.com', '+255 700 000 000', 'admin', 1, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:26:10', '2026-08-26 14:05:20'),
(3, 'admin2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'BRAICK', 'braick.admin@braick.com', '+255 700 000 000', 'admin', 1, NULL, 0, '2026-08-26 14:10:40', NULL, 'active', '2026-08-23 12:41:40', '2026-08-26 14:10:40'),
(4, 'Dr.Dodoma1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr.ERICK JOHN', 'erick.dodoma@braick.com', '+255 700 000 011', 'doctor', 1, 'General Medicine', 1, '2026-08-27 20:25:31', 'user_4_1787697956.png', 'active', '2026-08-23 12:41:40', '2026-08-27 20:25:31'),
(5, 'Dr.Dodoma2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Grace Peter', 'grace.dodoma@braick.com', '+255 700 000 012', 'doctor', 1, 'Pediatrics', 0, '2026-08-27 20:25:26', NULL, 'active', '2026-08-23 12:41:40', '2026-08-27 20:25:26'),
(6, 'Dr.Dodoma3', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. John Mushi', 'john.dodoma@braick.com', '+255 700 000 013', 'doctor', 1, 'Cardiology', 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-08-25 23:41:27'),
(7, 'Pharm.Dodoma3', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'LUCY MUSSA', 'pharm.dodoma@braick.com', '+255 700 000 014', 'pharmacy', 1, NULL, 0, '2026-08-25 08:30:19', 'user_7_1787493390.png', 'active', '2026-08-23 12:41:40', '2026-08-25 23:43:04'),
(8, 'Pharm.Dodoma2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mary John', 'mary.dodoma@braick.com', '+255 700 000 015', 'pharmacy', 1, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-08-25 23:42:30'),
(9, 'Pharm.Dodoma1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'James Mwangi', 'james.dodoma@braick.com', '+255 700 000 016', 'pharmacy', 1, NULL, 0, '2026-08-27 20:36:10', NULL, 'active', '2026-08-23 12:41:40', '2026-08-27 20:36:10'),
(10, 'Recpt.Dodoma1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Reception SALOME', 'salome.dodoma@braick.com', '+255 700 000 017', 'reception', 1, NULL, 0, '2026-08-27 21:11:42', 'reception_10_1787518197.png', 'active', '2026-08-23 12:41:40', '2026-08-27 21:11:42'),
(11, 'Recpt.Dodoma2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Rose Mwangi', 'rose.dodoma@braick.com', '+255 700 000 018', 'reception', 1, NULL, 0, '2026-08-26 15:13:58', NULL, 'active', '2026-08-23 12:41:40', '2026-08-26 15:13:58'),
(12, 'Recpt.Dodoma3', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'JUDITH SOLOMONI', 'anna.dodoma@braick.com', '+255 700 000 019', 'reception', 1, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-08-25 23:47:35'),
(13, 'Lab.Dodoma1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ANGERITHA KIMARO', 'lab.dodoma@braick.com', '+255 700 000 020', 'laboratory', 1, NULL, 0, '2026-08-27 20:35:50', 'user_13_1787502536.png', 'active', '2026-08-23 12:41:40', '2026-08-27 20:35:50'),
(14, 'Lab.Dodoma2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Peter Lema', 'peter.dodoma@braick.com', '+255 700 000 021', 'laboratory', 1, NULL, 0, '2026-08-26 08:50:32', NULL, 'active', '2026-08-23 12:41:40', '2026-08-26 08:50:32'),
(15, 'Lab.Dodoma3', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sarah Mwamba', 'sarah.dodoma@braick.com', '+255 700 000 022', 'laboratory', 1, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-08-25 23:46:17'),
(16, 'cashier.dodoma', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Cashier Dodoma', 'cashier.dodoma@braick.com', '+255 700 000 023', 'cashier', 1, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-08-23 12:41:40'),
(17, 'dr.arusha1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. David Mwanga', 'david.arusha@braick.com', '+255 700 000 024', 'doctor', 2, 'General Medicine', 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-08-23 12:41:40'),
(18, 'dr.arusha2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Anna Kivuyo', 'anna.arusha@braick.com', '+255 700 000 025', 'doctor', 2, 'Obstetrics', 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-08-23 12:41:40'),
(19, 'dr.arusha3', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Peter Lema', 'peter.arusha@braick.com', '+255 700 000 026', 'doctor', 2, 'Surgery', 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-08-23 12:41:40'),
(20, 'pharm.arusha1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Pharmacy Arusha', 'pharm.arusha@braick.com', '+255 700 000 027', 'pharmacy', 2, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-08-23 12:41:40'),
(21, 'pharm.arusha2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Juma Mussa', 'juma.arusha@braick.com', '+255 700 000 028', 'pharmacy', 2, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-08-23 12:41:40'),
(22, 'pharm.arusha3', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Catherine M', 'catherine.arusha@braick.com', '+255 700 000 029', 'pharmacy', 2, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-08-23 12:41:40'),
(23, 'reception.arusha1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Reception Arusha', 'reception.arusha@braick.com', '+255 700 000 030', 'reception', 2, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-08-23 12:41:40'),
(24, 'reception.arusha2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Grace Mushi', 'grace.arusha@braick.com', '+255 700 000 031', 'reception', 2, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-08-23 12:41:40'),
(25, 'recpt.arusha3', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Lucy Peter', 'lucy.arusha@braick.com', '+255 700 000 032', 'reception', 2, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-08-26 06:05:24'),
(26, 'Lab.Arusha1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'MARIA MSANGI', 'lab.arusha@braick.com', '+255 700 000 033', 'laboratory', 2, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-08-26 06:06:35'),
(27, 'lab.arusha2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Moses Paul', 'moses.arusha@braick.com', '+255 700 000 034', 'laboratory', 2, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-08-23 12:41:40'),
(28, 'lab.arusha3', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Hellen John', 'hellen.arusha@braick.com', '+255 700 000 035', 'laboratory', 2, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-08-23 12:41:40'),
(29, 'cashier.arusha', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Cashier Arusha', 'cashier.arusha@braick.com', '+255 700 000 036', 'cashier', 2, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-08-23 12:41:40'),
(30, 'dr.dar1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. James Kato', 'james.dar@braick.com', '+255 700 000 037', 'doctor', 3, 'Neurology', 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-08-23 12:41:40'),
(31, 'dr.dar2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Sarah Mwamba', 'sarah.dar@braick.com', '+255 700 000 038', 'doctor', 3, 'Cardiology', 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-08-23 12:41:40'),
(32, 'dr.dar3', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Mary Ndugu', 'mary.dar@braick.com', '+255 700 000 039', 'doctor', 3, 'Pediatrics', 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-08-23 12:41:40'),
(33, 'pharm.dar1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Pharmacy Dar', 'pharm.dar@braick.com', '+255 700 000 040', 'pharmacy', 3, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-08-23 12:41:40'),
(34, 'pharm.dar2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'William M', 'william.dar@braick.com', '+255 700 000 041', 'pharmacy', 3, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-08-23 12:41:40'),
(35, 'pharm.dar3', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Diana K', 'diana.dar@braick.com', '+255 700 000 042', 'pharmacy', 3, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-08-23 12:41:40'),
(36, 'reception.dar1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Reception Dar', 'reception.dar@braick.com', '+255 700 000 043', 'reception', 3, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-08-23 12:41:40'),
(37, 'reception.dar2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Flora M', 'flora.dar@braick.com', '+255 700 000 044', 'reception', 3, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-08-23 12:41:40'),
(38, 'reception.dar3', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Paul L', 'paul.dar@braick.com', '+255 700 000 045', 'reception', 3, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-08-23 12:41:40'),
(39, 'lab.dar1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Lab Technician Dar', 'lab.dar@braick.com', '+255 700 000 046', 'laboratory', 3, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-08-23 12:41:40'),
(40, 'lab.dar2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Thomas N', 'thomas.dar@braick.com', '+255 700 000 047', 'laboratory', 3, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-08-23 12:41:40'),
(41, 'lab.dar3', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane K', 'jane.dar@braick.com', '+255 700 000 048', 'laboratory', 3, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-08-23 12:41:40'),
(42, 'cashier.dar', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Cashier Dar', 'cashier.dar@braick.com', '+255 700 000 049', 'cashier', 3, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-08-23 12:41:40');

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
  `status` enum('pending','assigned','with_doctor','lab_test','lab_completed','prescribed','completed','cancelled') DEFAULT 'pending',
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

--
-- Dumping data for table `visits`
--

INSERT INTO `visits` (`id`, `visit_number`, `visit_date`, `patient_id`, `doctor_id`, `assigned_at`, `receptionist_id`, `branch_id`, `visit_type`, `service_id`, `consultation_fee`, `status`, `symptoms`, `hpi`, `physical_exam`, `complaint`, `diagnosis`, `disease_id`, `disease_code`, `treatment`, `follow_up_date`, `notes`, `is_referred`, `created_at`, `updated_at`, `is_completed`, `completed_at`, `lab_fees_total`, `pharmacy_fees_total`, `other_fees_total`, `visit_total`, `payment_status`, `total_discount`, `discount_percent`) VALUES
(59, 'VIS-20260826-0112', '2026-08-27 00:39:23', 61, 4, NULL, 11, 1, 'Specialist Consultation', 6, 30000.00, 'completed', 'Dizziness, Vomiting, Body Pain', 'KICHWA NA KIZUNGUZUNGU', 'KICHWA NA KIZUNGUZUNGU', 'KICHWA NA KIZUNGUZUNGU', 'Congestive Heart Failure', 9, 'CHF-001', 'Diuretics, ACE inhibitors, Beta-blockers', NULL, 'MACHO PIA YANUUMA SANA KWENYE MWANGA', 0, '2026-08-26 21:39:23', '2026-08-26 21:43:45', 1, '2026-08-26 21:43:45', 0.00, 0.00, 0.00, 0.00, 'pending', 0.00, 0.00),
(60, 'VIS-20260827-9154', '2026-08-27 01:37:54', 55, 4, NULL, 11, 1, 'visit_mpya', 21, 100000.00, 'completed', 'Dizziness', NULL, NULL, 'KICHWA KINAUMA SANA SANA FORE HEAD', NULL, NULL, NULL, NULL, NULL, 'PIA MISHIPA YA MACHO INAUMA', 0, '2026-08-26 22:37:54', '2026-08-26 22:42:09', 1, '2026-08-26 22:42:09', 0.00, 0.00, 0.00, 0.00, 'pending', 0.00, 0.00),
(61, 'VIS-20260827-3977', '2026-08-27 01:43:03', 60, 4, NULL, 11, 1, 'New Patient', 17, 10000.00, 'prescribed', 'Swelling', 'History of Presenting Illness (HPI)', 'History of Presenting Illness (HPI)\nPhysical Examination', 'KIPANDA USO', 'Typhoid Fever', 6, 'TY-001', 'Antibiotics, Hydration', NULL, 'KIZUNGUZUNGU', 0, '2026-08-26 22:43:03', '2026-08-27 21:20:53', 0, NULL, 0.00, 0.00, 0.00, 0.00, 'pending', 0.00, 0.00);

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
(1, 2, NULL, NULL, 10, 1, 30.0, 110, 79, 70, NULL, NULL, NULL, 70.00, 167.40, 25.0, NULL, NULL, NULL, '2026-08-23 22:32:04', '2026-08-23 22:32:04', '2026-08-23 22:32:04'),
(2, 1, NULL, NULL, 10, 1, 40.0, 128, 91, 60, NULL, NULL, NULL, 74.00, 170.00, 25.6, NULL, NULL, NULL, '2026-08-23 22:47:47', '2026-08-23 22:47:47', '2026-08-23 22:47:47'),
(3, 4, NULL, NULL, 10, 1, 30.0, 120, 90, 70, NULL, NULL, NULL, 70.00, 180.00, 21.6, NULL, NULL, NULL, '2026-08-24 22:11:50', '2026-08-24 22:11:50', '2026-08-24 22:11:50'),
(4, 38, NULL, NULL, 10, 1, 37.0, 123, 90, 77, NULL, NULL, NULL, 73.00, 180.00, 22.5, NULL, NULL, NULL, '2026-08-24 23:09:37', '2026-08-24 23:09:37', '2026-08-24 23:09:37'),
(5, 39, NULL, NULL, 10, 1, 30.0, 123, 80, 70, NULL, NULL, NULL, 76.00, 180.00, 23.5, NULL, NULL, NULL, '2026-08-24 23:33:52', '2026-08-24 23:33:52', '2026-08-24 23:33:52'),
(6, 40, NULL, NULL, 10, 1, 36.5, 120, 80, 72, NULL, NULL, NULL, 65.00, 170.00, 22.5, NULL, NULL, NULL, '2026-08-25 08:11:57', '2026-08-25 08:11:57', '2026-08-25 08:11:57'),
(7, 41, NULL, NULL, 10, 1, 37.0, 128, 79, 69, NULL, NULL, NULL, 73.00, 180.00, 22.5, NULL, NULL, NULL, '2026-08-25 08:42:21', '2026-08-25 08:42:21', '2026-08-25 08:42:21'),
(8, 42, NULL, NULL, 10, 1, 30.0, 126, 78, 65, NULL, NULL, NULL, 78.00, 179.90, 24.1, NULL, NULL, NULL, '2026-08-25 09:00:57', '2026-08-25 09:00:57', '2026-08-25 09:00:57'),
(9, 43, NULL, NULL, 10, 1, 36.0, 128, 89, 69, NULL, NULL, NULL, 79.00, 178.00, 24.9, NULL, NULL, NULL, '2026-08-25 09:52:25', '2026-08-25 09:52:25', '2026-08-25 09:52:25'),
(10, 44, NULL, NULL, 10, 1, 30.0, 120, 90, 60, NULL, NULL, NULL, 68.00, 178.00, 21.5, NULL, NULL, NULL, '2026-08-25 14:13:18', '2026-08-25 14:13:18', '2026-08-25 14:13:18'),
(11, 45, NULL, NULL, 10, 1, 38.0, 120, 80, 71, NULL, NULL, NULL, 77.90, 178.00, 24.6, NULL, NULL, NULL, '2026-08-25 14:41:37', '2026-08-25 14:41:37', '2026-08-25 14:41:37'),
(12, 46, NULL, NULL, 10, 1, 30.0, 119, 79, 60, NULL, NULL, NULL, 70.00, 180.00, 21.6, NULL, NULL, NULL, '2026-08-25 20:27:15', '2026-08-25 20:27:15', '2026-08-25 20:27:15'),
(13, 47, NULL, NULL, 10, 1, 32.0, 129, 80, 71, NULL, NULL, NULL, 75.00, 177.60, 23.8, NULL, NULL, NULL, '2026-08-25 21:20:23', '2026-08-25 21:20:23', '2026-08-25 21:20:23'),
(14, 48, NULL, NULL, 10, 1, 33.0, 129, 79, 60, NULL, NULL, NULL, 78.00, 183.00, 23.3, NULL, NULL, NULL, '2026-08-25 22:01:03', '2026-08-25 22:01:03', '2026-08-25 22:01:03'),
(15, 48, NULL, NULL, 10, 1, 32.0, 121, 78, 71, NULL, NULL, NULL, 71.00, 176.00, 22.9, NULL, NULL, NULL, '2026-08-26 08:37:01', '2026-08-26 08:37:01', '2026-08-26 08:37:01'),
(16, 47, NULL, NULL, 10, 1, 37.0, 129, 89, 69, NULL, NULL, NULL, 60.00, 169.00, 21.0, NULL, NULL, NULL, '2026-08-26 08:37:54', '2026-08-26 08:37:54', '2026-08-26 08:37:54'),
(17, 48, NULL, NULL, 10, 1, 38.0, 128, 90, 69, NULL, NULL, NULL, 72.00, 75.00, 128.0, NULL, NULL, NULL, '2026-08-26 09:49:07', '2026-08-26 09:49:07', '2026-08-26 09:49:07'),
(18, 47, NULL, NULL, 10, 1, 35.0, 129, 78, 69, NULL, NULL, NULL, 72.00, 174.00, 23.8, NULL, NULL, NULL, '2026-08-26 10:08:41', '2026-08-26 10:08:41', '2026-08-26 10:08:41'),
(19, 48, NULL, NULL, 10, 1, 33.0, 122, 78, 68, NULL, NULL, NULL, 74.00, 180.00, 22.8, NULL, NULL, NULL, '2026-08-26 10:28:56', '2026-08-26 10:28:56', '2026-08-26 10:28:56'),
(20, 47, NULL, NULL, 10, 1, 35.0, 120, 79, 67, NULL, NULL, NULL, 70.00, 170.00, 24.2, NULL, NULL, NULL, '2026-08-26 12:23:28', '2026-08-26 12:23:28', '2026-08-26 12:23:28'),
(21, 49, NULL, NULL, 10, 1, 25.0, 129, 78, 87, NULL, NULL, NULL, 58.00, 168.00, 20.5, NULL, NULL, NULL, '2026-08-26 12:30:15', '2026-08-26 12:30:15', '2026-08-26 12:30:15'),
(22, 48, NULL, NULL, 10, 1, 39.0, 125, 78, 70, NULL, NULL, NULL, 72.00, 178.00, 22.7, NULL, NULL, NULL, '2026-08-26 12:31:09', '2026-08-26 12:31:09', '2026-08-26 12:31:09'),
(23, 49, NULL, NULL, 10, 1, 25.0, 129, 78, 87, NULL, NULL, NULL, 58.00, 168.00, 20.5, NULL, NULL, NULL, '2026-08-26 12:45:41', '2026-08-26 12:45:41', '2026-08-26 12:45:41'),
(24, 48, NULL, NULL, 10, 1, 39.0, 123, 89, 70, NULL, NULL, NULL, 70.00, 178.00, 22.1, NULL, NULL, NULL, '2026-08-26 12:46:57', '2026-08-26 12:46:57', '2026-08-26 12:46:57'),
(25, 47, NULL, NULL, 10, 1, 35.0, 120, 79, 67, NULL, NULL, NULL, 70.00, 170.00, 24.2, NULL, NULL, NULL, '2026-08-26 12:47:57', '2026-08-26 12:47:57', '2026-08-26 12:47:57'),
(26, 50, NULL, NULL, 10, 1, 30.0, 120, 80, 70, NULL, NULL, NULL, 70.00, 177.90, 22.1, NULL, NULL, NULL, '2026-08-26 14:08:04', '2026-08-26 14:08:04', '2026-08-26 14:08:04'),
(27, 49, NULL, NULL, 10, 1, 25.0, 129, 78, 87, NULL, NULL, NULL, 58.00, 168.00, 20.5, NULL, NULL, NULL, '2026-08-26 14:08:25', '2026-08-26 14:08:25', '2026-08-26 14:08:25'),
(28, 48, NULL, NULL, 10, 1, 39.0, 123, 89, 70, NULL, NULL, NULL, 70.00, 178.00, 22.1, NULL, NULL, NULL, '2026-08-26 14:08:42', '2026-08-26 14:08:42', '2026-08-26 14:08:42'),
(29, 47, NULL, NULL, 10, 1, 35.0, 120, 79, 67, NULL, NULL, NULL, 70.00, 170.00, 24.2, NULL, NULL, NULL, '2026-08-26 14:09:04', '2026-08-26 14:09:04', '2026-08-26 14:09:04'),
(30, 50, NULL, NULL, 11, 1, 34.0, 123, 67, 76, NULL, NULL, NULL, 65.00, 174.00, 21.5, NULL, NULL, NULL, '2026-08-26 15:16:50', '2026-08-26 15:16:50', '2026-08-26 15:16:50'),
(31, 49, NULL, NULL, 11, 1, 25.0, 129, 78, 87, NULL, NULL, NULL, 58.00, 168.00, 20.5, NULL, NULL, NULL, '2026-08-26 15:17:23', '2026-08-26 15:17:23', '2026-08-26 15:17:23'),
(32, 48, NULL, NULL, 11, 1, 39.0, 123, 89, 70, NULL, NULL, NULL, 70.00, 178.00, 22.1, NULL, NULL, NULL, '2026-08-26 15:17:51', '2026-08-26 15:17:51', '2026-08-26 15:17:51'),
(33, 47, NULL, NULL, 11, 1, 35.0, 120, 79, 67, NULL, NULL, NULL, 70.00, 170.00, 24.2, NULL, NULL, NULL, '2026-08-26 15:18:12', '2026-08-26 15:18:12', '2026-08-26 15:18:12'),
(34, 61, 59, NULL, 11, 1, 39.0, 125, 85, 67, NULL, NULL, NULL, 67.00, 173.80, 22.2, NULL, NULL, NULL, '2026-08-26 21:39:23', '2026-08-26 21:39:23', '2026-08-26 21:39:23'),
(35, 55, 60, NULL, 11, 1, 35.0, 120, 80, 72, NULL, NULL, NULL, 68.00, 178.00, 21.5, NULL, NULL, NULL, '2026-08-26 22:37:54', '2026-08-26 22:37:54', '2026-08-26 22:37:54'),
(36, 60, 61, NULL, 11, 1, 37.0, 125, 80, 70, NULL, NULL, NULL, 67.00, 178.00, 21.1, NULL, NULL, NULL, '2026-08-26 22:43:03', '2026-08-26 22:43:03', '2026-08-26 22:43:03');

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
,`visit_status` enum('pending','assigned','with_doctor','lab_test','lab_completed','prescribed','completed','cancelled')
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=159;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bills`
--
ALTER TABLE `bills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=200;

--
-- AUTO_INCREMENT for table `bill_items`
--
ALTER TABLE `bill_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=260;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `diseases`
--
ALTER TABLE `diseases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `lab_tests_catalog`
--
ALTER TABLE `lab_tests_catalog`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `lab_test_equipment`
--
ALTER TABLE `lab_test_equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `medical_equipment`
--
ALTER TABLE `medical_equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `medications_inventory`
--
ALTER TABLE `medications_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=96;

--
-- AUTO_INCREMENT for table `otc_sales`
--
ALTER TABLE `otc_sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `otc_sale_items`
--
ALTER TABLE `otc_sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `patient_documents`
--
ALTER TABLE `patient_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `prescription_items`
--
ALTER TABLE `prescription_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `procedures`
--
ALTER TABLE `procedures`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `procedures_catalog`
--
ALTER TABLE `procedures_catalog`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `receipts`
--
ALTER TABLE `receipts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `referrals`
--
ALTER TABLE `referrals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `service_categories`
--
ALTER TABLE `service_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=113;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `visits`
--
ALTER TABLE `visits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `vital_signs`
--
ALTER TABLE `vital_signs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

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
