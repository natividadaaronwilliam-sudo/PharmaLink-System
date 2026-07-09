-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 22, 2025 at 04:45 AM
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
-- Database: `pharmacy`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `log_id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `admin_name` varchar(100) NOT NULL DEFAULT 'System',
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `date` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `customer_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `customer_type` enum('Regular','Senior','PWD','Other') DEFAULT 'Regular',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `password` varchar(255) NOT NULL,
  `loyalty_points` decimal(10,2) NOT NULL DEFAULT 0.00,
  `email` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`customer_id`, `first_name`, `middle_name`, `last_name`, `username`, `address`, `phone_number`, `is_active`, `customer_type`, `created_at`, `password`, `loyalty_points`, `email`) VALUES
(0, 'Test', 'Tes', 'Test', 'test_cx', 'sdjgjs sdgjjskg', '090809809', 0, 'Regular', '2025-11-21 11:10:52', '$2y$10$Sk3e/5yZWTVPHCV52XsZf.Rp0T6szkYSt6m.3my341EODAbPbp1OO', 0.00, 'test@gm.com'),
(4, 'Eric', '', 'Santon', 'juan_cruz', 'N/A', '0923425135', 1, 'Senior', '2025-11-14 11:48:19', '$2y$10$twxocidkZ5bBicg/MTEeUOxzQQa75nvWi96sO5nwStY91LDYQL7AW', 0.00, 'ericsan@gmail.com'),
(5, 'Juan', '', 'Dela Cruz', 'santos_men', 'N/A', '09123456789', 1, 'Regular', '2025-11-19 05:58:44', '$2y$10$RCcLZ5Rb19jUYL2sENa1/OPl.xwLwdI5WyOkyWTrY477daomVA19a', 0.00, 'juan@gmail.com');

-- --------------------------------------------------------

--
-- Table structure for table `customer_orders`
--

CREATE TABLE `customer_orders` (
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `order_date` datetime NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `order_status` varchar(50) NOT NULL,
  `sc_pwd_applied` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_read` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer_orders`
--

INSERT INTO `customer_orders` (`order_id`, `customer_id`, `order_date`, `total_amount`, `order_status`, `sc_pwd_applied`, `updated_at`) VALUES
(5, 4, '2025-11-14 21:12:16', 110.00, 'Completed', 0, '2025-11-14 20:24:25'),
(6, 4, '2025-11-14 21:47:49', 100.00, 'Completed', 0, '2025-11-14 20:27:15'),
(7, 4, '2025-11-14 21:53:40', 50.00, 'Completed', 0, '2025-11-14 20:40:44'),
(8, 4, '2025-11-14 22:05:23', 50.00, 'Completed', 0, '2025-11-14 20:47:43'),
(9, 4, '2025-11-14 22:13:17', 50.00, 'Completed', 0, '2025-11-21 16:13:36'),
(10, 4, '2025-11-14 22:17:23', 50.00, 'Pending', 0, '2025-11-14 19:10:43'),
(11, 4, '2025-11-14 22:39:07', 50.00, 'Cancelled', 0, '2025-11-19 05:36:09'),
(12, 4, '2025-11-19 13:34:55', 60.00, 'Ready for Pickup', 0, '2025-11-19 05:35:35');

-- --------------------------------------------------------

--
-- Table structure for table `drugs_master`
--

CREATE TABLE `drugs_master` (
  `drug_id` int(11) NOT NULL,
  `generic_name` varchar(255) NOT NULL,
  `brand_name` varchar(255) DEFAULT NULL,
  `dosage` varchar(50) NOT NULL,
  `form` varchar(50) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `minimum_stock` int(11) DEFAULT 0,
  `stock_status` enum('ok','low','out') NOT NULL DEFAULT 'ok',
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `drugs_master`
--

INSERT INTO `drugs_master` (`drug_id`, `generic_name`, `brand_name`, `dosage`, `form`, `category`, `minimum_stock`, `is_active`) VALUES
(19, 'Paracetamol', 'RiteMed', '500mg', 'Capsule', 'analgesic', 50, 1),
(20, 'Lozartan', 'RiteMed', '400mg', 'Tablet', 'antihypertensive', 50, 1),
(21, 'Amoxicillin', 'Amoxil', '500mg', 'Capsule', 'antibiotic', 50, 1),
(22, 'Gamot', 'RiteMed', '50mg', 'Capsule', 'antipyretic', 50, 0),
(23, 'Metformin', 'Ritemed', '100mg', 'Capsule', 'antipyretic', 100, 1),
(24, 'Deac', 'RiteMed', '500mg', 'Capsule', 'antibiotic', 100, 0),
(25, 'Multivitamins', 'Centrum', '500mg', 'Capsule', 'vitamin', 50, 1);

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `generic_name` varchar(100) NOT NULL,
  `brand_name` varchar(100) NOT NULL,
  `dosage` varchar(50) DEFAULT NULL,
  `form` varchar(50) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `current_stock` int(11) DEFAULT 0,
  `minimum_stock` int(11) DEFAULT 0,
  `price` decimal(10,2) DEFAULT NULL,
  `supplier` varchar(100) DEFAULT NULL,
  `status` varchar(30) DEFAULT 'In Stock',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `generic_name`, `brand_name`, `dosage`, `form`, `category`, `current_stock`, `minimum_stock`, `price`, `supplier`, `status`, `created_at`) VALUES
(1, 'Paracetamol', 'Ritemed', '500mg', 'Tablet', 'antipyretic', 20, 15, 100.00, 'pp', 'In Stock', '2025-10-24 10:10:11');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_lots`
--

CREATE TABLE `inventory_lots` (
  `lot_inventory_id` int(11) NOT NULL,
  `drug_id` int(11) NOT NULL,
  `lot_number` varchar(100) NOT NULL,
  `expiration_date` date NOT NULL,
  `current_stock` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `supplier` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_lots`
--

INSERT INTO `inventory_lots` (`lot_inventory_id`, `drug_id`, `lot_number`, `expiration_date`, `current_stock`, `price`, `supplier`, `is_active`) VALUES
(24, 19, 'QWE', '2025-10-31', 0, 10.00, 1, 0),
(25, 19, '1321654', '2025-10-31', 5, 60.00, 2, 1),
(27, 21, '12456', '2025-11-29', 2, 100.00, 1, 1),
(28, 20, 'AQW12', '2025-11-29', 0, 50.00, 3, 1),
(29, 21, '78946', '2025-11-21', 1, 1.00, 4, 1),
(37, 24, '12455', '2025-11-22', 0, 5.00, 2, 0),
(38, 25, '121232', '2025-11-29', 5, 50.00, 5, 1),
(39, 25, 'cap', '2025-11-22', 0, 7.00, 1, 0),
(50, 24, 'new', '2025-11-29', 0, 5.00, 4, 0);

-- --------------------------------------------------------

--
-- Table structure for table `order_details`
--

CREATE TABLE `order_details` (
  `detail_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `drug_id` int(11) NOT NULL,
  `lot_inventory_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price_per_unit` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_details`
--

INSERT INTO `order_details` (`detail_id`, `order_id`, `drug_id`, `lot_inventory_id`, `quantity`, `price_per_unit`) VALUES
(7, 5, 19, 25, 2, 55.00),
(8, 6, 20, 28, 2, 50.00),
(9, 7, 20, 28, 1, 50.00),
(10, 8, 20, 28, 1, 50.00),
(11, 9, 20, 28, 1, 50.00),
(12, 10, 20, 28, 1, 50.00),
(13, 11, 20, 28, 1, 50.00),
(14, 12, 19, 25, 1, 60.00);

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `extracted_text` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `returns`
--

CREATE TABLE `returns` (
  `id` int(11) NOT NULL,
  `transaction_id` varchar(30) NOT NULL,
  `sales_item_id` int(11) NOT NULL,
  `quantity_returned` int(11) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `return_date` datetime DEFAULT current_timestamp(),
  `cashier_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `role`
--

CREATE TABLE `role` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role`
--

INSERT INTO `role` (`role_id`, `role_name`) VALUES
(1, 'Admin'),
(2, 'Cashier/Pharmacist'),
(3, 'Customer');

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `sale_id` int(11) NOT NULL,
  `transaction_id` varchar(30) NOT NULL,
  `user_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT NULL,
  `tax_amount` decimal(10,2) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `cash_received` decimal(10,2) DEFAULT NULL,
  `change_amount` decimal(10,2) DEFAULT NULL,
  `payment_method` varchar(20) DEFAULT 'Cash',
  `status` enum('completed','cancelled','refunded') DEFAULT 'completed',
  `date_created` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`sale_id`, `transaction_id`, `user_id`, `customer_id`, `subtotal`, `discount_amount`, `tax_amount`, `total_amount`, `cash_received`, `change_amount`, `payment_method`, `status`, `date_created`) VALUES
(13, 'TXN-20251107232051-1976', 1, NULL, 35.71, 0.00, 0.00, 35.71, 50.00, 14.29, '0', 'completed', '2025-11-08 06:20:51'),
(14, 'TXN-20251107232844-8386', 1, NULL, 200.00, 0.00, 0.00, 200.00, 500.00, 300.00, '0', 'completed', '2025-11-08 06:28:44'),
(15, 'TXN-20251107232917-5951', 1, 3, 370.00, 0.00, 0.00, 370.00, 500.00, 130.00, '0', 'completed', '2025-11-08 06:29:17'),
(16, 'TXN-20251107234305-6388', 1, NULL, 275.00, 0.00, 0.00, 275.00, 500.00, 225.00, '0', 'completed', '2025-11-08 06:43:05'),
(17, 'TXN-20251107234328-2323', 1, NULL, 165.00, 0.00, 0.00, 165.00, 200.00, 35.00, '0', 'completed', '2025-11-08 06:43:28'),
(18, 'TXN-20251108022741-2794', 1, NULL, 332.14, 0.00, 0.00, 332.14, 1000.00, 667.86, '0', 'completed', '2025-11-08 09:27:41'),
(19, 'TXN-20251112113019-9866', 1, NULL, 200.00, 35.71, 0.00, 142.86, 150.00, 7.14, '0', 'completed', '2025-11-12 18:30:19'),
(20, 'TXN-20251112114622-2666', 1, NULL, 5.00, 0.00, 0.54, 5.00, 10.00, 5.00, '0', 'completed', '2025-11-12 18:46:22'),
(21, 'TXN-20251112120057-6791', 1, NULL, 5.00, 0.00, 0.54, 5.00, 50.00, 45.00, '0', 'completed', '2025-11-12 19:00:57'),
(22, 'TXN-20251112120637-7931', 1, NULL, 500.00, 0.00, 53.57, 500.00, 500.00, 0.00, '0', 'completed', '2025-11-12 19:06:37'),
(23, 'TXN-20251112120905-6434', 1, 1, 300.00, 0.00, 32.14, 300.00, 500.00, 200.00, '0', 'completed', '2025-11-12 19:09:05'),
(24, 'TXN-20251112122356-9425', 1, 1, 500.00, 0.00, 53.57, 500.00, 600.00, 100.00, '0', 'completed', '2025-11-12 19:23:56'),
(25, 'TXN-20251112122938-4329', 1, 1, 885.00, 0.00, 94.82, 885.00, 1000.00, 115.00, '0', 'completed', '2025-11-12 19:29:38'),
(26, 'TXN-20251112134226-5041', 1, NULL, 205.00, 36.61, 0.00, 146.43, 200.00, 53.57, '0', 'completed', '2025-11-12 20:42:26'),
(27, 'TXN-20251112134336-9786', 1, NULL, 450.00, 0.00, 48.21, 450.00, 500.00, 50.00, '0', 'completed', '2025-11-12 20:43:36'),
(28, 'TXN-20251112134518-3963', 1, NULL, 330.00, 58.93, 0.00, 235.71, 300.00, 64.29, '0', 'completed', '2025-11-12 20:45:18'),
(29, 'TXN-20251112135639-6568', 1, NULL, 275.00, 49.11, 0.00, 196.43, 200.00, 3.57, '0', 'completed', '2025-11-12 20:56:39'),
(30, 'TXN-20251112140733-2686', 1, NULL, 275.00, 49.11, 0.00, 196.43, 200.00, 3.57, '0', 'completed', '2025-11-12 21:07:33'),
(31, 'TXN-20251112141246-7803', 1, NULL, 525.00, 93.75, 0.00, 375.00, 500.00, 125.00, '0', 'completed', '2025-11-12 21:12:46'),
(32, 'TXN-20251112141516-1047', 1, NULL, 580.00, 103.57, 0.00, 414.29, 500.00, 85.71, '0', 'completed', '2025-11-12 21:15:16'),
(33, 'TXN-20251112141636-3264', 1, 3, 165.00, 29.46, 0.00, 117.86, 120.00, 2.14, '0', 'completed', '2025-11-12 21:16:36'),
(34, 'TXN-20251112142154-2061', 1, 3, 250.00, 0.00, 26.79, 250.00, 500.00, 250.00, '0', 'completed', '2025-11-12 21:21:54'),
(35, 'TXN-20251112142259-3879', 1, NULL, 250.00, 44.64, 0.00, 178.57, 500.00, 321.43, '0', 'completed', '2025-11-12 21:22:59'),
(36, 'TXN-20251112144456-9291', 1, 3, 150.00, 0.00, 16.07, 150.00, 200.00, 50.00, '0', 'completed', '2025-11-12 21:44:56'),
(37, 'TXN-20251112144534-3209', 1, NULL, 200.00, 24.00, 21.43, 176.00, 200.00, 24.00, '0', 'completed', '2025-11-12 21:45:34'),
(38, 'TXN-20251112144917-4725', 1, NULL, 100.00, 12.00, 10.71, 88.00, 200.00, 112.00, '0', 'completed', '2025-11-12 21:49:17'),
(39, 'TXN-20251112145653-7063', 1, NULL, 100.00, 0.00, 10.71, 100.00, 200.00, 100.00, '0', 'completed', '2025-11-12 21:56:53'),
(40, 'TXN-20251112145959-8979', 1, NULL, 50.00, 8.93, 0.00, 35.71, 70.00, 34.29, '0', 'completed', '2025-11-12 21:59:59'),
(41, 'TXN-20251112150410-6222', 1, NULL, 50.00, 0.00, 5.36, 50.00, 100.00, 50.00, '0', 'completed', '2025-11-12 22:04:10'),
(42, 'TXN-20251112151800-2402', 1, NULL, 50.00, 0.00, 5.36, 50.00, 60.00, 10.00, '0', 'completed', '2025-11-12 22:18:00'),
(43, 'TXN-20251112151818-1661', 1, 3, 50.00, 8.93, 0.00, 35.71, 50.00, 14.29, '0', 'completed', '2025-11-12 22:18:18'),
(44, 'TXN-20251114162938-3143', 1, 4, 55.00, 0.00, 5.89, 55.00, 60.00, 5.00, '0', 'completed', '2025-11-14 23:29:38'),
(47, 'TXN-20251114212425-5884', 1, NULL, 110.00, 0.00, 11.79, 110.00, 500.00, 390.00, '0', 'completed', '2025-11-15 04:24:25'),
(48, 'TXN-20251114212715-1056', 1, NULL, 100.00, 0.00, 10.71, 100.00, 200.00, 100.00, '0', 'completed', '2025-11-15 04:27:15'),
(49, 'TXN-20251114214044-6575', 1, 4, 50.00, 0.00, 5.36, 50.00, 50.00, 0.00, '0', 'completed', '2025-11-15 04:40:44'),
(50, 'TXN-20251114214743-7625', 1, 4, 50.00, 0.00, 5.36, 50.00, 500.00, 450.00, '0', 'completed', '2025-11-15 04:47:43'),
(51, 'TXN-20251121171331-6839', 1, 4, 50.00, 0.00, 5.36, 50.00, 100.00, 50.00, '0', 'completed', '2025-11-22 00:13:31');

-- --------------------------------------------------------

--
-- Table structure for table `sales_items`
--

CREATE TABLE `sales_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `lot_id` int(11) NOT NULL,
  `drug_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `promo_name` varchar(100) DEFAULT NULL,
  `vat_exempt` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales_items`
--

INSERT INTO `sales_items` (`id`, `sale_id`, `lot_id`, `drug_id`, `quantity`, `price`, `subtotal`, `discount_amount`, `promo_name`, `vat_exempt`) VALUES
(1, 13, 24, 19, 5, 10.00, 0.00, 0.00, NULL, 0),
(2, 14, 24, 19, 20, 10.00, 0.00, 0.00, NULL, 0),
(3, 15, 24, 19, 37, 10.00, 0.00, 0.00, NULL, 0),
(4, 16, 25, 19, 5, 55.00, 0.00, 0.00, NULL, 0),
(5, 17, 25, 19, 3, 55.00, 0.00, 0.00, NULL, 0),
(6, 18, 25, 19, 3, 55.00, 0.00, 0.00, NULL, 0),
(7, 18, 27, 21, 3, 100.00, 0.00, 0.00, NULL, 0),
(8, 19, 28, 20, 4, 50.00, 200.00, 0.00, NULL, 0),
(9, 20, 29, 21, 5, 1.00, 5.00, 0.00, NULL, 0),
(10, 21, 29, 21, 5, 1.00, 5.00, 0.00, NULL, 0),
(11, 22, 27, 21, 5, 100.00, 500.00, 0.00, NULL, 0),
(12, 23, 27, 21, 3, 100.00, 300.00, 0.00, NULL, 0),
(13, 24, 27, 21, 5, 100.00, 500.00, 0.00, NULL, 0),
(14, 25, 25, 19, 7, 55.00, 385.00, 0.00, NULL, 0),
(15, 25, 27, 21, 5, 100.00, 500.00, 0.00, NULL, 0),
(16, 26, 25, 19, 1, 55.00, 55.00, 0.00, NULL, 0),
(17, 26, 27, 21, 1, 100.00, 100.00, 0.00, NULL, 0),
(18, 26, 28, 20, 1, 50.00, 50.00, 0.00, NULL, 0),
(19, 27, 27, 21, 2, 100.00, 200.00, 0.00, NULL, 0),
(20, 27, 28, 20, 5, 50.00, 250.00, 0.00, NULL, 0),
(21, 28, 25, 19, 6, 55.00, 330.00, 0.00, NULL, 0),
(24, 31, 25, 19, 5, 55.00, 275.00, 0.00, NULL, 0),
(25, 31, 28, 20, 5, 50.00, 250.00, 0.00, NULL, 0),
(26, 32, 25, 19, 6, 55.00, 330.00, 0.00, NULL, 0),
(27, 32, 28, 20, 5, 50.00, 250.00, 0.00, NULL, 0),
(28, 33, 25, 19, 3, 55.00, 165.00, 0.00, NULL, 0),
(29, 34, 28, 20, 5, 50.00, 250.00, 0.00, NULL, 0),
(30, 35, 28, 20, 5, 50.00, 250.00, 0.00, NULL, 0),
(31, 36, 28, 20, 3, 50.00, 150.00, 0.00, NULL, 0),
(32, 37, 28, 20, 4, 50.00, 200.00, 0.00, NULL, 0),
(33, 38, 28, 20, 2, 50.00, 100.00, 0.00, NULL, 0),
(34, 39, 28, 20, 2, 50.00, 100.00, 0.00, NULL, 0),
(35, 40, 28, 20, 1, 50.00, 50.00, 0.00, NULL, 0),
(36, 41, 28, 20, 1, 50.00, 50.00, 0.00, NULL, 0),
(37, 42, 28, 20, 1, 50.00, 50.00, 0.00, NULL, 0),
(38, 43, 28, 20, 1, 50.00, 50.00, 0.00, NULL, 0),
(39, 44, 25, 19, 1, 55.00, 55.00, 0.00, NULL, 0),
(41, 47, 25, 19, 2, 55.00, 110.00, 0.00, NULL, 0),
(42, 48, 28, 20, 2, 50.00, 100.00, 0.00, NULL, 0),
(43, 49, 28, 20, 1, 50.00, 50.00, 0.00, NULL, 0),
(44, 50, 28, 20, 1, 50.00, 50.00, 0.00, NULL, 0),
(45, 51, 28, 20, 1, 50.00, 50.00, 0.00, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `staff_info`
--

CREATE TABLE `staff_info` (
  `staff_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_info`
--

INSERT INTO `staff_info` (`staff_id`, `user_id`, `first_name`, `middle_name`, `last_name`, `email`, `phone_number`, `address`) VALUES
(1, 6, 'Test', 'Test', 'Test', NULL, NULL, NULL),
(2, 7, 'Cashier', 'ChangeSS', 'Cashier', 'cashier@test.com', '13245668', 'udh hisudhgdgte'),
(3, 8, 'Angel', '', 'Villanueva', 'angel@gmail.com', '12346548', 'njkskjn jkng'),
(4, 9, 'Micha', '', 'Angulo', 'oisjf@gm.com', '132165464', 'oifmlks osijg');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `supplier_id` int(11) NOT NULL,
  `supplier_name` varchar(100) NOT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `inactive_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`supplier_id`, `supplier_name`, `contact_number`, `email`, `address`, `status`, `created_at`) VALUES
(1, 'Bambang Pharmaceutical Depot Inc.', '09177218748', 'marketing@bambangpharma.com', '1414 Thomas Mapua St., Sta. Cruz, Manila', 'Active', '2025-10-30 11:11:29'),
(2, 'Sterilab Co.', '090090999', 'steri@steri.com', 'Rm 203, Ormed Bldg, 121-a V. Luna Ext, Diliman, Quezon City, 1101 Metro Manila, Philippines', 'Active', '2025-10-30 12:38:15'),
(3, 'Angel', '0920399', 'lkfjs@gmail.co', 'iedshfsdgj', 'Active', '2025-11-08 06:43:51'),
(4, 'AAROM', '868767', 'CBWS@GM.COMD', 'DHGDCHJ', 'Inactive', '2025-11-08 06:46:52'),
(5, 'Neww', '090909', 'sdfd@gmail.com', 'Sdgdh Hfhf', 'Inactive', '2025-11-16 16:05:23'),
(6, 'Test', '051', 'sdkmg@g', ';dlmdf,d', 'Inactive', '2025-11-19 06:39:39'),
(7, 'Test Lang', '5321321', 'skdms@hfh.com', 'S;gmldkmdfh', 'Inactive', '2025-11-19 06:42:39'),
(8, 'Test', '136545555', 'ea@gami.com', 'Dfgdhhdfh', 'Inactive', '2025-11-21 14:15:19');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_drugs`
--

CREATE TABLE `supplier_drugs` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `drug_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supplier_drugs`
--

INSERT INTO `supplier_drugs` (`id`, `supplier_id`, `drug_id`) VALUES
(1, 1, 19),
(4, 1, 20),
(5, 1, 21),
(14, 1, 25),
(3, 2, 19),
(9, 2, 20),
(10, 2, 22),
(11, 2, 23),
(12, 2, 24),
(6, 3, 20),
(8, 3, 22),
(7, 4, 21),
(15, 4, 24),
(13, 5, 25);

-- --------------------------------------------------------

--
-- Table structure for table `supplier_medicines`
--

CREATE TABLE `supplier_medicines` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `drug_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `role_id`, `is_active`) VALUES
(1, 'admin', '$2y$10$akpW094B6g2XmARRunMw0uOz2cJfviFFkDIIas9VZYLNhtEMXmATq', 1, 1),
(2, 'cashier', '$2y$10$6EUowpoTGvIyip5a/VKe2.MTpynK4R1uiwEd69LPaDB7PeSNCpzQe', 2, 1),
(3, 'customer', '$2y$10$4PRdDZQjofrC2i0VbKzIy.KX2DEndgnSUCniye3gV4PQEW9LUPMt6', 3, 1),
(4, 'juan_cruz', '$2y$10$twxocidkZ5bBicg/MTEeUOxzQQa75nvWi96sO5nwStY91LDYQL7AW', 3, 1),
(5, 'santos_men', '$2y$10$RCcLZ5Rb19jUYL2sENa1/OPl.xwLwdI5WyOkyWTrY477daomVA19a', 3, 1),
(6, 'test_admin', '$2y$10$roGkmgO9wInUa3Eka3F67u0/q3L32ZQOz9plFhjlgiU.BjV5oPZ4K', 1, 0),
(7, 'test_cashier', '$2y$10$8pWuEe8owcOEA4Nn5wPO5O1.HZdLWzNDTWr6KQFmHLvvZBATq6K.u', 2, 0),
(8, 'angel123', '$2y$10$AWGIQFgTkOFOtntymluzS.Qy5XkGVxXy.DYt2QvMlcaROgI2Y6GL.', 1, 1),
(9, 'mj123', '$2y$10$7to3AypqYCjJjiQVvylMmOrZfvjtwQ0FRk.uY///kvqtjOXvI9l6q', 2, 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`log_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`customer_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `customer_orders`
--
ALTER TABLE `customer_orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `drugs_master`
--
ALTER TABLE `drugs_master`
  ADD PRIMARY KEY (`drug_id`),
  ADD UNIQUE KEY `unique_drug` (`generic_name`,`dosage`,`form`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory_lots`
--
ALTER TABLE `inventory_lots`
  ADD PRIMARY KEY (`lot_inventory_id`),
  ADD UNIQUE KEY `unique_lot` (`drug_id`,`lot_number`),
  ADD KEY `fk_supplier_id` (`supplier`);

--
-- Indexes for table `order_details`
--
ALTER TABLE `order_details`
  ADD PRIMARY KEY (`detail_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `drug_id` (`drug_id`),
  ADD KEY `lot_inventory_id` (`lot_inventory_id`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `returns`
--
ALTER TABLE `returns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sales_item_id` (`sales_item_id`),
  ADD KEY `cashier_id` (`cashier_id`);

--
-- Indexes for table `role`
--
ALTER TABLE `role`
  ADD PRIMARY KEY (`role_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`sale_id`),
  ADD UNIQUE KEY `transaction_id` (`transaction_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `sales_items`
--
ALTER TABLE `sales_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `lot_id` (`lot_id`),
  ADD KEY `drug_id` (`drug_id`);

--
-- Indexes for table `staff_info`
--
ALTER TABLE `staff_info`
  ADD PRIMARY KEY (`staff_id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`supplier_id`);

--
-- Indexes for table `supplier_drugs`
--
ALTER TABLE `supplier_drugs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `supplier_id` (`supplier_id`,`drug_id`),
  ADD KEY `drug_id` (`drug_id`);

--
-- Indexes for table `supplier_medicines`
--
ALTER TABLE `supplier_medicines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `drug_id` (`drug_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `role_id` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_orders`
--
ALTER TABLE `customer_orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `drugs_master`
--
ALTER TABLE `drugs_master`
  MODIFY `drug_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `inventory_lots`
--
ALTER TABLE `inventory_lots`
  MODIFY `lot_inventory_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `order_details`
--
ALTER TABLE `order_details`
  MODIFY `detail_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `returns`
--
ALTER TABLE `returns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `role`
--
ALTER TABLE `role`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `sale_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `sales_items`
--
ALTER TABLE `sales_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `staff_info`
--
ALTER TABLE `staff_info`
  MODIFY `staff_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `supplier_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `supplier_drugs`
--
ALTER TABLE `supplier_drugs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `supplier_medicines`
--
ALTER TABLE `supplier_medicines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `customer_orders`
--
ALTER TABLE `customer_orders`
  ADD CONSTRAINT `customer_orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`);

--
-- Constraints for table `inventory_lots`
--
ALTER TABLE `inventory_lots`
  ADD CONSTRAINT `fk_supplier_id` FOREIGN KEY (`supplier`) REFERENCES `suppliers` (`supplier_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `inventory_lots_ibfk_1` FOREIGN KEY (`drug_id`) REFERENCES `drugs_master` (`drug_id`) ON DELETE CASCADE;

--
-- Constraints for table `order_details`
--
ALTER TABLE `order_details`
  ADD CONSTRAINT `order_details_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `customer_orders` (`order_id`),
  ADD CONSTRAINT `order_details_ibfk_2` FOREIGN KEY (`drug_id`) REFERENCES `drugs_master` (`drug_id`),
  ADD CONSTRAINT `order_details_ibfk_3` FOREIGN KEY (`lot_inventory_id`) REFERENCES `inventory_lots` (`lot_inventory_id`);

--
-- Constraints for table `returns`
--
ALTER TABLE `returns`
  ADD CONSTRAINT `returns_ibfk_1` FOREIGN KEY (`sales_item_id`) REFERENCES `sales_items` (`id`),
  ADD CONSTRAINT `returns_ibfk_2` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `sales_items`
--
ALTER TABLE `sales_items`
  ADD CONSTRAINT `sales_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`sale_id`),
  ADD CONSTRAINT `sales_items_ibfk_2` FOREIGN KEY (`lot_id`) REFERENCES `inventory_lots` (`lot_inventory_id`),
  ADD CONSTRAINT `sales_items_ibfk_3` FOREIGN KEY (`drug_id`) REFERENCES `drugs_master` (`drug_id`);

--
-- Constraints for table `staff_info`
--
ALTER TABLE `staff_info`
  ADD CONSTRAINT `staff_info_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `supplier_drugs`
--
ALTER TABLE `supplier_drugs`
  ADD CONSTRAINT `supplier_drugs_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`),
  ADD CONSTRAINT `supplier_drugs_ibfk_2` FOREIGN KEY (`drug_id`) REFERENCES `drugs_master` (`drug_id`);

--
-- Constraints for table `supplier_medicines`
--
ALTER TABLE `supplier_medicines`
  ADD CONSTRAINT `supplier_medicines_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `supplier_medicines_ibfk_2` FOREIGN KEY (`drug_id`) REFERENCES `drugs_master` (`drug_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `role` (`role_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
