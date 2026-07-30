-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 29, 2026 at 03:07 PM
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

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `calculate_visit_total` (IN `visit_id_param` INT)   BEGIN
    DECLARE reg_fee DECIMAL(10,2) DEFAULT 0;
    DECLARE consult_fee DECIMAL(10,2) DEFAULT 0;
    DECLARE lab_fees DECIMAL(10,2) DEFAULT 0;
    DECLARE pharm_fees DECIMAL(10,2) DEFAULT 0;
    DECLARE other_fees DECIMAL(10,2) DEFAULT 0;
    DECLARE total DECIMAL(10,2) DEFAULT 0;
    DECLARE discount DECIMAL(10,2) DEFAULT 0;
    DECLARE discount_percent DECIMAL(5,2) DEFAULT 0;
    
    -- Get visit fees and discount
    SELECT 
        registration_fee,
        consultation_fee,
        lab_fees_total,
        pharmacy_fees_total,
        other_fees_total,
        discount_percent
    INTO reg_fee, consult_fee, lab_fees, pharm_fees, other_fees, discount_percent
    FROM visits 
    WHERE id = visit_id_param;
    
    -- Calculate total
    SET total = IFNULL(reg_fee, 0) + IFNULL(consult_fee, 0) + IFNULL(lab_fees, 0) + IFNULL(pharm_fees, 0) + IFNULL(other_fees, 0);
    
    -- Calculate discount amount
    SET discount = (total * IFNULL(discount_percent, 0)) / 100;
    
    -- Update visit total and discount
    UPDATE visits 
    SET 
        visit_total = total,
        total_discount = discount
    WHERE id = visit_id_param;
    
    SELECT total as subtotal, discount as discount_amount, (total - discount) as grand_total;
END$$

--
-- Functions
--
CREATE DEFINER=`root`@`localhost` FUNCTION `generate_service_code` (`p_category` VARCHAR(20), `p_branch_id` INT) RETURNS VARCHAR(20) CHARSET utf8mb4 COLLATE utf8mb4_general_ci DETERMINISTIC BEGIN
            DECLARE prefix VARCHAR(10);
            DECLARE next_number INT;
            DECLARE new_code VARCHAR(20);
            DECLARE category_prefix VARCHAR(10);
            
            CASE p_category
                WHEN 'registration' THEN SET category_prefix = 'REG';
                WHEN 'consultation' THEN SET category_prefix = 'CONS';
                WHEN 'lab_test' THEN SET category_prefix = 'LAB';
                WHEN 'medication' THEN SET category_prefix = 'MED';
                WHEN 'procedure' THEN SET category_prefix = 'PROC';
                WHEN 'other' THEN SET category_prefix = 'OTHR';
                ELSE SET category_prefix = 'SVC';
            END CASE;
            
            SET prefix = CONCAT(category_prefix, '-', LPAD(p_branch_id, 2, '0'));
            
            SELECT IFNULL(MAX(CAST(SUBSTRING_INDEX(service_code, '-', -1) AS UNSIGNED)), 0) + 1
            INTO next_number
            FROM service_fees
            WHERE service_code LIKE CONCAT(prefix, '-%');
            
            IF next_number IS NULL OR next_number = 0 THEN
                SET next_number = 1;
            END IF;
            
            SET new_code = CONCAT(prefix, '-', LPAD(next_number, 3, '0'));
            
            RETURN new_code;
        END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `branch_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(227, 6, NULL, 'patient_registered', 'New patient registered: ADAM CLISTOPHER (ID: P-2026-0008) in Dodoma - Visit: VIS-20260727-0063 (No bill created)', NULL, NULL, '2026-07-27 14:31:39'),
(228, 6, NULL, 'patient_registered', 'New patient registered: KELVIN P. KIMARO (ID: P-2026-0009) in Dodoma - Visit: VIS-20260727-0064 (No bill created)', NULL, NULL, '2026-07-27 17:44:55'),
(229, 6, NULL, 'patient_registered', 'New patient registered: MUSSA MONGI (ID: P-2026-0010) in Dodoma - Visit: VIS-20260727-0065 (No bill created)', NULL, NULL, '2026-07-27 18:19:37'),
(230, 6, NULL, 'patient_registered', 'New patient registered: MSAFIR JUMA (ID: P-2026-0011) in Dodoma - Visit: VIS-20260729-0066 (No bill created)', NULL, NULL, '2026-07-29 08:54:04'),
(231, 5, NULL, 'doctor_status_changed', 'Dr. Dr. John Mushi changed status to: offline', NULL, NULL, '2026-07-29 09:23:04'),
(232, 5, NULL, 'doctor_status_changed', 'Dr. Dr. John Mushi changed status to: online', NULL, NULL, '2026-07-29 09:32:35'),
(233, 5, NULL, 'doctor_status_changed', 'Dr. Dr. John Mushi changed status to: offline', NULL, NULL, '2026-07-29 09:42:28'),
(234, 5, NULL, 'doctor_status_changed', 'Dr. Dr. John Mushi changed status to: online', NULL, NULL, '2026-07-29 09:42:35'),
(235, 6, NULL, 'patient_registered_with_doctor', 'New patient registered and assigned to doctor ID #5: MUSSA WAMBURA (ID: P-2026-0012) - Visit: VIS-20260729-0067', NULL, NULL, '2026-07-29 09:43:50'),
(236, 5, NULL, 'consultation_auto_completed', 'Consultation #VIS-20260729-0067 auto-completed', NULL, NULL, '2026-07-29 09:51:09'),
(237, 9, NULL, 'prescription_confirmed', 'Prescription #PRES-20260729-0067-636 confirmed - Bill #BILL-PRES-20260729-0067 sent to Cashier', NULL, NULL, '2026-07-29 10:00:12'),
(238, 9, NULL, 'prescription_confirmed', 'Prescription #PRES-20260729-0066-755 confirmed - Bill #BILL-PRES-20260729-0066 sent to Cashier', NULL, NULL, '2026-07-29 10:44:09'),
(239, 5, NULL, 'prescription_confirmed', 'Prescription #PRES-20260729-0055-923 confirmed - Status: PENDING → CONFIRMED | Bill #BILL-PRES-20260729-0055 sent to Cashier | Branch: Dodoma', NULL, NULL, '2026-07-29 10:57:54'),
(240, 6, NULL, 'patient_registered_with_doctor', 'New patient registered and assigned to doctor ID #5: KELVIN P. NASHON (ID: P-2026-0013) - Visit: VIS-20260729-0068', NULL, NULL, '2026-07-29 11:03:36'),
(241, 9, NULL, 'prescription_confirmed', 'Prescription #PRES-20260729-0068-831 confirmed - Status: PENDING → CONFIRMED | Bill #BILL-PRES-20260729-0068 sent to Cashier | Branch: Dodoma', NULL, NULL, '2026-07-29 11:04:34'),
(242, 9, NULL, 'prescription_auto_dispensed', 'Prescription #PRES-20260729-0055-923 auto-dispensed after payment (Bill: BILL-PRES-20260729-0055)', NULL, NULL, '2026-07-29 11:12:15'),
(243, 9, NULL, 'prescription_auto_dispensed', 'Prescription #PRES-20260729-0068-831 auto-dispensed after payment (Bill: BILL-PRES-20260729-0068)', NULL, NULL, '2026-07-29 11:12:15'),
(244, 9, NULL, 'prescription_auto_dispensed', 'Prescription #PRES-20260729-0066-755 auto-dispensed after payment (Bill: BILL-PRES-20260729-0066)', NULL, NULL, '2026-07-29 11:14:36');

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `visit_id` int(11) DEFAULT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_date` datetime NOT NULL,
  `purpose` text DEFAULT NULL,
  `visit_type` enum('new','follow-up','emergency') DEFAULT 'new',
  `status` enum('scheduled','confirmed','completed','cancelled') DEFAULT 'scheduled',
  `notes` text DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bill_items`
--

CREATE TABLE `bill_items` (
  `id` int(11) NOT NULL,
  `bill_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `item_type` enum('registration','consultation','lab_test','medication','procedure','tool','other') NOT NULL DEFAULT 'other',
  `item_id` int(11) DEFAULT NULL,
  `item_name` varchar(100) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `payment_status` enum('pending','paid','partial','cancelled') DEFAULT 'pending',
  `is_paid` tinyint(1) DEFAULT 0,
  `status` varchar(20) DEFAULT 'pending',
  `paid_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `amount` decimal(10,2) DEFAULT 0.00,
  `description` varchar(255) DEFAULT NULL,
  `department` varchar(50) DEFAULT NULL,
  `service_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bill_items`
--

INSERT INTO `bill_items` (`id`, `bill_id`, `branch_id`, `item_type`, `item_id`, `item_name`, `quantity`, `unit_price`, `total_price`, `payment_status`, `is_paid`, `status`, `paid_at`, `created_at`, `amount`, `description`, `department`, `service_type`, `reference_id`, `updated_at`) VALUES
(394, 115, 1, 'consultation', NULL, 'Consultation (New Patient)', 1, 15000.00, 15000.00, 'paid', 1, 'pending', '2026-07-27 12:10:50', '2026-07-27 08:49:55', 0.00, NULL, NULL, NULL, NULL, NULL),
(395, 115, 1, 'lab_test', NULL, 'Renal Function Test (RFT)', 1, 20000.00, 20000.00, 'paid', 1, 'pending', '2026-07-27 12:10:50', '2026-07-27 08:51:14', 0.00, NULL, NULL, NULL, NULL, NULL),
(396, 115, 1, 'lab_test', NULL, 'Hepatitis B Surface Antigen (HBsAg)', 1, 15000.00, 15000.00, 'paid', 1, 'pending', '2026-07-27 12:10:50', '2026-07-27 08:51:17', 0.00, NULL, NULL, NULL, NULL, NULL),
(397, 115, 1, 'lab_test', NULL, 'Pregnancy Test (Blood - Beta HCG)', 1, 20000.00, 20000.00, 'paid', 1, 'pending', '2026-07-27 12:10:50', '2026-07-27 08:51:19', 0.00, NULL, NULL, NULL, NULL, NULL),
(398, 115, 1, 'procedure', NULL, 'Biopsy', 1, 40000.00, 40000.00, 'paid', 1, 'pending', '2026-07-27 12:10:50', '2026-07-27 08:52:52', 0.00, NULL, NULL, NULL, NULL, NULL),
(399, 115, 1, 'tool', NULL, 'Biopsy - Local Anesthetic', 10, 2500.00, 25000.00, 'paid', 1, 'pending', '2026-07-27 12:10:50', '2026-07-27 08:52:53', 0.00, NULL, NULL, NULL, NULL, NULL),
(400, 116, 1, 'medication', NULL, 'Amlodipine 5mg', 1, 450.00, 450.00, 'paid', 1, 'pending', '2026-07-27 13:45:27', '2026-07-27 10:44:58', 0.00, NULL, NULL, NULL, NULL, NULL),
(402, 118, 1, 'medication', NULL, 'Amoxicillin 250mg', 10, 800.00, 8000.00, 'paid', 1, 'pending', '2026-07-27 14:12:27', '2026-07-27 11:11:54', 0.00, NULL, NULL, NULL, NULL, NULL),
(403, 119, 1, 'consultation', NULL, 'Consultation (New Patient)', 1, 15000.00, 15000.00, 'paid', 1, 'pending', '2026-07-27 15:51:26', '2026-07-27 12:47:03', 0.00, NULL, NULL, NULL, NULL, NULL),
(404, 119, 1, 'lab_test', NULL, 'Renal Function Test (RFT)', 1, 20000.00, 20000.00, 'paid', 1, 'pending', '2026-07-27 15:51:26', '2026-07-27 12:48:57', 0.00, NULL, NULL, NULL, NULL, NULL),
(405, 120, 1, 'medication', NULL, 'Amoxicillin 250mg', 30, 800.00, 24000.00, 'paid', 1, 'pending', '2026-07-27 15:55:06', '2026-07-27 12:52:40', 0.00, NULL, NULL, NULL, NULL, NULL),
(406, 119, 1, 'tool', NULL, 'Biopsy - Biopsy Punch', 1, 4000.00, 4000.00, 'paid', 1, 'pending', '2026-07-27 15:55:34', '2026-07-27 12:54:20', 0.00, NULL, NULL, NULL, NULL, NULL),
(407, 119, 1, 'procedure', NULL, 'Chest Tube Insertion', 1, 50000.00, 50000.00, 'paid', 1, 'pending', '2026-07-27 15:55:34', '2026-07-27 12:54:20', 0.00, NULL, NULL, NULL, NULL, NULL),
(408, 119, 1, 'tool', NULL, 'Biopsy - Local Anesthetic', 10, 2500.00, 25000.00, 'paid', 1, 'pending', '2026-07-27 15:55:34', '2026-07-27 12:54:27', 0.00, NULL, NULL, NULL, NULL, NULL),
(409, 121, 1, 'medication', NULL, 'Amoxicillin 250mg', 30, 800.00, 24000.00, 'paid', 1, 'pending', '2026-07-27 16:00:08', '2026-07-27 12:59:50', 0.00, NULL, NULL, NULL, NULL, NULL),
(410, 122, 1, 'medication', NULL, 'Amoxicillin 250mg', 10, 800.00, 8000.00, 'paid', 1, 'pending', '2026-07-27 16:06:39', '2026-07-27 13:06:15', 0.00, NULL, NULL, NULL, NULL, NULL),
(411, 123, 1, 'medication', NULL, 'ALBENDERZOL', 10, 1500.00, 15000.00, 'paid', 1, 'pending', '2026-07-27 16:08:34', '2026-07-27 13:08:14', 0.00, NULL, NULL, NULL, NULL, NULL),
(412, 123, 1, 'medication', NULL, 'Amlodipine 5mg', 1, 450.00, 450.00, 'paid', 1, 'pending', '2026-07-27 16:08:34', '2026-07-27 13:08:14', 0.00, NULL, NULL, NULL, NULL, NULL),
(413, 123, 1, 'medication', NULL, 'Amlodipine 5mg', 1, 450.00, 450.00, 'paid', 1, 'pending', '2026-07-27 16:08:34', '2026-07-27 13:08:14', 0.00, NULL, NULL, NULL, NULL, NULL),
(414, 124, 1, 'consultation', NULL, 'Consultation (New Patient)', 1, 15000.00, 15000.00, 'pending', 0, 'pending', NULL, '2026-07-27 13:11:30', 0.00, NULL, NULL, NULL, NULL, NULL),
(415, 124, NULL, 'lab_test', NULL, 'Ultrasound - Abdomen & Pelvis', 1, 65000.00, 65000.00, 'pending', 0, 'pending', NULL, '2026-07-27 13:37:45', 0.00, NULL, NULL, NULL, NULL, NULL),
(416, 125, NULL, 'consultation', NULL, 'Consultation (New Patient)', 1, 15000.00, 15000.00, 'pending', 0, 'pending', NULL, '2026-07-27 14:31:59', 0.00, NULL, NULL, NULL, NULL, NULL),
(417, 125, NULL, 'lab_test', NULL, 'Ultrasound - 3D/4D Obstetric', 1, 80000.00, 80000.00, 'pending', 0, 'pending', NULL, '2026-07-27 14:32:41', 0.00, NULL, NULL, NULL, NULL, NULL),
(418, 125, NULL, 'lab_test', NULL, 'HIV ELISA', 1, 25000.00, 25000.00, 'pending', 0, 'pending', NULL, '2026-07-27 14:32:44', 0.00, NULL, NULL, NULL, NULL, NULL),
(419, 126, NULL, 'consultation', NULL, 'Consultation (New Patient)', 1, 15000.00, 15000.00, 'pending', 0, 'pending', NULL, '2026-07-27 14:41:34', 0.00, NULL, NULL, NULL, NULL, NULL),
(420, 126, NULL, 'lab_test', NULL, 'Ultrasound - Scrotal', 1, 40000.00, 40000.00, 'pending', 0, 'pending', NULL, '2026-07-27 14:42:01', 0.00, NULL, NULL, NULL, NULL, NULL),
(421, 127, NULL, 'lab_test', NULL, 'Blood Glucose (Random)', 1, 8000.00, 8000.00, 'pending', 0, 'pending', NULL, '2026-07-27 17:45:26', 0.00, NULL, NULL, NULL, NULL, NULL),
(422, 128, NULL, 'lab_test', NULL, 'Blood Glucose (Random)', 1, 8000.00, 8000.00, 'pending', 0, 'pending', NULL, '2026-07-27 18:20:07', 0.00, NULL, NULL, NULL, NULL, NULL),
(423, 129, NULL, 'consultation', NULL, 'Consultation (New patient)', 1, 15000.00, 15000.00, 'pending', 0, 'pending', NULL, '2026-07-29 09:17:26', 0.00, NULL, NULL, NULL, NULL, NULL),
(424, 130, NULL, 'consultation', NULL, 'Consultation (New patient)', 1, 15000.00, 15000.00, 'paid', 1, 'pending', '2026-07-29 12:51:00', '2026-07-29 09:43:50', 0.00, NULL, NULL, NULL, NULL, NULL),
(425, 130, NULL, 'lab_test', NULL, 'Blood Glucose (Fasting)', 1, 8000.00, 8000.00, 'paid', 1, 'pending', '2026-07-29 12:51:00', '2026-07-29 09:46:25', 0.00, NULL, NULL, NULL, NULL, NULL),
(426, 130, NULL, 'lab_test', NULL, 'Ultrasound - Thyroid', 1, 40000.00, 40000.00, 'paid', 1, 'pending', '2026-07-29 12:51:00', '2026-07-29 09:46:28', 0.00, NULL, NULL, NULL, NULL, NULL),
(427, 130, NULL, 'procedure', NULL, 'Cauterization', 1, 30000.00, 30000.00, 'paid', 1, 'pending', '2026-07-29 12:51:00', '2026-07-29 09:49:41', 0.00, NULL, NULL, NULL, NULL, NULL),
(428, 130, NULL, 'tool', NULL, 'Biopsy - Biopsy Punch', 1, 4000.00, 4000.00, 'paid', 1, 'pending', '2026-07-29 12:51:00', '2026-07-29 09:49:41', 0.00, NULL, NULL, NULL, NULL, NULL),
(429, 130, NULL, 'tool', NULL, 'Biopsy - Formalin Jar', 10, 500.00, 5000.00, 'paid', 1, 'pending', '2026-07-29 12:51:00', '2026-07-29 09:49:50', 0.00, NULL, NULL, NULL, NULL, NULL),
(430, 131, 1, 'medication', NULL, 'ALBENDERZOL', 10, 4000.00, 40000.00, 'paid', 1, 'pending', '2026-07-29 13:01:42', '2026-07-29 10:00:12', 0.00, NULL, NULL, NULL, NULL, NULL),
(431, 132, 1, 'medication', NULL, 'ALBENDERZOL', 20, 4000.00, 80000.00, 'paid', 1, 'pending', '2026-07-29 14:14:24', '2026-07-29 10:44:09', 0.00, NULL, NULL, NULL, NULL, NULL),
(432, 138, 1, 'medication', NULL, 'ALBENDERZOL', 10, 4000.00, 40000.00, 'paid', 1, 'pending', '2026-07-29 14:12:09', '2026-07-29 10:57:54', 0.00, NULL, NULL, NULL, NULL, NULL),
(433, 139, NULL, 'consultation', NULL, 'Consultation (New patient)', 1, 15000.00, 15000.00, 'paid', 1, 'pending', '2026-07-29 14:11:55', '2026-07-29 11:03:36', 0.00, NULL, NULL, NULL, NULL, NULL),
(434, 140, 1, 'medication', NULL, 'MSETO', 1, 3500.00, 3500.00, 'paid', 1, 'pending', '2026-07-29 14:11:41', '2026-07-29 11:04:34', 0.00, NULL, NULL, NULL, NULL, NULL);

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
(1, 'Dodoma', 'Dodoma City, Tanzania', '+255 700 000 001', 'dodoma@braick.com', NULL, 'active', '2026-07-16 11:29:23', '2026-07-16 11:29:23'),
(2, 'Arusha', 'Arusha City, Tanzania', '+255 700 000 002', 'arusha@braick.com', NULL, 'active', '2026-07-16 11:29:23', '2026-07-16 11:29:23'),
(3, 'Dar es Salaam', 'Dar es Salaam, Tanzania', '+255 700 000 003', 'dar@braick.com', NULL, 'active', '2026-07-16 11:29:23', '2026-07-16 11:29:23');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `head_of_department` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `description`, `head_of_department`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Medical Department', 'Handles all medical consultations and treatments', NULL, 'active', '2026-07-16 11:37:31', '2026-07-16 11:37:31'),
(2, 'Laboratory Department', 'Handles all laboratory tests and results', NULL, 'active', '2026-07-16 11:37:31', '2026-07-16 11:37:31'),
(3, 'Pharmacy Department', 'Handles medicine dispensing and inventory', NULL, 'active', '2026-07-16 11:37:31', '2026-07-16 11:37:31'),
(4, 'Reception Department', 'Handles patient registration and appointments', NULL, 'active', '2026-07-16 11:37:31', '2026-07-16 11:37:31'),
(5, 'Finance Department', 'Handles billing, payments and financial records', NULL, 'active', '2026-07-16 11:37:31', '2026-07-16 11:37:31'),
(6, 'Administration Department', 'Handles administrative tasks and management', NULL, 'active', '2026-07-16 11:37:31', '2026-07-16 11:37:31'),
(7, 'IT Department', 'Handles system maintenance and support', NULL, 'active', '2026-07-16 11:37:31', '2026-07-16 11:37:31');

-- --------------------------------------------------------

--
-- Table structure for table `doctor_status`
--

CREATE TABLE `doctor_status` (
  `id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `is_online` tinyint(1) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_departments`
--

CREATE TABLE `employee_departments` (
  `user_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_roles`
--

CREATE TABLE `employee_roles` (
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_categories`
--

CREATE TABLE `inventory_categories` (
  `id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `category_code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lab_billing_items`
--

CREATE TABLE `lab_billing_items` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `bill_id` int(11) NOT NULL,
  `test_id` int(11) NOT NULL,
  `test_name` varchar(100) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lab_requests`
--

CREATE TABLE `lab_requests` (
  `id` int(11) NOT NULL,
  `request_number` varchar(50) NOT NULL,
  `visit_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `lab_technician_id` int(11) DEFAULT NULL,
  `status` enum('pending','accepted','in_progress','completed','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `results` text DEFAULT NULL,
  `reference_range` varchar(255) DEFAULT NULL,
  `interpretation` text DEFAULT NULL,
  `result_added_by` int(11) DEFAULT NULL,
  `result_added_at` datetime DEFAULT NULL,
  `lab_total` decimal(10,2) DEFAULT 0.00,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `accepted_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lab_requests`
--

INSERT INTO `lab_requests` (`id`, `request_number`, `visit_id`, `patient_id`, `doctor_id`, `lab_technician_id`, `status`, `notes`, `results`, `reference_range`, `interpretation`, `result_added_by`, `result_added_at`, `lab_total`, `requested_at`, `created_at`, `accepted_at`, `completed_at`, `cancelled_at`, `created_by`, `branch_id`, `updated_at`) VALUES
(13, 'LAB-20260727-9300', 97, 64, NULL, 8, 'completed', '', NULL, NULL, NULL, NULL, NULL, 0.00, '2026-07-27 17:45:12', '2026-07-27 17:45:12', '2026-07-27 17:45:26', '2026-07-27 18:18:32', NULL, 6, 1, '2026-07-27 18:18:32'),
(14, 'LAB-20260727-4101', 98, 65, NULL, 8, 'completed', '', NULL, NULL, NULL, NULL, NULL, 0.00, '2026-07-27 18:19:57', '2026-07-27 18:19:57', '2026-07-27 18:20:07', '2026-07-27 18:20:27', NULL, 6, 1, '2026-07-27 18:20:27');

-- --------------------------------------------------------

--
-- Table structure for table `lab_request_items`
--

CREATE TABLE `lab_request_items` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `test_id` int(11) NOT NULL,
  `test_name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `result` text DEFAULT NULL,
  `reference_range` varchar(100) DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `performed_by` int(11) DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lab_request_items`
--

INSERT INTO `lab_request_items` (`id`, `request_id`, `test_id`, `test_name`, `price`, `status`, `result`, `reference_range`, `comments`, `performed_by`, `completed_at`, `created_at`, `updated_at`) VALUES
(98, 13, 3, 'Blood Glucose (Random)', 8000.00, 'pending', NULL, NULL, NULL, NULL, NULL, '2026-07-27 17:45:12', '2026-07-27 17:45:12'),
(99, 14, 3, 'Blood Glucose (Random)', 8000.00, 'pending', NULL, NULL, NULL, NULL, NULL, '2026-07-27 18:19:57', '2026-07-27 18:19:57');

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
(5, 'Obstetric Ultrasound (Twin - 2/3 Trimester)', 'Obstetric Ultrasound - Twin', 'ultrasound', '<div class=\"ultrasound-report\">\r\n    <div class=\"report-header\">\r\n        <div style=\"display:flex;align-items:center;justify-content:center;gap:15px;margin-bottom:10px;\">\r\n            <img src=\"/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png\" alt=\"Braick Dispensary\" style=\"height:60px;width:auto;max-height:60px;\" onerror=\"this.style.display=\'none\'\">\r\n            <div>\r\n                <h2 style=\"color:#0B5ED7;font-size:22px;margin:0;\">BRAICK DISPENSARY</h2>\r\n                <p style=\"font-size:12px;color:#666;margin:0;\">Quality Healthcare Services</p>\r\n            </div>\r\n        </div>\r\n        <h3 style=\"font-size:16px;color:#333;margin:0;\">ULTRASOUND REPORT – ABDOMEN AND PELVIS</h3>\r\n    </div>\r\n    \r\n    <div class=\"patient-info\">\r\n        <p><strong>Patient Name:</strong> {patient_name}</p>\r\n        <p><strong>Age/Sex:</strong> {age} yrs / {gender}</p>\r\n        <p><strong>Date of Exam:</strong> {exam_date}</p>\r\n    </div>\r\n    \r\n    <div class=\"findings\">\r\n        <h4>FINDINGS</h4>\r\n        <p><strong>Liver:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"liver\" placeholder=\"e.g. Appeared normal in size, shape, homogeneous echo pattern\"></p>\r\n        <p><strong>Gallbladder:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"gallbladder\" placeholder=\"e.g. Appears normal, well distended, no stones\"></p>\r\n        <p><strong>Pancreas:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"pancreas\" placeholder=\"e.g. Appeared normal in size and shape\"></p>\r\n        <p><strong>Spleen:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"spleen\" placeholder=\"e.g. Appeared normal in size, shape and echotexture\"></p>\r\n        <p><strong>Peritoneum:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"peritoneum\" placeholder=\"e.g. No free fluid noted\"></p>\r\n        <p><strong>Kidneys:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"kidneys\" placeholder=\"e.g. Both kidneys normal\"></p>\r\n        <p><strong>Urinary Bladder:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"bladder\" placeholder=\"e.g. Appears normal, well-distended\"></p>\r\n        <p><strong>Uterus:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"uterus\" placeholder=\"e.g. Appears normal in size and homogeneous echo pattern\"></p>\r\n        <p><strong>Right Ovary:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"right_ovary\" placeholder=\"e.g. Appears normal in size and appearance\"></p>\r\n        <p><strong>Left Ovary:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"left_ovary\" placeholder=\"e.g. Appears normal in size and appearance\"></p>\r\n        <p><strong>Pouch of Douglas:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"pouch_douglas\" placeholder=\"e.g. Free fluid seen\"></p>\r\n    </div>\r\n    \r\n    <div class=\"conclusion\">\r\n        <h4>IMPRESSION</h4>\r\n        <textarea class=\"form-control conclusion-field\" rows=\"2\" placeholder=\"Enter impression...\"></textarea>\r\n    </div>\r\n    \r\n    <div class=\"report-footer\">\r\n        <span>Technician: <input type=\"text\" class=\"form-control\" style=\"display:inline-block;width:auto;border:none;border-bottom:1px solid #ddd;padding:0 8px;\" placeholder=\"Technician Name\"></span>\r\n        <span>Date: {report_date}</span>\r\n    </div>\r\n</div>', 1, '2026-07-27 14:20:54', '2026-07-27 15:01:08'),
(6, 'Obstetric Ultrasound (Single - 2/3 Trimester)', 'Obstetric Ultrasound - Single', 'ultrasound', '<div class=\"ultrasound-report\">\r\n    <div class=\"report-header\">\r\n        <div style=\"display:flex;align-items:center;justify-content:center;gap:15px;margin-bottom:10px;\">\r\n            <img src=\"/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png\" alt=\"Braick Dispensary\" style=\"height:60px;width:auto;max-height:60px;\" onerror=\"this.style.display=\'none\'\">\r\n            <div>\r\n                <h2 style=\"color:#0B5ED7;font-size:22px;margin:0;\">BRAICK DISPENSARY</h2>\r\n                <p style=\"font-size:12px;color:#666;margin:0;\">Quality Healthcare Services</p>\r\n            </div>\r\n        </div>\r\n        <h3 style=\"font-size:16px;color:#333;margin:0;\">OBSTETRIC ULTRASOUND REPORT</h3>\r\n    </div>\r\n    \r\n    <div class=\"patient-info\">\r\n        <p><strong>Patient Name:</strong> {patient_name}</p>\r\n        <p><strong>Age/Sex:</strong> {age} yrs / {gender}</p>\r\n        <p><strong>Date of Exam:</strong> {exam_date}</p>\r\n    </div>\r\n    \r\n    <div class=\"findings\">\r\n        <h4>FINDINGS</h4>\r\n        <p><strong>Presentation and Lie:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"presentation\" placeholder=\"e.g. single viable intrauterine fetus, in cephalic presentation\" style=\"width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;\"></p>\r\n        <p><strong>Placenta:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"placenta\" placeholder=\"e.g. placenta is posterior, placenta calcification\" style=\"width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;\"></p>\r\n        <p><strong>Fetal Activity:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"fetal_activity\" placeholder=\"e.g. seen\" style=\"width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;\"></p>\r\n        <p><strong>Amniotic Fluid:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"amniotic_fluid\" placeholder=\"e.g. adequate\" style=\"width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;\"></p>\r\n        <p><strong>Anatomical Structures:</strong> <textarea class=\"form-control placeholder-field\" data-placeholder=\"anatomical_structures\" rows=\"2\" placeholder=\"Describe anatomical structures...\" style=\"width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;\"></textarea></p>\r\n        <p><strong>Maternal Kidney:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"maternal_kidney\" placeholder=\"e.g. appeared normal\" style=\"width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;\"></p>\r\n    </div>\r\n    \r\n    <div class=\"biometry\">\r\n        <h4>BIOMETRY</h4>\r\n        <div style=\"overflow-x:auto;\">\r\n            <table>\r\n                <thead>\r\n                    <tr>\r\n                        <th>Parameter</th>\r\n                        <th>Measurement</th>\r\n                    </tr>\r\n                </thead>\r\n                <tbody>\r\n                    <tr><td><strong>BPD</strong></td><td><input type=\"text\" class=\"form-control table-field\" placeholder=\"mm\" style=\"width:150px;\"></td></tr>\r\n                    <tr><td><strong>HC</strong></td><td><input type=\"text\" class=\"form-control table-field\" placeholder=\"mm\" style=\"width:150px;\"></td></tr>\r\n                    <tr><td><strong>AC</strong></td><td><input type=\"text\" class=\"form-control table-field\" placeholder=\"mm\" style=\"width:150px;\"></td></tr>\r\n                    <tr><td><strong>FL</strong></td><td><input type=\"text\" class=\"form-control table-field\" placeholder=\"mm\" style=\"width:150px;\"></td></tr>\r\n                    <tr><td><strong>GA</strong></td><td><input type=\"text\" class=\"form-control table-field\" placeholder=\"e.g. 39W+3D\" style=\"width:150px;\"></td></tr>\r\n                    <tr><td><strong>EDD</strong></td><td><input type=\"text\" class=\"form-control table-field\" placeholder=\"DD/MM/YYYY\" style=\"width:150px;\"></td></tr>\r\n                </tbody>\r\n            </table>\r\n        </div>\r\n    </div>\r\n    \r\n    <div class=\"conclusion\">\r\n        <h4>CONCLUSION</h4>\r\n        <textarea class=\"form-control conclusion-field\" rows=\"2\" placeholder=\"Enter conclusion...\" style=\"width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;\"></textarea>\r\n    </div>\r\n    \r\n    <div class=\"report-footer\">\r\n        <span>Technician: <input type=\"text\" class=\"form-control\" style=\"display:inline-block;width:auto;border:none;border-bottom:1px solid #ddd;padding:0 8px;\" placeholder=\"Technician Name\"></span>\r\n        <span>Date: {report_date}</span>\r\n    </div>\r\n</div>', 1, '2026-07-27 14:20:54', '2026-07-27 15:01:08'),
(7, 'Obstetric Ultrasound (Early Pregnancy)', 'Obstetric Ultrasound - Early', 'ultrasound', '<div class=\"ultrasound-report\">\r\n    <div class=\"report-header\">\r\n        <div style=\"display:flex;align-items:center;justify-content:center;gap:15px;margin-bottom:10px;\">\r\n            <img src=\"/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png\" alt=\"Braick Dispensary\" style=\"height:60px;width:auto;max-height:60px;\" onerror=\"this.style.display=\'none\'\">\r\n            <div>\r\n                <h2 style=\"color:#0B5ED7;font-size:22px;margin:0;\">BRAICK DISPENSARY</h2>\r\n                <p style=\"font-size:12px;color:#666;margin:0;\">Quality Healthcare Services</p>\r\n            </div>\r\n        </div>\r\n        <h3 style=\"font-size:16px;color:#333;margin:0;\">OBSTETRIC ULTRASOUND REPORT</h3>\r\n    </div>\r\n    \r\n    <div class=\"patient-info\">\r\n        <p><strong>Patient Name:</strong> {patient_name}</p>\r\n        <p><strong>Age/Sex:</strong> {age} yrs / {gender}</p>\r\n        <p><strong>Date of Exam:</strong> {exam_date}</p>\r\n    </div>\r\n    \r\n    <div class=\"findings\">\r\n        <h4>FINDINGS</h4>\r\n        <p><strong>Embryo:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"embryo\" placeholder=\"e.g. single viable intrauterine embryo\" style=\"width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;\"></p>\r\n        <p><strong>CRL (Crown Rump Length):</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"crl\" placeholder=\"e.g. 31.57mm\" style=\"width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;\"></p>\r\n        <p><strong>Gestational Age (GA):</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"ga\" placeholder=\"e.g. 10W+2D\" style=\"width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;\"></p>\r\n        <p><strong>Fetal Pole:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"fetal_pole\" placeholder=\"e.g. seen\" style=\"width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;\"></p>\r\n        <p><strong>Yolk Sac:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"yolk_sac\" placeholder=\"e.g. seen\" style=\"width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;\"></p>\r\n        <p><strong>Myometrium:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"myometrium\" placeholder=\"e.g. no myometrial masses seen\" style=\"width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;\"></p>\r\n        <p><strong>Cervix:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"cervix\" placeholder=\"e.g. normal and closed\" style=\"width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;\"></p>\r\n        <p><strong>Adnexal Areas:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"adnexa\" placeholder=\"e.g. looked normal\" style=\"width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;\"></p>\r\n        <p><strong>Pouch of Douglas:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"pouch_douglas\" placeholder=\"e.g. no fluid seen\" style=\"width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;\"></p>\r\n        <p><strong>Maternal Organs:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"maternal_organs\" placeholder=\"e.g. normal\" style=\"width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;\"></p>\r\n    </div>\r\n    \r\n    <div class=\"conclusion\">\r\n        <h4>CONCLUSION</h4>\r\n        <textarea class=\"form-control conclusion-field\" rows=\"2\" placeholder=\"Enter conclusion...\" style=\"width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;\"></textarea>\r\n    </div>\r\n    \r\n    <div class=\"report-footer\">\r\n        <span>Technician: <input type=\"text\" class=\"form-control\" style=\"display:inline-block;width:auto;border:none;border-bottom:1px solid #ddd;padding:0 8px;\" placeholder=\"Technician Name\"></span>\r\n        <span>Date: {report_date}</span>\r\n    </div>\r\n</div>', 1, '2026-07-27 14:20:54', '2026-07-27 15:01:08'),
(8, 'Abdominal Ultrasound (Male)', 'Abdominal Ultrasound - Male', 'ultrasound', '<div class=\"ultrasound-report\">\r\n    <div class=\"report-header\">\r\n        <div style=\"display:flex;align-items:center;justify-content:center;gap:15px;margin-bottom:10px;\">\r\n            <img src=\"/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png\" alt=\"Braick Dispensary\" style=\"height:60px;width:auto;max-height:60px;\" onerror=\"this.style.display=\'none\'\">\r\n            <div>\r\n                <h2 style=\"color:#0B5ED7;font-size:22px;margin:0;\">BRAICK DISPENSARY</h2>\r\n                <p style=\"font-size:12px;color:#666;margin:0;\">Quality Healthcare Services</p>\r\n            </div>\r\n        </div>\r\n        <h3 style=\"font-size:16px;color:#333;margin:0;\">ULTRASOUND REPORT – ABDOMEN AND PELVIS</h3>\r\n    </div>\r\n    \r\n    <div class=\"patient-info\">\r\n        <p><strong>Patient Name:</strong> {patient_name}</p>\r\n        <p><strong>Age/Sex:</strong> {age} yrs / {gender}</p>\r\n        <p><strong>Date of Exam:</strong> {exam_date}</p>\r\n    </div>\r\n    \r\n    <div class=\"findings\">\r\n        <h4>FINDINGS</h4>\r\n        <p><strong>Liver:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"liver\" placeholder=\"e.g. Appears normal in size, shape, homogeneous echo pattern\" style=\"width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;\"></p>\r\n        <p><strong>Gallbladder:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"gallbladder\" placeholder=\"e.g. Appears normal, well distended, no stones\" style=\"width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;\"></p>\r\n        <p><strong>Pancreas:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"pancreas\" placeholder=\"e.g. Appears normal in size and shape\" style=\"width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;\"></p>\r\n        <p><strong>Spleen:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"spleen\" placeholder=\"e.g. Appears normal in size, shape and echotexture\" style=\"width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;\"></p>\r\n        <p><strong>Peritoneum:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"peritoneum\" placeholder=\"e.g. No free fluid noted\" style=\"width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;\"></p>\r\n        <p><strong>Kidneys:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"kidneys\" placeholder=\"e.g. Both kidneys normal\" style=\"width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;\"></p>\r\n        <p><strong>Urinary Bladder:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"bladder\" placeholder=\"e.g. Appears normal, well-distended\" style=\"width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;\"></p>\r\n        <p><strong>Prostate:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"prostate\" placeholder=\"e.g. Appears normal in size, shape and echotexture\" style=\"width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;\"></p>\r\n    </div>\r\n    \r\n    <div class=\"conclusion\">\r\n        <h4>IMPRESSION/CONCLUSION</h4>\r\n        <textarea class=\"form-control conclusion-field\" rows=\"2\" placeholder=\"Enter impression...\" style=\"width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;\"></textarea>\r\n    </div>\r\n    \r\n    <div class=\"report-footer\">\r\n        <span>Technician: <input type=\"text\" class=\"form-control\" style=\"display:inline-block;width:auto;border:none;border-bottom:1px solid #ddd;padding:0 8px;\" placeholder=\"Technician Name\"></span>\r\n        <span>Date: {report_date}</span>\r\n    </div>\r\n</div>', 1, '2026-07-27 14:20:54', '2026-07-27 15:01:08'),
(9, 'Abdominal Ultrasound (Female)', 'Abdominal Ultrasound - Female', 'ultrasound', '<div class=\"ultrasound-report\">\r\n    <div class=\"report-header\">\r\n        <div style=\"display:flex;align-items:center;justify-content:center;gap:15px;margin-bottom:10px;\">\r\n            <img src=\"/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png\" alt=\"Braick Dispensary\" style=\"height:60px;width:auto;max-height:60px;\" onerror=\"this.style.display=\'none\'\">\r\n            <div>\r\n                <h2 style=\"color:#0B5ED7;font-size:22px;margin:0;\">BRAICK DISPENSARY</h2>\r\n                <p style=\"font-size:12px;color:#666;margin:0;\">Quality Healthcare Services</p>\r\n            </div>\r\n        </div>\r\n        <h3 style=\"font-size:16px;color:#333;margin:0;\">ULTRASOUND REPORT – ABDOMEN AND PELVIS</h3>\r\n    </div>\r\n    \r\n    <div class=\"patient-info\">\r\n        <p><strong>Patient Name:</strong> {patient_name}</p>\r\n        <p><strong>Age/Sex:</strong> {age} yrs / {gender}</p>\r\n        <p><strong>Date of Exam:</strong> {exam_date}</p>\r\n    </div>\r\n    \r\n    <div class=\"findings\">\r\n        <h4>FINDINGS</h4>\r\n        <p><strong>Liver:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"liver\" placeholder=\"e.g. Appeared normal in size, shape, homogeneous echo pattern\" style=\"width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;\"></p>\r\n        <p><strong>Gallbladder:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"gallbladder\" placeholder=\"e.g. Appears normal, well distended, no stones\" style=\"width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;\"></p>\r\n        <p><strong>Pancreas:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"pancreas\" placeholder=\"e.g. Appeared normal in size and shape\" style=\"width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;\"></p>\r\n        <p><strong>Spleen:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"spleen\" placeholder=\"e.g. Appeared normal in size, shape and echotexture\" style=\"width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;\"></p>\r\n        <p><strong>Peritoneum:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"peritoneum\" placeholder=\"e.g. No free fluid noted\" style=\"width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;\"></p>\r\n        <p><strong>Kidneys:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"kidneys\" placeholder=\"e.g. Both kidneys normal\" style=\"width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;\"></p>\r\n        <p><strong>Urinary Bladder:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"bladder\" placeholder=\"e.g. Appears normal, well-distended\" style=\"width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;\"></p>\r\n        <p><strong>Uterus:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"uterus\" placeholder=\"e.g. Appears normal in size and homogeneous echo pattern\" style=\"width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;\"></p>\r\n        <p><strong>Right Ovary:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"right_ovary\" placeholder=\"e.g. Appears normal in size and appearance\" style=\"width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;\"></p>\r\n        <p><strong>Left Ovary:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"left_ovary\" placeholder=\"e.g. Appears normal in size and appearance\" style=\"width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;\"></p>\r\n        <p><strong>Pouch of Douglas:</strong> <input type=\"text\" class=\"form-control placeholder-field\" data-placeholder=\"pouch_douglas\" placeholder=\"e.g. Free fluid seen\" style=\"width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;\"></p>\r\n    </div>\r\n    \r\n    <div class=\"conclusion\">\r\n        <h4>IMPRESSION</h4>\r\n        <textarea class=\"form-control conclusion-field\" rows=\"2\" placeholder=\"Enter impression...\" style=\"width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;\"></textarea>\r\n    </div>\r\n    \r\n    <div class=\"report-footer\">\r\n        <span>Technician: <input type=\"text\" class=\"form-control\" style=\"display:inline-block;width:auto;border:none;border-bottom:1px solid #ddd;padding:0 8px;\" placeholder=\"Technician Name\"></span>\r\n        <span>Date: {report_date}</span>\r\n    </div>\r\n</div>', 1, '2026-07-27 14:20:54', '2026-07-27 15:01:09');

-- --------------------------------------------------------

--
-- Table structure for table `lab_tests`
--

CREATE TABLE `lab_tests` (
  `id` int(11) NOT NULL,
  `visit_id` int(11) NOT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `lab_technician_id` int(11) DEFAULT NULL,
  `test_name` varchar(100) NOT NULL,
  `test_price` decimal(10,2) DEFAULT 0.00,
  `test_type` varchar(50) DEFAULT NULL,
  `sample_type` varchar(50) DEFAULT NULL,
  `test_date` date DEFAULT NULL,
  `results` text DEFAULT NULL,
  `reference_range` varchar(100) DEFAULT NULL,
  `interpretation` text DEFAULT NULL,
  `performed_by` int(11) DEFAULT NULL,
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `bill_created` tinyint(1) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `technician_id` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `result_template_id` int(11) DEFAULT NULL,
  `formatted_result` longtext DEFAULT NULL,
  `printed_at` timestamp NULL DEFAULT NULL,
  `printed_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lab_tests`
--

INSERT INTO `lab_tests` (`id`, `visit_id`, `doctor_id`, `lab_technician_id`, `test_name`, `test_price`, `test_type`, `sample_type`, `test_date`, `results`, `reference_range`, `interpretation`, `performed_by`, `status`, `bill_created`, `notes`, `technician_id`, `branch_id`, `created_at`, `completed_at`, `updated_at`, `result_template_id`, `formatted_result`, `printed_at`, `printed_by`) VALUES
(65, 92, 5, 8, 'Hepatitis B Surface Antigen (HBsAg)', 15000.00, NULL, NULL, NULL, 'Non-reactive', NULL, NULL, NULL, 'completed', 0, '', NULL, 1, '2026-07-27 08:50:52', '2026-07-27 08:51:52', '2026-07-27 08:51:52', NULL, NULL, NULL, NULL),
(66, 92, 5, 8, 'Pregnancy Test (Blood - Beta HCG)', 20000.00, NULL, NULL, NULL, 'Positive - HCG detected', NULL, NULL, NULL, 'completed', 0, '', NULL, 1, '2026-07-27 08:50:52', '2026-07-27 08:51:59', '2026-07-27 08:51:59', NULL, NULL, NULL, NULL),
(67, 92, 5, 8, 'Renal Function Test (RFT)', 20000.00, NULL, NULL, NULL, 'BUN > 25, BUN/Cr > 20', NULL, NULL, NULL, 'completed', 0, '', NULL, 1, '2026-07-27 08:50:52', '2026-07-27 08:52:06', '2026-07-27 08:52:06', NULL, NULL, NULL, NULL),
(68, 93, 5, 8, 'Renal Function Test (RFT)', 20000.00, NULL, NULL, NULL, 'Creatinine: 0.6-1.2, BUN: 7-20, Uric Acid: 3.5-7.2', NULL, NULL, NULL, 'completed', 0, '', NULL, 1, '2026-07-27 12:47:35', '2026-07-27 12:49:37', '2026-07-27 12:49:37', NULL, NULL, NULL, NULL),
(69, 94, 5, 8, 'Ultrasound - Abdomen & Pelvis', 65000.00, NULL, NULL, NULL, 'Liver: Normal\r\nGallbladder: No stones\r\nPancreas: normal\r\nSpleen: normal\r\nPeritoneum: no free\r\nKidneys: Normal\r\nBladder: Normal\r\nUterus: Normal\r\nRight Ovary: Normal\r\nLeft Ovary: Normal\r\nPouch Douglas: Normal\r\nEnter Impression...: okay', NULL, NULL, NULL, 'completed', 0, '', 8, 1, '2026-07-27 13:37:29', '2026-07-27 14:28:34', '2026-07-27 14:28:34', NULL, NULL, NULL, NULL),
(70, 95, 5, 8, 'HIV ELISA', 25000.00, NULL, NULL, NULL, 'Reactive - Confirm with ELISA', NULL, NULL, NULL, 'completed', 0, '', 8, 1, '2026-07-27 14:32:34', '2026-07-27 14:32:55', '2026-07-27 14:32:55', NULL, NULL, NULL, NULL),
(71, 95, 5, 8, 'Ultrasound - 3D/4D Obstetric', 80000.00, NULL, NULL, NULL, 'Liver: Normal\r\nGallbladder: No stones\r\nPancreas: Appered Normal\r\nSpleen: Normal\r\nPeritoneum: Free\r\nKidneys: Normal\r\nBladder: Normal\r\nUterus: Echo Patterns', NULL, NULL, NULL, 'completed', 0, '', NULL, 1, '2026-07-27 14:32:34', '2026-07-27 14:36:10', '2026-07-27 14:36:10', NULL, NULL, NULL, NULL),
(72, 96, 5, 8, 'Ultrasound - Scrotal', 40000.00, NULL, NULL, NULL, 'Liver: Normal\r\nGallbladder: Normal\r\nPancreas: Normal\r\nSpleen: Normal\r\nPeritoneum: Normal\r\nKidneys: Normal\r\nBladder: Normal\r\nUterus: Normal\r\nRight Ovary: Normal\r\nLeft Ovary: Normal\r\nPouch Douglas: Normal', '', '', 8, 'completed', 0, '', NULL, 1, '2026-07-27 14:41:54', '2026-07-29 09:47:32', '2026-07-29 09:47:32', NULL, NULL, NULL, NULL),
(73, 97, 8, NULL, '', 0.00, NULL, NULL, NULL, '100-125 mg/dL', '', '', 8, 'completed', 0, '', NULL, 1, '2026-07-27 18:18:32', NULL, '2026-07-27 18:18:32', NULL, NULL, NULL, NULL),
(74, 98, 8, NULL, '', 0.00, NULL, NULL, NULL, '100-125 mg/dL', '', '', 8, 'completed', 0, '', NULL, 1, '2026-07-27 18:20:27', NULL, '2026-07-27 18:20:27', NULL, NULL, NULL, NULL),
(75, 100, 5, 8, 'Blood Glucose (Fasting)', 8000.00, NULL, NULL, NULL, '70-100 mg/dL', '', '', 8, 'completed', 0, '', NULL, 1, '2026-07-29 09:46:02', '2026-07-29 09:47:46', '2026-07-29 09:47:46', NULL, NULL, NULL, NULL),
(76, 100, 5, 8, 'Ultrasound - Thyroid', 40000.00, NULL, NULL, NULL, 'Liver: Normal\r\nGallbladder: Normal\r\nPancreas: Normal\r\nSpleen: Normal\r\nPeritoneum: Normal\r\nKidneys: Normal\r\nBladder: Normal\r\nProstate: Normal', '', '', 8, 'completed', 0, '', NULL, 1, '2026-07-29 09:46:02', '2026-07-29 09:48:20', '2026-07-29 09:48:20', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `lab_tests_catalog`
--

CREATE TABLE `lab_tests_catalog` (
  `id` int(11) NOT NULL,
  `test_name` varchar(100) NOT NULL,
  `test_code` varchar(20) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `reference_range` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `branch_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lab_tests_catalog`
--

INSERT INTO `lab_tests_catalog` (`id`, `test_name`, `test_code`, `category`, `price`, `description`, `reference_range`, `is_active`, `branch_id`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Complete Blood Count (CBC)', 'CBC-001', 'Hematology', 15000.00, 'Full blood count including RBC, WBC, hemoglobin, platelets', 'RBC: 4.5-5.5M, WBC: 4.5-11K, HGB: 13-17g/dL, PLT: 150-400K', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(2, 'Blood Glucose (Fasting)', 'GLU-001', 'Biochemistry', 8000.00, 'Fasting blood sugar test', '70-100 mg/dL (Fasting)', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(3, 'Blood Glucose (Random)', 'GLU-002', 'Biochemistry', 8000.00, 'Random blood sugar test', '70-140 mg/dL', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(4, 'Lipid Profile', 'LIP-001', 'Biochemistry', 20000.00, 'Total cholesterol, HDL, LDL, Triglycerides', 'Total: <200, LDL: <100, HDL: >40, TG: <150', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(5, 'Liver Function Test (LFT)', 'LFT-001', 'Biochemistry', 25000.00, 'AST, ALT, ALP, Total Bilirubin, Direct Bilirubin', 'AST: 10-40, ALT: 7-56, ALP: 44-147, T.Bili: 0.1-1.2', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(6, 'Renal Function Test (RFT)', 'RFT-001', 'Biochemistry', 20000.00, 'Creatinine, BUN, Uric Acid, Electrolytes', 'Creatinine: 0.6-1.2, BUN: 7-20, Uric Acid: 3.5-7.2', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(7, 'Urinalysis', 'UNA-001', 'Urinalysis', 10000.00, 'Complete urine analysis with microscopy', 'pH: 4.5-8.0, Protein: Negative, Glucose: Negative', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(8, 'Malaria Rapid Test', 'MAL-001', 'Infectious Diseases', 5000.00, 'Rapid diagnostic test for malaria (Pf/Pv)', 'Negative/Positive', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(9, 'Malaria Microscopy', 'MAL-002', 'Infectious Diseases', 10000.00, 'Microscopic examination for malaria parasites', 'Negative/Positive', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(10, 'COVID-19 Rapid Antigen Test', 'COV-001', 'Infectious Diseases', 15000.00, 'Rapid antigen test for COVID-19', 'Negative/Positive', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(11, 'COVID-19 PCR Test', 'COV-002', 'Molecular', 50000.00, 'RT-PCR test for COVID-19', 'Negative/Positive', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(12, 'Typhoid Test (Widal)', 'TYPH-001', 'Infectious Diseases', 12000.00, 'Widal test for typhoid fever', 'O: <1:80, H: <1:160', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(13, 'Dengue Test', 'DEN-001', 'Infectious Diseases', 15000.00, 'Dengue NS1 antigen test', 'Negative/Positive', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(14, 'HIV Rapid Test', 'HIV-001', 'Infectious Diseases', 8000.00, 'Rapid HIV antibody test', 'Negative/Positive', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(15, 'HIV ELISA', 'HIV-002', 'Infectious Diseases', 25000.00, 'ELISA test for HIV', 'Negative/Positive', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(16, 'Pregnancy Test (Urine)', 'PRE-001', 'Urinalysis', 5000.00, 'Urine pregnancy test (HCG)', 'Negative/Positive', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(17, 'Pregnancy Test (Blood - Beta HCG)', 'PRE-002', 'Hormone', 20000.00, 'Quantitative blood HCG test', 'Negative: <5 mIU/mL', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(18, 'Tuberculosis (TB) Skin Test', 'TB-001', 'Infectious Diseases', 10000.00, 'Mantoux tuberculin skin test', '0-4mm: Negative, 5-9mm: Indeterminate', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(19, 'Tuberculosis (TB) GeneXpert', 'TB-002', 'Molecular', 45000.00, 'GeneXpert MTB/RIF test', 'Negative/Positive/RIF Resistance', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(20, 'Sputum AFB', 'SPUT-001', 'Microbiology', 12000.00, 'Acid-fast bacilli smear test', 'Negative/Positive', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(21, 'Blood Culture', 'CULT-001', 'Microbiology', 30000.00, 'Blood culture and sensitivity', 'No growth/Pathogen isolated', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(22, 'Urine Culture', 'CULT-002', 'Microbiology', 25000.00, 'Urine culture and sensitivity', 'No growth/Pathogen isolated', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(23, 'Stool Analysis', 'STL-001', 'Parasitology', 10000.00, 'Complete stool examination', 'Normal/Abnormal', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(24, 'Helicobacter Pylori Test', 'HP-001', 'Infectious Diseases', 15000.00, 'H. pylori antigen test', 'Negative/Positive', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(25, 'Thyroid Function Test (TFT)', 'THY-001', 'Hormone', 30000.00, 'TSH, T3, T4', 'TSH: 0.4-4.0, Free T4: 0.8-1.8', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(26, 'Vitamin D Test', 'VITD-001', 'Nutrition', 25000.00, '25-Hydroxy Vitamin D test', 'Deficient: <20, Insufficient: 20-29, Sufficient: >30', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(27, 'Vitamin B12 Test', 'VITB12-001', 'Nutrition', 20000.00, 'Vitamin B12 level', '200-900 pg/mL', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(28, 'Ferritin Test', 'FERR-001', 'Nutrition', 18000.00, 'Ferritin iron stores test', 'Male: 24-336, Female: 11-307 ng/mL', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(29, 'Hepatitis B Surface Antigen (HBsAg)', 'HEP-001', 'Infectious Diseases', 15000.00, 'Hepatitis B surface antigen test', 'Negative/Positive', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(30, 'Hepatitis C Antibody (Anti-HCV)', 'HEP-002', 'Infectious Diseases', 15000.00, 'Hepatitis C antibody test', 'Negative/Positive', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(31, 'STI Panel', 'STI-001', 'Infectious Diseases', 35000.00, 'Syphilis (RPR/VDRL), Chlamydia, Gonorrhea', 'Negative/Positive', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(32, 'Syphilis RPR/VDRL', 'SYPH-001', 'Infectious Diseases', 10000.00, 'RPR/VDRL test for syphilis', 'Non-reactive/Reactive', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(33, 'Influenza Rapid Test', 'FLU-001', 'Infectious Diseases', 12000.00, 'Rapid influenza A/B test', 'Negative/Positive', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(34, 'CD4 Count', 'CD4-001', 'Immunology', 30000.00, 'CD4 T-cell count (HIV monitoring)', '>500 cells/mm³ (Normal)', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(35, 'Viral Load HIV', 'VL-001', 'Molecular', 50000.00, 'HIV viral load quantification', '<20 copies/mL (Undetectable)', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(36, 'Chest X-Ray', 'XRAY-001', 'Radiology', 35000.00, 'Chest X-Ray imaging', 'Normal/Abnormal', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(37, 'Abdominal Ultrasound', 'US-001', 'Radiology', 50000.00, 'Abdominal ultrasound scan', 'Normal/Abnormal', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(38, 'Pelvic Ultrasound', 'US-002', 'Radiology', 45000.00, 'Pelvic ultrasound', 'Normal/Abnormal', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(39, 'Obstetric Ultrasound', 'US-003', 'Radiology', 55000.00, 'Obstetric ultrasound scan', 'Normal/Abnormal', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(40, 'ECG (Electrocardiogram)', 'ECG-001', 'Cardiology', 15000.00, '12-lead ECG', 'Normal/Abnormal', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(41, 'Echocardiogram', 'ECHO-001', 'Cardiology', 60000.00, 'Cardiac ultrasound', 'Normal/Abnormal', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(42, 'Pulmonary Function Test (PFT)', 'PFT-001', 'Pulmonology', 25000.00, 'Spirometry/Lung function test', 'Normal/Obstructive/Restrictive', 1, 1, 1, '2026-07-18 16:18:37', '2026-07-27 13:14:25'),
(43, 'Ultrasound - Abdomen & Pelvis', 'US-004', 'Radiology', 65000.00, 'Combined abdominal and pelvic ultrasound', 'Normal/Abnormal', 1, 1, 1, '2026-07-27 13:13:54', '2026-07-27 13:14:25'),
(44, 'Ultrasound - Thyroid', 'US-005', 'Radiology', 40000.00, 'Thyroid ultrasound scan', 'Normal/Abnormal', 1, 1, 1, '2026-07-27 13:13:54', '2026-07-27 13:14:25'),
(45, 'Ultrasound - Breast', 'US-006', 'Radiology', 45000.00, 'Breast ultrasound examination', 'Normal/Abnormal', 1, 1, 1, '2026-07-27 13:13:54', '2026-07-27 13:14:25'),
(46, 'Ultrasound - Scrotal', 'US-007', 'Radiology', 40000.00, 'Scrotal/testicular ultrasound', 'Normal/Abnormal', 1, 1, 1, '2026-07-27 13:13:54', '2026-07-27 13:14:25'),
(47, 'Ultrasound - Renal', 'US-008', 'Radiology', 45000.00, 'Renal/kidney ultrasound', 'Normal/Abnormal', 1, 1, 1, '2026-07-27 13:13:54', '2026-07-27 13:14:25'),
(48, 'Ultrasound - Liver/Biliary', 'US-009', 'Radiology', 40000.00, 'Liver and biliary tree ultrasound', 'Normal/Abnormal', 1, 1, 1, '2026-07-27 13:13:54', '2026-07-27 13:14:25'),
(49, 'Ultrasound - Musculoskeletal', 'US-010', 'Radiology', 50000.00, 'MSK ultrasound for joints and soft tissue', 'Normal/Abnormal', 1, 1, 1, '2026-07-27 13:13:54', '2026-07-27 13:14:25'),
(50, 'Ultrasound - Doppler', 'US-011', 'Radiology', 55000.00, 'Doppler ultrasound for vascular assessment', 'Normal/Abnormal', 1, 1, 1, '2026-07-27 13:13:54', '2026-07-27 13:14:25'),
(51, 'Ultrasound - 3D/4D Obstetric', 'US-012', 'Radiology', 80000.00, '3D/4D obstetric ultrasound', 'Normal/Abnormal', 1, 1, 1, '2026-07-27 13:13:54', '2026-07-27 13:14:25'),
(52, 'Ultrasound - Transvaginal', 'US-013', 'Radiology', 50000.00, 'Transvaginal pelvic ultrasound', 'Normal/Abnormal', 1, 1, 1, '2026-07-27 13:13:54', '2026-07-27 13:14:25');

-- --------------------------------------------------------

--
-- Table structure for table `lab_test_type_mapping`
--

CREATE TABLE `lab_test_type_mapping` (
  `id` int(11) NOT NULL,
  `test_name_pattern` varchar(100) NOT NULL,
  `template_id` int(11) NOT NULL,
  `is_exact_match` tinyint(1) DEFAULT 0,
  `priority` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lab_test_type_mapping`
--

INSERT INTO `lab_test_type_mapping` (`id`, `test_name_pattern`, `template_id`, `is_exact_match`, `priority`) VALUES
(1, 'Obstetric Ultrasound', 1, 0, 10),
(2, 'Obstetric Ultrasound - Twin', 1, 1, 20),
(3, 'Obstetric Ultrasound - Single', 2, 1, 20),
(4, 'Obstetric Ultrasound - Early', 3, 1, 20),
(5, 'Abdominal Ultrasound', 4, 1, 20),
(6, 'Ultrasound - Abdomen', 4, 0, 10),
(7, 'Ultrasound - Pelvis', 4, 0, 10);

-- --------------------------------------------------------

--
-- Table structure for table `medications`
--

CREATE TABLE `medications` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `strength` varchar(50) DEFAULT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `medications`
--

INSERT INTO `medications` (`id`, `name`, `strength`, `unit`, `category`, `branch_id`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Paracetamol', '500mg', 'Tablets', 'Pain Relief', NULL, NULL, 'active', '2026-07-16 11:30:25', '2026-07-16 11:30:25'),
(2, 'Amoxicillin', '250mg', 'Capsules', 'Antibiotic', NULL, NULL, 'active', '2026-07-16 11:30:25', '2026-07-16 11:30:25'),
(3, 'Amoxicillin', '500mg', 'Capsules', 'Antibiotic', NULL, NULL, 'active', '2026-07-16 11:30:25', '2026-07-16 11:30:25'),
(4, 'Ciprofloxacin', '500mg', 'Tablets', 'Antibiotic', NULL, NULL, 'active', '2026-07-16 11:30:25', '2026-07-16 11:30:25'),
(5, 'Metformin', '850mg', 'Tablets', 'Diabetes', NULL, NULL, 'active', '2026-07-16 11:30:25', '2026-07-16 11:30:25'),
(6, 'Metformin', '500mg', 'Tablets', 'Diabetes', NULL, NULL, 'active', '2026-07-16 11:30:25', '2026-07-16 11:30:25'),
(7, 'Lisinopril', '10mg', 'Tablets', 'Blood Pressure', NULL, NULL, 'active', '2026-07-16 11:30:25', '2026-07-16 11:30:25'),
(8, 'Amlodipine', '5mg', 'Tablets', 'Blood Pressure', NULL, NULL, 'active', '2026-07-16 11:30:25', '2026-07-16 11:30:25'),
(9, 'Omeprazole', '20mg', 'Capsules', 'Stomach', NULL, NULL, 'active', '2026-07-16 11:30:25', '2026-07-16 11:30:25'),
(10, 'Pantoprazole', '40mg', 'Tablets', 'Stomach', NULL, NULL, 'active', '2026-07-16 11:30:25', '2026-07-16 11:30:25'),
(11, 'Atorvastatin', '20mg', 'Tablets', 'Cholesterol', NULL, NULL, 'active', '2026-07-16 11:30:25', '2026-07-16 11:30:25'),
(12, 'Rosuvastatin', '10mg', 'Tablets', 'Cholesterol', NULL, NULL, 'active', '2026-07-16 11:30:25', '2026-07-16 11:30:25'),
(13, 'Doxycycline', '100mg', 'Capsules', 'Antibiotic', NULL, NULL, 'active', '2026-07-16 11:30:25', '2026-07-16 11:30:25'),
(14, 'Glibenclamide', '5mg', 'Tablets', 'Diabetes', NULL, NULL, 'active', '2026-07-16 11:30:25', '2026-07-16 11:30:25'),
(15, 'Enalapril', '5mg', 'Tablets', 'Blood Pressure', NULL, NULL, 'active', '2026-07-16 11:30:25', '2026-07-16 11:30:25'),
(16, 'Artemether/Lumefantrine', '20/120mg', 'Tablets', 'Antimalarial', NULL, NULL, 'active', '2026-07-16 11:30:25', '2026-07-16 11:30:25'),
(17, 'Quinine', '300mg', 'Tablets', 'Antimalarial', NULL, NULL, 'active', '2026-07-16 11:30:25', '2026-07-16 11:30:25'),
(18, 'Ibuprofen', '400mg', 'Tablets', 'Pain Relief', NULL, NULL, 'active', '2026-07-16 11:30:25', '2026-07-16 11:30:25'),
(19, 'Diclofenac', '50mg', 'Tablets', 'Pain Relief', NULL, NULL, 'active', '2026-07-16 11:30:25', '2026-07-16 11:30:25'),
(20, 'Cetirizine', '10mg', 'Tablets', 'Allergy', NULL, NULL, 'active', '2026-07-16 11:30:25', '2026-07-16 11:30:25');

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
  `unit_cost` decimal(10,2) DEFAULT 0.00,
  `selling_price` decimal(10,2) DEFAULT 0.00,
  `supplier` varchar(100) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `batch_number` varchar(50) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `medications_inventory`
--

INSERT INTO `medications_inventory` (`id`, `medication_name`, `category`, `unit`, `quantity`, `reorder_level`, `unit_cost`, `selling_price`, `supplier`, `expiry_date`, `batch_number`, `branch_id`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Paracetamol 500mg', 'Pain Relief', 'Tablets', 489, 50, 100.00, 200.00, 'Dodoma Pharma', NULL, NULL, 1, 'active', '2026-07-16 11:30:25', '2026-07-23 10:52:11'),
(2, 'Amoxicillin 250mg', 'Antibiotic', 'Capsules', 110, 30, 500.00, 800.00, 'Dodoma Pharma', NULL, NULL, 1, 'active', '2026-07-16 11:30:25', '2026-07-29 11:09:05'),
(3, 'Amoxicillin 500mg', 'Antibiotic', 'Capsules', 188, 20, 800.00, 1200.00, 'Dodoma Pharma', NULL, NULL, 1, 'active', '2026-07-16 11:30:25', '2026-07-23 15:47:42'),
(4, 'Metformin 850mg', 'Diabetes', 'Tablets', 130, 15, 400.00, 600.00, 'Dodoma Pharma', NULL, NULL, 1, 'active', '2026-07-16 11:30:25', '2026-07-23 10:27:04'),
(5, 'Artemether/Lumefantrine', 'Antimalarial', 'Tablets', 80, 20, 300.00, 500.00, 'Dodoma Pharma', NULL, NULL, 1, 'active', '2026-07-16 11:30:25', '2026-07-23 15:48:00'),
(6, 'Omeprazole 20mg', 'Stomach', 'Capsules', 80, 15, 200.00, 350.00, 'Dodoma Pharma', NULL, NULL, 1, 'active', '2026-07-16 11:30:25', '2026-07-23 17:31:49'),
(7, 'Lisinopril 10mg', 'Blood Pressure', 'Tablets', 49, 10, 250.00, 400.00, 'Dodoma Pharma', NULL, NULL, 1, 'active', '2026-07-16 11:30:25', '2026-07-22 15:52:40'),
(8, 'Amlodipine 5mg', 'Blood Pressure', 'Tablets', 5, 10, 300.00, 450.00, 'Dodoma Pharma', NULL, NULL, 1, 'active', '2026-07-16 11:30:25', '2026-07-27 13:08:14'),
(9, 'Paracetamol 500mg', 'Pain Relief', 'Tablets', 400, 40, 120.00, 250.00, 'Arusha Pharma', NULL, NULL, 2, 'active', '2026-07-16 11:30:25', '2026-07-16 11:30:25'),
(10, 'Amoxicillin 250mg', 'Antibiotic', 'Capsules', 250, 25, 550.00, 900.00, 'Arusha Pharma', NULL, NULL, 2, 'active', '2026-07-16 11:30:25', '2026-07-16 11:30:25'),
(11, 'Ciprofloxacin 500mg', 'Antibiotic', 'Tablets', 100, 15, 700.00, 1000.00, 'Arusha Pharma', NULL, NULL, 2, 'active', '2026-07-16 11:30:25', '2026-07-16 11:30:25'),
(12, 'Metformin 500mg', 'Diabetes', 'Tablets', 120, 15, 300.00, 500.00, 'Arusha Pharma', NULL, NULL, 2, 'active', '2026-07-16 11:30:25', '2026-07-16 11:30:25'),
(13, 'Ibuprofen 400mg', 'Pain Relief', 'Tablets', 200, 20, 150.00, 250.00, 'Arusha Pharma', NULL, NULL, 2, 'active', '2026-07-16 11:30:25', '2026-07-16 11:30:25'),
(14, 'Cetirizine 10mg', 'Allergy', 'Tablets', 150, 15, 100.00, 200.00, 'Arusha Pharma', NULL, NULL, 2, 'active', '2026-07-16 11:30:25', '2026-07-16 11:30:25'),
(15, 'Doxycycline 100mg', 'Antibiotic', 'Capsules', 80, 10, 400.00, 600.00, 'Arusha Pharma', NULL, NULL, 2, 'active', '2026-07-16 11:30:25', '2026-07-16 11:30:25'),
(16, 'Panadol', '', 'tablets', 300, 20, 200.00, 400.00, '', '2026-07-25', 'BATCH-20260720-GRW2CJ', 1, 'inactive', '2026-07-20 07:24:16', '2026-07-20 07:25:24'),
(17, 'ALBENDERZOL', 'Antibiotics', 'box', -40, 10, 2000.00, 4000.00, '', '2028-06-20', 'BATCH-20260720-X0JR5F', 1, 'active', '2026-07-20 07:27:21', '2026-07-29 11:14:36'),
(18, 'ALBENDERZOL', 'Antihistamines', 'box', 360, 50, 900.00, 1500.00, '', '2026-09-27', 'BATCH-20260727-TDB4I1', 1, 'active', '2026-07-27 11:27:47', '2026-07-29 11:14:36'),
(19, 'MSETO', 'Antibiotics', 'box', 199, 10, 2000.00, 3500.00, 'JAMES', '2027-02-28', 'BATCH-20260729-OXLOFN', 1, 'active', '2026-07-29 10:56:48', '2026-07-29 11:12:15');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','danger') DEFAULT 'info',
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `branch_id`, `title`, `message`, `type`, `link`, `is_read`, `created_at`) VALUES
(1, 11, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. Grace Peter is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-16 15:32:16'),
(2, 4, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. Grace Peter is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-16 15:32:16'),
(3, 11, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. Grace Peter is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-16 15:32:32'),
(4, 4, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. Grace Peter is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-16 15:32:32'),
(5, 11, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. Grace Peter is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-16 15:32:43'),
(6, 4, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. Grace Peter is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-16 15:32:43'),
(7, 11, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-16 15:37:55'),
(8, 4, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-16 15:37:55'),
(9, 11, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-16 15:37:58'),
(10, 4, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-16 15:37:58'),
(11, 11, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-16 15:38:08'),
(12, 4, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-16 15:38:08'),
(13, 11, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-16 15:53:06'),
(14, 4, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-16 15:53:06'),
(15, 11, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-16 15:53:07'),
(16, 4, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-16 15:53:07'),
(17, 11, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-16 15:55:03'),
(18, 4, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-16 15:55:03'),
(19, 11, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-16 15:55:21'),
(20, 4, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-16 15:55:21'),
(21, 11, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-16 16:43:34'),
(22, 4, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-16 16:43:34'),
(23, 11, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-16 17:40:25'),
(24, 4, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-16 17:40:25'),
(25, 11, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-16 17:54:43'),
(26, 4, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-16 17:54:43'),
(27, 11, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-16 17:54:51'),
(28, 4, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-16 17:54:51'),
(29, 11, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-17 13:40:20'),
(30, 4, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-17 13:40:20'),
(31, 11, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-17 13:47:59'),
(32, 4, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-17 13:47:59'),
(33, 11, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-17 13:48:20'),
(34, 4, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-17 13:48:20'),
(35, 11, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-17 13:48:27'),
(36, 4, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-17 13:48:27'),
(37, 11, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-17 13:52:38'),
(38, 4, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-17 13:52:38'),
(39, 11, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-17 13:58:11'),
(40, 4, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-17 13:58:11'),
(41, 11, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-17 14:21:10'),
(42, 4, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-17 14:21:10'),
(43, 11, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-17 14:21:55'),
(44, 4, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-17 14:21:55'),
(45, 11, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-17 14:22:24'),
(46, 4, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-17 14:22:24'),
(47, 11, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-17 14:22:29'),
(48, 4, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-17 14:22:29'),
(49, 11, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-17 14:22:31'),
(50, 4, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-17 14:22:31'),
(51, 11, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-17 14:22:40'),
(52, 4, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-17 14:22:40'),
(53, 11, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-17 14:27:46'),
(54, 4, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-17 14:27:46'),
(55, 11, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-17 14:27:50'),
(56, 4, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-17 14:27:50'),
(57, 11, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-17 14:29:47'),
(58, 4, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-17 14:29:47'),
(59, 11, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-17 14:29:52'),
(60, 4, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-17 14:29:52'),
(61, 11, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-17 14:30:34'),
(62, 4, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-17 14:30:34'),
(63, 11, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-17 14:32:53'),
(64, 4, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-17 14:32:53'),
(65, 11, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-17 14:33:43'),
(66, 4, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-17 14:33:43'),
(67, 11, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-17 14:33:46'),
(68, 4, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-17 14:33:46'),
(69, 11, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-17 14:40:50'),
(70, 4, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-17 14:40:50'),
(71, 11, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-17 14:59:48'),
(72, 4, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-17 14:59:48'),
(73, 11, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-17 16:03:32'),
(74, 4, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-17 16:03:32'),
(75, 11, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-17 16:03:48'),
(76, 4, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-17 16:03:48'),
(77, 11, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-17 16:21:13'),
(78, 4, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-17 16:21:13'),
(79, 11, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-17 16:21:24'),
(80, 4, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-17 16:21:24'),
(81, 11, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-18 11:24:54'),
(82, 4, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-18 11:24:54'),
(83, 11, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-18 11:24:57'),
(84, 4, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-18 11:24:57'),
(85, 11, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-18 11:25:04'),
(86, 4, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-18 11:25:04'),
(87, 11, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-18 11:25:07'),
(88, 4, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-18 11:25:07'),
(89, 11, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-20 06:36:28'),
(90, 4, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-20 06:36:28'),
(91, 11, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-20 06:36:39'),
(92, 4, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-20 06:36:39'),
(93, 11, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-20 06:40:08'),
(94, 4, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-20 06:40:08'),
(95, 11, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-20 06:40:17'),
(96, 4, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-20 06:40:17'),
(97, 11, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-20 07:51:18'),
(98, 4, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-20 07:51:18'),
(99, 11, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-20 07:51:57'),
(100, 4, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-20 07:51:57'),
(101, 11, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-21 12:42:29'),
(102, 4, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-21 12:42:29'),
(103, 11, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-21 12:42:35'),
(104, 4, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-21 12:42:35'),
(105, 11, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-21 13:01:57'),
(106, 4, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-21 13:01:57'),
(107, 11, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-21 13:02:02'),
(108, 4, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-21 13:02:02'),
(109, 11, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-23 14:47:29'),
(110, 4, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-23 14:47:29'),
(111, 11, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-23 14:47:46'),
(112, 4, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-23 14:47:46'),
(113, 11, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-23 16:40:09'),
(114, 4, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-23 16:40:09'),
(115, 11, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-23 16:40:22'),
(116, 4, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-23 16:40:22'),
(117, 10, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260729-0066-1508 (TSh 10,000) for patient ID #66', '', 'cashier_dashboard.php', 0, '2026-07-29 09:17:26'),
(118, 11, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-29 09:23:04'),
(119, 4, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-29 09:23:04'),
(120, 11, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-29 09:32:35'),
(121, 4, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-29 09:32:35'),
(122, 11, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-29 09:42:28'),
(123, 4, NULL, 'Doctor Status: 🔴 Offline', 'Dr. Dr. John Mushi is now OFFLINE.', 'warning', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-29 09:42:28'),
(124, 11, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/reception/assign_doctor.php', 0, '2026-07-29 09:42:35'),
(125, 4, NULL, 'Doctor Status: 🟢 Online', 'Dr. Dr. John Mushi is now ONLINE and available for patient assignments.', 'success', '/dispensary_system/frontend/pages/admin/doctors.php', 0, '2026-07-29 09:42:35'),
(126, 10, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260729-0067-7596 (TSh 10,000) for patient ID #67', '', 'cashier_dashboard.php', 0, '2026-07-29 09:43:50'),
(127, 10, NULL, '💰 New Bill Created', 'Consultation bill #BILL-20260729-0068-4146 (TSh 10,000) for patient ID #68', '', 'cashier_dashboard.php', 0, '2026-07-29 11:03:36');

-- --------------------------------------------------------

--
-- Table structure for table `otc_sales`
--

CREATE TABLE `otc_sales` (
  `id` int(11) NOT NULL,
  `sale_number` varchar(50) NOT NULL,
  `customer_name` varchar(100) DEFAULT 'Walk-in Customer',
  `customer_phone` varchar(20) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `net_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `bill_id` int(11) DEFAULT NULL,
  `payment_method` enum('cash','card','m-pesa','other') DEFAULT 'cash',
  `payment_status` enum('paid','pending','partial') DEFAULT 'paid',
  `sold_by` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `otc_sales`
--

INSERT INTO `otc_sales` (`id`, `sale_number`, `customer_name`, `customer_phone`, `total_amount`, `discount_amount`, `net_amount`, `bill_id`, `payment_method`, `payment_status`, `sold_by`, `branch_id`, `notes`, `created_at`, `updated_at`) VALUES
(3, 'OTC-20260727-4945', 'JACKSON', '', 8000.00, 500.00, 7500.00, 118, 'm-pesa', 'pending', 5, 1, 'OTC Sale - Bill sent to Cashier', '2026-07-27 11:11:54', '2026-07-27 11:11:54'),
(4, 'OTC-20260727-3152', 'JACKSON', '', 24000.00, 2500.00, 21500.00, 120, 'cash', 'paid', 5, 1, 'OTC Sale - Bill sent to Cashier', '2026-07-27 12:52:40', '2026-07-27 12:55:06'),
(5, 'OTC-20260727-0734', 'ADELA', '', 8000.00, 900.00, 7100.00, 122, 'cash', 'paid', 9, 1, 'OTC Sale - Bill sent to Cashier', '2026-07-27 13:06:15', '2026-07-27 13:06:39'),
(6, 'OTC-20260727-5987', 'KELVIN', '', 25050.00, 3000.00, 22050.00, 123, '', 'paid', 5, 1, 'OTC Sale - Bill sent to Cashier', '2026-07-27 13:08:14', '2026-07-27 13:08:34');

-- --------------------------------------------------------

--
-- Table structure for table `otc_sale_items`
--

CREATE TABLE `otc_sale_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `medicine_name` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `otc_sale_items`
--

INSERT INTO `otc_sale_items` (`id`, `sale_id`, `inventory_id`, `medicine_name`, `quantity`, `unit_price`, `total_price`, `created_at`) VALUES
(4, 5, 2, 'Amoxicillin 250mg', 10, 800.00, 8000.00, '2026-07-27 13:06:15'),
(5, 6, 17, 'ALBENDERZOL', 10, 1500.00, 15000.00, '2026-07-27 13:08:14'),
(6, 6, 8, 'Amlodipine 5mg', 1, 450.00, 450.00, '2026-07-27 13:08:14'),
(7, 6, 8, 'Amlodipine 5mg', 1, 450.00, 450.00, '2026-07-27 13:08:14');

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
(55, 'P-2026-0001', 'JACKSON MYULA', '2002-04-12', 'Male', 'Single', '0623693303', 'jacksonmyula773@gmail.com', 'TANZANIA', '0746526243', '', 'Milk, Soy, Wheat', 1, 6, 5, '2026-07-27 08:49:37', '2026-07-29 10:49:12'),
(57, 'PAT-OTC-20260727-3364', 'JACKSON', NULL, NULL, NULL, 'N/A', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, '2026-07-27 11:11:54', '2026-07-27 11:11:54'),
(58, 'P-2026-0003', 'MAGRETH MSAFIRI', '2003-08-09', 'Female', '', '0623693309', 'magreth@gmail.com', 'Rukwa\r\nMtakumbuka', '', 'A-', 'Penicillin', 1, 6, 5, '2026-07-27 12:46:23', '2026-07-27 12:47:03'),
(59, 'PAT-OTC-20260727-2955', 'JACKSON', NULL, NULL, NULL, 'N/A', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, '2026-07-27 12:52:40', '2026-07-27 12:52:40'),
(60, 'PAT-OTC-20260727-2887', 'ADELA', NULL, NULL, NULL, 'N/A', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, '2026-07-27 13:06:15', '2026-07-27 13:06:15'),
(61, 'PAT-OTC-20260727-7278', 'KELVIN', NULL, NULL, NULL, 'N/A', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, '2026-07-27 13:08:14', '2026-07-27 13:08:14'),
(62, 'P-2026-0007', 'ADELA MYULA', '2007-01-15', 'Female', '', '0676545412', 'adelamyula9@gmail.com', '', '', 'B+', 'Milk, Wheat', 1, 6, 5, '2026-07-27 13:11:07', '2026-07-27 13:11:30'),
(63, 'P-2026-0008', 'ADAM CLISTOPHER', '2000-09-10', 'Male', 'Married', '0652657682', 'adams@gmail.com', '', '', 'B-', 'Sulfa Drugs', 1, 6, 5, '2026-07-27 14:31:39', '2026-07-27 14:31:59'),
(64, 'P-2026-0009', 'KELVIN P. KIMARO', '1987-04-19', 'Male', '', '0678657687', 'kelvin@gmail.com', '', '', '', '', 1, 6, NULL, '2026-07-27 17:44:55', '2026-07-27 17:44:55'),
(65, 'P-2026-0010', 'MUSSA MONGI', '2003-09-11', 'Male', '', '0626693303', 'musamongi@gmail.com', 'Rukwa\r\nMtakumbuka', '', '', 'Penicillin, Eggs', 1, 6, NULL, '2026-07-27 18:19:37', '2026-07-27 18:19:37'),
(66, 'P-2026-0011', 'MSAFIR JUMA', '1992-09-10', 'Male', 'Married', '07165247181', 'msafirijuma@gmail.com', '', '', 'B-', 'Penicillin', 1, 6, 5, '2026-07-29 08:54:04', '2026-07-29 09:17:26'),
(67, 'P-2026-0012', 'MUSSA WAMBURA', '2003-03-19', 'Male', 'Single', '0716527819', 'mussawambura@gmail.com', '', '', 'B+', 'Wheat, Sulfa Drugs', 1, 6, 5, '2026-07-29 09:43:50', '2026-07-29 09:43:50'),
(68, 'P-2026-0013', 'KELVIN P. NASHON', '2003-03-12', 'Male', 'Single', '0766526243', 'kelvinb@gmail.com', 'TANZANIA', '', '', 'Sulfa Drugs, Milk', 1, 6, 5, '2026-07-29 11:03:36', '2026-07-29 11:03:36');

-- --------------------------------------------------------

--
-- Table structure for table `patient_bills`
--

CREATE TABLE `patient_bills` (
  `id` int(11) NOT NULL,
  `bill_number` varchar(50) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `visit_id` int(11) DEFAULT NULL,
  `prescription_id` int(11) DEFAULT NULL,
  `registration_fee` decimal(10,2) DEFAULT 0.00,
  `consultation_fee` decimal(10,2) DEFAULT 0.00,
  `lab_fees` decimal(10,2) DEFAULT 0.00,
  `medication_fees` decimal(10,2) DEFAULT 0.00,
  `other_fees` decimal(10,2) DEFAULT 0.00,
  `subtotal` decimal(10,2) DEFAULT 0.00,
  `discount_percent` decimal(5,2) DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `paid_amount` decimal(10,2) DEFAULT 0.00,
  `balance` decimal(10,2) DEFAULT 0.00,
  `status` enum('pending','partial','paid','cancelled') DEFAULT 'pending',
  `created_by` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patient_bills`
--

INSERT INTO `patient_bills` (`id`, `bill_number`, `patient_id`, `visit_id`, `prescription_id`, `registration_fee`, `consultation_fee`, `lab_fees`, `medication_fees`, `other_fees`, `subtotal`, `discount_percent`, `discount_amount`, `total_amount`, `paid_amount`, `balance`, `status`, `created_by`, `branch_id`, `created_at`, `updated_at`) VALUES
(115, 'BILL-20260727-0055-3889', 55, 92, NULL, 0.00, 15000.00, 0.00, 0.00, 0.00, 135000.00, 0.00, 5000.00, 135000.00, 130000.00, 0.00, 'paid', 6, 1, '2026-07-27 08:49:55', '2026-07-27 09:10:50'),
(116, 'BILL-PRES-20260727-0055-837', 55, 92, 41, 0.00, 0.00, 0.00, 0.00, 0.00, 450.00, 0.00, 50.00, 450.00, 400.00, 0.00, 'paid', 9, 1, '2026-07-27 10:44:58', '2026-07-27 10:45:27'),
(118, 'BILL-OTC-20260727-2415', 57, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 8000.00, 0.00, 500.00, 8000.00, 7500.00, 0.00, 'paid', 5, 1, '2026-07-27 11:11:54', '2026-07-27 11:12:27'),
(119, 'BILL-20260727-0058-2667', 58, 93, NULL, 0.00, 15000.00, 0.00, 0.00, 0.00, 114000.00, 0.00, 9000.00, 114000.00, 105000.00, 0.00, 'paid', 6, 1, '2026-07-27 12:47:03', '2026-07-27 12:55:34'),
(120, 'BILL-OTC-20260727-1705', 59, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 24000.00, 0.00, 2500.00, 24000.00, 21500.00, 0.00, 'paid', 5, 1, '2026-07-27 12:52:40', '2026-07-27 12:55:06'),
(121, 'BILL-PRES-20260727-0058-969', 58, 93, 42, 0.00, 0.00, 0.00, 0.00, 0.00, 24000.00, 0.00, 0.00, 24000.00, 24000.00, 0.00, 'paid', 5, 1, '2026-07-27 12:59:50', '2026-07-27 13:00:08'),
(122, 'BILL-OTC-20260727-8102', 60, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 8000.00, 0.00, 900.00, 7100.00, 7100.00, 0.00, 'paid', 9, 1, '2026-07-27 13:06:15', '2026-07-27 13:06:39'),
(123, 'BILL-OTC-20260727-1650', 61, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 25050.00, 0.00, 3000.00, 22050.00, 22050.00, 0.00, 'paid', 5, 1, '2026-07-27 13:08:14', '2026-07-27 13:08:34'),
(124, 'BILL-20260727-0062-9178', 62, 94, NULL, 0.00, 15000.00, 0.00, 0.00, 0.00, 80000.00, 0.00, 0.00, 80000.00, 0.00, 80000.00, 'pending', 6, 1, '2026-07-27 13:11:30', '2026-07-27 13:37:45'),
(125, 'BILL-20260727-0063-2247', 63, 95, NULL, 0.00, 15000.00, 0.00, 0.00, 0.00, 120000.00, 0.00, 0.00, 120000.00, 0.00, 120000.00, 'pending', 6, 1, '2026-07-27 14:31:59', '2026-07-27 14:32:44'),
(126, 'BILL-20260727-0062-9460', 62, 96, NULL, 0.00, 15000.00, 0.00, 0.00, 0.00, 55000.00, 0.00, 0.00, 55000.00, 0.00, 55000.00, 'pending', 6, 1, '2026-07-27 14:41:34', '2026-07-27 14:42:01'),
(127, 'BILL-20260727-000064', 64, 97, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 8000.00, 0.00, 0.00, 8000.00, 0.00, 8000.00, 'pending', 8, 1, '2026-07-27 17:45:26', '2026-07-27 17:45:26'),
(128, 'BILL-20260727-000065', 65, 98, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 8000.00, 0.00, 0.00, 8000.00, 0.00, 8000.00, 'pending', 8, 1, '2026-07-27 18:20:07', '2026-07-27 18:20:07'),
(129, 'BILL-20260729-0066-1508', 66, 99, NULL, 0.00, 10000.00, 0.00, 0.00, 0.00, 15000.00, 0.00, 0.00, 15000.00, 0.00, 15000.00, 'pending', 6, 1, '2026-07-29 09:17:26', '2026-07-29 10:43:29'),
(130, 'BILL-20260729-0067-7596', 67, 100, NULL, 0.00, 10000.00, 0.00, 0.00, 0.00, 102000.00, 0.00, 0.00, 102000.00, 102000.00, 0.00, 'paid', 6, 1, '2026-07-29 09:43:50', '2026-07-29 09:51:00'),
(131, 'BILL-PRES-20260729-0067', 67, 100, NULL, 0.00, 0.00, 0.00, 40000.00, 0.00, 0.00, 0.00, 0.00, 40000.00, 40000.00, 0.00, 'paid', 9, 1, '2026-07-29 10:00:12', '2026-07-29 10:01:42'),
(132, 'BILL-PRES-20260729-0066', 66, 99, NULL, 0.00, 0.00, 0.00, 80000.00, 0.00, 0.00, 0.00, 0.00, 80000.00, 80000.00, 0.00, 'paid', 9, 1, '2026-07-29 10:44:09', '2026-07-29 11:14:24'),
(133, 'BILL-20260729-000055', 55, 101, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'cancelled', 5, 1, '2026-07-29 10:49:20', '2026-07-29 11:41:44'),
(138, 'BILL-PRES-20260729-0055', 55, 101, NULL, 0.00, 0.00, 0.00, 40000.00, 0.00, 0.00, 0.00, 0.00, 40000.00, 40000.00, 0.00, 'paid', 5, 1, '2026-07-29 10:57:54', '2026-07-29 11:12:09'),
(139, 'BILL-20260729-0068-4146', 68, 102, NULL, 0.00, 10000.00, 0.00, 0.00, 0.00, 15000.00, 0.00, 0.00, 15000.00, 15000.00, 0.00, 'paid', 6, 1, '2026-07-29 11:03:36', '2026-07-29 11:11:55'),
(140, 'BILL-PRES-20260729-0068', 68, 102, NULL, 0.00, 0.00, 0.00, 3500.00, 0.00, 0.00, 0.00, 0.00, 3500.00, 3500.00, 0.00, 'paid', 9, 1, '2026-07-29 11:04:34', '2026-07-29 11:11:41');

-- --------------------------------------------------------

--
-- Table structure for table `patient_documents`
--

CREATE TABLE `patient_documents` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `visit_id` int(11) DEFAULT NULL,
  `document_number` varchar(50) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `document_type` enum('medical_record','referral_letter','lab_result','prescription','x_ray','scan','insurance','id_document','other') NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_verified` tinyint(1) DEFAULT 0,
  `verified_by` int(11) DEFAULT NULL,
  `verified_date` timestamp NULL DEFAULT NULL,
  `status` enum('active','archived','deleted') DEFAULT 'active',
  `branch_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `receipt_number` varchar(50) NOT NULL,
  `bill_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','m-pesa','airtel_money','tigo_pesa','halopesa','bank','card','other') DEFAULT 'cash',
  `reference_number` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `received_by` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `received_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `receipt_number`, `bill_id`, `patient_id`, `amount`, `payment_method`, `reference_number`, `notes`, `received_by`, `branch_id`, `received_at`) VALUES
(52, 'RCP-PARTIAL-20260727-2165', 115, 55, 100000.00, 'cash', NULL, 'Partial payment - Discount: TSh 5,000.00', 10, 1, '2026-07-27 09:08:43'),
(53, 'RCP-20260727-5041', 115, 55, 30000.00, 'cash', NULL, '', 10, 1, '2026-07-27 09:10:50'),
(54, 'RCP-20260727-2216', 116, 55, 400.00, 'cash', NULL, '', 10, 1, '2026-07-27 10:45:27'),
(55, 'RCP-20260727-5064', 118, 57, 7500.00, 'cash', NULL, '', 10, 1, '2026-07-27 11:12:27'),
(56, 'RCP-20260727-1400', 119, 58, 35000.00, 'cash', NULL, '', 10, 1, '2026-07-27 12:51:26'),
(57, 'RCP-20260727-5241', 120, 59, 21500.00, 'cash', NULL, '', 10, 1, '2026-07-27 12:55:06'),
(58, 'RCP-20260727-9782', 119, 58, 70000.00, 'cash', NULL, 'Discount: TSh 9,000.00', 10, 1, '2026-07-27 12:55:34'),
(59, 'RCP-20260727-3312', 121, 58, 24000.00, 'cash', NULL, '', 10, 1, '2026-07-27 13:00:08'),
(60, 'RCP-20260727-7399', 122, 60, 7100.00, 'cash', NULL, '', 10, 1, '2026-07-27 13:06:39'),
(61, 'RCP-20260727-7678', 123, 61, 22050.00, 'cash', NULL, '', 10, 1, '2026-07-27 13:08:34'),
(62, 'RCP-20260729-2397', 130, 67, 102000.00, 'cash', NULL, '', 11, 1, '2026-07-29 09:51:00'),
(63, 'RCP-20260729-9104', 131, 67, 40000.00, 'cash', NULL, '', 11, 1, '2026-07-29 10:01:42'),
(64, 'RCP-20260729-4661', 140, 68, 3500.00, 'cash', NULL, '', 11, 1, '2026-07-29 11:11:41'),
(65, 'RCP-20260729-4333', 139, 68, 15000.00, 'cash', NULL, '', 11, 1, '2026-07-29 11:11:55'),
(66, 'RCP-20260729-7511', 138, 55, 40000.00, 'cash', NULL, '', 11, 1, '2026-07-29 11:12:09'),
(67, 'RCP-20260729-3272', 132, 66, 80000.00, 'cash', NULL, '', 11, 1, '2026-07-29 11:14:24');

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `module` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pharmacy_sales`
--

CREATE TABLE `pharmacy_sales` (
  `id` int(11) NOT NULL,
  `sale_number` varchar(50) NOT NULL,
  `prescription_id` int(11) DEFAULT NULL,
  `patient_id` int(11) NOT NULL,
  `cashier_id` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `sale_type` enum('indoor','outdoor') NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `discount_percent` decimal(5,2) DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','card','insurance','m-pesa','other') DEFAULT 'cash',
  `payment_status` enum('pending','paid','cancelled') DEFAULT 'paid',
  `sale_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL,
  `visit_id` int(11) NOT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `patient_id` int(11) NOT NULL,
  `pharmacy_id` int(11) DEFAULT NULL,
  `prescription_number` varchar(50) NOT NULL,
  `diagnosis` text DEFAULT NULL,
  `medication` varchar(255) DEFAULT NULL,
  `dosage` varchar(50) DEFAULT NULL,
  `frequency` varchar(50) DEFAULT NULL,
  `duration` varchar(50) DEFAULT NULL,
  `route` varchar(50) DEFAULT NULL,
  `quantity` varchar(50) DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','confirmed','dispensed','cancelled') DEFAULT 'pending',
  `is_indoor` tinyint(1) DEFAULT 1,
  `branch_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `dispensed_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `prescriptions`
--

INSERT INTO `prescriptions` (`id`, `visit_id`, `doctor_id`, `patient_id`, `pharmacy_id`, `prescription_number`, `diagnosis`, `medication`, `dosage`, `frequency`, `duration`, `route`, `quantity`, `instructions`, `notes`, `status`, `is_indoor`, `branch_id`, `created_at`, `dispensed_at`, `updated_at`) VALUES
(41, 92, 5, 55, 9, 'PRES-20260727-0055-613', '', 'Amlodipine 5mg', '200', 'Twice Daily', '7', 'Oral', '1', 'After meals', NULL, 'dispensed', 1, 1, '2026-07-27 08:52:34', NULL, '2026-07-27 10:46:02'),
(42, 93, 5, 58, 9, 'PRES-20260727-0058-305', 'TYPHOID', 'Amoxicillin 250mg', '500', 'Twice Daily', '7', 'Oral', '30', 'After meals', NULL, 'dispensed', 1, 1, '2026-07-27 12:54:05', '2026-07-29 11:09:05', '2026-07-29 11:09:05'),
(43, 100, 5, 67, 9, 'PRES-20260729-0067-636', 'TYPHOID', 'ALBENDERZOL', '300', 'Twice Daily', '5', '', '10', 'After meals; In the evening', NULL, 'dispensed', 1, 1, '2026-07-29 09:49:27', '2026-07-29 11:10:54', '2026-07-29 11:10:54'),
(44, 99, 5, 66, 9, 'PRES-20260729-0066-755', '', 'ALBENDERZOL', '500', 'Twice Daily', '8', 'Oral', '20', 'Before meals; In the evening', NULL, 'dispensed', 1, 1, '2026-07-29 10:43:24', '2026-07-29 11:14:36', '2026-07-29 11:14:36'),
(45, 101, 5, 55, 9, 'PRES-20260729-0055-923', NULL, 'ALBENDERZOL', '400', 'Twice Daily', '7', 'Oral', '10', 'In the morning', NULL, 'dispensed', 1, 1, '2026-07-29 10:49:45', '2026-07-29 11:12:15', '2026-07-29 11:12:15'),
(46, 102, 5, 68, 9, 'PRES-20260729-0068-831', '', 'MSETO', '2', 'Twice Daily', '7', 'Oral', '1', 'Before meals; In the morning', NULL, 'dispensed', 1, 1, '2026-07-29 11:04:15', '2026-07-29 11:12:15', '2026-07-29 11:12:15');

-- --------------------------------------------------------

--
-- Table structure for table `prescription_items`
--

CREATE TABLE `prescription_items` (
  `id` int(11) NOT NULL,
  `prescription_id` int(11) NOT NULL,
  `inventory_id` int(11) DEFAULT NULL,
  `medication_name` varchar(100) NOT NULL,
  `dosage` varchar(50) DEFAULT NULL,
  `frequency` varchar(50) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `duration` varchar(50) DEFAULT NULL,
  `route` varchar(50) DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `unit_price` decimal(10,2) DEFAULT 0.00,
  `total_price` decimal(10,2) DEFAULT 0.00,
  `dispensed_at` timestamp NULL DEFAULT NULL,
  `dispensed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `prescription_items`
--

INSERT INTO `prescription_items` (`id`, `prescription_id`, `inventory_id`, `medication_name`, `dosage`, `frequency`, `quantity`, `duration`, `route`, `instructions`, `unit_price`, `total_price`, `dispensed_at`, `dispensed_by`, `created_at`) VALUES
(25, 41, NULL, 'Amlodipine 5mg', '200', 'Twice Daily', 1, '7', 'Oral', 'After meals', 450.00, 450.00, NULL, NULL, '2026-07-27 08:52:34'),
(26, 42, NULL, 'Amoxicillin 250mg', '500', 'Twice Daily', 30, '7', 'Oral', 'After meals', 800.00, 24000.00, '2026-07-29 11:09:05', 9, '2026-07-27 12:54:05'),
(27, 43, NULL, 'ALBENDERZOL', '300', 'Twice Daily', 10, '5', '', 'After meals; In the evening', 1500.00, 15000.00, NULL, NULL, '2026-07-29 09:49:27'),
(28, 44, NULL, 'ALBENDERZOL', '500', 'Twice Daily', 20, '8', 'Oral', 'Before meals; In the evening', 1500.00, 30000.00, NULL, NULL, '2026-07-29 10:43:24'),
(29, 45, NULL, 'ALBENDERZOL', '400', 'Twice Daily', 10, '7', 'Oral', 'In the morning', 1500.00, 15000.00, NULL, NULL, '2026-07-29 10:49:45'),
(30, 46, NULL, 'MSETO', '2', 'Twice Daily', 1, '7', 'Oral', 'Before meals; In the morning', 3500.00, 3500.00, NULL, NULL, '2026-07-29 11:04:15');

-- --------------------------------------------------------

--
-- Table structure for table `prescription_sales`
--

CREATE TABLE `prescription_sales` (
  `id` int(11) NOT NULL,
  `sale_number` varchar(50) NOT NULL,
  `prescription_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `visit_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `net_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `dispensed_by` int(11) NOT NULL,
  `status` enum('pending','dispensed','cancelled') DEFAULT 'pending',
  `payment_method` enum('cash','card','m-pesa','insurance','other') DEFAULT 'cash',
  `payment_status` enum('pending','paid','partial') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `dispensed_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prescription_sales`
--

INSERT INTO `prescription_sales` (`id`, `sale_number`, `prescription_id`, `patient_id`, `visit_id`, `doctor_id`, `total_amount`, `discount_amount`, `net_amount`, `dispensed_by`, `status`, `payment_method`, `payment_status`, `notes`, `branch_id`, `created_at`, `dispensed_at`, `updated_at`) VALUES
(1, 'SALE-20260729-000045', 45, 55, 101, 0, 40000.00, 0.00, 0.00, 9, 'dispensed', 'cash', 'paid', NULL, 1, '2026-07-29 11:12:15', '2026-07-29 11:12:15', '2026-07-29 11:12:15'),
(2, 'SALE-20260729-000046', 46, 68, 102, 0, 3500.00, 0.00, 0.00, 9, 'dispensed', 'cash', 'paid', NULL, 1, '2026-07-29 11:12:15', '2026-07-29 11:12:15', '2026-07-29 11:12:15'),
(3, 'SALE-20260729-000044', 44, 66, 99, 0, 80000.00, 0.00, 0.00, 9, 'dispensed', 'cash', 'paid', NULL, 1, '2026-07-29 11:14:36', '2026-07-29 11:14:36', '2026-07-29 11:14:36');

-- --------------------------------------------------------

--
-- Table structure for table `prescription_sale_items`
--

CREATE TABLE `prescription_sale_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `medicine_name` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `procedures`
--

CREATE TABLE `procedures` (
  `id` int(11) NOT NULL,
  `procedure_name` varchar(100) NOT NULL,
  `procedure_code` varchar(20) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `procedures`
--

INSERT INTO `procedures` (`id`, `procedure_name`, `procedure_code`, `category`, `branch_id`, `price`, `description`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Wound Wash', 'PROC-001', 'Wound Care', NULL, 15000.00, 'Cleaning and washing of wounds', 1, NULL, '2026-07-19 14:35:14', '2026-07-19 14:35:14'),
(2, 'Wound Dressing', 'PROC-002', 'Wound Care', NULL, 12000.00, 'Wound dressing and bandaging', 1, NULL, '2026-07-19 14:35:14', '2026-07-19 14:35:14'),
(3, 'Minor Surgery', 'PROC-003', 'Surgery', NULL, 30000.00, 'Minor surgical procedure', 1, NULL, '2026-07-19 14:35:14', '2026-07-19 14:35:14'),
(4, 'Major Surgery', 'PROC-004', 'Surgery', NULL, 80000.00, 'Major surgical procedure', 1, NULL, '2026-07-19 14:35:14', '2026-07-19 14:35:14'),
(5, 'Injection', 'PROC-005', 'Administration', NULL, 10000.00, 'Administration of injection', 1, NULL, '2026-07-19 14:35:14', '2026-07-19 14:35:14'),
(6, 'IV Cannulation', 'PROC-006', 'Administration', NULL, 15000.00, 'Intravenous cannulation', 1, NULL, '2026-07-19 14:35:14', '2026-07-19 14:35:14'),
(7, 'Suturing', 'PROC-007', 'Surgery', NULL, 25000.00, 'Wound suturing', 1, NULL, '2026-07-19 14:35:14', '2026-07-19 14:35:14'),
(8, 'Incision & Drainage', 'PROC-008', 'Surgery', NULL, 25000.00, 'Incision and drainage of abscess', 1, NULL, '2026-07-19 14:35:14', '2026-07-19 14:35:14'),
(9, 'Biopsy', 'PROC-009', 'Diagnostic', NULL, 40000.00, 'Tissue biopsy procedure', 1, NULL, '2026-07-19 14:35:14', '2026-07-19 14:35:14'),
(10, 'Cauterization', 'PROC-010', 'Surgery', NULL, 30000.00, 'Cauterization procedure', 1, NULL, '2026-07-19 14:35:14', '2026-07-19 14:35:14'),
(11, 'Catheterization', 'PROC-011', 'Procedure', NULL, 25000.00, 'Urinary catheterization', 1, NULL, '2026-07-19 14:35:14', '2026-07-19 14:35:14'),
(12, 'Chest Tube Insertion', 'PROC-012', 'Surgery', NULL, 50000.00, 'Chest tube insertion', 1, NULL, '2026-07-19 14:35:14', '2026-07-19 14:35:14'),
(13, 'Circumcision', 'PROC-013', 'Surgery', NULL, 45000.00, 'Male circumcision', 1, NULL, '2026-07-19 14:35:14', '2026-07-19 14:35:14'),
(14, 'POP Application', 'PROC-014', 'Orthopedics', NULL, 20000.00, 'Plaster of Paris application', 1, NULL, '2026-07-19 14:35:14', '2026-07-19 14:35:14'),
(15, 'Casting', 'PROC-015', 'Orthopedics', NULL, 25000.00, 'Casting procedure', 1, NULL, '2026-07-19 14:35:14', '2026-07-19 14:35:14');

-- --------------------------------------------------------

--
-- Table structure for table `procedure_tools`
--

CREATE TABLE `procedure_tools` (
  `id` int(11) NOT NULL,
  `procedure_name` varchar(100) NOT NULL,
  `tool_name` varchar(100) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `procedure_tools`
--

INSERT INTO `procedure_tools` (`id`, `procedure_name`, `tool_name`, `branch_id`, `price`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Injection', 'Syringe', NULL, 500.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(2, 'Injection', 'Needle', NULL, 300.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(3, 'Injection', 'Alcohol Swab', NULL, 100.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(4, 'Injection', 'Cotton Wool', NULL, 200.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(5, 'Injection', 'Bandage', NULL, 400.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(6, 'POP (Plaster of Paris)', 'POP Bandage', NULL, 2000.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(7, 'POP (Plaster of Paris)', 'Cotton Padding', NULL, 500.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(8, 'POP (Plaster of Paris)', 'Scissors', NULL, 1000.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(9, 'POP (Plaster of Paris)', 'Gloves', NULL, 300.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(10, 'POP (Plaster of Paris)', 'Water Basin', NULL, 800.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(11, 'Suturing', 'Suture Kit', NULL, 3000.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(12, 'Suturing', 'Needle Holder', NULL, 1500.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(13, 'Suturing', 'Scissors', NULL, 1000.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(14, 'Suturing', 'Tissue Forceps', NULL, 1200.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(15, 'Suturing', 'Local Anesthetic', NULL, 2500.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(16, 'Wound Dressing', 'Gauze', NULL, 300.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(17, 'Wound Dressing', 'Adhesive Tape', NULL, 200.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(18, 'Wound Dressing', 'Antiseptic', NULL, 500.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(19, 'Wound Dressing', 'Cotton Swab', NULL, 150.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(20, 'Wound Dressing', 'Bandage', NULL, 400.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(21, 'Incision & Drainage', 'Scalpel', NULL, 1500.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(22, 'Incision & Drainage', 'Forceps', NULL, 1000.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(23, 'Incision & Drainage', 'Scissors', NULL, 1000.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(24, 'Incision & Drainage', 'Drainage Tube', NULL, 2000.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(25, 'Incision & Drainage', 'Gauze', NULL, 300.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(26, 'Casting', 'Cast Material', NULL, 2500.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(27, 'Casting', 'Cotton Padding', NULL, 500.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(28, 'Casting', 'Scissors', NULL, 1000.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(29, 'Casting', 'Gloves', NULL, 300.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(30, 'Casting', 'Water Basin', NULL, 800.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(31, 'Biopsy', 'Biopsy Punch', NULL, 4000.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(32, 'Biopsy', 'Formalin Jar', NULL, 500.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(33, 'Biopsy', 'Suture Kit', NULL, 3000.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(34, 'Biopsy', 'Scissors', NULL, 1000.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(35, 'Biopsy', 'Local Anesthetic', NULL, 2500.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(36, 'Cauterization', 'Cautery Pen', NULL, 5000.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(37, 'Cauterization', 'Local Anesthetic', NULL, 2500.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(38, 'Cauterization', 'Gauze', NULL, 300.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(39, 'Cauterization', 'Scissors', NULL, 1000.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(40, 'Catheterization', 'Catheter Kit', NULL, 3500.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(41, 'Catheterization', 'Lubricant', NULL, 500.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(42, 'Catheterization', 'Gloves', NULL, 300.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(43, 'Catheterization', 'Drainage Bag', NULL, 2000.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(44, 'Chest Tube Insertion', 'Chest Tube', NULL, 5000.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(45, 'Chest Tube Insertion', 'Scalpel', NULL, 1500.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(46, 'Chest Tube Insertion', 'Forceps', NULL, 1000.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(47, 'Chest Tube Insertion', 'Suture Kit', NULL, 3000.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(48, 'Chest Tube Insertion', 'Drainage System', NULL, 4000.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(49, 'Joint Aspiration', 'Syringe', NULL, 500.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(50, 'Joint Aspiration', 'Needle', NULL, 300.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(51, 'Joint Aspiration', 'Local Anesthetic', NULL, 2500.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(52, 'Joint Aspiration', 'Gloves', NULL, 300.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(53, 'Joint Aspiration', 'Specimen Container', NULL, 500.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(54, 'Lumbar Puncture', 'Spinal Needle', NULL, 2000.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(55, 'Lumbar Puncture', 'Manometer', NULL, 3000.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(56, 'Lumbar Puncture', 'Local Anesthetic', NULL, 2500.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(57, 'Lumbar Puncture', 'Gloves', NULL, 300.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(58, 'Lumbar Puncture', 'Specimen Tubes', NULL, 800.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(59, 'Paracentesis', 'Paracentesis Kit', NULL, 4000.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(60, 'Paracentesis', 'Local Anesthetic', NULL, 2500.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(61, 'Paracentesis', 'Gloves', NULL, 300.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(62, 'Paracentesis', 'Drainage Bag', NULL, 2000.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(63, 'Thoracentesis', 'Thoracentesis Kit', NULL, 4500.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(64, 'Thoracentesis', 'Local Anesthetic', NULL, 2500.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(65, 'Thoracentesis', 'Gloves', NULL, 300.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(66, 'Thoracentesis', 'Specimen Container', NULL, 500.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(67, 'Skin Grafting', 'Graft Knife', NULL, 5000.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(68, 'Skin Grafting', 'Mesh Dermatome', NULL, 8000.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(69, 'Skin Grafting', 'Suture Kit', NULL, 3000.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(70, 'Skin Grafting', 'Bandage', NULL, 400.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(71, 'Skin Grafting', 'Gloves', NULL, 300.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(72, 'Circumcision', 'Circumcision Kit', NULL, 5000.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(73, 'Circumcision', 'Suture Kit', NULL, 3000.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(74, 'Circumcision', 'Local Anesthetic', NULL, 2500.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(75, 'Circumcision', 'Bandage', NULL, 400.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(76, 'Circumcision', 'Gloves', NULL, 300.00, 1, '2026-07-19 14:16:07', '2026-07-19 14:16:07'),
(77, 'Injection', 'Syringe', NULL, 500.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(78, 'Injection', 'Needle', NULL, 300.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(79, 'Injection', 'Alcohol Swab', NULL, 100.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(80, 'Injection', 'Cotton Wool', NULL, 200.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(81, 'Injection', 'Bandage', NULL, 400.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(82, 'POP (Plaster of Paris)', 'POP Bandage', NULL, 2000.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(83, 'POP (Plaster of Paris)', 'Cotton Padding', NULL, 500.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(84, 'POP (Plaster of Paris)', 'Scissors', NULL, 1000.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(85, 'POP (Plaster of Paris)', 'Gloves', NULL, 300.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(86, 'POP (Plaster of Paris)', 'Water Basin', NULL, 800.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(87, 'Suturing', 'Suture Kit', NULL, 3000.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(88, 'Suturing', 'Needle Holder', NULL, 1500.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(89, 'Suturing', 'Scissors', NULL, 1000.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(90, 'Suturing', 'Tissue Forceps', NULL, 1200.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(91, 'Suturing', 'Local Anesthetic', NULL, 2500.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(92, 'Wound Dressing', 'Gauze', NULL, 300.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(93, 'Wound Dressing', 'Adhesive Tape', NULL, 200.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(94, 'Wound Dressing', 'Antiseptic', NULL, 500.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(95, 'Wound Dressing', 'Cotton Swab', NULL, 150.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(96, 'Wound Dressing', 'Bandage', NULL, 400.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(97, 'Incision & Drainage', 'Scalpel', NULL, 1500.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(98, 'Incision & Drainage', 'Forceps', NULL, 1000.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(99, 'Incision & Drainage', 'Scissors', NULL, 1000.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(100, 'Incision & Drainage', 'Drainage Tube', NULL, 2000.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(101, 'Incision & Drainage', 'Gauze', NULL, 300.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(102, 'Casting', 'Cast Material', NULL, 2500.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(103, 'Casting', 'Cotton Padding', NULL, 500.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(104, 'Casting', 'Scissors', NULL, 1000.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(105, 'Casting', 'Gloves', NULL, 300.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(106, 'Casting', 'Water Basin', NULL, 800.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(107, 'Biopsy', 'Biopsy Punch', NULL, 4000.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(108, 'Biopsy', 'Formalin Jar', NULL, 500.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(109, 'Biopsy', 'Suture Kit', NULL, 3000.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(110, 'Biopsy', 'Scissors', NULL, 1000.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(111, 'Biopsy', 'Local Anesthetic', NULL, 2500.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(112, 'Cauterization', 'Cautery Pen', NULL, 5000.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(113, 'Cauterization', 'Local Anesthetic', NULL, 2500.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(114, 'Cauterization', 'Gauze', NULL, 300.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(115, 'Cauterization', 'Scissors', NULL, 1000.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(116, 'Catheterization', 'Catheter Kit', NULL, 3500.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(117, 'Catheterization', 'Lubricant', NULL, 500.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(118, 'Catheterization', 'Gloves', NULL, 300.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(119, 'Catheterization', 'Drainage Bag', NULL, 2000.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(120, 'Chest Tube Insertion', 'Chest Tube', NULL, 5000.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(121, 'Chest Tube Insertion', 'Scalpel', NULL, 1500.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(122, 'Chest Tube Insertion', 'Forceps', NULL, 1000.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(123, 'Chest Tube Insertion', 'Suture Kit', NULL, 3000.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(124, 'Chest Tube Insertion', 'Drainage System', NULL, 4000.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(125, 'Joint Aspiration', 'Syringe', NULL, 500.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(126, 'Joint Aspiration', 'Needle', NULL, 300.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(127, 'Joint Aspiration', 'Local Anesthetic', NULL, 2500.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(128, 'Joint Aspiration', 'Gloves', NULL, 300.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(129, 'Joint Aspiration', 'Specimen Container', NULL, 500.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(130, 'Lumbar Puncture', 'Spinal Needle', NULL, 2000.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(131, 'Lumbar Puncture', 'Manometer', NULL, 3000.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(132, 'Lumbar Puncture', 'Local Anesthetic', NULL, 2500.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(133, 'Lumbar Puncture', 'Gloves', NULL, 300.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(134, 'Lumbar Puncture', 'Specimen Tubes', NULL, 800.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(135, 'Paracentesis', 'Paracentesis Kit', NULL, 4000.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(136, 'Paracentesis', 'Local Anesthetic', NULL, 2500.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(137, 'Paracentesis', 'Gloves', NULL, 300.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(138, 'Paracentesis', 'Drainage Bag', NULL, 2000.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(139, 'Thoracentesis', 'Thoracentesis Kit', NULL, 4500.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(140, 'Thoracentesis', 'Local Anesthetic', NULL, 2500.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(141, 'Thoracentesis', 'Gloves', NULL, 300.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(142, 'Thoracentesis', 'Specimen Container', NULL, 500.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(143, 'Skin Grafting', 'Graft Knife', NULL, 5000.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(144, 'Skin Grafting', 'Mesh Dermatome', NULL, 8000.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(145, 'Skin Grafting', 'Suture Kit', NULL, 3000.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(146, 'Skin Grafting', 'Bandage', NULL, 400.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(147, 'Skin Grafting', 'Gloves', NULL, 300.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(148, 'Circumcision', 'Circumcision Kit', NULL, 5000.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(149, 'Circumcision', 'Suture Kit', NULL, 3000.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(150, 'Circumcision', 'Local Anesthetic', NULL, 2500.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(151, 'Circumcision', 'Bandage', NULL, 400.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34'),
(152, 'Circumcision', 'Gloves', NULL, 300.00, 1, '2026-07-19 14:20:34', '2026-07-19 14:20:34');

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
  `downloaded_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `receipts`
--

INSERT INTO `receipts` (`id`, `receipt_number`, `payment_id`, `bill_id`, `patient_id`, `branch_id`, `receipt_data`, `printed_by`, `printed_at`, `downloaded_at`) VALUES
(10, 'REC-20260727-000122', 60, 122, 0, NULL, '{\"bill_number\":\"BILL-OTC-20260727-8102\",\"patient_name\":\"ADELA\",\"total_amount\":\"7100.00\",\"paid_amount\":\"7100.00\",\"balance\":\"0.00\",\"items\":[{\"id\":410,\"bill_id\":122,\"item_type\":\"medication\",\"item_id\":null,\"item_name\":\"Amoxicillin 250mg\",\"quantity\":10,\"unit_price\":\"800.00\",\"total_price\":\"8000.00\",\"payment_status\":\"paid\",\"is_paid\":1,\"status\":\"pending\",\"paid_at\":\"2026-07-27 16:06:39\",\"created_at\":\"2026-07-27 16:06:15\",\"amount\":\"0.00\",\"description\":null,\"department\":null,\"service_type\":null,\"reference_id\":null}],\"payment_method\":\"cash\",\"printed_at\":\"2026-07-27 16:06:45\"}', 10, '2026-07-27 13:06:45', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `referrals`
--

CREATE TABLE `referrals` (
  `id` int(11) NOT NULL,
  `referral_number` varchar(50) NOT NULL,
  `visit_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `from_doctor_id` int(11) NOT NULL,
  `referral_type` enum('internal','external') NOT NULL DEFAULT 'internal',
  `to_doctor_id` int(11) DEFAULT NULL COMMENT 'Internal referral - doctor within dispensary',
  `to_hospital_name` varchar(200) DEFAULT NULL COMMENT 'External referral - hospital name',
  `to_hospital_address` text DEFAULT NULL COMMENT 'External referral - hospital address',
  `to_hospital_phone` varchar(20) DEFAULT NULL COMMENT 'External referral - hospital phone',
  `reason` text NOT NULL,
  `clinical_notes` text DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `treatment_given` text DEFAULT NULL,
  `urgency` enum('routine','urgent','emergency') DEFAULT 'routine',
  `status` enum('pending','accepted','completed','rejected','cancelled') DEFAULT 'pending',
  `referral_date` datetime NOT NULL DEFAULT current_timestamp(),
  `accepted_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `referral_logs`
--

CREATE TABLE `referral_logs` (
  `id` int(11) NOT NULL,
  `referral_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `performed_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(1, 'admin', 'Super Administrator - Full system access', 0, '2026-07-29 12:52:48', '2026-07-29 12:52:48'),
(2, 'reception', 'Receptionist - Patient registration and appointments', 0, '2026-07-29 12:52:48', '2026-07-29 12:52:48'),
(3, 'cashier', 'Cashier - Handle payments and billing', 0, '2026-07-29 12:52:48', '2026-07-29 12:52:48'),
(4, 'doctor', 'Doctor - Patient consultation and prescriptions', 0, '2026-07-29 12:52:48', '2026-07-29 12:52:48'),
(5, 'laboratory', 'Laboratory Technician - Lab tests and results', 0, '2026-07-29 12:52:48', '2026-07-29 12:52:48'),
(6, 'pharmacy', 'Pharmacist - Medicine dispensing and inventory', 0, '2026-07-29 12:52:48', '2026-07-29 12:52:48');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `medication_name` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(1, 1, 'Registration Fee', 'New patient registration', NULL, 10000.00, 'each', 1, 0, NULL, '2026-07-16 11:31:11', '2026-07-16 11:31:11'),
(2, 1, 'Re-registration', 'Existing patient re-registration', NULL, 5000.00, 'each', 1, 0, NULL, '2026-07-16 11:31:11', '2026-07-16 11:31:11'),
(3, 2, 'General Consultation', 'Standard doctor consultation', NULL, 15000.00, 'each', 1, 0, NULL, '2026-07-16 11:31:11', '2026-07-16 11:31:11'),
(4, 2, 'Follow-up Consultation', 'Follow-up visit', NULL, 10000.00, 'each', 1, 0, NULL, '2026-07-16 11:31:11', '2026-07-16 11:31:11'),
(5, 2, 'Consultation-B', 'Emergency visit', NULL, 25000.00, 'each', 1, 0, NULL, '2026-07-16 11:31:11', '2026-07-20 07:17:58'),
(6, 2, 'Specialist Consultation', 'Specialist doctor visit', NULL, 30000.00, 'each', 1, 0, NULL, '2026-07-16 11:31:11', '2026-07-16 11:31:11'),
(7, 3, 'Blood Test - Full', 'Complete blood count', NULL, 15000.00, 'each', 1, 0, NULL, '2026-07-16 11:31:11', '2026-07-16 11:31:11'),
(8, 3, 'Blood Test - Basic', 'Basic blood test', NULL, 8000.00, 'each', 1, 0, NULL, '2026-07-16 11:31:11', '2026-07-16 11:31:11'),
(9, 3, 'Urine Test', 'Urinalysis', NULL, 10000.00, 'each', 1, 0, NULL, '2026-07-16 11:31:11', '2026-07-16 11:31:11'),
(10, 3, 'Malaria Test', 'Malaria rapid test', NULL, 5000.00, 'each', 1, 0, NULL, '2026-07-16 11:31:11', '2026-07-16 11:31:11'),
(11, 3, 'COVID-19 Test', 'COVID-19 rapid test', NULL, 15000.00, 'each', 1, 0, NULL, '2026-07-16 11:31:11', '2026-07-16 11:31:11'),
(12, 3, 'X-Ray', 'X-Ray imaging', NULL, 35000.00, 'each', 1, 0, NULL, '2026-07-16 11:31:11', '2026-07-16 11:31:11'),
(13, 3, 'Ultrasound', 'Ultrasound scan', NULL, 50000.00, 'each', 1, 0, NULL, '2026-07-16 11:31:11', '2026-07-16 11:31:11'),
(14, 4, 'Prescription Charge', 'Prescription handling fee', NULL, 5000.00, 'each', 1, 0, NULL, '2026-07-16 11:31:11', '2026-07-16 11:31:11'),
(15, 5, 'Minor Procedure', 'Minor medical procedure', NULL, 20000.00, 'each', 1, 0, NULL, '2026-07-16 11:31:11', '2026-07-16 11:31:11'),
(16, 5, 'Major Procedure', 'Major medical procedure', NULL, 50000.00, 'each', 1, 0, NULL, '2026-07-16 11:31:11', '2026-07-16 11:31:11'),
(17, 2, 'New Patient', '', 1, 10000.00, 'each', 1, 0, 8, '2026-07-29 09:00:39', '2026-07-29 09:00:39');

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
-- Table structure for table `service_fees`
--

CREATE TABLE `service_fees` (
  `id` int(11) NOT NULL,
  `service_name` varchar(100) NOT NULL,
  `service_code` varchar(20) NOT NULL,
  `category` enum('registration','consultation','lab_test','medication','procedure','other') NOT NULL,
  `fee_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `branch_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `service_fees`
--
DELIMITER $$
CREATE TRIGGER `before_insert_service_fees` BEFORE INSERT ON `service_fees` FOR EACH ROW BEGIN
            IF NEW.service_code IS NULL OR NEW.service_code = '' THEN
                SET NEW.service_code = generate_service_code(NEW.category, NEW.branch_id);
            END IF;
        END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `sale_type` enum('prescription','otc') NOT NULL,
  `sale_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `previous_stock` int(11) NOT NULL,
  `new_stock` int(11) NOT NULL,
  `movement_type` enum('out','in') DEFAULT 'out',
  `performed_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `inventory_id`, `sale_type`, `sale_id`, `quantity`, `previous_stock`, `new_stock`, `movement_type`, `performed_by`, `notes`, `created_at`) VALUES
(4, 2, 'otc', 3, 10, 0, 0, 'out', 5, 'OTC Sale - Bill sent to Cashier', '2026-07-27 11:11:54'),
(5, 2, 'otc', 4, 30, 0, 0, 'out', 5, 'OTC Sale - Bill sent to Cashier', '2026-07-27 12:52:40'),
(6, 2, 'otc', 5, 10, 0, 0, 'out', 9, 'OTC Sale - Bill sent to Cashier', '2026-07-27 13:06:15'),
(7, 17, 'otc', 6, 10, 0, 0, 'out', 5, 'OTC Sale - Bill sent to Cashier', '2026-07-27 13:08:14'),
(8, 8, 'otc', 6, 1, 0, 0, 'out', 5, 'OTC Sale - Bill sent to Cashier', '2026-07-27 13:08:14'),
(9, 8, 'otc', 6, 1, 0, 0, 'out', 5, 'OTC Sale - Bill sent to Cashier', '2026-07-27 13:08:14');

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
(1, 'site_name', 'Braick Dispensary', 'general', 'Name of the facility', 1, '2026-07-16 11:35:58', '2026-07-16 11:35:58'),
(2, 'site_logo', '/uploads/braick_logo.png', 'general', 'Logo path', 1, '2026-07-16 11:35:58', '2026-07-16 11:35:58'),
(3, 'registration_fee', '5000.00', 'fees', 'Default registration fee', 1, '2026-07-16 11:35:58', '2026-07-16 11:35:58'),
(4, 'consultation_fee', '10000.00', 'fees', 'Default consultation fee', 1, '2026-07-16 11:35:58', '2026-07-16 11:35:58'),
(5, 'currency', 'TSh', 'general', 'Currency symbol', 1, '2026-07-16 11:35:58', '2026-07-16 11:35:58'),
(6, 'tax_percent', '0.00', 'financial', 'Tax percentage applied to all services', 1, '2026-07-16 11:35:58', '2026-07-16 11:35:58'),
(7, 'max_discount_percent', '20.00', 'financial', 'Maximum discount percentage allowed', 1, '2026-07-16 11:35:58', '2026-07-16 11:35:58'),
(8, 'business_hours_start', '08:00', 'general', 'Business hours start', 1, '2026-07-16 11:35:58', '2026-07-16 11:35:58'),
(9, 'business_hours_end', '18:00', 'general', 'Business hours end', 1, '2026-07-16 11:35:58', '2026-07-16 11:35:58');

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
(4, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Admin', 'admin@braick.com', '+255 700 000 000', 'admin', 1, NULL, 0, NULL, NULL, 'active', '2026-07-16 11:29:40', '2026-07-16 11:29:40'),
(5, 'dr.john', '$2y$10$Yg.0F8VQ29XRnnMX.jCz7e6KIN5wchqnsOxJDHp6riz5hbl17TQwK', 'Dr. John Mushi', 'john@braick.com', '+255 700 000 011', 'doctor', 1, 'General Medicine', 1, '2026-07-29 09:42:35', NULL, 'active', '2026-07-16 11:29:41', '2026-07-29 09:42:35'),
(6, 'dr.grace', '$2y$10$Yg.0F8VQ29XRnnMX.jCz7e6KIN5wchqnsOxJDHp6riz5hbl17TQwK', 'Dr. Grace Peter', 'grace@braick.com', '+255 700 000 012', 'doctor', 1, 'Pediatrics', 1, '2026-07-16 15:32:43', 'reception_6_1784212652.png', 'active', '2026-07-16 11:29:41', '2026-07-16 15:32:43'),
(7, 'dr.david', '$2y$10$Yg.0F8VQ29XRnnMX.jCz7e6KIN5wchqnsOxJDHp6riz5hbl17TQwK', 'Dr. David Mwanga', 'david@braick.com', '+255 700 000 013', 'doctor', 1, 'Cardiology', 1, '2026-07-16 15:15:56', NULL, 'active', '2026-07-16 11:29:41', '2026-07-16 15:15:56'),
(8, 'lab.dodoma', '$2y$10$Yg.0F8VQ29XRnnMX.jCz7e6KIN5wchqnsOxJDHp6riz5hbl17TQwK', 'Lab Technician Dodoma', 'lab.dodoma@braick.com', '+255 700 000 014', 'laboratory', 1, NULL, 0, NULL, 'user_8_1784535496.png', 'active', '2026-07-16 11:29:41', '2026-07-20 08:18:16'),
(9, 'pharm.dodoma', '$2y$10$Yg.0F8VQ29XRnnMX.jCz7e6KIN5wchqnsOxJDHp6riz5hbl17TQwK', 'Pharmacy Dodoma', 'pharm.dodoma@braick.com', '+255 700 000 015', 'pharmacy', 1, NULL, 0, NULL, NULL, 'active', '2026-07-16 11:29:41', '2026-07-16 12:27:31'),
(10, 'cashier.dodoma', '$2y$10$Yg.0F8VQ29XRnnMX.jCz7e6KIN5wchqnsOxJDHp6riz5hbl17TQwK', 'Rose Mwangi', 'cashier.dodoma@braick.com', '+255 700 000 016', 'cashier', 1, NULL, 0, NULL, 'cashier_10_1784371113.png', 'active', '2026-07-16 11:29:41', '2026-07-18 10:38:38'),
(11, 'reception.rose', '$2y$10$Yg.0F8VQ29XRnnMX.jCz7e6KIN5wchqnsOxJDHp6riz5hbl17TQwK', 'Rose Mwangi', 'rose@braick.com', '+255 700 000 005', 'reception', 1, NULL, 1, '2026-07-16 12:27:43', NULL, 'active', '2026-07-16 11:29:41', '2026-07-16 12:27:43'),
(12, 'dr.anna', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Anna Kivuyo', 'anna@braick.com', '+255 700 000 021', 'doctor', 2, 'Obstetrics', 1, NULL, NULL, 'active', '2026-07-16 11:29:41', '2026-07-27 15:53:12'),
(13, 'dr.peter', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Peter Lema', 'peter@braick.com', '+255 700 000 022', 'doctor', 2, 'Surgery', 1, NULL, NULL, 'active', '2026-07-16 11:29:41', '2026-07-27 15:53:12'),
(14, 'lab.arusha', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Lab Technician Arusha', 'lab.arusha@braick.com', '+255 700 000 023', 'laboratory', 2, NULL, 0, NULL, NULL, 'active', '2026-07-16 11:29:41', '2026-07-16 11:29:41'),
(15, 'pharm.arusha', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Pharmacy Arusha', 'pharm.arusha@braick.com', '+255 700 000 024', 'pharmacy', 2, NULL, 0, NULL, NULL, 'active', '2026-07-16 11:29:41', '2026-07-16 11:29:41'),
(16, 'cashier.arusha', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Cashier Arusha', 'cashier.arusha@braick.com', '+255 700 000 025', 'cashier', 2, NULL, 0, NULL, NULL, 'active', '2026-07-16 11:29:41', '2026-07-16 11:29:41'),
(17, 'reception.arusha', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Reception Arusha', 'reception.arusha@braick.com', '+255 700 000 026', 'reception', 2, NULL, 0, NULL, NULL, 'active', '2026-07-16 11:29:41', '2026-07-16 11:29:41'),
(18, 'dr.sarah', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Sarah Mwamba', 'sarah@braick.com', '+255 700 000 031', 'doctor', 3, 'Cardiology', 1, NULL, NULL, 'active', '2026-07-16 11:29:41', '2026-07-27 15:53:12'),
(19, 'dr.james', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. James Kato', 'james@braick.com', '+255 700 000 032', 'doctor', 3, 'Neurology', 1, NULL, NULL, 'active', '2026-07-16 11:29:41', '2026-07-27 15:53:12'),
(20, 'dr.mary', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Mary Ndugu', 'mary@braick.com', '+255 700 000 033', 'doctor', 3, 'Pediatrics', 1, NULL, NULL, 'active', '2026-07-16 11:29:41', '2026-07-27 15:53:12'),
(21, 'lab.dar', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Lab Technician Dar', 'lab.dar@braick.com', '+255 700 000 034', 'laboratory', 3, NULL, 0, NULL, NULL, 'active', '2026-07-16 11:29:41', '2026-07-16 11:29:41'),
(22, 'pharm.dar', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Pharmacy Dar', 'pharm.dar@braick.com', '+255 700 000 035', 'pharmacy', 3, NULL, 0, NULL, NULL, 'active', '2026-07-16 11:29:41', '2026-07-16 11:29:41'),
(23, 'cashier.dar', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Cashier Dar', 'cashier.dar@braick.com', '+255 700 000 036', 'cashier', 3, NULL, 0, NULL, NULL, 'active', '2026-07-16 11:29:41', '2026-07-16 11:29:41'),
(24, 'reception.dar', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Reception Dar', 'reception.dar@braick.com', '+255 700 000 037', 'reception', 3, NULL, 0, NULL, NULL, 'active', '2026-07-16 11:29:41', '2026-07-16 11:29:41');

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
  `receptionist_id` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `visit_type` enum('new','follow-up','emergency') DEFAULT 'new',
  `status` enum('pending','assigned','with_doctor','lab_test','prescribed','completed','cancelled') DEFAULT 'pending',
  `symptoms` text DEFAULT NULL,
  `complaint` text DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `treatment` text DEFAULT NULL,
  `follow_up_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `referred_to` int(11) DEFAULT NULL,
  `is_referred` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `registration_fee` decimal(10,2) DEFAULT 0.00,
  `consultation_fee` decimal(10,2) DEFAULT 0.00,
  `lab_fees_total` decimal(10,2) DEFAULT 0.00,
  `pharmacy_fees_total` decimal(10,2) DEFAULT 0.00,
  `other_fees_total` decimal(10,2) DEFAULT 0.00,
  `visit_total` decimal(10,2) DEFAULT 0.00,
  `payment_status` enum('pending','partial','paid','cancelled') DEFAULT 'pending',
  `is_completed` tinyint(1) DEFAULT 0,
  `completed_at` timestamp NULL DEFAULT NULL,
  `total_discount` decimal(10,2) DEFAULT 0.00,
  `discount_percent` decimal(5,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `visits`
--

INSERT INTO `visits` (`id`, `visit_number`, `visit_date`, `patient_id`, `doctor_id`, `receptionist_id`, `branch_id`, `visit_type`, `status`, `symptoms`, `complaint`, `diagnosis`, `treatment`, `follow_up_date`, `notes`, `referred_to`, `is_referred`, `created_at`, `updated_at`, `registration_fee`, `consultation_fee`, `lab_fees_total`, `pharmacy_fees_total`, `other_fees_total`, `visit_total`, `payment_status`, `is_completed`, `completed_at`, `total_discount`, `discount_percent`) VALUES
(92, 'VIS-20260727-0055', '2026-07-27 11:49:37', 55, 5, 6, 1, 'new', 'completed', '', '', '', '', NULL, '', NULL, 0, '2026-07-27 08:49:37', '2026-07-27 09:11:07', 0.00, 15000.00, 0.00, 0.00, 0.00, 0.00, 'pending', 1, '2026-07-27 09:11:07', 0.00, 0.00),
(93, 'VIS-20260727-0058', '2026-07-27 15:46:23', 58, 5, 6, 1, 'new', 'completed', '', '', 'TYPHOID', '', NULL, '', NULL, 0, '2026-07-27 12:46:23', '2026-07-27 13:00:26', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', 1, '2026-07-27 13:00:26', 0.00, 0.00),
(94, 'VIS-20260727-0062', '2026-07-27 16:11:07', 62, 5, 6, 1, 'new', 'prescribed', '', '', NULL, NULL, NULL, '', NULL, 0, '2026-07-27 13:11:07', '2026-07-29 09:44:17', 0.00, 15000.00, 0.00, 0.00, 0.00, 0.00, 'pending', 0, NULL, 0.00, 0.00),
(95, 'VIS-20260727-0063', '2026-07-27 17:31:39', 63, 5, 6, 1, 'new', 'prescribed', '', '', NULL, NULL, NULL, '', NULL, 0, '2026-07-27 14:31:39', '2026-07-29 09:44:17', 0.00, 15000.00, 0.00, 0.00, 0.00, 0.00, 'pending', 0, NULL, 0.00, 0.00),
(96, 'VIS-20260727-4067', '2026-07-27 17:41:34', 62, 5, 6, 1, 'new', 'prescribed', '', '', NULL, NULL, NULL, '', NULL, 0, '2026-07-27 14:41:34', '2026-07-29 09:51:11', 0.00, 15000.00, 0.00, 0.00, 0.00, 0.00, 'pending', 0, NULL, 0.00, 0.00),
(97, 'VIS-20260727-0064', '2026-07-27 20:44:55', 64, NULL, 6, 1, 'new', 'completed', '', '', NULL, NULL, NULL, '', NULL, 0, '2026-07-27 17:44:55', '2026-07-27 18:18:32', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', 0, '2026-07-27 18:18:32', 0.00, 0.00),
(98, 'VIS-20260727-0065', '2026-07-27 21:19:37', 65, NULL, 6, 1, 'new', 'completed', '', '', NULL, NULL, NULL, '', NULL, 0, '2026-07-27 18:19:37', '2026-07-27 18:20:27', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', 0, '2026-07-27 18:20:27', 0.00, 0.00),
(99, 'VIS-20260729-0066', '2026-07-29 11:54:04', 66, 5, 6, 1, '', 'prescribed', '', '', '', '', NULL, '', NULL, 0, '2026-07-29 08:54:04', '2026-07-29 10:43:29', 0.00, 15000.00, 0.00, 0.00, 0.00, 0.00, 'pending', 0, NULL, 0.00, 0.00),
(100, 'VIS-20260729-0067', '2026-07-29 12:43:50', 67, 5, 6, 1, 'new', 'completed', '', '', 'TYPHOID', '', NULL, '', NULL, 0, '2026-07-29 09:43:50', '2026-07-29 09:51:09', 0.00, 15000.00, 0.00, 0.00, 0.00, 0.00, 'pending', 1, '2026-07-29 09:51:09', 0.00, 0.00),
(101, 'VIS-20260729-9152', '2026-07-29 13:48:46', 55, 5, 6, 1, '', 'assigned', '', '', NULL, NULL, NULL, '', NULL, 0, '2026-07-29 10:48:46', '2026-07-29 10:49:12', 0.00, 10000.00, 0.00, 0.00, 0.00, 0.00, 'pending', 0, NULL, 0.00, 0.00),
(102, 'VIS-20260729-0068', '2026-07-29 14:03:36', 68, 5, 6, 1, 'new', 'prescribed', '', '', '', '', NULL, '', NULL, 0, '2026-07-29 11:03:36', '2026-07-29 11:04:25', 0.00, 15000.00, 0.00, 0.00, 0.00, 0.00, 'pending', 0, NULL, 0.00, 0.00);

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
  `temperature` decimal(4,1) DEFAULT NULL COMMENT 'Temperature in Celsius',
  `blood_pressure_systolic` int(11) DEFAULT NULL COMMENT 'Systolic BP (mmHg)',
  `blood_pressure_diastolic` int(11) DEFAULT NULL COMMENT 'Diastolic BP (mmHg)',
  `pulse_rate` int(11) DEFAULT NULL COMMENT 'Pulse rate (bpm)',
  `respiratory_rate` int(11) DEFAULT NULL COMMENT 'Respiratory rate (breaths/min)',
  `oxygen_saturation` int(11) DEFAULT NULL COMMENT 'SpO2 (%)',
  `blood_glucose` decimal(5,1) DEFAULT NULL COMMENT 'Blood glucose (mg/dL)',
  `weight` decimal(5,2) DEFAULT NULL COMMENT 'Weight (kg)',
  `height` decimal(5,2) DEFAULT NULL COMMENT 'Height (cm)',
  `bmi` decimal(4,1) DEFAULT NULL COMMENT 'BMI (calculated)',
  `muac` decimal(4,1) DEFAULT NULL COMMENT 'MUAC (cm)',
  `pain_score` int(11) DEFAULT NULL COMMENT 'Pain score (0-10)',
  `notes` text DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vital_signs`
--

INSERT INTO `vital_signs` (`id`, `patient_id`, `visit_id`, `appointment_id`, `recorded_by`, `branch_id`, `temperature`, `blood_pressure_systolic`, `blood_pressure_diastolic`, `pulse_rate`, `respiratory_rate`, `oxygen_saturation`, `blood_glucose`, `weight`, `height`, `bmi`, `muac`, `pain_score`, `notes`, `recorded_at`, `created_at`, `updated_at`) VALUES
(21, 55, 101, NULL, 6, 1, 35.0, 120, NULL, 60, NULL, NULL, NULL, 74.00, 180.00, 22.8, NULL, NULL, NULL, '2026-07-29 10:48:46', '2026-07-29 10:48:46', '2026-07-29 10:48:46');

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_patient_visit_fees`
-- (See below for the actual view)
--
CREATE TABLE `v_patient_visit_fees` (
`patient_id` int(11)
,`patient_number` varchar(50)
,`full_name` varchar(100)
,`phone` varchar(20)
,`visit_id` int(11)
,`visit_number` varchar(50)
,`visit_date` datetime
,`visit_type` enum('new','follow-up','emergency')
,`visit_status` enum('pending','assigned','with_doctor','lab_test','prescribed','completed','cancelled')
,`registration_fee` decimal(10,2)
,`consultation_fee` decimal(10,2)
,`lab_fees_total` decimal(10,2)
,`pharmacy_fees_total` decimal(10,2)
,`other_fees_total` decimal(10,2)
,`total_fees` decimal(10,2)
,`discount_percent` decimal(5,2)
,`total_discount` decimal(10,2)
,`net_payable` decimal(11,2)
,`payment_status` enum('pending','partial','paid','cancelled')
,`doctor_name` varchar(100)
,`branch_name` varchar(100)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_service_fees_summary`
-- (See below for the actual view)
--
CREATE TABLE `v_service_fees_summary` (
`category` enum('registration','consultation','lab_test','medication','procedure','other')
,`total_services` bigint(21)
,`total_fees` decimal(32,2)
,`average_fee` decimal(14,6)
,`min_fee` decimal(10,2)
,`max_fee` decimal(10,2)
,`active_services` decimal(22,0)
);

-- --------------------------------------------------------

--
-- Structure for view `v_patient_visit_fees`
--
DROP TABLE IF EXISTS `v_patient_visit_fees`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_patient_visit_fees`  AS SELECT `p`.`id` AS `patient_id`, `p`.`patient_id` AS `patient_number`, `p`.`full_name` AS `full_name`, `p`.`phone` AS `phone`, `v`.`id` AS `visit_id`, `v`.`visit_number` AS `visit_number`, `v`.`visit_date` AS `visit_date`, `v`.`visit_type` AS `visit_type`, `v`.`status` AS `visit_status`, `v`.`registration_fee` AS `registration_fee`, `v`.`consultation_fee` AS `consultation_fee`, `v`.`lab_fees_total` AS `lab_fees_total`, `v`.`pharmacy_fees_total` AS `pharmacy_fees_total`, `v`.`other_fees_total` AS `other_fees_total`, `v`.`visit_total` AS `total_fees`, `v`.`discount_percent` AS `discount_percent`, `v`.`total_discount` AS `total_discount`, `v`.`visit_total`- `v`.`total_discount` AS `net_payable`, `v`.`payment_status` AS `payment_status`, `u`.`full_name` AS `doctor_name`, `b`.`name` AS `branch_name` FROM (((`patients` `p` left join `visits` `v` on(`p`.`id` = `v`.`patient_id`)) left join `users` `u` on(`v`.`doctor_id` = `u`.`id`)) left join `branches` `b` on(`v`.`branch_id` = `b`.`id`)) WHERE `v`.`status` <> 'cancelled' ;

-- --------------------------------------------------------

--
-- Structure for view `v_service_fees_summary`
--
DROP TABLE IF EXISTS `v_service_fees_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_service_fees_summary`  AS SELECT `service_fees`.`category` AS `category`, count(0) AS `total_services`, sum(`service_fees`.`fee_amount`) AS `total_fees`, avg(`service_fees`.`fee_amount`) AS `average_fee`, min(`service_fees`.`fee_amount`) AS `min_fee`, max(`service_fees`.`fee_amount`) AS `max_fee`, sum(case when `service_fees`.`is_active` = 1 then 1 else 0 end) AS `active_services` FROM `service_fees` GROUP BY `service_fees`.`category` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `visit_id` (`visit_id`);

--
-- Indexes for table `bill_items`
--
ALTER TABLE `bill_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bill_id` (`bill_id`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `doctor_status`
--
ALTER TABLE `doctor_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `doctor_id` (`doctor_id`);

--
-- Indexes for table `employee_departments`
--
ALTER TABLE `employee_departments`
  ADD PRIMARY KEY (`user_id`,`department_id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `assigned_by` (`assigned_by`);

--
-- Indexes for table `employee_roles`
--
ALTER TABLE `employee_roles`
  ADD PRIMARY KEY (`user_id`,`role_id`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `assigned_by` (`assigned_by`);

--
-- Indexes for table `inventory_categories`
--
ALTER TABLE `inventory_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `category_code` (`category_code`),
  ADD KEY `is_active` (`is_active`),
  ADD KEY `inventory_categories_ibfk_1` (`created_by`);

--
-- Indexes for table `lab_billing_items`
--
ALTER TABLE `lab_billing_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_id` (`request_id`);

--
-- Indexes for table `lab_requests`
--
ALTER TABLE `lab_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `request_number` (`request_number`),
  ADD KEY `idx_visit` (`visit_id`),
  ADD KEY `idx_patient` (`patient_id`),
  ADD KEY `idx_doctor` (`doctor_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_lab_requests_status` (`status`),
  ADD KEY `idx_lab_requests_patient` (`patient_id`),
  ADD KEY `idx_lab_requests_doctor` (`doctor_id`),
  ADD KEY `idx_lab_requests_date` (`requested_at`);

--
-- Indexes for table `lab_request_items`
--
ALTER TABLE `lab_request_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `test_id` (`test_id`),
  ADD KEY `idx_lab_request_items_status` (`status`);

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
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `lab_technician_id` (`lab_technician_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `idx_technician_id` (`technician_id`);

--
-- Indexes for table `lab_tests_catalog`
--
ALTER TABLE `lab_tests_catalog`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `test_code` (`test_code`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `lab_test_type_mapping`
--
ALTER TABLE `lab_test_type_mapping`
  ADD PRIMARY KEY (`id`),
  ADD KEY `template_id` (`template_id`);

--
-- Indexes for table `medications`
--
ALTER TABLE `medications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name_strength_unit` (`name`,`strength`,`unit`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `medications_inventory`
--
ALTER TABLE `medications_inventory`
  ADD PRIMARY KEY (`id`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `otc_sales`
--
ALTER TABLE `otc_sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sale_number` (`sale_number`),
  ADD KEY `idx_customer` (`customer_name`),
  ADD KEY `idx_date` (`created_at`),
  ADD KEY `idx_bill_id` (`bill_id`);

--
-- Indexes for table `otc_sale_items`
--
ALTER TABLE `otc_sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `idx_inventory` (`inventory_id`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `patient_id` (`patient_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `assigned_doctor_id` (`assigned_doctor_id`);

--
-- Indexes for table `patient_bills`
--
ALTER TABLE `patient_bills`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bill_number` (`bill_number`);

--
-- Indexes for table `patient_documents`
--
ALTER TABLE `patient_documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `document_number` (`document_number`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `visit_id` (`visit_id`),
  ADD KEY `uploaded_by` (`uploaded_by`),
  ADD KEY `verified_by` (`verified_by`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `receipt_number` (`receipt_number`),
  ADD KEY `idx_bill` (`bill_id`),
  ADD KEY `idx_patient` (`patient_id`),
  ADD KEY `idx_receipt` (`receipt_number`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `pharmacy_sales`
--
ALTER TABLE `pharmacy_sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sale_number` (`sale_number`),
  ADD KEY `prescription_id` (`prescription_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `cashier_id` (`cashier_id`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `prescription_number` (`prescription_number`),
  ADD KEY `visit_id` (`visit_id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `pharmacy_id` (`pharmacy_id`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `prescription_items`
--
ALTER TABLE `prescription_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `prescription_id` (`prescription_id`);

--
-- Indexes for table `prescription_sales`
--
ALTER TABLE `prescription_sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sale_number` (`sale_number`),
  ADD KEY `idx_prescription` (`prescription_id`),
  ADD KEY `idx_patient` (`patient_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_date` (`created_at`);

--
-- Indexes for table `prescription_sale_items`
--
ALTER TABLE `prescription_sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `idx_inventory` (`inventory_id`);

--
-- Indexes for table `procedures`
--
ALTER TABLE `procedures`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_procedure_name` (`procedure_name`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `procedure_tools`
--
ALTER TABLE `procedure_tools`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `receipts`
--
ALTER TABLE `receipts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `receipt_number` (`receipt_number`),
  ADD KEY `payment_id` (`payment_id`),
  ADD KEY `idx_receipt_number` (`receipt_number`),
  ADD KEY `idx_branch_id` (`branch_id`);

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
  ADD KEY `status` (`status`),
  ADD KEY `referral_type` (`referral_type`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `referral_logs`
--
ALTER TABLE `referral_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `referral_id` (`referral_id`),
  ADD KEY `performed_by` (`performed_by`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`);

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
-- Indexes for table `service_fees`
--
ALTER TABLE `service_fees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `service_code` (`service_code`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `category` (`category`),
  ADD KEY `is_active` (`is_active`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inventory` (`inventory_id`),
  ADD KEY `idx_sale` (`sale_id`);

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
  ADD KEY `referred_to` (`referred_to`),
  ADD KEY `idx_visits_patient_status` (`patient_id`,`status`),
  ADD KEY `idx_visits_doctor_date` (`doctor_id`,`visit_date`),
  ADD KEY `idx_visits_branch_status` (`branch_id`,`status`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=245;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `bill_items`
--
ALTER TABLE `bill_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=435;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `doctor_status`
--
ALTER TABLE `doctor_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_categories`
--
ALTER TABLE `inventory_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lab_billing_items`
--
ALTER TABLE `lab_billing_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lab_requests`
--
ALTER TABLE `lab_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `lab_request_items`
--
ALTER TABLE `lab_request_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=100;

--
-- AUTO_INCREMENT for table `lab_result_templates`
--
ALTER TABLE `lab_result_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `lab_tests`
--
ALTER TABLE `lab_tests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

--
-- AUTO_INCREMENT for table `lab_tests_catalog`
--
ALTER TABLE `lab_tests_catalog`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `lab_test_type_mapping`
--
ALTER TABLE `lab_test_type_mapping`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `medications`
--
ALTER TABLE `medications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `medications_inventory`
--
ALTER TABLE `medications_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=128;

--
-- AUTO_INCREMENT for table `otc_sales`
--
ALTER TABLE `otc_sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `otc_sale_items`
--
ALTER TABLE `otc_sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `patient_bills`
--
ALTER TABLE `patient_bills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=141;

--
-- AUTO_INCREMENT for table `patient_documents`
--
ALTER TABLE `patient_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pharmacy_sales`
--
ALTER TABLE `pharmacy_sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `prescription_items`
--
ALTER TABLE `prescription_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `prescription_sales`
--
ALTER TABLE `prescription_sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `prescription_sale_items`
--
ALTER TABLE `prescription_sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `procedures`
--
ALTER TABLE `procedures`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `procedure_tools`
--
ALTER TABLE `procedure_tools`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=153;

--
-- AUTO_INCREMENT for table `receipts`
--
ALTER TABLE `receipts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `referrals`
--
ALTER TABLE `referrals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `referral_logs`
--
ALTER TABLE `referral_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `service_categories`
--
ALTER TABLE `service_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `service_fees`
--
ALTER TABLE `service_fees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `visits`
--
ALTER TABLE `visits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- AUTO_INCREMENT for table `vital_signs`
--
ALTER TABLE `vital_signs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_activity_logs_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `appointments_ibfk_4` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `bill_items`
--
ALTER TABLE `bill_items`
  ADD CONSTRAINT `bill_items_ibfk_1` FOREIGN KEY (`bill_id`) REFERENCES `patient_bills` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `doctor_status`
--
ALTER TABLE `doctor_status`
  ADD CONSTRAINT `doctor_status_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_departments`
--
ALTER TABLE `employee_departments`
  ADD CONSTRAINT `employee_departments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_departments_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_departments_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `employee_roles`
--
ALTER TABLE `employee_roles`
  ADD CONSTRAINT `employee_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_roles_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_roles_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_categories`
--
ALTER TABLE `inventory_categories`
  ADD CONSTRAINT `inventory_categories_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `lab_billing_items`
--
ALTER TABLE `lab_billing_items`
  ADD CONSTRAINT `lab_billing_items_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `lab_requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lab_request_items`
--
ALTER TABLE `lab_request_items`
  ADD CONSTRAINT `lab_request_items_ibfk_2` FOREIGN KEY (`test_id`) REFERENCES `lab_tests_catalog` (`id`);

--
-- Constraints for table `lab_tests`
--
ALTER TABLE `lab_tests`
  ADD CONSTRAINT `lab_tests_ibfk_1` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lab_tests_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `lab_tests_ibfk_3` FOREIGN KEY (`lab_technician_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `lab_tests_ibfk_4` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `lab_tests_catalog`
--
ALTER TABLE `lab_tests_catalog`
  ADD CONSTRAINT `fk_lab_tests_catalog_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_lab_tests_catalog_branch_id` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `medications`
--
ALTER TABLE `medications`
  ADD CONSTRAINT `fk_medications_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `medications_inventory`
--
ALTER TABLE `medications_inventory`
  ADD CONSTRAINT `medications_inventory_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `otc_sales`
--
ALTER TABLE `otc_sales`
  ADD CONSTRAINT `fk_otc_sales_bill_id` FOREIGN KEY (`bill_id`) REFERENCES `patient_bills` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `otc_sale_items`
--
ALTER TABLE `otc_sale_items`
  ADD CONSTRAINT `otc_sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `otc_sales` (`id`) ON DELETE CASCADE;

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
  ADD CONSTRAINT `patient_documents_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `patient_documents_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `patient_documents_ibfk_3` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `patient_documents_ibfk_4` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `patient_documents_ibfk_5` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `patient_documents_ibfk_6` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`bill_id`) REFERENCES `patient_bills` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pharmacy_sales`
--
ALTER TABLE `pharmacy_sales`
  ADD CONSTRAINT `pharmacy_sales_ibfk_1` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `pharmacy_sales_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pharmacy_sales_ibfk_3` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `pharmacy_sales_ibfk_4` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `prescriptions_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `prescriptions_ibfk_3` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `prescriptions_ibfk_4` FOREIGN KEY (`pharmacy_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `prescriptions_ibfk_5` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `prescription_items`
--
ALTER TABLE `prescription_items`
  ADD CONSTRAINT `prescription_items_ibfk_1` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `prescription_sale_items`
--
ALTER TABLE `prescription_sale_items`
  ADD CONSTRAINT `prescription_sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `prescription_sales` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `procedures`
--
ALTER TABLE `procedures`
  ADD CONSTRAINT `fk_procedures_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `procedure_tools`
--
ALTER TABLE `procedure_tools`
  ADD CONSTRAINT `fk_procedure_tools_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `receipts`
--
ALTER TABLE `receipts`
  ADD CONSTRAINT `fk_receipts_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `receipts_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`);

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `pharmacy_sales` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `services`
--
ALTER TABLE `services`
  ADD CONSTRAINT `fk_services_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `services_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `service_categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `services_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `service_categories`
--
ALTER TABLE `service_categories`
  ADD CONSTRAINT `fk_service_categories_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `visits`
--
ALTER TABLE `visits`
  ADD CONSTRAINT `visits_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `visits_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `visits_ibfk_3` FOREIGN KEY (`receptionist_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `visits_ibfk_4` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `visits_ibfk_5` FOREIGN KEY (`referred_to`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `vital_signs`
--
ALTER TABLE `vital_signs`
  ADD CONSTRAINT `vital_signs_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vital_signs_ibfk_2` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `vital_signs_ibfk_3` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `vital_signs_ibfk_4` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vital_signs_ibfk_5` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
