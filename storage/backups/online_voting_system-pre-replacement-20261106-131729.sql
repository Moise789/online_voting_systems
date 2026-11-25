-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: online_voting_system
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `online_voting_system`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `online_voting_system` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `online_voting_system`;

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `actor_type` enum('admin','voter','system') NOT NULL,
  `actor_id` bigint(20) unsigned DEFAULT NULL,
  `event_type` varchar(80) NOT NULL,
  `target_type` varchar(80) DEFAULT NULL,
  `target_id` bigint(20) unsigned DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip_address` varbinary(16) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_activity_created` (`created_at`),
  KEY `ix_activity_actor` (`actor_type`,`actor_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admins` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `singleton_key` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `full_name` varchar(160) NOT NULL,
  `email` varchar(254) NOT NULL,
  `email_normalized` varchar(254) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `session_version` bigint(20) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_singleton` (`singleton_key`),
  UNIQUE KEY `uq_admin_email` (`email_normalized`),
  CONSTRAINT `chk_admin_singleton` CHECK (`singleton_key` = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admins`
--

LOCK TABLES `admins` WRITE;
/*!40000 ALTER TABLE `admins` DISABLE KEYS */;
/*!40000 ALTER TABLE `admins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `application_edit_tokens`
--

DROP TABLE IF EXISTS `application_edit_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `application_edit_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `voter_id` bigint(20) unsigned NOT NULL,
  `token_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_application_token_hash` (`token_hash`),
  KEY `ix_application_token_voter` (`voter_id`,`expires_at`),
  CONSTRAINT `fk_application_token_voter` FOREIGN KEY (`voter_id`) REFERENCES `voters` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `application_edit_tokens`
--

LOCK TABLES `application_edit_tokens` WRITE;
/*!40000 ALTER TABLE `application_edit_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `application_edit_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `auth_rate_limits`
--

DROP TABLE IF EXISTS `auth_rate_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auth_rate_limits` (
  `limiter_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `attempts` smallint(5) unsigned NOT NULL DEFAULT 0,
  `window_started_at` datetime NOT NULL,
  `blocked_until` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`limiter_hash`),
  KEY `ix_rate_limit_blocked` (`blocked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auth_rate_limits`
--

LOCK TABLES `auth_rate_limits` WRITE;
/*!40000 ALTER TABLE `auth_rate_limits` DISABLE KEYS */;
/*!40000 ALTER TABLE `auth_rate_limits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `candidates`
--

DROP TABLE IF EXISTS `candidates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `candidates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `election_id` bigint(20) unsigned NOT NULL,
  `full_name` varchar(160) NOT NULL,
  `party` varchar(160) NOT NULL DEFAULT '',
  `biography` text NOT NULL,
  `photo_path` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_candidate_election` (`id`,`election_id`),
  KEY `ix_candidate_election` (`election_id`,`full_name`),
  CONSTRAINT `fk_candidate_election` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `candidates`
--

LOCK TABLES `candidates` WRITE;
/*!40000 ALTER TABLE `candidates` DISABLE KEYS */;
/*!40000 ALTER TABLE `candidates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `elections`
--

DROP TABLE IF EXISTS `elections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `elections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(180) NOT NULL,
  `description` text NOT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `status` enum('draft','scheduled','active','closed') NOT NULL DEFAULT 'draft',
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `closed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_election_schedule` (`status`,`starts_at`,`ends_at`),
  KEY `fk_election_admin` (`created_by`),
  CONSTRAINT `fk_election_admin` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`),
  CONSTRAINT `chk_election_dates` CHECK (`ends_at` > `starts_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `elections`
--

LOCK TABLES `elections` WRITE;
/*!40000 ALTER TABLE `elections` DISABLE KEYS */;
/*!40000 ALTER TABLE `elections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_notifications`
--

DROP TABLE IF EXISTS `email_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `recipient_email` varchar(254) NOT NULL,
  `notification_type` varchar(80) NOT NULL,
  `status` enum('sent','failed') NOT NULL,
  `error_message` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_notification_type_date` (`notification_type`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_notifications`
--

LOCK TABLES `email_notifications` WRITE;
/*!40000 ALTER TABLE `email_notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `email_notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `otp_challenges`
--

DROP TABLE IF EXISTS `otp_challenges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `otp_challenges` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `purpose` enum('registration','password_recovery','email_verification') NOT NULL,
  `flow_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `email` varchar(254) NOT NULL,
  `email_normalized` varchar(254) NOT NULL,
  `account_type` enum('admin','voter') DEFAULT NULL,
  `account_id` bigint(20) unsigned DEFAULT NULL,
  `payload` mediumtext DEFAULT NULL,
  `code_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `resend_count` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `last_sent_at` datetime NOT NULL,
  `verified_at` datetime DEFAULT NULL,
  `consumed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_otp_flow_hash` (`flow_hash`),
  KEY `ix_otp_email_purpose` (`email_normalized`,`purpose`,`created_at`),
  KEY `ix_otp_expiry` (`expires_at`,`consumed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `otp_challenges`
--

LOCK TABLES `otp_challenges` WRITE;
/*!40000 ALTER TABLE `otp_challenges` DISABLE KEYS */;
/*!40000 ALTER TABLE `otp_challenges` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `account_type` enum('admin','voter') NOT NULL,
  `account_id` bigint(20) unsigned NOT NULL,
  `token_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_password_reset_hash` (`token_hash`),
  KEY `ix_password_reset_account` (`account_type`,`account_id`,`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `voter_application_reviews`
--

DROP TABLE IF EXISTS `voter_application_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `voter_application_reviews` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `voter_id` bigint(20) unsigned NOT NULL,
  `submission_id` bigint(20) unsigned NOT NULL,
  `action` enum('submitted','resubmitted','approved','rejected') NOT NULL,
  `reviewer_admin_id` bigint(20) unsigned DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_review_voter_date` (`voter_id`,`created_at`),
  KEY `fk_review_submission` (`submission_id`),
  KEY `fk_review_admin` (`reviewer_admin_id`),
  CONSTRAINT `fk_review_admin` FOREIGN KEY (`reviewer_admin_id`) REFERENCES `admins` (`id`),
  CONSTRAINT `fk_review_submission` FOREIGN KEY (`submission_id`) REFERENCES `voter_application_submissions` (`id`),
  CONSTRAINT `fk_review_voter` FOREIGN KEY (`voter_id`) REFERENCES `voters` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `voter_application_reviews`
--

LOCK TABLES `voter_application_reviews` WRITE;
/*!40000 ALTER TABLE `voter_application_reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `voter_application_reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `voter_application_submissions`
--

DROP TABLE IF EXISTS `voter_application_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `voter_application_submissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `voter_id` bigint(20) unsigned NOT NULL,
  `revision` int(10) unsigned NOT NULL,
  `full_name` varchar(160) NOT NULL,
  `national_id_ciphertext` text NOT NULL,
  `national_id_hmac` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `email` varchar(254) NOT NULL,
  `email_normalized` varchar(254) NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender` varchar(40) NOT NULL,
  `phone` varchar(40) NOT NULL,
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_application_revision` (`voter_id`,`revision`),
  KEY `ix_submission_hmac` (`national_id_hmac`),
  CONSTRAINT `fk_submission_voter` FOREIGN KEY (`voter_id`) REFERENCES `voters` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `voter_application_submissions`
--

LOCK TABLES `voter_application_submissions` WRITE;
/*!40000 ALTER TABLE `voter_application_submissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `voter_application_submissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `voter_identifiers`
--

DROP TABLE IF EXISTS `voter_identifiers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `voter_identifiers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `voter_id` bigint(20) unsigned NOT NULL,
  `identifier_type` enum('email','national_id') NOT NULL,
  `identifier_hmac` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_identifier_owner` (`identifier_type`,`identifier_hmac`),
  KEY `ix_identifier_voter` (`voter_id`),
  CONSTRAINT `fk_identifier_voter` FOREIGN KEY (`voter_id`) REFERENCES `voters` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `voter_identifiers`
--

LOCK TABLES `voter_identifiers` WRITE;
/*!40000 ALTER TABLE `voter_identifiers` DISABLE KEYS */;
/*!40000 ALTER TABLE `voter_identifiers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `voter_profile_changes`
--

DROP TABLE IF EXISTS `voter_profile_changes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `voter_profile_changes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `voter_id` bigint(20) unsigned NOT NULL,
  `changed_by` bigint(20) unsigned NOT NULL,
  `previous_email` varchar(254) NOT NULL,
  `new_email` varchar(254) NOT NULL,
  `previous_national_id_ciphertext` text NOT NULL,
  `new_national_id_ciphertext` text NOT NULL,
  `previous_profile` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`previous_profile`)),
  `new_profile` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`new_profile`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_profile_change_voter` (`voter_id`,`created_at`),
  KEY `fk_profile_change_admin` (`changed_by`),
  CONSTRAINT `fk_profile_change_admin` FOREIGN KEY (`changed_by`) REFERENCES `admins` (`id`),
  CONSTRAINT `fk_profile_change_voter` FOREIGN KEY (`voter_id`) REFERENCES `voters` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `voter_profile_changes`
--

LOCK TABLES `voter_profile_changes` WRITE;
/*!40000 ALTER TABLE `voter_profile_changes` DISABLE KEYS */;
/*!40000 ALTER TABLE `voter_profile_changes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `voters`
--

DROP TABLE IF EXISTS `voters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `voters` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
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
  `session_version` bigint(20) unsigned NOT NULL DEFAULT 0,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `is_junior_admin` tinyint(1) NOT NULL DEFAULT 0,
  `promoted_by` bigint(20) unsigned DEFAULT NULL,
  `promoted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_voter_national_id_hmac` (`national_id_hmac`),
  UNIQUE KEY `uq_voter_email` (`email_normalized`),
  KEY `ix_voters_status` (`status`,`created_at`),
  KEY `ix_voters_junior` (`is_junior_admin`),
  KEY `fk_voters_promoted_by` (`promoted_by`),
  CONSTRAINT `fk_voters_promoted_by` FOREIGN KEY (`promoted_by`) REFERENCES `admins` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `voters`
--

LOCK TABLES `voters` WRITE;
/*!40000 ALTER TABLE `voters` DISABLE KEYS */;
/*!40000 ALTER TABLE `voters` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `votes`
--

DROP TABLE IF EXISTS `votes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `votes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `voter_id` bigint(20) unsigned NOT NULL,
  `election_id` bigint(20) unsigned NOT NULL,
  `candidate_id` bigint(20) unsigned NOT NULL,
  `cast_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_one_vote_per_election` (`voter_id`,`election_id`),
  KEY `ix_votes_election_candidate` (`election_id`,`candidate_id`),
  KEY `fk_vote_candidate_election` (`candidate_id`,`election_id`),
  CONSTRAINT `fk_vote_candidate_election` FOREIGN KEY (`candidate_id`, `election_id`) REFERENCES `candidates` (`id`, `election_id`),
  CONSTRAINT `fk_vote_election` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`),
  CONSTRAINT `fk_vote_voter` FOREIGN KEY (`voter_id`) REFERENCES `voters` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `votes`
--

LOCK TABLES `votes` WRITE;
/*!40000 ALTER TABLE `votes` DISABLE KEYS */;
/*!40000 ALTER TABLE `votes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'online_voting_system'
--

--
-- Dumping routines for database 'online_voting_system'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-06 13:17:31
