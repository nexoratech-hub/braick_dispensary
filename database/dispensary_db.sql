-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 11, 2026 at 02:26 PM
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
(1683, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-21 19:54:46', '2026-09-21 19:54:46'),
(1684, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-21 20:00:18', '2026-09-21 20:00:18'),
(1685, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-21 20:00:25', '2026-09-21 20:00:25'),
(1686, 45, 1, NULL, 'user_login', 'User logged in: NASMA ISMAIL (Mode: general_blue, Role: audit)', NULL, NULL, '2026-09-21 20:30:24', '2026-09-21 20:30:24'),
(1687, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-21 21:16:19', '2026-09-21 21:16:19'),
(1688, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-21 21:16:27', '2026-09-21 21:16:27'),
(1689, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-21 21:17:13', '2026-09-21 21:17:13'),
(1690, 5, 1, NULL, 'user_login', 'User logged in: Dr. Grace Peter (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-21 21:17:16', '2026-09-21 21:17:16'),
(1691, 5, 1, NULL, 'user_logout', 'User logged out: Dr. Grace Peter (Role: doctor)', NULL, NULL, '2026-09-21 21:18:05', '2026-09-21 21:18:05'),
(1692, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO MSANGI (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-21 21:18:08', '2026-09-21 21:18:08'),
(1693, 13, 1, NULL, 'lab_tests_bulk_started', 'Started 6 lab test(s) in bulk (global)', NULL, NULL, '2026-09-21 21:18:14', '2026-09-21 21:18:14'),
(1694, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO MSANGI (Role: laboratory)', NULL, NULL, '2026-09-21 21:19:08', '2026-09-21 21:19:08'),
(1695, 5, 1, NULL, 'user_login', 'User logged in: Dr. Grace Peter (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-21 21:19:12', '2026-09-21 21:19:12'),
(1696, 5, 1, NULL, 'user_logout', 'User logged out: Dr. Grace Peter (Role: doctor)', NULL, NULL, '2026-09-21 21:21:18', '2026-09-21 21:21:18'),
(1697, 8, 1, NULL, 'user_login', 'User logged in: Mary John (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-21 21:21:23', '2026-09-21 21:21:23'),
(1698, 8, 1, NULL, 'user_logout', 'User logged out: Mary John (Role: pharmacy)', NULL, NULL, '2026-09-21 21:22:22', '2026-09-21 21:22:22'),
(1699, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-21 21:22:28', '2026-09-21 21:22:28'),
(1700, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-21 21:24:36', '2026-09-21 21:24:36'),
(1701, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-21 21:24:41', '2026-09-21 21:24:41'),
(1702, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-21 21:24:46', '2026-09-21 21:24:46'),
(1703, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-21 21:24:53', '2026-09-21 21:24:53'),
(1704, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-21 21:25:39', '2026-09-21 21:25:39'),
(1705, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-21 21:25:43', '2026-09-21 21:25:43'),
(1706, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-21 22:56:03', '2026-09-21 22:56:03'),
(1707, 20, 2, NULL, 'user_login', 'User logged in: Pharmacy Arusha (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-21 22:56:09', '2026-09-21 22:56:09'),
(1708, 20, 2, NULL, 'user_logout', 'User logged out: Pharmacy Arusha (Role: pharmacy)', NULL, NULL, '2026-09-21 22:56:20', '2026-09-21 22:56:20'),
(1709, 23, 2, NULL, 'user_login', 'User logged in: NYANSAEL NZILU (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-21 22:56:37', '2026-09-21 22:56:37'),
(1710, 23, 2, NULL, 'user_logout', 'User logged out: NYANSAEL NZILU (Role: reception)', NULL, NULL, '2026-09-21 22:58:26', '2026-09-21 22:58:26'),
(1711, 17, 2, NULL, 'user_login', 'User logged in: Dr. David Mwanga (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-21 22:58:38', '2026-09-21 22:58:38'),
(1712, 17, 2, NULL, 'user_logout', 'User logged out: Dr. David Mwanga (Role: doctor)', NULL, NULL, '2026-09-21 22:58:52', '2026-09-21 22:58:52'),
(1713, 18, 2, NULL, 'user_login', 'User logged in: Dr. Anna Kivuyo (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-21 22:58:56', '2026-09-21 22:58:56'),
(1714, 18, 2, NULL, 'user_logout', 'User logged out: Dr. Anna Kivuyo (Role: doctor)', NULL, NULL, '2026-09-21 22:59:16', '2026-09-21 22:59:16'),
(1715, 26, 2, NULL, 'user_login', 'User logged in: MARIA MSANGI (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-21 22:59:28', '2026-09-21 22:59:28'),
(1716, 26, 2, NULL, 'lab_test_started', 'Started lab test ID: 331 - Blood Check', NULL, NULL, '2026-09-21 22:59:37', '2026-09-21 22:59:37'),
(1717, 26, 2, NULL, 'user_logout', 'User logged out: MARIA MSANGI (Role: laboratory)', NULL, NULL, '2026-09-21 22:59:46', '2026-09-21 22:59:46'),
(1718, 18, 2, NULL, 'user_login', 'User logged in: Dr. Anna Kivuyo (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-21 22:59:50', '2026-09-21 22:59:50'),
(1719, 18, 2, NULL, 'user_logout', 'User logged out: Dr. Anna Kivuyo (Role: doctor)', NULL, NULL, '2026-09-21 23:01:08', '2026-09-21 23:01:08'),
(1720, 23, 2, NULL, 'user_login', 'User logged in: NYANSAEL NZILU (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-21 23:01:20', '2026-09-21 23:01:20'),
(1721, 23, 2, NULL, 'user_logout', 'User logged out: NYANSAEL NZILU (Role: reception)', NULL, NULL, '2026-09-21 23:01:30', '2026-09-21 23:01:30'),
(1722, 20, 2, NULL, 'user_login', 'User logged in: Pharmacy Arusha (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-21 23:01:39', '2026-09-21 23:01:39'),
(1723, 20, 2, NULL, 'user_logout', 'User logged out: Pharmacy Arusha (Role: pharmacy)', NULL, NULL, '2026-09-21 23:02:24', '2026-09-21 23:02:24'),
(1724, 23, 2, NULL, 'user_login', 'User logged in: NYANSAEL NZILU (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-21 23:02:46', '2026-09-21 23:02:46'),
(1725, 23, 2, NULL, 'user_logout', 'User logged out: NYANSAEL NZILU (Role: reception)', NULL, NULL, '2026-09-21 23:03:18', '2026-09-21 23:03:18'),
(1726, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-21 23:03:36', '2026-09-21 23:03:36'),
(1727, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-22 08:43:53', '2026-09-22 08:43:53'),
(1728, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-22 11:56:09', '2026-09-22 11:56:09'),
(1729, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-22 11:56:17', '2026-09-22 11:56:17'),
(1730, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-22 11:56:50', '2026-09-22 11:56:50'),
(1731, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-22 11:56:57', '2026-09-22 11:56:57'),
(1732, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-22 11:57:38', '2026-09-22 11:57:38'),
(1733, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO MSANGI (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-22 11:57:44', '2026-09-22 11:57:44'),
(1734, 13, 1, NULL, 'lab_tests_bulk_started', 'Started 4 lab test(s) in bulk (global)', NULL, NULL, '2026-09-22 11:57:53', '2026-09-22 11:57:53'),
(1735, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO MSANGI (Role: laboratory)', NULL, NULL, '2026-09-22 11:58:31', '2026-09-22 11:58:31'),
(1736, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-22 11:58:46', '2026-09-22 11:58:46'),
(1737, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-22 12:00:02', '2026-09-22 12:00:02'),
(1738, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-22 12:00:09', '2026-09-22 12:00:09'),
(1739, 1, 1, 63, 'procedure_updated', 'Edited Equipment: \'Blood Pressure Monitor\' → \'Blood Pressure Monitor\' | Price: TSh 25,000 → TSh 250,000 | Qty: 1 → 1 | Status: paid', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-22 12:06:33', '2026-09-22 12:06:33'),
(1740, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-22 12:06:42', '2026-09-22 12:06:42'),
(1741, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-22 12:06:54', '2026-09-22 12:06:54'),
(1742, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-22 12:07:37', '2026-09-22 12:07:37'),
(1743, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-22 12:07:44', '2026-09-22 12:07:44'),
(1744, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-22 12:08:06', '2026-09-22 12:08:06'),
(1745, 23, 2, NULL, 'user_login', 'User logged in: NYANSAEL NZILU (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-22 12:08:50', '2026-09-22 12:08:50'),
(1746, 23, 2, NULL, 'user_logout', 'User logged out: NYANSAEL NZILU (Role: reception)', NULL, NULL, '2026-09-22 12:09:16', '2026-09-22 12:09:16'),
(1747, 18, 2, NULL, 'user_login', 'User logged in: Dr. Anna Kivuyo (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-22 12:09:24', '2026-09-22 12:09:24'),
(1748, 18, 2, NULL, 'user_logout', 'User logged out: Dr. Anna Kivuyo (Role: doctor)', NULL, NULL, '2026-09-22 12:09:43', '2026-09-22 12:09:43'),
(1749, 26, 2, NULL, 'user_login', 'User logged in: MARIA MSANGI (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-22 12:09:49', '2026-09-22 12:09:49'),
(1750, 26, 2, NULL, 'lab_test_started', 'Started lab test ID: 336 - Blood Check', NULL, NULL, '2026-09-22 12:09:57', '2026-09-22 12:09:57'),
(1751, 26, 2, NULL, 'user_logout', 'User logged out: MARIA MSANGI (Role: laboratory)', NULL, NULL, '2026-09-22 12:10:11', '2026-09-22 12:10:11'),
(1752, 18, 2, NULL, 'user_login', 'User logged in: Dr. Anna Kivuyo (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-22 12:10:41', '2026-09-22 12:10:41'),
(1753, 45, 1, NULL, 'user_login', 'User logged in: NASMA ISMAIL (Mode: general_blue, Role: audit)', NULL, NULL, '2026-09-22 12:15:56', '2026-09-22 12:15:56'),
(1754, 18, 2, NULL, 'user_logout', 'User logged out: Dr. Anna Kivuyo (Role: doctor)', NULL, NULL, '2026-09-22 12:16:12', '2026-09-22 12:16:12'),
(1755, 45, 1, NULL, 'user_login', 'User logged in: NASMA ISMAIL (Mode: general_blue, Role: audit)', NULL, NULL, '2026-09-22 12:16:17', '2026-09-22 12:16:17'),
(1756, 45, 1, NULL, 'user_logout', 'User logged out: NASMA ISMAIL (Role: audit)', NULL, NULL, '2026-09-22 12:19:19', '2026-09-22 12:19:19'),
(1757, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-22 12:19:29', '2026-09-22 12:19:29'),
(1758, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-22 12:19:48', '2026-09-22 12:19:48'),
(1759, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-22 12:19:59', '2026-09-22 12:19:59'),
(1760, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-22 12:29:38', '2026-09-22 12:29:38'),
(1761, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-22 12:29:44', '2026-09-22 12:29:44'),
(1762, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-22 12:33:14', '2026-09-22 12:33:14'),
(1763, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-22 12:33:18', '2026-09-22 12:33:18'),
(1764, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-22 12:36:14', '2026-09-22 12:36:14'),
(1765, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-22 12:36:23', '2026-09-22 12:36:23'),
(1766, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-22 12:38:26', '2026-09-22 12:38:26'),
(1767, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-22 12:38:34', '2026-09-22 12:38:34'),
(1768, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-22 12:59:42', '2026-09-22 12:59:42'),
(1769, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-22 12:59:47', '2026-09-22 12:59:47'),
(1770, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-22 14:18:21', '2026-09-22 14:18:21'),
(1771, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-22 14:18:32', '2026-09-22 14:18:32'),
(1772, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-22 18:12:59', '2026-09-22 18:12:59'),
(1773, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-22 18:13:05', '2026-09-22 18:13:05'),
(1774, 45, 1, NULL, 'user_logout', 'User logged out: NASMA ISMAIL (Role: audit)', NULL, NULL, '2026-09-22 19:07:40', '2026-09-22 19:07:40'),
(1775, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-22 19:07:53', '2026-09-22 19:07:53'),
(1776, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-22 19:08:03', '2026-09-22 19:08:03'),
(1777, 25, 2, NULL, 'user_login', 'User logged in: LUCY PETER (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-22 19:08:24', '2026-09-22 19:08:24'),
(1778, 25, 2, NULL, 'user_logout', 'User logged out: LUCY PETER (Role: reception)', NULL, NULL, '2026-09-22 19:08:32', '2026-09-22 19:08:32'),
(1779, 45, 1, NULL, 'user_login', 'User logged in: NASMA ISMAIL (Mode: general_blue, Role: audit)', NULL, NULL, '2026-09-22 19:08:42', '2026-09-22 19:08:42'),
(1780, 45, 1, NULL, 'user_logout', 'User logged out: NASMA ISMAIL (Role: audit)', NULL, NULL, '2026-09-22 19:08:56', '2026-09-22 19:08:56'),
(1781, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-22 19:09:02', '2026-09-22 19:09:02'),
(1782, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-22 19:09:39', '2026-09-22 19:09:39'),
(1783, 45, 1, NULL, 'user_login', 'User logged in: NASMA ISMAIL (Mode: general_blue, Role: audit)', NULL, NULL, '2026-09-22 19:09:42', '2026-09-22 19:09:42'),
(1784, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-22 21:58:55', '2026-09-22 21:58:55'),
(1785, 30, 3, NULL, 'user_login', 'User logged in: Dr. James Kato (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-22 21:59:04', '2026-09-22 21:59:04'),
(1786, 30, 3, NULL, 'user_logout', 'User logged out: Dr. James Kato (Role: doctor)', NULL, NULL, '2026-09-22 22:00:57', '2026-09-22 22:00:57'),
(1787, 33, 3, NULL, 'user_login', 'User logged in: Pharmacy Dar (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-22 22:01:06', '2026-09-22 22:01:06'),
(1788, 33, 3, NULL, 'user_logout', 'User logged out: Pharmacy Dar (Role: pharmacy)', NULL, NULL, '2026-09-22 22:03:01', '2026-09-22 22:03:01'),
(1789, 30, 3, NULL, 'user_login', 'User logged in: Dr. James Kato (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-22 22:03:12', '2026-09-22 22:03:12'),
(1790, 30, 3, NULL, 'user_logout', 'User logged out: Dr. James Kato (Role: doctor)', NULL, NULL, '2026-09-22 22:04:33', '2026-09-22 22:04:33'),
(1791, 36, 3, NULL, 'user_login', 'User logged in: Reception Dar (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-22 22:06:15', '2026-09-22 22:06:15'),
(1792, 36, 3, NULL, 'user_logout', 'User logged out: Reception Dar (Role: reception)', NULL, NULL, '2026-09-22 22:10:22', '2026-09-22 22:10:22'),
(1793, 30, 3, NULL, 'user_login', 'User logged in: Dr. James Kato (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-22 22:10:28', '2026-09-22 22:10:28'),
(1794, 30, 3, NULL, 'user_logout', 'User logged out: Dr. James Kato (Role: doctor)', NULL, NULL, '2026-09-22 22:10:34', '2026-09-22 22:10:34'),
(1795, 31, 3, NULL, 'user_login', 'User logged in: Dr. Sarah Mwamba (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-22 22:10:38', '2026-09-22 22:10:38'),
(1796, 31, 3, NULL, 'user_logout', 'User logged out: Dr. Sarah Mwamba (Role: doctor)', NULL, NULL, '2026-09-22 22:11:02', '2026-09-22 22:11:02'),
(1797, 40, 3, NULL, 'user_login', 'User logged in: Thomas N (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-22 22:11:11', '2026-09-22 22:11:11'),
(1798, 40, 3, NULL, 'lab_test_started', 'Started lab test ID: 337 - COVID 19', NULL, NULL, '2026-09-22 22:11:20', '2026-09-22 22:11:20'),
(1799, 40, 3, NULL, 'user_logout', 'User logged out: Thomas N (Role: laboratory)', NULL, NULL, '2026-09-22 22:11:31', '2026-09-22 22:11:31'),
(1800, 31, 3, NULL, 'user_login', 'User logged in: Dr. Sarah Mwamba (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-22 22:11:41', '2026-09-22 22:11:41'),
(1801, 31, 3, NULL, 'user_logout', 'User logged out: Dr. Sarah Mwamba (Role: doctor)', NULL, NULL, '2026-09-22 22:14:22', '2026-09-22 22:14:22'),
(1802, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-22 22:14:36', '2026-09-22 22:14:36'),
(1803, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-24 12:34:26', '2026-09-24 12:34:26'),
(1804, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-24 12:46:01', '2026-09-24 12:46:01'),
(1805, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-24 12:46:22', '2026-09-24 12:46:22'),
(1806, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-24 12:49:19', '2026-09-24 12:49:19'),
(1807, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-24 12:49:29', '2026-09-24 12:49:29'),
(1808, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-24 12:50:02', '2026-09-24 12:50:02'),
(1809, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-24 12:50:08', '2026-09-24 12:50:08'),
(1810, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-24 13:00:04', '2026-09-24 13:00:04'),
(1811, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-24 13:00:11', '2026-09-24 13:00:11'),
(1812, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-24 13:02:02', '2026-09-24 13:02:02'),
(1813, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-24 13:06:39', '2026-09-24 13:06:39'),
(1814, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-24 13:09:59', '2026-09-24 13:09:59'),
(1815, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-24 13:35:42', '2026-09-24 13:35:42'),
(1816, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-24 13:35:50', '2026-09-24 13:35:50'),
(1817, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-24 13:38:56', '2026-09-24 13:38:56'),
(1818, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO MSANGI (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-24 13:39:01', '2026-09-24 13:39:01'),
(1819, 13, 1, NULL, 'lab_tests_bulk_started', 'Started 12 lab test(s) in bulk (global)', NULL, NULL, '2026-09-24 13:39:07', '2026-09-24 13:39:07'),
(1820, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO MSANGI (Role: laboratory)', NULL, NULL, '2026-09-24 13:42:21', '2026-09-24 13:42:21'),
(1821, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-24 13:42:26', '2026-09-24 13:42:26');
INSERT INTO `activity_logs` (`id`, `user_id`, `branch_id`, `patient_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`, `updated_at`) VALUES
(1822, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-24 13:53:38', '2026-09-24 13:53:38'),
(1823, 8, 1, NULL, 'user_login', 'User logged in: Mary John (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-24 13:53:43', '2026-09-24 13:53:43'),
(1824, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-24 14:01:31', '2026-09-24 14:01:31'),
(1825, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-24 14:01:57', '2026-09-24 14:01:57'),
(1826, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-24 14:02:02', '2026-09-24 14:02:02'),
(1827, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-24 14:02:26', '2026-09-24 14:02:26'),
(1828, 8, 1, NULL, 'user_logout', 'User logged out: Mary John (Role: pharmacy)', NULL, NULL, '2026-09-24 14:02:31', '2026-09-24 14:02:31'),
(1829, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-24 14:02:39', '2026-09-24 14:02:39'),
(1830, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-24 14:07:34', '2026-09-24 14:07:34'),
(1831, 8, 1, NULL, 'user_login', 'User logged in: Mary John (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-24 14:07:39', '2026-09-24 14:07:39'),
(1832, 8, 1, NULL, 'user_logout', 'User logged out: Mary John (Role: pharmacy)', NULL, NULL, '2026-09-24 14:09:59', '2026-09-24 14:09:59'),
(1833, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-24 14:10:14', '2026-09-24 14:10:14'),
(1834, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-24 14:38:38', '2026-09-24 14:38:38'),
(1835, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-24 14:38:40', '2026-09-24 14:38:40'),
(1836, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-24 14:40:20', '2026-09-24 14:40:20'),
(1837, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO MSANGI (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-24 14:40:25', '2026-09-24 14:40:25'),
(1838, 13, 1, NULL, 'lab_tests_bulk_started', 'Started 7 lab test(s) in bulk (global)', NULL, NULL, '2026-09-24 14:40:30', '2026-09-24 14:40:30'),
(1839, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO MSANGI (Role: laboratory)', NULL, NULL, '2026-09-24 14:41:33', '2026-09-24 14:41:33'),
(1840, 8, 1, NULL, 'user_login', 'User logged in: Mary John (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-24 14:41:41', '2026-09-24 14:41:41'),
(1841, 8, 1, NULL, 'user_logout', 'User logged out: Mary John (Role: pharmacy)', NULL, NULL, '2026-09-24 14:46:15', '2026-09-24 14:46:15'),
(1842, 8, 1, NULL, 'user_login', 'User logged in: Mary John (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-24 14:46:17', '2026-09-24 14:46:17'),
(1843, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-24 14:48:16', '2026-09-24 14:48:16'),
(1844, 8, 1, NULL, 'user_logout', 'User logged out: Mary John (Role: pharmacy)', NULL, NULL, '2026-09-24 14:48:53', '2026-09-24 14:48:53'),
(1845, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-24 14:48:58', '2026-09-24 14:48:58'),
(1846, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-24 14:51:32', '2026-09-24 14:51:32'),
(1847, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-24 14:54:24', '2026-09-24 14:54:24'),
(1848, 8, 1, NULL, 'user_login', 'User logged in: Mary John (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-24 14:54:30', '2026-09-24 14:54:30'),
(1849, 8, 1, NULL, 'user_logout', 'User logged out: Mary John (Role: pharmacy)', NULL, NULL, '2026-09-24 14:58:55', '2026-09-24 14:58:55'),
(1850, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-24 14:58:59', '2026-09-24 14:58:59'),
(1851, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-24 15:01:57', '2026-09-24 15:01:57'),
(1852, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-24 15:02:08', '2026-09-24 15:02:08'),
(1853, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-24 15:02:39', '2026-09-24 15:02:39'),
(1854, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-24 15:02:44', '2026-09-24 15:02:44'),
(1855, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-24 15:04:24', '2026-09-24 15:04:24'),
(1856, 8, 1, NULL, 'user_login', 'User logged in: Mary John (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-24 15:04:30', '2026-09-24 15:04:30'),
(1857, 8, 1, NULL, 'user_logout', 'User logged out: Mary John (Role: pharmacy)', NULL, NULL, '2026-09-24 15:35:48', '2026-09-24 15:35:48'),
(1858, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-24 15:35:55', '2026-09-24 15:35:55'),
(1859, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-24 15:38:50', '2026-09-24 15:38:50'),
(1860, 8, 1, NULL, 'user_login', 'User logged in: Mary John (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-24 15:38:55', '2026-09-24 15:38:55'),
(1861, 8, 1, NULL, 'user_logout', 'User logged out: Mary John (Role: pharmacy)', NULL, NULL, '2026-09-24 16:33:40', '2026-09-24 16:33:40'),
(1862, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-24 16:33:46', '2026-09-24 16:33:46'),
(1863, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-24 16:35:08', '2026-09-24 16:35:08'),
(1864, 8, 1, NULL, 'user_login', 'User logged in: Mary John (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-24 16:35:15', '2026-09-24 16:35:15'),
(1865, 8, 1, NULL, 'user_logout', 'User logged out: Mary John (Role: pharmacy)', NULL, NULL, '2026-09-24 17:00:20', '2026-09-24 17:00:20'),
(1866, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-24 17:00:28', '2026-09-24 17:00:28'),
(1867, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-24 18:10:04', '2026-09-24 18:10:04'),
(1868, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-24 18:10:09', '2026-09-24 18:10:09'),
(1869, 1, 1, NULL, 'delete_lab_test', 'Deleted lab test: Renal Function Test (RFT) (ID: 357) | Bill BILL-LAB-20260924-0061-4625 auto-updated', '::1', NULL, '2026-09-24 18:18:16', '2026-09-24 18:18:16'),
(1870, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-24 19:52:06', '2026-09-24 19:52:06'),
(1871, 8, 1, NULL, 'user_login', 'User logged in: Mary John (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-24 19:52:10', '2026-09-24 19:52:10'),
(1872, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-29 11:45:06', '2026-09-29 11:45:06'),
(1873, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-29 11:45:42', '2026-09-29 11:45:42'),
(1874, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-29 11:45:46', '2026-09-29 11:45:46'),
(1875, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-29 11:45:59', '2026-09-29 11:45:59'),
(1876, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-29 11:46:04', '2026-09-29 11:46:04'),
(1877, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-29 11:50:22', '2026-09-29 11:50:22'),
(1878, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-29 11:50:25', '2026-09-29 11:50:25'),
(1879, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-29 11:51:37', '2026-09-29 11:51:37'),
(1880, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO MSANGI (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-29 11:51:42', '2026-09-29 11:51:42'),
(1881, 13, 1, NULL, 'lab_tests_bulk_started', 'Started 10 lab test(s) in bulk (global)', NULL, NULL, '2026-09-29 11:52:05', '2026-09-29 11:52:05'),
(1882, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO MSANGI (Role: laboratory)', NULL, NULL, '2026-09-29 12:02:48', '2026-09-29 12:02:48'),
(1883, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-29 12:02:54', '2026-09-29 12:02:54'),
(1884, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-29 12:14:43', '2026-09-29 12:14:43'),
(1885, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-29 12:14:53', '2026-09-29 12:14:53'),
(1886, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-09-29 12:22:34', '2026-09-29 12:22:34'),
(1887, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-29 12:22:51', '2026-09-29 12:22:51'),
(1888, 1, 1, NULL, 'bill_recalculated', 'Updated discounts/premiums for bill #398 | Total: 118,000 | Paid: 0 | Balance: 118,000 | Status: pending', '::1', NULL, '2026-09-29 12:23:28', '2026-09-29 12:23:28'),
(1889, 1, 1, NULL, 'bill_recalculated', 'Updated discounts/premiums for bill #398 | Total: 110,000 | Paid: 0 | Balance: 110,000 | Status: pending', '::1', NULL, '2026-09-29 12:23:40', '2026-09-29 12:23:40'),
(1890, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-29 12:28:11', '2026-09-29 12:28:11'),
(1891, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-29 12:28:15', '2026-09-29 12:28:15'),
(1892, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-29 12:31:58', '2026-09-29 12:31:58'),
(1893, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-29 12:32:04', '2026-09-29 12:32:04'),
(1894, 1, 1, NULL, 'bill_recalculated', 'Updated discounts/premiums for bill #397 | Total: 110,000 | Paid: 0 | Balance: 110,000 | Status: pending', '::1', NULL, '2026-09-29 12:33:08', '2026-09-29 12:33:08'),
(1895, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-29 12:37:21', '2026-09-29 12:37:21'),
(1896, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-29 12:37:29', '2026-09-29 12:37:29'),
(1897, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-29 12:43:58', '2026-09-29 12:43:58'),
(1898, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-29 12:44:05', '2026-09-29 12:44:05'),
(1899, 1, 1, NULL, 'bill_recalculated', 'Added procedure \'Cryotherapy\' | Total: 130,000 | Paid: 0 | Balance: 130,000 | Status: pending', '::1', NULL, '2026-09-29 12:45:14', '2026-09-29 12:45:14'),
(1900, 1, 1, NULL, 'bill_recalculated', 'Updated discounts/premiums for bill #397 | Total: 137,000 | Paid: 0 | Balance: 137,000 | Status: pending', '::1', NULL, '2026-09-29 12:45:44', '2026-09-29 12:45:44'),
(1901, 1, 1, NULL, 'bill_recalculated', 'Updated discounts/premiums for bill #397 | Total: 140,000 | Paid: 0 | Balance: 140,000 | Status: pending', '::1', NULL, '2026-09-29 12:45:55', '2026-09-29 12:45:55'),
(1902, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-29 12:49:15', '2026-09-29 12:49:15'),
(1903, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-29 12:49:23', '2026-09-29 12:49:23'),
(1904, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-29 12:50:19', '2026-09-29 12:50:19'),
(1905, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-29 12:50:24', '2026-09-29 12:50:24'),
(1906, 1, 1, NULL, 'bill_recalculated', 'Deleted lab test \'Blood Check\' | Total: 55,000 | Paid: 0 | Balance: 55,000 | Status: pending', '::1', NULL, '2026-09-29 13:22:30', '2026-09-29 13:22:30'),
(1907, 1, 1, NULL, 'bill_recalculated', 'Updated discounts/premiums for bill #396 | Total: 55,000 | Paid: 0 | Balance: 55,000 | Status: pending', '::1', NULL, '2026-09-29 13:22:44', '2026-09-29 13:22:44'),
(1908, 1, 2, NULL, 'employee_deactivated', 'Deactivated: ANGERITHA KIMARO by System Admin', NULL, NULL, '2026-09-29 13:42:13', '2026-09-29 13:42:13'),
(1909, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-29 13:51:41', '2026-09-29 13:51:41'),
(1910, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-29 13:51:57', '2026-09-29 13:51:57'),
(1911, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-29 14:28:25', '2026-09-29 14:28:25'),
(1912, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-29 14:28:28', '2026-09-29 14:28:28'),
(1913, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-29 14:28:58', '2026-09-29 14:28:58'),
(1914, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-29 14:29:03', '2026-09-29 14:29:03'),
(1915, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-29 14:30:12', '2026-09-29 14:30:12'),
(1916, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-29 14:30:16', '2026-09-29 14:30:16'),
(1917, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-29 14:30:52', '2026-09-29 14:30:52'),
(1918, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-29 14:30:59', '2026-09-29 14:30:59'),
(1919, 1, 1, NULL, 'delete_visit', 'Deleted visit: VIS-20260929-8820', '::1', NULL, '2026-09-29 14:31:30', '2026-09-29 14:31:30'),
(1920, 1, 1, NULL, 'delete_visit', 'Deleted visit: VIS-20260929-1939', '::1', NULL, '2026-09-29 14:31:58', '2026-09-29 14:31:58'),
(1921, 1, 1, NULL, 'delete_visit', 'Deleted visit: VIS-20260929-9627', '::1', NULL, '2026-09-29 14:32:17', '2026-09-29 14:32:17'),
(1922, 1, 1, NULL, 'delete_visit', 'Deleted visit: VIS-20260929-9623', '::1', NULL, '2026-09-29 14:32:30', '2026-09-29 14:32:30'),
(1923, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-09-29 14:34:19', '2026-09-29 14:34:19'),
(1924, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-29 14:34:22', '2026-09-29 14:34:22'),
(1925, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-29 14:39:18', '2026-09-29 14:39:18'),
(1926, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-29 14:39:29', '2026-09-29 14:39:29'),
(1927, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-29 14:39:56', '2026-09-29 14:39:56'),
(1928, 8, 1, NULL, 'user_login', 'User logged in: Mary John (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-29 14:40:03', '2026-09-29 14:40:03'),
(1929, 8, 1, NULL, 'user_logout', 'User logged out: Mary John (Role: pharmacy)', NULL, NULL, '2026-09-29 14:41:33', '2026-09-29 14:41:33'),
(1930, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-29 14:41:40', '2026-09-29 14:41:40'),
(1931, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-29 14:41:57', '2026-09-29 14:41:57'),
(1932, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-29 14:42:05', '2026-09-29 14:42:05'),
(1933, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-09-29 14:43:01', '2026-09-29 14:43:01'),
(1934, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-29 14:43:05', '2026-09-29 14:43:05'),
(1935, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-29 15:07:43', '2026-09-29 15:07:43'),
(1936, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-29 15:07:47', '2026-09-29 15:07:47'),
(1937, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-30 10:19:21', '2026-09-30 10:19:21'),
(1938, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-30 10:20:06', '2026-09-30 10:20:06'),
(1939, 15, 1, NULL, 'user_login', 'User logged in: Sarah Mwamba (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-09-30 10:20:19', '2026-09-30 10:20:19'),
(1940, 15, 1, NULL, 'lab_tests_bulk_started', 'Started 3 lab test(s) in bulk (global)', NULL, NULL, '2026-09-30 10:20:30', '2026-09-30 10:20:30'),
(1941, 15, 1, NULL, 'user_logout', 'User logged out: Sarah Mwamba (Role: laboratory)', NULL, NULL, '2026-09-30 10:21:08', '2026-09-30 10:21:08'),
(1942, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-30 10:21:15', '2026-09-30 10:21:15'),
(1943, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-30 10:23:26', '2026-09-30 10:23:26'),
(1944, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-09-30 10:23:36', '2026-09-30 10:23:36'),
(1945, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-09-30 10:23:56', '2026-09-30 10:23:56'),
(1946, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-30 10:23:59', '2026-09-30 10:23:59'),
(1947, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-30 10:24:15', '2026-09-30 10:24:15'),
(1948, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-30 10:24:23', '2026-09-30 10:24:23'),
(1949, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-09-30 10:25:40', '2026-09-30 10:25:40'),
(1950, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-09-30 10:25:43', '2026-09-30 10:25:43'),
(1951, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-09-30 14:35:27', '2026-09-30 14:35:27'),
(1952, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-09-30 14:35:34', '2026-09-30 14:35:34'),
(1953, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-09-30 21:17:26', '2026-09-30 21:17:26'),
(1954, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-10-01 11:55:03', '2026-10-01 11:55:03'),
(1955, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-10-01 11:55:12', '2026-10-01 11:55:12'),
(1956, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-10-01 11:55:17', '2026-10-01 11:55:17'),
(1957, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-10-01 11:55:42', '2026-10-01 11:55:42'),
(1958, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-10-01 11:55:47', '2026-10-01 11:55:47'),
(1959, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-10-01 16:46:25', '2026-10-01 16:46:25'),
(1960, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-10-04 16:26:04', '2026-10-04 16:26:04'),
(1961, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-10-04 16:30:40', '2026-10-04 16:30:40'),
(1962, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-10-04 16:30:44', '2026-10-04 16:30:44'),
(1963, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-10-04 16:32:39', '2026-10-04 16:32:39'),
(1964, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-10-04 16:32:44', '2026-10-04 16:32:44'),
(1965, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-10-05 17:26:39', '2026-10-05 17:26:39'),
(1966, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-10-16 11:23:31', '2026-10-16 11:23:31'),
(1967, 10, 1, NULL, 'appointment_created', 'New appointment created for patient ID: 62 with doctor ID: 4', NULL, NULL, '2026-10-16 11:41:52', '2026-10-16 11:41:52'),
(1968, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-10-16 11:42:11', '2026-10-16 11:42:11'),
(1969, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-10-16 11:42:19', '2026-10-16 11:42:19'),
(1970, 4, 1, 62, 'appointment_confirmed', 'Appointment #3 confirmed for patient: JACKSON PIUS MYULA (ID: P-2026-01-0016) | Doctor: Dr.ERICK JOHN | Notes: None', NULL, NULL, '2026-10-16 11:42:40', '2026-10-16 11:42:40'),
(1971, 4, 1, 63, 'referral_created', 'Patient referred externally: SAMSON  PIUS MYULA (#REF-20261016-0063-322) - Visit ID: 238 - To: MUHIMBILI', NULL, NULL, '2026-10-16 11:45:49', '2026-10-16 11:45:49'),
(1972, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-10-16 11:59:27', '2026-10-16 11:59:27'),
(1973, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO MSANGI (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-10-16 11:59:34', '2026-10-16 11:59:34'),
(1974, 13, 1, NULL, 'lab_tests_bulk_started', 'Started 3 test(s) for patient: DANIEL MUSSA JOHORA', NULL, NULL, '2026-10-16 12:00:33', '2026-10-16 12:00:33'),
(1975, 13, 1, NULL, 'lab_result_saved', 'Saved result for test ID #373 - Complete Blood Count (CBC) | Visit #241 | All completed: NO', NULL, NULL, '2026-10-16 12:01:19', '2026-10-16 12:01:19'),
(1976, 13, 1, NULL, 'lab_result_saved', 'Saved result for test ID #374 - Echocardiogram | Visit #241 | All completed: NO', NULL, NULL, '2026-10-16 12:01:44', '2026-10-16 12:01:44'),
(1977, 13, 1, NULL, 'lab_result_saved', 'Saved result for test ID #375 - Ultrasound - Liver/Biliary | Visit #241 | All completed: YES', NULL, NULL, '2026-10-16 12:03:32', '2026-10-16 12:03:32'),
(1978, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO MSANGI (Role: laboratory)', NULL, NULL, '2026-10-16 12:05:03', '2026-10-16 12:05:03'),
(1979, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-10-16 12:05:09', '2026-10-16 12:05:09'),
(1980, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-10-16 12:16:53', '2026-10-16 12:16:53'),
(1981, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-10-16 12:17:02', '2026-10-16 12:17:02'),
(1982, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-10-16 12:18:27', '2026-10-16 12:18:27'),
(1983, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-10-16 12:18:34', '2026-10-16 12:18:34'),
(1984, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-10-16 12:32:29', '2026-10-16 12:32:29'),
(1985, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-10-16 12:32:38', '2026-10-16 12:32:38'),
(1986, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-10-16 12:36:26', '2026-10-16 12:36:26'),
(1987, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-10-16 12:36:44', '2026-10-16 12:36:44'),
(1988, 1, 1, NULL, 'employee_added', 'Employee FATMATA GIG added with roles: audit (Primary role: audit)', NULL, NULL, '2026-10-16 12:40:49', '2026-10-16 12:40:49'),
(1989, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-10-16 12:51:20', '2026-10-16 12:51:20'),
(1990, 50, 1, NULL, 'user_login', 'User logged in: FATMATA GIG (Mode: general_blue, Role: audit)', NULL, NULL, '2026-10-16 12:51:39', '2026-10-16 12:51:39'),
(1991, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-10-17 16:57:31', '2026-10-17 16:57:31'),
(1992, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-10-17 16:58:32', '2026-10-17 16:58:32'),
(1993, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-10-17 16:58:35', '2026-10-17 16:58:35'),
(1994, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-10-17 16:58:47', '2026-10-17 16:58:47'),
(1995, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-10-17 16:58:54', '2026-10-17 16:58:54'),
(1996, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-10-17 16:59:15', '2026-10-17 16:59:15'),
(1997, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-10-17 16:59:18', '2026-10-17 16:59:18'),
(1998, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-10-17 16:59:31', '2026-10-17 16:59:31'),
(1999, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general_blue, Role: reception)', NULL, NULL, '2026-10-17 16:59:41', '2026-10-17 16:59:41'),
(2000, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-10-17 17:00:04', '2026-10-17 17:00:04'),
(2001, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-10-17 17:00:10', '2026-10-17 17:00:10'),
(2002, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-10-17 18:54:40', '2026-10-17 18:54:40'),
(2003, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-10-17 18:54:58', '2026-10-17 18:54:58'),
(2004, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-10-17 18:55:01', '2026-10-17 18:55:01'),
(2005, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-10-17 18:55:26', '2026-10-17 18:55:26'),
(2006, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-10-17 18:55:32', '2026-10-17 18:55:32'),
(2007, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-10-17 18:56:11', '2026-10-17 18:56:11'),
(2008, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-10-17 18:56:15', '2026-10-17 18:56:15'),
(2009, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-10-17 19:09:43', '2026-10-17 19:09:43'),
(2010, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-10-17 19:09:49', '2026-10-17 19:09:49'),
(2011, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-10-17 19:10:13', '2026-10-17 19:10:13'),
(2012, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-10-17 19:10:16', '2026-10-17 19:10:16'),
(2013, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-10-21 14:04:59', '2026-10-21 14:04:59'),
(2014, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-10-21 14:05:18', '2026-10-21 14:05:18'),
(2015, 15, 1, NULL, 'user_login', 'User logged in: Sarah Mwamba (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-10-21 14:05:25', '2026-10-21 14:05:25'),
(2016, 15, 1, NULL, 'lab_test_started', 'Started lab test ID: 376 - Lipid Profile', NULL, NULL, '2026-10-21 14:05:34', '2026-10-21 14:05:34'),
(2017, 15, 1, NULL, 'lab_result_saved', 'Saved result for test ID #376 - Lipid Profile | Visit #246 | All completed: YES', NULL, NULL, '2026-10-21 14:05:47', '2026-10-21 14:05:47'),
(2018, 15, 1, NULL, 'user_logout', 'User logged out: Sarah Mwamba (Role: laboratory)', NULL, NULL, '2026-10-21 14:05:52', '2026-10-21 14:05:52'),
(2019, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-10-21 14:05:56', '2026-10-21 14:05:56'),
(2020, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-10-21 14:06:55', '2026-10-21 14:06:55'),
(2021, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-10-21 14:07:02', '2026-10-21 14:07:02'),
(2022, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-10-21 14:08:05', '2026-10-21 14:08:05'),
(2023, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-10-21 14:08:10', '2026-10-21 14:08:10'),
(2024, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-10-21 14:08:39', '2026-10-21 14:08:39'),
(2025, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-10-21 14:08:46', '2026-10-21 14:08:46'),
(2026, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-10-21 14:09:07', '2026-10-21 14:09:07'),
(2027, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-10-21 14:09:09', '2026-10-21 14:09:09'),
(2028, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-10-21 14:09:58', '2026-10-21 14:09:58'),
(2029, 8, 1, NULL, 'user_login', 'User logged in: Mary John (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-10-21 14:10:03', '2026-10-21 14:10:03'),
(2030, 8, 1, NULL, 'user_logout', 'User logged out: Mary John (Role: pharmacy)', NULL, NULL, '2026-10-21 14:10:38', '2026-10-21 14:10:38'),
(2031, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-10-21 14:10:49', '2026-10-21 14:10:49'),
(2032, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-10-21 14:23:12', '2026-10-21 14:23:12'),
(2033, 8, 1, NULL, 'user_login', 'User logged in: Mary John (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-10-21 14:23:20', '2026-10-21 14:23:20'),
(2034, 8, 1, NULL, 'user_logout', 'User logged out: Mary John (Role: pharmacy)', NULL, NULL, '2026-10-21 14:23:40', '2026-10-21 14:23:40'),
(2035, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-10-21 14:23:43', '2026-10-21 14:23:43'),
(2036, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-10-21 14:25:35', '2026-10-21 14:25:35'),
(2037, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-10-21 14:25:41', '2026-10-21 14:25:41'),
(2038, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-10-21 14:25:54', '2026-10-21 14:25:54'),
(2039, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-10-21 14:26:06', '2026-10-21 14:26:06'),
(2040, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-10-21 14:29:59', '2026-10-21 14:29:59'),
(2041, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-10-21 14:30:04', '2026-10-21 14:30:04'),
(2042, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-10-21 14:30:38', '2026-10-21 14:30:38'),
(2043, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-10-21 14:30:47', '2026-10-21 14:30:47'),
(2044, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-10-21 14:31:14', '2026-10-21 14:31:14'),
(2045, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-10-21 14:31:24', '2026-10-21 14:31:24'),
(2046, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-10-21 14:32:50', '2026-10-21 14:32:50'),
(2047, 8, 1, NULL, 'user_login', 'User logged in: Mary John (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-10-21 14:32:57', '2026-10-21 14:32:57'),
(2048, 8, 1, NULL, 'user_logout', 'User logged out: Mary John (Role: pharmacy)', NULL, NULL, '2026-10-21 14:34:14', '2026-10-21 14:34:14'),
(2049, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-10-21 14:35:08', '2026-10-21 14:35:08'),
(2050, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-10-21 14:35:43', '2026-10-21 14:35:43'),
(2051, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-10-21 14:35:48', '2026-10-21 14:35:48'),
(2052, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-10-21 19:10:09', '2026-10-21 19:10:09'),
(2053, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-10-21 19:10:36', '2026-10-21 19:10:36'),
(2054, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-10-21 19:10:42', '2026-10-21 19:10:42'),
(2055, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-10-21 19:11:34', '2026-10-21 19:11:34'),
(2056, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general_blue, Role: reception)', NULL, NULL, '2026-10-21 19:11:41', '2026-10-21 19:11:41'),
(2057, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-10-21 19:12:05', '2026-10-21 19:12:05'),
(2058, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-10-21 19:12:09', '2026-10-21 19:12:09'),
(2059, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-10-21 19:12:53', '2026-10-21 19:12:53'),
(2060, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-10-21 19:13:00', '2026-10-21 19:13:00'),
(2061, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-10-21 19:13:20', '2026-10-21 19:13:20'),
(2062, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-10-21 19:13:25', '2026-10-21 19:13:25'),
(2063, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-10-21 19:15:18', '2026-10-21 19:15:18'),
(2064, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-10-21 19:15:29', '2026-10-21 19:15:29'),
(2065, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-10-26 17:58:04', '2026-10-26 17:58:04'),
(2066, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-10-26 17:59:54', '2026-10-26 17:59:54'),
(2067, 8, 1, NULL, 'user_login', 'User logged in: Mary John (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-10-26 17:59:59', '2026-10-26 17:59:59'),
(2068, 8, 1, NULL, 'user_login', 'User logged in: Mary John (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-10-27 19:33:34', '2026-10-27 19:33:34'),
(2069, 8, 1, NULL, 'user_logout', 'User logged out: Mary John (Role: pharmacy)', NULL, NULL, '2026-10-27 19:37:13', '2026-10-27 19:37:13'),
(2070, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-10-27 19:37:18', '2026-10-27 19:37:18'),
(2071, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-10-27 19:41:21', '2026-10-27 19:41:21'),
(2072, 13, 1, NULL, 'user_login', 'User logged in: ANGERITHA KIMARO MSANGI (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-10-27 19:41:28', '2026-10-27 19:41:28'),
(2073, 13, 1, NULL, 'lab_tests_bulk_started', 'Started 2 lab test(s) in bulk (global)', NULL, NULL, '2026-10-27 19:41:34', '2026-10-27 19:41:34'),
(2074, 13, 1, NULL, 'lab_result_saved', 'Saved result for test ID #377 - KFADURO | Visit #249 | All completed: NO', NULL, NULL, '2026-10-27 19:41:49', '2026-10-27 19:41:49'),
(2075, 13, 1, NULL, 'lab_result_saved', 'Saved result for test ID #378 - COVID-19 PCR Test | Visit #249 | All completed: YES', NULL, NULL, '2026-10-27 19:41:56', '2026-10-27 19:41:56'),
(2076, 13, 1, NULL, 'user_logout', 'User logged out: ANGERITHA KIMARO MSANGI (Role: laboratory)', NULL, NULL, '2026-10-27 19:41:58', '2026-10-27 19:41:58'),
(2077, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-10-27 19:42:03', '2026-10-27 19:42:03'),
(2078, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-10-27 19:43:06', '2026-10-27 19:43:06'),
(2079, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general_blue, Role: reception)', NULL, NULL, '2026-10-27 19:43:14', '2026-10-27 19:43:14'),
(2080, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-10-27 19:45:18', '2026-10-27 19:45:18'),
(2081, 8, 1, NULL, 'user_login', 'User logged in: Mary John (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-10-27 19:46:16', '2026-10-27 19:46:16'),
(2082, 8, 1, NULL, 'user_logout', 'User logged out: Mary John (Role: pharmacy)', NULL, NULL, '2026-10-27 19:47:29', '2026-10-27 19:47:29'),
(2083, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-10-27 19:47:34', '2026-10-27 19:47:34'),
(2084, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-10-27 19:50:30', '2026-10-27 19:50:30'),
(2085, 8, 1, NULL, 'user_login', 'User logged in: Mary John (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-10-27 19:50:38', '2026-10-27 19:50:38'),
(2086, 8, 1, NULL, 'user_logout', 'User logged out: Mary John (Role: pharmacy)', NULL, NULL, '2026-10-27 19:50:58', '2026-10-27 19:50:58'),
(2087, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-10-27 19:51:02', '2026-10-27 19:51:02'),
(2088, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-10-27 19:51:06', '2026-10-27 19:51:06'),
(2089, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-10-27 19:51:13', '2026-10-27 19:51:13'),
(2090, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-10-27 19:51:45', '2026-10-27 19:51:45'),
(2091, 8, 1, NULL, 'user_login', 'User logged in: Mary John (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-10-27 19:51:54', '2026-10-27 19:51:54'),
(2092, 8, 1, NULL, 'user_logout', 'User logged out: Mary John (Role: pharmacy)', NULL, NULL, '2026-10-27 21:20:25', '2026-10-27 21:20:25'),
(2093, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-10-27 21:20:32', '2026-10-27 21:20:32'),
(2094, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-10-27 21:27:17', '2026-10-27 21:27:17'),
(2095, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-10-27 21:27:23', '2026-10-27 21:27:23'),
(2096, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-10-27 21:52:47', '2026-10-27 21:52:47'),
(2097, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-10-27 21:52:54', '2026-10-27 21:52:54'),
(2098, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-10-27 22:04:37', '2026-10-27 22:04:37'),
(2099, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-10-27 22:04:43', '2026-10-27 22:04:43'),
(2100, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-10-27 22:05:05', '2026-10-27 22:05:05'),
(2101, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-10-27 22:05:09', '2026-10-27 22:05:09'),
(2102, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-10-27 22:06:14', '2026-10-27 22:06:14'),
(2103, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-10-27 22:06:25', '2026-10-27 22:06:25'),
(2104, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-10-27 22:06:35', '2026-10-27 22:06:35'),
(2105, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-10-27 22:06:39', '2026-10-27 22:06:39'),
(2106, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-10-27 22:07:21', '2026-10-27 22:07:21'),
(2107, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-10-27 22:07:26', '2026-10-27 22:07:26'),
(2108, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-10-27 22:07:53', '2026-10-27 22:07:53'),
(2109, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-10-27 22:08:06', '2026-10-27 22:08:06'),
(2110, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-10-27 22:08:10', '2026-10-27 22:08:10'),
(2111, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-10-27 22:08:16', '2026-10-27 22:08:16'),
(2112, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-10-27 22:14:29', '2026-10-27 22:14:29'),
(2113, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-10-27 22:14:32', '2026-10-27 22:14:32'),
(2114, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-10-27 22:18:23', '2026-10-27 22:18:23'),
(2115, 11, 1, NULL, 'user_login', 'User logged in: Rose Mwangi (Mode: general_blue, Role: reception)', NULL, NULL, '2026-10-27 22:18:29', '2026-10-27 22:18:29'),
(2116, 11, 1, NULL, 'user_logout', 'User logged out: Rose Mwangi (Role: reception)', NULL, NULL, '2026-10-27 22:18:54', '2026-10-27 22:18:54'),
(2117, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-10-27 22:18:57', '2026-10-27 22:18:57'),
(2118, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-10-27 22:23:54', '2026-10-27 22:23:54'),
(2119, 15, 1, NULL, 'user_login', 'User logged in: Sarah Mwamba (Mode: general_blue, Role: laboratory)', NULL, NULL, '2026-10-27 22:24:02', '2026-10-27 22:24:02'),
(2120, 15, 1, NULL, 'lab_tests_bulk_started', 'Started 3 lab test(s) in bulk (global)', NULL, NULL, '2026-10-27 22:24:09', '2026-10-27 22:24:09'),
(2121, 15, 1, NULL, 'lab_result_saved', 'Saved result for test ID #379 - Blood Check | Visit #251 | All completed: NO', NULL, NULL, '2026-10-27 22:24:21', '2026-10-27 22:24:21'),
(2122, 15, 1, NULL, 'lab_result_saved', 'Saved result for test ID #380 - Blood Glucose (Random) | Visit #251 | All completed: NO', NULL, NULL, '2026-10-27 22:24:29', '2026-10-27 22:24:29'),
(2123, 15, 1, NULL, 'lab_result_saved', 'Saved result for test ID #381 - KFADURO | Visit #251 | All completed: YES', NULL, NULL, '2026-10-27 22:24:43', '2026-10-27 22:24:43'),
(2124, 15, 1, NULL, 'user_logout', 'User logged out: Sarah Mwamba (Role: laboratory)', NULL, NULL, '2026-10-27 22:24:45', '2026-10-27 22:24:45'),
(2125, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-10-27 22:24:48', '2026-10-27 22:24:48'),
(2126, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-10-27 22:26:23', '2026-10-27 22:26:23'),
(2127, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-10-27 22:26:28', '2026-10-27 22:26:28'),
(2128, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-10-27 22:26:42', '2026-10-27 22:26:42'),
(2129, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-10-27 22:26:51', '2026-10-27 22:26:51'),
(2130, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-10-27 22:34:16', '2026-10-27 22:34:16'),
(2131, 8, 1, NULL, 'user_login', 'User logged in: Mary John (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-10-27 22:34:36', '2026-10-27 22:34:36'),
(2132, 8, 1, NULL, 'user_login', 'User logged in: Mary John (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-10-28 12:52:09', '2026-10-28 12:52:09'),
(2133, 8, 1, NULL, 'user_logout', 'User logged out: Mary John (Role: pharmacy)', NULL, NULL, '2026-10-28 12:56:29', '2026-10-28 12:56:29'),
(2134, 8, 1, NULL, 'user_login', 'User logged in: Mary John (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-10-28 12:57:38', '2026-10-28 12:57:38'),
(2135, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-10-29 20:01:01', '2026-10-29 20:01:01'),
(2136, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-10-29 20:01:55', '2026-10-29 20:01:55'),
(2137, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-10-29 20:02:00', '2026-10-29 20:02:00'),
(2138, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-11-10 11:08:17', '2026-11-10 11:08:17'),
(2139, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-11-10 11:08:33', '2026-11-10 11:08:33'),
(2140, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-11-10 11:08:36', '2026-11-10 11:08:36'),
(2141, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-11-10 11:08:43', '2026-11-10 11:08:43'),
(2142, 8, 1, NULL, 'user_login', 'User logged in: Mary John (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-11-10 11:08:49', '2026-11-10 11:08:49'),
(2143, 8, 1, NULL, 'user_logout', 'User logged out: Mary John (Role: pharmacy)', NULL, NULL, '2026-11-10 11:09:30', '2026-11-10 11:09:30'),
(2144, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-11-10 11:09:55', '2026-11-10 11:09:55'),
(2145, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-11-10 11:19:22', '2026-11-10 11:19:22'),
(2146, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-11-10 11:19:25', '2026-11-10 11:19:25'),
(2147, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-11-10 11:20:22', '2026-11-10 11:20:22');
INSERT INTO `activity_logs` (`id`, `user_id`, `branch_id`, `patient_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`, `updated_at`) VALUES
(2148, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-11-10 11:20:29', '2026-11-10 11:20:29'),
(2149, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-11-10 11:20:49', '2026-11-10 11:20:49'),
(2150, 8, 1, NULL, 'user_login', 'User logged in: Mary John (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-11-10 11:20:55', '2026-11-10 11:20:55'),
(2151, 8, 1, NULL, 'user_logout', 'User logged out: Mary John (Role: pharmacy)', NULL, NULL, '2026-11-10 11:21:32', '2026-11-10 11:21:32'),
(2152, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-11-10 11:21:42', '2026-11-10 11:21:42'),
(2153, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-11-10 11:22:41', '2026-11-10 11:22:41'),
(2154, 8, 1, NULL, 'user_login', 'User logged in: Mary John (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-11-10 11:22:46', '2026-11-10 11:22:46'),
(2155, 8, 1, NULL, 'user_logout', 'User logged out: Mary John (Role: pharmacy)', NULL, NULL, '2026-11-10 11:23:08', '2026-11-10 11:23:08'),
(2156, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-11-10 11:23:15', '2026-11-10 11:23:15'),
(2157, 1, 1, NULL, 'bill_recalculated', 'Added medication \'ALBENDAZOLE\' x10 | Total: 140,000 | Paid: 110,000 | Balance: 30,000 | Status: partial', '::1', NULL, '2026-11-10 11:27:09', '2026-11-10 11:27:09'),
(2158, 1, 1, NULL, 'bill_recalculated', 'Updated discounts/premiums for bill #420 | Total: 140,000 | Paid: 110,000 | Balance: 30,000 | Status: partial', '::1', NULL, '2026-11-10 11:27:15', '2026-11-10 11:27:15'),
(2159, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-11-10 11:27:19', '2026-11-10 11:27:19'),
(2160, 8, 1, NULL, 'user_login', 'User logged in: Mary John (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-11-10 11:27:25', '2026-11-10 11:27:25'),
(2161, 8, 1, NULL, 'user_logout', 'User logged out: Mary John (Role: pharmacy)', NULL, NULL, '2026-11-10 11:29:44', '2026-11-10 11:29:44'),
(2162, 1, 1, NULL, 'user_login', 'User logged in: System Admin (Mode: general_blue, Role: admin)', NULL, NULL, '2026-11-10 11:29:56', '2026-11-10 11:29:56'),
(2163, 1, 1, NULL, 'delete_bill_item', 'Deleted item #1601 (ALBENDAZOLE) from Bill BILL-CONS-20261029-0062-8702 | Total: 140,000 → 110,000 | Paid: 110,000 → 110,000 | Balance: 30,000 → 0 | Status: partial → paid | No payment adjustment', '::1', NULL, '2026-11-10 11:30:31', '2026-11-10 11:30:31'),
(2164, 1, 1, NULL, 'bill_recalculated', 'Deleted medication \'ALBENDAZOLE\' x10 | Total: 5,000 | Paid: 5,000 | Balance: 0 | Status: paid', '::1', NULL, '2026-11-10 11:33:02', '2026-11-10 11:33:02'),
(2165, 1, 1, NULL, 'bill_recalculated', 'Added medication \'ALBENDAZOLE\' x10 (pending pharmacy confirmation) | Total: 35,000 | Paid: 35,000 | Balance: 0 | Status: paid', '::1', NULL, '2026-11-10 11:33:17', '2026-11-10 11:33:17'),
(2166, 1, 1, NULL, 'bill_recalculated', 'Updated discounts/premiums for bill #420 | Total: 35,000 | Paid: 35,000 | Balance: 0 | Status: paid', '::1', NULL, '2026-11-10 11:33:52', '2026-11-10 11:33:52'),
(2167, 1, 1, NULL, 'bill_recalculated', 'Added medication \'ALBENDAZOLE\' x10 (pending pharmacy confirmation) | Total: 380,000 | Paid: 350,000 | Balance: 30,000 | Status: partial', '::1', NULL, '2026-11-10 11:42:48', '2026-11-10 11:42:48'),
(2168, 1, 1, NULL, 'bill_recalculated', 'Updated discounts/premiums for bill #416 | Total: 380,000 | Paid: 350,000 | Balance: 30,000 | Status: partial', '::1', NULL, '2026-11-10 11:42:55', '2026-11-10 11:42:55'),
(2169, 1, 1, NULL, 'user_logout', 'User logged out: System Admin (Role: admin)', NULL, NULL, '2026-11-10 11:43:00', '2026-11-10 11:43:00'),
(2170, 8, 1, NULL, 'user_login', 'User logged in: Mary John (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-11-10 11:43:10', '2026-11-10 11:43:10'),
(2171, 8, 1, NULL, 'user_logout', 'User logged out: Mary John (Role: pharmacy)', NULL, NULL, '2026-11-10 11:43:59', '2026-11-10 11:43:59'),
(2172, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-11-10 11:44:14', '2026-11-10 11:44:14'),
(2173, 10, 1, NULL, 'user_logout', 'User logged out: SALOME SANGA (Role: reception)', NULL, NULL, '2026-11-10 11:45:28', '2026-11-10 11:45:28'),
(2174, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-11-10 11:50:40', '2026-11-10 11:50:40'),
(2175, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-11-10 15:32:49', '2026-11-10 15:32:49'),
(2176, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-11-10 15:32:55', '2026-11-10 15:32:55'),
(2177, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-11-10 15:39:57', '2026-11-10 15:39:57'),
(2178, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-11-10 15:40:02', '2026-11-10 15:40:02'),
(2179, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-11-10 15:43:47', '2026-11-10 15:43:47'),
(2180, 12, 1, NULL, 'user_login', 'User logged in: JUDITH SOLOMONI (Mode: general_blue, Role: reception)', NULL, NULL, '2026-11-10 15:43:51', '2026-11-10 15:43:51'),
(2181, 12, 1, NULL, 'user_logout', 'User logged out: JUDITH SOLOMONI (Role: reception)', NULL, NULL, '2026-11-10 15:44:24', '2026-11-10 15:44:24'),
(2182, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-11-10 15:44:31', '2026-11-10 15:44:31'),
(2183, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-11-10 15:46:45', '2026-11-10 15:46:45'),
(2184, 4, 1, NULL, 'user_login', 'User logged in: Dr.ERICK JOHN (Mode: general_blue, Role: doctor)', NULL, NULL, '2026-11-10 15:46:55', '2026-11-10 15:46:55'),
(2185, 4, 1, NULL, 'user_logout', 'User logged out: Dr.ERICK JOHN (Role: doctor)', NULL, NULL, '2026-11-10 15:47:43', '2026-11-10 15:47:43'),
(2186, 7, 1, NULL, 'user_login', 'User logged in: LUCY MUSSA (Mode: general_blue, Role: pharmacy)', NULL, NULL, '2026-11-10 15:47:52', '2026-11-10 15:47:52'),
(2187, 7, 1, NULL, 'user_logout', 'User logged out: LUCY MUSSA (Role: pharmacy)', NULL, NULL, '2026-11-10 15:48:13', '2026-11-10 15:48:13'),
(2188, 10, 1, NULL, 'user_login', 'User logged in: SALOME SANGA (Mode: general_blue, Role: reception)', NULL, NULL, '2026-11-10 15:48:21', '2026-11-10 15:48:21');

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
(3, NULL, 62, 4, NULL, '2026-10-30 16:00:00', '', 'follow-up', 'confirmed', ' [CONFIRMED: ]', 1, 10, '2026-10-16 11:41:52', '2026-10-16 11:42:40', '2026-10-16 11:42:40', NULL, NULL);

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
(416, 'BILL-CONS-20261021-0067-7815', 67, 248, 1, 11, 377500.00, 0.00, 0.00, 0.00, 500.00, 3000.00, 'Premium Charge', 0.00, 3000.00, 'Pharmacy Premium: 0', '', 500.00, 380000.00, 380000.00, 0.00, 'paid', 'cash', ' | CONFIRMED BY LUCY MUSSA - Premium +0 Discount 0 at 2026-10-21 22:13:17 | CONFIRMED BY Mary John - Premium +0 Discount 0 at 2026-11-10 14:43:47', '2026-10-21 19:11:59', '2026-11-10 11:44:51'),
(417, 'BILL-CONS-20261026-0048-4014', 48, 249, 1, 12, 241500.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 0.00, 0.00, 'Pharmacy Premium: 0', NULL, 0.00, 241500.00, 241500.00, 0.00, 'paid', 'cash', ' | CONFIRMED BY Mary John - Premium +0 Discount 0 at 2026-10-27 22:50:53', '2026-10-26 17:59:46', '2026-10-27 22:23:07'),
(418, 'BILL-CONS-20261027-0062-6223', 62, 250, 1, 12, 212500.00, 0.00, 500.00, 500.00, 0.00, 8000.00, 'Premium Charge', 8000.00, 0.00, 'Pharmacy Premium: 8,000', NULL, 500.00, 220000.00, 220000.00, 0.00, 'paid', 'cash', ' | CONFIRMED BY LUCY MUSSA - Premium +8,000 Discount 500 at 2026-10-28 01:07:18', '2026-10-27 22:05:00', '2026-10-27 22:14:42'),
(419, 'BILL-CONS-20261027-0057-8876', 57, 251, 1, 11, 115000.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 0.00, 0.00, 'Pharmacy Premium: 0', NULL, 0.00, 115000.00, 115000.00, 0.00, 'paid', 'cash', ' | CONFIRMED BY LUCY MUSSA - Premium +0 Discount 0 at 2026-10-28 01:26:40', '2026-10-27 22:18:47', '2026-10-27 22:27:19'),
(420, 'BILL-CONS-20261029-0062-8702', 62, 252, 1, 10, 145000.00, 0.00, 5000.00, 5000.00, 0.00, 0.00, '', 0.00, 0.00, 'Pharmacy Premium: 0', '', 5000.00, 140000.00, 35000.00, 105000.00, 'partial', 'cash', ' | CONFIRMED BY Mary John - Premium +0 Discount 5,000 at 2026-11-10 14:21:22 | CONFIRMED BY LUCY MUSSA - Premium +0 Discount 0 at 2026-11-10 18:44:49', '2026-10-29 20:01:47', '2026-11-10 15:44:49'),
(421, 'BILL-CONS-20261110-0067-3340', 67, 253, 1, 12, 55000.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 0.00, 0.00, 'Pharmacy Premium: 0', NULL, 0.00, 55000.00, 55000.00, 0.00, 'paid', 'cash', ' | CONFIRMED BY LUCY MUSSA - Premium +0 Discount 0 at 2026-11-10 18:48:09', '2026-11-10 15:44:19', '2026-11-10 15:49:27');

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
(1570, 416, 67, 1, 'consultation', NULL, 'New Patient', NULL, NULL, 1, 10000.00, 10000.00, 0.00, 0.00, 0.00, NULL, NULL, 'paid', '2026-10-21 19:11:59', '2026-11-10 11:44:51'),
(1575, 417, 48, 1, 'consultation', NULL, 'New Patient', NULL, NULL, 1, 10000.00, 10000.00, 0.00, 0.00, 0.00, NULL, NULL, 'paid', '2026-10-26 17:59:46', '2026-10-27 19:51:36'),
(1576, 417, 48, 1, 'lab_test', 377, 'KFADURO', NULL, NULL, 1, 10000.00, 10000.00, 0.00, 0.00, 0.00, 377, '', 'paid', '2026-10-27 19:41:13', '2026-10-27 19:51:36'),
(1577, 417, 48, 1, 'lab_test', 378, 'COVID-19 PCR Test', NULL, NULL, 1, 50000.00, 50000.00, 0.00, 0.00, 0.00, 378, '', 'paid', '2026-10-27 19:41:13', '2026-10-27 19:51:36'),
(1579, 417, 48, 1, 'equipment', 40, 'BANDAGE', NULL, NULL, 1, 1500.00, 1500.00, 0.00, 0.00, 0.00, 40, 'equipment', 'paid', '2026-10-27 19:49:39', '2026-10-27 19:51:36'),
(1580, 417, 48, 1, 'procedure', 18, 'Cryotherapy', NULL, NULL, 1, 20000.00, 20000.00, 0.00, 0.00, 0.00, 319, 'procedure', 'paid', '2026-10-27 19:49:49', '2026-10-27 19:51:36'),
(1581, 417, 48, 1, 'medication', NULL, 'ZENCOF ()', NULL, NULL, 7, 7500.00, 52500.00, 0.00, 0.00, 52500.00, 321, 'prescription', 'paid', '2026-10-27 19:50:53', '2026-10-27 19:51:36'),
(1582, 417, 48, 1, 'medication', NULL, 'ZENCOF ()', NULL, NULL, 13, 7500.00, 97500.00, 0.00, 0.00, 97500.00, 321, 'prescription', 'paid', '2026-10-27 19:50:53', '2026-10-27 19:51:36'),
(1583, 418, 62, 1, 'consultation', NULL, 'New Patient', NULL, NULL, 1, 10000.00, 10000.00, 0.00, 0.00, 0.00, NULL, NULL, 'paid', '2026-10-27 22:05:00', '2026-10-27 22:07:45'),
(1585, 418, 62, 1, 'medication', NULL, 'ZENCOF ()', NULL, NULL, 25, 7500.00, 187500.00, 0.00, 0.00, 187500.00, 322, 'prescription', 'paid', '2026-10-27 22:07:18', '2026-10-27 22:07:45'),
(1586, 418, 62, 1, 'medication', NULL, 'ZENCOF ()', NULL, NULL, 2, 7500.00, 15000.00, 0.00, 0.00, 15000.00, 322, 'prescription', 'paid', '2026-10-27 22:07:18', '2026-10-27 22:07:45'),
(1587, 419, 57, 1, 'consultation', NULL, 'New Patient', NULL, NULL, 1, 10000.00, 10000.00, 0.00, 0.00, 0.00, NULL, NULL, 'paid', '2026-10-27 22:18:47', '2026-10-27 22:27:13'),
(1588, 419, 57, 1, 'lab_test', 379, 'Blood Check', NULL, NULL, 1, 7000.00, 7000.00, 0.00, 0.00, 0.00, 379, '', 'paid', '2026-10-27 22:23:51', '2026-10-27 22:27:13'),
(1589, 419, 57, 1, 'lab_test', 380, 'Blood Glucose (Random)', NULL, NULL, 1, 8000.00, 8000.00, 0.00, 0.00, 0.00, 380, '', 'paid', '2026-10-27 22:23:51', '2026-10-27 22:27:13'),
(1590, 419, 57, 1, 'lab_test', 381, 'KFADURO', NULL, NULL, 1, 10000.00, 10000.00, 0.00, 0.00, 0.00, 381, '', 'paid', '2026-10-27 22:23:51', '2026-10-27 22:27:13'),
(1593, 419, 57, 1, 'procedure', 15, 'ECG - Electrocardiogram', NULL, NULL, 1, 15000.00, 15000.00, 0.00, 0.00, 0.00, 320, 'procedure', 'paid', '2026-10-27 22:25:59', '2026-10-27 22:27:13'),
(1594, 419, 57, 1, 'equipment', 40, 'BANDAGE', NULL, NULL, 10, 1500.00, 15000.00, 0.00, 0.00, 0.00, 40, 'equipment', 'paid', '2026-10-27 22:26:13', '2026-10-27 22:27:13'),
(1595, 419, 57, 1, 'equipment', 6, 'Bandage Roll', NULL, NULL, 10, 1500.00, 15000.00, 0.00, 0.00, 0.00, 6, 'equipment', 'paid', '2026-10-27 22:26:13', '2026-10-27 22:27:13'),
(1596, 419, 57, 1, 'medication', NULL, 'Amoxicillin 500mg ()', NULL, NULL, 10, 500.00, 5000.00, 0.00, 0.00, 5000.00, 323, 'prescription', 'paid', '2026-10-27 22:26:40', '2026-10-27 22:27:13'),
(1597, 419, 57, 1, 'medication', NULL, 'ALBENDAZOLE ()', NULL, NULL, 10, 3000.00, 30000.00, 0.00, 0.00, 30000.00, 324, 'prescription', 'paid', '2026-10-27 22:26:40', '2026-10-27 22:27:13'),
(1598, 420, 62, 1, 'consultation', NULL, 'New Patient', NULL, NULL, 1, 10000.00, 10000.00, 0.00, 0.00, 0.00, NULL, NULL, 'paid', '2026-10-29 20:01:47', '2026-11-10 11:22:31'),
(1604, 416, 67, 1, 'medication', NULL, 'ZENCOF () [2 batches]', NULL, NULL, 45, 7500.00, 337500.00, 0.00, 0.00, 337500.00, 320, 'prescription', 'paid', '2026-11-10 11:43:47', '2026-11-10 11:44:51'),
(1605, 416, 67, 1, 'medication', NULL, 'ALBENDAZOLE ()', NULL, NULL, 10, 3000.00, 30000.00, 0.00, 0.00, 30000.00, 327, 'prescription', 'paid', '2026-11-10 11:43:47', '2026-11-10 11:44:51'),
(1606, 421, 67, 1, 'consultation', NULL, 'New Patient', NULL, NULL, 1, 10000.00, 10000.00, 0.00, 0.00, 0.00, NULL, NULL, 'paid', '2026-11-10 15:44:19', '2026-11-10 15:49:22'),
(1607, 420, 62, 1, 'medication', NULL, 'ZENCOF () [2 batches]', NULL, NULL, 14, 7500.00, 105000.00, 0.00, 0.00, 105000.00, 325, 'prescription', 'pending', '2026-11-10 15:44:49', '2026-11-10 15:44:49'),
(1608, 420, 62, 1, 'medication', NULL, 'ALBENDAZOLE ()', NULL, NULL, 10, 3000.00, 30000.00, 0.00, 0.00, 30000.00, 326, 'prescription', 'pending', '2026-11-10 15:44:49', '2026-11-10 15:44:49'),
(1610, 421, 67, 1, 'medication', NULL, 'ZENCOF () [2 batches]', NULL, NULL, 6, 7500.00, 45000.00, 0.00, 0.00, 45000.00, 328, 'prescription', 'paid', '2026-11-10 15:48:09', '2026-11-10 15:49:22');

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
-- Table structure for table `daily_activities`
--

CREATE TABLE `daily_activities` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `activity_date` date NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT NULL,
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'completed',
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `patient_id` int(11) DEFAULT NULL,
  `visit_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `daily_activities`
--

INSERT INTO `daily_activities` (`id`, `user_id`, `branch_id`, `activity_date`, `title`, `description`, `category`, `start_time`, `end_time`, `duration_minutes`, `status`, `priority`, `patient_id`, `visit_id`, `notes`, `created_at`, `updated_at`) VALUES
(1, 7, 1, '2026-10-16', 'NI MEADDD DAWA', 'ZENCOF ADDED 75 ITEMS', 'Drug Interaction Check', '15:31:58', NULL, NULL, 'completed', 'medium', NULL, NULL, NULL, '2026-10-16 12:31:58', '2026-10-16 12:31:58');

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
(27, 'D-TYPHOI-507', 'TYPHOID', NULL, NULL, NULL, '', 1, NULL, 1, '2026-09-19 20:11:42', '2026-09-19 20:11:42'),
(28, 'D-ANEMIA-815', 'ANEMIA', NULL, NULL, NULL, '', 1, NULL, 2, '2026-09-21 23:00:48', '2026-09-21 23:00:48'),
(29, 'D-MIMBA-376', 'MIMBA', NULL, NULL, NULL, '', 1, NULL, 3, '2026-09-22 22:12:44', '2026-09-22 22:12:44'),
(30, 'D-COVID1-984', 'Covid19', NULL, NULL, NULL, '', 1, NULL, 1, '2026-10-16 12:09:46', '2026-10-16 12:09:46');

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
(26, 49, 6, 1, '2026-09-15 22:50:01'),
(27, 50, 6, 1, '2026-10-16 12:40:49');

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
(29, 49, 'audit', 1, '2026-09-15 22:50:01'),
(30, 50, 'audit', 1, '2026-10-16 12:40:49');

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
(377, 249, 48, 4, 13, NULL, 68, 'KFADURO', 10000.00, NULL, NULL, NULL, NULL, NULL, 'positive', NULL, '', '', 13, 'completed', '2026-10-27 19:41:34', 0, 1, '', '2026-10-27 19:41:13', NULL, NULL, '2026-10-27 19:41:49', NULL, NULL, '2026-10-27 19:41:49'),
(378, 249, 48, 4, 13, NULL, 11, 'COVID-19 PCR Test', 50000.00, NULL, NULL, NULL, NULL, NULL, 'Positive - SARS-CoV-2 antigen detected', NULL, '', '', 13, 'completed', '2026-10-27 19:41:34', 0, 1, '', '2026-10-27 19:41:13', NULL, NULL, '2026-10-27 19:41:56', NULL, NULL, '2026-10-27 19:41:56'),
(379, 251, 57, 4, 15, NULL, 58, 'Blood Check', 7000.00, NULL, NULL, NULL, NULL, NULL, '>126 mg/dL', NULL, '', '', 15, 'completed', '2026-10-27 22:24:09', 0, 1, '', '2026-10-27 22:23:51', NULL, NULL, '2026-10-27 22:24:21', NULL, NULL, '2026-10-27 22:24:21'),
(380, 251, 57, 4, 15, NULL, 3, 'Blood Glucose (Random)', 8000.00, NULL, NULL, NULL, NULL, NULL, '>200 mg/dL', NULL, '', '', 15, 'completed', '2026-10-27 22:24:09', 0, 1, '', '2026-10-27 22:23:51', NULL, NULL, '2026-10-27 22:24:29', NULL, NULL, '2026-10-27 22:24:29'),
(381, 251, 57, 4, 15, NULL, 68, 'KFADURO', 10000.00, NULL, NULL, NULL, NULL, NULL, 'nept45', NULL, '', '', 15, 'completed', '2026-10-27 22:24:09', 0, 1, '', '2026-10-27 22:23:51', NULL, NULL, '2026-10-27 22:24:43', NULL, NULL, '2026-10-27 22:24:43');

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
(68, 'KFADURO', NULL, 'Lab Tests', 10000.00, '', NULL, NULL, 1, 1, 1, 4, '2026-09-09 14:27:04', '2026-09-09 14:27:04'),
(69, 'Urine Test', NULL, 'Lab Tests', 25000.00, '', NULL, NULL, 1, 1, 3, 30, '2026-09-22 22:04:04', '2026-09-22 22:04:04'),
(70, 'COVID 19', NULL, 'Lab Tests', 40000.00, '', NULL, NULL, 1, 1, 3, 30, '2026-09-22 22:04:30', '2026-09-22 22:04:30');

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
(15, 68, 41, 1, '2026-09-09 14:27:04'),
(16, 69, 42, 3, '2026-09-22 22:04:04'),
(17, 70, 43, 3, '2026-09-22 22:04:30');

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
(1, 'ECG Machine (12-Lead)', 'Cardiology', 'pcs', 480, 2, 5000.00, 15000.00, 'GE Healthcare', NULL, 'EQP-20260909-ECG-001', 1, 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-29 14:37:56'),
(2, 'Ultrasound Machine', 'Radiology', 'pcs', 500, 1, 80000.00, 25000.00, 'Siemens', NULL, 'EQP-20260909-US-001', 1, 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-24 20:14:41'),
(3, 'Blood Pressure Monitor', 'Diagnostic', 'pcs', 499, 3, 10000.00, 250000.00, 'Omron', NULL, 'EQP-20260909-BP-001', 1, 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-30 10:23:18'),
(4, 'Stethoscope', 'Diagnostic', 'pcs', 500, 5, 8000.00, 3000.00, '3M Littmann', NULL, 'EQP-20260909-ST-001', 1, 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-24 20:14:41'),
(5, 'Surgical Scalpel Set', 'Surgery', 'set', 500, 3, 15000.00, 50000.00, 'Medical Supplies Co', NULL, 'EQP-20260909-SS-001', 1, 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-24 20:14:41'),
(6, 'Bandage Roll', 'Wound Care', 'roll', 460, 20, 500.00, 1500.00, 'MediCare', '2027-12-31', 'EQP-20260909-BAND-001', 1, 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-10-27 22:27:13'),
(7, 'Gauze Swabs (Sterile)', 'Wound Care', 'pack', 500, 10, 300.00, 1000.00, 'MediCare', '2027-06-30', 'EQP-20260909-GAUZE-001', 1, 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-24 20:14:41'),
(8, 'Surgical Gloves (Sterile)', 'Surgery', 'box', 500, 5, 2000.00, 5000.00, 'Ansell', '2027-09-30', 'EQP-20260909-GLOVE-001', 1, 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-24 20:14:41'),
(9, 'Suture Kit', 'Surgery', 'kit', 499, 4, 25000.00, 10000.00, 'Ethicon', NULL, 'EQP-20260909-SUT-001', 1, 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-30 10:23:18'),
(40, 'BANDAGE', 'Lab Equipment', 'box', 459, 50, 500.00, 1500.00, 'WAKATI', '0000-00-00', 'EQP-20260909-8495C2', 1, NULL, 'active', 1, 'System Admin', '2026-09-09 13:35:41', '2026-10-27 22:27:13'),
(41, 'SINDANO', 'Wound Care', 'set', 488, 50, 500.00, 1200.00, 'AMANA', '0000-00-00', 'EQP-20260909-40E8C8', 1, NULL, 'active', 7, 'LUCY MUSSA', '2026-09-09 13:36:49', '2026-10-27 22:27:13'),
(42, 'Urinalysis Strips', 'Lab Equipment', 'pcs', 500, 50, 2000.00, 4000.00, '', '0000-00-00', 'EQP-20260923-CD6284', 3, NULL, 'active', 33, 'Pharmacy Dar', '2026-09-22 22:02:08', '2026-09-24 20:14:41'),
(43, 'Sindano', 'IV Equipment', 'pcs', 500, 50, 500.00, 1500.00, 'AVANA MEDICS', '0000-00-00', 'EQP-20260923-09350C', 3, NULL, 'active', 33, 'Pharmacy Dar', '2026-09-22 22:02:52', '2026-09-24 20:14:41'),
(44, 'SINDANO', 'Wound Care', 'set', 150, 50, 500.00, 1200.00, 'AMANA', NULL, 'EQP-20261026-4C971C', 1, NULL, 'active', 8, 'Mary John', '2026-10-26 18:00:40', '2026-10-26 18:00:40');

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
(1, 'Paracetamol 500mg', 'Analgesics', 'tablets', 500, 50, 50.00, 200.00, 'Medical Supplies Ltd', '2027-12-31', 'BATCH-PCM-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-24 19:50:27'),
(2, 'Amoxicillin 500mg', 'Antibiotics', 'capsules', 460, 30, 150.00, 500.00, 'PharmaPlus Ltd', '2027-10-15', 'BATCH-AMOX-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-29 14:42:54'),
(3, 'Ciprofloxacin 500mg', 'Antibiotics', 'tablets', 460, 20, 200.00, 800.00, 'PharmaPlus Ltd', '2027-11-30', 'BATCH-CIPRO-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-29 14:42:54'),
(4, 'Metronidazole 400mg', 'Antibiotics', 'tablets', 500, 25, 100.00, 400.00, 'Medical Supplies Ltd', '2027-09-20', 'BATCH-METRO-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-24 19:50:27'),
(5, 'Omeprazole 20mg', 'Antacids', 'capsules', 500, 15, 80.00, 350.00, 'HealthCare Ltd', '2028-01-15', 'BATCH-OME-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-24 19:50:27'),
(6, 'Ibuprofen 400mg', 'Analgesics', 'tablets', 500, 20, 60.00, 300.00, 'Medical Supplies Ltd', '2027-08-30', 'BATCH-IBU-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-24 19:50:27'),
(7, 'Diclofenac 50mg', 'Analgesics', 'tablets', 420, 18, 70.00, 350.00, 'PharmaPlus Ltd', '2027-07-25', 'BATCH-DICL-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-30 10:24:58'),
(8, 'Cetirizine 10mg', 'Antihistamines', 'tablets', 420, 30, 30.00, 150.00, 'HealthCare Ltd', '2028-02-28', 'BATCH-CET-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-30 10:24:59'),
(9, 'Loratadine 10mg', 'Antihistamines', 'tablets', 500, 25, 40.00, 200.00, 'Medical Supplies Ltd', '2027-12-15', 'BATCH-LORA-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-24 19:50:27'),
(10, 'Salbutamol Inhaler', 'Respiratory', 'inhaler', 490, 10, 2500.00, 5000.00, 'PharmaPlus Ltd', '2028-03-01', 'BATCH-SALB-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-10-16 12:34:13'),
(11, 'Beclomethasone Inhaler', 'Respiratory', '__other__', 500, 10, 3000.00, 6500.00, 'HealthCare Ltd', '2028-04-15', 'BATCH-BECLO-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-29 14:38:54'),
(12, 'Amlodipine 5mg', 'Cardiovascular', 'tablets', 495, 15, 120.00, 450.00, 'Medical Supplies Ltd', '2027-11-30', 'BATCH-AML-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-10-16 12:23:27'),
(13, 'Enalapril 10mg', 'Cardiovascular', 'tablets', 420, 10, 130.00, 500.00, 'PharmaPlus Ltd', '2027-10-20', 'BATCH-ENAL-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-30 10:24:59'),
(14, 'Hydrochlorothiazide 25mg', 'Cardiovascular', 'tablets', 500, 15, 80.00, 300.00, 'HealthCare Ltd', '2027-09-10', 'BATCH-HCTZ-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-24 19:50:27'),
(15, 'Metformin 500mg', 'Antidiabetic', 'tablets', 500, 20, 100.00, 400.00, 'Medical Supplies Ltd', '2028-01-20', 'BATCH-MET-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-24 19:50:27'),
(16, 'Glibenclamide 5mg', 'Antidiabetic', 'tablets', 500, 15, 90.00, 350.00, 'PharmaPlus Ltd', '2027-12-05', 'BATCH-GLIB-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-24 13:46:28'),
(17, 'Furosemide 40mg', 'Diuretics', 'tablets', 500, 10, 70.00, 250.00, 'HealthCare Ltd', '2027-08-15', 'BATCH-FURO-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-24 13:45:59'),
(18, 'Diazepam 5mg', 'Sedatives', 'tablets', 380, 10, 150.00, 600.00, 'Medical Supplies Ltd', '2028-02-10', 'BATCH-DIAZ-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-30 10:24:59'),
(19, 'Amitriptyline 25mg', 'Antidepressants', 'tablets', 300, 10, 120.00, 450.00, 'PharmaPlus Ltd', '2027-09-25', 'BATCH-AMIT-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-10-21 14:35:30'),
(20, 'Multivitamin Tablets', 'Vitamins', 'tablets', 500, 40, 30.00, 150.00, 'HealthCare Ltd', '2028-06-30', 'BATCH-MVIT-20260909-001', 1, 'active', 1, 'System Admin', '2026-09-08 21:00:00', '2026-09-24 19:50:27'),
(25, 'ALBENDAZOLE', 'Antibiotics', 'pcs', 480, 100, 1300.00, 3000.00, 'AVANA MEDICS', '2028-09-08', 'BATCH-20260908-A196B7', 1, 'active', 8, 'Mary John', '2026-09-08 13:38:44', '2026-11-10 11:44:51'),
(26, 'AMOXILINE', 'Antacids', 'pcs', 500, 20, 1000.00, 2500.00, 'AVANA MEDICS', '2027-05-08', 'BATCH-20260908-49302D', 1, 'active', 8, 'Mary John', '2026-09-08 13:40:09', '2026-09-24 19:50:27'),
(27, 'Paracetamol 500mg', 'Analgesics', 'tablets', 500, 50, 50.00, 200.00, 'Medical Supplies Ltd', '2027-12-31', 'ARU-BATCH-PCM-20260909-0', 2, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(28, 'Amoxicillin 500mg', 'Antibiotics', 'capsules', 500, 30, 150.00, 500.00, 'PharmaPlus Ltd', '2027-10-15', 'ARU-BATCH-AMOX-20260909-', 2, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(29, 'Ciprofloxacin 500mg', 'Antibiotics', 'tablets', 500, 20, 200.00, 800.00, 'PharmaPlus Ltd', '2027-11-30', 'ARU-BATCH-CIPRO-20260909', 2, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(30, 'Metronidazole 400mg', 'Antibiotics', 'tablets', 500, 25, 100.00, 400.00, 'Medical Supplies Ltd', '2027-09-20', 'ARU-BATCH-METRO-20260909', 2, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(31, 'Omeprazole 20mg', 'Antacids', 'capsules', 500, 15, 80.00, 350.00, 'HealthCare Ltd', '2028-01-15', 'ARU-BATCH-OME-20260909-0', 2, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(32, 'Ibuprofen 400mg', 'Analgesics', 'tablets', 500, 20, 60.00, 300.00, 'Medical Supplies Ltd', '2027-08-30', 'ARU-BATCH-IBU-20260909-0', 2, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(33, 'Diclofenac 50mg', 'Analgesics', 'tablets', 500, 18, 70.00, 350.00, 'PharmaPlus Ltd', '2027-07-25', 'ARU-BATCH-DICL-20260909-', 2, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(34, 'Cetirizine 10mg', 'Antihistamines', 'tablets', 500, 30, 30.00, 150.00, 'HealthCare Ltd', '2028-02-28', 'ARU-BATCH-CET-20260909-0', 2, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(35, 'Loratadine 10mg', 'Antihistamines', 'tablets', 500, 25, 40.00, 200.00, 'Medical Supplies Ltd', '2027-12-15', 'ARU-BATCH-LORA-20260909-', 2, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(36, 'Salbutamol Inhaler', 'Respiratory', 'inhaler', 500, 10, 2500.00, 5000.00, 'PharmaPlus Ltd', '2028-03-01', 'ARU-BATCH-SALB-20260909-', 2, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(37, 'Beclomethasone Inhaler', 'Respiratory', 'inhaler', 500, 10, 3000.00, 6500.00, 'HealthCare Ltd', '2028-04-15', 'ARU-BATCH-BECLO-20260909', 2, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(38, 'Amlodipine 5mg', 'Cardiovascular', 'tablets', 500, 15, 120.00, 450.00, 'Medical Supplies Ltd', '2027-11-30', 'ARU-BATCH-AML-20260909-0', 2, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(39, 'Enalapril 10mg', 'Cardiovascular', 'tablets', 500, 10, 130.00, 500.00, 'PharmaPlus Ltd', '2027-10-20', 'ARU-BATCH-ENAL-20260909-', 2, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(40, 'Hydrochlorothiazide 25mg', 'Cardiovascular', 'tablets', 500, 15, 80.00, 300.00, 'HealthCare Ltd', '2027-09-10', 'ARU-BATCH-HCTZ-20260909-', 2, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(41, 'Metformin 500mg', 'Antidiabetic', 'tablets', 500, 20, 100.00, 400.00, 'Medical Supplies Ltd', '2028-01-20', 'ARU-BATCH-MET-20260909-0', 2, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(42, 'Glibenclamide 5mg', 'Antidiabetic', 'tablets', 500, 15, 90.00, 350.00, 'PharmaPlus Ltd', '2027-12-05', 'ARU-BATCH-GLIB-20260909-', 2, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(43, 'Furosemide 40mg', 'Diuretics', 'tablets', 500, 10, 70.00, 250.00, 'HealthCare Ltd', '2027-08-15', 'ARU-BATCH-FURO-20260909-', 2, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(44, 'Diazepam 5mg', 'Sedatives', 'tablets', 500, 10, 150.00, 600.00, 'Medical Supplies Ltd', '2028-02-10', 'ARU-BATCH-DIAZ-20260909-', 2, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(45, 'Amitriptyline 25mg', 'Antidepressants', 'tablets', 500, 10, 120.00, 450.00, 'PharmaPlus Ltd', '2027-09-25', 'ARU-BATCH-AMIT-20260909-', 2, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(46, 'Multivitamin Tablets', 'Vitamins', 'tablets', 500, 40, 30.00, 150.00, 'HealthCare Ltd', '2028-06-30', 'ARU-BATCH-MVIT-20260909-', 2, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(47, 'ALBENDAZOLE', 'Antibiotics', 'pcs', 500, 100, 1300.00, 3000.00, 'AVANA MEDICS', '2028-09-08', 'ARU-BATCH-20260908-A196B', 2, 'active', 8, 'Mary John', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(48, 'AMOXILINE', 'Antacids', 'pcs', 500, 20, 1000.00, 2500.00, 'AVANA MEDICS', '2027-05-08', 'ARU-BATCH-20260908-49302', 2, 'active', 8, 'Mary John', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(58, 'Paracetamol 500mg', 'Analgesics', 'tablets', 500, 50, 50.00, 200.00, 'Medical Supplies Ltd', '2027-12-31', 'DAR-BATCH-PCM-20260909-0', 3, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(59, 'Amoxicillin 500mg', 'Antibiotics', 'capsules', 500, 30, 150.00, 500.00, 'PharmaPlus Ltd', '2027-10-15', 'DAR-BATCH-AMOX-20260909-', 3, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(60, 'Ciprofloxacin 500mg', 'Antibiotics', 'tablets', 500, 20, 200.00, 800.00, 'PharmaPlus Ltd', '2027-11-30', 'DAR-BATCH-CIPRO-20260909', 3, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(61, 'Metronidazole 400mg', 'Antibiotics', 'tablets', 500, 25, 100.00, 400.00, 'Medical Supplies Ltd', '2027-09-20', 'DAR-BATCH-METRO-20260909', 3, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(62, 'Omeprazole 20mg', 'Antacids', 'capsules', 500, 15, 80.00, 350.00, 'HealthCare Ltd', '2028-01-15', 'DAR-BATCH-OME-20260909-0', 3, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(63, 'Ibuprofen 400mg', 'Analgesics', 'tablets', 500, 20, 60.00, 300.00, 'Medical Supplies Ltd', '2027-08-30', 'DAR-BATCH-IBU-20260909-0', 3, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(64, 'Diclofenac 50mg', 'Analgesics', 'tablets', 500, 18, 70.00, 350.00, 'PharmaPlus Ltd', '2027-07-25', 'DAR-BATCH-DICL-20260909-', 3, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(65, 'Cetirizine 10mg', 'Antihistamines', 'tablets', 500, 30, 30.00, 150.00, 'HealthCare Ltd', '2028-02-28', 'DAR-BATCH-CET-20260909-0', 3, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(66, 'Loratadine 10mg', 'Antihistamines', 'tablets', 500, 25, 40.00, 200.00, 'Medical Supplies Ltd', '2027-12-15', 'DAR-BATCH-LORA-20260909-', 3, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(67, 'Salbutamol Inhaler', 'Respiratory', 'inhaler', 500, 10, 2500.00, 5000.00, 'PharmaPlus Ltd', '2028-03-01', 'DAR-BATCH-SALB-20260909-', 3, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(68, 'Beclomethasone Inhaler', 'Respiratory', 'inhaler', 500, 10, 3000.00, 6500.00, 'HealthCare Ltd', '2028-04-15', 'DAR-BATCH-BECLO-20260909', 3, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(69, 'Amlodipine 5mg', 'Cardiovascular', 'tablets', 500, 15, 120.00, 450.00, 'Medical Supplies Ltd', '2027-11-30', 'DAR-BATCH-AML-20260909-0', 3, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(70, 'Enalapril 10mg', 'Cardiovascular', 'tablets', 500, 10, 130.00, 500.00, 'PharmaPlus Ltd', '2027-10-20', 'DAR-BATCH-ENAL-20260909-', 3, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(71, 'Hydrochlorothiazide 25mg', 'Cardiovascular', 'tablets', 500, 15, 80.00, 300.00, 'HealthCare Ltd', '2027-09-10', 'DAR-BATCH-HCTZ-20260909-', 3, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(72, 'Metformin 500mg', 'Antidiabetic', 'tablets', 500, 20, 100.00, 400.00, 'Medical Supplies Ltd', '2028-01-20', 'DAR-BATCH-MET-20260909-0', 3, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(73, 'Glibenclamide 5mg', 'Antidiabetic', 'tablets', 500, 15, 90.00, 350.00, 'PharmaPlus Ltd', '2027-12-05', 'DAR-BATCH-GLIB-20260909-', 3, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(74, 'Furosemide 40mg', 'Diuretics', 'tablets', 500, 10, 70.00, 250.00, 'HealthCare Ltd', '2027-08-15', 'DAR-BATCH-FURO-20260909-', 3, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(75, 'Diazepam 5mg', 'Sedatives', 'tablets', 500, 10, 150.00, 600.00, 'Medical Supplies Ltd', '2028-02-10', 'DAR-BATCH-DIAZ-20260909-', 3, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(76, 'Amitriptyline 25mg', 'Antidepressants', 'tablets', 500, 10, 120.00, 450.00, 'PharmaPlus Ltd', '2027-09-25', 'DAR-BATCH-AMIT-20260909-', 3, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(77, 'Multivitamin Tablets', 'Vitamins', 'tablets', 500, 40, 30.00, 150.00, 'HealthCare Ltd', '2028-06-30', 'DAR-BATCH-MVIT-20260909-', 3, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(78, 'ALBENDAZOLE', 'Antibiotics', 'pcs', 500, 100, 1300.00, 3000.00, 'AVANA MEDICS', '2028-09-08', 'DAR-BATCH-20260908-A196B', 3, 'active', 8, 'Mary John', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(79, 'AMOXILINE', 'Antacids', 'pcs', 500, 20, 1000.00, 2500.00, 'AVANA MEDICS', '2027-05-08', 'DAR-BATCH-20260908-49302', 3, 'active', 8, 'Mary John', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(89, 'Paracetamol 500mg', 'Analgesics', 'tablets', 500, 50, 50.00, 200.00, 'Medical Supplies Ltd', '2027-12-31', 'KAH-BATCH-PCM-20260909-0', 4, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(90, 'Amoxicillin 500mg', 'Antibiotics', 'capsules', 500, 30, 150.00, 500.00, 'PharmaPlus Ltd', '2027-10-15', 'KAH-BATCH-AMOX-20260909-', 4, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(91, 'Ciprofloxacin 500mg', 'Antibiotics', 'tablets', 500, 20, 200.00, 800.00, 'PharmaPlus Ltd', '2027-11-30', 'KAH-BATCH-CIPRO-20260909', 4, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(92, 'Metronidazole 400mg', 'Antibiotics', 'tablets', 500, 25, 100.00, 400.00, 'Medical Supplies Ltd', '2027-09-20', 'KAH-BATCH-METRO-20260909', 4, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(93, 'Omeprazole 20mg', 'Antacids', 'capsules', 500, 15, 80.00, 350.00, 'HealthCare Ltd', '2028-01-15', 'KAH-BATCH-OME-20260909-0', 4, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(94, 'Ibuprofen 400mg', 'Analgesics', 'tablets', 500, 20, 60.00, 300.00, 'Medical Supplies Ltd', '2027-08-30', 'KAH-BATCH-IBU-20260909-0', 4, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(95, 'Diclofenac 50mg', 'Analgesics', 'tablets', 500, 18, 70.00, 350.00, 'PharmaPlus Ltd', '2027-07-25', 'KAH-BATCH-DICL-20260909-', 4, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(96, 'Cetirizine 10mg', 'Antihistamines', 'tablets', 500, 30, 30.00, 150.00, 'HealthCare Ltd', '2028-02-28', 'KAH-BATCH-CET-20260909-0', 4, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(97, 'Loratadine 10mg', 'Antihistamines', 'tablets', 500, 25, 40.00, 200.00, 'Medical Supplies Ltd', '2027-12-15', 'KAH-BATCH-LORA-20260909-', 4, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(98, 'Salbutamol Inhaler', 'Respiratory', 'inhaler', 500, 10, 2500.00, 5000.00, 'PharmaPlus Ltd', '2028-03-01', 'KAH-BATCH-SALB-20260909-', 4, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(99, 'Beclomethasone Inhaler', 'Respiratory', 'inhaler', 500, 10, 3000.00, 6500.00, 'HealthCare Ltd', '2028-04-15', 'KAH-BATCH-BECLO-20260909', 4, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(100, 'Amlodipine 5mg', 'Cardiovascular', 'tablets', 500, 15, 120.00, 450.00, 'Medical Supplies Ltd', '2027-11-30', 'KAH-BATCH-AML-20260909-0', 4, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(101, 'Enalapril 10mg', 'Cardiovascular', 'tablets', 500, 10, 130.00, 500.00, 'PharmaPlus Ltd', '2027-10-20', 'KAH-BATCH-ENAL-20260909-', 4, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(102, 'Hydrochlorothiazide 25mg', 'Cardiovascular', 'tablets', 500, 15, 80.00, 300.00, 'HealthCare Ltd', '2027-09-10', 'KAH-BATCH-HCTZ-20260909-', 4, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(103, 'Metformin 500mg', 'Antidiabetic', 'tablets', 500, 20, 100.00, 400.00, 'Medical Supplies Ltd', '2028-01-20', 'KAH-BATCH-MET-20260909-0', 4, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(104, 'Glibenclamide 5mg', 'Antidiabetic', 'tablets', 500, 15, 90.00, 350.00, 'PharmaPlus Ltd', '2027-12-05', 'KAH-BATCH-GLIB-20260909-', 4, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(105, 'Furosemide 40mg', 'Diuretics', 'tablets', 500, 10, 70.00, 250.00, 'HealthCare Ltd', '2027-08-15', 'KAH-BATCH-FURO-20260909-', 4, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(106, 'Diazepam 5mg', 'Sedatives', 'tablets', 500, 10, 150.00, 600.00, 'Medical Supplies Ltd', '2028-02-10', 'KAH-BATCH-DIAZ-20260909-', 4, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(107, 'Amitriptyline 25mg', 'Antidepressants', 'tablets', 500, 10, 120.00, 450.00, 'PharmaPlus Ltd', '2027-09-25', 'KAH-BATCH-AMIT-20260909-', 4, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(108, 'Multivitamin Tablets', 'Vitamins', 'tablets', 500, 40, 30.00, 150.00, 'HealthCare Ltd', '2028-06-30', 'KAH-BATCH-MVIT-20260909-', 4, 'active', 1, 'System Admin', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(109, 'ALBENDAZOLE', 'Antibiotics', 'pcs', 500, 100, 1300.00, 3000.00, 'AVANA MEDICS', '2028-09-08', 'KAH-BATCH-20260908-A196B', 4, 'active', 8, 'Mary John', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(110, 'AMOXILINE', 'Antacids', 'pcs', 500, 20, 1000.00, 2500.00, 'AVANA MEDICS', '2027-05-08', 'KAH-BATCH-20260908-49302', 4, 'active', 8, 'Mary John', '2026-09-18 20:23:28', '2026-09-24 19:50:27'),
(111, 'AMOXILINE', 'Antacids', 'pcs', 60, 20, 1000.00, 2500.00, 'AVANA MEDICS', '2028-09-13', 'BATCH-20261013-C9F15C', 1, 'active', 1, 'System Admin', '2026-10-12 22:12:16', '2026-10-12 22:12:16'),
(112, 'ALBENDAZOLE', 'Antibiotics', 'pcs', 0, 100, 1300.00, 3000.00, 'AVANA MEDICS', '2028-05-13', 'BATCH-20261013-3A6820', 1, 'active', 1, 'System Admin', '2026-10-12 22:20:49', '2026-10-27 22:27:13'),
(113, 'Metronidazole 400mg', 'Antibiotics', 'tablets', 70, 25, 100.00, 400.00, 'Medical Supplies Ltd', '2028-05-13', 'BATCH-20261013-875AE0', 1, 'active', 1, 'System Admin', '2026-10-12 22:20:49', '2026-10-12 22:20:49'),
(114, 'Amoxicillin 500mg', 'Antibiotics', 'capsules', 35, 30, 150.00, 500.00, 'PharmaPlus Ltd', '2027-05-13', 'BATCH-20261013-7A18B3', 1, 'active', 1, 'System Admin', '2026-10-12 23:12:00', '2026-10-27 22:27:13'),
(115, 'Amoxicillin 500mg', 'Antibiotics', 'capsules', 70, 30, 150.00, 500.00, 'PharmaPlus Ltd', '2028-05-13', 'BATCH-20261013-ABD836', 1, 'active', 8, 'Mary John', '2026-10-12 23:12:00', '2026-10-12 23:12:00'),
(116, 'ZENCOF', 'Antacids', 'bottle', 0, 20, 2000.00, 7500.00, 'AVANA MEDICS', '2028-11-16', 'BATCH-20261016-B27B37', 1, 'active', 7, 'LUCY MUSSA', '2026-10-16 12:29:24', '2026-10-27 22:07:45'),
(117, 'ZENCOF', 'Antacids', 'bottle', 0, 20, 2000.00, 7500.00, 'AVANA MEDICS', '2027-09-21', 'BATCH-20261021-6E0A9E', 1, 'active', 1, 'System Admin', '2026-10-21 14:09:03', '2026-10-21 19:14:16'),
(118, 'ZENCOF', 'Antacids', 'bottle', 0, 2, 2000.00, 7500.00, 'AVANA MEDICS', '2028-06-19', 'BATCH-20261027-C3EEFC', 1, 'active', 8, 'Mary John', '2026-10-27 19:47:26', '2026-10-27 19:51:36'),
(119, 'ZENCOF', 'Antacids', 'bottle', 0, 2, 2000.00, 7500.00, 'AVANA MEDICS', '2028-10-27', 'BATCH-20261027-4462D2', 1, 'active', 8, 'Mary John', '2026-10-27 20:21:52', '2026-10-27 22:07:45'),
(120, 'Panadol', 'Painkillers', 'tablets', 400, 50, 412.50, 1200.00, 'PharmaPlus Ltd', '2029-09-28', 'BATCH-20261027-3768D5', 1, 'active', 7, 'LUCY MUSSA', '2026-10-27 21:35:23', '2026-10-27 21:35:23'),
(121, 'MAGNEZIUM', 'Painkillers', 'tablets', 50, 10, 1000.00, 3000.00, 'AVANA MEDICS', '2027-10-22', 'BATCH-20261028-1A6789', 1, 'active', 8, 'Mary John', '2026-10-28 12:55:12', '2026-10-28 12:55:12'),
(122, 'ZENCOF', 'Antacids', 'bottle', 0, 2, 2000.00, 7500.00, 'AVANA MEDICS', '2028-05-28', 'BATCH-20261028-20D2A6', 1, 'active', 8, 'Mary John', '2026-10-28 12:58:24', '2026-11-10 11:22:31'),
(123, 'ZENCOF', 'Antacids', 'bottle', 0, 2, 2000.00, 7500.00, 'AVANA MEDICS', '2028-10-10', 'BATCH-20261110-6CB883', 1, 'active', 1, 'System Admin', '2026-11-10 11:11:24', '2026-11-10 11:22:31'),
(124, 'ZENCOF', 'Antacids', 'bottle', 0, 2, 2000.00, 7500.00, 'AVANA MEDICS', '2028-05-10', 'BATCH-20261110-312002', 1, 'active', 7, 'LUCY MUSSA', '2026-11-10 15:45:40', '2026-11-10 15:49:22'),
(125, 'ZENCOF', 'Antacids', 'bottle', 2, 2, 2000.00, 7500.00, 'AVANA MEDICS', '2028-05-10', 'BATCH-20261110-534F07', 1, 'active', 7, 'LUCY MUSSA', '2026-11-10 15:46:22', '2026-11-10 15:49:22');

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
(283, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260921-0059-3209 (TSh 10,000) for patient ID #59', '', 'cashier_dashboard.php', 0, '2026-09-21 19:39:49', '2026-09-21 19:39:49'),
(284, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260921-0063-8286 (TSh 10,000) for patient ID #63', '', 'cashier_dashboard.php', 0, '2026-09-21 21:16:50', '2026-09-21 21:16:50'),
(285, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260921-0062-3700 (TSh 10,000) for patient ID #62', '', 'cashier_dashboard.php', 0, '2026-09-21 21:17:07', '2026-09-21 21:17:07'),
(286, 29, 2, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260922-0064-6488 (TSh 10,000) for patient ODAMA NGWALE - New Patient', '', 'cashier_dashboard.php', 0, '2026-09-21 22:58:19', '2026-09-21 22:58:19'),
(287, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260922-0063-9522 (TSh 10,000) for patient ID #63', '', 'cashier_dashboard.php', 0, '2026-09-22 11:56:42', '2026-09-22 11:56:42'),
(288, 29, 2, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260922-0064-1872 (TSh 10,000) for patient ID #64', '', 'cashier_dashboard.php', 0, '2026-09-22 12:09:11', '2026-09-22 12:09:11'),
(289, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260922-0062-1950 (TSh 10,000) for patient ID #62', '', 'cashier_dashboard.php', 0, '2026-09-22 12:19:46', '2026-09-22 12:19:46'),
(290, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260922-0059-7369 (TSh 100,000) for patient ID #59', '', 'cashier_dashboard.php', 0, '2026-09-22 19:09:31', '2026-09-22 19:09:31'),
(291, 42, 3, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260923-0065-8377 (TSh 10,000) for patient DIANA MSAFIRI - Registration Fee', '', 'cashier_dashboard.php', 0, '2026-09-22 22:07:53', '2026-09-22 22:07:53'),
(292, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260924-0058-5113 (TSh 10,000) for patient ID #58', '', 'cashier_dashboard.php', 0, '2026-09-24 12:49:54', '2026-09-24 12:49:54'),
(293, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260924-0063-4262 (TSh 10,000) for patient ID #63', '', 'cashier_dashboard.php', 0, '2026-09-24 13:36:09', '2026-09-24 13:36:09'),
(294, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260924-0062-3933 (TSh 10,000) for patient ID #62', '', 'cashier_dashboard.php', 0, '2026-09-24 13:36:27', '2026-09-24 13:36:27'),
(295, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260924-0061-9248 (TSh 30,000) for patient ID #61', '', 'cashier_dashboard.php', 0, '2026-09-24 13:36:49', '2026-09-24 13:36:49'),
(296, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260924-0063-9654 (TSh 10,000) for patient ID #63', '', 'cashier_dashboard.php', 0, '2026-09-24 14:38:56', '2026-09-24 14:38:56'),
(297, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260924-0062-7334 (TSh 10,000) for patient ID #62', '', 'cashier_dashboard.php', 0, '2026-09-24 14:39:17', '2026-09-24 14:39:17'),
(298, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260924-0060-4929 (TSh 30,000) for patient ID #60', '', 'cashier_dashboard.php', 0, '2026-09-24 15:03:10', '2026-09-24 15:03:10'),
(299, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260924-0063-2892 (TSh 10,000) for patient ID #63', '', 'cashier_dashboard.php', 0, '2026-09-24 15:36:49', '2026-09-24 15:36:49'),
(300, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260924-0062-3128 (TSh 10,000) for patient ID #62', '', 'cashier_dashboard.php', 0, '2026-09-24 16:34:01', '2026-09-24 16:34:01'),
(301, 16, 1, NULL, '🧪 Lab Test Bill Created', 'Lab Test bill #BILL-LAB-20260924-0061-4625 (TSh 20,000) for patient ID #61 - Renal Function Test (RFT)', '', 'cashier_dashboard.php', 0, '2026-09-24 18:10:44', '2026-09-24 18:10:44'),
(302, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260929-0063-7828 (TSh 10,000) for patient ID #63', '', 'cashier_dashboard.php', 0, '2026-09-29 11:49:00', '2026-09-29 11:49:00'),
(303, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260929-0063-2395 (TSh 10,000) for patient ID #63', '', 'cashier_dashboard.php', 0, '2026-09-29 11:49:33', '2026-09-29 11:49:33'),
(304, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260929-0062-1229 (TSh 10,000) for patient ID #62', '', 'cashier_dashboard.php', 0, '2026-09-29 11:49:59', '2026-09-29 11:49:59'),
(305, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260929-0047-3983 (TSh 10,000) for patient ID #47', '', 'cashier_dashboard.php', 0, '2026-09-29 12:29:51', '2026-09-29 12:29:51'),
(306, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260929-0047-7000 (TSh 10,000) for patient ID #47', '', 'cashier_dashboard.php', 0, '2026-09-29 13:56:21', '2026-09-29 13:56:21'),
(307, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260929-0061-3212 (TSh 10,000) for patient ID #61', '', 'cashier_dashboard.php', 0, '2026-09-29 13:56:46', '2026-09-29 13:56:46'),
(308, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260929-0061-3425 (TSh 10,000) for patient ID #61', '', 'cashier_dashboard.php', 0, '2026-09-29 15:09:24', '2026-09-29 15:09:24'),
(309, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260929-0061-6761 (TSh 10,000) for patient ID #61', '', 'cashier_dashboard.php', 0, '2026-09-29 15:11:24', '2026-09-29 15:11:24'),
(310, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260929-0061-4264 (TSh 10,000) for patient ID #61', '', 'cashier_dashboard.php', 0, '2026-09-29 15:11:44', '2026-09-29 15:11:44'),
(311, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20260929-0063-5894 (TSh 10,000) for patient ID #63', '', 'cashier_dashboard.php', 0, '2026-09-29 15:12:11', '2026-09-29 15:12:11'),
(312, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20261001-0063-2324 (TSh 10,000) for patient ID #63', '', 'cashier_dashboard.php', 0, '2026-10-01 11:55:37', '2026-10-01 11:55:37'),
(313, 16, 1, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20261004-0066-9143 (TSh 10,000) for patient MUSSA SAMSON - New Patient', '', 'cashier_dashboard.php', 0, '2026-10-04 16:32:05', '2026-10-04 16:32:05'),
(314, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20261016-0067-8599 (TSh 10,000) for patient ID #67', '', 'cashier_dashboard.php', 0, '2026-10-16 11:36:48', '2026-10-16 11:36:48'),
(315, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20261017-0067-6619 (TSh 10,000) for patient ID #67', '', 'cashier_dashboard.php', 0, '2026-10-17 16:59:10', '2026-10-17 16:59:10'),
(316, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20261017-0061-6961 (TSh 30,000) for patient ID #61', '', 'cashier_dashboard.php', 0, '2026-10-17 19:10:08', '2026-10-17 19:10:08'),
(317, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20261021-0057-4573 (TSh 10,000) for patient ID #57', '', 'cashier_dashboard.php', 0, '2026-10-21 14:31:09', '2026-10-21 14:31:09'),
(318, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20261021-0067-7815 (TSh 10,000) for patient ID #67', '', 'cashier_dashboard.php', 0, '2026-10-21 19:11:59', '2026-10-21 19:11:59'),
(319, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20261026-0048-4014 (TSh 10,000) for patient ID #48', '', 'cashier_dashboard.php', 0, '2026-10-26 17:59:46', '2026-10-26 17:59:46'),
(320, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20261027-0062-6223 (TSh 10,000) for patient ID #62', '', 'cashier_dashboard.php', 0, '2026-10-27 22:05:00', '2026-10-27 22:05:00'),
(321, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20261027-0057-8876 (TSh 10,000) for patient ID #57', '', 'cashier_dashboard.php', 0, '2026-10-27 22:18:47', '2026-10-27 22:18:47'),
(322, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20261029-0062-8702 (TSh 10,000) for patient ID #62', '', 'cashier_dashboard.php', 0, '2026-10-29 20:01:47', '2026-10-29 20:01:47'),
(323, 16, 1, NULL, '💰 Consultation Bill Created', 'Consultation bill #BILL-CONS-20261110-0067-3340 (TSh 10,000) for patient ID #67', '', 'cashier_dashboard.php', 0, '2026-11-10 15:44:19', '2026-11-10 15:44:19');

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
(36, 'OTC-20261016-8487', 'Fatmata', '0746526243', NULL, 4750.00, 50.00, 300.00, 'Premium added', 5000.00, NULL, 'cash', 'paid', 7, 1, 'Paid by Pharmacy (Self) - Customer: Fatmata | Premium: TSh 300 - Premium added', '2026-10-16 12:23:27', '2026-10-16 12:23:27');

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
(69, 36, NULL, NULL, 'Amlodipine 5mg', 'Amlodipine 5mg', 5, 450.00, 2250.00, '2 tablets', 'Evening only', 'Otic (Ear)', '4x daily', 1, '2026-10-16 12:23:27'),
(70, 36, NULL, NULL, 'Amoxicillin 500mg', 'Amoxicillin 500mg', 5, 500.00, 2500.00, '1 tablet', 'Night only', 'Ophthalmic (Eye)', 'Morning dose, Evening dose, Before meals', 1, '2026-10-16 12:23:27');

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
(47, 'P-2026-01-0001', 'MARTHA KIMAMALA', '2003-09-23', 'Female', 'Single', '0616171819', 'marthakimamala@gmail.com', 'DODOMA - KISASA SHELI', '0623693303', 'AB+', 'Penicillin, Sulfa Drugs', 1, NULL, NULL, 10, NULL, '2026-08-25 21:18:56', '2026-09-29 13:56:31'),
(48, 'P-2026-01-0002', 'IBRAHIM DOUMBIA', '2003-09-10', 'Male', 'Single', '0746512183', 'doumbia@gmail.com', 'KISASA SHELI', '0622682202', 'O+', 'Sulfa Drugs, Aspirin', 1, NULL, NULL, 10, NULL, '2026-08-25 22:00:07', '2026-10-27 22:18:33'),
(49, 'P-2026-01-0003', 'AGUSTINO VALENTINE', '2003-02-15', 'Male', 'Single', '0678552288', 'augustino@gmail.com', 'kiasa', '0678723', 'AB-', 'Sulfa Drugs, Soy', 1, NULL, NULL, 10, NULL, '2026-08-26 12:29:24', '2026-09-19 20:00:23'),
(50, 'P-2026-01-0004', 'KELVIN MSAFIRI', '2001-09-12', 'Male', '', '09876525', 'kelvin@gmail.com', 'kisasa', '0678723123', 'AB-', 'Penicillin, Sulfa Drugs', 1, NULL, NULL, 10, NULL, '2026-08-26 13:03:37', '2026-09-04 19:18:32'),
(51, 'P-2026-01-0005', 'CLEOFAS WILLIUM', '2001-07-18', 'Male', 'Single', '0746526253', 'jacksonmyula3@gmail.com', 'mtakumbuka', '067872311', 'AB-', 'Penicillin, Milk', 1, NULL, NULL, 11, NULL, '2026-08-26 18:36:46', '2026-09-19 20:00:23'),
(52, 'P-2026-01-0006', 'JUDITH SOLOMONI', '2002-04-09', 'Female', 'Single', '0678176542', 'judithsolomoni@gmail.com', '', '', 'O+', 'Penicillin, Milk', 1, NULL, NULL, 11, NULL, '2026-08-26 19:23:28', '2026-09-21 18:09:40'),
(53, 'P-2026-01-0007', 'MAGRETH CHAKUPEWA', '2002-05-19', 'Female', 'Married', '0987536818', 'magreth@gmail.com', '', '', 'B-', 'Penicillin, Milk', 1, NULL, NULL, 11, NULL, '2026-08-26 19:24:36', '2026-09-19 20:00:23'),
(54, 'P-2026-01-0008', 'CLEMENCY MTUKA', '2001-10-10', 'Male', 'Single', '0746526111', 'clemecy@gmail.com', 'mtakumbuka', '', 'B-', 'Ibuprofen', 1, NULL, NULL, 11, NULL, '2026-08-26 19:55:59', '2026-09-19 20:00:23'),
(55, 'P-2026-01-0009', 'ALPHONSE MABULA', '1998-02-12', 'Male', '', '0787615242', 'alphonce@gmail.com', '', '0678723133', 'AB-', 'Sulfa Drugs', 1, NULL, NULL, 11, NULL, '2026-08-26 19:59:21', '2026-09-19 20:00:23'),
(56, 'P-2026-01-0010', 'julieth kalinde', '2001-09-13', 'Male', '', '0789189123', 'juliath@gmail.com', '', '', 'AB+', 'Penicillin', 1, NULL, NULL, 11, NULL, '2026-08-26 20:07:21', '2026-09-19 20:00:23'),
(57, 'P-2026-01-0011', 'VICTORIA SALINGO', '2008-03-12', 'Male', 'Single', '074671827361', 'victoria@gmail.com', '', '', '', '', 1, NULL, NULL, 11, NULL, '2026-08-26 20:22:10', '2026-10-29 20:01:16'),
(58, 'P-2026-01-0012', 'AYUBU NZAL', '1992-08-12', 'Male', 'Married', '0765457899', 'ayubunzali@gmail.com', '', '', 'A+', '', 1, NULL, NULL, 11, NULL, '2026-08-26 20:35:50', '2026-09-24 13:35:55'),
(59, 'P-2026-01-0013', 'AMOSI NGOMENI', '2000-12-12', 'Male', 'Single', '0756176210', 'amosi@gmail.com', '', '', 'A+', '', 1, NULL, NULL, 11, NULL, '2026-08-26 20:52:13', '2026-09-24 13:35:55'),
(60, 'P-2026-01-0014', 'ANDREW VICENT CHIKUPE', '1993-07-10', 'Male', '', '0746826243', 'endrew@gmail.com', 'mtakumbuka', '0678723129', 'B-', 'Aspirin', 1, NULL, NULL, 11, NULL, '2026-08-26 21:00:03', '2026-09-24 15:36:36'),
(61, 'P-2026-01-0015', 'MUSSA MONGI MASNGI', '2003-08-01', 'Male', 'Single', '0789878980', 'musa@gmail.com', '', '', '', 'Sulfa Drugs', 1, NULL, NULL, 11, NULL, '2026-08-26 21:11:23', '2026-10-27 22:18:33'),
(62, 'P-2026-01-0016', 'JACKSON PIUS MYULA', '1999-06-09', 'Male', 'Single', '0678176542', 'jacksonmyula3@gmail.com', 'mtakumbuka', '', 'O+', '', 1, NULL, NULL, 10, NULL, '2026-09-10 10:30:45', '2026-11-10 15:43:56'),
(63, 'P-2026-01-0017', 'SAMSON  PIUS MYULA', '2014-05-18', 'Male', 'Single', '0623693303', 'jacksonmyula773@gmail.com', 'Rukwa\r\nMtakumbuka', '', 'AB+', 'Sulfa Drugs, Soy', 1, 12, 'JUDITH SOLOMONI', 12, NULL, '2026-09-18 18:18:26', '2026-10-27 22:18:33'),
(64, 'P-2026-02-0001', 'ODAMA NGWALE', '2006-09-22', 'Female', 'Single', '0746526243', 'jacksonmyula773@gmail.com', 'TANZANIA', '0765362782', 'O+', 'Penicillin, Sulfa Drugs', 2, 23, 'NYANSAEL NZILU', 23, NULL, '2026-09-21 22:58:19', '2026-09-24 13:35:55'),
(65, 'P-2026-03-0001', 'DIANA MSAFIRI', '2009-02-23', 'Female', 'Single', '0746526243', 'jacksonmyula773@gmail.com', 'TANZANIA', '', 'AB+', 'Codeine', 3, 36, 'Reception Dar', 36, NULL, '2026-09-22 22:07:53', '2026-09-24 13:35:55'),
(66, 'P-2026-01-0018', 'MUSSA SAMSON', '2024-01-04', 'Male', '', '0746526243', 'jacksonmyula773@gmail.com', 'TANZANIA', '', '', '', 1, 10, 'SALOME SANGA', 10, NULL, '2026-10-04 16:32:05', '2026-10-16 11:38:16'),
(67, 'P-2026-01-0019', 'DANIEL MUSSA JOHORA', '2005-01-16', 'Male', 'Single', '', '', 'kisasa', '', 'O+', 'hatakiwi kutumia kilevi cha aina yoyote', 1, 10, 'SALOME SANGA', 10, 4, '2026-10-16 11:32:16', '2026-11-10 15:44:19');

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
(185, 'RCP-20261021-6261', 416, 67, 350000.00, 'cash', NULL, 'V30 Payment', 10, 1, '2026-10-21 19:14:16', '2026-10-21 19:14:16'),
(186, 'RCP-20261027-9644', 417, 48, 70000.00, 'cash', NULL, 'V30 Payment', 11, 1, '2026-10-27 19:45:10', '2026-10-27 19:45:10'),
(187, 'RCP-20261027-7449', 417, 48, 171500.00, 'cash', NULL, 'V21 Payment | Pharm Disc: TSh 0 | Cashier Disc: TSh 0 | FULLY PAID', 12, 1, '2026-10-27 19:51:36', '2026-10-27 19:51:36'),
(188, 'RCP-20261027-5958', 418, 62, 220000.00, 'cash', NULL, 'V30 Payment', 12, 1, '2026-10-27 22:07:45', '2026-10-27 22:07:45'),
(189, 'RCP-20261027-3934', 419, 57, 115000.00, 'cash', NULL, 'V30 Payment', 10, 1, '2026-10-27 22:27:13', '2026-10-27 22:27:13'),
(190, 'RCP-20261110-1485', 420, 62, 110000.00, 'cash', NULL, 'V31 Payment', 12, 1, '2026-11-10 11:22:31', '2026-11-10 11:22:31'),
(191, 'RCP-20261110-5766', 416, 67, 30000.00, 'cash', NULL, 'V22 Payment | Pharm Disc: TSh 0 | Cashier Disc: TSh 500 | Pharm Prem: TSh 0 | Cashier Prem: TSh 3,000 | FULLY PAID', 10, 1, '2026-11-10 11:44:51', '2026-11-10 11:44:51'),
(192, 'RCP-20261110-6206', 421, 67, 55000.00, 'cash', NULL, 'V31 Payment', 10, 1, '2026-11-10 15:49:22', '2026-11-10 15:49:22');

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
(320, 'PRES-20261021-0067-840', 248, 67, 4, 10, NULL, NULL, NULL, 'dispensed', 1, '2026-10-21 19:12:41', '2026-10-21 19:14:16', '2026-10-21 19:14:16'),
(321, 'PRES-20261027-0048-326', 249, 48, 4, 12, NULL, NULL, NULL, 'dispensed', 1, '2026-10-27 19:48:59', '2026-10-27 19:51:36', '2026-10-27 19:51:36'),
(322, 'PRES-20261027-0062-966', 250, 62, 4, 12, NULL, NULL, NULL, 'dispensed', 1, '2026-10-27 22:06:01', '2026-10-27 22:07:45', '2026-10-27 22:07:45'),
(323, 'PRES-20261027-0057-909', 251, 57, 4, 10, NULL, NULL, NULL, 'dispensed', 1, '2026-10-27 22:25:44', '2026-10-27 22:27:13', '2026-10-27 22:27:13'),
(324, 'PRES-20261027-0057-581', 251, 57, 4, 10, NULL, NULL, NULL, 'dispensed', 1, '2026-10-27 22:25:44', '2026-10-27 22:27:13', '2026-10-27 22:27:13'),
(325, 'PRES-20261110-0062-963', 252, 62, 4, 12, NULL, NULL, NULL, 'dispensed', 1, '2026-11-10 11:20:10', '2026-11-10 11:22:31', '2026-11-10 11:22:31'),
(326, 'RX-20261110-0252-862', 252, 62, 1, 7, NULL, NULL, NULL, 'confirmed', 1, '2026-11-10 11:33:17', NULL, '2026-11-10 15:44:49'),
(327, 'RX-20261110-0248-885', 248, 67, 1, 10, NULL, NULL, NULL, 'dispensed', 1, '2026-11-10 11:42:48', '2026-11-10 11:44:51', '2026-11-10 11:44:51'),
(328, 'PRES-20261110-0067-806', 253, 67, 4, 10, NULL, NULL, NULL, 'dispensed', 1, '2026-11-10 15:47:34', '2026-11-10 15:49:22', '2026-11-10 15:49:22');

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
  `original_quantity` int(11) DEFAULT NULL,
  `duration` varchar(50) DEFAULT NULL,
  `route` varchar(50) DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `pharmacy_instructions` text DEFAULT NULL,
  `pharmacy_instruction_mode` varchar(20) DEFAULT 'manual',
  `pharmacy_instruction_updated_at` timestamp NULL DEFAULT NULL,
  `pharmacy_instruction_updated_by` int(11) DEFAULT NULL,
  `unit_price` decimal(12,2) DEFAULT 0.00,
  `total_price` decimal(12,2) DEFAULT 0.00,
  `original_total_price` decimal(12,2) DEFAULT NULL,
  `original_bill_amount` decimal(12,2) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `dispensed_at` timestamp NULL DEFAULT NULL,
  `dispensed_by` int(11) DEFAULT NULL,
  `confirmed_by` int(11) DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `cancelled_by` int(11) DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancelled_quantity` int(11) DEFAULT 0,
  `cancel_reason` varchar(255) DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `prescription_items`
--

INSERT INTO `prescription_items` (`id`, `prescription_id`, `patient_id`, `inventory_id`, `medication_name`, `dosage`, `frequency`, `quantity`, `original_quantity`, `duration`, `route`, `instructions`, `pharmacy_instructions`, `pharmacy_instruction_mode`, `pharmacy_instruction_updated_at`, `pharmacy_instruction_updated_by`, `unit_price`, `total_price`, `original_total_price`, `original_bill_amount`, `branch_id`, `created_at`, `dispensed_at`, `dispensed_by`, `confirmed_by`, `confirmed_at`, `cancelled_by`, `cancelled_at`, `cancelled_quantity`, `cancel_reason`, `cancellation_reason`) VALUES
(320, 320, 67, 117, 'ZENCOF', '', '', 30, NULL, '7', '', '', NULL, 'manual', NULL, NULL, 7500.00, 225000.00, NULL, NULL, 1, '2026-10-21 19:12:41', '2026-10-21 19:14:16', 10, 7, '2026-10-21 19:13:17', NULL, NULL, 0, NULL, NULL),
(321, 320, 67, 116, 'ZENCOF', '', '', 15, NULL, '7', '', '', NULL, 'manual', NULL, NULL, 7500.00, 112500.00, NULL, NULL, 1, '2026-10-21 19:12:41', '2026-10-21 19:14:16', 10, 7, '2026-10-21 19:13:17', NULL, NULL, 0, NULL, NULL),
(322, 321, 48, 118, 'ZENCOF', '', '', 7, NULL, '7', 'Oral', '', NULL, 'manual', NULL, NULL, 7500.00, 52500.00, NULL, NULL, 1, '2026-10-27 19:48:59', '2026-10-27 19:51:36', 12, 8, '2026-10-27 19:50:53', NULL, NULL, 0, NULL, NULL),
(323, 321, 48, 116, 'ZENCOF', '', '', 13, NULL, '7', 'Oral', '', NULL, 'manual', NULL, NULL, 7500.00, 97500.00, NULL, NULL, 1, '2026-10-27 19:49:00', '2026-10-27 19:51:36', 12, 8, '2026-10-27 19:50:53', NULL, NULL, 0, NULL, NULL),
(324, 322, 62, 119, 'ZENCOF', '', '', 25, NULL, '7', 'Oral', '', NULL, 'manual', NULL, NULL, 7500.00, 187500.00, NULL, NULL, 1, '2026-10-27 22:06:01', '2026-10-27 22:07:45', 12, 7, '2026-10-27 22:07:18', NULL, NULL, 0, NULL, NULL),
(325, 322, 62, 116, 'ZENCOF', '', '', 2, NULL, '7', 'Oral', '', NULL, 'manual', NULL, NULL, 7500.00, 15000.00, NULL, NULL, 1, '2026-10-27 22:06:01', '2026-10-27 22:07:45', 12, 7, '2026-10-27 22:07:18', NULL, NULL, 0, NULL, NULL),
(326, 323, 57, 114, 'Amoxicillin 500mg', '', 'Every 12 Hours', 10, NULL, '7', 'Sublingual', 'Before Meals', NULL, 'manual', NULL, NULL, 500.00, 5000.00, NULL, NULL, 1, '2026-10-27 22:25:44', '2026-10-27 22:27:13', 10, 7, '2026-10-27 22:26:40', NULL, NULL, 0, NULL, NULL),
(327, 324, 57, 112, 'ALBENDAZOLE', '', 'Every 12 Hours', 10, NULL, '7', 'Sublingual', 'Before Meals', NULL, 'manual', NULL, NULL, 3000.00, 30000.00, NULL, NULL, 1, '2026-10-27 22:25:44', '2026-10-27 22:27:13', 10, 7, '2026-10-27 22:26:40', NULL, NULL, 0, NULL, NULL),
(328, 325, 62, 122, 'ZENCOF', '', 'Every 6 Hours', 7, NULL, '7', 'Topical', '', NULL, 'manual', NULL, NULL, 7500.00, 52500.00, NULL, NULL, 1, '2026-11-10 11:20:10', '2026-11-10 11:22:31', 12, 8, '2026-11-10 11:21:22', NULL, NULL, 0, NULL, NULL),
(329, 325, 62, 123, 'ZENCOF', '', 'Every 6 Hours', 7, NULL, '7', 'Topical', '', NULL, 'manual', NULL, NULL, 7500.00, 52500.00, NULL, NULL, 1, '2026-11-10 11:20:10', '2026-11-10 11:22:31', 12, 8, '2026-11-10 11:21:22', NULL, NULL, 0, NULL, NULL),
(331, 326, 62, 25, 'ALBENDAZOLE', '', '', 10, 10, '', '', '', NULL, 'manual', NULL, NULL, 3000.00, 30000.00, 30000.00, NULL, 1, '2026-11-10 11:33:17', NULL, NULL, 7, '2026-11-10 15:44:49', NULL, NULL, 0, NULL, NULL),
(332, 327, 67, 25, 'ALBENDAZOLE', '', '', 10, 10, '', '', '', NULL, 'manual', NULL, NULL, 3000.00, 30000.00, 30000.00, NULL, 1, '2026-11-10 11:42:48', '2026-11-10 11:44:51', 10, 8, '2026-11-10 11:43:47', NULL, NULL, 0, NULL, NULL),
(333, 328, 67, 124, 'ZENCOF', '', 'Every 12 Hours', 4, NULL, '7', 'Oral', 'With Water', NULL, 'manual', NULL, NULL, 7500.00, 30000.00, NULL, NULL, 1, '2026-11-10 15:47:34', '2026-11-10 15:49:22', 10, 7, '2026-11-10 15:48:09', NULL, NULL, 0, NULL, NULL),
(334, 328, 67, 125, 'ZENCOF', '', 'Every 12 Hours', 2, NULL, '7', 'Oral', 'With Water', NULL, 'manual', NULL, NULL, 7500.00, 15000.00, NULL, NULL, 1, '2026-11-10 15:47:34', '2026-11-10 15:49:22', 10, 7, '2026-11-10 15:48:09', NULL, NULL, 0, NULL, NULL);

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
(319, 249, 48, 4, 18, 'Cryotherapy', NULL, 'Dermatology', NULL, 20000.00, 'completed', 1, NULL, NULL, '2026-10-27 19:49:49', '2026-11-10 15:49:27'),
(320, 251, 57, 4, 15, 'ECG - Electrocardiogram', NULL, 'Cardiology', NULL, 15000.00, 'completed', 1, NULL, NULL, '2026-10-27 22:25:59', '2026-11-10 15:49:27');

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
(22, 'wound dressig', 'PROC-2026-001', 'Procedures', 45000.00, '', NULL, 1, 1, 2, 1, '2026-08-29 23:22:11', '2026-08-29 23:22:11'),
(23, 'wound dressing', 'PROC-20260922-5433', 'Procedures', 55.00, '', NULL, 1, 1, 3, 30, '2026-09-22 21:59:30', '2026-09-22 21:59:30'),
(24, 'wound dressing', 'PROC-20260922-1405', 'Procedures', 55000.00, '', NULL, 1, 1, 3, 30, '2026-09-22 21:59:54', '2026-09-22 21:59:54'),
(25, 'Simple Dressing', 'PROC-20260923-7441', 'Procedures', 30000.00, '', NULL, 1, 1, 3, 30, '2026-09-22 22:00:19', '2026-09-22 22:00:19'),
(26, 'Surgery', 'PROC-20260923-6325', 'Procedures', 200000.00, '', NULL, 1, 1, 3, 30, '2026-09-22 22:00:43', '2026-09-22 22:00:43');

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
  `global_discount_type` varchar(20) DEFAULT 'amount',
  `global_discount_value` decimal(12,2) DEFAULT 0.00,
  `global_discount_amount` decimal(12,2) DEFAULT 0.00,
  `final_total_buying_cost` decimal(12,2) DEFAULT 0.00,
  `final_total_selling_value` decimal(12,2) DEFAULT 0.00,
  `total_cost` decimal(15,2) DEFAULT 0.00,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchases`
--

INSERT INTO `purchases` (`id`, `invoice_number`, `purchase_type`, `created_by`, `joined_users`, `created_by_name`, `branch_id`, `status`, `cancelled_reason`, `cancelled_by`, `cancelled_by_name`, `cancelled_at`, `total_items`, `total_quantity`, `total_buying_cost`, `total_selling_value`, `global_discount_type`, `global_discount_value`, `global_discount_amount`, `final_total_buying_cost`, `final_total_selling_value`, `total_cost`, `completed_at`, `created_at`, `updated_at`) VALUES
(14, 'INV-MED-20261013-0001', 'medicine', 1, NULL, 'System Admin', 1, 'COMPLETED', NULL, NULL, NULL, NULL, 1, 60, 60000.00, 150000.00, 'amount', 0.00, 0.00, 0.00, 0.00, 0.00, '2026-10-13 01:12:16', '2026-10-13 01:11:24', '2026-10-13 01:12:16'),
(15, 'INV-MED-20261013-0002', 'medicine', 1, NULL, 'System Admin', 1, 'COMPLETED', NULL, NULL, NULL, NULL, 2, 100, 46000.00, 118000.00, 'amount', 0.00, 0.00, 0.00, 0.00, 0.00, '2026-10-13 01:20:49', '2026-10-13 01:20:03', '2026-10-13 01:20:49'),
(16, 'INV-MED-20261013-0003', 'medicine', 1, NULL, 'System Admin', 1, 'COMPLETED', NULL, NULL, NULL, NULL, 2, 120, 18000.00, 60000.00, 'amount', 0.00, 0.00, 0.00, 0.00, 0.00, '2026-10-13 02:12:00', '2026-10-13 02:10:15', '2026-10-13 02:12:00'),
(17, 'INV-MED-20261016-0001', 'medicine', 7, NULL, 'LUCY MUSSA', 1, 'COMPLETED', NULL, NULL, NULL, NULL, 1, 70, 140000.00, 525000.00, 'amount', 0.00, 0.00, 0.00, 0.00, 0.00, '2026-10-16 15:29:24', '2026-10-16 15:24:58', '2026-10-16 15:29:24'),
(18, 'INV-MED-20261021-0001', 'medicine', 1, NULL, 'System Admin', 1, 'COMPLETED', NULL, NULL, NULL, NULL, 1, 50, 100000.00, 375000.00, 'amount', 0.00, 0.00, 0.00, 0.00, 0.00, '2026-10-21 17:09:03', '2026-10-21 17:07:34', '2026-10-21 17:09:03'),
(19, 'INV-EQP-20261026-0001', 'equipment', 8, NULL, 'Mary John', 1, 'COMPLETED', NULL, NULL, NULL, NULL, 1, 150, 75000.00, 180000.00, 'amount', 0.00, 0.00, 0.00, 0.00, 0.00, '2026-10-26 21:00:40', '2026-10-26 21:00:20', '2026-10-26 21:00:40'),
(20, 'INV-MED-20261027-0001', 'medicine', 8, NULL, 'Mary John', 1, 'COMPLETED', NULL, NULL, NULL, NULL, 1, 7, 14000.00, 52500.00, 'amount', 0.00, 0.00, 0.00, 0.00, 0.00, '2026-10-27 22:47:26', '2026-10-27 22:46:51', '2026-10-27 22:47:26'),
(21, 'INV-MED-20261027-0002', 'medicine', 8, NULL, 'Mary John', 1, 'COMPLETED', NULL, NULL, NULL, NULL, 1, 25, 50000.00, 187500.00, 'amount', 0.00, 0.00, 0.00, 0.00, 0.00, '2026-10-27 23:21:52', '2026-10-27 23:13:55', '2026-10-27 23:21:52'),
(22, 'INV-MED-20261027-0003', 'medicine', 7, NULL, 'LUCY MUSSA', 1, 'COMPLETED', NULL, NULL, NULL, NULL, 1, 400, 200000.00, 480000.00, 'amount', 35000.00, 35000.00, 165000.00, 480000.00, 0.00, '2026-10-28 00:35:23', '2026-10-28 00:27:46', '2026-10-28 00:35:23'),
(23, 'INV-MED-20261028-0001', 'medicine', 8, NULL, 'Mary John', 1, 'COMPLETED', NULL, NULL, NULL, NULL, 1, 50, 50000.00, 150000.00, 'amount', 0.00, 0.00, 50000.00, 150000.00, 0.00, '2026-10-28 15:55:12', '2026-10-28 15:54:08', '2026-10-28 15:55:12'),
(24, 'INV-MED-20261028-0002', 'medicine', 8, NULL, 'Mary John', 1, 'COMPLETED', NULL, NULL, NULL, NULL, 1, 70, 140000.00, 525000.00, 'amount', 0.00, 0.00, 140000.00, 525000.00, 0.00, '2026-10-28 15:58:24', '2026-10-28 15:57:53', '2026-10-28 15:58:24'),
(25, 'INV-MED-20261110-0001', 'medicine', 1, NULL, 'System Admin', 1, 'COMPLETED', NULL, NULL, NULL, NULL, 1, 7, 14000.00, 52500.00, 'amount', 0.00, 0.00, 14000.00, 52500.00, 0.00, '2026-11-10 14:11:24', '2026-11-10 14:10:46', '2026-11-10 14:11:24'),
(26, 'INV-MED-20261110-0002', 'medicine', 7, NULL, 'LUCY MUSSA', 1, 'COMPLETED', NULL, NULL, NULL, NULL, 1, 4, 8000.00, 30000.00, 'amount', 0.00, 0.00, 8000.00, 30000.00, 0.00, '2026-11-10 18:45:40', '2026-11-10 18:45:06', '2026-11-10 18:45:40'),
(27, 'INV-MED-20261110-0003', 'medicine', 7, NULL, 'LUCY MUSSA', 1, 'COMPLETED', NULL, NULL, NULL, NULL, 1, 4, 8000.00, 30000.00, 'amount', 0.00, 0.00, 8000.00, 30000.00, 0.00, '2026-11-10 18:46:22', '2026-11-10 18:45:56', '2026-11-10 18:46:22');

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
  `item_id` int(11) DEFAULT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `item_category` varchar(100) DEFAULT NULL,
  `item_unit` varchar(50) DEFAULT NULL,
  `item_supplier` varchar(255) DEFAULT NULL,
  `item_expiry_date` date DEFAULT NULL,
  `item_batch_number` varchar(100) DEFAULT NULL,
  `item_status` varchar(20) DEFAULT 'active',
  `item_reorder_level` int(11) DEFAULT 10,
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

INSERT INTO `purchase_items` (`id`, `purchase_id`, `item_type`, `medicine_id`, `equipment_id`, `item_id`, `item_name`, `item_category`, `item_unit`, `item_supplier`, `item_expiry_date`, `item_batch_number`, `item_status`, `item_reorder_level`, `quantity`, `unit_price`, `buying_price`, `selling_price`, `buying_total`, `total_price`, `total_buying_cost`, `total_selling_value`, `added_by`, `added_by_name`, `added_at`) VALUES
(33, 14, 'medicine', NULL, NULL, NULL, 'AMOXILINE', 'Antacids', 'pcs', 'AVANA MEDICS', '2028-09-13', 'BATCH-20261013-C9F15C', 'active', 20, 60, 0.00, 1000.00, 2500.00, 0.00, 0.00, 60000.00, 150000.00, 1, 'System Admin', '2026-10-13 01:11:46'),
(34, 15, 'medicine', NULL, NULL, NULL, 'ALBENDAZOLE', 'Antibiotics', 'pcs', 'AVANA MEDICS', '2028-05-13', 'BATCH-20261013-3A6820', 'active', 100, 30, 0.00, 1300.00, 3000.00, 0.00, 0.00, 39000.00, 90000.00, 1, 'System Admin', '2026-10-13 01:20:24'),
(35, 15, 'medicine', NULL, NULL, NULL, 'Metronidazole 400mg', 'Antibiotics', 'tablets', 'Medical Supplies Ltd', '2028-05-13', 'BATCH-20261013-875AE0', 'active', 25, 70, 0.00, 100.00, 400.00, 0.00, 0.00, 7000.00, 28000.00, 1, 'System Admin', '2026-10-13 01:20:44'),
(36, 16, 'medicine', NULL, NULL, NULL, 'Amoxicillin 500mg', 'Antibiotics', 'capsules', 'PharmaPlus Ltd', '2027-05-13', 'BATCH-20261013-7A18B3', 'active', 30, 50, 0.00, 150.00, 500.00, 0.00, 0.00, 7500.00, 25000.00, 1, 'System Admin', '2026-10-13 02:10:34'),
(37, 16, 'medicine', NULL, NULL, NULL, 'Amoxicillin 500mg', 'Antibiotics', 'capsules', 'PharmaPlus Ltd', '2028-05-13', 'BATCH-20261013-ABD836', 'active', 30, 70, 0.00, 150.00, 500.00, 0.00, 0.00, 10500.00, 35000.00, 8, 'Mary John', '2026-10-13 02:11:20'),
(38, 17, 'medicine', NULL, NULL, NULL, 'ZENCOF', 'Antacids', 'bottle', 'AVANA MEDICS', '2028-11-16', 'BATCH-20261016-B27B37', 'active', 20, 70, 0.00, 2000.00, 7500.00, 0.00, 0.00, 140000.00, 525000.00, 7, 'LUCY MUSSA', '2026-10-16 15:28:37'),
(39, 18, 'medicine', NULL, NULL, NULL, 'ZENCOF', 'Antacids', 'bottle', 'AVANA MEDICS', '2027-09-21', 'BATCH-20261021-6E0A9E', 'active', 20, 50, 0.00, 2000.00, 7500.00, 0.00, 0.00, 100000.00, 375000.00, 1, 'System Admin', '2026-10-21 17:08:00'),
(40, 19, 'equipment', NULL, NULL, NULL, 'SINDANO', 'Wound Care', 'set', 'AMANA', NULL, 'EQP-20261026-4C971C', 'active', 50, 150, 0.00, 500.00, 1200.00, 0.00, 0.00, 75000.00, 180000.00, 8, 'Mary John', '2026-10-26 21:00:34'),
(41, 20, 'medicine', NULL, NULL, NULL, 'ZENCOF', 'Antacids', 'bottle', 'AVANA MEDICS', '2028-06-19', 'BATCH-20261027-C3EEFC', 'active', 2, 7, 0.00, 2000.00, 7500.00, 0.00, 0.00, 14000.00, 52500.00, 8, 'Mary John', '2026-10-27 22:47:22'),
(42, 21, 'medicine', NULL, NULL, NULL, 'ZENCOF', 'Antacids', 'bottle', 'AVANA MEDICS', '2028-10-27', 'BATCH-20261027-4462D2', 'active', 2, 25, 0.00, 2000.00, 7500.00, 0.00, 0.00, 50000.00, 187500.00, 8, 'Mary John', '2026-10-27 23:14:20'),
(44, 22, 'medicine', NULL, NULL, NULL, 'Panadol', 'Painkillers', 'tablets', 'PharmaPlus Ltd', '2029-09-28', 'BATCH-20261027-3768D5', 'active', 50, 400, 0.00, 500.00, 1200.00, 0.00, 0.00, 200000.00, 480000.00, 7, 'LUCY MUSSA', '2026-10-28 00:34:41'),
(45, 23, 'medicine', NULL, NULL, NULL, 'MAGNEZIUM', 'Painkillers', 'tablets', 'AVANA MEDICS', '2027-10-22', 'BATCH-20261028-1A6789', 'active', 10, 50, 0.00, 1000.00, 3000.00, 0.00, 0.00, 50000.00, 150000.00, 8, 'Mary John', '2026-10-28 15:55:00'),
(46, 24, 'medicine', NULL, NULL, NULL, 'ZENCOF', 'Antacids', 'bottle', 'AVANA MEDICS', '2028-05-28', 'BATCH-20261028-20D2A6', 'active', 2, 70, 0.00, 2000.00, 7500.00, 0.00, 0.00, 140000.00, 525000.00, 8, 'Mary John', '2026-10-28 15:58:18'),
(47, 25, 'medicine', NULL, NULL, NULL, 'ZENCOF', 'Antacids', 'bottle', 'AVANA MEDICS', '2028-10-10', 'BATCH-20261110-6CB883', 'active', 2, 7, 0.00, 2000.00, 7500.00, 0.00, 0.00, 14000.00, 52500.00, 1, 'System Admin', '2026-11-10 14:11:12'),
(48, 26, 'medicine', NULL, NULL, NULL, 'ZENCOF', 'Antacids', 'bottle', 'AVANA MEDICS', '2028-05-10', 'BATCH-20261110-312002', 'active', 2, 4, 0.00, 2000.00, 7500.00, 0.00, 0.00, 8000.00, 30000.00, 7, 'LUCY MUSSA', '2026-11-10 18:45:28'),
(49, 27, 'medicine', NULL, NULL, NULL, 'ZENCOF', 'Antacids', 'bottle', 'AVANA MEDICS', '2028-05-10', 'BATCH-20261110-534F07', 'active', 2, 4, 0.00, 2000.00, 7500.00, 0.00, 0.00, 8000.00, 30000.00, 7, 'LUCY MUSSA', '2026-11-10 18:46:16');

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
(25, 'REF-20261016-0063-322', NULL, 63, 4, 'external', NULL, 'MUHIMBILI', '', '+255623693303', NULL, 'HATUNA VIFAA NA MTAALAMU WA UPASUAJI', 'Expert Type: Surgery Expert\n\n\n\n--- Diagnosis ---\nAMIBA 13\nDisease Code: D-AMIBA1-981', 'AMIBA 13', '', 'Surgery Expert', 'emergency', 'referred', NULL, NULL, NULL, '2026-10-16 14:45:49', 4, 1, '2026-10-16 11:45:49', '2026-10-16 11:45:49', NULL, NULL, NULL);

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
(341, 2, NULL, 62, 'out', 20, 500, 480, 'prescription', 305, 4, 1, 'Prescribed by Dr. Dr.ERICK JOHN: Amoxicillin 500mg (Rx #PRES-20260929-0062-440) - Qty: 20', '2026-09-29 14:37:35'),
(342, 3, NULL, 62, 'out', 20, 500, 480, 'prescription', 306, 4, 1, 'Prescribed by Dr. Dr.ERICK JOHN: Ciprofloxacin 500mg (Rx #PRES-20260929-0062-956) - Qty: 20', '2026-09-29 14:37:35'),
(343, 18, NULL, 62, 'out', 20, 500, 480, 'prescription', 307, 4, 1, 'Prescribed by Dr. Dr.ERICK JOHN: Diazepam 5mg (Rx #PRES-20260929-0062-950) - Qty: 20', '2026-09-29 14:37:35'),
(344, 11, NULL, 62, 'out', 20, 500, 480, 'prescription', 308, 4, 1, 'Prescribed by Dr. Dr.ERICK JOHN: Beclomethasone Inhaler (Rx #PRES-20260929-0062-860) - Qty: 20', '2026-09-29 14:37:35'),
(345, NULL, 40, 62, 'out', 20, 500, 480, 'procedure', 1510, 4, 1, 'Doctor used (Dr. Dr.ERICK JOHN): BANDAGE - Bill #398 - Qty: 20', '2026-09-29 14:37:56'),
(346, NULL, 6, 62, 'out', 20, 500, 480, 'procedure', 1511, 4, 1, 'Doctor used (Dr. Dr.ERICK JOHN): Bandage Roll - Bill #398 - Qty: 20', '2026-09-29 14:37:56'),
(347, NULL, 1, 62, 'out', 20, 500, 480, 'procedure', 1512, 4, 1, 'Doctor used (Dr. Dr.ERICK JOHN): ECG Machine (12-Lead) - Bill #398 - Qty: 20', '2026-09-29 14:37:56'),
(348, 11, NULL, 62, 'in', 20, 480, 500, 'prescription', 308, 4, 1, 'Stock returned - Removed by Dr. Dr.ERICK JOHN: Beclomethasone Inhaler (Rx #PRES-20260929-0062-860) - Qty: 20', '2026-09-29 14:38:54'),
(349, 2, NULL, 62, 'out', 20, 480, 460, 'prescription', 305, 10, 1, 'Auto-dispensed - Rx #PRES-20260929-0062-440', '2026-09-29 14:42:54'),
(350, 3, NULL, 62, 'out', 20, 480, 460, 'prescription', 306, 10, 1, 'Auto-dispensed - Rx #PRES-20260929-0062-956', '2026-09-29 14:42:54'),
(351, 18, NULL, 62, 'out', 20, 480, 460, 'prescription', 307, 10, 1, 'Auto-dispensed - Rx #PRES-20260929-0062-950', '2026-09-29 14:42:54'),
(352, 7, NULL, 61, 'out', 40, 500, 460, 'prescription', 309, 4, 1, 'Prescribed by Dr. Dr.ERICK JOHN: Diclofenac 50mg (Rx #PRES-20260930-0061-221) - Qty: 40', '2026-09-30 10:22:55'),
(353, 18, NULL, 61, 'out', 40, 460, 420, 'prescription', 310, 4, 1, 'Prescribed by Dr. Dr.ERICK JOHN: Diazepam 5mg (Rx #PRES-20260930-0061-595) - Qty: 40', '2026-09-30 10:22:55'),
(354, 13, NULL, 61, 'out', 40, 500, 460, 'prescription', 311, 4, 1, 'Prescribed by Dr. Dr.ERICK JOHN: Enalapril 10mg (Rx #PRES-20260930-0061-752) - Qty: 40', '2026-09-30 10:22:55'),
(355, 8, NULL, 61, 'out', 40, 500, 460, 'prescription', 312, 4, 1, 'Prescribed by Dr. Dr.ERICK JOHN: Cetirizine 10mg (Rx #PRES-20260930-0061-990) - Qty: 40', '2026-09-30 10:22:55'),
(356, NULL, 3, 61, 'out', 1, 500, 499, 'procedure', 1529, 4, 1, 'Doctor used (Dr. Dr.ERICK JOHN): Blood Pressure Monitor - Bill #406 - Qty: 1', '2026-09-30 10:23:18'),
(357, NULL, 9, 61, 'out', 1, 500, 499, 'procedure', 1530, 4, 1, 'Doctor used (Dr. Dr.ERICK JOHN): Suture Kit - Bill #406 - Qty: 1', '2026-09-30 10:23:18'),
(358, 7, NULL, 61, 'out', 40, 460, 420, 'prescription', 309, 11, 1, 'Auto-dispensed - Rx #PRES-20260930-0061-221', '2026-09-30 10:24:58'),
(359, 18, NULL, 61, 'out', 40, 420, 380, 'prescription', 310, 11, 1, 'Auto-dispensed - Rx #PRES-20260930-0061-595', '2026-09-30 10:24:59'),
(360, 13, NULL, 61, 'out', 40, 460, 420, 'prescription', 311, 11, 1, 'Auto-dispensed - Rx #PRES-20260930-0061-752', '2026-09-30 10:24:59'),
(361, 8, NULL, 61, 'out', 40, 460, 420, 'prescription', 312, 11, 1, 'Auto-dispensed - Rx #PRES-20260930-0061-990', '2026-09-30 10:24:59'),
(362, 25, NULL, 63, 'out', 10, 500, 490, 'prescription', 313, 4, 1, 'Prescribed by Dr. Dr.ERICK JOHN: ALBENDAZOLE (Rx #PRES-20261001-0063-735) - Qty: 10', '2026-10-01 11:56:44'),
(363, 12, NULL, NULL, 'out', 5, 500, 495, 'otc', 36, 7, 1, 'OTC Sale - PAID: OTC-20261016-8487 - Customer: Fatmata - Item: Amlodipine 5mg', '2026-10-16 12:23:28'),
(364, 114, NULL, NULL, 'out', 5, 50, 45, 'otc', 36, 7, 1, 'OTC Sale - PAID: OTC-20261016-8487 - Customer: Fatmata - Item: Amoxicillin 500mg', '2026-10-16 12:23:28'),
(365, 112, NULL, 67, 'out', 20, 30, 10, 'prescription', 314, 10, 1, 'V27 - Dispensed - Rx #PRES-20261016-0067-285 - Qty: 20 - Batch: BATCH-20261013-3A6820', '2026-10-16 12:34:13'),
(366, 10, NULL, 67, 'out', 10, 500, 490, 'prescription', 316, 10, 1, 'V27 - Dispensed - Rx #PRES-20261016-0067-693 - Qty: 10 - Batch: BATCH-SALB-20260909-001', '2026-10-16 12:34:13'),
(367, NULL, 40, 67, 'out', 10, 480, 470, '', 1546, 10, 1, 'V27 - Equipment - Bill #410 - BANDAGE | Batch: EQP-20260909-8495C2 | Stock: 480 → 470', '2026-10-16 12:34:13'),
(368, NULL, 6, 67, 'out', 10, 480, 470, '', 1547, 10, 1, 'V27 - Equipment - Bill #410 - Bandage Roll | Batch: EQP-20260909-BAND-001 | Stock: 480 → 470', '2026-10-16 12:34:13'),
(369, NULL, 41, 67, 'out', 10, 500, 490, '', 1548, 10, 1, 'V27 - Equipment - Bill #410 - SINDANO | Batch: EQP-20260909-40E8C8 | Stock: 500 → 490', '2026-10-16 12:34:13'),
(370, 117, NULL, 61, 'out', 50, 50, 0, 'prescription', 318, 10, 1, 'V30 - Dispensed - Rx #PRES-20261021-0061-594 - Qty: 50 - Batch: BATCH-20261021-6E0A9E', '2026-10-21 14:28:01'),
(371, 116, NULL, 61, 'out', 40, 70, 30, 'prescription', 318, 10, 1, 'V30 - Dispensed - Rx #PRES-20261021-0061-594 - Qty: 40 - Batch: BATCH-20261016-B27B37', '2026-10-21 14:28:01'),
(372, 19, NULL, 57, 'out', 200, 500, 300, 'prescription', 319, 10, 1, 'V30 - Dispensed - Rx #PRES-20261021-0057-641 - Qty: 200 - Batch: BATCH-AMIT-20260909-001', '2026-10-21 14:35:30'),
(373, 117, NULL, 67, 'out', 30, 30, 0, 'prescription', 320, 10, 1, 'V30 - Dispensed - Rx #PRES-20261021-0067-840 - Qty: 30 - Batch: BATCH-20261021-6E0A9E', '2026-10-21 19:14:16'),
(374, 116, NULL, 67, 'out', 15, 30, 15, 'prescription', 320, 10, 1, 'V30 - Dispensed - Rx #PRES-20261021-0067-840 - Qty: 15 - Batch: BATCH-20261016-B27B37', '2026-10-21 19:14:16'),
(375, NULL, 41, 48, 'out', 1, 490, 489, 'lab_test', 377, 11, 1, 'V30 - Lab equipment - KFADURO - Lab #377 | Batch: EQP-20260909-40E8C8 | Stock: 490 → 489', '2026-10-27 19:45:10'),
(376, 118, NULL, 48, 'out', 7, 7, 0, 'prescription', 321, 12, 1, 'V21 - Dispensed - Rx #PRES-20261027-0048-326 - Qty: 7 - Batch: BATCH-20261027-C3EEFC', '2026-10-27 19:51:36'),
(377, 116, NULL, 48, 'out', 13, 15, 2, 'prescription', 321, 12, 1, 'V21 - Dispensed - Rx #PRES-20261027-0048-326 - Qty: 13 - Batch: BATCH-20261016-B27B37', '2026-10-27 19:51:36'),
(378, NULL, 40, 48, 'out', 1, 470, 469, '', 1579, 12, 1, 'V21 - Equipment - Bill #417 - BANDAGE | Batch: EQP-20260909-8495C2', '2026-10-27 19:51:36'),
(379, 119, NULL, 62, 'out', 25, 25, 0, 'prescription', 322, 12, 1, 'V30 - Dispensed - Rx #PRES-20261027-0062-966 - Qty: 25 - Batch: BATCH-20261027-4462D2', '2026-10-27 22:07:45'),
(380, 116, NULL, 62, 'out', 2, 2, 0, 'prescription', 322, 12, 1, 'V30 - Dispensed - Rx #PRES-20261027-0062-966 - Qty: 2 - Batch: BATCH-20261016-B27B37', '2026-10-27 22:07:45'),
(381, 114, NULL, 57, 'out', 10, 45, 35, 'prescription', 323, 10, 1, 'V30 - Dispensed - Rx #PRES-20261027-0057-909 - Qty: 10 - Batch: BATCH-20261013-7A18B3', '2026-10-27 22:27:13'),
(382, 112, NULL, 57, 'out', 10, 10, 0, 'prescription', 324, 10, 1, 'V30 - Dispensed - Rx #PRES-20261027-0057-581 - Qty: 10 - Batch: BATCH-20261013-3A6820', '2026-10-27 22:27:13'),
(383, NULL, 40, 57, 'out', 10, 469, 459, '', 1594, 10, 1, 'V30 - Equipment - Bill #419 - BANDAGE | Batch: EQP-20260909-8495C2 | Stock: 469 → 459', '2026-10-27 22:27:13'),
(384, NULL, 6, 57, 'out', 10, 470, 460, '', 1595, 10, 1, 'V30 - Equipment - Bill #419 - Bandage Roll | Batch: EQP-20260909-BAND-001 | Stock: 470 → 460', '2026-10-27 22:27:13'),
(385, NULL, 41, 57, 'out', 1, 489, 488, 'lab_test', 381, 10, 1, 'V30 - Lab equipment - KFADURO - Lab #381 | Batch: EQP-20260909-40E8C8 | Stock: 489 → 488', '2026-10-27 22:27:13'),
(386, 122, NULL, 62, 'out', 7, 7, 0, 'prescription', 325, 12, 1, 'V31 - Dispensed - Rx #PRES-20261110-0062-963 - Qty: 7 - Batch: BATCH-20261028-20D2A6', '2026-11-10 11:22:31'),
(387, 123, NULL, 62, 'out', 7, 7, 0, 'prescription', 325, 12, 1, 'V31 - Dispensed - Rx #PRES-20261110-0062-963 - Qty: 7 - Batch: BATCH-20261110-6CB883', '2026-11-10 11:22:31'),
(388, 25, NULL, 67, 'out', 10, 490, 480, 'prescription', 327, 10, 1, 'V22 - Dispensed - Rx #RX-20261110-0248-885 - Qty: 10 - Batch: BATCH-20260908-A196B7', '2026-11-10 11:44:51'),
(389, 124, NULL, 67, 'out', 4, 4, 0, 'prescription', 328, 10, 1, 'V31 - Dispensed - Rx #PRES-20261110-0067-806 - Qty: 4 - Batch: BATCH-20261110-312002', '2026-11-10 15:49:22'),
(390, 125, NULL, 67, 'out', 2, 4, 2, 'prescription', 328, 10, 1, 'V31 - Dispensed - Rx #PRES-20261110-0067-806 - Qty: 2 - Batch: BATCH-20261110-534F07', '2026-11-10 15:49:22');

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
(1, 'admin1', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'System Admin', 'admin@braick.com', '+255 613 234 123', 'admin', 1, NULL, 1, '2026-11-10 11:29:58', 'user_1_1788291272.png', 'active', '2026-08-23 12:26:10', '2026-11-10 11:29:58'),
(3, 'admin2', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'BRAICK', 'braick.admin@braick.com', '+255 732 123 030', 'admin', 1, NULL, 0, '2026-08-26 14:10:40', NULL, 'active', '2026-08-23 12:41:40', '2026-09-04 17:59:45'),
(4, 'Dr.Dodoma1', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Dr.ERICK JOHN', 'erick.dodoma@braick.com', '+255 700 000 011', 'doctor', 1, 'General Medicine', 0, '2026-11-10 15:47:43', 'user_4_1787697956.png', 'active', '2026-08-23 12:41:40', '2026-11-10 15:47:43'),
(5, 'Dr.Dodoma2', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Dr. Grace Peter', 'grace.dodoma@braick.com', '+255 700 000 012', 'doctor', 1, 'Pediatrics', 0, '2026-09-21 21:21:18', NULL, 'active', '2026-08-23 12:41:40', '2026-09-21 21:21:18'),
(6, 'Dr.Dodoma3', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Dr. John Mushi', 'john.dodoma@braick.com', '+255 700 000 013', 'doctor', 1, 'Cardiology', 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(7, 'Pharm.Dodoma3', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'LUCY MUSSA', 'pharm.dodoma@braick.com', '+255 700 000 014', 'pharmacy', 1, NULL, 1, '2026-11-10 15:48:09', 'user_7_1787493390.png', 'active', '2026-08-23 12:41:40', '2026-11-10 15:48:09'),
(8, 'Pharm.Dodoma2', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Mary John', 'mary.dodoma@braick.com', '+255 700 000 015', 'pharmacy', 1, NULL, 0, '2026-11-10 11:43:10', NULL, 'active', '2026-08-23 12:41:40', '2026-11-10 11:43:10'),
(9, 'Pharm.Dodoma1', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'James Mwangi', 'james.dodoma@braick.com', '+255 700 000 016', 'pharmacy', 1, NULL, 0, '2026-09-05 13:24:29', NULL, 'active', '2026-08-23 12:41:40', '2026-09-05 13:24:29'),
(10, 'Recpt.Dodoma1', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'SALOME SANGA', 'salome.dodoma@braick.com', '+255 700 000 017', 'reception', 1, NULL, 1, '2026-11-10 16:22:20', 'reception_10_1792150816.png', 'active', '2026-08-23 12:41:40', '2026-11-10 16:22:20'),
(11, 'Recpt.Dodoma2', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Rose Mwangi', 'rose.dodoma@braick.com', '+255 700 000 018', 'reception', 1, NULL, 1, '2026-10-27 22:18:53', NULL, 'active', '2026-08-23 12:41:40', '2026-10-27 22:18:53'),
(12, 'Recpt.Dodoma3', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'JUDITH SOLOMONI', 'anna.dodoma@braick.com', '+255 700 000 019', 'reception', 1, NULL, 1, '2026-11-10 15:44:22', NULL, 'active', '2026-08-23 12:41:40', '2026-11-10 15:44:22'),
(13, 'Lab.Dodoma1', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'ANGERITHA KIMARO MSANGI', 'angel@gmail.com', '+255 700 000 020', 'laboratory', 1, 'Reception', 0, '2026-10-27 19:41:28', 'user_13_1787502536.png', 'active', '2026-08-23 12:41:40', '2026-10-27 19:41:28'),
(14, 'Lab.Dodoma2', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Peter Lema', 'peter.dodoma@braick.com', '+255 700 000 021', 'laboratory', 1, NULL, 0, '2026-09-21 18:59:00', NULL, 'active', '2026-08-23 12:41:40', '2026-09-21 18:59:00'),
(15, 'Lab.Dodoma3', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Sarah Mwamba', 'sarah.dodoma@braick.com', '+255 700 000 022', 'laboratory', 1, NULL, 0, '2026-10-27 22:24:02', NULL, 'active', '2026-08-23 12:41:40', '2026-10-27 22:24:02'),
(16, 'cashier.dodoma', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Cashier Dodoma', 'cashier.dodoma@braick.com', '+255 700 000 023', 'cashier', 1, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(17, 'Dr.Arusha1', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Dr. David Mwanga', 'david.arusha@braick.com', '+255 700 000 024', 'doctor', 2, 'General Medicine', 0, '2026-09-21 22:58:52', NULL, 'active', '2026-08-23 12:41:40', '2026-09-21 22:58:52'),
(18, 'Dr.Arusha2', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Dr. Anna Kivuyo', 'anna.arusha@braick.com', '+255 700 000 025', 'doctor', 2, 'Obstetrics', 0, '2026-09-22 12:16:12', NULL, 'active', '2026-08-23 12:41:40', '2026-09-22 12:16:12'),
(19, 'Dr.Arusha3', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Dr. Peter Lema', 'peter.arusha@braick.com', '+255 700 000 026', 'doctor', 2, 'Surgery', 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(20, 'Pharm.Arusha1', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Pharmacy Arusha', 'pharm.arusha@braick.com', '+255 700 000 027', 'pharmacy', 2, NULL, 0, '2026-09-21 23:01:39', NULL, 'active', '2026-08-23 12:41:40', '2026-09-21 23:01:39'),
(21, 'Pharm.Arusha2', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Juma Mussa', 'juma.arusha@braick.com', '+255 700 000 028', 'pharmacy', 2, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(22, 'Pharm.Arusha3', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Catherine M', 'catherine.arusha@braick.com', '+255 700 000 029', 'pharmacy', 2, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(23, 'Recpt.Arusha1', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'NYANSAEL NZILU', 'reception.arusha@braick.com', '+255 700 000 030', 'reception', 2, NULL, 0, '2026-09-22 12:08:50', NULL, 'active', '2026-08-23 12:41:40', '2026-09-22 12:08:50'),
(24, 'Recpt.Arusha2', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'GRACE MUSHI', 'grace.arusha@braick.com', '+255 700 000 031', 'reception', 2, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(25, 'Recpt.Arusha3', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'LUCY PETER', 'lucy.arusha@braick.com', '+255 700 000 032', 'reception', 2, NULL, 0, '2026-09-22 19:08:24', NULL, 'active', '2026-08-23 12:41:40', '2026-09-22 19:08:24'),
(26, 'Lab.Arusha1', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'MARIA MSANGI', 'lab.arusha@braick.com', '+255 700 000 033', 'laboratory', 2, NULL, 0, '2026-09-22 12:09:49', NULL, 'active', '2026-08-23 12:41:40', '2026-09-22 12:09:49'),
(27, 'lab.arusha2', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Moses Paul', 'moses.arusha@braick.com', '+255 700 000 034', 'laboratory', 2, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(28, 'lab.arusha3', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Hellen John', 'hellen.arusha@braick.com', '+255 700 000 035', 'laboratory', 2, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(29, 'cashier.arusha', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Cashier Arusha', 'cashier.arusha@braick.com', '+255 700 000 036', 'cashier', 2, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(30, 'dr.dar1', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Dr. James Kato', 'james.dar@braick.com', '+255 700 000 037', 'doctor', 3, 'Neurology', 0, '2026-09-22 22:10:34', NULL, 'active', '2026-08-23 12:41:40', '2026-09-22 22:10:34'),
(31, 'dr.dar2', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Dr. Sarah Mwamba', 'sarah.dar@braick.com', '+255 700 000 038', 'doctor', 3, 'Cardiology', 0, '2026-09-22 22:14:22', NULL, 'active', '2026-08-23 12:41:40', '2026-09-22 22:14:22'),
(32, 'dr.dar3', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Dr. Mary Ndugu', 'mary.dar@braick.com', '+255 700 000 039', 'doctor', 3, 'Pediatrics', 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(33, 'pharm.dar1', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Pharmacy Dar', 'pharm.dar@braick.com', '+255 700 000 040', 'pharmacy', 3, NULL, 0, '2026-09-22 22:01:06', NULL, 'active', '2026-08-23 12:41:40', '2026-09-22 22:01:06'),
(34, 'pharm.dar2', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'William M', 'william.dar@braick.com', '+255 700 000 041', 'pharmacy', 3, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(35, 'pharm.dar3', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Diana K', 'diana.dar@braick.com', '+255 700 000 042', 'pharmacy', 3, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(36, 'reception.dar1', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Reception Dar', 'reception.dar@braick.com', '+255 700 000 043', 'reception', 3, NULL, 0, '2026-09-22 22:06:15', NULL, 'active', '2026-08-23 12:41:40', '2026-09-22 22:06:15'),
(37, 'reception.dar2', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Flora M', 'flora.dar@braick.com', '+255 700 000 044', 'reception', 3, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(38, 'reception.dar3', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Paul L', 'paul.dar@braick.com', '+255 700 000 045', 'reception', 3, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(39, 'lab.dar1', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Lab Technician Dar', 'lab.dar@braick.com', '+255 700 000 046', 'laboratory', 3, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(40, 'lab.dar2', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Thomas N', 'thomas.dar@braick.com', '+255 700 000 047', 'laboratory', 3, NULL, 0, '2026-09-22 22:11:11', NULL, 'active', '2026-08-23 12:41:40', '2026-09-22 22:11:11'),
(41, 'lab.dar3', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Jane K', 'jane.dar@braick.com', '+255 700 000 048', 'laboratory', 3, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(42, 'cashier.dar', '$2y$10$4SErCssRNRm8SXyKU.wGw.dSdvzA.p1xbEELciT.2HRYEpji6mSUe', '2026-09-02 09:37:37', 1, 'Cashier Dar', 'cashier.dar@braick.com', '+255 700 000 049', 'cashier', 3, NULL, 0, NULL, NULL, 'active', '2026-08-23 12:41:40', '2026-09-02 09:37:37'),
(43, 'Dr.dodoma4', '$2y$10$kM2BXne4K607PIvNGibY9e1TYiL.DWW4TWoVb91F0BKw5gArXgcPy', '2026-09-02 21:06:05', 1, 'SARAFINA MHECHE', 'sarah@braick.com', '0623693303', 'doctor', 1, 'Reception', 0, '2026-09-02 21:06:38', NULL, 'active', '2026-09-02 21:06:05', '2026-09-02 21:15:20'),
(44, 'R.angerith', '$2y$10$uWAY/jylf2vW4kUfBKVx6.7WOOEqJ/MDHZNYv170sMOLcIPKHZZPi', '2026-09-03 01:31:31', 1, 'ANGERITHA KIMARO', 'receptiondodoma@braick.com', '0746526243', 'reception', 2, '', 0, NULL, NULL, 'inactive', '2026-09-03 01:31:31', '2026-09-29 13:42:12'),
(45, 'Audit.Dodoma1', '$2y$10$wJzev6ObUWTxiWk3betrHebeo7vH.Hg4AofMFZSe12cgyxXrPwz3m', '2026-09-15 21:47:08', 1, 'NASMA ISMAIL', 'jacksonmyula773@gmail.com', '0623693303', 'audit', 1, '', 0, '2026-09-22 19:09:42', NULL, 'active', '2026-09-15 21:47:08', '2026-09-22 19:09:42'),
(46, 'Audit.Dodoma2', '$2y$10$bNO6t6.NymBGd2fAk89tS.ak.4z0RT.KGQ3NlwrIGR99NOm/WXanC', '2026-09-15 22:31:06', 1, 'NYANSAEL NZILU', 'nyansael@gmail.com', '0746526243', 'audit', 1, '', 0, NULL, NULL, 'active', '2026-09-15 22:31:06', '2026-09-15 22:41:25'),
(47, 'Audit.Arusha1', '$2y$10$v7f/O/YkZmWGb.NmvQBvDeKgyOAG544hJ6euTJhRk.iuNmZ53mXlq', '2026-09-15 22:42:42', 1, 'FLORA DANIEL', 'flora@gmail.com', '0746657891', 'audit', 2, '', 0, NULL, NULL, 'active', '2026-09-15 22:42:42', '2026-09-15 22:49:10'),
(48, 'Audit.Arusha2', '$2y$10$wOWeB62L0kHcFZCRko9gN.nSwcFqSCMmyaiSjMcLp2Q6eSERlx93.', '2026-09-15 22:46:31', 1, 'MICHAEL NJIRO', 'michael@gmail.com', '0746652891', 'audit', 2, '', 0, NULL, NULL, 'active', '2026-09-15 22:46:31', '2026-09-15 22:46:31'),
(49, 'Audit.Arusha3', '$2y$10$0Vavj/eEDFa2WoE0UJlXXuyimebTdwZLefYgYSYaFmrC/sndrwfV6', '2026-09-15 22:48:10', 1, 'PETRO EMANUAL', 'petro@gmail.com', '0743426243', 'audit', 2, '', 0, NULL, NULL, 'active', '2026-09-15 22:48:10', '2026-09-15 22:50:01'),
(50, 'FATMA', '$2y$10$CyFX6e.PN8cx/VlKW2jFJOViXUQJ9WYA6pGSADhuA/Ho73fA.miUO', '2026-10-16 12:40:49', 1, 'FATMATA GIG', 'fatma@gmail.com', '071000001', 'audit', 1, '', 1, '2026-10-16 12:56:21', NULL, 'active', '2026-10-16 12:40:49', '2026-10-16 12:56:21');

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
(248, 'VIS-20261021-3316', '2026-10-21 22:11:59', 67, 4, 11, '2026-10-21 19:11:59', 11, 1, 'New Patient', 17, 10000.00, 'completed', '', NULL, NULL, NULL, 'Covid19', 30, 'D-COVID1-984', NULL, NULL, '', 0, NULL, NULL, NULL, '2026-10-21 19:11:59', '2026-11-10 11:44:51', 1, '2026-10-21 19:14:16', 0.00, 0.00, 0.00, 380000.00, 'paid', 500.00, 0.00),
(249, 'VIS-20261026-4361', '2026-10-26 20:59:46', 48, 4, 12, '2026-10-26 17:59:46', 12, 1, 'New Patient', 17, 10000.00, 'completed', 'Sore Throat', NULL, NULL, NULL, 'SAFURA, TYPHOID', 20, 'D-SAFURA-833, D-TYPHOI-507', NULL, NULL, '', 0, NULL, NULL, NULL, '2026-10-26 17:59:46', '2026-10-27 19:51:36', 1, '2026-10-27 19:51:36', 0.00, 0.00, 0.00, 241500.00, 'paid', 0.00, 0.00),
(250, 'VIS-20261027-4374', '2026-10-28 01:05:00', 62, 4, 12, '2026-10-27 22:05:00', 12, 1, 'New Patient', 17, 10000.00, 'completed', '', NULL, NULL, NULL, 'KICHOCHO', 24, 'D-KICHOC-635', NULL, NULL, '', 0, NULL, NULL, NULL, '2026-10-27 22:05:00', '2026-10-27 22:07:45', 1, '2026-10-27 22:07:45', 0.00, 0.00, 0.00, 220000.00, 'paid', 500.00, 0.00),
(251, 'VIS-20261027-9032', '2026-10-28 01:18:47', 57, 4, 11, '2026-10-27 22:18:47', 11, 1, 'New Patient', 17, 10000.00, 'completed', '', NULL, NULL, NULL, 'ANTENCIK 104, KICHOCHO, SAFURA', 22, '13BRT9_BTC8, D-KICHOC-635, D-SAFURA-833', NULL, NULL, '', 0, NULL, NULL, NULL, '2026-10-27 22:18:47', '2026-10-27 22:27:13', 1, '2026-10-27 22:27:13', 0.00, 0.00, 0.00, 115000.00, 'paid', 0.00, 0.00),
(252, 'VIS-20261029-7248', '2026-10-29 23:01:47', 62, 4, 10, '2026-10-29 20:01:47', 10, 1, 'New Patient', 17, 10000.00, 'completed', '', NULL, NULL, NULL, 'ANTENCIK 104', 22, '13BRT9_BTC8', NULL, NULL, '', 0, NULL, NULL, NULL, '2026-10-29 20:01:47', '2026-11-10 11:22:31', 1, '2026-11-10 11:22:31', 0.00, 0.00, 0.00, 110000.00, 'paid', 5000.00, 0.00),
(253, 'VIS-20261110-8781', '2026-11-10 18:44:19', 67, 4, 12, '2026-11-10 15:44:19', 12, 1, 'New Patient', 17, 10000.00, 'prescribed', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', 0, NULL, NULL, NULL, '2026-11-10 15:44:19', '2026-11-10 15:49:22', 0, NULL, 0.00, 0.00, 0.00, 55000.00, 'paid', 0.00, 0.00);

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
(157, 63, NULL, NULL, 10, 1, 34.0, 129, 91, 74, NULL, 109, NULL, 51.00, 153.00, 21.8, NULL, NULL, NULL, '2026-09-21 19:38:39', '2026-09-21 19:38:39', '2026-09-21 19:38:39'),
(158, 62, NULL, NULL, 10, 1, 38.0, 129, 93, 78, NULL, 127, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-21 19:38:57', '2026-09-21 19:38:57', '2026-09-21 19:38:57'),
(159, 61, NULL, NULL, 10, 1, 37.0, 120, 80, 72, NULL, 98, NULL, 65.00, 170.00, 22.5, NULL, NULL, NULL, '2026-09-21 19:39:13', '2026-09-21 19:39:13', '2026-09-21 19:39:13'),
(160, 59, NULL, NULL, 10, 1, 33.0, 123, 89, 69, NULL, NULL, NULL, 100.00, 173.00, 33.4, NULL, NULL, NULL, '2026-09-21 19:39:49', '2026-09-21 19:39:49', '2026-09-21 19:39:49'),
(161, 63, NULL, NULL, 10, 1, 34.0, 129, 91, 74, NULL, 109, NULL, 51.00, 153.00, 21.8, NULL, NULL, NULL, '2026-09-21 21:16:50', '2026-09-21 21:16:50', '2026-09-21 21:16:50'),
(162, 62, NULL, NULL, 10, 1, 38.0, 129, 93, 78, NULL, 127, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-21 21:17:07', '2026-09-21 21:17:07', '2026-09-21 21:17:07'),
(163, 64, NULL, NULL, 23, 2, 39.0, 200, 100, 79, NULL, 100, NULL, 71.00, 174.00, 23.5, NULL, NULL, NULL, '2026-09-21 22:58:19', '2026-09-21 22:58:19', '2026-09-21 22:58:19'),
(164, 64, NULL, NULL, 23, 2, 39.0, 200, 100, 79, NULL, 100, NULL, 71.00, 174.00, 23.5, NULL, NULL, NULL, '2026-09-22 12:09:11', '2026-09-22 12:09:11', '2026-09-22 12:09:11'),
(165, 65, NULL, NULL, 36, 3, 39.0, 124, 78, 80, NULL, 103, NULL, 69.00, 174.00, 22.8, NULL, NULL, NULL, '2026-09-22 22:07:53', '2026-09-22 22:07:53', '2026-09-22 22:07:53'),
(166, 63, NULL, NULL, 11, 1, 34.0, 129, 91, 74, NULL, 109, NULL, 51.00, 153.00, 21.8, NULL, NULL, NULL, '2026-09-24 13:36:09', '2026-09-24 13:36:09', '2026-09-24 13:36:09'),
(167, 62, NULL, NULL, 11, 1, 38.0, 129, 93, 78, NULL, 127, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-24 13:36:27', '2026-09-24 13:36:27', '2026-09-24 13:36:27'),
(168, 61, NULL, NULL, 11, 1, 37.0, 120, 80, 72, NULL, 98, NULL, 65.00, 170.00, 22.5, NULL, NULL, NULL, '2026-09-24 13:36:49', '2026-09-24 13:36:49', '2026-09-24 13:36:49'),
(169, 63, NULL, NULL, 10, 1, 34.0, 129, 91, 74, NULL, 109, NULL, 51.00, 153.00, 21.8, NULL, NULL, NULL, '2026-09-24 14:38:56', '2026-09-24 14:38:56', '2026-09-24 14:38:56'),
(170, 62, NULL, NULL, 10, 1, 38.0, 129, 93, 78, NULL, 127, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-24 14:39:17', '2026-09-24 14:39:17', '2026-09-24 14:39:17'),
(171, 60, NULL, NULL, 11, 1, 39.0, 127, 90, 78, NULL, 99, NULL, 71.00, 176.00, 22.9, NULL, NULL, NULL, '2026-09-24 15:03:10', '2026-09-24 15:03:10', '2026-09-24 15:03:10'),
(172, 63, NULL, NULL, 12, 1, 34.0, 129, 91, 74, NULL, 109, NULL, 51.00, 153.00, 21.8, NULL, NULL, NULL, '2026-09-24 15:36:49', '2026-09-24 15:36:49', '2026-09-24 15:36:49'),
(173, 61, NULL, NULL, 10, 1, 37.0, 120, 80, 72, NULL, 98, NULL, 65.00, 170.00, 22.5, NULL, NULL, NULL, '2026-09-24 18:10:44', '2026-09-24 18:10:44', '2026-09-24 18:10:44'),
(174, 63, NULL, NULL, 10, 1, 34.0, 129, 91, 74, NULL, 109, NULL, 51.00, 153.00, 21.8, NULL, NULL, NULL, '2026-09-29 11:49:33', '2026-09-29 11:49:33', '2026-09-29 11:49:33'),
(175, 62, NULL, NULL, 10, 1, 38.0, 129, 93, 78, NULL, 127, NULL, 80.00, 178.00, 25.2, NULL, NULL, NULL, '2026-09-29 11:49:59', '2026-09-29 11:49:59', '2026-09-29 11:49:59'),
(176, 61, NULL, NULL, 10, 1, 37.0, 120, 80, 72, NULL, 98, NULL, 65.00, 170.00, 22.5, NULL, NULL, NULL, '2026-09-29 15:11:44', '2026-09-29 15:11:44', '2026-09-29 15:11:44'),
(177, 63, NULL, NULL, 10, 1, 34.0, 129, 91, 74, NULL, 109, NULL, 51.00, 153.00, 21.8, NULL, NULL, NULL, '2026-10-01 11:55:37', '2026-10-01 11:55:37', '2026-10-01 11:55:37'),
(179, 67, NULL, NULL, 10, 1, 37.0, 129, 89, 78, NULL, 100, NULL, 77.00, 178.00, 24.3, NULL, NULL, NULL, '2026-10-16 11:36:48', '2026-10-16 11:36:48', '2026-10-16 11:36:48'),
(182, 48, 249, NULL, 12, 1, 39.0, 122, 90, 78, NULL, 112, NULL, 78.00, 175.00, 25.5, NULL, NULL, NULL, '2026-10-26 17:59:46', '2026-10-26 17:59:46', '2026-10-26 17:59:46'),
(183, 67, 253, NULL, 12, 1, 37.0, 129, 89, 78, NULL, 100, NULL, 77.00, 178.00, 24.3, NULL, NULL, NULL, '2026-11-10 15:44:19', '2026-11-10 15:44:19', '2026-11-10 15:44:19');

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
-- Indexes for table `daily_activities`
--
ALTER TABLE `daily_activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `activity_date` (`activity_date`),
  ADD KEY `idx_user_date` (`user_id`,`activity_date`),
  ADD KEY `idx_date_time` (`activity_date`,`start_time`,`end_time`),
  ADD KEY `daily_activities_ibfk_3` (`patient_id`),
  ADD KEY `daily_activities_ibfk_4` (`visit_id`);

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
  ADD KEY `idx_prescription_items_prescription` (`prescription_id`),
  ADD KEY `fk_prescription_items_confirmed_by` (`confirmed_by`),
  ADD KEY `fk_prescription_items_cancelled_by` (`cancelled_by`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2189;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `bills`
--
ALTER TABLE `bills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=422;

--
-- AUTO_INCREMENT for table `bill_items`
--
ALTER TABLE `bill_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1611;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `daily_activities`
--
ALTER TABLE `daily_activities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `diseases`
--
ALTER TABLE `diseases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `employee_departments`
--
ALTER TABLE `employee_departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `employee_roles`
--
ALTER TABLE `employee_roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=382;

--
-- AUTO_INCREMENT for table `lab_tests_catalog`
--
ALTER TABLE `lab_tests_catalog`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT for table `lab_test_equipment`
--
ALTER TABLE `lab_test_equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `medical_equipment`
--
ALTER TABLE `medical_equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `medications_inventory`
--
ALTER TABLE `medications_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=126;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=324;

--
-- AUTO_INCREMENT for table `otc_sales`
--
ALTER TABLE `otc_sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `otc_sale_items`
--
ALTER TABLE `otc_sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT for table `patient_documents`
--
ALTER TABLE `patient_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=193;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=329;

--
-- AUTO_INCREMENT for table `prescription_items`
--
ALTER TABLE `prescription_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=335;

--
-- AUTO_INCREMENT for table `procedures`
--
ALTER TABLE `procedures`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=321;

--
-- AUTO_INCREMENT for table `procedures_catalog`
--
ALTER TABLE `procedures_catalog`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `purchases`
--
ALTER TABLE `purchases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `purchase_items`
--
ALTER TABLE `purchase_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `receipts`
--
ALTER TABLE `receipts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `referrals`
--
ALTER TABLE `referrals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=391;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `visits`
--
ALTER TABLE `visits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=254;

--
-- AUTO_INCREMENT for table `vital_signs`
--
ALTER TABLE `vital_signs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=184;

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
-- Constraints for table `daily_activities`
--
ALTER TABLE `daily_activities`
  ADD CONSTRAINT `daily_activities_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `daily_activities_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `daily_activities_ibfk_3` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `daily_activities_ibfk_4` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE SET NULL;

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
  ADD CONSTRAINT `fk_prescription_items_cancelled_by` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_prescription_items_confirmed_by` FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
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
