-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 27, 2026 at 06:18 AM
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
-- Database: `edonate_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `role` enum('Admin','Staff') DEFAULT 'Staff',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `remember_token` varchar(100) DEFAULT NULL,
  `remember_token_expires_at` timestamp NULL DEFAULT NULL,
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `two_factor_secret` text DEFAULT NULL,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `two_factor_recovery_codes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`two_factor_recovery_codes`)),
  `two_factor_last_verified_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `username`, `email`, `password`, `full_name`, `role`, `created_at`, `remember_token`, `remember_token_expires_at`, `two_factor_enabled`, `two_factor_secret`, `two_factor_confirmed_at`, `two_factor_recovery_codes`, `two_factor_last_verified_at`) VALUES
(2, 'Merrick', 'fortismerrick@gmail.com', '$2y$12$uRhH7MuDWYgPkP31I/zmj.QoCnBdAde3b5dtXxa7wUBbNQ7l4fsFu', 'John Merrick Fortis', 'Admin', '2026-03-18 05:15:40', NULL, NULL, 1, 'eyJpdiI6IlFWTWdPaGQ0NTRuSVBuV202WnpqREE9PSIsInZhbHVlIjoiQzBuYUhmb0EyWWIra3lrcnNGTFphbGxMYzVBZ1lLY2hJSWlIQXRVMkdLMD0iLCJtYWMiOiJiMGUwMzZjMDg0NDg0YjM4NzcwNjZiYWI5MjA1OWM4Y2FkMWE2MWM1NDYwYmQ3ODBiZTUxYTEwNGZiMjFmMTNmIiwidGFnIjoiIn0=', '2026-04-23 04:06:38', '[\"$2y$12$w08NBTRcJSM9ENCrkehkGOC3h/obNVdZ4CPn4x4d3jNFfqZbveEEe\",\"$2y$12$ZyJvvKc3.L4gVRLNdZSWiOoDRCLCmxybCMqKTm7kxtd7xPoRzufju\",\"$2y$12$uSOw.dc69k8vpF/y7MoySehf/bEj5R0OzRpCyJYiTicXu0PmBH00q\",\"$2y$12$zVfHnvF.rT9ERZLyiSwTJuOEJAfVJ7ee2cnjMBveTPfaUoJEuDtRe\",\"$2y$12$FUCG7/vA4y55Y0XaPw.dv.uFjVxb1hNHjUwUv8spJF4UVDfeNKlbe\",\"$2y$12$DoW4nDQvzQhQ5siZ4dhoS.ZtTy7ukqu7giQRqUh2D21.bz0/sJTaS\",\"$2y$12$VOX4d/GGQwF7IAae9HpLm.NLjrt1V8j5uu291cM6adjZR4BleQuUy\",\"$2y$12$98xD2pF/7FQ3VJj/FlNshuhUR671vvSRBJ0I5H5EUDHuf54Fapi4e\"]', '2026-04-26 19:50:56'),
(3, 'Queen', 'queen@gmail.com', '$2y$12$pow614REn1J5u0QKYzkFxO7jnFrvWfZmuUr3aKxC8e.I3EDOG39Hu', 'Queen Trixia Fallarme', 'Staff', '2026-04-14 08:45:26', NULL, NULL, 0, NULL, NULL, NULL, NULL),
(4, 'johan', 'merrickfortis2004@gmail.com', '$2y$12$KAtKA2pBJPHP7242MZV.m.oeP2TkbEyU9NBgNQ4c38U70Ma9w4nBG', 'Johan Franz Firmalan', 'Staff', '2026-04-17 23:33:02', NULL, NULL, 1, 'eyJpdiI6Ilc4bmEzQjdGdURheUlkVWdBU29JTmc9PSIsInZhbHVlIjoiQWxWYmhPTUpxWE5rb3NaT1lzbDRpTlJ3d2IyWDdCSHpPNkhsWXhuSVRsdz0iLCJtYWMiOiJkMzBhNjkzMGZkZTZjZWE3M2Q4ZmIyYjUzYzU1MjY4Y2U2Yzg2ZjY4Mjk5NDEwNjEzMTBkMDk1YzY4ODBmYzEwIiwidGFnIjoiIn0=', '2026-04-18 00:38:16', '[\"$2y$12$tsJTHd3GuerqwiUCKIHVnOVXFhIIxSbfXZ0G3h5q8mByEazvEiBra\",\"$2y$12$1mZFrKOhzgA.cqoLghLZLOh7/OHDhdMVvaJqr1P.nQWFANKGTOFza\",\"$2y$12$HEDO7dll6.YV5A53nJFeFOGTxR6r6kJ8ABw8N4FWvdXcseL.eIyNu\",\"$2y$12$lV9q6uTO4tAP0qUQSw38VefassPO406xPe763lNIzKeaJ1IVNOEmS\",\"$2y$12$.zbppDfpm4yljaF6RnAhn..RCYEkZunfUlNVH/VrG9cITqAir3VWe\",\"$2y$12$vxPpk9bZh7DYERyaPU0CMuN2ngoqH9LI5PfuW/OopCNXzpiob3gfG\",\"$2y$12$rauxzUncME1.EpYQIH/Se.ZaXJMBQviyctCTSv.9gnf0mRIzTk7G2\",\"$2y$12$OxNz/T23SrM4CxnV2YzgxuwGMa0QocvN/UAv0NJaHFyllGqn0DRnO\"]', '2026-04-23 04:08:09');

-- --------------------------------------------------------

--
-- Table structure for table `admin_security_settings`
--

CREATE TABLE `admin_security_settings` (
  `admin_security_setting_id` bigint(20) UNSIGNED NOT NULL,
  `enforce_two_factor` tinyint(1) NOT NULL DEFAULT 1,
  `session_timeout_minutes` tinyint(3) UNSIGNED NOT NULL DEFAULT 10,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_security_settings`
--

INSERT INTO `admin_security_settings` (`admin_security_setting_id`, `enforce_two_factor`, `session_timeout_minutes`, `created_at`, `updated_at`) VALUES
(1, 1, 10, '2026-04-17 23:56:02', '2026-04-17 23:56:02');

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `appointment_id` int(11) NOT NULL,
  `donor_id` int(11) DEFAULT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `admin_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `audit_log_id` bigint(20) UNSIGNED NOT NULL,
  `actor_admin_id` bigint(20) UNSIGNED DEFAULT NULL,
  `actor_name` varchar(150) DEFAULT NULL,
  `actor_role` varchar(50) DEFAULT NULL,
  `action_type` varchar(50) NOT NULL,
  `module_type` varchar(80) DEFAULT NULL,
  `target_table` varchar(80) DEFAULT NULL,
  `target_id` bigint(20) UNSIGNED DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `result` varchar(30) NOT NULL DEFAULT 'success',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`audit_log_id`, `actor_admin_id`, `actor_name`, `actor_role`, `action_type`, `module_type`, `target_table`, `target_id`, `description`, `ip_address`, `result`, `metadata`, `created_at`) VALUES
(1, 2, 'John Merrick Fortis', 'Admin', 'create', 'rbac', 'admins', 3, 'Created admin account: Queen Trixia Fallarme', '127.0.0.1', 'success', '{\"username\":\"Queen\",\"email\":\"queen@gmail.com\",\"role\":\"staff\"}', '2026-04-14 08:45:27'),
(2, 2, 'John Merrick Fortis', 'Admin', 'update', 'rbac', 'admins', 2, 'Enabled Google Authenticator two-factor authentication.', '127.0.0.1', 'success', '{\"security_event\":\"two_factor_enabled\"}', '2026-04-17 21:00:08'),
(3, 2, 'John Merrick Fortis', 'Admin', 'login', 'rbac', 'admins', 2, 'Completed admin login with two-factor authentication.', '127.0.0.1', 'success', '{\"two_factor_method\":\"totp\"}', '2026-04-17 21:02:06'),
(4, 2, 'John Merrick Fortis', 'Admin', 'login', 'rbac', 'admins', 2, 'Completed admin login with two-factor authentication.', '127.0.0.1', 'success', '{\"two_factor_method\":\"totp\"}', '2026-04-17 21:04:41'),
(5, 2, 'John Merrick Fortis', 'Admin', 'update', 'rbac', 'admins', 2, 'Disabled Google Authenticator two-factor authentication.', '127.0.0.1', 'success', '{\"security_event\":\"two_factor_disabled\"}', '2026-04-17 21:05:59'),
(6, 2, 'John Merrick Fortis', 'Admin', 'update', 'rbac', 'admins', 2, 'Enabled Google Authenticator two-factor authentication.', '127.0.0.1', 'success', '{\"security_event\":\"two_factor_enabled\"}', '2026-04-17 21:06:41'),
(7, 2, 'John Merrick Fortis', 'Admin', 'login', 'rbac', 'admins', 2, 'Completed admin login with two-factor authentication.', '127.0.0.1', 'success', '{\"two_factor_method\":\"recovery_code\"}', '2026-04-17 21:07:55'),
(8, 2, 'John Merrick Fortis', 'Admin', 'update', 'rbac', 'admins', 2, 'Disabled Google Authenticator two-factor authentication.', '127.0.0.1', 'success', '{\"security_event\":\"two_factor_disabled\"}', '2026-04-17 21:09:35'),
(9, 2, 'John Merrick Fortis', 'Admin', 'update', 'rbac', 'admins', 2, 'Enabled Google Authenticator two-factor authentication.', '127.0.0.1', 'success', '{\"security_event\":\"two_factor_enabled\"}', '2026-04-17 21:10:12'),
(10, 2, 'John Merrick Fortis', 'Admin', 'login', 'rbac', 'admins', 2, 'Completed admin login with two-factor authentication.', '127.0.0.1', 'success', '{\"two_factor_method\":\"totp\"}', '2026-04-17 21:11:51'),
(11, 2, 'John Merrick Fortis', 'Admin', 'login', 'rbac', 'admins', 2, 'Completed admin login with two-factor authentication.', '127.0.0.1', 'success', '{\"two_factor_method\":\"totp\"}', '2026-04-17 23:31:58'),
(12, 2, 'John Merrick Fortis', 'Admin', 'create', 'rbac', 'admins', 4, 'Created admin account: Johan Franz Firmalan', '127.0.0.1', 'success', '{\"username\":\"johan\",\"email\":\"merrickfortis2004@gmail.com\",\"role\":\"staff\"}', '2026-04-17 23:33:02'),
(13, 2, 'John Merrick Fortis', 'Admin', 'login', 'rbac', 'admins', 2, 'Completed admin login with two-factor authentication.', '127.0.0.1', 'success', '{\"two_factor_method\":\"totp\"}', '2026-04-17 23:39:54'),
(14, 2, 'John Merrick Fortis', 'Admin', 'login', 'rbac', 'admins', 2, 'Completed admin login with two-factor authentication.', '127.0.0.1', 'success', '{\"two_factor_method\":\"totp\"}', '2026-04-17 23:54:52'),
(15, 2, 'John Merrick Fortis', 'Admin', 'security_policy_update', 'security', 'admin_security_settings', NULL, 'Updated global admin security settings.', '127.0.0.1', 'success', '{\"two_factor_required\":true,\"session_timeout\":10}', '2026-04-17 23:56:02'),
(16, 2, 'John Merrick Fortis', 'Admin', 'login', 'rbac', 'admins', 2, 'Completed admin login with two-factor authentication.', '127.0.0.1', 'success', '{\"two_factor_method\":\"totp\"}', '2026-04-17 23:58:25'),
(17, 4, 'Johan Franz Firmalan', 'Staff', 'update', 'rbac', 'admins', 4, 'Enabled Google Authenticator two-factor authentication.', '127.0.0.1', 'success', '{\"security_event\":\"two_factor_enabled\"}', '2026-04-18 00:38:16'),
(18, 4, 'Johan Franz Firmalan', 'Staff', 'login', 'rbac', 'admins', 4, 'Completed admin login with two-factor authentication.', '127.0.0.1', 'success', '{\"two_factor_method\":\"totp\"}', '2026-04-18 00:56:41'),
(19, 2, 'John Merrick Fortis', 'Admin', 'login', 'rbac', 'admins', 2, 'Completed admin login with two-factor authentication.', '127.0.0.1', 'success', '{\"two_factor_method\":\"totp\"}', '2026-04-18 00:57:41'),
(20, 2, 'John Merrick Fortis', 'Admin', 'login', 'rbac', 'admins', 2, 'Completed admin login with two-factor authentication.', '127.0.0.1', 'success', '{\"two_factor_method\":\"totp\"}', '2026-04-18 01:08:25'),
(21, 2, 'John Merrick Fortis', 'Admin', 'login', 'rbac', 'admins', 2, 'Completed admin login with two-factor authentication.', '127.0.0.1', 'success', '{\"two_factor_method\":\"totp\"}', '2026-04-23 04:01:57'),
(22, 2, 'John Merrick Fortis', 'Admin', 'update', 'rbac', 'admins', 2, 'Enabled Google Authenticator two-factor authentication.', '127.0.0.1', 'success', '{\"security_event\":\"two_factor_enabled\"}', '2026-04-23 04:06:38'),
(23, 4, 'Johan Franz Firmalan', 'Staff', 'login', 'rbac', 'admins', 4, 'Completed admin login with two-factor authentication.', '127.0.0.1', 'success', '{\"two_factor_method\":\"totp\"}', '2026-04-23 04:08:09'),
(24, 2, 'John Merrick Fortis', 'Admin', 'login', 'rbac', 'admins', 2, 'Completed admin login with two-factor authentication.', '127.0.0.1', 'success', '{\"two_factor_method\":\"totp\"}', '2026-04-26 19:50:56');

-- --------------------------------------------------------

--
-- Table structure for table `blood_types`
--

CREATE TABLE `blood_types` (
  `blood_type_id` int(11) NOT NULL,
  `blood_type` varchar(5) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `blood_types`
--

INSERT INTO `blood_types` (`blood_type_id`, `blood_type`) VALUES
(1, 'O+'),
(2, 'AB-'),
(3, 'B-'),
(4, 'AB+');

-- --------------------------------------------------------

--
-- Table structure for table `donation_records`
--

CREATE TABLE `donation_records` (
  `donation_id` int(11) NOT NULL,
  `donor_id` int(11) DEFAULT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `donation_date` date DEFAULT NULL,
  `blood_units` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `donors`
--

CREATE TABLE `donors` (
  `donor_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `blood_type_id` int(11) DEFAULT NULL,
  `location_id` int(11) DEFAULT NULL,
  `date_registered` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `donors`
--

INSERT INTO `donors` (`donor_id`, `first_name`, `last_name`, `gender`, `birthdate`, `contact_number`, `blood_type_id`, `location_id`, `date_registered`) VALUES
(15, 'Fortis', 'Faigmaniiiiiiiiiiiii', 'Male', '2026-03-07', '+639940780881', 4, 14, '2026-03-15 19:35:23'),
(16, 'John', 'Merrick Fortis', 'Male', '2004-06-24', '+639940780881', 3, 15, '2026-03-17 08:21:40'),
(17, 'Merrick', 'Fortis', NULL, NULL, NULL, NULL, NULL, '2026-03-17 20:13:02');

-- --------------------------------------------------------

--
-- Table structure for table `donor_authentication`
--

CREATE TABLE `donor_authentication` (
  `auth_id` int(11) NOT NULL,
  `donor_id` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `verification_token` varchar(255) DEFAULT NULL,
  `verification_sent_at` datetime DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `donor_authentication`
--

INSERT INTO `donor_authentication` (`auth_id`, `donor_id`, `email`, `password`, `is_verified`, `verification_token`, `verification_sent_at`, `verified_at`, `created_at`) VALUES
(15, 15, 'kriztopher.kier.estioco@gmail.com', '$2y$12$TNHPwXnoR5ZAUmuZGsy7GelFipel5TY23qDxHhHRBWpskXqy0fhBu', 1, NULL, '2026-03-16 03:35:28', '2026-03-16 03:35:28', '2026-03-15 19:35:26'),
(16, 16, 'merrickfortis2004@gmail.com', '$2y$12$pIPkvnGfm5RxAnIsTu8elOITRV6hrrpqVMDphMIx1y9MweSO7XPjK', 1, NULL, '2026-03-17 16:21:42', '2026-03-17 16:21:42', '2026-03-17 08:21:42'),
(17, 17, 'fortismerrick@gmail.com', '$2y$12$N.rMIsPmLPcQXOMVHAtU4.K.SYNaE4893F3YTPWaRIxy1xwhRrSky', 1, NULL, '2026-03-18 04:13:06', '2026-03-18 04:13:06', '2026-03-17 20:13:06');

-- --------------------------------------------------------

--
-- Table structure for table `donor_forget`
--

CREATE TABLE `donor_forget` (
  `forget_id` int(11) NOT NULL,
  `donor_id` int(11) NOT NULL,
  `reset_token` varchar(255) NOT NULL,
  `token_expires` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `eligibility_status`
--

CREATE TABLE `eligibility_status` (
  `eligibility_id` int(11) NOT NULL,
  `donor_id` int(11) DEFAULT NULL,
  `last_donation_date` date DEFAULT NULL,
  `next_eligible_date` date DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `location_id` int(11) NOT NULL,
  `street_address` varchar(150) DEFAULT NULL,
  `barangay_name` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `latitude` decimal(10,6) DEFAULT NULL,
  `longitude` decimal(10,6) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`location_id`, `street_address`, `barangay_name`, `city`, `province`, `latitude`, `longitude`) VALUES
(1, 'POBLACION, CONCEPCION, ROMBLON', 'Balintawak', 'Lipa City', 'fortism@gmail.com', NULL, NULL),
(2, 'POBLACION, CONCEPCION, ROMBLON', 'Balintawak', 'Lipa City', 'ROMBLON', NULL, NULL),
(3, 'POBLACION, CONCEPCION, ROMBLON', 'Balintawak', 'Lipa City', 'ROMBLON', NULL, NULL),
(4, 'POBLACION, CONCEPCION, ROMBLON', 'Balintawak', 'Lipa City', 'ROMBLON', NULL, NULL),
(5, 'POBLACION, CONCEPCION, ROMBLON', 'Balintawak', 'Lipa City', 'ROMBLON', NULL, NULL),
(6, 'POBLACION, CONCEPCION, ROMBLON', 'Balintawak', 'Lipa City', 'ROMBLON', NULL, NULL),
(7, 'POBLACION, CONCEPCION, ROMBLON', 'Balintawak', 'Lipa City', 'ROMBLON', NULL, NULL),
(8, 'POBLACION, CONCEPCION, ROMBLON', 'Balintawak', 'Lipa City', 'ROMBLON', NULL, NULL),
(9, 'POBLACION, CONCEPCION, ROMBLON', 'Balintawak', 'Lipa City', 'ROMBLON', NULL, NULL),
(10, 'POBLACION, CONCEPCION, ROMBLON', 'Balintawak', 'Lipa City', 'ROMBLON', NULL, NULL),
(11, 'POBLACION, CONCEPCION, ROMBLON', 'Balintawak', 'Lipa City', 'ROMBLON', NULL, NULL),
(12, 'POBLACION, CONCEPCION, ROMBLON', 'Balintawak', 'Lipa City', 'ROMBLON', NULL, NULL),
(13, 'POBLACION, CONCEPCION, ROMBLON', 'Balintawak', 'Lipa City', 'ROMBLON', NULL, NULL),
(14, 'POBLACION, CONCEPCION, ROMBLON', 'Balintawak', 'Lipa City', 'ROMBLON', NULL, NULL),
(15, 'POBLACION, CONCEPCION, ROMBLON', 'Balintawak', 'Lipa City', 'ROMBLON', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '2026_03_14_040400_create_sessions_table', 1),
(2, '2026_03_29_120000_add_remember_columns_to_admins_table', 2),
(3, '2026_04_15_000000_create_audit_logs_table', 3),
(4, '2026_04_18_120000_add_two_factor_columns_to_admins_table', 4),
(5, '2026_04_18_130000_create_admin_security_settings_table', 5);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `donor_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `notification_type` varchar(50) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('KdRX4LsplADhcmLajWobk9r1mOCPgVIYvtGOM4gX', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'YTo3OntzOjY6Il90b2tlbiI7czo0MDoicVJ5YTE2Vk1kOXVHNW5aWUdCYmNNeFFEWkpyaURDN2lobVFJVDBkbCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mzc6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9hZG1pbi9kYXNoYm9hcmQiO3M6NToicm91dGUiO3M6MTU6ImFkbWluLmRhc2hib2FyZCI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fXM6ODoiYWRtaW5faWQiO2k6MjtzOjE0OiJhZG1pbl91c2VybmFtZSI7czo3OiJNZXJyaWNrIjtzOjE1OiJhZG1pbl9mdWxsX25hbWUiO3M6MTk6IkpvaG4gTWVycmljayBGb3J0aXMiO3M6MTA6ImFkbWluX3JvbGUiO3M6NToiYWRtaW4iO30=', 1777263333),
('mbzjYq4XCWmjPoBHMhnIAvMrtNPKJvONj0T9BplW', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiWFlGMDdURzc4dGNZTktxM3lqQ1RxNnI2WVI5czRWVWU3T0dxd0pEMiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzM6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9hZG1pbi9sb2dpbiI7czo1OiJyb3V0ZSI7czoxMToiYWRtaW4ubG9naW4iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1777261795),
('sYz6RJAJVkYBpdX2HaNHRrEUYSzgnVY3CLwHCOmE', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoiV3VvbFBia09ZdG5RWnhvZFF0WHNJeG56UzVGMjRFYXJjVkdTQk1QaCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzM6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9hZG1pbi9sb2dpbiI7czo1OiJyb3V0ZSI7czoxMToiYWRtaW4ubG9naW4iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjE3OiJwZW5kaW5nX2FkbWluXzJmYSI7YTo2OntzOjg6ImFkbWluX2lkIjtpOjI7czo1OiJlbWFpbCI7czoyMzoiZm9ydGlzbWVycmlja0BnbWFpbC5jb20iO3M6NDoicm9sZSI7czo1OiJhZG1pbiI7czo4OiJyZW1lbWJlciI7YjowO3M6ODoiYXR0ZW1wdHMiO2k6MDtzOjEwOiJleHBpcmVzX2F0IjtpOjE3NzcyNjIxMTk7fX0=', 1777261831);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `admin_security_settings`
--
ALTER TABLE `admin_security_settings`
  ADD PRIMARY KEY (`admin_security_setting_id`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD KEY `donor_id` (`donor_id`),
  ADD KEY `fk_admin_appointment` (`admin_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`audit_log_id`),
  ADD KEY `audit_logs_action_type_created_at_index` (`action_type`,`created_at`),
  ADD KEY `audit_logs_target_table_target_id_index` (`target_table`,`target_id`);

--
-- Indexes for table `blood_types`
--
ALTER TABLE `blood_types`
  ADD PRIMARY KEY (`blood_type_id`);

--
-- Indexes for table `donation_records`
--
ALTER TABLE `donation_records`
  ADD PRIMARY KEY (`donation_id`),
  ADD KEY `donor_id` (`donor_id`),
  ADD KEY `appointment_id` (`appointment_id`);

--
-- Indexes for table `donors`
--
ALTER TABLE `donors`
  ADD PRIMARY KEY (`donor_id`),
  ADD KEY `blood_type_id` (`blood_type_id`),
  ADD KEY `location_id` (`location_id`);

--
-- Indexes for table `donor_authentication`
--
ALTER TABLE `donor_authentication`
  ADD PRIMARY KEY (`auth_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `donor_id` (`donor_id`),
  ADD KEY `idx_auth_email` (`email`),
  ADD KEY `idx_verification_token` (`verification_token`);

--
-- Indexes for table `donor_forget`
--
ALTER TABLE `donor_forget`
  ADD PRIMARY KEY (`forget_id`),
  ADD KEY `donor_id` (`donor_id`);

--
-- Indexes for table `eligibility_status`
--
ALTER TABLE `eligibility_status`
  ADD PRIMARY KEY (`eligibility_id`),
  ADD KEY `donor_id` (`donor_id`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`location_id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `donor_id` (`donor_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `admin_security_settings`
--
ALTER TABLE `admin_security_settings`
  MODIFY `admin_security_setting_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `audit_log_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `blood_types`
--
ALTER TABLE `blood_types`
  MODIFY `blood_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `donation_records`
--
ALTER TABLE `donation_records`
  MODIFY `donation_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `donors`
--
ALTER TABLE `donors`
  MODIFY `donor_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `donor_authentication`
--
ALTER TABLE `donor_authentication`
  MODIFY `auth_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `donor_forget`
--
ALTER TABLE `donor_forget`
  MODIFY `forget_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `eligibility_status`
--
ALTER TABLE `eligibility_status`
  MODIFY `eligibility_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `location_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`donor_id`) REFERENCES `donors` (`donor_id`),
  ADD CONSTRAINT `fk_admin_appointment` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`admin_id`);

--
-- Constraints for table `donation_records`
--
ALTER TABLE `donation_records`
  ADD CONSTRAINT `donation_records_ibfk_1` FOREIGN KEY (`donor_id`) REFERENCES `donors` (`donor_id`),
  ADD CONSTRAINT `donation_records_ibfk_2` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`);

--
-- Constraints for table `donors`
--
ALTER TABLE `donors`
  ADD CONSTRAINT `donors_ibfk_1` FOREIGN KEY (`blood_type_id`) REFERENCES `blood_types` (`blood_type_id`),
  ADD CONSTRAINT `donors_ibfk_2` FOREIGN KEY (`location_id`) REFERENCES `locations` (`location_id`);

--
-- Constraints for table `donor_authentication`
--
ALTER TABLE `donor_authentication`
  ADD CONSTRAINT `donor_authentication_ibfk_1` FOREIGN KEY (`donor_id`) REFERENCES `donors` (`donor_id`) ON DELETE CASCADE;

--
-- Constraints for table `donor_forget`
--
ALTER TABLE `donor_forget`
  ADD CONSTRAINT `donor_forget_ibfk_1` FOREIGN KEY (`donor_id`) REFERENCES `donors` (`donor_id`) ON DELETE CASCADE;

--
-- Constraints for table `eligibility_status`
--
ALTER TABLE `eligibility_status`
  ADD CONSTRAINT `eligibility_status_ibfk_1` FOREIGN KEY (`donor_id`) REFERENCES `donors` (`donor_id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`donor_id`) REFERENCES `donors` (`donor_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
