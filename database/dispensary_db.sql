-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 21, 2026 at 09:59 PM
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
(1180, 1, 1, NULL, 'employee_added', 'Employee NASMA ISMAIL added with roles: audit', NULL, NULL, '2026-09-15 21:47:08', '2026-09-15 21:47:08'),
(1181, 1, 1, NULL, 'employee_updated', 'Employee ANGERITHA KIMARO MSANGI updated (Roles: laboratory)', NULL, NULL, '2026-09-15 21:54:58', '2026-09-15 21:54:58'),
(1182, 1, 1, NULL, 'patient_updated', 'Patient updated: JACKSON MYULA (ID: P-2026-01-0016) by System Admin', NULL, NULL, '2026-09-15 21:55:38', '2026-09-15 21:55:38'),
(1183, 1, 1, NULL, 'employee_updated', 'Employee NASMA ISMAIL updated (Primary role: audit, All roles: audit)', NULL, NULL, '2026-09-15 22:06:09', '2026-09-15 22:06:09'),
(1184, 1, 1, NULL, 'employee_updated', 'Employee NASMA ISMAIL updated (Primary role: reception, All roles: reception, audit)', NULL, NULL, '2026-09-15 22:06:57', '2026-09-15 22:06:57'),
(1185, 1, 1, NULL, 'employee_updated', 'Employee NASMA ISMAIL updated (Primary role: audit, All roles: audit)', NULL, NULL, '2026-09-15 22:07:40', '2026-09-15 22:07:40'),
(1186, 1, 1, NULL, 'employee_updated', 'Employee NASMA ISMAIL updated (Primary role: audit, All roles: audit)', NULL, NULL, '2026-09-15 22:10:59', '2026-09-15 22:10:59'),
(1187, 1, 1, NULL, 'employee_updated', 'Employee NASMA ISMAIL updated (Role: audit)', NULL, NULL, '2026-09-15 22:19:15', '2026-09-15 22:19:15'),
(1188, 1, 1, NULL, 'employee_updated', 'Employee NASMA ISMAIL updated (Role: cashier)', NULL, NULL, '2026-09-15 22:20:07', '2026-09-15 22:20:07'),
(1189, 1, 1, NULL, 'employee_added', 'Employee NYANSAEL NZILU added with roles: audit (Primary role: audit)', NULL, NULL, '2026-09-15 22:31:06', '2026-09-15 22:31:06'),
(1190, 1, 1, NULL, 'employee_updated', 'Employee NASMA ISMAIL updated (Role: audit)', NULL, NULL, '2026-09-15 22:34:29', '2026-09-15 22:34:29'),
(1191, 1, 1, NULL, 'employee_updated', 'Employee NASMA ISMAIL updated (Role: audit)', NULL, NULL, '2026-09-15 22:34:52', '2026-09-15 22:34:52'),
(1192, 1, 1, NULL, 'employee_updated', 'Employee NASMA ISMAIL updated (Role: laboratory)', NULL, NULL, '2026-09-15 22:35:46', '2026-09-15 22:35:46'),
(1193, 1, 1, NULL, 'employee_updated', 'Employee NASMA ISMAIL updated (Role: audit)', NULL, NULL, '2026-09-15 22:38:22', '2026-09-15 22:38:22'),
(1194, 1, 1, NULL, 'employee_updated', 'Employee NYANSAEL NZILU updated (Role: audit)', NULL, NULL, '2026-09-15 22:41:25', '2026-09-15 22:41:25'),
(1195, 1, 1, NULL, 'employee_added', 'Employee FLORA DANIEL added with roles: audit (Primary role: audit)', NULL, NULL, '2026-09-15 22:42:42', '2026-09-15 22:42:42'),
(1196, 1, 2, NULL, 'employee_added', 'Employee MICHAEL NJIRO added with roles: audit (Primary role: audit)', NULL, NULL, '2026-09-15 22:46:31', '2026-09-15 22:46:31'),
(1197, 1, 1, NULL, 'employee_added', 'Employee PETRO EMANUAL added with roles: audit (Primary role: audit)', NULL, NULL, '2026-09-15 22:48:10', '2026-09-15 22:48:10'),
(1198, 1, 2, NULL, 'employee_updated', 'Employee FLORA DANIEL updated (Role: audit)', NULL, NULL, '2026-09-15 22:49:10', '2026-09-15 22:49:10'),
(1199, 1, 2, NULL, 'employee_updated', 'Employee PETRO EMANUAL updated (Role: audit)', NULL, NULL, '2026-09-15 22:50:01', '2026-09-15 22:50:01'),
(1200, 1, 1, 47, 'referral_created', 'Patient referred externally: MARTHA KIMAMALA (#REF-20260916-0047-265) - To: MUHIMBILI', NULL, NULL, '2026-09-15 22:58:49', '2026-09-15 22:58:49'),
(1201, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-16 11:41:32', '2026-09-16 11:41:32'),
(1202, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-16 12:12:27', '2026-09-16 12:12:27'),
(1203, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-16 12:12:31', '2026-09-16 12:12:31'),
(1204, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-16 13:22:02', '2026-09-16 13:22:02'),
(1205, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-16 13:26:01', '2026-09-16 13:26:01'),
(1206, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-16 13:26:09', '2026-09-16 13:26:09'),
(1207, 45, 1, NULL, 'user_login', 'User logged in: NASMA ISMAIL (Mode: general_blue, Role: audit)', NULL, NULL, '2026-09-16 13:26:51', '2026-09-16 13:26:51'),
(1208, 45, 1, NULL, 'user_logout', 'User logged out: NASMA ISMAIL (Role: audit)', NULL, NULL, '2026-09-16 13:39:11', '2026-09-16 13:39:11'),
(1209, 45, 1, NULL, 'user_login', 'User logged in: NASMA ISMAIL (Mode: general_blue, Role: audit)', NULL, NULL, '2026-09-16 13:39:33', '2026-09-16 13:39:33'),
(1210, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-16 13:49:34', '2026-09-16 13:49:34'),
(1211, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-16 13:54:57', '2026-09-16 13:54:57'),
(1212, 45, 1, NULL, 'user_logout', 'User logged out: NASMA ISMAIL (Role: audit)', NULL, NULL, '2026-09-16 14:32:33', '2026-09-16 14:32:33'),
(1213, 45, 1, NULL, 'user_login', 'User logged in: NASMA ISMAIL (Mode: general_blue, Role: audit)', NULL, NULL, '2026-09-16 14:32:36', '2026-09-16 14:32:36'),
(1214, 45, 1, NULL, 'user_logout', 'User logged out: NASMA ISMAIL (Role: audit)', NULL, NULL, '2026-09-16 14:42:21', '2026-09-16 14:42:21'),
(1215, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-16 14:42:24', '2026-09-16 14:42:24'),
(1216, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-16 15:34:36', '2026-09-16 15:34:36'),
(1217, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-16 15:34:44', '2026-09-16 15:34:44'),
(1218, 1, 1, NULL, 'edit_expense', 'Edited expense: EXP-20260903-2839 | Amount: TSh 60,000 → TSh 70,000', '::1', NULL, '2026-09-16 22:08:32', '2026-09-16 22:08:32'),
(1219, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-17 13:21:06', '2026-09-17 13:21:06'),
(1220, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-17 14:22:25', '2026-09-17 14:22:25'),
(1221, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-17 14:22:28', '2026-09-17 14:22:28'),
(1222, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-17 14:22:51', '2026-09-17 14:22:51'),
(1223, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-17 14:22:59', '2026-09-17 14:22:59'),
(1224, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-17 14:28:22', '2026-09-17 14:28:22'),
(1225, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-17 14:28:26', '2026-09-17 14:28:26'),
(1226, 4, NULL, NULL, 'consultation_auto_completed', 'Consultation #VIS-20260910-3706 auto-completed (all bills paid, diagnosis exists)', NULL, NULL, '2026-09-17 14:28:30', '2026-09-17 14:28:30'),
(1227, 4, NULL, NULL, 'consultation_auto_completed', 'Consultation #VIS-20260910-8328 auto-completed (all bills paid, diagnosis exists)', NULL, NULL, '2026-09-17 14:28:30', '2026-09-17 14:28:30'),
(1228, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-17 14:29:21', '2026-09-17 14:29:21'),
(1229, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-17 14:29:27', '2026-09-17 14:29:27'),
(1230, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-17 14:35:21', '2026-09-17 14:35:21'),
(1231, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-17 14:35:27', '2026-09-17 14:35:27'),
(1232, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-17 14:35:57', '2026-09-17 14:35:57'),
(1233, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-17 14:36:01', '2026-09-17 14:36:01'),
(1234, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-17 14:36:38', '2026-09-17 14:36:38'),
(1235, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-17 14:36:48', '2026-09-17 14:36:48'),
(1236, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-17 15:15:30', '2026-09-17 15:15:30'),
(1237, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-17 15:15:40', '2026-09-17 15:15:40'),
(1238, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-17 15:15:57', '2026-09-17 15:15:57'),
(1239, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-17 15:16:07', '2026-09-17 15:16:07'),
(1240, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-17 15:20:01', '2026-09-17 15:20:01'),
(1241, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-17 15:20:05', '2026-09-17 15:20:05'),
(1242, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-17 15:23:47', '2026-09-17 15:23:47'),
(1243, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-17 15:23:54', '2026-09-17 15:23:54'),
(1244, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-17 15:31:58', '2026-09-17 15:31:58'),
(1245, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-17 15:32:01', '2026-09-17 15:32:01'),
(1246, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-17 15:37:15', '2026-09-17 15:37:15'),
(1247, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-17 15:37:22', '2026-09-17 15:37:22'),
(1248, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-17 17:36:06', '2026-09-17 17:36:06'),
(1249, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-17 17:36:42', '2026-09-17 17:36:42'),
(1250, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-17 17:36:50', '2026-09-17 17:36:50'),
(1251, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-17 17:38:50', '2026-09-17 17:38:50'),
(1252, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-17 17:38:59', '2026-09-17 17:38:59'),
(1253, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-17 17:39:04', '2026-09-17 17:39:04'),
(1254, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-17 17:39:13', '2026-09-17 17:39:13'),
(1255, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-17 17:40:04', '2026-09-17 17:40:04'),
(1256, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-17 17:40:07', '2026-09-17 17:40:07'),
(1257, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-17 19:32:18', '2026-09-17 19:32:18'),
(1258, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-17 19:32:24', '2026-09-17 19:32:24'),
(1259, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-17 20:45:46', '2026-09-17 20:45:46'),
(1260, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-17 20:45:51', '2026-09-17 20:45:51'),
(1261, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-17 20:46:40', '2026-09-17 20:46:40'),
(1262, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-17 20:46:44', '2026-09-17 20:46:44'),
(1263, 1, 1, NULL, 'bill_created', 'Consultation bill #BILL-CONS-20260917-0052-5784 - TSh 25,000 for JUDITH SOLOMONI', NULL, NULL, '2026-09-17 20:47:38', '2026-09-17 20:47:38'),
(1264, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-17 20:47:47', '2026-09-17 20:47:47'),
(1265, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-17 20:47:51', '2026-09-17 20:47:51'),
(1266, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-17 21:08:59', '2026-09-17 21:08:59'),
(1267, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-17 21:09:02', '2026-09-17 21:09:02'),
(1268, 1, 1, NULL, 'bill_created', 'Consultation bill #BILL-CONS-20260917-0061-8436 - TSh 25,000 for MUSSA MONGI MASNGI', NULL, NULL, '2026-09-17 21:09:23', '2026-09-17 21:09:23'),
(1269, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-17 21:12:45', '2026-09-17 21:12:45'),
(1270, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-17 21:12:48', '2026-09-17 21:12:48'),
(1271, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-17 21:42:37', '2026-09-17 21:42:37'),
(1272, 45, 1, NULL, 'user_login', 'User logged in: NASMA ISMAIL (Mode: general_blue, Role: audit)', NULL, NULL, '2026-09-17 21:42:44', '2026-09-17 21:42:44'),
(1273, 45, 1, NULL, 'user_logout', 'User logged out: NASMA ISMAIL (Role: audit)', NULL, NULL, '2026-09-17 22:33:45', '2026-09-17 22:33:45'),
(1274, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-17 22:33:52', '2026-09-17 22:33:52'),
(1275, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-17 22:38:53', '2026-09-17 22:38:53'),
(1276, 45, 1, NULL, 'user_login', 'User logged in: NASMA ISMAIL (Mode: general_blue, Role: audit)', NULL, NULL, '2026-09-17 22:38:58', '2026-09-17 22:38:58'),
(1277, 45, 1, NULL, 'user_logout', 'User logged out: NASMA ISMAIL (Role: audit)', NULL, NULL, '2026-09-17 22:53:40', '2026-09-17 22:53:40'),
(1278, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-17 22:53:51', '2026-09-17 22:53:51'),
(1279, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-17 22:55:02', '2026-09-17 22:55:02'),
(1280, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-17 22:55:06', '2026-09-17 22:55:06'),
(1281, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-17 22:55:10', '2026-09-17 22:55:10'),
(1282, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-17 22:55:15', '2026-09-17 22:55:15'),
(1283, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-17 22:55:43', '2026-09-17 22:55:43'),
(1284, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-17 22:55:47', '2026-09-17 22:55:47'),
(1285, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-17 22:56:43', '2026-09-17 22:56:43'),
(1286, 45, 1, NULL, 'user_login', 'User logged in: NASMA ISMAIL (Mode: general_blue, Role: audit)', NULL, NULL, '2026-09-17 22:56:49', '2026-09-17 22:56:49'),
(1287, 45, 1, NULL, 'user_logout', 'User logged out: NASMA ISMAIL (Role: audit)', NULL, NULL, '2026-09-17 23:04:23', '2026-09-17 23:04:23'),
(1288, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-17 23:04:31', '2026-09-17 23:04:31'),
(1289, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-17 23:06:39', '2026-09-17 23:06:39'),
(1290, 45, 1, NULL, 'user_login', 'User logged in: NASMA ISMAIL (Mode: general_blue, Role: audit)', NULL, NULL, '2026-09-17 23:06:45', '2026-09-17 23:06:45'),
(1291, 45, 1, NULL, 'user_logout', 'User logged out: NASMA ISMAIL (Role: audit)', NULL, NULL, '2026-09-17 23:12:33', '2026-09-17 23:12:33'),
(1292, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-17 23:12:36', '2026-09-17 23:12:36'),
(1293, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-17 23:16:09', '2026-09-17 23:16:09'),
(1294, 45, 1, NULL, 'user_login', 'User logged in: NASMA ISMAIL (Mode: general_blue, Role: audit)', NULL, NULL, '2026-09-17 23:16:15', '2026-09-17 23:16:15'),
(1295, 45, 1, NULL, 'user_logout', 'User logged out: NASMA ISMAIL (Role: audit)', NULL, NULL, '2026-09-17 23:24:29', '2026-09-17 23:24:29'),
(1296, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-17 23:24:34', '2026-09-17 23:24:34'),
(1297, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-18 13:24:28', '2026-09-18 13:24:28'),
(1298, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-18 13:32:41', '2026-09-18 13:32:41'),
(1299, 45, 1, NULL, 'user_login', 'User logged in: NASMA ISMAIL (Mode: general_blue, Role: audit)', NULL, NULL, '2026-09-18 13:32:47', '2026-09-18 13:32:47'),
(1300, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-18 14:41:38', '2026-09-18 14:41:38'),
(1301, 45, 1, NULL, 'user_logout', 'User logged out: NASMA ISMAIL (Role: audit)', NULL, NULL, '2026-09-18 14:44:04', '2026-09-18 14:44:04'),
(1302, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-18 14:44:30', '2026-09-18 14:44:30'),
(1303, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-18 14:50:27', '2026-09-18 14:50:27'),
(1304, 45, 1, NULL, 'user_login', 'User logged in: NASMA ISMAIL (Mode: general_blue, Role: audit)', NULL, NULL, '2026-09-18 14:50:31', '2026-09-18 14:50:31'),
(1305, 45, 1, NULL, 'user_logout', 'User logged out: NASMA ISMAIL (Role: audit)', NULL, NULL, '2026-09-18 14:52:11', '2026-09-18 14:52:11'),
(1306, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-18 14:52:23', '2026-09-18 14:52:23'),
(1307, 1, 1, NULL, 'delete_prescription_item', 'Deleted prescription item: Cetirizine 10mg (Qty: 70, Amount: TSh 10,500, Status: paid) from Rx #111 | Stock NOT returned (paid/dispensed) | Bill REDUCED: -TSh 10,500', '::1', NULL, '2026-09-18 15:44:07', '2026-09-18 15:44:07'),
(1308, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-18 16:06:38', '2026-09-18 16:06:38'),
(1309, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-18 16:06:58', '2026-09-18 16:06:58'),
(1310, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-18 16:11:10', '2026-09-18 16:11:10'),
(1311, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-18 16:11:21', '2026-09-18 16:11:21'),
(1312, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-18 16:17:49', '2026-09-18 16:17:49'),
(1313, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-18 16:17:54', '2026-09-18 16:17:54'),
(1314, 4, NULL, NULL, 'consultation_auto_completed', 'Consultation #VIS-20260917-6980 auto-completed (all bills paid, diagnosis exists)', NULL, NULL, '2026-09-18 16:17:58', '2026-09-18 16:17:58'),
(1315, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-18 16:18:10', '2026-09-18 16:18:10'),
(1316, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-18 16:18:20', '2026-09-18 16:18:20'),
(1317, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-18 16:18:46', '2026-09-18 16:18:46'),
(1318, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-18 16:19:00', '2026-09-18 16:19:00'),
(1319, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-18 16:20:08', '2026-09-18 16:20:08'),
(1320, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-18 16:20:15', '2026-09-18 16:20:15'),
(1321, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-18 16:44:05', '2026-09-18 16:44:05'),
(1322, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-18 16:44:15', '2026-09-18 16:44:15'),
(1323, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-18 18:09:06', '2026-09-18 18:09:06'),
(1324, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-18 18:09:16', '2026-09-18 18:09:16'),
(1325, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-18 18:18:46', '2026-09-18 18:18:46'),
(1326, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-18 18:18:51', '2026-09-18 18:18:51'),
(1327, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-18 18:42:09', '2026-09-18 18:42:09'),
(1328, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-18 18:42:17', '2026-09-18 18:42:17'),
(1329, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-18 18:45:19', '2026-09-18 18:45:19'),
(1330, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-18 18:45:26', '2026-09-18 18:45:26'),
(1331, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-18 18:55:19', '2026-09-18 18:55:19'),
(1332, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-18 18:55:32', '2026-09-18 18:55:32'),
(1333, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-18 18:57:58', '2026-09-18 18:57:58'),
(1334, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-18 18:58:05', '2026-09-18 18:58:05'),
(1335, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-18 19:02:09', '2026-09-18 19:02:09'),
(1336, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-18 19:02:18', '2026-09-18 19:02:18'),
(1337, 12, 1, NULL, 'edit_patient', 'Edited patient: SAMSON MYULA (ID: P-2026-01-0017)', '::1', NULL, '2026-09-18 19:16:17', '2026-09-18 19:16:17'),
(1338, 12, 1, NULL, 'edit_patient', 'Edited patient: JACKSON MYULA (ID: P-2026-01-0016)', '::1', NULL, '2026-09-18 19:19:37', '2026-09-18 19:19:37'),
(1339, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-18 19:21:55', '2026-09-18 19:21:55'),
(1340, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-18 19:22:00', '2026-09-18 19:22:00'),
(1341, 1, 1, NULL, 'edit_patient', 'Admin edited patient: SAMSON  PIUS MYULA (ID: P-2026-01-0017)', '::1', NULL, '2026-09-18 21:43:16', '2026-09-18 21:43:16'),
(1342, 1, 1, NULL, 'edit_patient', 'Admin edited patient: SAMSON  PIUS MYULA (ID: P-2026-01-0017)', '::1', NULL, '2026-09-18 21:49:29', '2026-09-18 21:49:29'),
(1343, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-18 21:56:27', '2026-09-18 21:56:27'),
(1344, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-18 21:56:36', '2026-09-18 21:56:36'),
(1345, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-18 21:57:07', '2026-09-18 21:57:07'),
(1346, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-18 21:57:11', '2026-09-18 21:57:11'),
(1347, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-18 21:58:18', '2026-09-18 21:58:18'),
(1348, 45, 1, NULL, 'user_login', 'User logged in: NASMA ISMAIL (Mode: general_blue, Role: audit)', NULL, NULL, '2026-09-18 21:58:30', '2026-09-18 21:58:30'),
(1349, 45, 1, NULL, 'user_logout', 'User logged out: NASMA ISMAIL (Role: audit)', NULL, NULL, '2026-09-18 21:58:51', '2026-09-18 21:58:51'),
(1350, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO MSANGI (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-18 21:58:57', '2026-09-18 21:58:57'),
(1351, 13, 1, NULL, 'lab_tests_bulk_started', 'Started 17 lab test(s) in bulk (global)', NULL, NULL, '2026-09-18 21:59:05', '2026-09-18 21:59:05'),
(1352, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO MSANGI (Role: laboratory)', NULL, NULL, '2026-09-18 22:02:41', '2026-09-18 22:02:41'),
(1353, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-18 22:02:45', '2026-09-18 22:02:45'),
(1354, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-18 22:05:26', '2026-09-18 22:05:26'),
(1355, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-18 22:05:35', '2026-09-18 22:05:35'),
(1356, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-18 22:06:59', '2026-09-18 22:06:59'),
(1357, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-18 22:07:08', '2026-09-18 22:07:08'),
(1358, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-18 22:07:15', '2026-09-18 22:07:15'),
(1359, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-18 22:07:23', '2026-09-18 22:07:23'),
(1360, 1, 1, NULL, 'edit_otc_sale', 'Edited OTC sale: OTC-20260918-9565 | Total: TSh 35,000 → TSh 40,000', '::1', NULL, '2026-09-18 23:38:10', '2026-09-18 23:38:10'),
(1361, 1, 1, NULL, 'edit_lab_test', 'Edited lab test: CD4 Count (ID: 204) | Price: TSh 30,000 → TSh 37,000 | Bill auto-updated', '::1', NULL, '2026-09-18 23:44:35', '2026-09-18 23:44:35'),
(1362, 1, 1, NULL, 'edit_lab_test', 'Edited lab test: CD4 Count (ID: 204) | Price: TSh 37,000 → TSh 107,000 | Bill auto-updated', '::1', NULL, '2026-09-18 23:45:23', '2026-09-18 23:45:23'),
(1363, 1, 1, NULL, 'edit_lab_test', 'Edited lab test: CD4 Count (ID: 204) | Price: TSh 107,000 → TSh 30,000 | Bill auto-updated', '::1', NULL, '2026-09-18 23:45:41', '2026-09-18 23:45:41'),
(1364, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-18 23:57:29', '2026-09-18 23:57:29'),
(1365, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-18 23:57:35', '2026-09-18 23:57:35'),
(1366, 4, NULL, NULL, 'consultation_auto_completed', 'Consultation #VIS-20260918-0063 auto-completed (all bills paid, diagnosis exists)', NULL, NULL, '2026-09-18 23:57:39', '2026-09-18 23:57:39'),
(1367, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-18 23:58:18', '2026-09-18 23:58:18'),
(1368, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-18 23:58:24', '2026-09-18 23:58:24'),
(1369, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-19 10:04:38', '2026-09-19 10:04:38'),
(1370, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-19 12:49:53', '2026-09-19 12:49:53'),
(1371, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-19 12:49:58', '2026-09-19 12:49:58'),
(1372, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-19 12:50:21', '2026-09-19 12:50:21'),
(1373, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-19 12:50:28', '2026-09-19 12:50:28'),
(1374, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-19 13:01:21', '2026-09-19 13:01:21'),
(1375, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-19 13:01:31', '2026-09-19 13:01:31'),
(1376, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-19 13:01:44', '2026-09-19 13:01:44'),
(1377, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-19 13:01:49', '2026-09-19 13:01:49'),
(1378, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-19 13:02:37', '2026-09-19 13:02:37'),
(1379, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-19 13:02:56', '2026-09-19 13:02:56'),
(1380, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-19 13:03:34', '2026-09-19 13:03:34'),
(1381, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-19 13:03:50', '2026-09-19 13:03:50'),
(1382, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-19 13:04:10', '2026-09-19 13:04:10'),
(1383, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-19 13:04:37', '2026-09-19 13:04:37'),
(1384, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-19 13:10:04', '2026-09-19 13:10:04'),
(1385, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-19 13:10:09', '2026-09-19 13:10:09'),
(1386, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-19 13:23:38', '2026-09-19 13:23:38'),
(1387, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-19 13:23:47', '2026-09-19 13:23:47'),
(1388, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-19 13:23:54', '2026-09-19 13:23:54'),
(1389, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-19 13:24:03', '2026-09-19 13:24:03'),
(1390, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-19 13:51:19', '2026-09-19 13:51:19'),
(1391, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-19 13:52:34', '2026-09-19 13:52:34'),
(1392, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-19 13:53:24', '2026-09-19 13:53:24'),
(1393, 45, 1, NULL, 'user_login', 'User logged in: NASMA ISMAIL (Mode: general_blue, Role: audit)', NULL, NULL, '2026-09-19 13:53:35', '2026-09-19 13:53:35'),
(1394, 45, 1, NULL, 'user_logout', 'User logged out: NASMA ISMAIL (Role: audit)', NULL, NULL, '2026-09-19 14:32:34', '2026-09-19 14:32:34'),
(1395, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-19 14:32:39', '2026-09-19 14:32:39'),
(1396, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-19 14:35:07', '2026-09-19 14:35:07'),
(1397, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-19 14:35:16', '2026-09-19 14:35:16'),
(1398, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-19 14:36:33', '2026-09-19 14:36:33'),
(1399, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO MSANGI (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-19 14:36:39', '2026-09-19 14:36:39'),
(1400, 13, 1, NULL, 'lab_tests_bulk_started', 'Started 8 lab test(s) in bulk (global)', NULL, NULL, '2026-09-19 14:37:00', '2026-09-19 14:37:00'),
(1401, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO MSANGI (Role: laboratory)', NULL, NULL, '2026-09-19 14:38:57', '2026-09-19 14:38:57'),
(1402, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-19 14:39:00', '2026-09-19 14:39:00'),
(1403, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-19 14:40:51', '2026-09-19 14:40:51'),
(1404, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-19 14:41:01', '2026-09-19 14:41:01'),
(1405, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-19 14:43:47', '2026-09-19 14:43:47'),
(1406, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-19 14:43:57', '2026-09-19 14:43:57'),
(1407, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-19 14:50:07', '2026-09-19 14:50:07'),
(1408, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-19 14:50:17', '2026-09-19 14:50:17'),
(1409, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-19 14:58:59', '2026-09-19 14:58:59'),
(1410, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-19 14:59:02', '2026-09-19 14:59:02'),
(1411, 1, 1, 63, 'bill_item_deleted', 'Deleted Equipment: \'Suture Kit\' (Bill: BILL-CONS-20260919-0063-3474) | Amount: TSh 10,000 | Bill recalculated: Total → TSh 253,000, Balance → TSh 93,000', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-19 17:09:40', '2026-09-19 17:09:40'),
(1412, 1, 1, 63, 'bill_item_deleted', 'Deleted Procedure: \'Cryotherapy\' (Bill: BILL-CONS-20260919-0063-3474) | Amount: TSh 20,000 | Bill recalculated: Total → TSh 233,000, Balance → TSh 73,000', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-19 17:09:58', '2026-09-19 17:09:58'),
(1413, 1, 1, 63, 'bill_item_deleted', 'Deleted Equipment: \'Surgical Scalpel Set\' (Bill: BILL-CONS-20260919-0063-3474) | Amount: TSh 5,000 | Bill recalculated: Total → TSh 228,000, Balance → TSh 68,000', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-19 17:15:18', '2026-09-19 17:15:18'),
(1414, 1, 1, NULL, 'delete_otc_sale', 'Deleted OTC Sale: OTC-20260919-4321 | Customer: Walk-in Customer | Amount: TSh 87,000 | Items: 5 | Status: paid', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-19 17:24:16', '2026-09-19 17:24:16'),
(1415, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-19 17:28:46', '2026-09-19 17:28:46'),
(1416, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-19 17:28:53', '2026-09-19 17:28:53'),
(1417, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-19 17:28:58', '2026-09-19 17:28:58'),
(1418, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-19 17:29:03', '2026-09-19 17:29:03'),
(1419, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-19 17:31:41', '2026-09-19 17:31:41'),
(1420, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-19 17:31:50', '2026-09-19 17:31:50'),
(1421, 1, 1, NULL, 'delete_otc_item', 'Deleted OTC Item: \'Amoxicillin 500mg\' from Sale OTC-20260919-7626 | Remaining items: 9', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-19 17:37:12', '2026-09-19 17:37:12'),
(1422, 1, 1, NULL, 'delete_otc_item', 'Deleted OTC Item: \'Metronidazole 400mg\' from Sale OTC-20260919-7626 | Remaining items: 8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-19 17:37:48', '2026-09-19 17:37:48'),
(1423, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-19 19:21:42', '2026-09-19 19:21:42'),
(1424, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-19 19:24:52', '2026-09-19 19:24:52'),
(1425, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-19 19:24:58', '2026-09-19 19:24:58'),
(1426, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-19 19:25:41', '2026-09-19 19:25:41'),
(1427, 14, 1, NULL, 'user_login', 'User logged in: Peter Lema (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-19 19:25:47', '2026-09-19 19:25:47'),
(1428, 14, 1, NULL, 'lab_tests_bulk_started', 'Started 5 lab test(s) in bulk (global)', NULL, NULL, '2026-09-19 19:25:54', '2026-09-19 19:25:54'),
(1429, 14, 1, NULL, 'user_logout', 'User logged out: Peter Lema (Role: laboratory)', NULL, NULL, '2026-09-19 19:27:30', '2026-09-19 19:27:30'),
(1430, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-19 19:27:33', '2026-09-19 19:27:33'),
(1431, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-19 19:29:26', '2026-09-19 19:29:26'),
(1432, 8, 1, NULL, 'user_login', 'User logged in: Mary John (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-19 19:29:31', '2026-09-19 19:29:31'),
(1433, 8, 1, NULL, 'user_logout', 'User logged out: Mary John (Role: pharmacy)', NULL, NULL, '2026-09-19 19:29:58', '2026-09-19 19:29:58'),
(1434, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-19 19:30:09', '2026-09-19 19:30:09'),
(1435, 1, 1, 61, 'bill_item_deleted', 'Deleted equipment: \'SINDANO\' from Bill #BILL-CONS-20260919-0061-5023. Bill recalculated.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-19 19:32:21', '2026-09-19 19:32:21'),
(1436, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-19 19:43:21', '2026-09-19 19:43:21'),
(1437, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-19 19:43:24', '2026-09-19 19:43:24'),
(1438, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-19 19:51:57', '2026-09-19 19:51:57'),
(1439, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-19 19:52:02', '2026-09-19 19:52:02'),
(1440, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-19 19:53:08', '2026-09-19 19:53:08'),
(1441, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-19 19:53:13', '2026-09-19 19:53:13'),
(1442, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-19 20:03:11', '2026-09-19 20:03:11'),
(1443, 5, 1, NULL, 'user_login', 'User logged in: Dr. Grace Peter (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-19 20:03:18', '2026-09-19 20:03:18'),
(1444, 5, 1, NULL, 'user_logout', 'User logged out: Dr. Grace Peter (Role: doctor)', NULL, NULL, '2026-09-19 20:03:26', '2026-09-19 20:03:26'),
(1445, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-19 20:03:38', '2026-09-19 20:03:38'),
(1446, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-19 20:04:20', '2026-09-19 20:04:20'),
(1447, 5, 1, NULL, 'user_login', 'User logged in: Dr. Grace Peter (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-19 20:04:24', '2026-09-19 20:04:24'),
(1448, 5, 1, NULL, 'user_logout', 'User logged out: Dr. Grace Peter (Role: doctor)', NULL, NULL, '2026-09-19 20:05:02', '2026-09-19 20:05:02'),
(1449, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-19 20:05:05', '2026-09-19 20:05:05'),
(1450, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-19 20:06:12', '2026-09-19 20:06:12'),
(1451, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO MSANGI (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-19 20:06:19', '2026-09-19 20:06:19'),
(1452, 13, 1, NULL, 'lab_tests_bulk_started', 'Started 18 lab test(s) in bulk (global)', NULL, NULL, '2026-09-19 20:06:27', '2026-09-19 20:06:27'),
(1453, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO MSANGI (Role: laboratory)', NULL, NULL, '2026-09-19 20:08:41', '2026-09-19 20:08:41'),
(1454, 15, 1, NULL, 'user_login', 'User logged in: Sarah Mwamba (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-19 20:08:45', '2026-09-19 20:08:45'),
(1455, 15, 1, NULL, 'user_logout', 'User logged out: Sarah Mwamba (Role: laboratory)', NULL, NULL, '2026-09-19 20:10:18', '2026-09-19 20:10:18'),
(1456, 5, 1, NULL, 'user_login', 'User logged in: Dr. Grace Peter (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-19 20:10:21', '2026-09-19 20:10:21'),
(1457, 5, 1, NULL, 'user_logout', 'User logged out: Dr. Grace Peter (Role: doctor)', NULL, NULL, '2026-09-19 20:12:25', '2026-09-19 20:12:25'),
(1458, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-19 20:12:28', '2026-09-19 20:12:28'),
(1459, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-19 20:19:49', '2026-09-19 20:19:49'),
(1460, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-19 20:19:59', '2026-09-19 20:19:59'),
(1461, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-19 20:26:05', '2026-09-19 20:26:05'),
(1462, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-19 20:26:20', '2026-09-19 20:26:20'),
(1463, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-19 20:26:29', '2026-09-19 20:26:29'),
(1464, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-19 20:26:37', '2026-09-19 20:26:37'),
(1465, 1, 1, 61, 'edit_consultation', 'Edited consultation #VIS-20260919-5515: No significant changes', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-19 20:44:51', '2026-09-19 20:44:51'),
(1466, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-19 21:08:18', '2026-09-19 21:08:18'),
(1467, 5, 1, NULL, 'user_login', 'User logged in: Dr. Grace Peter (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-19 21:08:24', '2026-09-19 21:08:24'),
(1468, 5, 1, NULL, 'user_logout', 'User logged out: Dr. Grace Peter (Role: doctor)', NULL, NULL, '2026-09-19 21:09:00', '2026-09-19 21:09:00'),
(1469, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-19 21:09:03', '2026-09-19 21:09:03'),
(1470, 4, NULL, NULL, 'consultation_auto_completed', 'Consultation #VIS-20260919-9347 auto-completed (all bills paid, diagnosis exists)', NULL, NULL, '2026-09-19 21:09:07', '2026-09-19 21:09:07'),
(1471, 4, NULL, NULL, 'consultation_auto_completed', 'Consultation #VIS-20260919-1387 auto-completed (all bills paid, diagnosis exists)', NULL, NULL, '2026-09-19 21:09:07', '2026-09-19 21:09:07'),
(1472, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-19 21:36:11', '2026-09-19 21:36:11'),
(1473, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-19 21:36:27', '2026-09-19 21:36:27'),
(1474, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-19 21:38:02', '2026-09-19 21:38:02'),
(1475, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-19 21:38:06', '2026-09-19 21:38:06'),
(1476, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-19 21:38:35', '2026-09-19 21:38:35'),
(1477, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO MSANGI (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-19 21:38:49', '2026-09-19 21:38:49'),
(1478, 13, 1, NULL, 'lab_tests_bulk_started', 'Started 7 lab test(s) in bulk (global)', NULL, NULL, '2026-09-19 21:38:56', '2026-09-19 21:38:56'),
(1479, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO MSANGI (Role: laboratory)', NULL, NULL, '2026-09-19 21:40:10', '2026-09-19 21:40:10'),
(1480, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-19 21:40:18', '2026-09-19 21:40:18'),
(1481, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-19 21:42:00', '2026-09-19 21:42:00'),
(1482, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-19 21:42:24', '2026-09-19 21:42:24'),
(1483, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-19 21:44:12', '2026-09-19 21:44:12'),
(1484, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-19 21:44:24', '2026-09-19 21:44:24'),
(1485, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-19 21:45:26', '2026-09-19 21:45:26'),
(1486, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-19 21:45:38', '2026-09-19 21:45:38'),
(1487, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-19 21:53:39', '2026-09-19 21:53:39'),
(1488, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-19 21:53:42', '2026-09-19 21:53:42'),
(1489, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-20 13:23:02', '2026-09-20 13:23:02'),
(1490, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-20 13:23:12', '2026-09-20 13:23:12'),
(1491, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-20 13:58:30', '2026-09-20 13:58:30'),
(1492, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-20 13:58:40', '2026-09-20 13:58:40'),
(1493, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-20 15:16:57', '2026-09-20 15:16:57'),
(1494, 45, 1, NULL, 'user_login', 'User logged in: NASMA ISMAIL (Mode: general_blue, Role: audit)', NULL, NULL, '2026-09-20 15:18:07', '2026-09-20 15:18:07'),
(1495, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-20 15:58:19', '2026-09-20 15:58:19');
INSERT INTO `activity_logs` (`id`, `user_id`, `branch_id`, `patient_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`, `updated_at`) VALUES
(1496, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-20 15:58:34', '2026-09-20 15:58:34'),
(1497, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-20 15:59:25', '2026-09-20 15:59:25'),
(1498, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-20 15:59:29', '2026-09-20 15:59:29'),
(1499, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-20 15:59:57', '2026-09-20 15:59:57'),
(1500, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-20 16:00:05', '2026-09-20 16:00:05'),
(1501, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-20 16:00:21', '2026-09-20 16:00:21'),
(1502, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-20 16:00:27', '2026-09-20 16:00:27'),
(1503, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-20 16:01:30', '2026-09-20 16:01:30'),
(1504, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-20 16:01:37', '2026-09-20 16:01:37'),
(1505, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-20 16:02:16', '2026-09-20 16:02:16'),
(1506, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-20 16:02:20', '2026-09-20 16:02:20'),
(1507, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-20 16:03:19', '2026-09-20 16:03:19'),
(1508, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-20 16:03:36', '2026-09-20 16:03:36'),
(1509, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-20 16:04:44', '2026-09-20 16:04:44'),
(1510, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-20 16:04:48', '2026-09-20 16:04:48'),
(1511, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-20 16:22:40', '2026-09-20 16:22:40'),
(1512, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-20 16:22:48', '2026-09-20 16:22:48'),
(1513, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-20 16:34:46', '2026-09-20 16:34:46'),
(1514, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-20 16:35:06', '2026-09-20 16:35:06'),
(1515, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-20 16:45:39', '2026-09-20 16:45:39'),
(1516, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-20 16:45:54', '2026-09-20 16:45:54'),
(1517, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-20 16:46:44', '2026-09-20 16:46:44'),
(1518, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-20 16:46:48', '2026-09-20 16:46:48'),
(1519, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-20 16:50:06', '2026-09-20 16:50:06'),
(1520, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-20 16:50:14', '2026-09-20 16:50:14'),
(1521, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-20 16:50:37', '2026-09-20 16:50:37'),
(1522, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-20 16:51:19', '2026-09-20 16:51:19'),
(1523, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-20 16:51:57', '2026-09-20 16:51:57'),
(1524, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-20 16:52:07', '2026-09-20 16:52:07'),
(1525, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-20 16:54:07', '2026-09-20 16:54:07'),
(1526, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-20 16:54:11', '2026-09-20 16:54:11'),
(1527, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-20 18:04:32', '2026-09-20 18:04:32'),
(1528, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-20 18:04:39', '2026-09-20 18:04:39'),
(1529, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-20 19:00:32', '2026-09-20 19:00:32'),
(1530, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-20 19:00:38', '2026-09-20 19:00:38'),
(1531, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-20 19:26:02', '2026-09-20 19:26:02'),
(1532, 5, 1, NULL, 'user_login', 'User logged in: Dr. Grace Peter (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-20 19:26:11', '2026-09-20 19:26:11'),
(1533, 5, 1, NULL, 'user_logout', 'User logged out: Dr. Grace Peter (Role: doctor)', NULL, NULL, '2026-09-20 19:27:06', '2026-09-20 19:27:06'),
(1534, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-20 19:27:11', '2026-09-20 19:27:11'),
(1535, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-20 19:28:11', '2026-09-20 19:28:11'),
(1536, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO MSANGI (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-20 19:28:39', '2026-09-20 19:28:39'),
(1537, 13, 1, NULL, 'lab_tests_bulk_started', 'Started 30 lab test(s) in bulk (global)', NULL, NULL, '2026-09-20 19:28:45', '2026-09-20 19:28:45'),
(1538, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO MSANGI (Role: laboratory)', NULL, NULL, '2026-09-20 19:30:20', '2026-09-20 19:30:20'),
(1539, 14, 1, NULL, 'user_login', 'User logged in: Peter Lema (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-20 19:30:24', '2026-09-20 19:30:24'),
(1540, 14, 1, NULL, 'user_logout', 'User logged out: Peter Lema (Role: laboratory)', NULL, NULL, '2026-09-20 19:31:26', '2026-09-20 19:31:26'),
(1541, 15, 1, NULL, 'user_login', 'User logged in: Sarah Mwamba (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-20 19:31:31', '2026-09-20 19:31:31'),
(1542, 15, 1, NULL, 'user_logout', 'User logged out: Sarah Mwamba (Role: laboratory)', NULL, NULL, '2026-09-20 19:32:29', '2026-09-20 19:32:29'),
(1543, 5, 1, NULL, 'user_login', 'User logged in: Dr. Grace Peter (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-20 19:32:32', '2026-09-20 19:32:32'),
(1544, 5, 1, NULL, 'user_logout', 'User logged out: Dr. Grace Peter (Role: doctor)', NULL, NULL, '2026-09-20 19:33:59', '2026-09-20 19:33:59'),
(1545, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-20 19:34:03', '2026-09-20 19:34:03'),
(1546, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-20 19:35:22', '2026-09-20 19:35:22'),
(1547, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-20 19:35:27', '2026-09-20 19:35:27'),
(1548, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-20 19:36:38', '2026-09-20 19:36:38'),
(1549, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-20 19:36:49', '2026-09-20 19:36:49'),
(1550, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-20 20:27:16', '2026-09-20 20:27:16'),
(1551, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-20 20:27:19', '2026-09-20 20:27:19'),
(1552, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-20 20:27:31', '2026-09-20 20:27:31'),
(1553, 5, 1, NULL, 'user_login', 'User logged in: Dr. Grace Peter (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-20 20:27:35', '2026-09-20 20:27:35'),
(1554, 5, 1, NULL, 'user_logout', 'User logged out: Dr. Grace Peter (Role: doctor)', NULL, NULL, '2026-09-20 20:29:07', '2026-09-20 20:29:07'),
(1555, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-20 20:29:16', '2026-09-20 20:29:16'),
(1556, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-20 20:29:51', '2026-09-20 20:29:51'),
(1557, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-20 20:29:57', '2026-09-20 20:29:57'),
(1558, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-20 20:30:28', '2026-09-20 20:30:28'),
(1559, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-20 20:30:48', '2026-09-20 20:30:48'),
(1560, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-20 20:33:51', '2026-09-20 20:33:51'),
(1561, 5, 1, NULL, 'user_login', 'User logged in: Dr. Grace Peter (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-20 20:33:55', '2026-09-20 20:33:55'),
(1562, 5, 1, NULL, 'user_logout', 'User logged out: Dr. Grace Peter (Role: doctor)', NULL, NULL, '2026-09-20 21:04:08', '2026-09-20 21:04:08'),
(1563, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-20 21:04:26', '2026-09-20 21:04:26'),
(1564, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-20 21:05:15', '2026-09-20 21:05:15'),
(1565, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-20 21:05:18', '2026-09-20 21:05:18'),
(1566, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-20 21:06:51', '2026-09-20 21:06:51'),
(1567, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-20 21:07:23', '2026-09-20 21:07:23'),
(1568, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-20 21:11:18', '2026-09-20 21:11:18'),
(1569, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-20 21:11:34', '2026-09-20 21:11:34'),
(1570, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-20 21:55:47', '2026-09-20 21:55:47'),
(1571, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-20 21:55:58', '2026-09-20 21:55:58'),
(1572, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-20 22:06:05', '2026-09-20 22:06:05'),
(1573, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-20 22:06:09', '2026-09-20 22:06:09'),
(1574, 1, 1, NULL, 'delete_bill_item', 'Deleted bill item: Minor Surgery - Excision', '::1', NULL, '2026-09-20 23:56:33', '2026-09-20 23:56:33'),
(1575, 8, 1, NULL, 'user_login', 'User logged in: Mary John (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-21 10:11:41', '2026-09-21 10:11:41'),
(1576, 8, 1, NULL, 'user_logout', 'User logged out: Mary John (Role: pharmacy)', NULL, NULL, '2026-09-21 12:35:55', '2026-09-21 12:35:55'),
(1577, 45, 1, NULL, 'user_login', 'User logged in: NASMA ISMAIL (Mode: general_blue, Role: audit)', NULL, NULL, '2026-09-21 12:36:04', '2026-09-21 12:36:04'),
(1578, 1, 1, 62, 'bill_item_deleted', 'Deleted Procedure: \'Cryotherapy\' from Bill #BILL-CONS-20260920-0062-3069', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-21 13:37:49', '2026-09-21 13:37:49'),
(1579, 1, 1, 62, 'bill_item_deleted', 'Deleted Procedure: \'Incision and Drainage\' from Bill #BILL-CONS-20260920-0062-3069', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-21 13:38:40', '2026-09-21 13:38:40'),
(1580, 45, 1, NULL, 'user_logout', 'User logged out: NASMA ISMAIL (Role: audit)', NULL, NULL, '2026-09-21 14:26:20', '2026-09-21 14:26:20'),
(1581, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-21 14:26:28', '2026-09-21 14:26:28'),
(1582, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-21 14:27:38', '2026-09-21 14:27:38'),
(1583, 45, 1, NULL, 'user_login', 'User logged in: NASMA ISMAIL (Mode: general_blue, Role: audit)', NULL, NULL, '2026-09-21 14:50:39', '2026-09-21 14:50:39'),
(1584, 45, 1, NULL, 'edit_patient', 'Audit edited patient: SAMSON  PIUS MYULA (ID: P-2026-01-0017)', '::1', NULL, '2026-09-21 14:53:46', '2026-09-21 14:53:46'),
(1585, 1, 1, NULL, 'edit_lab_test', 'Edited lab test: Blood Glucose (Random) (ID: 272) | Bill auto-updated', '::1', NULL, '2026-09-21 15:16:48', '2026-09-21 15:16:48'),
(1586, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-21 15:35:27', '2026-09-21 15:35:27'),
(1587, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-21 15:35:37', '2026-09-21 15:35:37'),
(1588, 45, 1, NULL, 'user_logout', 'User logged out: NASMA ISMAIL (Role: audit)', NULL, NULL, '2026-09-21 15:37:22', '2026-09-21 15:37:22'),
(1589, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-21 15:37:42', '2026-09-21 15:37:42'),
(1590, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-21 15:39:20', '2026-09-21 15:39:20'),
(1591, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-21 15:39:30', '2026-09-21 15:39:30'),
(1592, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-21 15:41:49', '2026-09-21 15:41:49'),
(1593, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-21 15:41:57', '2026-09-21 15:41:57'),
(1594, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-21 15:42:44', '2026-09-21 15:42:44'),
(1595, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-21 15:42:57', '2026-09-21 15:42:57'),
(1596, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-21 15:44:25', '2026-09-21 15:44:25'),
(1597, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-21 15:44:30', '2026-09-21 15:44:30'),
(1598, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-21 16:52:18', '2026-09-21 16:52:18'),
(1599, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-21 16:52:22', '2026-09-21 16:52:22'),
(1600, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-21 16:52:32', '2026-09-21 16:52:32'),
(1601, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-21 16:52:37', '2026-09-21 16:52:37'),
(1602, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-21 18:02:36', '2026-09-21 18:02:36'),
(1603, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-21 18:02:40', '2026-09-21 18:02:40'),
(1604, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-21 18:09:30', '2026-09-21 18:09:30'),
(1605, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-21 18:09:35', '2026-09-21 18:09:35'),
(1606, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-21 18:10:58', '2026-09-21 18:10:58'),
(1607, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-21 18:11:17', '2026-09-21 18:11:17'),
(1608, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-21 18:12:59', '2026-09-21 18:12:59'),
(1609, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO MSANGI (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-21 18:13:03', '2026-09-21 18:13:03'),
(1610, 13, 1, NULL, 'lab_tests_bulk_started', 'Started 15 lab test(s) in bulk (global)', NULL, NULL, '2026-09-21 18:13:11', '2026-09-21 18:13:11'),
(1611, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO MSANGI (Role: laboratory)', NULL, NULL, '2026-09-21 18:15:13', '2026-09-21 18:15:13'),
(1612, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-21 18:15:16', '2026-09-21 18:15:16'),
(1613, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-21 18:20:25', '2026-09-21 18:20:25'),
(1614, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-21 18:20:32', '2026-09-21 18:20:32'),
(1615, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-21 18:21:43', '2026-09-21 18:21:43'),
(1616, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-21 18:21:57', '2026-09-21 18:21:57'),
(1617, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-21 18:24:12', '2026-09-21 18:24:12'),
(1618, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-21 18:24:15', '2026-09-21 18:24:15'),
(1619, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-21 18:25:47', '2026-09-21 18:25:47'),
(1620, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-21 18:25:52', '2026-09-21 18:25:52'),
(1621, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-21 18:26:15', '2026-09-21 18:26:15'),
(1622, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-21 18:26:22', '2026-09-21 18:26:22'),
(1623, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-21 18:29:00', '2026-09-21 18:29:00'),
(1624, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-21 18:29:05', '2026-09-21 18:29:05'),
(1625, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-21 18:29:23', '2026-09-21 18:29:23'),
(1626, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-21 18:29:26', '2026-09-21 18:29:26'),
(1627, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-21 18:30:35', '2026-09-21 18:30:35'),
(1628, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-21 18:30:45', '2026-09-21 18:30:45'),
(1629, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-21 18:34:42', '2026-09-21 18:34:42'),
(1630, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-21 18:34:47', '2026-09-21 18:34:47'),
(1631, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-21 18:38:10', '2026-09-21 18:38:10'),
(1632, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-21 18:38:16', '2026-09-21 18:38:16'),
(1633, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-21 18:52:45', '2026-09-21 18:52:45'),
(1634, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-21 18:52:50', '2026-09-21 18:52:50'),
(1635, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-21 18:53:16', '2026-09-21 18:53:16'),
(1636, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-21 18:53:22', '2026-09-21 18:53:22'),
(1637, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-21 18:54:06', '2026-09-21 18:54:06'),
(1638, 5, 1, NULL, 'user_login', 'User logged in: Dr. Grace Peter (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-21 18:54:09', '2026-09-21 18:54:09'),
(1639, 5, 1, NULL, 'user_logout', 'User logged out: Dr. Grace Peter (Role: doctor)', NULL, NULL, '2026-09-21 18:54:25', '2026-09-21 18:54:25'),
(1640, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-21 18:54:29', '2026-09-21 18:54:29'),
(1641, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-21 18:55:01', '2026-09-21 18:55:01'),
(1642, 14, 1, NULL, 'user_login', 'User logged in: Peter Lema (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-21 18:55:08', '2026-09-21 18:55:08'),
(1643, 14, 1, NULL, 'lab_tests_bulk_started', 'Started 4 lab test(s) in bulk (global)', NULL, NULL, '2026-09-21 18:55:14', '2026-09-21 18:55:14'),
(1644, 14, 1, NULL, 'user_logout', 'User logged out: Peter Lema (Role: laboratory)', NULL, NULL, '2026-09-21 18:56:07', '2026-09-21 18:56:07'),
(1645, 5, 1, NULL, 'user_login', 'User logged in: Dr. Grace Peter (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-21 18:56:09', '2026-09-21 18:56:09'),
(1646, 5, 1, NULL, 'user_logout', 'User logged out: Dr. Grace Peter (Role: doctor)', NULL, NULL, '2026-09-21 18:57:25', '2026-09-21 18:57:25'),
(1647, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-21 18:57:28', '2026-09-21 18:57:28'),
(1648, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-21 18:58:57', '2026-09-21 18:58:57'),
(1649, 14, 1, NULL, 'user_login', 'User logged in: Peter Lema (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-21 18:59:00', '2026-09-21 18:59:00'),
(1650, 14, 1, NULL, 'lab_tests_bulk_started', 'Started 3 lab test(s) in bulk (global)', NULL, NULL, '2026-09-21 18:59:06', '2026-09-21 18:59:06'),
(1651, 14, 1, NULL, 'user_logout', 'User logged out: Peter Lema (Role: laboratory)', NULL, NULL, '2026-09-21 18:59:53', '2026-09-21 18:59:53'),
(1652, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-21 18:59:56', '2026-09-21 18:59:56'),
(1653, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-21 19:01:09', '2026-09-21 19:01:09'),
(1654, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-21 19:01:17', '2026-09-21 19:01:17'),
(1655, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-21 19:02:36', '2026-09-21 19:02:36'),
(1656, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-21 19:02:42', '2026-09-21 19:02:42'),
(1657, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-21 19:07:24', '2026-09-21 19:07:24'),
(1658, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-21 19:07:28', '2026-09-21 19:07:28'),
(1659, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-21 19:38:16', '2026-09-21 19:38:16'),
(1660, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-21 19:38:22', '2026-09-21 19:38:22'),
(1661, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-21 19:40:43', '2026-09-21 19:40:43'),
(1662, 5, 1, NULL, 'user_login', 'User logged in: Dr. Grace Peter (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-21 19:40:45', '2026-09-21 19:40:45'),
(1663, 5, 1, NULL, 'user_logout', 'User logged out: Dr. Grace Peter (Role: doctor)', NULL, NULL, '2026-09-21 19:41:25', '2026-09-21 19:41:25'),
(1664, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-21 19:41:29', '2026-09-21 19:41:29'),
(1665, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-21 19:43:04', '2026-09-21 19:43:04'),
(1666, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO MSANGI (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-21 19:43:11', '2026-09-21 19:43:11'),
(1667, 13, 1, NULL, 'lab_tests_bulk_started', 'Started 20 lab test(s) in bulk (global)', NULL, NULL, '2026-09-21 19:43:18', '2026-09-21 19:43:18'),
(1668, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO MSANGI (Role: laboratory)', NULL, NULL, '2026-09-21 19:45:57', '2026-09-21 19:45:57'),
(1669, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO MSANGI (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-21 19:46:01', '2026-09-21 19:46:01'),
(1670, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO MSANGI (Role: laboratory)', NULL, NULL, '2026-09-21 19:46:04', '2026-09-21 19:46:04'),
(1671, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-21 19:46:10', '2026-09-21 19:46:10'),
(1672, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-21 19:46:16', '2026-09-21 19:46:16'),
(1673, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-21 19:46:28', '2026-09-21 19:46:28'),
(1674, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-21 19:46:33', '2026-09-21 19:46:33'),
(1675, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-21 19:46:36', '2026-09-21 19:46:36'),
(1676, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-21 19:49:59', '2026-09-21 19:49:59'),
(1677, 5, 1, NULL, 'user_login', 'User logged in: Dr. Grace Peter (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-21 19:50:02', '2026-09-21 19:50:02'),
(1678, 5, 1, NULL, 'user_logout', 'User logged out: Dr. Grace Peter (Role: doctor)', NULL, NULL, '2026-09-21 19:52:37', '2026-09-21 19:52:37'),
(1679, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-21 19:52:46', '2026-09-21 19:52:46'),
(1680, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-21 19:53:00', '2026-09-21 19:53:00'),
(1681, 8, 1, NULL, 'user_login', 'User logged in: Mary John (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-21 19:53:18', '2026-09-21 19:53:18'),
(1682, 8, 1, NULL, 'user_logout', 'User logged out: Mary John (Role: pharmacy)', NULL, NULL, '2026-09-21 19:54:35', '2026-09-21 19:54:35'),
(1683, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-21 19:54:46', '2026-09-21 19:54:46');

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

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `visit_id`, `patient_id`, `doctor_id`, `assigned_at`, `appointment_date`, `purpose`, `visit_type`, `status`, `notes`, `branch_id`, `created_by`, `created_at`, `updated_at`, `confirmed_at`, `completed_at`, `cancelled_at`) VALUES
(2, NULL, 61, 4, NULL, '2026-09-23 21:56:00', '', '', 'scheduled', '', 1, 1, '2026-09-15 17:57:10', '2026-09-15 17:57:10', NULL, NULL, NULL);

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
  `premium_amount` decimal(12,2) DEFAULT 0.00,
  `premium_note` varchar(255) DEFAULT NULL,
  `pharmacy_premium` decimal(15,2) NOT NULL DEFAULT 0.00,
  `cashier_premium` decimal(15,2) NOT NULL DEFAULT 0.00,
  `pharmacy_premium_note` varchar(255) DEFAULT NULL,
  `cashier_premium_note` varchar(255) DEFAULT NULL,
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
-- Dumping data for table `bills`
--

INSERT INTO `bills` (`id`, `bill_number`, `patient_id`, `visit_id`, `branch_id`, `created_by`, `subtotal`, `discount_percent`, `discount_amount`, `pharmacy_discount`, `cashier_discount`, `premium_amount`, `premium_note`, `pharmacy_premium`, `cashier_premium`, `pharmacy_premium_note`, `cashier_premium_note`, `total_discount`, `total_amount`, `paid_amount`, `balance`, `status`, `payment_method`, `notes`, `created_at`, `updated_at`) VALUES
(372, 'BILL-CONS-20260921-0063-3374', 63, 201, 1, 10, 776000.00, 0.00, 0.00, 0.00, 0.00, 4000.00, 'Premium Charge', 1000.00, 3000.00, 'Pharmacy Premium: 1,000', NULL, 0.00, 780000.00, 780000.00, 0.00, 'paid', 'cash', ' | Pharmacy: Premium +TSh 1,000 (Total Pharmacy Premium: TSh 1,000) Discount TSh 0 at 2026-09-21 22:54:31', '2026-09-21 19:38:39', '2026-09-21 19:57:30'),
(373, 'BILL-CONS-20260921-0062-9458', 62, 202, 1, 10, 353000.00, 0.00, 0.00, 0.00, 0.00, 7000.00, 'Premium Charge', 7000.00, 0.00, 'Pharmacy Premium: 7,000', NULL, 0.00, 360000.00, 360000.00, 0.00, 'paid', 'cash', ' | Pharmacy: Premium +TSh 7,000 (Total Pharmacy Premium: TSh 7,000) Discount TSh 0 at 2026-09-21 22:54:18', '2026-09-21 19:38:57', '2026-09-21 19:57:08'),
(374, 'BILL-CONS-20260921-0061-9245', 61, 203, 1, 10, 416200.00, 0.00, 500.00, 500.00, 700.00, 5000.00, 'Premium Charge', 0.00, 5000.00, 'Pharmacy Premium: 0', NULL, 1200.00, 420000.00, 420000.00, 0.00, 'paid', 'cash', ' | Pharmacy: Premium +TSh 0 (Total Pharmacy Premium: TSh 0) Discount TSh 500 at 2026-09-21 22:54:07', '2026-09-21 19:39:13', '2026-09-21 19:56:41'),
(375, 'BILL-CONS-20260921-0060-4072', 60, 204, 1, 10, 1101500.00, 0.00, 0.00, 0.00, 2000.00, 500.00, 'Premium Charge', 500.00, 0.00, 'Pharmacy Premium: 500', NULL, 2000.00, 1100000.00, 1100000.00, 0.00, 'paid', 'cash', ' | Pharmacy: Premium +TSh 500 (Total Pharmacy Premium: TSh 500) Discount TSh 0 at 2026-09-21 22:53:53', '2026-09-21 19:39:30', '2026-09-21 19:55:55'),
(376, 'BILL-CONS-20260921-0059-3209', 59, 205, 1, 10, 476000.00, 0.00, 0.00, 0.00, 0.00, 24000.00, 'Premium Charge', 4000.00, 20000.00, 'Pharmacy Premium: 4,000', NULL, 0.00, 500000.00, 500000.00, 0.00, 'paid', 'cash', ' | Pharmacy: Premium +TSh 4,000 (Total Pharmacy Premium: TSh 4,000) Discount TSh 0 at 2026-09-21 22:53:38', '2026-09-21 19:39:49', '2026-09-21 19:55:25');

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
(1196, 372, 63, 1, 'consultation', NULL, 'New Patient', NULL, NULL, 1, 10000.00, 10000.00, 0.00, 0.00, 0.00, NULL, NULL, 'paid', '2026-09-21 19:38:39', '2026-09-21 19:57:27'),
(1197, 373, 62, 1, 'consultation', NULL, 'New Patient', NULL, NULL, 1, 10000.00, 10000.00, 0.00, 0.00, 0.00, NULL, NULL, 'paid', '2026-09-21 19:38:57', '2026-09-21 19:57:05'),
(1198, 374, 61, 1, 'consultation', NULL, 'New Patient', NULL, NULL, 1, 10000.00, 10000.00, 0.00, 0.00, 0.00, NULL, NULL, 'paid', '2026-09-21 19:39:13', '2026-09-21 19:56:38'),
(1199, 375, 60, 1, 'consultation', NULL, 'New Patient', NULL, NULL, 1, 10000.00, 10000.00, 0.00, 0.00, 0.00, NULL, NULL, 'paid', '2026-09-21 19:39:30', '2026-09-21 19:55:51'),
(1200, 376, 59, 1, 'consultation', NULL, 'New Patient', NULL, NULL, 1, 10000.00, 10000.00, 0.00, 0.00, 0.00, NULL, NULL, 'paid', '2026-09-21 19:39:49', '2026-09-21 19:55:21'),
(1201, 376, 59, 1, 'lab_test', 305, 'Blood Check', NULL, NULL, 1, 7000.00, 7000.00, 0.00, 0.00, 0.00, 305, '', 'paid', '2026-09-21 19:41:05', '2026-09-21 19:55:21'),
(1202, 376, 59, 1, 'lab_test', 306, 'SS', NULL, NULL, 1, 3000.00, 3000.00, 0.00, 0.00, 0.00, 306, '', 'paid', '2026-09-21 19:41:05', '2026-09-21 19:55:21'),
(1203, 376, 59, 1, 'lab_test', 307, 'Lipid Profile', NULL, NULL, 1, 20000.00, 20000.00, 0.00, 0.00, 0.00, 307, '', 'paid', '2026-09-21 19:41:05', '2026-09-21 19:55:21'),
(1204, 376, 59, 1, 'lab_test', 308, 'Complete Blood Count (CBC)', NULL, NULL, 1, 15000.00, 15000.00, 0.00, 0.00, 0.00, 308, '', 'paid', '2026-09-21 19:41:05', '2026-09-21 19:55:21'),
(1205, 376, 59, 1, 'lab_test', 309, 'ECG (Electrocardiogram)', NULL, NULL, 1, 15000.00, 15000.00, 0.00, 0.00, 0.00, 309, '', 'paid', '2026-09-21 19:41:05', '2026-09-21 19:55:21'),
(1206, 376, 59, 1, 'lab_test', 310, 'Echocardiogram', NULL, NULL, 1, 60000.00, 60000.00, 0.00, 0.00, 0.00, 310, '', 'paid', '2026-09-21 19:41:05', '2026-09-21 19:55:21'),
(1207, 376, 59, 1, 'lab_test', 311, 'Liver Function Test (LFT)', NULL, NULL, 1, 25000.00, 25000.00, 0.00, 0.00, 0.00, 311, '', 'paid', '2026-09-21 19:41:05', '2026-09-21 19:55:21'),
(1208, 375, 60, 1, 'lab_test', 312, 'Echocardiogram', NULL, NULL, 1, 60000.00, 60000.00, 0.00, 0.00, 0.00, 312, '', 'paid', '2026-09-21 19:41:23', '2026-09-21 19:55:51'),
(1209, 375, 60, 1, 'lab_test', 313, 'Renal Function Test (RFT)', NULL, NULL, 1, 20000.00, 20000.00, 0.00, 0.00, 0.00, 313, '', 'paid', '2026-09-21 19:41:23', '2026-09-21 19:55:51'),
(1210, 375, 60, 1, 'lab_test', 314, 'Lipid Profile', NULL, NULL, 1, 20000.00, 20000.00, 0.00, 0.00, 0.00, 314, '', 'paid', '2026-09-21 19:41:23', '2026-09-21 19:55:51'),
(1211, 374, 61, 1, 'lab_test', 315, 'Liver Function Test (LFT)', NULL, NULL, 1, 25000.00, 25000.00, 0.00, 0.00, 0.00, 315, '', 'paid', '2026-09-21 19:42:03', '2026-09-21 19:56:38'),
(1212, 374, 61, 1, 'lab_test', 316, 'Hepatitis C Antibody (Anti-HCV)', NULL, NULL, 1, 15000.00, 15000.00, 0.00, 0.00, 0.00, 316, '', 'paid', '2026-09-21 19:42:03', '2026-09-21 19:56:38'),
(1213, 374, 61, 1, 'lab_test', 317, 'Complete Blood Count (CBC)', NULL, NULL, 1, 15000.00, 15000.00, 0.00, 0.00, 0.00, 317, '', 'paid', '2026-09-21 19:42:03', '2026-09-21 19:56:38'),
(1214, 374, 61, 1, 'lab_test', 318, 'COVID-19 PCR Test', NULL, NULL, 1, 50000.00, 50000.00, 0.00, 0.00, 0.00, 318, '', 'paid', '2026-09-21 19:42:03', '2026-09-21 19:56:38'),
(1215, 373, 62, 1, 'lab_test', 319, 'ECG (Electrocardiogram)', NULL, NULL, 1, 15000.00, 15000.00, 0.00, 0.00, 0.00, 319, '', 'paid', '2026-09-21 19:42:19', '2026-09-21 19:57:05'),
(1216, 373, 62, 1, 'lab_test', 320, 'Echocardiogram', NULL, NULL, 1, 60000.00, 60000.00, 0.00, 0.00, 0.00, 320, '', 'paid', '2026-09-21 19:42:19', '2026-09-21 19:57:05'),
(1217, 373, 62, 1, 'lab_test', 321, 'Liver Function Test (LFT)', NULL, NULL, 1, 25000.00, 25000.00, 0.00, 0.00, 0.00, 321, '', 'paid', '2026-09-21 19:42:19', '2026-09-21 19:57:05'),
(1218, 372, 63, 1, 'lab_test', 322, 'COVID-19 PCR Test', NULL, NULL, 1, 50000.00, 50000.00, 0.00, 0.00, 0.00, 322, '', 'paid', '2026-09-21 19:42:42', '2026-09-21 19:57:27'),
(1219, 372, 63, 1, 'lab_test', 323, 'Echocardiogram', NULL, NULL, 1, 60000.00, 60000.00, 0.00, 0.00, 0.00, 323, '', 'paid', '2026-09-21 19:42:42', '2026-09-21 19:57:27'),
(1220, 372, 63, 1, 'lab_test', 324, 'Viral Load HIV', NULL, NULL, 1, 50000.00, 50000.00, 0.00, 0.00, 0.00, 324, '', 'paid', '2026-09-21 19:42:42', '2026-09-21 19:57:27'),
(1221, 372, 63, 1, 'medication', NULL, 'ALBENDAZOLE (Batch: BATCH-20260908-A196B7)', NULL, NULL, 20, 3000.00, 60000.00, 0.00, 0.00, 0.00, 207, 'prescription', 'paid', '2026-09-21 19:47:12', '2026-09-21 19:57:27'),
(1222, 372, 63, 1, 'medication', NULL, 'Amlodipine 5mg (Batch: BATCH-AML-20260909-001)', NULL, NULL, 20, 450.00, 9000.00, 0.00, 0.00, 0.00, 208, 'prescription', 'paid', '2026-09-21 19:47:12', '2026-09-21 19:57:27'),
(1223, 372, 63, 1, 'medication', NULL, 'AMOXILINE (Batch: BATCH-20260908-49302D)', NULL, NULL, 20, 2500.00, 50000.00, 0.00, 0.00, 0.00, 209, 'prescription', 'paid', '2026-09-21 19:47:12', '2026-09-21 19:57:27'),
(1224, 372, 63, 1, 'procedure', 18, 'Cryotherapy', NULL, NULL, 1, 20000.00, 20000.00, 0.00, 0.00, 0.00, 265, 'procedure', 'paid', '2026-09-21 19:47:19', '2026-09-21 19:57:27'),
(1225, 372, 63, 1, 'procedure', 15, 'ECG - Electrocardiogram', NULL, NULL, 1, 15000.00, 15000.00, 0.00, 0.00, 0.00, 266, 'procedure', 'paid', '2026-09-21 19:47:19', '2026-09-21 19:57:27'),
(1226, 372, 63, 1, 'procedure', 20, 'Free - Nutrition Counseling (FREE)', NULL, NULL, 1, 0.00, 0.00, 0.00, 0.00, 0.00, 267, 'procedure', 'paid', '2026-09-21 19:47:19', '2026-09-21 19:57:27'),
(1227, 372, 63, 1, 'procedure', 16, 'Spirometry - Lung Function', NULL, NULL, 1, 25000.00, 25000.00, 0.00, 0.00, 0.00, 268, 'procedure', 'paid', '2026-09-21 19:47:19', '2026-09-21 19:57:27'),
(1228, 372, 63, 1, 'equipment', 40, 'BANDAGE', NULL, NULL, 10, 1500.00, 15000.00, 0.00, 0.00, 0.00, 40, 'equipment', 'paid', '2026-09-21 19:47:33', '2026-09-21 19:57:27'),
(1229, 372, 63, 1, 'equipment', 3, 'Blood Pressure Monitor', NULL, NULL, 10, 25000.00, 250000.00, 0.00, 0.00, 0.00, 3, 'equipment', 'paid', '2026-09-21 19:47:33', '2026-09-21 19:57:27'),
(1230, 372, 63, 1, 'equipment', 1, 'ECG Machine (12-Lead)', NULL, NULL, 10, 15000.00, 150000.00, 0.00, 0.00, 0.00, 1, 'equipment', 'paid', '2026-09-21 19:47:33', '2026-09-21 19:57:27'),
(1231, 372, 63, 1, 'equipment', 41, 'SINDANO', NULL, NULL, 10, 1200.00, 12000.00, 0.00, 0.00, 0.00, 41, 'equipment', 'paid', '2026-09-21 19:47:33', '2026-09-21 19:57:27'),
(1232, 374, 61, 1, 'medication', NULL, 'ALBENDAZOLE (Batch: BATCH-20260908-A196B7)', NULL, NULL, 30, 3000.00, 90000.00, 0.00, 0.00, 0.00, 210, 'prescription', 'paid', '2026-09-21 19:48:19', '2026-09-21 19:56:38'),
(1233, 374, 61, 1, 'medication', NULL, 'Amlodipine 5mg (Batch: BATCH-AML-20260909-001)', NULL, NULL, 30, 450.00, 13500.00, 0.00, 0.00, 0.00, 211, 'prescription', 'paid', '2026-09-21 19:48:19', '2026-09-21 19:56:38'),
(1234, 374, 61, 1, 'medication', NULL, 'Amoxicillin 500mg (Batch: BATCH-AMOX-20260909-001)', NULL, NULL, 30, 500.00, 15000.00, 0.00, 0.00, 0.00, 212, 'prescription', 'paid', '2026-09-21 19:48:20', '2026-09-21 19:56:38'),
(1235, 374, 61, 1, 'procedure', 18, 'Cryotherapy', NULL, NULL, 1, 20000.00, 20000.00, 0.00, 0.00, 0.00, 269, 'procedure', 'paid', '2026-09-21 19:48:27', '2026-09-21 19:56:38'),
(1236, 374, 61, 1, 'procedure', 16, 'Spirometry - Lung Function', NULL, NULL, 1, 25000.00, 25000.00, 0.00, 0.00, 0.00, 270, 'procedure', 'paid', '2026-09-21 19:48:27', '2026-09-21 19:56:38'),
(1237, 374, 61, 1, 'procedure', 1, 'WOUND DRESSING', NULL, NULL, 1, 45000.00, 45000.00, 0.00, 0.00, 0.00, 271, 'procedure', 'paid', '2026-09-21 19:48:27', '2026-09-21 19:56:38'),
(1238, 374, 61, 1, 'equipment', 40, 'BANDAGE', NULL, NULL, 1, 1500.00, 1500.00, 0.00, 0.00, 0.00, 40, 'equipment', 'paid', '2026-09-21 19:48:38', '2026-09-21 19:56:38'),
(1239, 374, 61, 1, 'equipment', 3, 'Blood Pressure Monitor', NULL, NULL, 1, 25000.00, 25000.00, 0.00, 0.00, 0.00, 3, 'equipment', 'paid', '2026-09-21 19:48:38', '2026-09-21 19:56:38'),
(1240, 374, 61, 1, 'equipment', 1, 'ECG Machine (12-Lead)', NULL, NULL, 1, 15000.00, 15000.00, 0.00, 0.00, 0.00, 1, 'equipment', 'paid', '2026-09-21 19:48:38', '2026-09-21 19:56:38'),
(1241, 374, 61, 1, 'equipment', 41, 'SINDANO', NULL, NULL, 1, 1200.00, 1200.00, 0.00, 0.00, 0.00, 41, 'equipment', 'paid', '2026-09-21 19:48:38', '2026-09-21 19:56:38'),
(1242, 374, 61, 1, 'equipment', 5, 'Surgical Scalpel Set', NULL, NULL, 1, 50000.00, 50000.00, 0.00, 0.00, 0.00, 5, 'equipment', 'paid', '2026-09-21 19:48:38', '2026-09-21 19:56:38'),
(1243, 373, 62, 1, 'medication', NULL, 'Amlodipine 5mg (Batch: BATCH-AML-20260909-001)', NULL, NULL, 10, 450.00, 4500.00, 0.00, 0.00, 0.00, 213, 'prescription', 'paid', '2026-09-21 19:49:15', '2026-09-21 19:57:05'),
(1244, 373, 62, 1, 'medication', NULL, 'Amoxicillin 500mg (Batch: BATCH-AMOX-20260909-001)', NULL, NULL, 10, 500.00, 5000.00, 0.00, 0.00, 0.00, 214, 'prescription', 'paid', '2026-09-21 19:49:15', '2026-09-21 19:57:05'),
(1245, 373, 62, 1, 'medication', NULL, 'ALBENDAZOLE (Batch: BATCH-20260908-A196B7)', NULL, NULL, 10, 3000.00, 30000.00, 0.00, 0.00, 0.00, 215, 'prescription', 'paid', '2026-09-21 19:49:15', '2026-09-21 19:57:05'),
(1246, 373, 62, 1, 'medication', NULL, 'Omeprazole 20mg (Batch: BATCH-OME-20260909-001)', NULL, NULL, 10, 350.00, 3500.00, 0.00, 0.00, 0.00, 216, 'prescription', 'paid', '2026-09-21 19:49:15', '2026-09-21 19:57:05'),
(1247, 373, 62, 1, 'procedure', 18, 'Cryotherapy', NULL, NULL, 1, 20000.00, 20000.00, 0.00, 0.00, 0.00, 272, 'procedure', 'paid', '2026-09-21 19:49:22', '2026-09-21 19:57:05'),
(1248, 373, 62, 1, 'procedure', 15, 'ECG - Electrocardiogram', NULL, NULL, 1, 15000.00, 15000.00, 0.00, 0.00, 0.00, 273, 'procedure', 'paid', '2026-09-21 19:49:22', '2026-09-21 19:57:05'),
(1249, 373, 62, 1, 'procedure', 17, 'Minor Surgery - Excision', NULL, NULL, 1, 50000.00, 50000.00, 0.00, 0.00, 0.00, 274, 'procedure', 'paid', '2026-09-21 19:49:23', '2026-09-21 19:57:05'),
(1250, 373, 62, 1, 'procedure', 16, 'Spirometry - Lung Function', NULL, NULL, 1, 25000.00, 25000.00, 0.00, 0.00, 0.00, 275, 'procedure', 'paid', '2026-09-21 19:49:23', '2026-09-21 19:57:05'),
(1251, 373, 62, 1, 'equipment', 3, 'Blood Pressure Monitor', NULL, NULL, 1, 25000.00, 25000.00, 0.00, 0.00, 0.00, 3, 'equipment', 'paid', '2026-09-21 19:49:35', '2026-09-21 19:57:05'),
(1252, 373, 62, 1, 'equipment', 1, 'ECG Machine (12-Lead)', NULL, NULL, 1, 15000.00, 15000.00, 0.00, 0.00, 0.00, 1, 'equipment', 'paid', '2026-09-21 19:49:35', '2026-09-21 19:57:05'),
(1253, 373, 62, 1, 'equipment', 5, 'Surgical Scalpel Set', NULL, NULL, 1, 50000.00, 50000.00, 0.00, 0.00, 0.00, 5, 'equipment', 'paid', '2026-09-21 19:49:35', '2026-09-21 19:57:05'),
(1254, 376, 59, 1, 'medication', NULL, 'Amlodipine 5mg (Batch: BATCH-AML-20260909-001)', NULL, NULL, 15, 450.00, 6750.00, 0.00, 0.00, 0.00, 217, 'prescription', 'paid', '2026-09-21 19:50:42', '2026-09-21 19:55:21'),
(1255, 376, 59, 1, 'medication', NULL, 'ALBENDAZOLE (Batch: BATCH-20260908-A196B7)', NULL, NULL, 15, 3000.00, 45000.00, 0.00, 0.00, 0.00, 218, 'prescription', 'paid', '2026-09-21 19:50:43', '2026-09-21 19:55:21'),
(1256, 376, 59, 1, 'medication', NULL, 'AMOXILINE (Batch: BATCH-20260908-49302D)', NULL, NULL, 15, 2500.00, 37500.00, 0.00, 0.00, 0.00, 219, 'prescription', 'paid', '2026-09-21 19:50:43', '2026-09-21 19:55:21'),
(1257, 376, 59, 1, 'medication', NULL, 'Amitriptyline 25mg (Batch: BATCH-AMIT-20260909-001)', NULL, NULL, 15, 450.00, 6750.00, 0.00, 0.00, 0.00, 220, 'prescription', 'paid', '2026-09-21 19:50:43', '2026-09-21 19:55:21'),
(1258, 376, 59, 1, 'procedure', 18, 'Cryotherapy', NULL, NULL, 1, 20000.00, 20000.00, 0.00, 0.00, 0.00, 276, 'procedure', 'paid', '2026-09-21 19:50:51', '2026-09-21 19:55:21'),
(1259, 376, 59, 1, 'procedure', 15, 'ECG - Electrocardiogram', NULL, NULL, 1, 15000.00, 15000.00, 0.00, 0.00, 0.00, 277, 'procedure', 'paid', '2026-09-21 19:50:51', '2026-09-21 19:55:21'),
(1260, 376, 59, 1, 'procedure', 20, 'Free - Nutrition Counseling (FREE)', NULL, NULL, 1, 0.00, 0.00, 0.00, 0.00, 0.00, 278, 'procedure', 'paid', '2026-09-21 19:50:51', '2026-09-21 19:55:21'),
(1261, 376, 59, 1, 'procedure', 17, 'Minor Surgery - Excision', NULL, NULL, 1, 50000.00, 50000.00, 0.00, 0.00, 0.00, 279, 'procedure', 'paid', '2026-09-21 19:50:51', '2026-09-21 19:55:21'),
(1262, 376, 59, 1, 'procedure', 1, 'WOUND DRESSING', NULL, NULL, 1, 45000.00, 45000.00, 0.00, 0.00, 0.00, 280, 'procedure', 'paid', '2026-09-21 19:50:51', '2026-09-21 19:55:21'),
(1263, 376, 59, 1, 'equipment', 3, 'Blood Pressure Monitor', NULL, NULL, 1, 25000.00, 25000.00, 0.00, 0.00, 0.00, 3, 'equipment', 'paid', '2026-09-21 19:51:02', '2026-09-21 19:55:21'),
(1264, 376, 59, 1, 'equipment', 1, 'ECG Machine (12-Lead)', NULL, NULL, 1, 15000.00, 15000.00, 0.00, 0.00, 0.00, 1, 'equipment', 'paid', '2026-09-21 19:51:02', '2026-09-21 19:55:21'),
(1265, 376, 59, 1, 'equipment', 8, 'Surgical Gloves (Sterile)', NULL, NULL, 1, 5000.00, 5000.00, 0.00, 0.00, 0.00, 8, 'equipment', 'paid', '2026-09-21 19:51:02', '2026-09-21 19:55:21'),
(1266, 376, 59, 1, 'equipment', 5, 'Surgical Scalpel Set', NULL, NULL, 1, 50000.00, 50000.00, 0.00, 0.00, 0.00, 5, 'equipment', 'paid', '2026-09-21 19:51:02', '2026-09-21 19:55:21'),
(1267, 375, 60, 1, 'medication', NULL, 'Amlodipine 5mg (Batch: BATCH-AML-20260909-001)', NULL, NULL, 10, 450.00, 4500.00, 0.00, 0.00, 0.00, 221, 'prescription', 'paid', '2026-09-21 19:51:53', '2026-09-21 19:55:51'),
(1268, 375, 60, 1, 'medication', NULL, 'ALBENDAZOLE (Batch: BATCH-20260908-A196B7)', NULL, NULL, 10, 3000.00, 30000.00, 0.00, 0.00, 0.00, 222, 'prescription', 'paid', '2026-09-21 19:51:54', '2026-09-21 19:55:51'),
(1269, 375, 60, 1, 'medication', NULL, 'AMOXILINE (Batch: BATCH-20260908-49302D)', NULL, NULL, 10, 2500.00, 25000.00, 0.00, 0.00, 0.00, 223, 'prescription', 'paid', '2026-09-21 19:51:54', '2026-09-21 19:55:51'),
(1270, 375, 60, 1, 'procedure', 18, 'Cryotherapy', NULL, NULL, 1, 20000.00, 20000.00, 0.00, 0.00, 0.00, 281, 'procedure', 'paid', '2026-09-21 19:52:04', '2026-09-21 19:55:51'),
(1271, 375, 60, 1, 'procedure', 15, 'ECG - Electrocardiogram', NULL, NULL, 1, 15000.00, 15000.00, 0.00, 0.00, 0.00, 282, 'procedure', 'paid', '2026-09-21 19:52:04', '2026-09-21 19:55:51'),
(1272, 375, 60, 1, 'procedure', 17, 'Minor Surgery - Excision', NULL, NULL, 1, 50000.00, 50000.00, 0.00, 0.00, 0.00, 283, 'procedure', 'paid', '2026-09-21 19:52:04', '2026-09-21 19:55:51'),
(1273, 375, 60, 1, 'procedure', 1, 'WOUND DRESSING', NULL, NULL, 1, 45000.00, 45000.00, 0.00, 0.00, 0.00, 284, 'procedure', 'paid', '2026-09-21 19:52:04', '2026-09-21 19:55:51'),
(1274, 375, 60, 1, 'procedure', 12, 'Wound Dressing', NULL, NULL, 1, 25000.00, 25000.00, 0.00, 0.00, 0.00, 285, 'procedure', 'paid', '2026-09-21 19:52:04', '2026-09-21 19:55:51'),
(1275, 375, 60, 1, 'equipment', 40, 'BANDAGE', NULL, NULL, 10, 1500.00, 15000.00, 0.00, 0.00, 0.00, 40, 'equipment', 'paid', '2026-09-21 19:52:21', '2026-09-21 19:55:51'),
(1276, 375, 60, 1, 'equipment', 3, 'Blood Pressure Monitor', NULL, NULL, 10, 25000.00, 250000.00, 0.00, 0.00, 0.00, 3, 'equipment', 'paid', '2026-09-21 19:52:21', '2026-09-21 19:55:51'),
(1277, 375, 60, 1, 'equipment', 41, 'SINDANO', NULL, NULL, 10, 1200.00, 12000.00, 0.00, 0.00, 0.00, 41, 'equipment', 'paid', '2026-09-21 19:52:21', '2026-09-21 19:55:51'),
(1278, 375, 60, 1, 'equipment', 5, 'Surgical Scalpel Set', NULL, NULL, 10, 50000.00, 500000.00, 0.00, 0.00, 0.00, 5, 'equipment', 'paid', '2026-09-21 19:52:21', '2026-09-21 19:55:51');

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
(1, 'Dodoma', 'Dodoma City, Tanzania', '+255 700 000 701', 'dodoma@braick.com', '', 'active', '2026-08-23 12:26:09', '2026-09-15 19:52:46'),
(2, 'Arusha', 'Arusha City, Tanzania', '+255 700 000 002', 'arusha@braick.com', NULL, 'active', '2026-08-23 12:26:09', '2026-08-23 12:26:09'),
(3, 'Dar es Salaam', 'Dar es Salaam, Tanzania', '+255 700 000 003', 'dar@braick.com', NULL, 'active', '2026-08-23 12:26:09', '2026-08-23 12:26:09'),
(4, 'KAHAMA', 'KIGOMA', '+255623693303', 'jacksonmyula773@gmail.com', NULL, 'active', '2026-09-15 15:25:24', '2026-09-15 15:25:24');

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
(22, '13BRT9_BTC8', 'ANTENCIK 104', NULL, NULL, NULL, '-MAZIWA NA MATUNDA KWA WINGI\r\n-VYAKULA VYA PROTEINS', 1, NULL, 1, '2026-09-06 14:50:38', '2026-09-06 14:51:05'),
(23, 'D-KICHWA-704', 'KICHWA', NULL, NULL, NULL, '', 1, NULL, 1, '2026-09-15 14:18:04', '2026-09-15 14:18:04'),
(24, 'D-KICHOC-635', 'KICHOCHO', NULL, NULL, NULL, '', 1, NULL, 1, '2026-09-15 14:18:04', '2026-09-15 14:18:04'),
(25, 'ATM28', 'ATHMA', NULL, NULL, NULL, '', 1, NULL, 1, '2026-09-15 14:37:01', '2026-09-19 19:28:35'),
(26, 'D-AMIBA1-981', 'AMIBA 13', NULL, NULL, NULL, '', 1, NULL, 1, '2026-09-19 20:11:42', '2026-09-19 20:11:42'),
(27, 'D-TYPHOI-507', 'TYPHOID', NULL, NULL, NULL, '', 1, NULL, 1, '2026-09-19 20:11:42', '2026-09-19 20:11:42');

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
(9, 43, 1, 1, '2026-09-02 21:15:20'),
(11, 13, 3, 1, '2026-09-15 21:54:58'),
(20, 45, 6, 1, '2026-09-15 22:38:22'),
(21, 46, 6, 1, '2026-09-15 22:41:25'),
(23, 48, 6, 1, '2026-09-15 22:46:31'),
(25, 47, 6, 1, '2026-09-15 22:49:10'),
(26, 49, 6, 1, '2026-09-15 22:50:01');

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
(8, 44, 'reception', 1, '2026-09-03 01:31:31'),
(11, 13, 'laboratory', 1, '2026-09-15 21:54:58'),
(23, 45, 'audit', 1, '2026-09-15 22:38:22'),
(24, 46, 'audit', 1, '2026-09-15 22:41:25'),
(26, 48, 'audit', 1, '2026-09-15 22:46:31'),
(28, 47, 'audit', 1, '2026-09-15 22:49:10'),
(29, 49, 'audit', 1, '2026-09-15 22:50:01');

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
(2, 'EXP-20260903-2839', 'Transport', 'chakula', 70000.00, 'cash', '2026-09-03', 'paid', '', '', 10, 1, '2026-09-03 20:54:24', '2026-09-19 15:16:07');

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
  `oxygen_saturation` int(11) DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `height` decimal(5,2) DEFAULT NULL,
  `bmi` decimal(4,1) DEFAULT NULL,
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

INSERT INTO `external_sick_sheets` (`id`, `document_number`, `full_name`, `patient_id`, `phone`, `gender`, `date_of_birth`, `address`, `blood_group`, `allergies`, `symptoms`, `diagnosis`, `treatment`, `instructions`, `temperature`, `bp_systolic`, `bp_diastolic`, `pulse_rate`, `oxygen_saturation`, `weight`, `height`, `bmi`, `lab_results`, `medications`, `procedures`, `sick_days`, `sick_from`, `sick_to`, `sick_reason`, `sick_restrictions`, `doctor_id`, `branch_id`, `file_name`, `file_path`, `file_type`, `created_at`, `updated_at`, `status`) VALUES
(1, 'SS-20260824-3276', 'KELVIN', 'EXT-2026-3526', '0623693303', 'Male', '2001-09-10', 'TANZANIA', 'AB-', '', 'DIZZ', 'MALARIA', '', '', 30.0, 129, 78, 70, NULL, 68.00, 172.80, NULL, '', '', '', 3, '2026-08-24', '2026-08-27', 'Medical condition requiring rest', 'No heavy lifting, complete rest', 4, 1, 'sick_sheet_SS-20260824-3276.html', '/dispensary_system/frontend/assets/uploads/sick_sheets/sick_sheet_SS-20260824-3276.html', 'text/html', '2026-08-24 12:20:29', '2026-08-24 12:20:29', 'active');

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
  `requested_by_id` int(11) DEFAULT NULL,
  `requested_at` datetime DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `printed_at` timestamp NULL DEFAULT NULL,
  `printed_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lab_tests`
--

INSERT INTO `lab_tests` (`id`, `visit_id`, `patient_id`, `doctor_id`, `lab_technician_id`, `technician_id`, `test_id`, `test_name`, `test_price`, `equipment_used`, `batch_number`, `test_type`, `sample_type`, `test_date`, `results`, `formatted_result`, `reference_range`, `interpretation`, `performed_by`, `status`, `started_at`, `bill_created`, `branch_id`, `notes`, `created_at`, `requested_by_id`, `requested_at`, `completed_at`, `printed_at`, `printed_by`, `updated_at`) VALUES
(305, 205, 59, 5, 13, NULL, 58, 'Blood Check', 7000.00, NULL, NULL, NULL, NULL, NULL, '100-125 mg/dL', NULL, '', '', 13, 'completed', '2026-09-21 19:43:18', 0, 1, '', '2026-09-21 19:41:05', NULL, NULL, '2026-09-21 19:43:29', NULL, NULL, '2026-09-21 19:43:29'),
(306, 205, 59, 5, 13, NULL, 63, 'SS', 3000.00, NULL, NULL, NULL, NULL, NULL, '100-125 mg/dL', NULL, '', '', 13, 'completed', '2026-09-21 19:43:18', 0, 1, '', '2026-09-21 19:41:05', NULL, NULL, '2026-09-21 19:43:37', NULL, NULL, '2026-09-21 19:43:37'),
(307, 205, 59, 5, 13, NULL, 4, 'Lipid Profile', 20000.00, NULL, NULL, NULL, NULL, NULL, '100-125 mg/dL', NULL, '', '', 13, 'completed', '2026-09-21 19:43:18', 0, 1, '', '2026-09-21 19:41:05', NULL, NULL, '2026-09-21 19:43:45', NULL, NULL, '2026-09-21 19:43:45'),
(308, 205, 59, 5, 13, NULL, 1, 'Complete Blood Count (CBC)', 15000.00, NULL, NULL, NULL, NULL, NULL, 'RBC: 4.5-5.5M, WBC: 4.5-11K, HGB: 13-17g/dL, PLT: 150-400K', NULL, '', '', 13, 'completed', '2026-09-21 19:43:18', 0, 1, '', '2026-09-21 19:41:05', NULL, NULL, '2026-09-21 19:43:51', NULL, NULL, '2026-09-21 19:43:51'),
(309, 205, 59, 5, 13, NULL, 40, 'ECG (Electrocardiogram)', 15000.00, NULL, NULL, NULL, NULL, NULL, '100-125 mg/dL', NULL, '', '', 13, 'completed', '2026-09-21 19:43:18', 0, 1, '', '2026-09-21 19:41:05', NULL, NULL, '2026-09-21 19:43:58', NULL, NULL, '2026-09-21 19:43:58'),
(310, 205, 59, 5, 13, NULL, 41, 'Echocardiogram', 60000.00, NULL, NULL, NULL, NULL, NULL, '100-125 mg/dL', NULL, '', '', 13, 'completed', '2026-09-21 19:43:18', 0, 1, '', '2026-09-21 19:41:05', NULL, NULL, '2026-09-21 19:44:06', NULL, NULL, '2026-09-21 19:44:06'),
(311, 205, 59, 5, 13, NULL, 5, 'Liver Function Test (LFT)', 25000.00, NULL, NULL, NULL, NULL, NULL, '100-125 mg/dL', NULL, '', '', 13, 'completed', '2026-09-21 19:43:18', 0, 1, '', '2026-09-21 19:41:05', NULL, NULL, '2026-09-21 19:44:14', NULL, NULL, '2026-09-21 19:44:14'),
(312, 204, 60, 5, 13, NULL, 41, 'Echocardiogram', 60000.00, NULL, NULL, NULL, NULL, NULL, '100-125 mg/dL', NULL, '', '', 13, 'completed', '2026-09-21 19:43:18', 0, 1, '', '2026-09-21 19:41:23', NULL, NULL, '2026-09-21 19:44:22', NULL, NULL, '2026-09-21 19:44:22'),
(313, 204, 60, 5, 13, NULL, 6, 'Renal Function Test (RFT)', 20000.00, NULL, NULL, NULL, NULL, NULL, 'Creatinine: 0.6-1.2, BUN: 7-20, Uric Acid: 3.5-7.2', NULL, '', '', 13, 'completed', '2026-09-21 19:43:18', 0, 1, '', '2026-09-21 19:41:23', NULL, NULL, '2026-09-21 19:44:29', NULL, NULL, '2026-09-21 19:44:29'),
(314, 204, 60, 5, 13, NULL, 4, 'Lipid Profile', 20000.00, NULL, NULL, NULL, NULL, NULL, '100-125 mg/dL', NULL, '', '', 13, 'completed', '2026-09-21 19:43:18', 0, 1, '', '2026-09-21 19:41:23', NULL, NULL, '2026-09-21 19:44:38', NULL, NULL, '2026-09-21 19:44:38'),
(315, 203, 61, 4, 13, NULL, 5, 'Liver Function Test (LFT)', 25000.00, NULL, NULL, NULL, NULL, NULL, '100-125 mg/dL', NULL, '', '', 13, 'completed', '2026-09-21 19:43:18', 0, 1, '', '2026-09-21 19:42:03', NULL, NULL, '2026-09-21 19:45:10', NULL, NULL, '2026-09-21 19:45:10'),
(316, 203, 61, 4, 13, NULL, 30, 'Hepatitis C Antibody (Anti-HCV)', 15000.00, NULL, NULL, NULL, NULL, NULL, 'Reactive', NULL, '', '', 13, 'completed', '2026-09-21 19:43:18', 0, 1, '', '2026-09-21 19:42:03', NULL, NULL, '2026-09-21 19:45:16', NULL, NULL, '2026-09-21 19:45:16'),
(317, 203, 61, 4, 13, NULL, 1, 'Complete Blood Count (CBC)', 15000.00, NULL, NULL, NULL, NULL, NULL, 'RBC: 4.5-5.5M, WBC: 4.5-11K, HGB: 13-17g/dL, PLT: 150-400K', NULL, '', '', 13, 'completed', '2026-09-21 19:43:18', 0, 1, '', '2026-09-21 19:42:03', NULL, NULL, '2026-09-21 19:45:23', NULL, NULL, '2026-09-21 19:45:23'),
(318, 203, 61, 4, 13, NULL, 11, 'COVID-19 PCR Test', 50000.00, NULL, NULL, NULL, NULL, NULL, 'Positive - SARS-CoV-2 antigen detected', NULL, '', '', 13, 'completed', '2026-09-21 19:43:18', 0, 1, '', '2026-09-21 19:42:03', NULL, NULL, '2026-09-21 19:45:30', NULL, NULL, '2026-09-21 19:45:30'),
(319, 202, 62, 4, 13, NULL, 40, 'ECG (Electrocardiogram)', 15000.00, NULL, NULL, NULL, NULL, NULL, '100-125 mg/dL', NULL, '', '', 13, 'completed', '2026-09-21 19:43:18', 0, 1, '', '2026-09-21 19:42:19', NULL, NULL, '2026-09-21 19:44:46', NULL, NULL, '2026-09-21 19:44:46'),
(320, 202, 62, 4, 13, NULL, 41, 'Echocardiogram', 60000.00, NULL, NULL, NULL, NULL, NULL, '100-125 mg/dL', NULL, '', '', 13, 'completed', '2026-09-21 19:43:18', 0, 1, '', '2026-09-21 19:42:19', NULL, NULL, '2026-09-21 19:44:53', NULL, NULL, '2026-09-21 19:44:53'),
(321, 202, 62, 4, 13, NULL, 5, 'Liver Function Test (LFT)', 25000.00, NULL, NULL, NULL, NULL, NULL, '100-125 mg/dL', NULL, '', '', 13, 'completed', '2026-09-21 19:43:18', 0, 1, '', '2026-09-21 19:42:19', NULL, NULL, '2026-09-21 19:45:02', NULL, NULL, '2026-09-21 19:45:02'),
(322, 201, 63, 4, 13, NULL, 11, 'COVID-19 PCR Test', 50000.00, NULL, NULL, NULL, NULL, NULL, 'Positive - SARS-CoV-2 antigen detected', NULL, '', '', 13, 'completed', '2026-09-21 19:43:18', 0, 1, '', '2026-09-21 19:42:42', NULL, NULL, '2026-09-21 19:45:37', NULL, NULL, '2026-09-21 19:45:37'),
(323, 201, 63, 4, 13, NULL, 41, 'Echocardiogram', 60000.00, NULL, NULL, NULL, NULL, NULL, '100-125 mg/dL', NULL, '', '', 13, 'completed', '2026-09-21 19:43:18', 0, 1, '', '2026-09-21 19:42:42', NULL, NULL, '2026-09-21 19:45:46', NULL, NULL, '2026-09-21 19:45:46'),
(324, 201, 63, 4, 13, NULL, 35, 'Viral Load HIV', 50000.00, NULL, NULL, NULL, NULL, NULL, '100-125 mg/dL', NULL, '', '', 13, 'completed', '2026-09-21 19:43:18', 0, 1, '', '2026-09-21 19:42:42', NULL, NULL, '2026-09-21 19:45:54', NULL, NULL, '2026-09-21 19:45:54');

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
(1, 'ECG Machine (12-Lead)', 'Cardiology', 'pcs', 583, 2, 5000.00, 15000.00, 'GE Healthcare', NULL, 'EQP-20260909-ECG-001', 1, 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-21 19:51:02'),
(2, 'Ultrasound Machine', 'Radiology', 'pcs', 0, 1, 80000.00, 25000.00, 'Siemens', NULL, 'EQP-20260909-US-001', 1, 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-19 20:14:02'),
(3, 'Blood Pressure Monitor', 'Diagnostic', 'pcs', 771, 3, 10000.00, 25000.00, 'Omron', NULL, 'EQP-20260909-BP-001', 1, 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-21 19:52:21'),
(4, 'Stethoscope', 'Diagnostic', 'pcs', 0, 5, 8000.00, 3000.00, '3M Littmann', NULL, 'EQP-20260909-ST-001', 1, 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-21 18:19:14'),
(5, 'Surgical Scalpel Set', 'Surgery', 'set', 381, 3, 15000.00, 50000.00, 'Medical Supplies Co', NULL, 'EQP-20260909-SS-001', 1, 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-21 19:52:21'),
(6, 'Bandage Roll', 'Wound Care', 'roll', 588, 20, 500.00, 1500.00, 'MediCare', '2027-12-31', 'EQP-20260909-BAND-001', 1, 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-21 18:20:19'),
(7, 'Gauze Swabs (Sterile)', 'Wound Care', 'pack', 736, 10, 300.00, 1000.00, 'MediCare', '2027-06-30', 'EQP-20260909-GAUZE-001', 1, 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-21 18:20:19'),
(8, 'Surgical Gloves (Sterile)', 'Surgery', 'box', 19, 5, 2000.00, 5000.00, 'Ansell', '2027-09-30', 'EQP-20260909-GLOVE-001', 1, 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-21 19:51:02'),
(9, 'Suture Kit', 'Surgery', 'kit', 0, 4, 25000.00, 10000.00, 'Ethicon', NULL, 'EQP-20260909-SUT-001', 1, 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-21 18:20:19'),
(40, 'BANDAGE', 'Lab Equipment', 'box', 266, 50, 500.00, 1500.00, 'WAKATI', '0000-00-00', 'EQP-20260909-8495C2', 1, NULL, 'active', 1, 'System Admin', '2026-09-09 13:35:41', '2026-09-21 19:52:21'),
(41, 'SINDANO', 'Wound Care', 'set', 259, 50, 500.00, 1200.00, 'AMANA', '0000-00-00', 'EQP-20260909-40E8C8', 1, NULL, 'active', 7, 'LUCY MUSSA', '2026-09-09 13:36:49', '2026-09-21 19:52:21');

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
(1, 'Paracetamol 500mg', 'Analgesics', 'tablets', 310, 50, 50.00, 200.00, 'Medical Supplies Ltd', '2027-12-31', 'BATCH-PCM-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-21 18:29:18'),
(2, 'Amoxicillin 500mg', 'Antibiotics', 'capsules', 269, 30, 150.00, 500.00, 'PharmaPlus Ltd', '2027-10-15', 'BATCH-AMOX-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-21 19:49:15'),
(3, 'Ciprofloxacin 500mg', 'Antibiotics', 'tablets', 75, 20, 200.00, 800.00, 'PharmaPlus Ltd', '2027-11-30', 'BATCH-CIPRO-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-21 18:29:18'),
(4, 'Metronidazole 400mg', 'Antibiotics', 'tablets', 165, 25, 100.00, 400.00, 'Medical Supplies Ltd', '2027-09-20', 'BATCH-METRO-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-21 18:29:18'),
(5, 'Omeprazole 20mg', 'Antacids', 'capsules', 110, 15, 80.00, 350.00, 'HealthCare Ltd', '2028-01-15', 'BATCH-OME-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-21 19:49:15'),
(6, 'Ibuprofen 400mg', 'Analgesics', 'tablets', 150, 20, 60.00, 300.00, 'Medical Supplies Ltd', '2027-08-30', 'BATCH-IBU-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-19 14:39:58'),
(7, 'Diclofenac 50mg', 'Analgesics', 'tablets', 35, 18, 70.00, 350.00, 'PharmaPlus Ltd', '2027-07-25', 'BATCH-DICL-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-19 17:30:38'),
(8, 'Cetirizine 10mg', 'Antihistamines', 'tablets', 85, 30, 30.00, 150.00, 'HealthCare Ltd', '2028-02-28', 'BATCH-CET-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-21 18:29:18'),
(9, 'Loratadine 10mg', 'Antihistamines', 'tablets', 180, 25, 40.00, 200.00, 'Medical Supplies Ltd', '2027-12-15', 'BATCH-LORA-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-21 15:36:28'),
(10, 'Salbutamol Inhaler', 'Respiratory', 'inhaler', 150, 10, 2500.00, 5000.00, 'PharmaPlus Ltd', '2028-03-01', 'BATCH-SALB-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-21 18:29:18'),
(11, 'Beclomethasone Inhaler', 'Respiratory', 'inhaler', 6, 10, 3000.00, 6500.00, 'HealthCare Ltd', '2028-04-15', 'BATCH-BECLO-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-20 16:22:52'),
(12, 'Amlodipine 5mg', 'Cardiovascular', 'tablets', 270, 15, 120.00, 450.00, 'Medical Supplies Ltd', '2027-11-30', 'BATCH-AML-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-21 19:51:53'),
(13, 'Enalapril 10mg', 'Cardiovascular', 'tablets', 50, 10, 130.00, 500.00, 'PharmaPlus Ltd', '2027-10-20', 'BATCH-ENAL-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-19 21:44:28'),
(14, 'Hydrochlorothiazide 25mg', 'Cardiovascular', 'tablets', 55, 15, 80.00, 300.00, 'HealthCare Ltd', '2027-09-10', 'BATCH-HCTZ-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-19 17:30:38'),
(15, 'Metformin 500mg', 'Antidiabetic', 'tablets', 160, 20, 100.00, 400.00, 'Medical Supplies Ltd', '2028-01-20', 'BATCH-MET-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-21 15:36:28'),
(16, 'Glibenclamide 5mg', 'Antidiabetic', 'tablets', 40, 15, 90.00, 350.00, 'PharmaPlus Ltd', '2027-12-05', 'BATCH-GLIB-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-19 21:44:28'),
(17, 'Furosemide 40mg', 'Diuretics', 'tablets', 55, 10, 70.00, 250.00, 'HealthCare Ltd', '2027-08-15', 'BATCH-FURO-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-19 21:44:28'),
(18, 'Diazepam 5mg', 'Sedatives', 'tablets', 80, 10, 150.00, 600.00, 'Medical Supplies Ltd', '2028-02-10', 'BATCH-DIAZ-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-08 21:00:00'),
(19, 'Amitriptyline 25mg', 'Antidepressants', 'tablets', 99, 10, 120.00, 450.00, 'PharmaPlus Ltd', '2027-09-25', 'BATCH-AMIT-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-21 19:50:43'),
(20, 'Multivitamin Tablets', 'Vitamins', 'tablets', 320, 40, 30.00, 150.00, 'HealthCare Ltd', '2028-06-30', 'BATCH-MVIT-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-21 18:29:18'),
(25, 'ALBENDAZOLE', 'Antibiotics', 'pcs', 270, 100, 1300.00, 3000.00, 'AVANA MEDICS', '2028-09-08', 'BATCH-20260908-A196B7', 1, 'active', 8, 'Mary John', '2026-09-08 13:38:44', '2026-09-21 19:51:54'),
(26, 'AMOXILINE', 'Antacids', 'pcs', 264, 20, 1000.00, 2500.00, 'AVANA MEDICS', '2027-05-08', 'BATCH-20260908-49302D', 1, 'active', 8, 'Mary John', '2026-09-08 13:40:09', '2026-09-21 19:51:54'),
(27, 'Paracetamol 500mg', 'Analgesics', 'tablets', 480, 50, 50.00, 200.00, 'Medical Supplies Ltd', '2027-12-31', 'ARU-BATCH-PCM-20260909-0', 2, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(28, 'Amoxicillin 500mg', 'Antibiotics', 'capsules', 200, 30, 150.00, 500.00, 'PharmaPlus Ltd', '2027-10-15', 'ARU-BATCH-AMOX-20260909-', 2, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(29, 'Ciprofloxacin 500mg', 'Antibiotics', 'tablets', 120, 20, 200.00, 800.00, 'PharmaPlus Ltd', '2027-11-30', 'ARU-BATCH-CIPRO-20260909', 2, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(30, 'Metronidazole 400mg', 'Antibiotics', 'tablets', 250, 25, 100.00, 400.00, 'Medical Supplies Ltd', '2027-09-20', 'ARU-BATCH-METRO-20260909', 2, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(31, 'Omeprazole 20mg', 'Antacids', 'capsules', 150, 15, 80.00, 350.00, 'HealthCare Ltd', '2028-01-15', 'ARU-BATCH-OME-20260909-0', 2, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(32, 'Ibuprofen 400mg', 'Analgesics', 'tablets', 200, 20, 60.00, 300.00, 'Medical Supplies Ltd', '2027-08-30', 'ARU-BATCH-IBU-20260909-0', 2, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(33, 'Diclofenac 50mg', 'Analgesics', 'tablets', 40, 18, 70.00, 350.00, 'PharmaPlus Ltd', '2027-07-25', 'ARU-BATCH-DICL-20260909-', 2, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(34, 'Cetirizine 10mg', 'Antihistamines', 'tablets', 130, 30, 30.00, 150.00, 'HealthCare Ltd', '2028-02-28', 'ARU-BATCH-CET-20260909-0', 2, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(35, 'Loratadine 10mg', 'Antihistamines', 'tablets', 250, 25, 40.00, 200.00, 'Medical Supplies Ltd', '2027-12-15', 'ARU-BATCH-LORA-20260909-', 2, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(36, 'Salbutamol Inhaler', 'Respiratory', 'inhaler', 0, 10, 2500.00, 5000.00, 'PharmaPlus Ltd', '2028-03-01', 'ARU-BATCH-SALB-20260909-', 2, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(37, 'Beclomethasone Inhaler', 'Respiratory', 'inhaler', 39, 10, 3000.00, 6500.00, 'HealthCare Ltd', '2028-04-15', 'ARU-BATCH-BECLO-20260909', 2, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(38, 'Amlodipine 5mg', 'Cardiovascular', 'tablets', 70, 15, 120.00, 450.00, 'Medical Supplies Ltd', '2027-11-30', 'ARU-BATCH-AML-20260909-0', 2, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(39, 'Enalapril 10mg', 'Cardiovascular', 'tablets', 100, 10, 130.00, 500.00, 'PharmaPlus Ltd', '2027-10-20', 'ARU-BATCH-ENAL-20260909-', 2, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(40, 'Hydrochlorothiazide 25mg', 'Cardiovascular', 'tablets', 130, 15, 80.00, 300.00, 'HealthCare Ltd', '2027-09-10', 'ARU-BATCH-HCTZ-20260909-', 2, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(41, 'Metformin 500mg', 'Antidiabetic', 'tablets', 200, 20, 100.00, 400.00, 'Medical Supplies Ltd', '2028-01-20', 'ARU-BATCH-MET-20260909-0', 2, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(42, 'Glibenclamide 5mg', 'Antidiabetic', 'tablets', 50, 15, 90.00, 350.00, 'PharmaPlus Ltd', '2027-12-05', 'ARU-BATCH-GLIB-20260909-', 2, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(43, 'Furosemide 40mg', 'Diuretics', 'tablets', 100, 10, 70.00, 250.00, 'HealthCare Ltd', '2027-08-15', 'ARU-BATCH-FURO-20260909-', 2, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(44, 'Diazepam 5mg', 'Sedatives', 'tablets', 80, 10, 150.00, 600.00, 'Medical Supplies Ltd', '2028-02-10', 'ARU-BATCH-DIAZ-20260909-', 2, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(45, 'Amitriptyline 25mg', 'Antidepressants', 'tablets', 0, 10, 120.00, 450.00, 'PharmaPlus Ltd', '2027-09-25', 'ARU-BATCH-AMIT-20260909-', 2, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(46, 'Multivitamin Tablets', 'Vitamins', 'tablets', 400, 40, 30.00, 150.00, 'HealthCare Ltd', '2028-06-30', 'ARU-BATCH-MVIT-20260909-', 2, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(47, 'ALBENDAZOLE', 'Antibiotics', 'pcs', 91, 100, 1300.00, 3000.00, 'AVANA MEDICS', '2028-09-08', 'ARU-BATCH-20260908-A196B', 2, 'active', 8, 'Mary John', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(48, 'AMOXILINE', 'Antacids', 'pcs', 30, 20, 1000.00, 2500.00, 'AVANA MEDICS', '2027-05-08', 'ARU-BATCH-20260908-49302', 2, 'active', 8, 'Mary John', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(58, 'Paracetamol 500mg', 'Analgesics', 'tablets', 480, 50, 50.00, 200.00, 'Medical Supplies Ltd', '2027-12-31', 'DAR-BATCH-PCM-20260909-0', 3, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(59, 'Amoxicillin 500mg', 'Antibiotics', 'capsules', 200, 30, 150.00, 500.00, 'PharmaPlus Ltd', '2027-10-15', 'DAR-BATCH-AMOX-20260909-', 3, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(60, 'Ciprofloxacin 500mg', 'Antibiotics', 'tablets', 120, 20, 200.00, 800.00, 'PharmaPlus Ltd', '2027-11-30', 'DAR-BATCH-CIPRO-20260909', 3, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(61, 'Metronidazole 400mg', 'Antibiotics', 'tablets', 250, 25, 100.00, 400.00, 'Medical Supplies Ltd', '2027-09-20', 'DAR-BATCH-METRO-20260909', 3, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(62, 'Omeprazole 20mg', 'Antacids', 'capsules', 150, 15, 80.00, 350.00, 'HealthCare Ltd', '2028-01-15', 'DAR-BATCH-OME-20260909-0', 3, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(63, 'Ibuprofen 400mg', 'Analgesics', 'tablets', 200, 20, 60.00, 300.00, 'Medical Supplies Ltd', '2027-08-30', 'DAR-BATCH-IBU-20260909-0', 3, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(64, 'Diclofenac 50mg', 'Analgesics', 'tablets', 40, 18, 70.00, 350.00, 'PharmaPlus Ltd', '2027-07-25', 'DAR-BATCH-DICL-20260909-', 3, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(65, 'Cetirizine 10mg', 'Antihistamines', 'tablets', 130, 30, 30.00, 150.00, 'HealthCare Ltd', '2028-02-28', 'DAR-BATCH-CET-20260909-0', 3, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(66, 'Loratadine 10mg', 'Antihistamines', 'tablets', 250, 25, 40.00, 200.00, 'Medical Supplies Ltd', '2027-12-15', 'DAR-BATCH-LORA-20260909-', 3, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(67, 'Salbutamol Inhaler', 'Respiratory', 'inhaler', 0, 10, 2500.00, 5000.00, 'PharmaPlus Ltd', '2028-03-01', 'DAR-BATCH-SALB-20260909-', 3, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(68, 'Beclomethasone Inhaler', 'Respiratory', 'inhaler', 39, 10, 3000.00, 6500.00, 'HealthCare Ltd', '2028-04-15', 'DAR-BATCH-BECLO-20260909', 3, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(69, 'Amlodipine 5mg', 'Cardiovascular', 'tablets', 70, 15, 120.00, 450.00, 'Medical Supplies Ltd', '2027-11-30', 'DAR-BATCH-AML-20260909-0', 3, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(70, 'Enalapril 10mg', 'Cardiovascular', 'tablets', 100, 10, 130.00, 500.00, 'PharmaPlus Ltd', '2027-10-20', 'DAR-BATCH-ENAL-20260909-', 3, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(71, 'Hydrochlorothiazide 25mg', 'Cardiovascular', 'tablets', 130, 15, 80.00, 300.00, 'HealthCare Ltd', '2027-09-10', 'DAR-BATCH-HCTZ-20260909-', 3, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(72, 'Metformin 500mg', 'Antidiabetic', 'tablets', 200, 20, 100.00, 400.00, 'Medical Supplies Ltd', '2028-01-20', 'DAR-BATCH-MET-20260909-0', 3, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(73, 'Glibenclamide 5mg', 'Antidiabetic', 'tablets', 50, 15, 90.00, 350.00, 'PharmaPlus Ltd', '2027-12-05', 'DAR-BATCH-GLIB-20260909-', 3, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(74, 'Furosemide 40mg', 'Diuretics', 'tablets', 100, 10, 70.00, 250.00, 'HealthCare Ltd', '2027-08-15', 'DAR-BATCH-FURO-20260909-', 3, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(75, 'Diazepam 5mg', 'Sedatives', 'tablets', 80, 10, 150.00, 600.00, 'Medical Supplies Ltd', '2028-02-10', 'DAR-BATCH-DIAZ-20260909-', 3, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(76, 'Amitriptyline 25mg', 'Antidepressants', 'tablets', 0, 10, 120.00, 450.00, 'PharmaPlus Ltd', '2027-09-25', 'DAR-BATCH-AMIT-20260909-', 3, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(77, 'Multivitamin Tablets', 'Vitamins', 'tablets', 400, 40, 30.00, 150.00, 'HealthCare Ltd', '2028-06-30', 'DAR-BATCH-MVIT-20260909-', 3, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(78, 'ALBENDAZOLE', 'Antibiotics', 'pcs', 91, 100, 1300.00, 3000.00, 'AVANA MEDICS', '2028-09-08', 'DAR-BATCH-20260908-A196B', 3, 'active', 8, 'Mary John', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(79, 'AMOXILINE', 'Antacids', 'pcs', 30, 20, 1000.00, 2500.00, 'AVANA MEDICS', '2027-05-08', 'DAR-BATCH-20260908-49302', 3, 'active', 8, 'Mary John', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(89, 'Paracetamol 500mg', 'Analgesics', 'tablets', 480, 50, 50.00, 200.00, 'Medical Supplies Ltd', '2027-12-31', 'KAH-BATCH-PCM-20260909-0', 4, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(90, 'Amoxicillin 500mg', 'Antibiotics', 'capsules', 200, 30, 150.00, 500.00, 'PharmaPlus Ltd', '2027-10-15', 'KAH-BATCH-AMOX-20260909-', 4, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(91, 'Ciprofloxacin 500mg', 'Antibiotics', 'tablets', 120, 20, 200.00, 800.00, 'PharmaPlus Ltd', '2027-11-30', 'KAH-BATCH-CIPRO-20260909', 4, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(92, 'Metronidazole 400mg', 'Antibiotics', 'tablets', 250, 25, 100.00, 400.00, 'Medical Supplies Ltd', '2027-09-20', 'KAH-BATCH-METRO-20260909', 4, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(93, 'Omeprazole 20mg', 'Antacids', 'capsules', 150, 15, 80.00, 350.00, 'HealthCare Ltd', '2028-01-15', 'KAH-BATCH-OME-20260909-0', 4, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(94, 'Ibuprofen 400mg', 'Analgesics', 'tablets', 200, 20, 60.00, 300.00, 'Medical Supplies Ltd', '2027-08-30', 'KAH-BATCH-IBU-20260909-0', 4, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(95, 'Diclofenac 50mg', 'Analgesics', 'tablets', 40, 18, 70.00, 350.00, 'PharmaPlus Ltd', '2027-07-25', 'KAH-BATCH-DICL-20260909-', 4, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(96, 'Cetirizine 10mg', 'Antihistamines', 'tablets', 130, 30, 30.00, 150.00, 'HealthCare Ltd', '2028-02-28', 'KAH-BATCH-CET-20260909-0', 4, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(97, 'Loratadine 10mg', 'Antihistamines', 'tablets', 250, 25, 40.00, 200.00, 'Medical Supplies Ltd', '2027-12-15', 'KAH-BATCH-LORA-20260909-', 4, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(98, 'Salbutamol Inhaler', 'Respiratory', 'inhaler', 0, 10, 2500.00, 5000.00, 'PharmaPlus Ltd', '2028-03-01', 'KAH-BATCH-SALB-20260909-', 4, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(99, 'Beclomethasone Inhaler', 'Respiratory', 'inhaler', 39, 10, 3000.00, 6500.00, 'HealthCare Ltd', '2028-04-15', 'KAH-BATCH-BECLO-20260909', 4, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(100, 'Amlodipine 5mg', 'Cardiovascular', 'tablets', 70, 15, 120.00, 450.00, 'Medical Supplies Ltd', '2027-11-30', 'KAH-BATCH-AML-20260909-0', 4, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(101, 'Enalapril 10mg', 'Cardiovascular', 'tablets', 100, 10, 130.00, 500.00, 'PharmaPlus Ltd', '2027-10-20', 'KAH-BATCH-ENAL-20260909-', 4, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(102, 'Hydrochlorothiazide 25mg', 'Cardiovascular', 'tablets', 130, 15, 80.00, 300.00, 'HealthCare Ltd', '2027-09-10', 'KAH-BATCH-HCTZ-20260909-', 4, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(103, 'Metformin 500mg', 'Antidiabetic', 'tablets', 200, 20, 100.00, 400.00, 'Medical Supplies Ltd', '2028-01-20', 'KAH-BATCH-MET-20260909-0', 4, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(104, 'Glibenclamide 5mg', 'Antidiabetic', 'tablets', 50, 15, 90.00, 350.00, 'PharmaPlus Ltd', '2027-12-05', 'KAH-BATCH-GLIB-20260909-', 4, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(105, 'Furosemide 40mg', 'Diuretics', 'tablets', 100, 10, 70.00, 250.00, 'HealthCare Ltd', '2027-08-15', 'KAH-BATCH-FURO-20260909-', 4, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(106, 'Diazepam 5mg', 'Sedatives', 'tablets', 80, 10, 150.00, 600.00, 'Medical Supplies Ltd', '2028-02-10', 'KAH-BATCH-DIAZ-20260909-', 4, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(107, 'Amitriptyline 25mg', 'Antidepressants', 'tablets', 0, 10, 120.00, 450.00, 'PharmaPlus Ltd', '2027-09-25', 'KAH-BATCH-AMIT-20260909-', 4, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(108, 'Multivitamin Tablets', 'Vitamins', 'tablets', 400, 40, 30.00, 150.00, 'HealthCare Ltd', '2028-06-30', 'KAH-BATCH-MVIT-20260909-', 4, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(109, 'ALBENDAZOLE', 'Antibiotics', 'pcs', 91, 100, 1300.00, 3000.00, 'AVANA MEDICS', '2028-09-08', 'KAH-BATCH-20260908-A196B', 4, 'active', 8, 'Mary John', '2026-09-18 20:23:28', '2026-09-18 20:23:28'),
(110, 'AMOXILINE', 'Antacids', 'pcs', 30, 20, 1000.00, 2500.00, 'AVANA MEDICS', '2027-05-08', 'KAH-BATCH-20260908-49302', 4, 'active', 8, 'Mary John', '2026-09-18 20:23:28', '2026-09-18 20:23:28');

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
(30, 1, NULL, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. ERICK is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 1, '2026-08-25 20:28:33', '2026-09-15 14:52:58'),
(31, 3, NULL, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. ERICK is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-08-25 20:28:33', '2026-08-25 20:28:33'),
(32, 10, NULL, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. ERICK is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 1, '2026-08-25 20:28:34', '2026-08-29 12:56:45'),
(33, 11, NULL, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. ERICK is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-08-25 20:28:34', '2026-08-25 20:28:34'),
(34, 12, NULL, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. ERICK is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-08-25 20:28:34', '2026-08-25 20:28:34'),
(35, 1, NULL, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. ERICK is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 1, '2026-08-25 20:28:34', '2026-09-15 14:52:58'),
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
(115, 1, 1, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr.ERICK JOHN is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 1, '2026-08-29 16:55:47', '2026-09-15 14:52:58'),
(116, 3, 1, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr.ERICK JOHN is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-08-29 16:55:47', '2026-08-29 16:55:47'),
(117, 10, 1, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr.ERICK JOHN is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-08-29 16:55:48', '2026-08-29 16:55:48'),
(118, 11, 1, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr.ERICK JOHN is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-08-29 16:55:48', '2026-08-29 16:55:48'),
(119, 12, 1, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr.ERICK JOHN is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-08-29 16:55:48', '2026-08-29 16:55:48'),
(120, 1, 1, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr.ERICK JOHN is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 1, '2026-08-29 16:55:48', '2026-09-15 14:52:58'),
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
(179, 1, 1, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr.ERICK JOHN is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 1, '2026-09-04 22:15:03', '2026-09-15 14:52:58'),
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
(193, 1, 1, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr.ERICK JOHN is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 1, '2026-09-05 14:19:48', '2026-09-15 14:52:47'),
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
(223, 3, 1, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr.ERICK JOHN is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-09-09 15:46:46', '2026-09-09 15:46:46'),
(224, 10, 1, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr.ERICK JOHN is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-09-09 15:46:47', '2026-09-09 15:46:47'),
(225, 11, 1, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr.ERICK JOHN is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-09-09 15:46:47', '2026-09-09 15:46:47'),
(226, 12, 1, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr.ERICK JOHN is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-09-09 15:46:47', '2026-09-09 15:46:47'),
(228, 3, 1, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr.ERICK JOHN is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-09-09 15:46:47', '2026-09-09 15:46:47'),
(229, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260910-0061-1474 (TSh 10,000) for patient ID #61 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-10 09:48:07', '2026-09-10 09:48:07'),
(230, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260910-0062-5872 (TSh 10,000) for patient JACKSON MYULA - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-10 10:30:45', '2026-09-10 10:30:45'),
(231, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260910-0060-3094 (TSh 30,000) for patient ID #60 - Specialist Consultation', '', 'cashier_dashboard.php', 0, '2026-09-10 10:33:14', '2026-09-10 10:33:14'),
(232, 16, 1, NULL, '🧪 Lab Test Bill Created', 'Lab Test bill #BILL-LAB-20260910-0058-9649 (TSh 11,000) for patient ID #58 - BLOOD, BLOOD', '', 'cashier_dashboard.php', 0, '2026-09-10 10:33:50', '2026-09-10 10:33:50'),
(233, 16, 1, NULL, '🧪 Lab Test Bill Created', 'Lab Test bill #BILL-LAB-20260910-0061-1571 (TSh 40,000) for patient ID #61 - Blood Glucose (Fasting), Liver Function Test (LFT), Blood Check', '', 'cashier_dashboard.php', 0, '2026-09-10 12:08:52', '2026-09-10 12:08:52'),
(234, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260910-0062-8487 (TSh 10,000) for patient ID #62 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-10 12:29:24', '2026-09-10 12:29:24'),
(235, 16, 1, NULL, '🧪 Lab Test Bill Created', 'Lab Test bill #BILL-LAB-20260910-0060-3081 (TSh 7,000) for patient ID #60 - Blood Check', '', 'cashier_dashboard.php', 0, '2026-09-10 12:34:32', '2026-09-10 12:34:32'),
(236, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260910-0059-6858 (TSh 100,000) for patient ID #59 - visit_mpya', '', 'cashier_dashboard.php', 0, '2026-09-10 12:35:40', '2026-09-10 12:35:40'),
(237, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260910-0058-7187 (TSh 10,000) for patient ID #58 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-10 12:36:36', '2026-09-10 12:36:36'),
(238, 18, 1, NULL, '📅 New Appointment Scheduled', 'New appointment for patient: MARTHA KIMAMALA on Sep 30, 2026 06:15 PM', 'info', NULL, 0, '2026-09-15 15:15:58', '2026-09-15 15:15:58'),
(239, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260915-0061-7501 (TSh 10,000) for patient ID #61', 'info', 'cashier_dashboard.php', 0, '2026-09-15 15:44:33', '2026-09-15 15:44:33'),
(240, 4, 1, NULL, '📅 New Appointment Scheduled', 'New appointment for patient: MUSSA MONGI MASNGI on Sep 23, 2026 09:56 PM', 'info', NULL, 0, '2026-09-15 18:57:10', '2026-09-15 18:57:10'),
(241, 16, 1, NULL, '🧪 Lab Test Bill Created', 'Lab Test bill #BILL-LAB-20260917-0060-7238 (TSh 23,000) for patient ID #60 - Blood Glucose (Random), ECG (Electrocardiogram)', '', 'cashier_dashboard.php', 0, '2026-09-17 14:31:17', '2026-09-17 14:31:17'),
(242, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260917-0047-4910 (TSh 10,000) for patient ID #47 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-17 15:07:58', '2026-09-17 15:07:58'),
(243, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260917-0059-8560 (TSh 10,000) for patient ID #59 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-17 17:38:20', '2026-09-17 17:38:20'),
(244, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260917-0060-1385 (TSh 10,000) for patient ID #60 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-17 20:46:16', '2026-09-17 20:46:16'),
(245, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260917-0052-5784 (TSh 25,000) for patient JUDITH SOLOMONI (ID: P-2026-01-0006) — Consultation-B', '', '/dispensary_system/frontend/pages/cashier/dashboard.php', 0, '2026-09-17 20:47:38', '2026-09-17 20:47:38'),
(246, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260917-0051-2840 (TSh 10,000) for patient ID #51 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-17 20:54:31', '2026-09-17 20:54:31'),
(247, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260917-0062-5174 (TSh 10,000) for patient ID #62 - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-17 21:08:50', '2026-09-17 21:08:50');
INSERT INTO `notifications` (`id`, `user_id`, `branch_id`, `patient_id`, `title`, `message`, `type`, `link`, `is_read`, `created_at`, `updated_at`) VALUES
(248, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260917-0061-8436 (TSh 25,000) for patient MUSSA MONGI MASNGI (ID: P-2026-01-0015) — Consultation-B', '', '/dispensary_system/frontend/pages/cashier/dashboard.php', 0, '2026-09-17 21:09:23', '2026-09-17 21:09:23'),
(249, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260918-0061-2688 (TSh 10,000) for patient ID #61', '', 'cashier_dashboard.php', 0, '2026-09-18 16:18:44', '2026-09-18 16:18:44'),
(250, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260918-0047-4931 (TSh 10,000) for patient ID #47', '', 'cashier_dashboard.php', 0, '2026-09-18 18:10:38', '2026-09-18 18:10:38'),
(251, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260918-0063-3226 (TSh 10,000) for patient SAMSON MYULA - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-18 18:18:26', '2026-09-18 18:18:26'),
(252, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260919-0063-3474 (TSh 10,000) for patient ID #63', '', 'cashier_dashboard.php', 0, '2026-09-19 14:33:28', '2026-09-19 14:33:28'),
(253, 16, 1, NULL, '🧪 Lab Test Bill Created', 'Lab Test bill #BILL-LAB-20260919-0060-1991 (TSh 30,000) for patient ID #60 - Lipid Profile, Blood Check, SS', '', 'cashier_dashboard.php', 0, '2026-09-19 14:34:47', '2026-09-19 14:34:47'),
(254, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260919-0061-5023 (TSh 10,000) for patient ID #61', '', 'cashier_dashboard.php', 0, '2026-09-19 19:24:45', '2026-09-19 19:24:45'),
(255, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260919-0063-7158 (TSh 10,000) for patient ID #63', '', 'cashier_dashboard.php', 0, '2026-09-19 20:02:12', '2026-09-19 20:02:12'),
(256, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260919-0062-9637 (TSh 10,000) for patient ID #62', '', 'cashier_dashboard.php', 0, '2026-09-19 20:03:05', '2026-09-19 20:03:05'),
(257, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260919-0061-6655 (TSh 10,000) for patient ID #61', '', 'cashier_dashboard.php', 0, '2026-09-19 20:04:15', '2026-09-19 20:04:15'),
(258, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260919-0063-5880 (TSh 10,000) for patient ID #63', '', 'cashier_dashboard.php', 0, '2026-09-19 21:37:01', '2026-09-19 21:37:01'),
(259, 16, 1, NULL, '🧪 Lab Test Bill Created', 'Lab Test bill #BILL-LAB-20260919-0060-1165 (TSh 38,000) for patient ID #60 - Blood Glucose (Random), Lipid Profile, Blood Check, SS', '', 'cashier_dashboard.php', 0, '2026-09-19 21:54:14', '2026-09-19 21:54:14'),
(260, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260920-0060-8198 (TSh 10,000) for patient ID #60', '', 'cashier_dashboard.php', 0, '2026-09-20 15:59:19', '2026-09-20 15:59:19'),
(261, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260920-0059-6676 (TSh 10,000) for patient ID #59', '', 'cashier_dashboard.php', 0, '2026-09-20 16:02:07', '2026-09-20 16:02:07'),
(262, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260920-0060-4508 (TSh 10,000) for patient ID #60', '', 'cashier_dashboard.php', 0, '2026-09-20 16:46:16', '2026-09-20 16:46:16'),
(263, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260920-0063-2389 (TSh 10,000) for patient ID #63', '', 'cashier_dashboard.php', 0, '2026-09-20 16:46:40', '2026-09-20 16:46:40'),
(264, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260920-0063-3528 (TSh 10,000) for patient ID #63', '', 'cashier_dashboard.php', 0, '2026-09-20 19:24:56', '2026-09-20 19:24:56'),
(265, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260920-0062-9661 (TSh 10,000) for patient ID #62', '', 'cashier_dashboard.php', 0, '2026-09-20 19:25:14', '2026-09-20 19:25:14'),
(266, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260920-0061-4072 (TSh 10,000) for patient ID #61', '', 'cashier_dashboard.php', 0, '2026-09-20 19:25:35', '2026-09-20 19:25:35'),
(267, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260920-0060-2341 (TSh 10,000) for patient ID #60', '', 'cashier_dashboard.php', 0, '2026-09-20 19:25:58', '2026-09-20 19:25:58'),
(268, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260920-0063-9028 (TSh 10,000) for patient ID #63', '', 'cashier_dashboard.php', 0, '2026-09-20 21:04:47', '2026-09-20 21:04:47'),
(269, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260920-0062-3069 (TSh 10,000) for patient ID #62', '', 'cashier_dashboard.php', 0, '2026-09-20 21:05:09', '2026-09-20 21:05:09'),
(270, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260921-0059-6982 (TSh 10,000) for patient ID #59', '', 'cashier_dashboard.php', 0, '2026-09-21 14:27:01', '2026-09-21 14:27:01'),
(271, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260921-0052-6929 (TSh 10,000) for patient ID #52', '', 'cashier_dashboard.php', 0, '2026-09-21 14:27:32', '2026-09-21 14:27:32'),
(272, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260921-0062-3916 (TSh 10,000) for patient ID #62', '', 'cashier_dashboard.php', 0, '2026-09-21 18:09:54', '2026-09-21 18:09:54'),
(273, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260921-0063-4013 (TSh 10,000) for patient ID #63', '', 'cashier_dashboard.php', 0, '2026-09-21 18:10:12', '2026-09-21 18:10:12'),
(274, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260921-0061-7403 (TSh 10,000) for patient ID #61', '', 'cashier_dashboard.php', 0, '2026-09-21 18:10:29', '2026-09-21 18:10:29'),
(275, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260921-0060-2771 (TSh 10,000) for patient ID #60', '', 'cashier_dashboard.php', 0, '2026-09-21 18:10:54', '2026-09-21 18:10:54'),
(276, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260921-0063-6003 (TSh 10,000) for patient ID #63', '', 'cashier_dashboard.php', 0, '2026-09-21 18:53:11', '2026-09-21 18:53:11'),
(277, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260921-0062-1124 (TSh 10,000) for patient ID #62', '', 'cashier_dashboard.php', 0, '2026-09-21 18:53:42', '2026-09-21 18:53:42'),
(278, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260921-0061-6405 (TSh 10,000) for patient ID #61', '', 'cashier_dashboard.php', 0, '2026-09-21 18:54:01', '2026-09-21 18:54:01'),
(279, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260921-0063-3374 (TSh 10,000) for patient ID #63', '', 'cashier_dashboard.php', 0, '2026-09-21 19:38:39', '2026-09-21 19:38:39'),
(280, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260921-0062-9458 (TSh 10,000) for patient ID #62', '', 'cashier_dashboard.php', 0, '2026-09-21 19:38:57', '2026-09-21 19:38:57'),
(281, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260921-0061-9245 (TSh 10,000) for patient ID #61', '', 'cashier_dashboard.php', 0, '2026-09-21 19:39:13', '2026-09-21 19:39:13'),
(282, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260921-0060-4072 (TSh 10,000) for patient ID #60', '', 'cashier_dashboard.php', 0, '2026-09-21 19:39:30', '2026-09-21 19:39:30'),
(283, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260921-0059-3209 (TSh 10,000) for patient ID #59', '', 'cashier_dashboard.php', 0, '2026-09-21 19:39:49', '2026-09-21 19:39:49');

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
(33, 'OTC-20260919-7626', 'Walk-in Customer', '', NULL, 59000.00, 500.00, 2000.00, 'Premium added', 60500.00, NULL, 'cash', 'paid', 7, 1, 'Paid by Pharmacy (Self) - Customer: Walk-in Customer | Premium: TSh 2,000 - Premium added', '2026-09-19 17:30:38', '2026-09-19 17:37:48'),
(34, 'OTC-20260921-6013', 'Walk-in Customer', '', NULL, 64500.00, 500.00, 0.00, '', 64000.00, NULL, 'cash', 'paid', 8, 1, 'Paid by Pharmacy (Self) - Customer: Walk-in Customer', '2026-09-21 10:13:03', '2026-09-21 10:13:03'),
(35, 'OTC-20260921-5315', 'KELVIN JOHN', '0710111212', NULL, 67000.00, 0.00, 3000.00, 'Premium added', 70000.00, NULL, 'm-pesa', 'paid', 8, 1, 'Paid by Pharmacy (Self) - Customer: KELVIN JOHN | Premium: TSh 3,000 - Premium added', '2026-09-21 10:16:03', '2026-09-21 10:16:03');

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
  `dosage` varchar(100) DEFAULT NULL,
  `frequency` varchar(100) DEFAULT NULL,
  `route` varchar(100) DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `otc_sale_items`
--

INSERT INTO `otc_sale_items` (`id`, `sale_id`, `patient_id`, `inventory_id`, `medicine_name`, `item_name`, `quantity`, `unit_price`, `total_price`, `dosage`, `frequency`, `route`, `instructions`, `branch_id`, `created_at`) VALUES
(52, 33, NULL, NULL, NULL, 'Ciprofloxacin 500mg', 5, 800.00, 4000.00, '', '', '', '', 1, '2026-09-19 17:30:38'),
(54, 33, NULL, NULL, NULL, 'Diclofenac 50mg', 5, 350.00, 1750.00, '', '', '', '', 1, '2026-09-19 17:30:38'),
(55, 33, NULL, NULL, NULL, 'Cetirizine 10mg', 5, 150.00, 750.00, '', '', '', '', 1, '2026-09-19 17:30:38'),
(56, 33, NULL, NULL, NULL, 'Beclomethasone Inhaler', 5, 6500.00, 32500.00, '', '', '', '', 1, '2026-09-19 17:30:38'),
(57, 33, NULL, NULL, NULL, 'Amlodipine 5mg', 5, 450.00, 2250.00, '', '', '', '', 1, '2026-09-19 17:30:38'),
(58, 33, NULL, NULL, NULL, 'Hydrochlorothiazide 25mg', 5, 300.00, 1500.00, '', '', '', '', 1, '2026-09-19 17:30:38'),
(59, 33, NULL, NULL, NULL, 'Furosemide 40mg', 5, 250.00, 1250.00, '', '', '', '', 1, '2026-09-19 17:30:38'),
(60, 33, NULL, NULL, NULL, 'ALBENDAZOLE', 5, 3000.00, 15000.00, '', '', '', '', 1, '2026-09-19 17:30:38'),
(61, 34, NULL, NULL, NULL, 'Amoxicillin 500mg', 10, 500.00, 5000.00, '1 tablet', 'Every 12 hours', 'Inhalation', '2x daily', 1, '2026-09-21 10:13:03'),
(62, 34, NULL, NULL, NULL, 'Amitriptyline 25mg', 10, 450.00, 4500.00, '2 tablets', 'Morning only', 'Transdermal', '2x daily, 1x daily', 1, '2026-09-21 10:13:03'),
(63, 34, NULL, NULL, NULL, 'ALBENDAZOLE', 10, 3000.00, 30000.00, '2 tablets', 'Evening only', 'Otic (Ear)', '2x daily, Morning dose', 1, '2026-09-21 10:13:03'),
(64, 34, NULL, NULL, NULL, 'AMOXILINE', 10, 2500.00, 25000.00, '5ml', 'Evening only', 'Otic (Ear)', '2x daily, After dinner', 1, '2026-09-21 10:13:03'),
(65, 35, NULL, NULL, NULL, 'Amoxicillin 500mg', 5, 500.00, 2500.00, '2 tablets', 'Evening only', 'Transdermal', '', 1, '2026-09-21 10:16:03'),
(66, 35, NULL, NULL, NULL, 'Amitriptyline 25mg', 10, 450.00, 4500.00, '10ml', 'Evening only', 'Vaginal', '2x daily', 1, '2026-09-21 10:16:03'),
(67, 35, NULL, NULL, NULL, 'ALBENDAZOLE', 5, 3000.00, 15000.00, '10ml', 'Night only', 'Sublingual', 'After lunch', 1, '2026-09-21 10:16:03'),
(68, 35, NULL, NULL, NULL, 'AMOXILINE', 18, 2500.00, 45000.00, '2 tablets', 'As needed', 'Ophthalmic (Eye)', 'After lunch, 3x daily', 1, '2026-09-21 10:16:03');

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
  `registered_by` int(11) DEFAULT NULL,
  `registered_by_name` varchar(100) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `assigned_doctor_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `patient_id`, `full_name`, `date_of_birth`, `gender`, `marital_status`, `phone`, `email`, `address`, `emergency_contact`, `blood_group`, `allergies`, `branch_id`, `registered_by`, `registered_by_name`, `created_by`, `assigned_doctor_id`, `created_at`, `updated_at`) VALUES
(47, 'P-2026-01-0001', 'MARTHA KIMAMALA', '2003-09-23', 'Female', 'Single', '0616171819', 'marthakimamala@gmail.com', 'DODOMA - KISASA SHELI', '0623693303', 'AB+', 'Penicillin, Sulfa Drugs', 1, NULL, NULL, 10, NULL, '2026-08-25 21:18:56', '2026-09-19 20:00:23'),
(48, 'P-2026-01-0002', 'IBRAHIM DOUMBIA', '2003-09-10', 'Male', 'Single', '0746512183', 'doumbia@gmail.com', 'KISASA SHELI', '0622682202', 'O+', 'Sulfa Drugs, Aspirin', 1, NULL, NULL, 10, NULL, '2026-08-25 22:00:07', '2026-09-19 20:00:23'),
(49, 'P-2026-01-0003', 'AGUSTINO VALENTINE', '2003-02-15', 'Male', 'Single', '0678552288', 'augustino@gmail.com', 'kiasa', '0678723', 'AB-', 'Sulfa Drugs, Soy', 1, NULL, NULL, 10, NULL, '2026-08-26 12:29:24', '2026-09-19 20:00:23'),
(50, 'P-2026-01-0004', 'KELVIN MSAFIRI', '2001-09-12', 'Male', '', '09876525', 'kelvin@gmail.com', 'kisasa', '0678723123', 'AB-', 'Penicillin, Sulfa Drugs', 1, NULL, NULL, 10, NULL, '2026-08-26 13:03:37', '2026-09-04 19:18:32'),
(51, 'P-2026-01-0005', 'CLEOFAS WILLIUM', '2001-07-18', 'Male', 'Single', '0746526253', 'jacksonmyula3@gmail.com', 'mtakumbuka', '067872311', 'AB-', 'Penicillin, Milk', 1, NULL, NULL, 11, NULL, '2026-08-26 18:36:46', '2026-09-19 20:00:23'),
(52, 'P-2026-01-0006', 'JUDITH SOLOMONI', '2002-04-09', 'Female', 'Single', '0678176542', 'judithsolomoni@gmail.com', '', '', 'O+', 'Penicillin, Milk', 1, NULL, NULL, 11, NULL, '2026-08-26 19:23:28', '2026-09-21 18:09:40'),
(53, 'P-2026-01-0007', 'MAGRETH CHAKUPEWA', '2002-05-19', 'Female', 'Married', '0987536818', 'magreth@gmail.com', '', '', 'B-', 'Penicillin, Milk', 1, NULL, NULL, 11, NULL, '2026-08-26 19:24:36', '2026-09-19 20:00:23'),
(54, 'P-2026-01-0008', 'CLEMENCY MTUKA', '2001-10-10', 'Male', 'Single', '0746526111', 'clemecy@gmail.com', 'mtakumbuka', '', 'B-', 'Ibuprofen', 1, NULL, NULL, 11, NULL, '2026-08-26 19:55:59', '2026-09-19 20:00:23'),
(55, 'P-2026-01-0009', 'ALPHONSE MABULA', '1998-02-12', 'Male', '', '0787615242', 'alphonce@gmail.com', '', '0678723133', 'AB-', 'Sulfa Drugs', 1, NULL, NULL, 11, NULL, '2026-08-26 19:59:21', '2026-09-19 20:00:23'),
(56, 'P-2026-01-0010', 'julieth kalinde', '2001-09-13', 'Male', '', '0789189123', 'juliath@gmail.com', '', '', 'AB+', 'Penicillin', 1, NULL, NULL, 11, NULL, '2026-08-26 20:07:21', '2026-09-19 20:00:23'),
(57, 'P-2026-01-0011', 'VICTORIA SALINGO', '2008-03-12', 'Male', 'Single', '074671827361', 'victoria@gmail.com', '', '', '', '', 1, NULL, NULL, 11, NULL, '2026-08-26 20:22:10', '2026-09-19 20:00:23'),
(58, 'P-2026-01-0012', 'AYUBU NZAL', '1992-08-12', 'Male', 'Married', '0765457899', 'ayubunzali@gmail.com', '', '', 'A+', '', 1, NULL, NULL, 11, NULL, '2026-08-26 20:35:50', '2026-09-17 15:15:18'),
(59, 'P-2026-01-0013', 'AMOSI NGOMENI', '2000-12-12', 'Male', 'Single', '0756176210', 'amosi@gmail.com', '', '', 'A+', '', 1, NULL, NULL, 11, 5, '2026-08-26 20:52:13', '2026-09-21 19:39:49'),
(60, 'P-2026-01-0014', 'ANDREW VICENT CHIKUPE', '1993-07-10', 'Male', '', '0746826243', 'endrew@gmail.com', 'mtakumbuka', '0678723129', 'B-', 'Aspirin', 1, NULL, NULL, 11, 5, '2026-08-26 21:00:03', '2026-09-21 19:39:30'),
(61, 'P-2026-01-0015', 'MUSSA MONGI MASNGI', '2003-08-01', 'Male', 'Single', '0789878980', 'musa@gmail.com', '', '', '', 'Sulfa Drugs', 1, NULL, NULL, 11, 4, '2026-08-26 21:11:23', '2026-09-21 19:39:13'),
(62, 'P-2026-01-0016', 'JACKSON PIUS MYULA', '1999-06-09', 'Male', 'Single', '0678176542', 'jacksonmyula3@gmail.com', 'mtakumbuka', '', 'O+', '', 1, NULL, NULL, 10, 4, '2026-09-10 10:30:45', '2026-09-21 19:38:57'),
(63, 'P-2026-01-0017', 'SAMSON  PIUS MYULA', '2014-05-18', 'Male', 'Single', '0623693303', 'jacksonmyula773@gmail.com', 'Rukwa\r\nMtakumbuka', '', 'AB+', 'Sulfa Drugs, Soy', 1, 12, 'JUDITH SOLOMONI', 12, 4, '2026-09-18 18:18:26', '2026-09-21 19:38:39');

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
(6, 'DOC-20260915-9479', 53, NULL, 5, 1, 1, 'prescription', 'xray', 'SURGERY', '', 'DOC-20260915-9479.pdf', '/dispensary_system/frontend/assets/uploads/documents/DOC-20260915-9479.pdf', 105820, 'application/pdf', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 'active', '2026-09-15 17:34:24', '2026-09-15 18:34:24');

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

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `receipt_number`, `bill_id`, `patient_id`, `amount`, `payment_method`, `reference_number`, `notes`, `received_by`, `branch_id`, `received_at`, `updated_at`) VALUES
(163, 'RCP-20260921-8344', 376, 59, 500000.00, 'cash', NULL, 'Payment | Pharm Disc: TSh 0 | Cashier Disc: TSh 0 | Pharm Prem: TSh 4,000 | Cashier Prem: TSh 20,000 (Premium Charge)', 12, 1, '2026-09-21 19:55:21', '2026-09-21 19:55:21'),
(164, 'RCP-20260921-7717', 375, 60, 1100000.00, 'cash', NULL, 'Payment | Pharm Disc: TSh 0 | Cashier Disc: TSh 2,000 | Pharm Prem: TSh 500 | Cashier Prem: TSh 0 (Premium Charge)', 12, 1, '2026-09-21 19:55:51', '2026-09-21 19:55:51'),
(165, 'RCP-20260921-8961', 374, 61, 420000.00, 'cash', NULL, 'Payment | Pharm Disc: TSh 500 | Cashier Disc: TSh 700 | Pharm Prem: TSh 0 | Cashier Prem: TSh 5,000 (Premium Charge)', 12, 1, '2026-09-21 19:56:38', '2026-09-21 19:56:38'),
(166, 'RCP-20260921-3511', 373, 62, 360000.00, 'cash', NULL, 'Payment | Pharm Disc: TSh 0 | Cashier Disc: TSh 0 | Pharm Prem: TSh 7,000 | Cashier Prem: TSh 0 (Premium Charge)', 12, 1, '2026-09-21 19:57:05', '2026-09-21 19:57:05'),
(167, 'RCP-20260921-4784', 372, 63, 780000.00, 'cash', NULL, 'Payment | Pharm Disc: TSh 0 | Cashier Disc: TSh 0 | Pharm Prem: TSh 1,000 | Cashier Prem: TSh 3,000 (Premium Charge)', 12, 1, '2026-09-21 19:57:27', '2026-09-21 19:57:27');

--
-- Triggers `payments`
--
DELIMITER $$
CREATE TRIGGER `after_payment_delete` AFTER DELETE ON `payments` FOR EACH ROW BEGIN
    DECLARE total_paid DECIMAL(12,2);
    DECLARE bill_total DECIMAL(12,2);
    DECLARE new_status VARCHAR(20);
    
    SELECT COALESCE(SUM(amount), 0) INTO total_paid 
    FROM payments WHERE bill_id = OLD.bill_id;
    
    SELECT total_amount INTO bill_total 
    FROM bills WHERE id = OLD.bill_id;
    
    IF total_paid >= bill_total THEN
        SET new_status = 'paid';
    ELSEIF total_paid > 0 THEN
        SET new_status = 'partial';
    ELSE
        SET new_status = 'pending';
    END IF;
    
    UPDATE bills 
    SET paid_amount = total_paid,
        balance = bill_total - total_paid,
        status = new_status
    WHERE id = OLD.bill_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_payment_insert` AFTER INSERT ON `payments` FOR EACH ROW BEGIN
    DECLARE total_paid DECIMAL(12,2);
    DECLARE bill_total DECIMAL(12,2);
    DECLARE new_status VARCHAR(20);
    
    SELECT COALESCE(SUM(amount), 0) INTO total_paid 
    FROM payments WHERE bill_id = NEW.bill_id;
    
    SELECT total_amount INTO bill_total 
    FROM bills WHERE id = NEW.bill_id;
    
    IF total_paid >= bill_total THEN
        SET new_status = 'paid';
    ELSEIF total_paid > 0 THEN
        SET new_status = 'partial';
    ELSE
        SET new_status = 'pending';
    END IF;
    
    UPDATE bills 
    SET paid_amount = total_paid,
        balance = bill_total - total_paid,
        status = new_status
    WHERE id = NEW.bill_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_payment_update` AFTER UPDATE ON `payments` FOR EACH ROW BEGIN
    DECLARE total_paid DECIMAL(12,2);
    DECLARE bill_total DECIMAL(12,2);
    DECLARE new_status VARCHAR(20);
    
    SELECT COALESCE(SUM(amount), 0) INTO total_paid 
    FROM payments WHERE bill_id = NEW.bill_id;
    
    SELECT total_amount INTO bill_total 
    FROM bills WHERE id = NEW.bill_id;
    
    IF total_paid >= bill_total THEN
        SET new_status = 'paid';
    ELSEIF total_paid > 0 THEN
        SET new_status = 'partial';
    ELSE
        SET new_status = 'pending';
    END IF;
    
    UPDATE bills 
    SET paid_amount = total_paid,
        balance = bill_total - total_paid,
        status = new_status
    WHERE id = NEW.bill_id;
END
$$
DELIMITER ;

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
(207, 'PRES-20260921-0063-830', 201, 63, 4, 8, NULL, NULL, NULL, 'confirmed', 1, '2026-09-21 19:47:12', NULL, '2026-09-21 19:54:31'),
(208, 'PRES-20260921-0063-447', 201, 63, 4, 8, NULL, NULL, NULL, 'confirmed', 1, '2026-09-21 19:47:12', NULL, '2026-09-21 19:54:31'),
(209, 'PRES-20260921-0063-880', 201, 63, 4, 8, NULL, NULL, NULL, 'confirmed', 1, '2026-09-21 19:47:12', NULL, '2026-09-21 19:54:31'),
(210, 'PRES-20260921-0061-978', 203, 61, 4, 8, NULL, NULL, NULL, 'confirmed', 1, '2026-09-21 19:48:19', NULL, '2026-09-21 19:54:07'),
(211, 'PRES-20260921-0061-397', 203, 61, 4, 8, NULL, NULL, NULL, 'confirmed', 1, '2026-09-21 19:48:19', NULL, '2026-09-21 19:54:07'),
(212, 'PRES-20260921-0061-896', 203, 61, 4, 8, NULL, NULL, NULL, 'confirmed', 1, '2026-09-21 19:48:20', NULL, '2026-09-21 19:54:07'),
(213, 'PRES-20260921-0062-465', 202, 62, 4, 8, NULL, NULL, NULL, 'confirmed', 1, '2026-09-21 19:49:15', NULL, '2026-09-21 19:54:18'),
(214, 'PRES-20260921-0062-267', 202, 62, 4, 8, NULL, NULL, NULL, 'confirmed', 1, '2026-09-21 19:49:15', NULL, '2026-09-21 19:54:18'),
(215, 'PRES-20260921-0062-174', 202, 62, 4, 8, NULL, NULL, NULL, 'confirmed', 1, '2026-09-21 19:49:15', NULL, '2026-09-21 19:54:18'),
(216, 'PRES-20260921-0062-704', 202, 62, 4, 8, NULL, NULL, NULL, 'confirmed', 1, '2026-09-21 19:49:15', NULL, '2026-09-21 19:54:18'),
(217, 'PRES-20260921-0059-136', 205, 59, 5, 8, NULL, NULL, NULL, 'confirmed', 1, '2026-09-21 19:50:42', NULL, '2026-09-21 19:53:38'),
(218, 'PRES-20260921-0059-571', 205, 59, 5, 8, NULL, NULL, NULL, 'confirmed', 1, '2026-09-21 19:50:43', NULL, '2026-09-21 19:53:38'),
(219, 'PRES-20260921-0059-376', 205, 59, 5, 8, NULL, NULL, NULL, 'confirmed', 1, '2026-09-21 19:50:43', NULL, '2026-09-21 19:53:38'),
(220, 'PRES-20260921-0059-739', 205, 59, 5, 8, NULL, NULL, NULL, 'confirmed', 1, '2026-09-21 19:50:43', NULL, '2026-09-21 19:53:38'),
(221, 'PRES-20260921-0060-146', 204, 60, 5, 8, NULL, NULL, NULL, 'confirmed', 1, '2026-09-21 19:51:53', NULL, '2026-09-21 19:53:53'),
(222, 'PRES-20260921-0060-300', 204, 60, 5, 8, NULL, NULL, NULL, 'confirmed', 1, '2026-09-21 19:51:54', NULL, '2026-09-21 19:53:53'),
(223, 'PRES-20260921-0060-745', 204, 60, 5, 8, NULL, NULL, NULL, 'confirmed', 1, '2026-09-21 19:51:54', NULL, '2026-09-21 19:53:53');

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
(206, 207, 63, 25, 'ALBENDAZOLE', '300', '', 20, '7', 'Otic', 'Take with plenty of water', NULL, 'manual', NULL, NULL, 3000.00, 60000.00, 1, '2026-09-21 19:47:12', NULL, NULL),
(207, 208, 63, 12, 'Amlodipine 5mg', '300', '', 20, '7', 'Otic', 'Take with plenty of water', NULL, 'manual', NULL, NULL, 450.00, 9000.00, 1, '2026-09-21 19:47:12', NULL, NULL),
(208, 209, 63, 26, 'AMOXILINE', '300', '', 20, '7', 'Otic', 'Take with plenty of water', NULL, 'manual', NULL, NULL, 2500.00, 50000.00, 1, '2026-09-21 19:47:12', NULL, NULL),
(209, 210, 61, 25, 'ALBENDAZOLE', '300', 'With Meals', 30, '7', 'Sublingual', 'Take after meals', NULL, 'manual', NULL, NULL, 3000.00, 90000.00, 1, '2026-09-21 19:48:19', NULL, NULL),
(210, 211, 61, 12, 'Amlodipine 5mg', '300', 'With Meals', 30, '7', 'Sublingual', 'Take after meals', NULL, 'manual', NULL, NULL, 450.00, 13500.00, 1, '2026-09-21 19:48:19', NULL, NULL),
(211, 212, 61, 2, 'Amoxicillin 500mg', '300', 'With Meals', 30, '7', 'Sublingual', 'Take after meals', NULL, 'manual', NULL, NULL, 500.00, 15000.00, 1, '2026-09-21 19:48:20', NULL, NULL),
(212, 213, 62, 12, 'Amlodipine 5mg', '300', '', 10, '7', 'Ophthalmic', 'Take after meals', NULL, 'manual', NULL, NULL, 450.00, 4500.00, 1, '2026-09-21 19:49:15', NULL, NULL),
(213, 214, 62, 2, 'Amoxicillin 500mg', '300', '', 10, '7', 'Ophthalmic', 'Take after meals', NULL, 'manual', NULL, NULL, 500.00, 5000.00, 1, '2026-09-21 19:49:15', NULL, NULL),
(214, 215, 62, 25, 'ALBENDAZOLE', '300', '', 10, '7', 'Ophthalmic', 'Take after meals', NULL, 'manual', NULL, NULL, 3000.00, 30000.00, 1, '2026-09-21 19:49:15', NULL, NULL),
(215, 216, 62, 5, 'Omeprazole 20mg', '300', '', 10, '7', 'Ophthalmic', 'Take after meals', NULL, 'manual', NULL, NULL, 350.00, 3500.00, 1, '2026-09-21 19:49:15', NULL, NULL),
(216, 217, 59, 12, 'Amlodipine 5mg', '300', 'With Meals', 15, '7', 'Sublingual', 'Take before meals, Take at bedtime, Take with plenty of water', NULL, 'manual', NULL, NULL, 450.00, 6750.00, 1, '2026-09-21 19:50:42', NULL, NULL),
(217, 218, 59, 25, 'ALBENDAZOLE', '300', 'With Meals', 15, '7', 'Sublingual', 'Take before meals, Take at bedtime, Take with plenty of water', NULL, 'manual', NULL, NULL, 3000.00, 45000.00, 1, '2026-09-21 19:50:43', NULL, NULL),
(218, 219, 59, 26, 'AMOXILINE', '300', 'With Meals', 15, '7', 'Sublingual', 'Take before meals, Take at bedtime, Take with plenty of water', NULL, 'manual', NULL, NULL, 2500.00, 37500.00, 1, '2026-09-21 19:50:43', NULL, NULL),
(219, 220, 59, 19, 'Amitriptyline 25mg', '300', 'With Meals', 15, '7', 'Sublingual', 'Take before meals, Take at bedtime, Take with plenty of water', NULL, 'manual', NULL, NULL, 450.00, 6750.00, 1, '2026-09-21 19:50:43', NULL, NULL),
(220, 221, 60, 12, 'Amlodipine 5mg', '300', 'At Bedtime', 10, '7', 'Otic', 'Take after meals,Take with plenty of water', NULL, 'manual', NULL, NULL, 450.00, 4500.00, 1, '2026-09-21 19:51:53', NULL, NULL),
(221, 222, 60, 25, 'ALBENDAZOLE', '300', 'At Bedtime', 10, '7', 'Otic', 'Take after meals,Take with plenty of water', NULL, 'manual', NULL, NULL, 3000.00, 30000.00, 1, '2026-09-21 19:51:54', NULL, NULL),
(222, 223, 60, 26, 'AMOXILINE', '300', 'At Bedtime', 10, '7', 'Otic', 'Take after meals,Take with plenty of water', NULL, 'manual', NULL, NULL, 2500.00, 25000.00, 1, '2026-09-21 19:51:54', NULL, NULL);

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
(265, 201, 63, 4, 18, 'Cryotherapy', NULL, 'Dermatology', NULL, 20000.00, 'completed', 1, NULL, NULL, '2026-09-21 19:47:19', '2026-09-21 19:57:27'),
(266, 201, 63, 4, 15, 'ECG - Electrocardiogram', NULL, 'Cardiology', NULL, 15000.00, 'completed', 1, NULL, NULL, '2026-09-21 19:47:19', '2026-09-21 19:57:27'),
(267, 201, 63, 4, 20, 'Free - Nutrition Counseling', NULL, 'Nutrition', NULL, 0.00, 'completed', 1, NULL, NULL, '2026-09-21 19:47:19', '2026-09-21 19:57:27'),
(268, 201, 63, 4, 16, 'Spirometry - Lung Function', NULL, 'Pulmonology', NULL, 25000.00, 'completed', 1, NULL, NULL, '2026-09-21 19:47:19', '2026-09-21 19:57:27'),
(269, 203, 61, 4, 18, 'Cryotherapy', NULL, 'Dermatology', NULL, 20000.00, 'completed', 1, NULL, NULL, '2026-09-21 19:48:27', '2026-09-21 19:56:38'),
(270, 203, 61, 4, 16, 'Spirometry - Lung Function', NULL, 'Pulmonology', NULL, 25000.00, 'completed', 1, NULL, NULL, '2026-09-21 19:48:27', '2026-09-21 19:56:38'),
(271, 203, 61, 4, 1, 'WOUND DRESSING', NULL, 'Procedures', NULL, 45000.00, 'completed', 1, NULL, NULL, '2026-09-21 19:48:27', '2026-09-21 19:56:38'),
(272, 202, 62, 4, 18, 'Cryotherapy', NULL, 'Dermatology', NULL, 20000.00, 'completed', 1, NULL, NULL, '2026-09-21 19:49:22', '2026-09-21 19:57:05'),
(273, 202, 62, 4, 15, 'ECG - Electrocardiogram', NULL, 'Cardiology', NULL, 15000.00, 'completed', 1, NULL, NULL, '2026-09-21 19:49:22', '2026-09-21 19:57:05'),
(274, 202, 62, 4, 17, 'Minor Surgery - Excision', NULL, 'Surgery', NULL, 50000.00, 'completed', 1, NULL, NULL, '2026-09-21 19:49:23', '2026-09-21 19:57:05'),
(275, 202, 62, 4, 16, 'Spirometry - Lung Function', NULL, 'Pulmonology', NULL, 25000.00, 'completed', 1, NULL, NULL, '2026-09-21 19:49:23', '2026-09-21 19:57:05'),
(276, 205, 59, 5, 18, 'Cryotherapy', NULL, 'Dermatology', NULL, 20000.00, 'completed', 1, NULL, NULL, '2026-09-21 19:50:51', '2026-09-21 19:55:21'),
(277, 205, 59, 5, 15, 'ECG - Electrocardiogram', NULL, 'Cardiology', NULL, 15000.00, 'completed', 1, NULL, NULL, '2026-09-21 19:50:51', '2026-09-21 19:55:21'),
(278, 205, 59, 5, 20, 'Free - Nutrition Counseling', NULL, 'Nutrition', NULL, 0.00, 'completed', 1, NULL, NULL, '2026-09-21 19:50:51', '2026-09-21 19:55:21'),
(279, 205, 59, 5, 17, 'Minor Surgery - Excision', NULL, 'Surgery', NULL, 50000.00, 'completed', 1, NULL, NULL, '2026-09-21 19:50:51', '2026-09-21 19:55:21'),
(280, 205, 59, 5, 1, 'WOUND DRESSING', NULL, 'Procedures', NULL, 45000.00, 'completed', 1, NULL, NULL, '2026-09-21 19:50:51', '2026-09-21 19:55:21'),
(281, 204, 60, 5, 18, 'Cryotherapy', NULL, 'Dermatology', NULL, 20000.00, 'completed', 1, NULL, NULL, '2026-09-21 19:52:04', '2026-09-21 19:55:51'),
(282, 204, 60, 5, 15, 'ECG - Electrocardiogram', NULL, 'Cardiology', NULL, 15000.00, 'completed', 1, NULL, NULL, '2026-09-21 19:52:04', '2026-09-21 19:55:51'),
(283, 204, 60, 5, 17, 'Minor Surgery - Excision', NULL, 'Surgery', NULL, 50000.00, 'completed', 1, NULL, NULL, '2026-09-21 19:52:04', '2026-09-21 19:55:51'),
(284, 204, 60, 5, 1, 'WOUND DRESSING', NULL, 'Procedures', NULL, 45000.00, 'completed', 1, NULL, NULL, '2026-09-21 19:52:04', '2026-09-21 19:55:51'),
(285, 204, 60, 5, 12, 'Wound Dressing', NULL, 'Wound Care', NULL, 25000.00, 'completed', 1, NULL, NULL, '2026-09-21 19:52:04', '2026-09-21 19:55:51');

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
  `branch_id` int(11) DEFAULT NULL,
  `status` enum('IN_PROGRESS','COMPLETED','CANCELLED') DEFAULT 'IN_PROGRESS',
  `cancelled_reason` text DEFAULT NULL,
  `cancelled_by` int(11) DEFAULT NULL,
  `cancelled_by_name` varchar(100) DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
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

INSERT INTO `purchases` (`id`, `invoice_number`, `purchase_type`, `created_by`, `joined_users`, `created_by_name`, `branch_id`, `status`, `cancelled_reason`, `cancelled_by`, `cancelled_by_name`, `cancelled_at`, `total_items`, `total_quantity`, `total_buying_cost`, `total_selling_value`, `total_cost`, `completed_at`, `created_at`, `updated_at`) VALUES
(5, 'INV-MED-20260908-0001', 'medicine', 8, NULL, 'Mary John', 1, 'COMPLETED', NULL, NULL, NULL, NULL, 2, 600, 720000.00, 1700000.00, 0.00, '2026-09-08 16:42:37', '2026-09-08 16:38:02', '2026-09-15 16:19:53'),
(6, 'INV-EQP-20260908-0001', 'equipment', 7, NULL, 'LUCY MUSSA', 1, 'COMPLETED', NULL, NULL, NULL, NULL, 1, 90, 90000.00, 225000.00, 0.00, '2026-09-08 20:02:40', '2026-09-08 17:27:53', '2026-09-15 16:19:53'),
(7, 'INV-EQP-20260908-0002', 'equipment', 4, NULL, 'Dr.ERICK JOHN', 1, 'COMPLETED', NULL, NULL, NULL, NULL, 1, 100, 100000.00, 200000.00, 0.00, '2026-09-09 16:16:29', '2026-09-08 17:29:50', '2026-09-15 16:19:53'),
(8, 'INV-MED-20260908-0002', 'medicine', 1, NULL, 'System Admin', 1, 'COMPLETED', NULL, NULL, NULL, NULL, 4, 1200, 1476000.00, 3285000.00, 0.00, '2026-09-19 23:24:14', '2026-09-08 18:48:29', '2026-09-19 23:24:14'),
(9, 'INV-EQP-20260909-0001', 'equipment', 1, NULL, 'System Admin', 1, 'COMPLETED', NULL, NULL, NULL, NULL, 2, 500, 350000.00, 850000.00, 0.00, '2026-09-09 16:37:29', '2026-09-09 16:34:48', '2026-09-15 16:19:53'),
(10, 'INV-EQP-20260909-0002', 'equipment', 1, NULL, 'System Admin', 1, 'COMPLETED', NULL, NULL, NULL, NULL, 1, 100, 50000.00, 120000.00, 0.00, '2026-09-09 16:52:29', '2026-09-09 16:51:51', '2026-09-15 16:19:53'),
(11, 'INV-EQP-20260921-0001', 'equipment', 7, NULL, 'LUCY MUSSA', 1, 'COMPLETED', NULL, NULL, NULL, NULL, 5, 3000, 17460000.00, 50450000.00, 0.00, '2026-09-21 21:05:50', '2026-09-21 21:02:53', '2026-09-21 21:05:50'),
(12, 'INV-MED-20260921-0001', 'medicine', 7, NULL, 'LUCY MUSSA', 1, 'COMPLETED', NULL, NULL, NULL, NULL, 4, 1493, 924160.00, 2321850.00, 0.00, '2026-09-21 21:09:19', '2026-09-21 21:07:31', '2026-09-21 21:09:19');

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
(17, 10, 'equipment', NULL, NULL, 41, 100, 0.00, 500.00, 1200.00, 0.00, 0.00, 50000.00, 120000.00, 1, 'System Admin', '2026-09-09 16:52:22'),
(18, 8, 'medicine', NULL, NULL, 25, 300, 0.00, 1300.00, 3000.00, 0.00, 0.00, 390000.00, 900000.00, 1, 'System Admin', '2026-09-19 23:16:58'),
(19, 8, 'medicine', NULL, NULL, 26, 300, 0.00, 1000.00, 2500.00, 0.00, 0.00, 300000.00, 750000.00, 1, 'System Admin', '2026-09-19 23:19:27'),
(20, 8, 'medicine', NULL, NULL, 19, 300, 0.00, 120.00, 450.00, 0.00, 0.00, 36000.00, 135000.00, 7, 'LUCY MUSSA', '2026-09-19 23:22:37'),
(21, 8, 'medicine', NULL, NULL, 10, 300, 0.00, 2500.00, 5000.00, 0.00, 0.00, 750000.00, 1500000.00, 7, 'LUCY MUSSA', '2026-09-19 23:23:48'),
(22, 11, 'equipment', NULL, NULL, 7, 700, 0.00, 300.00, 1000.00, 0.00, 0.00, 210000.00, 700000.00, 7, 'LUCY MUSSA', '2026-09-21 21:03:25'),
(23, 11, 'equipment', NULL, NULL, 3, 800, 0.00, 10000.00, 25000.00, 0.00, 0.00, 8000000.00, 20000000.00, 7, 'LUCY MUSSA', '2026-09-21 21:03:57'),
(24, 11, 'equipment', NULL, NULL, 1, 600, 0.00, 5000.00, 15000.00, 0.00, 0.00, 3000000.00, 9000000.00, 7, 'LUCY MUSSA', '2026-09-21 21:04:25'),
(25, 11, 'equipment', NULL, NULL, 6, 500, 0.00, 500.00, 1500.00, 0.00, 0.00, 250000.00, 750000.00, 7, 'LUCY MUSSA', '2026-09-21 21:04:59'),
(26, 11, 'equipment', NULL, NULL, 5, 400, 0.00, 15000.00, 50000.00, 0.00, 0.00, 6000000.00, 20000000.00, 7, 'LUCY MUSSA', '2026-09-21 21:05:38'),
(27, 12, 'medicine', NULL, NULL, 25, 400, 0.00, 1300.00, 3000.00, 0.00, 0.00, 520000.00, 1200000.00, 7, 'LUCY MUSSA', '2026-09-21 21:07:50'),
(28, 12, 'medicine', NULL, NULL, 12, 493, 0.00, 120.00, 450.00, 0.00, 0.00, 59160.00, 221850.00, 7, 'LUCY MUSSA', '2026-09-21 21:08:20'),
(29, 12, 'medicine', NULL, NULL, 26, 300, 0.00, 1000.00, 2500.00, 0.00, 0.00, 300000.00, 750000.00, 7, 'LUCY MUSSA', '2026-09-21 21:08:48'),
(30, 12, 'medicine', NULL, NULL, 2, 300, 0.00, 150.00, 500.00, 0.00, 0.00, 45000.00, 150000.00, 7, 'LUCY MUSSA', '2026-09-21 21:09:11');

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
(23, 'REF-20260903-0060-665', NULL, 60, 4, 'external', NULL, 'MUHIMBILI', '', '', NULL, 'ttt', 'Expert Type: Other (Specify)\n\n', '', '', 'Other (Specify)', 'routine', 'referred', NULL, NULL, NULL, '2026-09-03 20:16:57', 4, 1, '2026-09-03 17:16:57', '2026-09-03 17:16:57', NULL, NULL, NULL),
(24, 'REF-20260916-0047-265', NULL, 47, 1, 'external', NULL, 'MUHIMBILI', '', '+255623693303', '', '', 'Expert Type: Rheumatology Expert\n\n', '', '', 'Rheumatology Expert', 'emergency', 'referred', NULL, NULL, NULL, '2026-09-16 01:58:49', 1, 1, '2026-09-15 22:58:49', '2026-09-15 22:58:49', NULL, NULL, NULL);

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
(6, 'pharmacy', 'Pharmacist - Medicine dispensing and inventory', 0, '2026-08-23 12:26:09', '2026-08-23 12:26:09'),
(7, 'audit', 'Audit - System audit and compliance', 0, '2026-09-15 19:49:54', '2026-09-15 19:49:54');

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
(5, 'Procedures', 'Medical procedures', 'fa-file-medical', '#0D9488', NULL, 0, 1, '2026-07-16 11:31:11', '2026-07-16 11:31:11'),
(6, 'Audit', 'System audit & compliance', 'fa-clipboard-check', '#DC2626', NULL, 0, 1, '2026-09-15 21:47:07', '2026-09-15 21:47:07');

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
(195, NULL, 41, 61, 'out', 1, 300, 299, 'lab_test', 67, 4, 1, 'Lab test: KICHOCHO - Equipment used', '2026-09-09 18:26:53'),
(196, NULL, 41, 61, 'out', 1, 299, 298, 'lab_test', 68, 4, 1, 'Lab test: KFADURO - Equipment used', '2026-09-10 10:04:59'),
(197, 1, NULL, NULL, 'out', 10, 0, 0, 'otc', 22, 7, 1, 'OTC Sale - PAID: OTC-20260915-0342 - Customer: Walk-in Customer', '2026-09-15 13:02:45'),
(198, 1, NULL, NULL, 'out', 10, 0, 0, 'otc', 22, 7, 1, 'OTC Sale - PAID: OTC-20260915-0342 - Customer: Walk-in Customer', '2026-09-15 13:02:45'),
(199, 10, NULL, NULL, 'out', 80, 0, 0, 'otc', 23, 7, 1, 'OTC Sale - PAID: OTC-20260915-5522 - Customer: Walk-in Customer', '2026-09-15 13:19:40'),
(200, 16, NULL, NULL, 'out', 50, 0, 0, 'otc', 24, 7, 1, 'OTC Sale - PAID: OTC-20260915-2084 - Customer: Walk-in Customer', '2026-09-15 13:21:48'),
(201, 16, NULL, NULL, 'out', 50, 0, 0, 'otc', 24, 7, 1, 'OTC Sale - PAID: OTC-20260915-2084 - Customer: Walk-in Customer', '2026-09-15 13:21:48'),
(202, 19, NULL, NULL, 'out', 50, 0, 0, 'otc', 25, 7, 1, 'OTC Sale - PAID: OTC-20260915-3780 - Customer: Walk-in Customer', '2026-09-15 13:23:32'),
(203, 19, NULL, NULL, 'out', 40, 0, 0, 'otc', 25, 7, 1, 'OTC Sale - PAID: OTC-20260915-3780 - Customer: Walk-in Customer', '2026-09-15 13:23:32'),
(204, 12, NULL, NULL, 'out', 10, 0, 0, 'otc', 26, 7, 1, 'OTC Sale - PAID: OTC-20260915-5919 - Customer: Walk-in Customer', '2026-09-15 13:41:48'),
(205, 25, NULL, NULL, 'out', 10, 0, 0, 'otc', 26, 7, 1, 'OTC Sale - PAID: OTC-20260915-5919 - Customer: Walk-in Customer', '2026-09-15 13:41:48'),
(206, 12, NULL, NULL, 'out', 9, 0, 0, 'otc', 27, 7, 1, 'OTC Sale - PAID: OTC-20260916-9573 - Customer: Walk-in Customer', '2026-09-16 11:56:00'),
(207, 25, NULL, NULL, 'out', 99, 0, 0, 'otc', 27, 7, 1, 'OTC Sale - PAID: OTC-20260916-9573 - Customer: Walk-in Customer', '2026-09-16 11:56:00'),
(208, 25, NULL, 58, 'out', 10, 200, 190, 'prescription', 97, 7, 1, 'Auto-dispensed - Rx #PRES-20260915-0058-229', '2026-09-17 15:37:26'),
(209, 12, NULL, 58, 'out', 10, 90, 80, 'prescription', 98, 7, 1, 'Auto-dispensed - Rx #PRES-20260915-0058-284', '2026-09-17 15:37:26'),
(210, 2, NULL, 58, 'out', 10, 290, 280, 'prescription', 99, 7, 1, 'Auto-dispensed - Rx #PRES-20260915-0058-139', '2026-09-17 15:37:26'),
(211, 11, NULL, 58, 'out', 10, 49, 39, 'prescription', 100, 7, 1, 'Auto-dispensed - Rx #PRES-20260915-0058-471', '2026-09-17 15:37:26'),
(212, 26, NULL, 58, 'out', 10, 190, 180, 'prescription', 101, 7, 1, 'Auto-dispensed - Rx #PRES-20260915-0058-968', '2026-09-17 15:37:26'),
(213, 8, NULL, 58, 'out', 10, 290, 280, 'prescription', 102, 7, 1, 'Auto-dispensed - Rx #PRES-20260915-0058-566', '2026-09-17 15:37:26'),
(214, 25, NULL, 59, 'out', 80, 190, 110, 'prescription', 103, 7, 1, 'Auto-dispensed - Rx #PRES-20260915-0059-385', '2026-09-17 15:37:26'),
(215, 25, NULL, 62, 'in', 1, 100, 101, '', 104, 1, 1, 'Stock returned - Deleted pending prescription #PRES-20260915-0062-449', '2026-09-17 19:53:55'),
(216, 26, NULL, 61, 'out', 70, 100, 30, 'prescription', 109, 7, 1, 'Auto-dispensed - Rx #PRES-20260918-0061-723', '2026-09-18 16:07:02'),
(217, 7, NULL, 61, 'out', 70, 110, 40, 'prescription', 110, 7, 1, 'Auto-dispensed - Rx #PRES-20260918-0061-748', '2026-09-18 16:07:02'),
(218, 12, NULL, NULL, 'out', 10, 0, 0, 'otc', 28, 7, 1, 'OTC Sale - PAID: OTC-20260918-9565 - Customer: Walk-in Customer', '2026-09-18 16:11:04'),
(219, 25, NULL, NULL, 'out', 10, 0, 0, 'otc', 28, 7, 1, 'OTC Sale - PAID: OTC-20260918-9565 - Customer: Walk-in Customer', '2026-09-18 16:11:04'),
(220, 4, NULL, NULL, 'out', 10, 0, 0, 'otc', 30, 7, 1, 'OTC Sale - PAID: OTC-20260919-0533 - Customer: Walk-in Customer', '2026-09-19 13:25:40'),
(221, 13, NULL, NULL, 'out', 20, 0, 0, 'otc', 30, 7, 1, 'OTC Sale - PAID: OTC-20260919-0533 - Customer: Walk-in Customer', '2026-09-19 13:25:40'),
(222, 14, NULL, NULL, 'out', 20, 0, 0, 'otc', 30, 7, 1, 'OTC Sale - PAID: OTC-20260919-0533 - Customer: Walk-in Customer', '2026-09-19 13:25:40'),
(223, 17, NULL, NULL, 'out', 30, 0, 0, 'otc', 30, 7, 1, 'OTC Sale - PAID: OTC-20260919-0533 - Customer: Walk-in Customer', '2026-09-19 13:25:40'),
(224, 25, NULL, NULL, 'out', 11, 0, 0, 'otc', 30, 7, 1, 'OTC Sale - PAID: OTC-20260919-0533 - Customer: Walk-in Customer', '2026-09-19 13:25:40'),
(225, 2, NULL, NULL, 'out', 10, 0, 0, 'otc', 31, 7, 1, 'OTC Sale - PAID: OTC-20260919-6227 - Customer: Walk-in Customer', '2026-09-19 13:50:52'),
(226, 12, NULL, NULL, 'out', 10, 0, 0, 'otc', 31, 7, 1, 'OTC Sale - PAID: OTC-20260919-6227 - Customer: Walk-in Customer', '2026-09-19 13:50:52'),
(227, 25, NULL, NULL, 'out', 10, 0, 0, 'otc', 31, 7, 1, 'OTC Sale - PAID: OTC-20260919-6227 - Customer: Walk-in Customer', '2026-09-19 13:50:52'),
(228, 1, NULL, NULL, 'out', 50, 0, 0, 'otc', 32, 7, 1, 'OTC Sale - PAID: OTC-20260919-4321 - Customer: Walk-in Customer', '2026-09-19 14:43:33'),
(229, 2, NULL, NULL, 'out', 50, 0, 0, 'otc', 32, 7, 1, 'OTC Sale - PAID: OTC-20260919-4321 - Customer: Walk-in Customer', '2026-09-19 14:43:33'),
(230, 14, NULL, NULL, 'out', 50, 0, 0, 'otc', 32, 7, 1, 'OTC Sale - PAID: OTC-20260919-4321 - Customer: Walk-in Customer', '2026-09-19 14:43:33'),
(231, 20, NULL, NULL, 'out', 50, 0, 0, 'otc', 32, 7, 1, 'OTC Sale - PAID: OTC-20260919-4321 - Customer: Walk-in Customer', '2026-09-19 14:43:33'),
(232, 25, NULL, NULL, 'out', 10, 0, 0, 'otc', 32, 7, 1, 'OTC Sale - PAID: OTC-20260919-4321 - Customer: Walk-in Customer', '2026-09-19 14:43:33'),
(233, 2, NULL, NULL, 'out', 5, 0, 0, 'otc', 33, 7, 1, 'OTC Sale - PAID: OTC-20260919-7626 - Customer: Walk-in Customer', '2026-09-19 17:30:38'),
(234, 3, NULL, NULL, 'out', 5, 0, 0, 'otc', 33, 7, 1, 'OTC Sale - PAID: OTC-20260919-7626 - Customer: Walk-in Customer', '2026-09-19 17:30:38'),
(235, 4, NULL, NULL, 'out', 5, 0, 0, 'otc', 33, 7, 1, 'OTC Sale - PAID: OTC-20260919-7626 - Customer: Walk-in Customer', '2026-09-19 17:30:38'),
(236, 7, NULL, NULL, 'out', 5, 0, 0, 'otc', 33, 7, 1, 'OTC Sale - PAID: OTC-20260919-7626 - Customer: Walk-in Customer', '2026-09-19 17:30:38'),
(237, 8, NULL, NULL, 'out', 5, 0, 0, 'otc', 33, 7, 1, 'OTC Sale - PAID: OTC-20260919-7626 - Customer: Walk-in Customer', '2026-09-19 17:30:38'),
(238, 11, NULL, NULL, 'out', 5, 0, 0, 'otc', 33, 7, 1, 'OTC Sale - PAID: OTC-20260919-7626 - Customer: Walk-in Customer', '2026-09-19 17:30:38'),
(239, 12, NULL, NULL, 'out', 5, 0, 0, 'otc', 33, 7, 1, 'OTC Sale - PAID: OTC-20260919-7626 - Customer: Walk-in Customer', '2026-09-19 17:30:38'),
(240, 14, NULL, NULL, 'out', 5, 0, 0, 'otc', 33, 7, 1, 'OTC Sale - PAID: OTC-20260919-7626 - Customer: Walk-in Customer', '2026-09-19 17:30:38'),
(241, 17, NULL, NULL, 'out', 5, 0, 0, 'otc', 33, 7, 1, 'OTC Sale - PAID: OTC-20260919-7626 - Customer: Walk-in Customer', '2026-09-19 17:30:38'),
(242, 25, NULL, NULL, 'out', 5, 0, 0, 'otc', 33, 7, 1, 'OTC Sale - PAID: OTC-20260919-7626 - Customer: Walk-in Customer', '2026-09-19 17:30:38'),
(243, NULL, 41, 62, 'out', 1, 296, 295, 'lab_test', 68, 4, 1, 'Lab test: KFADURO', '2026-09-19 20:05:39'),
(244, 12, NULL, 63, 'out', 10, 26, 16, 'prescription', 128, 7, 1, 'Auto-dispensed - Rx #PRES-20260919-0063-624', '2026-09-19 21:44:28'),
(245, 15, NULL, 63, 'out', 10, 190, 180, 'prescription', 129, 7, 1, 'Auto-dispensed - Rx #PRES-20260919-0063-211', '2026-09-19 21:44:28'),
(246, 13, NULL, 63, 'out', 10, 65, 55, 'prescription', 130, 7, 1, 'Auto-dispensed - Rx #PRES-20260919-0063-725', '2026-09-19 21:44:28'),
(247, 3, NULL, 63, 'out', 10, 105, 95, 'prescription', 131, 7, 1, 'Auto-dispensed - Rx #PRES-20260919-0063-358', '2026-09-19 21:44:28'),
(248, 25, NULL, 63, 'out', 10, 300, 290, 'prescription', 132, 7, 1, 'Auto-dispensed - Rx #PRES-20260919-0063-262', '2026-09-19 21:44:28'),
(249, 26, NULL, 63, 'out', 10, 279, 269, 'prescription', 133, 7, 1, 'Auto-dispensed - Rx #PRES-20260919-0063-880', '2026-09-19 21:44:28'),
(250, 12, NULL, 62, 'out', 5, 16, 11, 'prescription', 134, 7, 1, 'Auto-dispensed - Rx #PRES-20260919-0062-666', '2026-09-19 21:44:28'),
(251, 25, NULL, 62, 'out', 5, 290, 285, 'prescription', 135, 7, 1, 'Auto-dispensed - Rx #PRES-20260919-0062-293', '2026-09-19 21:44:28'),
(252, 11, NULL, 62, 'out', 5, 15, 10, 'prescription', 136, 7, 1, 'Auto-dispensed - Rx #PRES-20260919-0062-810', '2026-09-19 21:44:28'),
(253, 26, NULL, 62, 'out', 5, 269, 264, 'prescription', 137, 7, 1, 'Auto-dispensed - Rx #PRES-20260919-0062-717', '2026-09-19 21:44:28'),
(254, 2, NULL, 62, 'out', 5, 120, 115, 'prescription', 138, 7, 1, 'Auto-dispensed - Rx #PRES-20260919-0062-801', '2026-09-19 21:44:28'),
(255, 8, NULL, 62, 'out', 5, 110, 105, 'prescription', 139, 7, 1, 'Auto-dispensed - Rx #PRES-20260919-0062-737', '2026-09-19 21:44:28'),
(256, 16, NULL, 62, 'out', 5, 45, 40, 'prescription', 140, 7, 1, 'Auto-dispensed - Rx #PRES-20260919-0062-980', '2026-09-19 21:44:28'),
(257, 17, NULL, 62, 'out', 5, 60, 55, 'prescription', 141, 7, 1, 'Auto-dispensed - Rx #PRES-20260919-0062-623', '2026-09-19 21:44:28'),
(258, 13, NULL, 62, 'out', 5, 55, 50, 'prescription', 142, 7, 1, 'Auto-dispensed - Rx #PRES-20260919-0062-984', '2026-09-19 21:44:28'),
(259, 25, NULL, 61, 'out', 4, 280, 276, 'prescription', 125, 7, 1, 'Auto-dispensed - Rx #PRES-20260919-0061-313', '2026-09-20 16:22:52'),
(260, 12, NULL, 61, 'out', 4, 11, 7, 'prescription', 126, 7, 1, 'Auto-dispensed - Rx #PRES-20260919-0061-418', '2026-09-20 16:22:52'),
(261, 11, NULL, 61, 'out', 4, 10, 6, 'prescription', 127, 7, 1, 'Auto-dispensed - Rx #PRES-20260919-0061-515', '2026-09-20 16:22:52'),
(262, 26, NULL, 63, 'out', 26, 264, 238, 'prescription', 143, 7, 1, 'Auto-dispensed - Rx #PRES-20260919-0063-214', '2026-09-20 16:22:52'),
(263, 25, NULL, 63, 'out', 26, 276, 250, 'prescription', 144, 7, 1, 'Auto-dispensed - Rx #PRES-20260919-0063-936', '2026-09-20 16:22:52'),
(264, 19, NULL, 60, 'out', 25, 245, 220, 'prescription', 149, 12, 1, 'Auto-dispensed (Bill paid) - Rx #PRES-20260920-0060-706', '2026-09-20 19:16:43'),
(265, 26, NULL, 60, 'out', 25, 213, 188, 'prescription', 151, 12, 1, 'Auto-dispensed (Bill paid) - Rx #PRES-20260920-0060-618', '2026-09-20 19:16:43'),
(266, 10, NULL, 60, 'out', 10, 290, 280, 'prescription', 152, 12, 1, 'Auto-dispensed (Bill paid) - Rx #PRES-20260920-0060-285', '2026-09-20 19:16:43'),
(267, 19, NULL, 60, 'out', 10, 210, 200, 'prescription', 155, 12, 1, 'Auto-dispensed (Bill paid) - Rx #PRES-20260920-0060-161', '2026-09-20 19:37:48'),
(268, 10, NULL, 60, 'out', 10, 245, 235, 'prescription', 156, 12, 1, 'Auto-dispensed (Bill paid) - Rx #PRES-20260920-0060-814', '2026-09-20 19:37:48'),
(269, 26, NULL, 60, 'out', 10, 178, 168, 'prescription', 157, 12, 1, 'Auto-dispensed (Bill paid) - Rx #PRES-20260920-0060-181', '2026-09-20 19:37:48'),
(270, 25, NULL, 60, 'out', 10, 190, 180, 'prescription', 158, 12, 1, 'Auto-dispensed (Bill paid) - Rx #PRES-20260920-0060-649', '2026-09-20 19:37:48'),
(271, 1, NULL, 60, 'out', 10, 370, 360, 'prescription', 159, 12, 1, 'Auto-dispensed (Bill paid) - Rx #PRES-20260920-0060-291', '2026-09-20 19:37:48'),
(272, 5, NULL, 60, 'out', 10, 140, 130, 'prescription', 160, 12, 1, 'Auto-dispensed (Bill paid) - Rx #PRES-20260920-0060-933', '2026-09-20 19:37:48'),
(273, 25, NULL, 61, 'out', 25, 180, 155, 'prescription', 153, 12, 1, 'Auto-dispensed (Bill paid) - Rx #PRES-20260920-0061-253', '2026-09-20 20:19:56'),
(274, 10, NULL, 61, 'out', 25, 235, 210, 'prescription', 154, 12, 1, 'Auto-dispensed (Bill paid) - Rx #PRES-20260920-0061-993', '2026-09-20 20:19:56'),
(275, 25, NULL, 63, 'out', 10, 145, 135, 'prescription', 161, 12, 1, 'Auto-dispensed (Bill paid) - Rx #PRES-20260920-0063-348', '2026-09-20 20:33:36'),
(276, 26, NULL, 63, 'out', 10, 158, 148, 'prescription', 162, 12, 1, 'Auto-dispensed (Bill paid) - Rx #PRES-20260920-0063-401', '2026-09-20 20:33:36'),
(277, 10, NULL, 63, 'out', 10, 200, 190, 'prescription', 163, 12, 1, 'Auto-dispensed (Bill paid) - Rx #PRES-20260920-0063-227', '2026-09-20 20:33:36'),
(278, 2, NULL, NULL, 'out', 10, 0, 0, 'otc', 34, 8, 1, 'OTC Sale - PAID: OTC-20260921-6013 - Customer: Walk-in Customer', '2026-09-21 10:13:03'),
(279, 19, NULL, NULL, 'out', 10, 0, 0, 'otc', 34, 8, 1, 'OTC Sale - PAID: OTC-20260921-6013 - Customer: Walk-in Customer', '2026-09-21 10:13:03'),
(280, 25, NULL, NULL, 'out', 10, 0, 0, 'otc', 34, 8, 1, 'OTC Sale - PAID: OTC-20260921-6013 - Customer: Walk-in Customer', '2026-09-21 10:13:03'),
(281, 26, NULL, NULL, 'out', 10, 0, 0, 'otc', 34, 8, 1, 'OTC Sale - PAID: OTC-20260921-6013 - Customer: Walk-in Customer', '2026-09-21 10:13:03'),
(282, 2, NULL, NULL, 'out', 5, 0, 0, 'otc', 35, 8, 1, 'OTC Sale - PAID: OTC-20260921-5315 - Customer: KELVIN JOHN', '2026-09-21 10:16:03'),
(283, 19, NULL, NULL, 'out', 10, 0, 0, 'otc', 35, 8, 1, 'OTC Sale - PAID: OTC-20260921-5315 - Customer: KELVIN JOHN', '2026-09-21 10:16:03'),
(284, 25, NULL, NULL, 'out', 5, 0, 0, 'otc', 35, 8, 1, 'OTC Sale - PAID: OTC-20260921-5315 - Customer: KELVIN JOHN', '2026-09-21 10:16:03'),
(285, 26, NULL, NULL, 'out', 18, 0, 0, 'otc', 35, 8, 1, 'OTC Sale - PAID: OTC-20260921-5315 - Customer: KELVIN JOHN', '2026-09-21 10:16:03'),
(286, 12, NULL, 60, 'out', 10, 454, 444, 'prescription', 169, 12, 1, 'Auto-dispensed (Bill paid) - Rx #PRES-20260921-0060-941', '2026-09-21 18:26:08'),
(287, 25, NULL, 60, 'out', 10, 454, 444, 'prescription', 170, 12, 1, 'Auto-dispensed (Bill paid) - Rx #PRES-20260921-0060-287', '2026-09-21 18:26:08'),
(288, 1, NULL, 60, 'out', 10, 330, 320, 'prescription', 171, 12, 1, 'Auto-dispensed (Bill paid) - Rx #PRES-20260921-0060-228', '2026-09-21 18:26:08'),
(289, 2, NULL, 60, 'out', 10, 354, 344, 'prescription', 172, 12, 1, 'Auto-dispensed (Bill paid) - Rx #PRES-20260921-0060-889', '2026-09-21 18:26:08'),
(290, 26, NULL, 60, 'out', 10, 374, 364, 'prescription', 173, 12, 1, 'Auto-dispensed (Bill paid) - Rx #PRES-20260921-0060-607', '2026-09-21 18:26:08'),
(291, 3, NULL, 62, 'out', 10, 85, 75, 'prescription', 179, 11, 1, 'Auto-dispensed (Bill paid) - Rx #PRES-20260921-0062-196', '2026-09-21 18:29:18'),
(292, 19, NULL, 62, 'out', 10, 124, 114, 'prescription', 180, 11, 1, 'Auto-dispensed (Bill paid) - Rx #PRES-20260921-0062-241', '2026-09-21 18:29:18'),
(293, 12, NULL, 62, 'out', 10, 444, 434, 'prescription', 181, 11, 1, 'Auto-dispensed (Bill paid) - Rx #PRES-20260921-0062-630', '2026-09-21 18:29:18'),
(294, 25, NULL, 62, 'out', 10, 444, 434, 'prescription', 182, 11, 1, 'Auto-dispensed (Bill paid) - Rx #PRES-20260921-0062-744', '2026-09-21 18:29:18'),
(295, 2, NULL, 62, 'out', 10, 344, 334, 'prescription', 183, 11, 1, 'Auto-dispensed (Bill paid) - Rx #PRES-20260921-0062-923', '2026-09-21 18:29:18'),
(296, 26, NULL, 62, 'out', 10, 364, 354, 'prescription', 184, 11, 1, 'Auto-dispensed (Bill paid) - Rx #PRES-20260921-0062-167', '2026-09-21 18:29:18'),
(297, 8, NULL, 62, 'out', 10, 95, 85, 'prescription', 185, 11, 1, 'Auto-dispensed (Bill paid) - Rx #PRES-20260921-0062-747', '2026-09-21 18:29:18'),
(298, 4, NULL, 62, 'out', 10, 175, 165, 'prescription', 186, 11, 1, 'Auto-dispensed (Bill paid) - Rx #PRES-20260921-0062-199', '2026-09-21 18:29:18'),
(299, 1, NULL, 62, 'out', 10, 320, 310, 'prescription', 187, 11, 1, 'Auto-dispensed (Bill paid) - Rx #PRES-20260921-0062-718', '2026-09-21 18:29:18'),
(300, 20, NULL, 62, 'out', 10, 330, 320, 'prescription', 188, 11, 1, 'Auto-dispensed (Bill paid) - Rx #PRES-20260921-0062-584', '2026-09-21 18:29:18'),
(301, 10, NULL, 62, 'out', 10, 160, 150, 'prescription', 189, 11, 1, 'Auto-dispensed (Bill paid) - Rx #PRES-20260921-0062-581', '2026-09-21 18:29:18');

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
  `role` varchar(50) NOT NULL,
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
(1, 'admin1', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'System Admin', 'admin@braick.com', '+255 613 234 123', 'admin', 1, NULL, 0, '2026-09-21 19:07:28', 'user_1_1788291272.png', 'active', '2026-08-23 12:26:10', '2026-09-21 19:07:28'),
(3, 'admin2', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'BRAICK', 'braick.admin@braick.com', '+255 732 123 030', 'admin', 1, NULL, 0, '2026-08-26 14:10:40', NULL, 'active', '2026-08-23 12:41:40', '2026-09-04 17:59:45'),
(4, 'Dr.Dodoma1', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Dr.ERICK JOHN', 'erick.dodoma@braick.com', '+255 700 000 011', 'doctor', 1, 'General Medicine', 0, '2026-09-21 19:49:59', 'user_4_1787697956.png', 'active', '2026-08-23 12:41:40', '2026-09-21 19:49:59'),
(5, 'Dr.Dodoma2', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Dr. Grace Peter', 'grace.dodoma@braick.com', '+255 700 000 012', 'doctor', 1, 'Pediatrics', 0, '2026-09-21 19:52:37', NULL, 'active', '2026-08-23 12:41:40', '2026-09-21 19:52:37'),
(6, 'Dr.Dodoma3', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Dr. John Mushi', 'john.dodoma@braick.com', '+255 700 000 013', 'doctor', 1, 'Cardiology', 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(7, 'Pharm.Dodoma3', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'LUCY MUSSA', 'pharm.dodoma@braick.com', '+255 700 000 014', 'pharmacy', 1, NULL, 0, '2026-09-21 19:46:28', 'user_7_1787493390.png', 'active', '2026-08-23 12:41:40', '2026-09-21 19:46:28'),
(8, 'Pharm.Dodoma2', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Mary John', 'mary.dodoma@braick.com', '+255 700 000 015', 'pharmacy', 1, NULL, 0, '2026-09-21 19:53:18', NULL, 'active', '2026-08-23 12:41:40', '2026-09-21 19:53:18'),
(9, 'Pharm.Dodoma1', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'James Mwangi', 'james.dodoma@braick.com', '+255 700 000 016', 'pharmacy', 1, NULL, 0, '2026-09-05 13:24:29', NULL, 'active', '2026-08-23 12:41:40', '2026-09-05 13:24:29'),
(10, 'Recpt.Dodoma1', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'SALOME SANGA', 'salome.dodoma@braick.com', '+255 700 000 017', 'reception', 1, NULL, 0, '2026-09-21 19:38:22', 'reception_10_1787518197.png', 'active', '2026-08-23 12:41:40', '2026-09-21 19:38:22'),
(11, 'Recpt.Dodoma2', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Rose Mwangi', 'rose.dodoma@braick.com', '+255 700 000 018', 'reception', 1, NULL, 0, '2026-09-21 19:46:10', NULL, 'active', '2026-08-23 12:41:40', '2026-09-21 19:46:10'),
(12, 'Recpt.Dodoma3', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'JUDITH SOLOMONI', 'anna.dodoma@braick.com', '+255 700 000 019', 'reception', 1, NULL, 0, '2026-09-21 19:54:46', NULL, 'active', '2026-08-23 12:41:40', '2026-09-21 19:54:46'),
(13, 'Lab.Dodoma1', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'ANGERITHA KIMARO MSANGI', 'angel@gmail.com', '+255 700 000 020', 'laboratory', 1, 'Reception', 0, '2026-09-21 19:46:01', 'user_13_1787502536.png', 'active', '2026-08-23 12:41:40', '2026-09-21 19:46:01'),
(14, 'Lab.Dodoma2', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Peter Lema', 'peter.dodoma@braick.com', '+255 700 000 021', 'laboratory', 1, NULL, 0, '2026-09-21 18:59:00', NULL, 'active', '2026-08-23 12:41:40', '2026-09-21 18:59:00'),
(15, 'Lab.Dodoma3', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Sarah Mwamba', 'sarah.dodoma@braick.com', '+255 700 000 022', 'laboratory', 1, NULL, 0, '2026-09-20 19:31:31', NULL, 'active', '2026-08-23 12:41:40', '2026-09-20 19:31:31'),
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
(44, 'R.angerith', '$2y$10$uWAY/jylf2vW4kUfBKVx6.7WOOEqJ/MDHZNYv170sMOLcIPKHZZPi', '2026-09-03 01:31:31', 1, 'ANGERITHA KIMARO', 'receptiondodoma@braick.com', '0746526243', 'reception', 2, '', 0, NULL, NULL, 'active', '2026-09-03 01:31:31', '2026-09-15 19:48:29'),
(45, 'Audit.Dodoma1', '$2y$10$wJzev6ObUWTxiWk3betrHebeo7vH.Hg4AofMFZSe12cgyxXrPwz3m', '2026-09-15 21:47:08', 1, 'NASMA ISMAIL', 'jacksonmyula773@gmail.com', '0623693303', 'audit', 1, '', 0, '2026-09-21 14:50:39', NULL, 'active', '2026-09-15 21:47:08', '2026-09-21 14:50:39'),
(46, 'Audit.Dodoma2', '$2y$10$bNO6t6.NymBGd2fAk89tS.ak.4z0RT.KGQ3NlwrIGR99NOm/WXanC', '2026-09-15 22:31:06', 1, 'NYANSAEL NZILU', 'nyansael@gmail.com', '0746526243', 'audit', 1, '', 0, NULL, NULL, 'active', '2026-09-15 22:31:06', '2026-09-15 22:41:25'),
(47, 'Audit.Arusha1', '$2y$10$v7f/O/YkZmWGb.NmvQBvDeKgyOAG544hJ6euTJhRk.iuNmZ53mXlq', '2026-09-15 22:42:42', 1, 'FLORA DANIEL', 'flora@gmail.com', '0746657891', 'audit', 2, '', 0, NULL, NULL, 'active', '2026-09-15 22:42:42', '2026-09-15 22:49:10'),
(48, 'Audit.Arusha2', '$2y$10$wOWeB62L0kHcFZCRko9gN.nSwcFqSCMmyaiSjMcLp2Q6eSERlx93.', '2026-09-15 22:46:31', 1, 'MICHAEL NJIRO', 'michael@gmail.com', '0746652891', 'audit', 2, '', 0, NULL, NULL, 'active', '2026-09-15 22:46:31', '2026-09-15 22:46:31'),
(49, 'Audit.Arusha3', '$2y$10$0Vavj/eEDFa2WoE0UJlXXuyimebTdwZLefYgYSYaFmrC/sndrwfV6', '2026-09-15 22:48:10', 1, 'PETRO EMANUAL', 'petro@gmail.com', '0743426243', 'audit', 2, '', 0, NULL, NULL, 'active', '2026-09-15 22:48:10', '2026-09-15 22:50:01');

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
  `assigned_by_id` int(11) DEFAULT NULL,
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

--
-- Dumping data for table `visits`
--

INSERT INTO `visits` (`id`, `visit_number`, `visit_date`, `patient_id`, `doctor_id`, `assigned_by_id`, `assigned_at`, `receptionist_id`, `branch_id`, `visit_type`, `service_id`, `consultation_fee`, `status`, `symptoms`, `hpi`, `physical_exam`, `complaint`, `diagnosis`, `disease_id`, `disease_code`, `treatment`, `follow_up_date`, `notes`, `is_referred`, `referred_by_doctor_id`, `referred_to_doctor_id`, `referral_id`, `created_at`, `updated_at`, `is_completed`, `completed_at`, `lab_fees_total`, `pharmacy_fees_total`, `other_fees_total`, `visit_total`, `payment_status`, `total_discount`, `discount_percent`) VALUES
(201, 'VIS-20260921-0204', '2026-09-21 22:38:39', 63, 4, 10, '2026-09-21 19:38:39', 10, 1, 'New Patient', 17, 10000.00, 'completed', NULL, NULL, NULL, NULL, 'ANTENCIK 104, KICHWA, TYPHOID', 22, '13BRT9_BTC8, D-KICHWA-704, D-TYPHOI-507', NULL, NULL, NULL, 0, NULL, NULL, NULL, '2026-09-21 19:38:39', '2026-09-21 19:57:27', 1, '2026-09-21 19:57:27', 0.00, 0.00, 0.00, 780000.00, 'paid', 0.00, 0.00),
(202, 'VIS-20260921-9942', '2026-09-21 22:38:57', 62, 4, 10, '2026-09-21 19:38:57', 10, 1, 'New Patient', 17, 10000.00, 'completed', NULL, NULL, NULL, NULL, 'KICHOCHO, KICHWA, SAFURA, TYPHOID', 24, 'D-KICHOC-635, D-KICHWA-704, D-SAFURA-833, D-TYPHOI', NULL, NULL, NULL, 0, NULL, NULL, NULL, '2026-09-21 19:38:57', '2026-09-21 19:57:05', 1, '2026-09-21 19:57:05', 0.00, 0.00, 0.00, 360000.00, 'paid', 0.00, 0.00),
(203, 'VIS-20260921-9790', '2026-09-21 22:39:13', 61, 4, 10, '2026-09-21 19:39:13', 10, 1, 'New Patient', 17, 10000.00, 'completed', NULL, NULL, NULL, NULL, 'AMIBA 13, KICHOCHO, KICHWA', 26, 'D-AMIBA1-981, D-KICHOC-635, D-KICHWA-704', NULL, NULL, NULL, 0, NULL, NULL, NULL, '2026-09-21 19:39:13', '2026-09-21 19:56:38', 1, '2026-09-21 19:56:38', 0.00, 0.00, 0.00, 420000.00, 'paid', 1200.00, 0.00),
(204, 'VIS-20260921-1058', '2026-09-21 22:39:30', 60, 5, 10, '2026-09-21 19:39:30', 10, 1, 'New Patient', 17, 10000.00, 'completed', NULL, NULL, NULL, NULL, 'AMIBA 13, KICHWA, TYPHOID', 26, 'D-AMIBA1-981, D-KICHWA-704, D-TYPHOI-507', NULL, NULL, NULL, 0, NULL, NULL, NULL, '2026-09-21 19:39:30', '2026-09-21 19:55:51', 1, '2026-09-21 19:55:51', 0.00, 0.00, 0.00, 1100000.00, 'paid', 2000.00, 0.00),
(205, 'VIS-20260921-7318', '2026-09-21 22:39:49', 59, 5, 10, '2026-09-21 19:39:49', 10, 1, 'New Patient', 17, 10000.00, 'completed', NULL, NULL, NULL, NULL, 'AMIBA 13, KICHOCHO, KICHWA, TYPHOID', 26, 'D-AMIBA1-981, D-KICHOC-635, D-KICHWA-704, D-TYPHOI', NULL, NULL, NULL, 0, NULL, NULL, NULL, '2026-09-21 19:39:49', '2026-09-21 19:55:21', 1, '2026-09-21 19:55:21', 0.00, 0.00, 0.00, 500000.00, 'paid', 0.00, 0.00);

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
(44, 61, NULL, NULL, 10, 1, 39.0, 122, 81, 73, NULL, 105, NULL, 73.00, 178.00, 23.0, NULL, NULL, NULL, '2026-08-29 17:51:46', '2026-08-29 17:51:46', '2026-09-16 21:29:50'),
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
(116, 61, NULL, NULL, 12, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-09 15:17:20', '2026-09-09 15:17:20', '2026-09-09 15:17:20'),
(117, 61, NULL, NULL, 12, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-10 09:48:07', '2026-09-10 09:48:07', '2026-09-10 09:48:07'),
(118, 62, NULL, NULL, 10, 1, 39.0, 126, 80, 73, NULL, NULL, NULL, 78.00, 180.00, 24.1, NULL, NULL, NULL, '2026-09-10 10:30:45', '2026-09-10 10:30:45', '2026-09-10 10:30:45'),
(119, 60, NULL, NULL, 10, 1, 39.0, 122, 90, 75, NULL, NULL, NULL, 68.00, 175.00, 22.2, NULL, NULL, NULL, '2026-09-10 10:33:14', '2026-09-10 10:33:14', '2026-09-10 10:33:14'),
(120, 61, NULL, NULL, 10, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-10 12:08:52', '2026-09-10 12:08:52', '2026-09-10 12:08:52'),
(121, 62, NULL, NULL, 12, 1, 39.0, 126, 80, 73, NULL, NULL, NULL, 78.00, 180.00, 24.1, NULL, NULL, NULL, '2026-09-10 12:29:24', '2026-09-10 12:29:24', '2026-09-18 19:19:37'),
(122, 60, NULL, NULL, 10, 1, 35.0, 120, 89, 70, NULL, NULL, NULL, 70.00, 175.00, 22.9, NULL, NULL, NULL, '2026-09-10 12:34:32', '2026-09-10 12:34:32', '2026-09-10 12:34:32'),
(123, 59, NULL, NULL, 10, 1, 33.0, 123, 89, 69, NULL, NULL, NULL, 100.00, 173.00, 33.4, NULL, NULL, NULL, '2026-09-10 12:35:40', '2026-09-10 12:35:40', '2026-09-10 12:35:40'),
(124, 58, NULL, NULL, 10, 1, 44.0, 133, 88, 77, NULL, NULL, NULL, 66.00, 177.00, 21.1, NULL, NULL, NULL, '2026-09-10 12:36:36', '2026-09-10 12:36:36', '2026-09-10 12:36:36'),
(125, 61, NULL, NULL, 1, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-15 15:44:33', '2026-09-15 15:44:33', '2026-09-15 15:44:33'),
(126, 61, NULL, NULL, 12, 1, 34.0, 123, 78, 78, NULL, NULL, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-17 14:32:57', '2026-09-17 14:32:57', '2026-09-17 14:32:57'),
(127, 47, NULL, NULL, 1, 1, 34.0, 128, 89, 68, NULL, NULL, NULL, 67.00, 167.00, 24.0, NULL, NULL, NULL, '2026-09-17 15:07:58', '2026-09-17 15:07:58', '2026-09-17 15:07:58'),
(128, 59, NULL, NULL, 12, 1, 33.0, 123, 89, 69, NULL, NULL, NULL, 100.00, 173.00, 33.4, NULL, NULL, NULL, '2026-09-17 17:38:20', '2026-09-17 17:38:20', '2026-09-17 17:38:20'),
(129, 63, NULL, NULL, 12, 1, 34.0, 129, 91, 74, NULL, 109, NULL, 51.00, 153.00, 21.8, NULL, NULL, NULL, '2026-09-18 18:18:26', '2026-09-18 18:18:26', '2026-09-18 21:49:29'),
(130, 63, NULL, NULL, 12, 1, 34.0, 129, 91, 74, NULL, 109, NULL, 51.00, 153.00, 21.8, NULL, NULL, NULL, '2026-09-19 14:33:28', '2026-09-19 14:33:28', '2026-09-19 14:33:28'),
(131, 60, NULL, NULL, 12, 1, 39.0, 127, 90, 78, NULL, 99, NULL, 71.00, 176.00, 22.9, NULL, NULL, NULL, '2026-09-19 14:34:47', '2026-09-19 14:34:47', '2026-09-19 14:34:47'),
(132, 61, NULL, NULL, 10, 1, 37.0, 120, 80, 72, NULL, 98, NULL, 65.00, 170.00, 22.5, NULL, NULL, NULL, '2026-09-19 19:24:45', '2026-09-19 19:24:45', '2026-09-19 19:24:45'),
(133, 61, NULL, NULL, 11, 1, 37.0, 120, 80, 72, NULL, 98, NULL, 65.00, 170.00, 22.5, NULL, NULL, NULL, '2026-09-19 19:49:38', '2026-09-19 19:49:38', '2026-09-19 19:49:38'),
(134, 63, NULL, NULL, 11, 1, 34.0, 129, 91, 74, NULL, 109, NULL, 51.00, 153.00, 21.8, NULL, NULL, NULL, '2026-09-19 20:02:12', '2026-09-19 20:02:12', '2026-09-19 20:02:12'),
(135, 62, NULL, NULL, 11, 1, 38.0, 129, 93, 78, NULL, 127, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-19 20:03:05', '2026-09-19 20:03:05', '2026-09-19 20:03:05'),
(136, 61, NULL, NULL, 12, 1, 37.0, 120, 80, 72, NULL, 98, NULL, 65.00, 170.00, 22.5, NULL, NULL, NULL, '2026-09-19 20:04:15', '2026-09-19 20:04:15', '2026-09-19 20:04:15'),
(137, 63, NULL, NULL, 12, 1, 34.0, 129, 91, 74, NULL, 109, NULL, 51.00, 153.00, 21.8, NULL, NULL, NULL, '2026-09-19 21:37:01', '2026-09-19 21:37:01', '2026-09-19 21:37:01'),
(138, 63, NULL, NULL, 12, 1, 34.0, 129, 91, 74, NULL, 109, NULL, 51.00, 153.00, 21.8, NULL, NULL, NULL, '2026-09-19 21:37:56', '2026-09-19 21:37:56', '2026-09-19 21:37:56'),
(139, 60, NULL, NULL, 11, 1, 39.0, 127, 90, 78, NULL, 99, NULL, 71.00, 176.00, 22.9, NULL, NULL, NULL, '2026-09-19 21:54:14', '2026-09-19 21:54:14', '2026-09-19 21:54:14'),
(140, 60, NULL, NULL, 12, 1, 39.0, 127, 90, 78, NULL, 99, NULL, 71.00, 176.00, 22.9, NULL, NULL, NULL, '2026-09-20 15:59:19', '2026-09-20 15:59:19', '2026-09-20 15:59:19'),
(141, 59, NULL, NULL, 12, 1, 33.0, 123, 89, 69, NULL, NULL, NULL, 100.00, 173.00, 33.4, NULL, NULL, NULL, '2026-09-20 16:02:07', '2026-09-20 16:02:07', '2026-09-20 16:02:07'),
(142, 63, NULL, NULL, 11, 1, 34.0, 129, 91, 74, NULL, 109, NULL, 51.00, 153.00, 21.8, NULL, NULL, NULL, '2026-09-20 16:46:40', '2026-09-20 16:46:40', '2026-09-20 16:46:40'),
(143, 63, NULL, NULL, 12, 1, 34.0, 129, 91, 74, NULL, 109, NULL, 51.00, 153.00, 21.8, NULL, NULL, NULL, '2026-09-20 19:24:56', '2026-09-20 19:24:56', '2026-09-20 19:24:56'),
(144, 62, NULL, NULL, 12, 1, 38.0, 129, 93, 78, NULL, 127, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-20 19:25:14', '2026-09-20 19:25:14', '2026-09-20 19:25:14'),
(145, 61, NULL, NULL, 12, 1, 37.0, 120, 80, 72, NULL, 98, NULL, 65.00, 170.00, 22.5, NULL, NULL, NULL, '2026-09-20 19:25:35', '2026-09-20 19:25:35', '2026-09-20 19:25:35'),
(146, 60, NULL, NULL, 12, 1, 39.0, 127, 90, 78, NULL, 99, NULL, 71.00, 176.00, 22.9, NULL, NULL, NULL, '2026-09-20 19:25:58', '2026-09-20 19:25:58', '2026-09-20 19:25:58'),
(147, 63, NULL, NULL, 10, 1, 34.0, 129, 91, 74, NULL, 109, NULL, 51.00, 153.00, 21.8, NULL, NULL, NULL, '2026-09-20 21:04:47', '2026-09-20 21:04:47', '2026-09-21 14:53:46'),
(148, 62, NULL, NULL, 10, 1, 38.0, 129, 93, 78, NULL, 127, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-20 21:05:09', '2026-09-20 21:05:09', '2026-09-20 21:05:09'),
(149, 59, NULL, NULL, 10, 1, 33.0, 123, 89, 69, NULL, NULL, NULL, 100.00, 173.00, 33.4, NULL, NULL, NULL, '2026-09-21 14:27:01', '2026-09-21 14:27:01', '2026-09-21 14:27:01'),
(150, 52, NULL, NULL, 10, 1, 30.0, 129, 90, 70, NULL, NULL, NULL, 60.00, 180.00, 18.5, NULL, NULL, NULL, '2026-09-21 14:27:32', '2026-09-21 14:27:32', '2026-09-21 14:27:32'),
(151, 62, NULL, NULL, 12, 1, 38.0, 129, 93, 78, NULL, 127, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-21 18:09:54', '2026-09-21 18:09:54', '2026-09-21 18:09:54'),
(152, 63, NULL, NULL, 12, 1, 34.0, 129, 91, 74, NULL, 109, NULL, 51.00, 153.00, 21.8, NULL, NULL, NULL, '2026-09-21 18:10:12', '2026-09-21 18:10:12', '2026-09-21 18:10:12'),
(153, 61, NULL, NULL, 12, 1, 37.0, 120, 80, 72, NULL, 98, NULL, 65.00, 170.00, 22.5, NULL, NULL, NULL, '2026-09-21 18:10:29', '2026-09-21 18:10:29', '2026-09-21 18:10:29'),
(154, 63, NULL, NULL, 10, 1, 34.0, 129, 91, 74, NULL, 109, NULL, 51.00, 153.00, 21.8, NULL, NULL, NULL, '2026-09-21 18:53:11', '2026-09-21 18:53:11', '2026-09-21 18:53:11'),
(155, 62, NULL, NULL, 11, 1, 38.0, 129, 93, 78, NULL, 127, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-21 18:53:42', '2026-09-21 18:53:42', '2026-09-21 18:53:42'),
(156, 61, NULL, NULL, 11, 1, 37.0, 120, 80, 72, NULL, 98, NULL, 65.00, 170.00, 22.5, NULL, NULL, NULL, '2026-09-21 18:54:01', '2026-09-21 18:54:01', '2026-09-21 18:54:01'),
(157, 63, 201, NULL, 10, 1, 34.0, 129, 91, 74, NULL, 109, NULL, 51.00, 153.00, 21.8, NULL, NULL, NULL, '2026-09-21 19:38:39', '2026-09-21 19:38:39', '2026-09-21 19:38:39'),
(158, 62, 202, NULL, 10, 1, 38.0, 129, 93, 78, NULL, 127, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-21 19:38:57', '2026-09-21 19:38:57', '2026-09-21 19:38:57'),
(159, 61, 203, NULL, 10, 1, 37.0, 120, 80, 72, NULL, 98, NULL, 65.00, 170.00, 22.5, NULL, NULL, NULL, '2026-09-21 19:39:13', '2026-09-21 19:39:13', '2026-09-21 19:39:13'),
(160, 59, 205, NULL, 10, 1, 33.0, 123, 89, 69, NULL, NULL, NULL, 100.00, 173.00, 33.4, NULL, NULL, NULL, '2026-09-21 19:39:49', '2026-09-21 19:39:49', '2026-09-21 19:39:49');

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
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_invoice_number` (`invoice_number`);

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
  ADD KEY `service_id` (`service_id`),
  ADD KEY `idx_assigned_by` (`assigned_by_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1684;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `bills`
--
ALTER TABLE `bills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=377;

--
-- AUTO_INCREMENT for table `bill_items`
--
ALTER TABLE `bill_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1279;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `diseases`
--
ALTER TABLE `diseases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `employee_departments`
--
ALTER TABLE `employee_departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `employee_roles`
--
ALTER TABLE `employee_roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=325;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=111;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=284;

--
-- AUTO_INCREMENT for table `otc_sales`
--
ALTER TABLE `otc_sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `otc_sale_items`
--
ALTER TABLE `otc_sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT for table `patient_documents`
--
ALTER TABLE `patient_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=168;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=224;

--
-- AUTO_INCREMENT for table `prescription_items`
--
ALTER TABLE `prescription_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=223;

--
-- AUTO_INCREMENT for table `procedures`
--
ALTER TABLE `procedures`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=286;

--
-- AUTO_INCREMENT for table `procedures_catalog`
--
ALTER TABLE `procedures_catalog`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `purchases`
--
ALTER TABLE `purchases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `purchase_items`
--
ALTER TABLE `purchase_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `receipts`
--
ALTER TABLE `receipts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `referrals`
--
ALTER TABLE `referrals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `service_categories`
--
ALTER TABLE `service_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=302;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `visits`
--
ALTER TABLE `visits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=206;

--
-- AUTO_INCREMENT for table `vital_signs`
--
ALTER TABLE `vital_signs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=161;

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
  ADD CONSTRAINT `fk_visits_assigned_by` FOREIGN KEY (`assigned_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
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
