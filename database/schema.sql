-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql106.infinityfree.com
-- Generation Time: Nov 25, 2026 at 03:44 AM
-- Server version: 11.4.14-MariaDB
-- PHP Version: 7.2.22

CREATE DATABASE IF NOT EXISTS if0_43372442_voting
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE if0_43372442_voting;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_43372442_voting`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `actor_type` enum('admin','voter','system') NOT NULL,
  `actor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `event_type` varchar(80) NOT NULL,
  `target_type` varchar(80) DEFAULT NULL,
  `target_id` bigint(20) UNSIGNED DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL
) ;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `actor_type`, `actor_id`, `event_type`, `target_type`, `target_id`, `details`, `ip_address`, `created_at`) VALUES
(1, 'admin', 1, 'login', NULL, NULL, NULL, 0xc59dbb40, '2026-11-07 08:22:26'),
(2, 'admin', 1, 'logout', NULL, NULL, NULL, 0xc59dbb40, '2026-11-07 08:24:10'),
(3, 'admin', 1, 'login', NULL, NULL, NULL, 0xc59dbb40, '2026-11-07 08:24:13'),
(4, 'admin', 1, 'logout', NULL, NULL, NULL, 0xc59dbb40, '2026-11-07 08:25:36'),
(5, 'system', NULL, 'password_reset_requested', NULL, NULL, NULL, 0xc59dbb40, '2026-11-07 08:25:47'),
(6, 'system', NULL, 'password_reset_completed', 'admin', 1, NULL, 0xc59dbb40, '2026-11-07 08:27:06'),
(7, 'admin', 1, 'login', NULL, NULL, NULL, 0xc59dbb40, '2026-11-07 08:27:13'),
(8, 'system', NULL, 'voter_registered', 'voter', 1, NULL, 0xc59dbb40, '2026-11-07 08:30:19'),
(9, 'voter', 1, 'login', NULL, NULL, NULL, 0xc59dbb40, '2026-11-07 08:30:48'),
(10, 'admin', 1, 'election_created', 'election', 1, NULL, 0xc59dbb40, '2026-11-07 08:32:57'),
(11, 'admin', 1, 'candidate_created', 'candidate', 1, NULL, 0xc59dbb40, '2026-11-07 08:35:05'),
(12, 'admin', 1, 'candidate_updated', 'candidate', 1, NULL, 0xc59dbb40, '2026-11-07 08:35:56'),
(13, 'system', NULL, 'login_failed', NULL, NULL, NULL, 0xc59dba3e, '2026-11-08 10:01:36'),
(14, 'system', NULL, 'login_failed', NULL, NULL, NULL, 0xc59dba3e, '2026-11-08 10:01:39'),
(15, 'system', NULL, 'voter_registered', 'voter', 2, NULL, 0xc59dba3e, '2026-11-08 10:04:48'),
(16, 'voter', 2, 'login', NULL, NULL, NULL, 0xc59dba3e, '2026-11-08 10:04:55'),
(17, 'system', NULL, 'password_reset_requested', NULL, NULL, NULL, 0x9a4448a4, '2026-11-09 07:41:00'),
(18, 'system', NULL, 'voter_registered', 'voter', 3, NULL, 0xc59dba07, '2026-11-09 07:49:33'),
(19, 'voter', 3, 'login', NULL, NULL, NULL, 0xc59dba07, '2026-11-09 07:49:50'),
(20, 'system', NULL, 'voter_registered', 'voter', 4, NULL, 0x9a4448a4, '2026-11-09 07:52:38'),
(21, 'voter', 4, 'login', NULL, NULL, NULL, 0x9a4448a4, '2026-11-09 07:52:45'),
(22, 'voter', 3, 'logout', NULL, NULL, NULL, 0xc59dba07, '2026-11-09 07:56:37'),
(23, 'system', NULL, 'login_failed', NULL, NULL, NULL, 0xc59dba07, '2026-11-09 07:56:51'),
(24, 'admin', 1, 'login', NULL, NULL, NULL, 0xc59dba07, '2026-11-09 07:58:12'),
(25, 'system', NULL, 'login_failed', NULL, NULL, NULL, 0x9a444896, '2026-11-09 07:59:09'),
(26, 'admin', 1, 'junior_admin_promote', 'voter', 4, NULL, 0xc59dba07, '2026-11-09 08:00:01'),
(27, 'voter', 4, 'login', NULL, NULL, NULL, 0x9a4448a4, '2026-11-09 08:00:28'),
(28, 'system', NULL, 'voter_registered', 'voter', 5, NULL, 0x9a444896, '2026-11-09 08:01:44'),
(29, 'voter', 5, 'login', NULL, NULL, NULL, 0x9a444896, '2026-11-09 08:01:54'),
(30, 'admin', 1, 'logout', NULL, NULL, NULL, 0xc59dba07, '2026-11-09 08:09:00'),
(31, 'admin', 1, 'login', NULL, NULL, NULL, 0xc59dba07, '2026-11-09 08:09:07'),
(32, 'admin', 1, 'logout', NULL, NULL, NULL, 0xc59dba07, '2026-11-09 08:09:40'),
(33, 'system', NULL, 'login_failed', NULL, NULL, NULL, 0xc59dba07, '2026-11-09 08:10:24'),
(34, 'admin', 1, 'login', NULL, NULL, NULL, 0xc59dba07, '2026-11-09 08:10:39'),
(35, 'admin', 1, 'junior_admin_demote', 'voter', 4, NULL, 0xc59dba07, '2026-11-09 08:11:19'),
(36, 'voter', 4, 'login', NULL, NULL, NULL, 0x9a4448a4, '2026-11-09 08:11:30'),
(37, 'admin', 1, 'voter_status_updated', 'voter', 4, '{\"status\":\"active\"}', 0xc59dba07, '2026-11-09 08:11:38'),
(38, 'admin', 1, 'voter_status_updated', 'voter', 5, '{\"status\":\"deactivated\"}', 0xc59dba07, '2026-11-09 08:11:48'),
(39, 'voter', 4, 'login', NULL, NULL, NULL, 0x9a4448a4, '2026-11-09 08:11:55'),
(40, 'admin', 1, 'voter_status_updated', 'voter', 4, '{\"status\":\"deactivated\"}', 0xc59dba07, '2026-11-09 08:12:10'),
(41, 'admin', 1, 'voter_status_updated', 'voter', 5, '{\"status\":\"unverified\"}', 0xc59dba07, '2026-11-09 08:12:27'),
(42, 'voter', 4, 'login', NULL, NULL, NULL, 0x9a4448a4, '2026-11-09 08:23:53'),
(43, 'system', NULL, 'login_failed', NULL, NULL, NULL, 0xc59dba07, '2026-11-09 08:37:08'),
(44, 'system', NULL, 'login_failed', NULL, NULL, NULL, 0xc59dba07, '2026-11-09 08:37:27'),
(45, 'system', NULL, 'login_failed', NULL, NULL, NULL, 0xc59dba07, '2026-11-09 08:37:41'),
(46, 'system', NULL, 'login_failed', NULL, NULL, NULL, 0xc59dba07, '2026-11-09 08:38:20'),
(47, 'admin', 1, 'login', NULL, NULL, NULL, 0xc59dba07, '2026-11-09 08:38:34'),
(48, 'admin', 1, 'voter_status_updated', 'voter', 5, '{\"status\":\"active\"}', 0xc59dba07, '2026-11-09 08:38:46'),
(49, 'admin', 1, 'voter_account_deleted', 'voter', 3, '{\"full_name\":\"Johnson Mike\"}', 0xc59dba07, '2026-11-09 08:38:58'),
(50, 'admin', 1, 'voter_account_deleted', 'voter', 1, '{\"full_name\":\"NEZERWA Allain Junior\"}', 0xc59dba07, '2026-11-09 08:39:08'),
(51, 'voter', 6, 'voter_email_verified', 'voter', 6, NULL, 0xc59dbbce, '2026-11-09 08:43:01'),
(52, 'admin', 1, 'election_created', 'election', 2, NULL, 0xc59dbbce, '2026-11-09 08:47:50'),
(53, 'admin', 1, 'candidate_created', 'candidate', 2, NULL, 0xc59dbbce, '2026-11-09 08:48:27'),
(54, 'admin', 1, 'election_updated', 'election', 2, NULL, 0xc59dbbce, '2026-11-09 08:48:57'),
(55, 'admin', 1, 'election_updated', 'election', 2, NULL, 0xc59dbbce, '2026-11-09 08:49:10'),
(56, 'admin', 1, 'candidate_created', 'candidate', 3, NULL, 0xc59db874, '2026-11-09 08:50:25'),
(57, 'voter', 4, 'vote_cast', 'election', 2, NULL, 0x9a4448a4, '2026-11-09 08:51:36'),
(58, 'voter', 6, 'vote_cast', 'election', 2, NULL, 0xc59db874, '2026-11-09 08:51:51'),
(59, 'voter', 5, 'login', NULL, NULL, NULL, 0x9a444896, '2026-11-09 08:52:42'),
(60, 'voter', 5, 'vote_cast', 'election', 2, NULL, 0x9a444896, '2026-11-09 08:52:50'),
(61, 'admin', 1, 'election_closed', 'election', 2, NULL, 0xc59db874, '2026-11-09 08:53:13'),
(62, 'voter', 5, 'login', NULL, NULL, NULL, 0x9a444896, '2026-11-09 13:52:09'),
(63, 'voter', 5, 'login', NULL, NULL, NULL, 0xc59dbbe0, '2026-11-11 06:36:55'),
(64, 'voter', 5, 'logout', NULL, NULL, NULL, 0xc59dbbe0, '2026-11-11 06:38:46'),
(65, 'voter', 4, 'login', NULL, NULL, NULL, 0x9a4448d3, '2026-11-11 09:52:47'),
(66, 'voter', 7, 'voter_email_verified', 'voter', 7, NULL, 0xc59da5d1, '2026-11-11 10:17:33'),
(67, 'voter', 4, 'login', NULL, NULL, NULL, 0x9a4448e0, '2026-11-13 07:37:11'),
(68, 'voter', 5, 'login', NULL, NULL, NULL, 0x9a444867, '2026-11-19 07:24:01'),
(69, 'voter', 5, 'login', NULL, NULL, NULL, 0x9a44487c, '2026-11-25 06:37:45'),
(70, 'admin', 1, 'login', NULL, NULL, NULL, 0x9a4448ee, '2026-11-25 06:48:01');

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `singleton_key` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `full_name` varchar(160) NOT NULL,
  `email` varchar(254) NOT NULL,
  `email_normalized` varchar(254) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `session_version` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `singleton_key`, `full_name`, `email`, `email_normalized`, `password_hash`, `session_version`, `created_at`, `updated_at`) VALUES
(1, 1, 'Alain Junior NEZERWA', 'allainjuniornezerwa@gmail.com', 'allainjuniornezerwa@gmail.com', '$2y$12$eJ8t4oyI9QPlpytzFO.OEu5IqVY16GSkg8WjS.j09ytcxRvJq16gO', 1, '2026-11-06 22:20:19', '2026-11-07 08:27:06');

-- --------------------------------------------------------

--
-- Table structure for table `auth_rate_limits`
--

CREATE TABLE `auth_rate_limits` (
  `limiter_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `attempts` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `window_started_at` datetime NOT NULL,
  `blocked_until` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `auth_rate_limits`
--

INSERT INTO `auth_rate_limits` (`limiter_hash`, `attempts`, `window_started_at`, `blocked_until`, `updated_at`) VALUES
('0098cbd7e28e3b53e8d4a18547149515744aeaf67bb75c48d6c78e5bb40113dc', 1, '2026-11-09 08:00:48', NULL, '2026-11-09 08:00:49'),
('039bca7277ab34e4de3676047b5969f82398115765884e1db645bdf74223e29c', 1, '2026-11-09 07:40:59', NULL, '2026-11-09 07:41:00'),
('03e4c82362b42b7251f54ffc45645778d183690dadc9e24bd823694a65c26eff', 1, '2026-11-07 08:29:18', NULL, '2026-11-07 08:29:17'),
('119f7bc8349cf4bfeae886d3b487ba3fa28708f9e7af672e888aab7bab3dcf22', 1, '2026-11-09 08:41:19', NULL, '2026-11-09 08:41:18'),
('2cc832dc363b667f57014622dedc9646a9f2492fce4a7f04328e621602885206', 5, '2026-11-08 10:00:54', NULL, '2026-11-08 10:03:25'),
('30a62ae8d92c551fef50e23a9b9711d965bfa0ddf93490b5cb3cad22ac1c9dc4', 1, '2026-11-09 07:40:59', NULL, '2026-11-09 07:41:00'),
('35febe3183a91c49cf9e638c0478598dd18ee829769341cd6a6ffdd144939e62', 1, '2026-11-07 08:25:44', NULL, '2026-11-07 08:25:44'),
('37d9bd2027735503b8845ea523a6ae42f761113090c47a229056ef15db6e43e6', 1, '2026-11-11 10:16:07', NULL, '2026-11-11 10:16:07'),
('4469ac5869fe35ca8acfa35258b463872ff666e88e56979f051365c0c1093b05', 1, '2026-11-09 08:42:38', NULL, '2026-11-09 08:42:38'),
('52c952f8ef298480dcd3d50931a64a3c7dc3a544b4541686735140c81d993c95', 1, '2026-11-09 07:48:39', NULL, '2026-11-09 07:48:40'),
('52ceacffc62f9ac6eb4db674aa974024004326cc8d88d0bbf9c0c71df0d9a98c', 1, '2026-11-09 07:40:59', NULL, '2026-11-09 07:41:00'),
('6b90797e68e7737cedc93dae1144df9caa96ceaf17dba890c95e83800b149567', 5, '2026-11-08 10:00:54', NULL, '2026-11-08 10:03:25'),
('799176afb56bdcc037cffd8575cb91f8b8b58ab2756c5551548644832b7577c2', 1, '2026-11-09 07:48:39', NULL, '2026-11-09 07:48:40'),
('9780b87e0c66f7f41037434f243a1e41edd4c3ef03dafb3f6a91e58ef4e017ee', 1, '2026-11-07 08:25:44', NULL, '2026-11-07 08:25:44'),
('a7720735522db9f6fe3e062ec9c154587d5d74f7574058b06235fc49cc3a6deb', 1, '2026-11-09 08:00:48', NULL, '2026-11-09 08:00:49'),
('b00d1b2ea898e60a60772a931a2758dceff2b4a939341de343bbb73d696c856e', 1, '2026-11-09 07:52:01', NULL, '2026-11-09 07:52:01'),
('b5cbfa624a999d64a476453422f015a0792ef2c1ae8d5155dc8fffd88a25de2d', 1, '2026-11-07 08:25:44', NULL, '2026-11-07 08:25:44'),
('c0c13ea6acd9c1f04ea20bef69e782782a40e7035740812663cd22ade8b46064', 1, '2026-11-11 10:16:07', NULL, '2026-11-11 10:16:07'),
('c84b581f5db8dc10ecb4b538891deb3ab1138684966e58ed3c4b988a37e38f9b', 1, '2026-11-09 07:40:59', NULL, '2026-11-09 07:41:00'),
('dd753a336ce93a55d9b9525ae7c941964695152224bdb18a228c7f5d5efe8f74', 1, '2026-11-09 07:52:01', NULL, '2026-11-09 07:52:01'),
('de790fc87b172e4056c6520d7812803d6ad5bd06ac923dfc9e67c4ccc84b5f5d', 2, '2026-11-09 08:41:19', NULL, '2026-11-09 08:42:38'),
('f1aaaf2a7c463fda56a73da6b128384298a9d80215a8bdaf2897881e761ad5aa', 1, '2026-11-07 08:25:44', NULL, '2026-11-07 08:25:44'),
('f3225b285e4517af303eb105318cb4a807ac83413bd7cf7a5add8665a392cf77', 1, '2026-11-07 08:29:18', NULL, '2026-11-07 08:29:17');

-- --------------------------------------------------------

--
-- Table structure for table `candidates`
--

CREATE TABLE `candidates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `election_id` bigint(20) UNSIGNED NOT NULL,
  `full_name` varchar(160) NOT NULL,
  `party` varchar(160) NOT NULL DEFAULT '',
  `biography` text NOT NULL,
  `photo_path` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `candidates`
--

INSERT INTO `candidates` (`id`, `election_id`, `full_name`, `party`, `biography`, `photo_path`, `created_at`) VALUES
(2, 2, 'Aboy Junior', 'Abagome', 'Yishe abantu 300', 'uploads/candidates/704ca6c1729ba796d7f7d6a705d2b881d1bf18b1.jpg', '2026-11-09 08:48:27'),
(3, 2, 'Rocky Kimomo', 'Rocy entertainment', 'abagome industries', 'uploads/candidates/e07c264c3c49c3326659db161efb95acd20ad0ad.jpg', '2026-11-09 08:50:25');

-- --------------------------------------------------------

--
-- Table structure for table `elections`
--

CREATE TABLE `elections` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(180) NOT NULL,
  `description` text NOT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `status` enum('draft','scheduled','active','closed') NOT NULL DEFAULT 'draft',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `closed_at` datetime DEFAULT NULL
) ;

--
-- Dumping data for table `elections`
--

INSERT INTO `elections` (`id`, `name`, `description`, `starts_at`, `ends_at`, `status`, `created_by`, `created_at`, `updated_at`, `closed_at`) VALUES
(2, 'Chef De Cartie', 'Umugome mukuru', '2026-11-09 08:51:00', '2026-11-09 08:55:00', 'closed', 1, '2026-11-09 08:47:50', '2026-11-09 08:53:13', '2026-11-09 08:53:13');

-- --------------------------------------------------------

--
-- Table structure for table `email_notifications`
--

CREATE TABLE `email_notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `recipient_email` varchar(254) NOT NULL,
  `notification_type` varchar(80) NOT NULL,
  `status` enum('sent','failed') NOT NULL,
  `error_message` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `email_notifications`
--

INSERT INTO `email_notifications` (`id`, `recipient_email`, `notification_type`, `status`, `error_message`, `created_at`) VALUES
(1, 'allainjuniornezerwa@gmail.com', 'email_otp_password_recovery', 'sent', NULL, '2026-11-07 08:25:47'),
(2, 'alainjuniornezerwa@gmail.com', 'email_otp_registration', 'sent', NULL, '2026-11-07 08:29:19'),
(3, 'alainjuniornezerwa@gmail.com', 'registration_received', 'sent', NULL, '2026-11-07 08:30:21'),
(4, 'irakozeeddychristian@gmail.com', 'email_otp_registration', 'sent', NULL, '2026-11-08 10:00:59'),
(5, 'irakozeeddychristian@gmail.com', 'email_otp_registration', 'sent', NULL, '2026-11-08 10:03:27'),
(6, 'irakozeeddychristian@gmail.com', 'registration_received', 'sent', NULL, '2026-11-08 10:04:50'),
(7, 'ibipusipusi@gmail.com', 'email_otp_registration', 'sent', NULL, '2026-11-09 07:48:43'),
(8, 'ibipusipusi@gmail.com', 'registration_received', 'sent', NULL, '2026-11-09 07:49:35'),
(9, 'sabanasage@gmail.com', 'email_otp_registration', 'sent', NULL, '2026-11-09 07:52:03'),
(10, 'sabanasage@gmail.com', 'registration_received', 'sent', NULL, '2026-11-09 07:52:39'),
(11, 'moiseirakiza2@gmail.com', 'email_otp_registration', 'sent', NULL, '2026-11-09 08:00:50'),
(12, 'moiseirakiza2@gmail.com', 'registration_received', 'sent', NULL, '2026-11-09 08:01:45'),
(13, 'alainjuniornezerwa@gmail.com', 'email_otp_email_verification', 'sent', NULL, '2026-11-09 08:41:20'),
(14, 'alainjuniornezerwa@gmail.com', 'email_otp_email_verification', 'sent', NULL, '2026-11-09 08:42:39'),
(15, 'rwandamuzi@gmail.com', 'email_otp_email_verification', 'sent', NULL, '2026-11-11 10:16:12');

-- --------------------------------------------------------

--
-- Table structure for table `otp_challenges`
--

CREATE TABLE `otp_challenges` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `purpose` enum('registration','password_recovery','email_verification') NOT NULL,
  `flow_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `email` varchar(254) NOT NULL,
  `email_normalized` varchar(254) NOT NULL,
  `account_type` enum('admin','voter') DEFAULT NULL,
  `account_id` bigint(20) UNSIGNED DEFAULT NULL,
  `payload` mediumtext DEFAULT NULL,
  `code_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `resend_count` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `last_sent_at` datetime NOT NULL,
  `verified_at` datetime DEFAULT NULL,
  `consumed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `otp_challenges`
--

INSERT INTO `otp_challenges` (`id`, `purpose`, `flow_hash`, `email`, `email_normalized`, `account_type`, `account_id`, `payload`, `code_hash`, `attempts`, `resend_count`, `expires_at`, `last_sent_at`, `verified_at`, `consumed_at`, `created_at`) VALUES
(1, 'password_recovery', 'ca092b268d5811583216d69adfa6154dded1d7fe8a59d9aee180cd51e900ab3e', 'allainjuniornezerwa@gmail.com', 'allainjuniornezerwa@gmail.com', 'admin', 1, NULL, NULL, 0, 0, '2026-11-07 08:35:44', '2026-11-07 08:25:44', '2026-11-07 08:26:37', '2026-11-07 08:27:06', '2026-11-07 08:25:44'),
(2, 'registration', '2619076c53fa9f6433ae25a48bf8586145ddf6e99e98ebde617acfc08e03e057', 'alainjuniornezerwa@gmail.com', 'alainjuniornezerwa@gmail.com', NULL, NULL, 'v1:V1OTWpluK2v1UgrMUJMqmS65OcCKC9YzUuTAFAzBMikpXSFRbpwUxKdZff14EqQOzv0VhA73qvRTMMfm6VmE2i6oGJT63GigSYECx4rny5lqJVAFc91ssARhtBsO/eCmsqVXMp6MhsDYvAHX7atvkWVFqP90d1IX89AyWbL1bfh3W35mi192Fd+BysKo8iXKGIpq8qL524xtD9r4ZvRRbWp9FE7FoD2d57K0cGUwiUeTWjwSxdqIb6i0L6TTfK9i1E23uCPneepevh+1C+ccVZTiowkWvs18zNrLJsfAH+m5inLHrn+gTrTSepGDxOAddrFaWrIdQruKdj3qgIsVtLhCjTvcAvYYGKF925Gb3gaSiwgK4qIYQLM02y23+qv/18EJqzbsbZMYNt4W5N7AuV4geRZFxIqIgbmKgfW+/7NgzqWFTJXU1NryJAqswqx+9r5Z8mlVcdJWt5+diiwZIXcFvp/Rn3nya6fbGGIWNd8DhAlVydgBRFAYbKsEIESC4sJrvWw9rTTjqZKnXuJ8VUW1c9cfuOE8XDceg/+Q7hb4RacIls3ro1PVBtt8ujfrslY4sRmg1mEgSkdCdyf2tnBnICdxVjijQmWDa4A9s+AsxcADntX0n8No/JZlXKrerDK9LqOn2qI/7RNawlCXQPFb5RtNnvpvI9fEBDaV5uz9ga3cI7lPiCYbik0vKzZ1FLViejQCgUYdEq4ll977ciLZ+mu87YRuClSyBIfy', NULL, 0, 0, '2026-11-07 08:39:18', '2026-11-07 08:29:18', '2026-11-07 08:30:19', '2026-11-07 08:30:19', '2026-11-07 08:29:17'),
(3, 'registration', '0b149ae4b1f8242944aaba8a20e463a137bf692f846ab6d9aa456b4f9a337d8b', 'irakozeeddychristian@gmail.com', 'irakozeeddychristian@gmail.com', NULL, NULL, 'v1:ImpECtltLPFXFjO/4yZ0UXGm9WaOZZFOOGsNGQaRJ6uRoGVWjpSVwmp/PsCdwPU5q65pPTUWxL9m98X1O4u1nKq7+Xl2LGTMmjsB1ke+lheHBlBrXHnzfc6GcEvM5SbJkrw2TiIIgxKpph0ltgvO2a4A91zDNjP6ek2y2dsqOwXF/K0cB307eWaAxtfV3hPHIAFkLKxAZdAqNW+IEqMuDQO7IJ+W5PSRFRT2gCt8oEkSBGCWCy60hQYtuhctWeR9vGGRYe7TzqjgwefmyJk+Yqob+80nhlTQrTGXhFuZ1JE3IHTs0PNSF1VmJWIGsh/0zA9/xgM41qaMiEb3mNXTtNDVX6SSlj0E9OXh8Q7qPh8A/+THukLR8FZS7iVtdYfPYkWNeI+KqfIkH3rS+di844q9scxwKUmDj5OmajZ9v1vGcakqNzyFx95hszgqyiXNN7VVr516yA1qU+62zC1ObrVo7pJz0i1DjleO0Ln1XhlSdrP8rbhR1mdm4SyqvKIqxLCxzAoG8CBESJ6+4DI9AeMAsv+yjJW2OcnMfDidVEv0xJfnaS1VFv7hzao0uNq5lGqYrFnfr1SnLBEWxiFPYGQZ2+60HcrO+axQzNQajcd9nB4kvqshVccBlYMUd6RjyMEfOjjrJsr1pJo2vaKwNaW3RwDro+d6vTmxaPh6Uq7wuVwqCCblo1SCVrVjFMi3KZDXjpj8A0xjLPuZwlhw0fDNB6IxkqDlccseAXzVYDTbLxNK', NULL, 0, 0, '2026-11-08 10:10:54', '2026-11-08 10:00:54', NULL, '2026-11-08 10:03:24', '2026-11-08 10:00:55'),
(4, 'registration', 'a12cb4cb0f9af70ea0fa2b458e9c344a34927225267017848a75bec408f667a4', 'irakozeeddychristian@gmail.com', 'irakozeeddychristian@gmail.com', NULL, NULL, 'v1:aLrw7L7eVlqQJM6LpFJm6aayiFJKEgVv4jWqrzfTsdsakeOhRabSHMlFUkPOd7MIR/Ow+v7YTeNr+7ms8djbi4nUhQQ5eFWc9FQQFMLV9eJ5fWObjkEjOxcqeMTybpWMHt0A51CfCMx3eAJZF3Xdqdgo+a1EnLepj8UrMoodZDYHoVluQeJPIeDsleFKdVzAI1o0SZTUsgb/rP8ForBJb4yyyhghcoQ8LXq5XbIosiO+RLmLI7pb7WxQkyy6XXMn6tYZ5yxPZO9jomusbO5hY/ur7DR6FOBNU3oQauw3uJDnPiJvGyFiqTdBiN3qWP29rhzmtvKd8NmzkYAAGR6dqelv9cs0iBL5fVsZPHl7+cfGhkd8sANWIzetBRkDqcXG7D38y3OU9HOQ4Uf1JdOME/YJROEWE7sD3qh2LLNi40CTwqfAUt+/ckrXSl4T/KmF7HTRtrMZyzYdScIV8B6kNAIuLG0zPXyrAgjeYFH9SvcCR+JwGAQBXKKo76dr5C1ov2ZSheuvgOtm/G2K0E0tlBQ/YuAtT3AO71xI6T45zY2P8DCHpdLYpWuMjQdYj6NS2CQ6nsQrKZYjnfFfRYQBts0yNSxwVWQcXvdxZ6lbv/frySekb7PGVsZRpdZo2tR/mj5Vx24FeSbNWc+EIdS/xSmb+0oqPzNCjwU1+bh7Rr2rmxh19/q1MtcUsGDSkriGZKE7dPq4BOO/3ud5cMVl70mi5gf8SWmBFSiN5N9T6GGzHCyh6P6Ed1JW', NULL, 1, 0, '2026-11-08 10:13:24', '2026-11-08 10:03:24', '2026-11-08 10:04:48', '2026-11-08 10:04:48', '2026-11-08 10:03:25'),
(5, 'password_recovery', 'b854c3c04a47c67113051d0b39322a4b801d39195d1e25a82a44dac40644681a', 'sabanasage@gmail.com', 'sabanasage@gmail.com', NULL, NULL, NULL, '72eabf3b34a4cad6bdc8e2ca83ea95c4c70819db8b9cac2c063ddeb8296f51ea', 0, 0, '2026-11-09 07:50:59', '2026-11-09 07:40:59', NULL, NULL, '2026-11-09 07:41:00'),
(6, 'registration', '4cde99e11b4ba04ca0b65569e1bb218505d8ad56daf80b1c6e65ae7541390550', 'ibipusipusi@gmail.com', 'ibipusipusi@gmail.com', NULL, NULL, 'v1:+ReleWyLMopw3xNY9ABEeIe5F0jRRYPo5gCz8/7BlS3zrnXK4Eaz2FQmkiizVi5G4qqZW409DbK7Zcg/D9xMZTnQJWAZvXsvfs/ZB8ivZ4muSOTuR1rAKzXoJdMlLfvwvU5dL5xNpixvE4eFb/7YRKEiFmaj+Px10TVJOPmpkMtkfjfw1UcoZS/AiNds9/+N09xF2X2DNYczaQNHQyZNjfKN114kDhtrVsk6aLJTQlafYUc44vKHL0PVVAOaSNHyQr8SWAxu2QNIW7PHb0U275qdnPRcr0aD+E4MgcEMsE+3etMWdyRLxuqgBTFWDNwIBCbZk+MFBOPMs7rkIIrEMn92qDCdtzNn/NqAx3b10o4lD16giSv0xezjRlpOaoTftXpGSJZROjarGZ5Ceeq0x1XW7Xvyp7mnBi5hlKMGL50Yw4lECJfvoXfMFhIok1Ksf368qvNvV4jMSbVj/s8TGKM81skNAR+bTJBZgljY7XVC8UypGaO0U8XWW1JaQgnrx1BsAG4DQ6FaRBFb33G8idQI56hdkGewa5LSIyjqOPRSxmRWKU6I/QJCr/zGjvjPdqPELT9M25aSKusGTYfNeRjn14YIou4m6yaIUwZ8xjjpJ11qyI3T2H0sKQMmqZRcrS7iB1ljz/EARh23Ec6lWvWYhe1ioWMLcUhBVJH2nZ6IvkgQpa/Zpe1G1h819Qscm9fquRI=', NULL, 0, 0, '2026-11-09 07:58:39', '2026-11-09 07:48:39', '2026-11-09 07:49:33', '2026-11-09 07:49:33', '2026-11-09 07:48:40'),
(7, 'registration', 'b2e5a84ab61cb14d954cf404e159bab4c755f6031a3ddfc1ecdb577b4dcfa0ca', 'sabanasage@gmail.com', 'sabanasage@gmail.com', NULL, NULL, 'v1:N0XDq7cchP+H04hobAeBR1xMDCRqaapzTDZBvQVq56OZS4Pixn92sz6xLLVWx+77kswFqjeRz8o8mF1AIc6lewNTbAE+RqHL+BrQ5ek4jg7ANxHgiExTxVM2//CXlsJipMYqpOqAm3ap7re6cBZfdTiyOSrIe73mFsp2Or6+NFFKi1cGY/laqJdQEDSo4dSwUumO47uI42VhofNitxd4YmKGMGL/h62mbu4dTatlBtFJtk9vw+G5YIC8KmLv6+jHe/3qZUQ9QNOve3DVWtlM94a+PgF2fQTwk5uLKQOzMMDJhNrKEV5N5SfzByUXnxZ+h1sh/Yc9aWONNjhoPcHZxks4wz3UxBXIlQbpysQVmAX8+faZ3supRl+cxTcZtev2DwFPDrYdhMY5qVkgs9vsGiNeJ3HF4qYFhCpHqtlDy75/M04fh0WfhjdwqRsyeUpQbzm5QOIofAp0RpIp+ljBHzTy+PrpFHwoQqP8n+j2MXZxypcFLAFkfkYkrO54cCiFhkXetouxJ6j55We+AE+GpM27KHTt8UEq0IiZU/LOzzt4kpZW+4WYy6ZzVRrqigY4M7dzIE+5Mx/ImPxOhIqyjq59J9YDnscPfBaDNhV6hvsvA7YGjNvxrM3Is/7n/eqA3QQUapSI94mH5MojHCC1lNqBUI0UnCvOFB0eRcrZkwSd4V/DGPYhr0F40/PlZTrxZewk', NULL, 0, 0, '2026-11-09 08:02:01', '2026-11-09 07:52:01', '2026-11-09 07:52:37', '2026-11-09 07:52:37', '2026-11-09 07:52:01'),
(8, 'registration', 'b5cb5c6dfd4fd544efdc5b2faddb8990f9d210237b62a46bc39ee40052d60609', 'moiseirakiza2@gmail.com', 'moiseirakiza2@gmail.com', NULL, NULL, 'v1:ULFRw0PCB8GwtwV5/qEDy2J/0D6c365T2MH/K4NgfbK/YfjCCyAvCJmtTfvd9TvMdOtQefdH5441WkqhnB/7jyPYbvOzSPmmApWiuIkeMnTQfOBD1iFE1Qq464Aqx9yKL0w+gAWoYbtKpKUoim7foHgbYXup9CQPR4rliUw2du5SwzesvPMoBo1qkkqm9YLsjFM48EQ28pBqHASV9uRF8jl5dSRapJ41m4EMdAkg0EalO5OrSj/0pqrMwFMyfPk8ssVos+dwoHlgYEzcvQ3Rr58zTr5Nk0h8MHavaTpEWJQ2t7m1ZYre5Z90bBqTIQw6ZIwx94KuQ8tgg1p/7rI/iuQiI7s8eePeGhVzKlDvguUy89qcYrbsd9mK4YLXqoQ6h8Od17NoRwehOKAtNdB7MQ4tFtNihpOkIl/Jc2lTND5lrMI43Ccj52UUFOFgEtnHXs/eUrkwCZva6k84qPYxFbrmhyg2k36y7U9hvBqtFvj4JoO6l1Gq4zPCEn6aIIQKwQGqlR5svygRXB//f4RlZleKQLYdnzr8xMeKV4/Dg5JB6nzC+M3GlxoKJjmybU8sOcG4mUYMtfCHKCzBbKhxGlldmqNBQucZ0xd7y07zrR31eW2muqHW1U4AhGXifxlW/BWkfh2WXCtKPebyASFaOiqNiArVxZ7bAViUmpC/uqYANmUc/aKcet/t0PGh9cCBxa75mg==', NULL, 0, 0, '2026-11-09 08:10:48', '2026-11-09 08:00:48', '2026-11-09 08:01:44', '2026-11-09 08:01:44', '2026-11-09 08:00:49'),
(9, 'email_verification', '673d9927419bc1c8125749428ddca3c826f516c4c937c70f8cc87f25b341f101', 'alainjuniornezerwa@gmail.com', 'alainjuniornezerwa@gmail.com', 'voter', 6, NULL, NULL, 0, 0, '2026-11-09 08:51:19', '2026-11-09 08:41:19', NULL, '2026-11-09 08:42:38', '2026-11-09 08:41:18'),
(10, 'email_verification', '7dc572edc60bb8e95a2907668cc38db19226b02274cb3df5d078152ea9915a39', 'alainjuniornezerwa@gmail.com', 'alainjuniornezerwa@gmail.com', 'voter', 6, NULL, NULL, 0, 0, '2026-11-09 08:52:38', '2026-11-09 08:42:38', '2026-11-09 08:43:01', '2026-11-09 08:43:01', '2026-11-09 08:42:38'),
(11, 'email_verification', '5ae8af9180037ae4c4d3725f49fb52e1c5098e0034e4e135833a2f1f2872c45a', 'rwandamuzi@gmail.com', 'rwandamuzi@gmail.com', 'voter', 7, NULL, NULL, 0, 0, '2026-11-11 10:26:07', '2026-11-11 10:16:07', '2026-11-11 10:17:33', '2026-11-11 10:17:33', '2026-11-11 10:16:07');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_type` enum('admin','voter') NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `token_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `voters`
--

CREATE TABLE `voters` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `full_name` varchar(160) NOT NULL,
  `national_id_ciphertext` text NOT NULL,
  `national_id_hmac` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `email` varchar(254) NOT NULL,
  `email_normalized` varchar(254) NOT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `date_of_birth` date NOT NULL,
  `gender` varchar(40) NOT NULL,
  `phone` varchar(40) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `session_version` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `status` enum('unverified','active','suspended','deactivated') NOT NULL DEFAULT 'unverified',
  `is_junior_admin` tinyint(1) NOT NULL DEFAULT 0,
  `promoted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `promoted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `voters`
--

INSERT INTO `voters` (`id`, `full_name`, `national_id_ciphertext`, `national_id_hmac`, `email`, `email_normalized`, `email_verified_at`, `date_of_birth`, `gender`, `phone`, `password_hash`, `session_version`, `status`, `is_junior_admin`, `promoted_by`, `promoted_at`, `created_at`, `updated_at`) VALUES
(2, 'Irakoze Eddy christian', 'v1:eSVvpCDljk+/R5aCC2qDpk0we021D9OxQ+9qYq+2/BKdQNvanw9M/Vp0fZU=', '7337a6c995e3c4ef8407f4596204a89bfc8b3716bf19457bf0e4abc41aa41720', 'irakozeeddychristian@gmail.com', 'irakozeeddychristian@gmail.com', '2026-11-08 10:04:48', '2005-01-10', 'Male', '+250791759876', '$2y$12$dWVe/jtafWJirChkjTN/Eu/HAX.kQo9qHItyLvjYI7VS7VV4lBN7a', 0, 'active', 0, NULL, NULL, '2026-11-08 10:04:48', '2026-11-08 10:04:48'),
(4, 'sabana Sage', 'v1:IB8dI83aWjqklPZsukF147Lo8sWSY+a3XZAwo9msxGmWgixEmxrPgbLAQ8I=', '00ad6e5007e16d9e4210ec8bdf6f1c4d7d2cf9ca8c247ca06c435203dd2a9490', 'sabanasage@gmail.com', 'sabanasage@gmail.com', '2026-11-09 07:52:37', '2000-01-26', 'Male', '0798205718', '$2y$12$U1XLrUPIAz/JE6jXgrPh3.7sZhZaVgZ9IrUAWAOedVRSeUrFlW85u', 4, 'active', 0, NULL, NULL, '2026-11-09 07:52:38', '2026-11-09 00:23:41'),
(5, 'Irakiza Moise', 'v1:XmhDuu7saXOvmcFJoBKQfTYiUt1SCt7SeVJbBnI1Bw3tHKaWAd4=', '4aa622d5f82831dac3964a05200b8e0f38e0377612816f47e618a435b8e0258f', 'moiseirakiza2@gmail.com', 'moiseirakiza2@gmail.com', '2026-11-09 08:01:44', '2000-01-22', 'Female', '0784484940', '$2y$12$hjZoal1C544l.Tql4GTCqe1q0RDkGcx3uBlB8GdNBCQcsxwYf5AlW', 3, 'active', 0, NULL, NULL, '2026-11-09 08:01:44', '2026-11-09 08:38:46'),
(6, 'Alain Junior NEZERWA', 'v1:vLOMlZ3ySJ6/5wuhUGwrNe29GgCeBUU0jadTwwY1AB0rCl0R5nlluUWN9G0=', '204375080837d6866a93be83af268baf6fb7c9061bf8c7f5cb9e9b4cbff46972', 'alainjuniornezerwa@gmail.com', 'alainjuniornezerwa@gmail.com', '2026-11-09 08:43:01', '2005-08-05', 'Male', '0721816013', '$2y$12$9HWrgUFu4TWjDmMRBh1xVOjRUxgCtBxXTpp9IUjg2qD8JTDBjn9vi', 0, 'active', 0, NULL, NULL, '2026-11-09 08:41:18', '2026-11-09 08:43:01'),
(7, 'ndayizeye boris', 'v1:AbgVbWibcGmkGHfwUIoWn1I2TCrahO3lo81B+a/SvTr8Gsy3vcl3JxgOURI=', '17fe33bb7240e81bc47e296b70143dde907920685e11e40cd75a9cd645806d51', 'rwandamuzi@gmail.com', 'rwandamuzi@gmail.com', '2026-11-11 10:17:33', '2005-02-22', 'Male', '0787766789', '$2y$12$k.k8XDnGvRG5fK45McLocO1IVhbGRk4R8iTZ8.68M0VUPCW4/YnGu', 0, 'active', 0, NULL, NULL, '2026-11-11 10:16:07', '2026-11-11 10:17:33');

-- --------------------------------------------------------

--
-- Table structure for table `voter_identifiers`
--

CREATE TABLE `voter_identifiers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `voter_id` bigint(20) UNSIGNED NOT NULL,
  `identifier_type` enum('email','national_id') NOT NULL,
  `identifier_hmac` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `voter_identifiers`
--

INSERT INTO `voter_identifiers` (`id`, `voter_id`, `identifier_type`, `identifier_hmac`, `created_at`) VALUES
(3, 2, 'email', 'facd6ab9f8867b3bc9b82933f7df7efe750ea6172f65a776a5333aff252f93ca', '2026-11-08 10:04:48'),
(4, 2, 'national_id', '7337a6c995e3c4ef8407f4596204a89bfc8b3716bf19457bf0e4abc41aa41720', '2026-11-08 10:04:48'),
(7, 4, 'email', 'a71df7b48aacee67dd1bec8f80f16cff0f4648041d7885939371551bcc61da90', '2026-11-09 07:52:38'),
(8, 4, 'national_id', '00ad6e5007e16d9e4210ec8bdf6f1c4d7d2cf9ca8c247ca06c435203dd2a9490', '2026-11-09 07:52:38'),
(9, 5, 'email', '999b1b19492c554939633fc7fb0fb9f81de474c60fc2c2211f186af77fbd2a17', '2026-11-09 08:01:44'),
(10, 5, 'national_id', '4aa622d5f82831dac3964a05200b8e0f38e0377612816f47e618a435b8e0258f', '2026-11-09 08:01:44'),
(11, 6, 'email', '280d4a50f6022dedfbef97deaf9dce0e56946e8c34a76a7516377012384a9353', '2026-11-09 08:41:18'),
(12, 6, 'national_id', '204375080837d6866a93be83af268baf6fb7c9061bf8c7f5cb9e9b4cbff46972', '2026-11-09 08:41:18'),
(13, 7, 'email', 'b9d5057bddadd0fa929c5f549d30ac78a56a2c1b55c27c9e21b779db83613eb6', '2026-11-11 10:16:07'),
(14, 7, 'national_id', '17fe33bb7240e81bc47e296b70143dde907920685e11e40cd75a9cd645806d51', '2026-11-11 10:16:07');

-- --------------------------------------------------------

--
-- Table structure for table `voter_profile_changes`
--

CREATE TABLE `voter_profile_changes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `voter_id` bigint(20) UNSIGNED NOT NULL,
  `changed_by` bigint(20) UNSIGNED NOT NULL,
  `previous_email` varchar(254) NOT NULL,
  `new_email` varchar(254) NOT NULL,
  `previous_national_id_ciphertext` text NOT NULL,
  `new_national_id_ciphertext` text NOT NULL,
  `previous_profile` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL
) ;

-- --------------------------------------------------------

--
-- Table structure for table `votes`
--

CREATE TABLE `votes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `voter_id` bigint(20) UNSIGNED NOT NULL,
  `election_id` bigint(20) UNSIGNED NOT NULL,
  `candidate_id` bigint(20) UNSIGNED NOT NULL,
  `cast_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `votes`
--

INSERT INTO `votes` (`id`, `voter_id`, `election_id`, `candidate_id`, `cast_at`) VALUES
(1, 4, 2, 2, '2026-11-09 08:51:36'),
(2, 6, 2, 2, '2026-11-09 08:51:51'),
(3, 5, 2, 3, '2026-11-09 08:52:50');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_admin_singleton` (`singleton_key`),
  ADD UNIQUE KEY `uq_admin_email` (`email_normalized`);

--
-- Indexes for table `auth_rate_limits`
--
ALTER TABLE `auth_rate_limits`
  ADD PRIMARY KEY (`limiter_hash`),
  ADD KEY `ix_rate_limit_blocked` (`blocked_until`);

--
-- Indexes for table `candidates`
--
ALTER TABLE `candidates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_candidate_election` (`id`,`election_id`),
  ADD KEY `ix_candidate_election` (`election_id`,`full_name`);

--
-- Indexes for table `elections`
--
ALTER TABLE `elections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ix_election_schedule` (`status`,`starts_at`,`ends_at`),
  ADD KEY `fk_election_admin` (`created_by`);

--
-- Indexes for table `email_notifications`
--
ALTER TABLE `email_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ix_notification_type_date` (`notification_type`,`created_at`);

--
-- Indexes for table `otp_challenges`
--
ALTER TABLE `otp_challenges`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_otp_flow_hash` (`flow_hash`),
  ADD KEY `ix_otp_email_purpose` (`email_normalized`,`purpose`,`created_at`),
  ADD KEY `ix_otp_expiry` (`expires_at`,`consumed_at`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_password_reset_hash` (`token_hash`),
  ADD KEY `ix_password_reset_account` (`account_type`,`account_id`,`expires_at`);

--
-- Indexes for table `voters`
--
ALTER TABLE `voters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_voter_national_id_hmac` (`national_id_hmac`),
  ADD UNIQUE KEY `uq_voter_email` (`email_normalized`),
  ADD KEY `ix_voters_status` (`status`,`created_at`),
  ADD KEY `ix_voters_junior` (`is_junior_admin`),
  ADD KEY `fk_voters_promoted_by` (`promoted_by`);

--
-- Indexes for table `voter_identifiers`
--
ALTER TABLE `voter_identifiers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_identifier_owner` (`identifier_type`,`identifier_hmac`),
  ADD KEY `ix_identifier_voter` (`voter_id`);

--
-- Indexes for table `votes`
--
ALTER TABLE `votes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_one_vote_per_election` (`voter_id`,`election_id`),
  ADD KEY `ix_votes_election_candidate` (`election_id`,`candidate_id`),
  ADD KEY `fk_vote_candidate_election` (`candidate_id`,`election_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `candidates`
--
ALTER TABLE `candidates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `elections`
--
ALTER TABLE `elections`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_notifications`
--
ALTER TABLE `email_notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `otp_challenges`
--
ALTER TABLE `otp_challenges`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `voters`
--
ALTER TABLE `voters`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `voter_identifiers`
--
ALTER TABLE `voter_identifiers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `voter_profile_changes`
--
ALTER TABLE `voter_profile_changes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `votes`
--
ALTER TABLE `votes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `candidates`
--
ALTER TABLE `candidates`
  ADD CONSTRAINT `fk_candidate_election` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`);

--
-- Constraints for table `elections`
--
ALTER TABLE `elections`
  ADD CONSTRAINT `fk_election_admin` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`);

--
-- Constraints for table `voters`
--
ALTER TABLE `voters`
  ADD CONSTRAINT `fk_voters_promoted_by` FOREIGN KEY (`promoted_by`) REFERENCES `admins` (`id`);

--
-- Constraints for table `voter_identifiers`
--
ALTER TABLE `voter_identifiers`
  ADD CONSTRAINT `fk_identifier_voter` FOREIGN KEY (`voter_id`) REFERENCES `voters` (`id`);

--
-- Constraints for table `votes`
--
ALTER TABLE `votes`
  ADD CONSTRAINT `fk_vote_candidate_election` FOREIGN KEY (`candidate_id`,`election_id`) REFERENCES `candidates` (`id`, `election_id`),
  ADD CONSTRAINT `fk_vote_election` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`),
  ADD CONSTRAINT `fk_vote_voter` FOREIGN KEY (`voter_id`) REFERENCES `voters` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
