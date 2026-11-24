-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: water_billing_systems
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
-- Table structure for table `announcement_deliveries`
--

DROP TABLE IF EXISTS `announcement_deliveries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `announcement_deliveries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `announcement_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Queued',
  `attempts` smallint(5) unsigned NOT NULL DEFAULT 0,
  `queued_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `last_error` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `announcement_deliveries_announcement_id_recipient_email_unique` (`announcement_id`,`recipient_email`),
  KEY `announcement_deliveries_user_id_foreign` (`user_id`),
  KEY `announcement_deliveries_status_queued_at_index` (`status`,`queued_at`),
  CONSTRAINT `announcement_deliveries_announcement_id_foreign` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`id`) ON DELETE CASCADE,
  CONSTRAINT `announcement_deliveries_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `announcement_deliveries`
--

LOCK TABLES `announcement_deliveries` WRITE;
/*!40000 ALTER TABLE `announcement_deliveries` DISABLE KEYS */;
INSERT INTO `announcement_deliveries` VALUES (1,1,1,'admin@example.com','Cancelled',0,'2026-11-07 12:44:14',NULL,'Announcement was deleted.','2026-11-07 12:44:14','2026-11-07 12:52:59');
INSERT INTO `announcement_deliveries` VALUES (2,1,3,'reader@example.com','Cancelled',0,'2026-11-07 12:44:14',NULL,'Announcement was deleted.','2026-11-07 12:44:14','2026-11-07 12:52:59');
/*!40000 ALTER TABLE `announcement_deliveries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `announcement_recipients`
--

DROP TABLE IF EXISTS `announcement_recipients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `announcement_recipients` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `announcement_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `announcement_recipients_announcement_id_user_id_unique` (`announcement_id`,`user_id`),
  KEY `announcement_recipients_user_id_foreign` (`user_id`),
  CONSTRAINT `announcement_recipients_announcement_id_foreign` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`id`) ON DELETE CASCADE,
  CONSTRAINT `announcement_recipients_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `announcement_recipients`
--

LOCK TABLES `announcement_recipients` WRITE;
/*!40000 ALTER TABLE `announcement_recipients` DISABLE KEYS */;
/*!40000 ALTER TABLE `announcement_recipients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `announcements`
--

DROP TABLE IF EXISTS `announcements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `announcements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `category` varchar(60) NOT NULL,
  `audience` varchar(30) NOT NULL DEFAULT 'Everyone',
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `recipient_user_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`recipient_user_ids`)),
  `photo_path` varchar(255) DEFAULT NULL,
  `priority` varchar(20) NOT NULL DEFAULT 'Normal',
  `expires_at` timestamp NULL DEFAULT NULL,
  `affected_area` varchar(255) DEFAULT NULL,
  `service_starts_at` timestamp NULL DEFAULT NULL,
  `service_restores_at` timestamp NULL DEFAULT NULL,
  `notifications_queued_at` timestamp NULL DEFAULT NULL,
  `edited_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `announcements_user_id_foreign` (`user_id`),
  KEY `announcements_deleted_by_foreign` (`deleted_by`),
  CONSTRAINT `announcements_deleted_by_foreign` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `announcements_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `announcements`
--

LOCK TABLES `announcements` WRITE;
/*!40000 ALTER TABLE `announcements` DISABLE KEYS */;
INSERT INTO `announcements` VALUES (1,1,'WATER INTERRUPTION','There is Water Interruption!','Service Advisory','Everyone',1,'2026-11-07 12:42:00','2026-11-07 12:44:14','2026-11-07 12:52:59',NULL,'announcements/7hQwj1IVUgXG0xBUOLBvHIMna49xN2mHAUk7Ictt.png','Urgent',NULL,'block 3','2026-11-07 12:43:00','2026-11-07 13:43:00','2026-11-07 12:44:14',NULL,1,'2026-11-07 12:52:59');
/*!40000 ALTER TABLE `announcements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bills`
--

DROP TABLE IF EXISTS `bills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bills` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `consumer_id` bigint(20) unsigned NOT NULL,
  `bill_type` varchar(255) NOT NULL DEFAULT 'Water',
  `meter_reading_id` bigint(20) unsigned DEFAULT NULL,
  `loan_id` bigint(20) unsigned DEFAULT NULL,
  `billing_period` date NOT NULL,
  `due_date` date NOT NULL,
  `prn` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `base_charge` decimal(10,2) DEFAULT NULL,
  `water_charge` decimal(10,2) DEFAULT NULL,
  `arrears_amount` decimal(10,2) DEFAULT NULL,
  `carried_forward_from_bill_id` bigint(20) unsigned DEFAULT NULL,
  `carried_forward_to_bill_id` bigint(20) unsigned DEFAULT NULL,
  `penalty_amount` decimal(10,2) DEFAULT NULL,
  `loan_installment_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `units_consumed` decimal(10,2) DEFAULT NULL,
  `unit_rate` decimal(10,2) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'Unpaid',
  `months_unpaid` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `notice_level` varchar(255) DEFAULT NULL,
  `request_type` varchar(255) DEFAULT NULL,
  `request_status` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `waived_reconnection_fee_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `carried_balance_snapshot` decimal(10,2) DEFAULT NULL,
  `carried_paid_snapshot` decimal(10,2) DEFAULT NULL,
  `is_demo` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bills_prn_unique` (`prn`),
  UNIQUE KEY `bills_meter_reading_id_unique` (`meter_reading_id`),
  KEY `bills_consumer_id_foreign` (`consumer_id`),
  KEY `bills_loan_id_foreign` (`loan_id`),
  KEY `bills_carried_forward_from_bill_id_foreign` (`carried_forward_from_bill_id`),
  KEY `bills_carried_forward_to_bill_id_foreign` (`carried_forward_to_bill_id`),
  KEY `bills_reporting_period_status_index` (`billing_period`,`status`),
  CONSTRAINT `bills_carried_forward_from_bill_id_foreign` FOREIGN KEY (`carried_forward_from_bill_id`) REFERENCES `bills` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bills_carried_forward_to_bill_id_foreign` FOREIGN KEY (`carried_forward_to_bill_id`) REFERENCES `bills` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bills_consumer_id_foreign` FOREIGN KEY (`consumer_id`) REFERENCES `consumers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bills_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bills_meter_reading_id_foreign` FOREIGN KEY (`meter_reading_id`) REFERENCES `meter_readings` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=118 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bills`
--

LOCK TABLES `bills` WRITE;
/*!40000 ALTER TABLE `bills` DISABLE KEYS */;
INSERT INTO `bills` VALUES (1,76,'Water',1,NULL,'2026-11-01','2026-12-02','PRN-2611-0001',180.00,0.00,180.00,0.00,NULL,NULL,0.00,0.00,10.00,18.00,'Paid',0,NULL,NULL,NULL,'2026-11-10 21:39:47','2026-11-16 22:54:12',0.00,NULL,NULL,0);
INSERT INTO `bills` VALUES (5,80,'Water',5,NULL,'2026-11-01','2026-12-02','PRN-2611-0005',270.00,0.00,270.00,0.00,NULL,NULL,0.00,0.00,15.00,18.00,'Paid',0,NULL,NULL,NULL,'2026-11-17 01:18:54','2026-11-17 01:30:02',0.00,NULL,NULL,0);
INSERT INTO `bills` VALUES (27,75,'Water',NULL,NULL,'2026-08-01','2026-08-25','DEMO-DISC-MT-8989-202608',294.00,150.00,144.00,0.00,NULL,28,0.00,0.00,8.00,18.00,'Paid',0,NULL,NULL,NULL,'2026-11-18 00:06:06','2026-11-18 00:06:06',0.00,294.00,0.00,0);
INSERT INTO `bills` VALUES (28,75,'Water',NULL,NULL,'2026-09-01','2026-09-25','DEMO-DISC-MT-8989-202609',624.00,150.00,180.00,294.00,27,29,0.00,0.00,10.00,18.00,'Paid',0,NULL,NULL,NULL,'2026-11-18 00:06:06','2026-11-18 00:06:06',0.00,624.00,0.00,0);
INSERT INTO `bills` VALUES (29,75,'Water',NULL,NULL,'2026-10-01','2026-10-25','DEMO-DISC-MT-8989-202610',936.00,150.00,162.00,624.00,28,NULL,0.00,0.00,9.00,18.00,'Overdue',3,NULL,NULL,NULL,'2026-11-18 00:06:06','2026-11-18 01:27:29',0.00,NULL,NULL,0);
INSERT INTO `bills` VALUES (30,76,'Water',NULL,NULL,'2026-08-01','2026-08-25','DEMO-DISC-MT02-202608',312.00,150.00,162.00,0.00,NULL,31,0.00,0.00,9.00,18.00,'Paid',0,NULL,NULL,NULL,'2026-11-18 00:06:06','2026-11-18 00:06:06',0.00,312.00,0.00,0);
INSERT INTO `bills` VALUES (31,76,'Water',NULL,NULL,'2026-09-01','2026-09-25','DEMO-DISC-MT02-202609',660.00,150.00,198.00,312.00,30,32,0.00,0.00,11.00,18.00,'Paid',0,NULL,NULL,NULL,'2026-11-18 00:06:06','2026-11-18 00:06:06',0.00,660.00,0.00,0);
INSERT INTO `bills` VALUES (32,76,'Water',NULL,NULL,'2026-10-01','2026-10-25','DEMO-DISC-MT02-202610',990.00,150.00,180.00,660.00,31,NULL,0.00,0.00,10.00,18.00,'Paid',0,NULL,NULL,NULL,'2026-11-18 00:06:06','2026-11-22 06:24:08',0.00,NULL,NULL,0);
INSERT INTO `bills` VALUES (116,88,'Water',50,NULL,'2026-11-01','2026-12-08','PRN-2611-0050',180.00,0.00,180.00,0.00,NULL,NULL,0.00,0.00,10.00,18.00,'Unpaid',0,NULL,NULL,NULL,'2026-11-23 03:41:32','2026-11-23 03:41:32',0.00,NULL,NULL,0);
INSERT INTO `bills` VALUES (117,87,'Water',51,NULL,'2026-11-01','2026-12-08','PRN-2611-0051',270.00,0.00,270.00,0.00,NULL,NULL,0.00,0.00,15.00,18.00,'Unpaid',0,NULL,NULL,NULL,'2026-11-23 03:41:32','2026-11-23 03:41:32',0.00,NULL,NULL,0);
/*!40000 ALTER TABLE `bills` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `consumer_email_verifications`
--

DROP TABLE IF EXISTS `consumer_email_verifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `consumer_email_verifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `consumer_id` bigint(20) unsigned NOT NULL,
  `email` varchar(255) NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `consumer_email_verifications_consumer_id_unique` (`consumer_id`),
  CONSTRAINT `consumer_email_verifications_consumer_id_foreign` FOREIGN KEY (`consumer_id`) REFERENCES `consumers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `consumer_email_verifications`
--

LOCK TABLES `consumer_email_verifications` WRITE;
/*!40000 ALTER TABLE `consumer_email_verifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `consumer_email_verifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `consumer_notification_logs`
--

DROP TABLE IF EXISTS `consumer_notification_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `consumer_notification_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `consumer_id` bigint(20) unsigned DEFAULT NULL,
  `channel` varchar(255) NOT NULL DEFAULT 'email',
  `event` varchar(255) NOT NULL,
  `recipient` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'sent',
  `error_message` text DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `consumer_notification_logs_consumer_event_index` (`consumer_id`,`event`),
  CONSTRAINT `consumer_notification_logs_consumer_id_foreign` FOREIGN KEY (`consumer_id`) REFERENCES `consumers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `consumer_notification_logs`
--

LOCK TABLES `consumer_notification_logs` WRITE;
/*!40000 ALTER TABLE `consumer_notification_logs` DISABLE KEYS */;
INSERT INTO `consumer_notification_logs` VALUES (3,76,'email','connection_approved','bonghanoylovely9@gmail.com','sent',NULL,'2026-11-10 21:32:31','2026-11-10 21:32:31','2026-11-10 21:32:31');
INSERT INTO `consumer_notification_logs` VALUES (4,76,'email','installation_reschedule_1_assigned','bonghanoylovely9@gmail.com','sent',NULL,'2026-11-10 21:35:09','2026-11-10 21:35:09','2026-11-10 21:35:09');
INSERT INTO `consumer_notification_logs` VALUES (8,76,'email','credentials_resent','bonghanoylovely9@gmail.com','sent',NULL,'2026-11-17 20:27:22','2026-11-16 21:38:32','2026-11-17 20:27:22');
INSERT INTO `consumer_notification_logs` VALUES (16,80,'email','connection_approved','bonghanoylovely9@gmail.com','sent',NULL,'2026-11-17 01:13:27','2026-11-17 01:13:27','2026-11-17 01:13:27');
INSERT INTO `consumer_notification_logs` VALUES (21,80,'email','loan_installation_4_initial','bonghanoylovely9@gmail.com','sent',NULL,'2026-11-17 20:16:14','2026-11-17 20:16:14','2026-11-17 20:16:14');
INSERT INTO `consumer_notification_logs` VALUES (22,80,'email','installation_reschedule_5_assigned','bonghanoylovely9@gmail.com','sent',NULL,'2026-11-17 20:18:07','2026-11-17 20:18:07','2026-11-17 20:18:07');
INSERT INTO `consumer_notification_logs` VALUES (23,76,'email','password_temporary','bonghanoylovely9@gmail.com','sent',NULL,'2026-11-23 07:56:33','2026-11-17 20:24:25','2026-11-23 07:56:33');
INSERT INTO `consumer_notification_logs` VALUES (28,75,'email','connection_approved','leonelmartinez0953@gmail.com','failed','Set APP_PUBLIC_URL to the public application URL before sending consumer account emails.',NULL,'2026-11-17 21:56:21','2026-11-17 21:56:21');
INSERT INTO `consumer_notification_logs` VALUES (31,80,'email','password_temporary','bonghanoylovely9@gmail.com','sent',NULL,'2026-11-20 19:51:06','2026-11-17 23:10:51','2026-11-20 19:51:06');
INSERT INTO `consumer_notification_logs` VALUES (33,87,'email','connection_approved','bonghanoylovely9@gmail.com','sent',NULL,'2026-11-18 00:41:16','2026-11-18 00:41:16','2026-11-18 00:41:16');
INSERT INTO `consumer_notification_logs` VALUES (35,88,'email','connection_approved','bonghanoylovely9@gmail.com','sent',NULL,'2026-11-18 01:23:30','2026-11-18 01:23:30','2026-11-18 01:23:30');
INSERT INTO `consumer_notification_logs` VALUES (38,80,'email','credentials_resent','bonghanoylovely9@gmail.com','sent',NULL,'2026-11-20 19:50:16','2026-11-20 19:50:16','2026-11-20 19:50:16');
INSERT INTO `consumer_notification_logs` VALUES (39,88,'email','password_temporary','bonghanoylovely9@gmail.com','sent',NULL,'2026-11-23 08:02:57','2026-11-20 19:53:04','2026-11-23 08:02:57');
INSERT INTO `consumer_notification_logs` VALUES (40,88,'email','loan_installation_10_initial','bonghanoylovely9@gmail.com','sent',NULL,'2026-11-20 19:55:58','2026-11-20 19:55:58','2026-11-20 19:55:58');
INSERT INTO `consumer_notification_logs` VALUES (43,76,'email','loan_installation_1_initial','bonghanoylovely9@gmail.com','sent',NULL,'2026-11-20 20:13:43','2026-11-20 20:13:37','2026-11-20 20:13:43');
INSERT INTO `consumer_notification_logs` VALUES (44,88,'email','credentials_resent','bonghanoylovely9@gmail.com','sent',NULL,'2026-11-20 20:21:06','2026-11-20 20:21:06','2026-11-20 20:21:06');
INSERT INTO `consumer_notification_logs` VALUES (51,76,'email','payment_verified_20261122142408','bonghanoylovely9@gmail.com','sent',NULL,'2026-11-22 06:24:14','2026-11-22 06:24:14','2026-11-22 06:24:14');
INSERT INTO `consumer_notification_logs` VALUES (52,76,'email','consumer_reconnected_5','bonghanoylovely9@gmail.com','sent',NULL,'2026-11-22 06:31:48','2026-11-22 06:31:42','2026-11-22 06:31:48');
INSERT INTO `consumer_notification_logs` VALUES (54,76,'email','loan_overdue_disconnection_14','loan-overdue-qa@example.test','sent',NULL,'2026-11-23 00:14:44','2026-11-23 00:14:44','2026-11-23 00:14:44');
INSERT INTO `consumer_notification_logs` VALUES (68,109,'email','connection_approved','bonghanoylovely9@gmail.com','sent',NULL,'2026-11-23 08:10:03','2026-11-23 08:10:03','2026-11-23 08:10:03');
INSERT INTO `consumer_notification_logs` VALUES (69,109,'email','loan_installation_21_initial','bonghanoylovely9@gmail.com','sent',NULL,'2026-11-23 08:13:22','2026-11-23 08:13:16','2026-11-23 08:13:22');
INSERT INTO `consumer_notification_logs` VALUES (73,109,'email','password_temporary','bonghanoylovely9@gmail.com','sent',NULL,'2026-11-23 19:36:15','2026-11-23 19:36:15','2026-11-23 19:36:15');
/*!40000 ALTER TABLE `consumer_notification_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `consumer_password_setups`
--

DROP TABLE IF EXISTS `consumer_password_setups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `consumer_password_setups` (
  `user_id` bigint(20) unsigned NOT NULL,
  `consumer_id` bigint(20) unsigned NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  `temporary_password_hash` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  KEY `consumer_password_setups_consumer_id_foreign` (`consumer_id`),
  CONSTRAINT `consumer_password_setups_consumer_id_foreign` FOREIGN KEY (`consumer_id`) REFERENCES `consumers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `consumer_password_setups_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `consumer_password_setups`
--

LOCK TABLES `consumer_password_setups` WRITE;
/*!40000 ALTER TABLE `consumer_password_setups` DISABLE KEYS */;
INSERT INTO `consumer_password_setups` VALUES (40,76,'16cc1567f909fd46bd9c479ac17d918d981547c4b1776b5aedbc3c9d53b1b3c4','bonghanoylovely9@gmail.com','2026-11-23 16:56:27','2026-11-23 15:56:27','$2y$12$lfEbwoZXxfq3jyxNYPJMHO9vOs3clnLKuGlhJns27oFwUcM6koSFG');
INSERT INTO `consumer_password_setups` VALUES (43,80,'dc5f5a08b5fc3176f6cef8115dc3220caf588fee2fd1d18d2986ada5b93aa772','bonghanoylovely9@gmail.com','2026-11-21 04:51:00','2026-11-21 03:51:00','$2y$12$MCBK7wWjjzaq/wHcgOE1je8FklX3GJPr4/WkKoEo1Jv5TkieG26RO');
INSERT INTO `consumer_password_setups` VALUES (51,87,'207fbbcdf8cc45955482dd570028dc83259c90c9991bbf089aefd55b61230451','bonghanoylovely9@gmail.com','2026-11-18 09:41:10','2026-11-18 08:41:10',NULL);
INSERT INTO `consumer_password_setups` VALUES (52,88,'d4812894f6a36926833d359cb20717a1fed972dbc8962f76d29a261ad7b3c68a','bonghanoylovely9@gmail.com','2026-11-23 17:02:51','2026-11-23 16:02:51','$2y$12$I8ZHvwr4zj7UAQLGTOxlheteW0c2bbaviTzBij1GalM7oPyPhriX2');
INSERT INTO `consumer_password_setups` VALUES (56,109,'28f2ecaa9b4b837dda6796570cb2edef5d2ae6f34510fb29e53cc4e1eeeabf1f','bonghanoylovely9@gmail.com','2026-11-24 04:36:10','2026-11-24 03:36:10','$2y$12$eeddA/9Y8MV5yIe.HX7ucO4QfqN3ExXin2CIPG.A7Du7xSQaDtZ4C');
/*!40000 ALTER TABLE `consumer_password_setups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `consumer_reconnections`
--

DROP TABLE IF EXISTS `consumer_reconnections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `consumer_reconnections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `consumer_id` bigint(20) unsigned NOT NULL,
  `confirmed_by` bigint(20) unsigned NOT NULL,
  `disconnected_at` datetime DEFAULT NULL,
  `reconnection_date` date NOT NULL,
  `installation_scheduled_at` datetime NOT NULL,
  `activated_at` datetime NOT NULL,
  `reconnection_fee` decimal(10,2) NOT NULL,
  `verified_payment_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`verified_payment_ids`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `disconnection_notice_id` bigint(20) unsigned DEFAULT NULL,
  `payment_settled_at` datetime DEFAULT NULL,
  `pending_credentials` text DEFAULT NULL,
  `credentials_issued_at` timestamp NULL DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `completed_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `consumer_reconnections_disconnection_notice_id_unique` (`disconnection_notice_id`),
  KEY `consumer_reconnections_consumer_id_foreign` (`consumer_id`),
  KEY `consumer_reconnections_confirmed_by_foreign` (`confirmed_by`),
  KEY `consumer_reconnections_completed_by_foreign` (`completed_by`),
  CONSTRAINT `consumer_reconnections_completed_by_foreign` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `consumer_reconnections_confirmed_by_foreign` FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`),
  CONSTRAINT `consumer_reconnections_consumer_id_foreign` FOREIGN KEY (`consumer_id`) REFERENCES `consumers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `consumer_reconnections_disconnection_notice_id_foreign` FOREIGN KEY (`disconnection_notice_id`) REFERENCES `disconnection_notices` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `consumer_reconnections`
--

LOCK TABLES `consumer_reconnections` WRITE;
/*!40000 ALTER TABLE `consumer_reconnections` DISABLE KEYS */;
INSERT INTO `consumer_reconnections` VALUES (5,76,1,'2026-11-22 14:22:18','2026-11-25','2026-11-25 14:31:31','2026-11-22 14:31:42',0.00,'[77]','2026-11-22 06:31:42','2026-11-22 06:32:42',2,'2026-11-22 14:24:08',NULL,NULL,'2026-11-22 14:32:42',1);
/*!40000 ALTER TABLE `consumer_reconnections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `consumers`
--

DROP TABLE IF EXISTS `consumers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `consumers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `application_reference` varchar(255) DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `account_number` varchar(255) DEFAULT NULL,
  `meter_number` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'Active',
  `full_payment_verified_at` timestamp NULL DEFAULT NULL,
  `disconnected_at` timestamp NULL DEFAULT NULL,
  `reconnected_at` timestamp NULL DEFAULT NULL,
  `membership_type` varchar(255) NOT NULL DEFAULT 'Non-Member',
  `requested_membership_type` varchar(20) NOT NULL DEFAULT 'Non-Member',
  `membership_verification_status` varchar(30) NOT NULL DEFAULT 'Not Required',
  `membership_reviewed_at` timestamp NULL DEFAULT NULL,
  `membership_reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `membership_verification_notes` text DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `membership_started_at` date DEFAULT NULL,
  `property_type` varchar(255) DEFAULT NULL,
  `ownership_status` varchar(255) DEFAULT NULL,
  `valid_id_presented` varchar(255) DEFAULT NULL,
  `valid_id_path` varchar(255) DEFAULT NULL,
  `housing_rights_documented` tinyint(1) NOT NULL DEFAULT 0,
  `resident_of_community` tinyint(1) NOT NULL DEFAULT 0,
  `association_member` tinyint(1) NOT NULL DEFAULT 0,
  `association_registered` tinyint(1) NOT NULL DEFAULT 0,
  `housing_loan_contract_submitted` tinyint(1) NOT NULL DEFAULT 0,
  `valid_id_submitted` tinyint(1) NOT NULL DEFAULT 0,
  `proof_of_residency_submitted` tinyint(1) NOT NULL DEFAULT 0,
  `membership_document_type` varchar(255) DEFAULT NULL,
  `membership_document_paths` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`membership_document_paths`)),
  `proof_of_residency_path` varchar(255) DEFAULT NULL,
  `other_document_details` varchar(255) DEFAULT NULL,
  `other_document_path` varchar(255) DEFAULT NULL,
  `membership_renewal_date` date DEFAULT NULL,
  `installation_date` date DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `rejection_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`rejection_fields`)),
  `rejection_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `resubmitted_at` timestamp NULL DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `service_address` varchar(255) DEFAULT NULL,
  `service_purok` varchar(100) DEFAULT NULL,
  `contact_number` varchar(255) DEFAULT NULL,
  `address_block` varchar(255) DEFAULT NULL,
  `address_street` varchar(255) DEFAULT NULL,
  `acknowledgment_accepted` tinyint(1) NOT NULL DEFAULT 0,
  `agreement_accepted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `reconnection_fee` decimal(10,2) DEFAULT NULL,
  `reconnection_fee_bill_id` bigint(20) unsigned DEFAULT NULL,
  `reconnection_date` date DEFAULT NULL,
  `reconnection_scheduled_at` datetime DEFAULT NULL,
  `installation_time` time DEFAULT NULL,
  `connection_approval_credentials` text DEFAULT NULL,
  `installation_completed_at` timestamp NULL DEFAULT NULL,
  `installation_completed_by` bigint(20) unsigned DEFAULT NULL,
  `reconnection_pending` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `consumers_account_number_unique` (`account_number`),
  UNIQUE KEY `consumers_meter_number_unique` (`meter_number`),
  UNIQUE KEY `consumers_user_id_unique` (`user_id`),
  UNIQUE KEY `consumers_application_reference_unique` (`application_reference`),
  KEY `consumers_membership_verification_index` (`requested_membership_type`,`membership_verification_status`),
  KEY `consumers_membership_reviewed_by_index` (`membership_reviewed_by`),
  KEY `consumers_reporting_status_index` (`status`),
  KEY `consumers_reporting_purok_index` (`service_purok`),
  KEY `consumers_reporting_disconnected_index` (`disconnected_at`),
  KEY `consumers_reporting_reconnected_index` (`reconnected_at`),
  KEY `consumers_verified_at_index` (`verified_at`),
  KEY `consumers_reconnection_fee_bill_id_foreign` (`reconnection_fee_bill_id`),
  CONSTRAINT `consumers_reconnection_fee_bill_id_foreign` FOREIGN KEY (`reconnection_fee_bill_id`) REFERENCES `bills` (`id`) ON DELETE SET NULL,
  CONSTRAINT `consumers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=111 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `consumers`
--

LOCK TABLES `consumers` WRITE;
/*!40000 ALTER TABLE `consumers` DISABLE KEYS */;
INSERT INTO `consumers` VALUES (75,'VVA-20261109-GBQE7PLL',49,'Mica Oquias','leonelmartinez0953@gmail.com','24','MT-8989','Disconnected',NULL,'2026-11-18 01:27:29',NULL,'Member','Member','Verified','2026-11-17 21:56:20',1,NULL,'2026-11-17 21:56:21','2026-11-18','Residential','Tenant','Philhealth','requirements/valid-id/1794213412_Mica_Oquias.png',1,1,1,1,1,0,0,'Housing Loan Contract (Pag-IBIG)','[\"requirements\\/membership-documents\\/1794212930_NwcPZ6eH2bdB.png\"]',NULL,NULL,NULL,NULL,'2026-11-19',NULL,NULL,0,NULL,NULL,'Block 9, Orchid, Purok 1-B, Via Vida, Panabo City, Philippines','Purok 1-B','09126473416','9','Orchid',0,1,'2026-11-09 00:36:53','2026-11-18 01:27:29',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0);
INSERT INTO `consumers` VALUES (76,'VVA-20261111-6BQL57ZR',40,'Leonel Martinez','bonghanoylovely9@gmail.com','15','MT-2121','Active','2026-11-22 06:24:08','2026-11-22 06:22:18','2026-11-22 06:32:42','Member','Member','Verified','2026-11-10 21:32:23',1,NULL,'2026-11-10 21:32:23','2026-11-11','Residential','Owner','National','requirements/valid-id/1794375085_Leonel_Martinez.jpg',1,1,1,1,0,0,1,'Proof of Residency','[\"requirements\\/membership-documents\\/1794374792_2IYKFVa9BfEU.jpg\"]',NULL,NULL,NULL,NULL,'2026-11-13',NULL,NULL,0,NULL,NULL,'Block 3, Maharlika, Purok 1-A, Via Vida, Panabo City, Philippines','Purok 1-A','09535581787','3','Maharlika',0,1,'2026-11-10 21:31:25','2026-11-22 06:32:42',0.00,NULL,'2026-11-25','2026-11-25 14:31:31','06:34:00',NULL,NULL,NULL,0);
INSERT INTO `consumers` VALUES (80,'VVA-20261117-4UJJOAWQ',43,'Len Adan','bonghanoylovely9@gmail.com','18','MT-7777','Active',NULL,NULL,NULL,'Member','Member','Verified','2026-11-17 01:13:21',1,NULL,'2026-11-17 01:13:22','2026-11-17','Residential','Owner','Philhealth','requirements/valid-id/1794906732_Len_Adan.jpg',1,1,1,1,0,0,1,'Proof of Residency','[\"requirements\\/membership-documents\\/1794906671_zMsWPBJvzCus.png\"]',NULL,NULL,NULL,NULL,'2026-11-20',NULL,NULL,0,NULL,NULL,'Block 2, Orchid, Purok 1-B, Via Vida, Panabo City, Philippines','Purok 1-B','09535581787','2','Orchid',0,1,'2026-11-17 01:12:12','2026-11-20 19:50:09',NULL,NULL,NULL,NULL,NULL,'eyJpdiI6IlU2cHBBZDBPbDR0OW01TkMyaElieVE9PSIsInZhbHVlIjoicGp6NThmYXAyYXhyQTE4NlpKL3I3WjNDeEU2dm11RGg2cWRVSkFYTDArazB0R0ZWRG5MUEI4OUZXUi9GV1hvc2hobmFkUkNnZjBxVUQrT1lqMFZQTXlDellpUGFZZVp2eVNWTUUydDVDQkJNTVAxRW9UTklFZTQ5VDZIZWdkK3Y0aExYVVB0MzlEb0VhTDViYmprdWExY2RLMDFEL0JTemdpWXhHM2VqdWVHV0Q4akt5bkRzVDVRaE4rL25CRS9Gem5MbHFMOGZFUHE2dnhSbHgxbjJaeHRxTHZCV0t3WDdxSUZsUXZTMUpsQWwweCtneGlBOUJ3SUlabnJrZ0FIWCthbWZFMjNoY3BzRzlyWEJwWFEwWG5DaFZjVEoxcjNldVM3SlljbkRhUFVmYkJJM082WTBLNnd2MWYrR2d1RitBTSs4QVVSMTJyVmNCdzZRZG5TaVhRaHV6cmhFNkhHeDlZOFU0QnNjODM0PSIsIm1hYyI6ImZkMTFiZmVkY2FiYWI2YWI0N2VjYTZiYWI1ODM1YWYxYTAyODhhOTA0ZDA2NTliZGI1N2E5NTU2NTJlMjQ1ZjciLCJ0YWciOiIifQ==',NULL,NULL,0);
INSERT INTO `consumers` VALUES (87,'VVA-20261118-C0UVAKQU',51,'Gray Hunt','bonghanoylovely9@gmail.com','26','MT-9191','Active',NULL,NULL,NULL,'Member','Member','Verified','2026-11-18 00:41:09',1,NULL,'2026-11-18 00:41:09','2026-11-18','Residential','Owner','National','requirements/valid-id/1794991210_Gray_Hunt.png',1,1,1,1,1,0,0,'Housing Loan Contract (Pag-IBIG)','[\"requirements\\/membership-documents\\/1794991158_zjcpuVvofjA1.png\"]',NULL,NULL,NULL,NULL,'2026-11-19',NULL,NULL,0,NULL,NULL,'Block 4, Sampaguita, Purok 1-C, Via Vida, Panabo City, Philippines','Purok 1-C','09535581787','4','Sampaguita',0,1,'2026-11-18 00:40:10','2026-11-18 00:41:10',NULL,NULL,NULL,NULL,NULL,'eyJpdiI6Ikk0RTNHYzFhbklqdDltellwT0djN2c9PSIsInZhbHVlIjoiV2gweXZ5VTExeDFCMklxdUhiaHFiSHB0d2s0c3Bub016UHl2UUFObHBnYWR3K0tnZU4rUHpMM2xrcDhhcjBxdTFHRzcxckIrT0JDYWhvS1BoazlKMW41R0JEZFc1b0hqV05YR3pDeW5MMTh6K3pXdFpPOUFUSmZxWndLdWpLcjY0WW0zU0Q5UTZoYjdQUzFETnU5akl0TlNyL2N6cDFTSFZYbnRnalFIQnMrUzZabFc1LzhXUHlBN3ZBYXEzdGhndzlyci90VFlXYU5Xc0I0czFTa1crc1ZlajZTTlZRRFNtL3dqM1hyNGlGMXZPdkVMT1Y3MGd3b0k3cVhnbHhIRm1MbGtpd3hKNUV2dm9kaysxS1lKT0NMWXlsUFhJbC9JVUZRd1RUNkw1NzFHYlJOUk95REUxK25PaW96NlVyZHR3eDc1dGVvOEV2bWVSdVVmMEVORy9HN2JCU1pvRnljMHF5OXU3bG5Ba2FMaTBmSElMcjFkV1k3aGNIcDgzQXBDQjNHb1RJb2RYY0RHcTZ1ODdiVEJjdz09IiwibWFjIjoiNTUwOWVhMWVmZjgxMzc5YTU0NzNlMWY4MTczNzc2ZTBhNDg3NzI5MTllNmY0NzBhNDUzZWQ2Y2Q2ODc1YWUyNiIsInRhZyI6IiJ9',NULL,NULL,0);
INSERT INTO `consumers` VALUES (88,'VVA-20261118-72ZTY32G',52,'Ren Rey','bonghanoylovely9@gmail.com','27','MT-0909','Active',NULL,NULL,NULL,'Member','Member','Verified','2026-11-18 01:23:24',1,NULL,'2026-11-18 01:23:24','2026-11-18','Residential','Owner','National','requirements/valid-id/1794993723_Ren_Rey.jpg',1,1,1,1,1,0,0,'Housing Loan Contract (Pag-IBIG)','[\"requirements\\/membership-documents\\/1794993663_e9r0flbS7Bqz.png\"]',NULL,NULL,NULL,NULL,'2026-11-20',NULL,NULL,0,NULL,NULL,'Block 1, Sampaguita, Purok 2-A, Via Vida, Panabo City, Philippines','Purok 2-A','09535581787','1','Sampaguita',0,1,'2026-11-18 01:22:03','2026-11-20 20:21:00',NULL,NULL,NULL,NULL,NULL,'eyJpdiI6ImpiWkYvazdNSHdIUXp6Um13SEkwNmc9PSIsInZhbHVlIjoiYUZYWGxwNXNsQStEQ3dhSGhiamtHVjRmTzF4aUIzN1B3ZytGNENYUm8xN3pQZGJJMlNMd0gzTXFtSTNodmNIMnJyNWNCWWh2MVRUSTJkcVhobEVzSkdvYVFtSUY5djgxdXUrdHJCWk82TWgwSW9GbHBQN0gwSVhUa2pJZEJjUFErbEZ0cHY4eEorSEdadHFzRkt0OHNVeVNhTDNhWkZkM3RyeVREeDFDanZBPSIsIm1hYyI6ImZhMTQ1ZWIxZjA4YmQ2ZTQxNDM0NDU1ZWY1MTliNmU1OTExN2JhZWNiOGE3NGZhYWY5NmZkZTQzMzJjMTAxYWEiLCJ0YWciOiIifQ==','2026-11-19 19:45:47',1,0);
INSERT INTO `consumers` VALUES (109,'VVA-20261123-VJF6CYAR',56,'Lenne Lans','bonghanoylovely9@gmail.com','31','MT-3333','Active',NULL,NULL,NULL,'Member','Member','Verified','2026-11-23 08:09:56',1,NULL,'2026-11-23 08:09:57','2026-11-23','Residential','Tenant','National','requirements/valid-id/1795450158_Lenne_Lans.jpg',1,1,1,1,0,0,1,'Proof of Residency','[\"requirements\\/membership-documents\\/1795450084_dg2u4OmZzoNM.jpg\"]',NULL,NULL,NULL,NULL,'2026-11-25',NULL,NULL,0,NULL,NULL,'Block 4, Sampaguita, Purok 1-B, Via Vida, Panabo City, Philippines','Purok 1-B','09535581787','4','Sampaguita',0,1,'2026-11-23 08:09:19','2026-11-23 08:13:16',NULL,NULL,NULL,NULL,NULL,'eyJpdiI6ImVnQnMwRmc3dnVlOUJ0cnVZSHNxZXc9PSIsInZhbHVlIjoiaEttWUd5L0xuUHVJd0hlQlU5UDkyRkw0R2xhTjJGeWpnZlRGRG8zVEZ4Rm5wVFBxUU5DNVgxK1Bxc29aUVFrUS96SFBiZm0wSWpOcWNvem91d0dyNFhhTTkwaUVqVEtYYlk1N2hqdlJTaDFNT01hSUoyK0dkWGd6VmhDY1U1d3pwR2F3cXZHUlFjZjdPR29nU1VlOWhIM0dETklHZzNMWEV5MVh3ZVRaRUhrKzIvKzFQVE9zdUpvKzJ4OVpvTmdxU2ZtNUR5NkRaYmpacmpURlNPQ09DZU5KMjVLajFrY213bTRoSUdLWUdERXQxS0F0UC9hK0M2d3dNT2ZEV2JCTkFnT0YxVHIrcnhjR3lHVVVlQ2Y4K29CMjBJaDlnMmZRZTZzeWxjcGRjcUhNV0p2UXBQVVRXMzhmaU9TRGZPU21lTGFpYmZYSnFpcnlPa3IzYVNNS0hVR0c3ckNIU3pLSVlCN3N2K0MzWjZISkxTRFllRXhWMHZsMFo3YmlvbVNoc2p6dnVKck5yVzRiNHpoZzVmN25TUT09IiwibWFjIjoiNjA4NzZlYzlkYzBlYWIxZWJkZWYzZjAzNDUyZWExM2E0ZDJhYWQwMDMwMDE0MGZiY2MyMDk0ODU4MjAxNjY2MCIsInRhZyI6IiJ9','2026-11-23 08:11:15',1,0);
/*!40000 ALTER TABLE `consumers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `disconnection_notices`
--

DROP TABLE IF EXISTS `disconnection_notices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `disconnection_notices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `consumer_id` bigint(20) unsigned NOT NULL,
  `bill_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'Active',
  `issued_at` timestamp NULL DEFAULT NULL,
  `deadline_at` timestamp NULL DEFAULT NULL,
  `settled_at` timestamp NULL DEFAULT NULL,
  `disconnected_at` timestamp NULL DEFAULT NULL,
  `confirmed_by` bigint(20) unsigned DEFAULT NULL,
  `outstanding_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `months_unpaid` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `outstanding_at_disconnection` decimal(12,2) DEFAULT NULL,
  `disconnection_reason` text DEFAULT NULL,
  `source` varchar(255) NOT NULL DEFAULT 'water',
  `loan_id` bigint(20) unsigned DEFAULT NULL,
  `loan_due_date` date DEFAULT NULL,
  `loan_balance_due` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `disconnection_notices_consumer_id_bill_id_unique` (`consumer_id`,`bill_id`),
  UNIQUE KEY `disconnection_notices_loan_id_unique` (`loan_id`),
  KEY `disconnection_notices_bill_id_foreign` (`bill_id`),
  KEY `disconnection_notices_confirmed_by_foreign` (`confirmed_by`),
  KEY `disconnection_notices_consumer_id_status_index` (`consumer_id`,`status`),
  CONSTRAINT `disconnection_notices_bill_id_foreign` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`id`) ON DELETE SET NULL,
  CONSTRAINT `disconnection_notices_confirmed_by_foreign` FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `disconnection_notices_consumer_id_foreign` FOREIGN KEY (`consumer_id`) REFERENCES `consumers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `disconnection_notices_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `disconnection_notices`
--

LOCK TABLES `disconnection_notices` WRITE;
/*!40000 ALTER TABLE `disconnection_notices` DISABLE KEYS */;
INSERT INTO `disconnection_notices` VALUES (1,75,29,'Disconnected','2026-11-14 00:06:06','2026-11-17 00:06:06',NULL,'2026-11-18 01:27:29',1,936.00,3,NULL,'2026-11-18 00:06:06','2026-11-18 01:27:29',936.00,'Unpaid billing balance after the disconnection notice payment deadline.','water',NULL,NULL,0.00);
INSERT INTO `disconnection_notices` VALUES (2,76,32,'Disconnected','2026-11-18 00:06:06','2026-11-21 00:06:06','2026-11-22 06:24:08','2026-11-22 06:22:18',1,990.00,3,NULL,'2026-11-18 00:06:06','2026-11-22 06:24:08',990.00,'Unpaid billing balance after the disconnection notice payment deadline.','water',NULL,NULL,0.00);
INSERT INTO `disconnection_notices` VALUES (21,76,NULL,'Active','2026-11-20 00:13:44','2026-11-23 00:13:44',NULL,NULL,NULL,439.00,0,NULL,'2026-11-20 00:13:44','2026-11-20 00:13:44',NULL,NULL,'loan',14,'2026-11-19',439.00);
/*!40000 ALTER TABLE `disconnection_notices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `incidents`
--

DROP TABLE IF EXISTS `incidents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `incidents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `consumer_id` bigint(20) unsigned DEFAULT NULL,
  `reported_by` varchar(255) NOT NULL,
  `category` varchar(255) NOT NULL,
  `location` varchar(255) NOT NULL,
  `purok` varchar(100) DEFAULT NULL,
  `block` varchar(50) DEFAULT NULL,
  `description` text NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'Pending',
  `reported_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolution_notes` text DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `media_type` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `custom_incident_type` varchar(255) DEFAULT NULL,
  `priority` varchar(20) NOT NULL DEFAULT 'Needs Review',
  `automatic_priority` varchar(20) NOT NULL DEFAULT 'Needs Review',
  `classification_reason` varchar(255) NOT NULL DEFAULT 'No automatic classification was recorded for this report. Review the incident details.',
  `priority_reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `priority_reviewed_at` timestamp NULL DEFAULT NULL,
  `priority_review_note` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `incidents_consumer_id_foreign` (`consumer_id`),
  KEY `incidents_status_index` (`status`),
  KEY `incidents_reported_at_index` (`reported_at`),
  KEY `incidents_reporting_date_status_index` (`reported_at`,`status`),
  KEY `incidents_reporting_category_index` (`category`),
  KEY `incidents_location_group_index` (`purok`,`block`),
  KEY `incidents_priority_reviewed_by_foreign` (`priority_reviewed_by`),
  KEY `incidents_priority_index` (`priority`),
  CONSTRAINT `incidents_consumer_id_foreign` FOREIGN KEY (`consumer_id`) REFERENCES `consumers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incidents_priority_reviewed_by_foreign` FOREIGN KEY (`priority_reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `incidents`
--

LOCK TABLES `incidents` WRITE;
/*!40000 ALTER TABLE `incidents` DISABLE KEYS */;
INSERT INTO `incidents` VALUES (1,76,'Leonel Martinez','Water Leak','Via Vida','Purok 1-A','3','AGAS ANG TUBIG','Resolved','2026-11-11 05:47:49',NULL,'2026-11-10 21:47:49','2026-11-10 21:47:49','incident-evidence/NcWeGReCMQmahjeXRVDuMdETN8ZnFKNQ2NXnIGp4.jpg','image','2026-11-10 21:47:25','2026-11-10 21:47:49',NULL,'Needs Review','Needs Review','No automatic classification was recorded for this report. Review the incident details.',NULL,NULL,NULL);
INSERT INTO `incidents` VALUES (2,76,'Leonel Martinez','Water Leak','Via Vida','Purok 1-A','3','AGAS ANG TUBIG','Pending','2026-11-22 19:03:33',NULL,NULL,NULL,'incident-evidence/NVsXDo3NLiDBUCizLrBD2mcIc4QcloNpCj8WdoDB.jpg','image','2026-11-10 21:47:25','2026-11-22 11:03:33',NULL,'Urgent','Needs Review','No automatic classification was recorded for this report. Review the incident details.',1,'2026-11-22 11:03:33','water flooding');
INSERT INTO `incidents` VALUES (3,76,'Leonel Martinez','Water Quality Issue','Via Vida','Purok 1-A','3','POOR WATER QUALITY','Resolved','2026-11-11 05:50:33',NULL,'2026-11-10 21:50:33','2026-11-10 21:50:33',NULL,NULL,'2026-11-10 21:49:19','2026-11-10 21:50:33',NULL,'Needs Review','Needs Review','No automatic classification was recorded for this report. Review the incident details.',NULL,NULL,NULL);
INSERT INTO `incidents` VALUES (4,76,'Leonel Martinez','Others','Via Vida','Purok 1-A','3','ING ANI NAHITABO','Pending','2026-11-22 20:01:05',NULL,NULL,NULL,NULL,NULL,'2026-11-11 05:59:34','2026-11-22 12:01:05','PIPE','Urgent','Needs Review','No automatic classification was recorded for this report. Review the incident details.',1,'2026-11-22 12:01:05','naguba');
INSERT INTO `incidents` VALUES (6,76,'LEONEL','Others','Block 3, Maharlika, Purok 1-A, Via Vida, Panabo City, Philippines','Purok 1-A','3','APLOAFAO','Resolved','2026-11-11 14:04:21',NULL,'2026-11-11 06:04:21','2026-11-11 06:04:21',NULL,NULL,'2026-11-11 06:03:31','2026-11-11 06:04:21','PIPE NAGUBA','Needs Review','Needs Review','No automatic classification was recorded for this report. Review the incident details.',NULL,NULL,NULL);
/*!40000 ALTER TABLE `incidents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `installation_reschedule_requests`
--

DROP TABLE IF EXISTS `installation_reschedule_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `installation_reschedule_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reschedule_type` varchar(255) NOT NULL,
  `consumer_id` bigint(20) unsigned NOT NULL,
  `loan_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `reason` text NOT NULL,
  `original_installation_date` date NOT NULL,
  `original_installation_time` time DEFAULT NULL,
  `new_installation_date` date DEFAULT NULL,
  `new_installation_time` time DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `reviewed_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `consumer_reconnection_id` bigint(20) unsigned DEFAULT NULL,
  `preferred_installation_date` date DEFAULT NULL,
  `preferred_installation_time` time DEFAULT NULL,
  `assignment_number` int(10) unsigned DEFAULT NULL,
  `assigned_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reschedule_consumer_assignment_unique` (`consumer_id`,`assignment_number`),
  KEY `installation_reschedule_requests_reviewed_by_user_id_foreign` (`reviewed_by_user_id`),
  KEY `installation_reschedule_requests_consumer_id_status_index` (`consumer_id`,`status`),
  KEY `installation_reschedule_requests_loan_id_status_index` (`loan_id`,`status`),
  KEY `installation_reschedule_requests_status_reschedule_type_index` (`status`,`reschedule_type`),
  KEY `reschedule_reconnection_fk` (`consumer_reconnection_id`),
  CONSTRAINT `installation_reschedule_requests_consumer_id_foreign` FOREIGN KEY (`consumer_id`) REFERENCES `consumers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `installation_reschedule_requests_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `installation_reschedule_requests_reviewed_by_user_id_foreign` FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reschedule_reconnection_fk` FOREIGN KEY (`consumer_reconnection_id`) REFERENCES `consumer_reconnections` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `installation_reschedule_requests`
--

LOCK TABLES `installation_reschedule_requests` WRITE;
/*!40000 ALTER TABLE `installation_reschedule_requests` DISABLE KEYS */;
INSERT INTO `installation_reschedule_requests` VALUES (1,'connection',76,NULL,'assigned','WALA KO SA BALAY','2026-11-12',NULL,'2026-11-13','06:34:00',NULL,1,'2026-11-10 21:35:02','2026-11-10 21:34:19','2026-11-10 21:35:02',NULL,NULL,NULL,1,'2026-11-11 05:35:02');
INSERT INTO `installation_reschedule_requests` VALUES (5,'loan',80,4,'assigned','wala ko ani, available nko kay 19','2026-11-19','15:15:00','2026-11-19','15:17:00',NULL,1,'2026-11-17 20:18:02','2026-11-17 20:17:12','2026-11-17 20:18:02',NULL,NULL,NULL,1,'2026-11-18 04:18:02');
/*!40000 ALTER TABLE `installation_reschedule_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
INSERT INTO `jobs` VALUES (1,'announcements','{\"uuid\":\"46b2eed5-df10-4981-b255-b11417732a1d\",\"displayName\":\"App\\\\Jobs\\\\SendAnnouncementEmail\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":3,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":\"60,300\",\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"App\\\\Jobs\\\\SendAnnouncementEmail\",\"command\":\"O:30:\\\"App\\\\Jobs\\\\SendAnnouncementEmail\\\":3:{s:10:\\\"deliveryId\\\";i:1;s:10:\\\"connection\\\";s:8:\\\"database\\\";s:5:\\\"queue\\\";s:13:\\\"announcements\\\";}\",\"batchId\":null},\"createdAt\":1794084254,\"illuminate:log:context\":{\"data\":[],\"hidden\":{\"laravel_unique_job_cache_store\":\"s:4:\\\"file\\\";\",\"laravel_unique_job_key\":\"s:51:\\\"laravel_unique_job:App\\\\Jobs\\\\SendAnnouncementEmail:1\\\";\"}},\"delay\":null}',0,NULL,1794084254,1794084254);
INSERT INTO `jobs` VALUES (2,'announcements','{\"uuid\":\"7f2b0136-43de-458d-a86f-cd13cd75fc9c\",\"displayName\":\"App\\\\Jobs\\\\SendAnnouncementEmail\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":3,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":\"60,300\",\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"App\\\\Jobs\\\\SendAnnouncementEmail\",\"command\":\"O:30:\\\"App\\\\Jobs\\\\SendAnnouncementEmail\\\":3:{s:10:\\\"deliveryId\\\";i:2;s:10:\\\"connection\\\";s:8:\\\"database\\\";s:5:\\\"queue\\\";s:13:\\\"announcements\\\";}\",\"batchId\":null},\"createdAt\":1794084254,\"illuminate:log:context\":{\"data\":[],\"hidden\":{\"laravel_unique_job_cache_store\":\"s:4:\\\"file\\\";\",\"laravel_unique_job_key\":\"s:51:\\\"laravel_unique_job:App\\\\Jobs\\\\SendAnnouncementEmail:2\\\";\"}},\"delay\":null}',0,NULL,1794084254,1794084254);
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loan_agreements`
--

DROP TABLE IF EXISTS `loan_agreements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `loan_agreements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `loan_id` bigint(20) unsigned NOT NULL,
  `pdf_base64` longtext NOT NULL,
  `issued_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `email_status` varchar(20) NOT NULL DEFAULT 'pending',
  `email_recipient` varchar(255) DEFAULT NULL,
  `email_sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `loan_agreements_loan_id_unique` (`loan_id`),
  CONSTRAINT `loan_agreements_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loan_agreements`
--

LOCK TABLES `loan_agreements` WRITE;
/*!40000 ALTER TABLE `loan_agreements` DISABLE KEYS */;
INSERT INTO `loan_agreements` VALUES (2,4,'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KUg7gxzSxKBYPXkrNMjFI2dQEA/3hfcfPdGGGgplbmRzdHJlYW0KZW5kb2JqCjggMCBvYmoKPDwvVHlwZSAvRm9udAovU3VidHlwZSAvVHlwZTAKL0Jhc2VGb250IC9TVUJBQUIrRGVqYVZ1U2FucwovRW5jb2RpbmcgL0lkZW50aXR5LUgKL0Rlc2NlbmRhbnRGb250cyBbMTAgMCBSXQovVG9Vbmljb2RlIDkgMCBSCj4+CmVuZG9iago5IDAgb2JqCjw8L0xlbmd0aCAzNDUgPj4Kc3RyZWFtCi9DSURJbml0IC9Qcm9jU2V0IGZpbmRyZXNvdXJjZSBiZWdpbgoxMiBkaWN0IGJlZ2luCmJlZ2luY21hcAovQ0lEU3lzdGVtSW5mbwo8PC9SZWdpc3RyeSAoQWRvYmUpCi9PcmRlcmluZyAoVUNTKQovU3VwcGxlbWVudCAwCj4+IGRlZgovQ01hcE5hbWUgL0Fkb2JlLUlkZW50aXR5LVVDUyBkZWYKL0NNYXBUeXBlIDIgZGVmCjEgYmVnaW5jb2Rlc3BhY2VyYW5nZQo8MDAwMD4gPEZGRkY+CmVuZGNvZGVzcGFjZXJhbmdlCjEgYmVnaW5iZnJhbmdlCjwwMDAwPiA8RkZGRj4gPDAwMDA+CmVuZGJmcmFuZ2UKZW5kY21hcApDTWFwTmFtZSBjdXJyZW50ZGljdCAvQ01hcCBkZWZpbmVyZXNvdXJjZSBwb3AKZW5kCmVuZAplbmRzdHJlYW0KZW5kb2JqCjEwIDAgb2JqCjw8L1R5cGUgL0ZvbnQKL1N1YnR5cGUgL0NJREZvbnRUeXBlMgovQmFzZUZvbnQgL1NVQkFBQitEZWphVnVTYW5zCi9DSURTeXN0ZW1JbmZvIDExIDAgUgovRm9udERlc2NyaXB0b3IgMTggMCBSCi9EVyA1MDAKL1cgWzAgWzYwMF0gMzIgWzMxOF0gMzUgWzgzOF0gMzcgWzk1MF0gNDAgWzM5MF0gNDEgWzM5MF0gNDQgWzMxOF0gNDUgWzM2MV0gNDYgWzMxOF0gNDggWzYzNl0gNDkgWzYzNl0gNTAgWzYzNl0gNTEgWzYzNl0gNTIgWzYzNl0gNTMgWzYzNl0gNTQgWzYzNl0gNTUgWzYzNl0gNTYgWzYzNl0gNTcgWzYzNl0gNTggWzMzN10gNjUgWzY4NF0gNjcgWzY5OF0gNjggWzc3MF0gNzAgWzU3NV0gNzIgWzc1Ml0gNzMgWzI5NV0gNzQgWzI5NV0gNzYgWzU1N10gNzcgWzg2M10gNzggWzc0OF0gNzkgWzc4N10gODAgWzYwM10gODIgWzY5NV0gODMgWzYzNV0gODQgWzYxMV0gODYgWzY4NF0gODcgWzk4OV0gOTcgWzYxM10gOTggWzYzNV0gOTkgWzU1MF0gMTAwIFs2MzVdIDEwMSBbNjE1XSAxMDIgWzM1Ml0gMTAzIFs2MzVdIDEwNCBbNjM0XSAxMDUgWzI3OF0gMTA3IFs1NzldIDEwOCBbMjc4XSAxMDkgWzk3NF0gMTEwIFs2MzRdIDExMSBbNjEyXSAxMTIgWzYzNV0gMTE0IFs0MTFdIDExNSBbNTIxXSAxMTYgWzM5Ml0gMTE3IFs2MzRdIDExOCBbNTkyXSAxMTkgWzgxOF0gMTIxIFs1OTJdIDgzNjkgWzYzNl0gNjU1MzMgWzEwMjVdIF0KL0NJRFRvR0lETWFwIDEyIDAgUgo+PgplbmRvYmoKMTEgMCBvYmoKPDwvUmVnaXN0cnkgKEFkb2JlKQovT3JkZXJpbmcgKFVDUykKL1N1cHBsZW1lbnQgMAo+PgplbmRvYmoKMTIgMCBvYmoKPDwKL0ZpbHRlciAvRmxhdGVEZWNvZGUKL0xlbmd0aCAyNzk+PgpzdHJlYW0KeJztz8dOQ0EMBdArIEAIPXQCJPTea4D//y2st0NilwWKdI7kufYsRp5kRBPNOVk11XStTDc5k9m0K+fSyXwWspilLGclq+lm7dcL61Ub2axzq2o7O9mt7GUv+zlIv/pBDnNUeZyTPzY4zVnOc5HLXOU6N7mtu7vc5yGPecpzTS95zVve85FhTZ+jfhkAAAAAAAAAAAAAAAAAAAAYO1//vQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAIyB7x9QSAdjCmVuZHN0cmVhbQplbmRvYmoKMTMgMCBvYmoKPDwvVHlwZSAvRm9udAovU3VidHlwZSAvVHlwZTAKL0Jhc2VGb250IC9TVUJBQUMrRGVqYVZ1U2Fucy1Cb2xkCi9FbmNvZGluZyAvSWRlbnRpdHktSAovRGVzY2VuZGFudEZvbnRzIFsxNSAwIFJdCi9Ub1VuaWNvZGUgMTQgMCBSCj4+CmVuZG9iagoxNCAwIG9iago8PC9MZW5ndGggMzQ1ID4+CnN0cmVhbQovQ0lESW5pdCAvUHJvY1NldCBmaW5kcmVzb3VyY2UgYmVnaW4KMTIgZGljdCBiZWdpbgpiZWdpbmNtYXAKL0NJRFN5c3RlbUluZm8KPDwvUmVnaXN0cnkgKEFkb2JlKQovT3JkZXJpbmcgKFVDUykKL1N1cHBsZW1lbnQgMAo+PiBkZWYKL0NNYXBOYW1lIC9BZG9iZS1JZGVudGl0eS1VQ1MgZGVmCi9DTWFwVHlwZSAyIGRlZgoxIGJlZ2luY29kZXNwYWNlcmFuZ2UKPDAwMDA+IDxGRkZGPgplbmRjb2Rlc3BhY2VyYW5nZQoxIGJlZ2luYmZyYW5nZQo8MDAwMD4gPEZGRkY+IDwwMDAwPgplbmRiZnJhbmdlCmVuZGNtYXAKQ01hcE5hbWUgY3VycmVudGRpY3QgL0NNYXAgZGVmaW5lcmVzb3VyY2UgcG9wCmVuZAplbmQKZW5kc3RyZWFtCmVuZG9iagoxNSAwIG9iago8PC9UeXBlIC9Gb250Ci9TdWJ0eXBlIC9DSURGb250VHlwZTIKL0Jhc2VGb250IC9TVUJBQUMrRGVqYVZ1U2Fucy1Cb2xkCi9DSURTeXN0ZW1JbmZvIDE2IDAgUgovRm9udERlc2NyaXB0b3IgMjAgMCBSCi9EVyA1MDAKL1cgWzAgWzYwMF0gMzIgWzM0OF0gNjUgWzc3NF0gNjcgWzczNF0gNjggWzgzMF0gNzAgWzY4M10gNzMgWzM3Ml0gNzYgWzYzN10gNzcgWzk5NV0gODIgWzc3MF0gODQgWzY4Ml0gOTcgWzY3NV0gOTggWzcxNl0gOTkgWzU5M10gMTAwIFs3MTZdIDEwMSBbNjc4XSAxMDIgWzQzNV0gMTAzIFs3MTZdIDEwNCBbNzEyXSAxMDUgWzM0M10gMTA4IFszNDNdIDEwOSBbMTA0Ml0gMTEwIFs3MTJdIDExMSBbNjg3XSAxMTIgWzcxNl0gMTE0IFs0OTNdIDExNSBbNTk1XSAxMTYgWzQ3OF0gMTE3IFs3MTJdIDExOCBbNjUyXSAxMjEgWzY1Ml0gMTIyIFs1ODJdIDY1NTMzIFsxMTEzXSBdCi9DSURUb0dJRE1hcCAxNyAwIFIKPj4KZW5kb2JqCjE2IDAgb2JqCjw8L1JlZ2lzdHJ5IChBZG9iZSkKL09yZGVyaW5nIChVQ1MpCi9TdXBwbGVtZW50IDAKPj4KZW5kb2JqCjE3IDAgb2JqCjw8Ci9GaWx0ZXIgL0ZsYXRlRGVjb2RlCi9MZW5ndGggMjIxPj4Kc3RyZWFtCnic7c43DgMgDAXQr/Tee8/9LxnEkC0TW/SeZAuDjZw06rR+kG6JXvolD2o9rHmU8bdjUmL6c36WeRZZZpV1NtlmV2/3OeSYU87lfMk1t9zzqC/PvJp3BgAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAgH/z/gB9/AIxCmVuZHN0cmVhbQplbmRvYmoKMTggMCBvYmoKPDwgL1R5cGUgL0ZvbnREZXNjcmlwdG9yCi9GbGFncyAzMgovRm9udE5hbWUgL1NVQkFBQitEZWphVnVTYW5zCi9TdGVtViA3MAovQXNjZW50IDkyOAovRGVzY2VudCAtMjM2Ci9Gb250QkJveCBbLTEwMjEgLTQ2MyAxNzkzIDEyMzJdCi9JdGFsaWNBbmdsZSAwCi9DYXBIZWlnaHQgOTI4Ci9Gb250RmlsZTIgMTkgMCBSCj4+CmVuZG9iagoxOSAwIG9iago8PCAvRmlsdGVyIC9GbGF0ZURlY29kZQovTGVuZ3RoMSAxNDM3NgovTGVuZ3RoIDEwNDIzID4+CnN0cmVhbQp4nNV6eXwUVbb/vffUraqu3jvd2ZPuLJ0QwhITAgQQ2sgOYmSJgIIJhLC4ALITMCxDAgIDCAmKCFEDIiBGZDCBiKgRRGBcAN8w4EMliD4jMj4cnZDc/E5VJ4C+mfd776/f55fOrfXWueece875nnOrCCWEWMkSAiRnyqT8Ass39HO8chFb1yl4wbpd2UMIdeN54sTH82dcPtuf43l3PL825fHZ8/3bvPWEsHZ4/uvj+fNnSHvkmYSAD899kx9bUHiu7nwVngcISZny2PSJ+USa9y0hHXbh/aYn8h+f1GH1PfsJ6ViJNMiM6bNmtywlYwnpXKk/P3HubB+ZGpOF53U4piicMfnxmV3mTiPkLjwne2Y8OWlGT+VveJg+gxA+5f4RndMf7Ni1EyHd/oIXhxFdNja+GrfkEXuvn4lX1Q/JmfdSm9r2v3zeNNQ2xqTLq9+kRgfcKo+LGEJs4pfPGx+wjWm9fvuvt9ELjOsWIpGvcd+e+PBI0zknCcRPUkgn0oPcQ/qS/mQwySEjyYNkKplB5pEFvjdamltaDErBvu1IB5KGfe/FvgPJUDKc5JJ88ih58nbflq9bvmy51PJFy4WWv7acbTnT8lnLpy2ftHzc8ueWD1rqWt5vee/SahL4L5z+938MZZAIJzJRUH4Tcm9GeazERuzEQZzERUKIm3gIkU6TQuOB1YTIB8g2ZQuZCV5Sha0Sr21jl0kB7i/wMFKK7Sts5di2YivAtg3bOmy7sK3GtpTtJ9f5AuLg5WSWnEJOSFvICVkiJ9jDemspl+bg+VVygjfhfiiZJZ3B43XY3KRUutzSqD+rfEf6SldJEV47JBWSmbifKTWQmexT0lk/5i5yiGWRd6WXgnvlJDmkX5OuGH0PwWA8TiXTIYF0w+v7pFpyt7ya5Op75LGv2oXEGvrsDSNJKpmCmmGoled0S5E8LBT3El4pFOVSIa9ETSokMmCRbhL5JlV5MZPQbs823EUcZxvONqSFOOOc/jhnXKFEmmZBVNMVUa7Yfv3pSZSekW2o1TB+BGl4SV7grigzKVWkGBOPsLLSsBC7x+mNjY6KDAv1uENcTofNajGpiswlYJREauGyz9F0KizrLtK519levZp66du69Ib09LSA00zNzGwxW91SpDPS5faqdByNgzgzjQvJgDhPnNESQoyWGWc0KUx8MZL6+06hiVMrJtPu4sXhtLd4fkrFZHFh8otTxAc0b6Q4QqcWwgqxH0pFPt0u8reI/c+JCXSb3p6jw7bQ7eiSZGbLZSWFX0erCkM7TyYZZFWga6Ldn+RPsicnJmeTZyyxz3RaG/5MovyMZW2Sa027xA1dkuOi/Cawemwmqz3OmmqLstrvMndxNA2pMo0cUmUe+dCQKou+cesbm76xjnxodA25q+Vo9zF1DTcaHNf+fs2Z5XShRhz16Td61RtXHNfShowYrTj4D3obE0/jZY87NCO9a2aXpOR0VK2cEJ+U2aXrndf9GTQh5I57/NMHJ058cNTEiaO2HTr8QsWhw02bcydOePDBiQVwV0XT2ArvttrD27fXHGIbNv1hWVnZsuVlxRcPH75w4XDtBZZftuwPmzb9YWl58T/+U7ZeOPz2Xy/UHroYjDdV4idWJLvQ87oF7PKzZLPNqhBwySREszkuDqkKGYnyabp8Q6rsxjEJdB9Tn97gzNKFbGpIozLzuF1hCUkss4urGysqWbZ8RUV52abNsusb0fvqVdHzyvf02JeXaF0DjleJ4003xvMG7Io+nkKJ2SWFqATH63XjNt2QjFCXx82UhK6uzC6sEkmWlVesWL5cdjWIXpe+FD2+v0I/uHqVvmfIsa3FRd8nAmc+ImCBbWS5DBKNIOEykjp7KkixW4YHje36mcqlw8VecZTqMauAXmLFbDnav/Mg2cokSiTHxVOG66SFoIUWsKjmK2x5pT7GBdzswzGw71tkOdPJS0gXrd+gnXDhzBkh0KdKWy5L69psLxAiV7hIhWWDa024KdoeC9GeqHB86obun/W6yaTReOZ0uDLS0cFYcjpxOkhCvL5lq7e+8AL+v/DCTWoSv9y8KX6hJp4jTotT2E7TDPx1oRkVYpYoEaViFl1LF9CFdK3O61cYKcbyc/p0BTzZUCGxCr5UIRUm1StHo7NTs+Ns64xSfUYb6oKCpN/QQwaaK5rpATvYJTauW5yTZ/oznKgNQQeL5+ikj+jgpsrd0qyB1QMbz+029F+O4w1GmaPJtkByRGQUhEc7uUScnEvZjhedm6wV7g0SqcAwpjGqRYc5QI7RHcuDjhXa6lPICxiedLbh6FHDiQx+bjTcch/+A62KdjjDspC7QPooKZfnKgulhXxuVGmEIhEpQoqUonj0bDJXnhM5K2p29DJSErEsclnUsuhdZFeUcxwZ50cxMruSbr0pellCvKxk9qYZ6RI6miITuoq92zQUFZmRf98rJY+cmb/w7OhvqbvfQxHixu7du+fRDT0e3zxoXnn2vafuSv/2vYd3zIgR3xvyb8U5n4XytyMzAp2IJ0QrMXlLfCEVHmuFaaMcXeHbmLBBXuN5OSU0OoSAOyI6yeeIBrfXJKfoaggd2aYBk6EBVAG6QpjhCw31NzCOfINhRP+hXtJowFQQm+/N9xXESWQcjaUetxQXn5ScGUuD0SOVZgYPfiMg9NnwsvhEfDv++LSRHz5+5HjNjn0Hy7a9/OyII0/OOjHmG2r5I/i9deu/+Mnvf/+u9PJ1fyjbOW/GrKLEpAM+36f7F+3RUbsA57kS7YqhBy8NxFArWAmANZuAWangFJaaqEUj0bIqWYz4YR7ZFiQtumBne9U1pDv1ma0/26shHWUxplY6gdN7Qp/U9mZMZwaSMZiwzCNPEyWUppIkmgpd6TB6v+V+ay4tpHPoQlhBrTiZJkSUDCe6njPBGZcJsmBUZIpz5040j+f+pstwuiljl6igee+3xojLUgHyHkPGBxKkSMVZ4oiJrFDcFY5VVlZBllrXKJWxYdFUg2iiOeRYRxO9c2Ycd0RBh+4zOEmOumu6G+t+jBMk6oLzowcOp6514nGT30yMPh9fQERzRYfRHRppojgrfhz//pSxRx997aOPXnvgxZH83G7xjN0urv3H38TPPt/Ju9IObt16MDHJyJjWIf/lRlxJJKMDiSEysZZYSEWoXBEdusNRYVkVvyF6jd8Sb4qOiA2JhjhvlB8DDRpSvRFq6pvqb5tQwH2SnKSn2Wk4LZ3kJ2WUfH8sG4cYfQcIUU8nmhDPoE2UBJ8eluLSQ1nlyu3bV2KjpqHPD/3wjL3n/ke/olxc/1o0i2s0h0YNfR56HnrpxcOHX3zpEFtQnZgkfhI/PjhO/Pj9N+I/jEA1ge6IDWIQ5uTSFJwXmUwMhHMnAwZOCeMGxzkBDhSDsqxgolFnYEPnO+JBEE71SRr9NiZBekBXiILz5OzWfUzANZpRGSJ5Fh/IJ0MVqZIVtBmcHJpA43bB0eavz1DRnMHP5TYu5ak6L0BWo45XGzpOIJ3JvQF/OGo4Wa6I7Vjh2hC7JvnltHBLYvtoT2K03YSRHMO5PS4qzdGE6F/XYCi3zWeNsyx01jtRvRNGncQMA9cVw20T4hMR3UPaOqB9sNXrd+xYv37nDrFj2QbS8u+XxIalz7wsfvnlF/FL5cANy5dt3Lhs+Qb2wZbS0i3Pl5RuyfXtX/LmJ5+8uWS/L/7YuvPffnt+3TGaP3vZstnYDLtZijKVokzhht0kKN4IWkIiKrQdUgVZFeqtcGwIXeNXoqPjQmJJfHy01TAbFKANob4RP7dZTWhdxHuRR6OORh+NeS+2zqvsdtW6vnMB2k03w8ZdITa0GJLZhWQEbSU+ibYJhlr4aujWIWgtPfY/9qW4SR1fU6BO8Ya4MnQr7d1qUV60FWqlrtyHqf37b2ioAW7bxUOxbHObPekyXUfDeV9KMHLf6IBNXi7tREjGACSRcNXRhLFFR48bQWDWI8T1M2d0eJYShKETh163GXFMI+8F7iFOBH4uMR2vqFMDjTixQgFNQRCT9YsmJ2iqfgMjnVKmxzmuZ8JGImziGqJpXTAPrj/b0BbVDMC6tVN/aDNZ/XhM/H6fhdJxgWw7tTO7YlftZDSZi1XaGmJSqMpkMEmhNILl0tEsxzKZTmHz6Vy2CJ6U5inz1VK6ki2xPMueg3IpLBgIMRlIAMymWa24xvyi6ArL+mxl8yMrz3FbcwTsa0ylxWKp4W+zWi7zZLSHCNI1EGl90bZPK3PSF7EMKQvb4FwTqURYSZrbEYlGkN5qAsHMNu2APcobxTBG6HGhNRZ07eax3ToJ5cmFV5e1EHGdOihZdrVw2g9/EK+JhbSEjij5gU8498h4cVz8RZwXx8c/cmbgQLqdomR0+wBjTk7gnFThnKhY/3UKeEiZaSktc6jMoRGsR9JJtElyGZkT+pgRCfT53Z8XgmqkzlZ788cZ+xRKN96gmdQrvhInRTaOs5+WiykiR+Tzzjfn0XDaiXagYTvFZrFEPCXKDb3g+FICjm8iKQGXXCaxMrJUek3lVEFAkDRdH2frgsNi1rbfbsaBjVCvt4QTMLZ5BstprvpID+IDdzd3a6XJeyJNM+kQcKtl7DWJLNVkJMi7myhStehUmwxr7VWPB2n7c6w6WUy3nBk6qiHhj9i/f/RRczzSbd7KChpT2fHmrFbadCPSBtLpLfI608lJejLh0BGKGFmdkZvSgDWNB3gOz+Pr+HYu69SRKtJrxKhHW8pFoUHHTLIDIWZGlDJeRZZauCpntbL4G5r1RoSrb9KnwaBtDVhzrHnWddbtVoO2Q27NP058dPryfX1KnsCBNoqfbuwuf++WnqfheJz4AxZDyTQaehBJz9V1jusb0gKmNCVHWQJLJCmoDE8cquHfmh5BUphxGjRkL9KwkHcCXcCpqApzUqbqO2AmzYROrJmyNYWBCuR11Yzeiq7KNTla6q2hUFYcq0nPsHTF6zlJ2G2nNVxVbUWX/TNsupvmgu6iJqZ5mFsJ0ZJYkuJTkjSf1kXJ1KayRaxIWaAtYcuUZdp6FipRM4TQKEigHSBZbWfqQntBrjrGNEmdZpqrLkC7Xgtl9HlwG3iEzoszjaCEs0I70sW0mHb8QBSfFMV1/FyTCr82pnJvE5b8jV+16Y9nGHa6IBCroNiUOTF7zUZhUVQuU4VFS12VVpttCpZUnTEkZrWlWz/oDWXz6iYcSOvKuisD2QBlKitUljBFpibZQyPl/nSQ/CAdLU+iU+UF8gr6tFxGt8jbzQ6Da3Q5Z5zBsoOV14nrzdOQ25te6avGVOmrm170Zz3OnL+jDipzkbJgHRRhz4AIjyPcYO+OOkh33QyjAkoOurOxheSLopnCxYuUipaLtAedL1aKY+IDUUoX8KGiWlwR34hqOpBG0ig6sFI8JLbpiEErMcfALKMttkhrjdgSQnoEwjGu6OHF5dBUJunRpY9TDy/uoDcGzcKohAJmu8fr6eN5xPO6hxtxJghn/jg9pZZSUQF0o1i7Zcta0Z1+eFPn8Kb4iHdu/viZ0pJndl6+8MXXzbtaYxtmdzh6NHkjkInYAZrsBAm4U5IgW5aIByRPmcldZl1qlrgMThOJDrVxLSJCcvZxa9EWKcZgrk7nzhmMf710Fl1Z+u/OlMioj/YHYg14WRhCOeGUI6Aokod4qJuFQpjkJ37qZ0mQLCcpSWqSyRfblXZl/Wl/NoXPkebweSEr5ZXKs/KzinecUWKEhSRAJ6rL64zz6fnLLVXA2nuKep8+/87g1fMvfkQ/pKRpefMq8UxZ2TOsNnT9U2IKLS6f0LyKn/v8L2sPsfubr5UuX75Ct2O9Tn4J7SOZPBXoZbUwm5nFemNVE1M05vXGZmvmWK/kocTzontTeJlTKiOb/AhQ7WI1szdKIfFREbaOSoQ7vp3jYh3aUT1mKFnB2HSjdeHl2C23tulaad2hcuwIEOMOelM6p9yfAkE8MxIU7z8ppjrTtqxNGjjr1CM73py3c+HX/ya+EFen/bikqOHJ12pLtxR9/REN+3nqX3nlB926Lpk7cZI3IvX8wfNfpnX+pF//lU89scgb3vHonmP1SXq8bURb/A5tQSGDMXMJBsAAQkyAq46zmKUbtpeehnmtpue1qpHXqkRty2tDiMlLHOh2XsVhCphmmLabTOPAACFnnCz92HztZPM1BKHGc3pWS2/lOjJ5K5AsObnMAZNsWd9J+koyRk5KskGirxM9oSEcEa8tn8Gg8S/iYo6q29cEAu1hgDSAj4XFsBywhlaYKunxw80ipUjeHqu3JJYipXC/7FO7kwyawXpJvXg3eSDpR/uxQdIgPkAeQ3LlQjZVmsoXkrmY5iyQFvA58hL1WbJZTkH7w+TGhPkNG9x87Aw9T//6WfNxjDVh0ncIYJT0JUTZqWMBLQoM4pEyx/gvRWomiNTMGoukzGxGX0OIQF/jrRBhwd5OQizZGkdVYNw0qxazZlKDa5tmhVgdZ1tXNhvS0/85ONza30rpiIEVP8lM5kzDzFJzae14IqJEb9abd9HStKHsPp6tBbQxbBp7lE/W8rQiVswW8WK+RCtnZTxGISaGiCXJXF8cp4qE866YiEnSNAuxRYJH8qgRFofNJ8Vxn+xTfGqCKVHzm302n60X6wGZUgZPU7uassx9LGm2/qQ/HcwCUj+E/2wEiGw1oAZMfbX7LAFbwDaaISZZcmyFbDLkSxN4npyn5KkFpgKtwDwP56GIzYd50my+QF6gzFNnqPMtxZZiWwkrhZXSKr7C9LR5nW2ztN32uu0hHRH0KdJnKcFEE/qewjCddVnfnBarBMbq9wTOmEu6pjfEM0fjdSMuF2EM6CgVoa78pDaQHOE1h5ls5NUwucbm9JV4D0XXJFQ714RZSBiEW02q2Ququ18SRsJTZ3FegshWV3+jCQHkmFF9OfVgGHgiLSYtNs2b5kuLS4vvkxyICcQGvAFfIC4QnxOTE5vjzfHlxOXE5yTPSF4RUxpb6i31lcatiF+fXJF8PTm27dG2h9oeyIvN8+b58uJmxM7wzvDNiFsSu8S7xLckLvzOuvlu2s2ZkKmXQfq6bUbcnaswoezIpb1Lpz9XU13dp3bl3pPNNyl7ZXPewZGTjoz9z+sso7BowqzzB1KGNi/dXZj/7ktvH3UVr+7UaXdycpOuq0Ooq0rZjXgaTboHIqDGYjfVhHvW2KujNkcQl2tAuEVWI/sbOJF+w8j96vWq9Ni1tIN5sUtiK2IB+WyrxZBVahT2TgdDXpP1MgmuvPLMM6/orfmPPd4oOkVaWk4VvdGjpoZ1Pnn16klsbHhBvqgVv+KvNr9gF3JD9bV2uIpzGEH6BKJICV0p2UqsK7Uap1QTVq0XFC4rGejuhwVFfVtB4RA3rjl+vqbDa5QjaknU+qiKKE7vCMQZrYVFfGthAVeHvZDz5rFjb+a8MOy+HeOaxeeYKcmjXpIy96amXj59+nJq6u7ERBTIRl20R4JhW8iXNBY5dAT1FVlDbO4arq6xVdPNCIFEZQOcLnO/GKOmSE+/pa+63+hLT8ON6WRGchJK76jV4aXq6h5vLDrZQlpOLnqj+Thqbtcu1B4cZOP/0bCrIJ/2pSr++uYLT6sCW/kqRn25SRSZEUhETDaVqCu551XKayz0cHiNq9qyJjrKw1SPSoYwl71ftMFiXeuLhRsNwYWcG8GaPKVPzIyYiphPYq7H8D6kD+3D+nj6RPEOSme1s6mDNp1Mp9PZdM/0KNO4mbqK4wxgu122oQkohtoVqbhpv+X0W9OOT5j4yaPihjhOU5q+pko127FyS42NjR975HiXLvvad6DdqUZD6L3ii7rNB/Zt0zGmMyr8V9R1CBkTiOYOalFflWkp2WyTazUWohDFxFWr3TzUrRcTml5MmPViQn+Noh/rS2u96pp61dW5DJfWSyHHtXSXXmEcDHhyPBUeHaaRyRgaBLqEzAzdvdivVRPvo53FpzVVVfvelt3P5UyZuK6pM3y6btjhPbquRa40FnVtJu0QbRMiLDEmV0lIaI0dapISqpNrTTX2tyNjkiKIahkgu1y+finGWk7QHOrqgwYhzumazkKraL+kfUX733lRmIPdzhfupq2m4kJTCcvMgJd2lG3asWNT2Y5qIRrz9z7wwLbhfzqQtX/Rn5ua/rxof1Y1u/vDixc/PH7x4vfia/FdTOybHdq//c5DEydg6NRXvnpMmLhb1+8hzBkKDP12Qc83EbBRudTmrLZs1rDsIcP02NjfSF8Nx++lL0c5XYhb+/M8Rn2c4AyyjAcZxtpkqFRQvWhR2d6amuw357x7jFU2P8y2bd92pLK5VHY3b5tU8KPuQ+/i4AtwXH2dJRWzlSPSG6SWcapKpP+tdRasBdGT9Vwkx5SH+Qg3ajZj2eXdavyT8m5WyO7v2uTYivQ0jPYpqp6LKM5gJtK6+pKtIiBDLdlskjmVZPQe0t8cLL2dQTiuD7ujjGlbUTHKGYexovKIxDQ1lCWzFJ6q5jJEN3UWm8eXsVX8j+pGVs43qy8zl4mbZGYGTWkHyVI7niqnKgHLFMizrIIViG5r5XXKFtis7IZX+EHlA+Vz5Re4Dr9I16XIcTN1sHPqYKeXbIdqmP/75n3s0evNx2tkd9NUern5RvNeltD8Bcp7W3/xb5HNTJfm1jujgNXRWpbP4NexbDOUhgqT3f9oaNWVEoO2G0/GBpJklyncTuQYxWMpjfFBdVRthEMhTruqyjlO1Z4THY6hP8EoEZqwgjHenvTqVX/DKG51QwiEpCXmJM5IXJ9Ygb93Ei8ltiSa0DIMW/DcaR+3DcUTNJSUfkeXvX6k5sk563bWPDlv7c6amj5VCxbugVWL5v78tW42L27VzYZte+n5d15uLpXy9k2esCi4tmvYLcoQQrr+1m5r/7nd1rfZ7YE8z8ce9nvL9fxfLBeH1g03GGPnGH4fhn4fIte4SI2lWq89XfYHwOXp97t3cIGEPhFFpEguVorVYlOxVmwushRbi23F9mJHsbPIVRFxPcL529Xx37yqm7Vp756yjXv3brxOXeLa9b+JH6kTLl09ceLqtx8e/26r+FA0iB8woGZh3HQbX7EgnmNsqkQedXzqHYhqw6dq2xr6NtTGIDYNMFDqDkR31Ne3QVTAFMSoL2MlOs5/SzmtcP4bmJ9VU3MbzVn3Nozf1bxP1nbfgef0+zaQaps7GIz8OUlawC1jTuw0Q6mt2lSraDKmpf1deqg0/B+x6ewpHYwO5IRsD9FnLYjjt6csDAZ7B3XY+gpycmhFSKdoOOBynjzSvB8nrHAi58Z40zGPOI7jJZOrrfXgiNZycMTtchDzi1WSu8SzKlzPL/zVt+vB4VGqTVHd8f3a6Xyd/U09iDH8Zz3hcP22HmwrB0myHjgej9aizdGWTgiaHcwdLD1NPbWe5p4Ws4/4aCJrp7Uztw/p7O7saR/aLradN8WXEpeYXKKVmEssJVaXLgFjsiabwQJWsIEdHBABkRAF0VKMKblzSp+UR1KKU5akrE+pSLmeEo4p88zfF576y/7fF55dUXuwetiusatWTdjUp27HL38Z+/5jhcfyl62ZtCew59kv/1x4QOqzr127kSMDg+Js7Z9btfVgQsKRzMwxDwzJ8dsTy5Zt29v6nqUbGt1PfBv6ImZDNq7a4VXipLVqqWZGLaOlOVw23RcNIE5vXWENvrBDHHk9iCM6+rpDe+pYnJSpo7CTzqNFYsWQWW+/fe6l0lK+Tby3rrli1bAt2z9jeeto7+DXPvvQH0cbccBNegaib0eCNRqtdVdbMA64zcMwIvT36I6ZFbSr+vRb4WC656geDkIQvYIOeCsvS6L79HDwWnX1vW/MefdD+jE9xHY252/ffqSSFd2s2Fs48Trs0uW/G2NRsZSHdfDNQHIr4lDG9R0wGastLAjlbAbkHb1EZpRLRNHfkmu31j71V4H6O2v9dSAxXtT+90UyDfxxIFZ6eo1XwpawDaySqfpAJjAZazGRECklYYWcAimST80kmbQH9JDSVL1uGwSDpP58oBxQc0kuHQNjpBy1kBTSqTBVmsynyHnqHDKbFkGRNIcvlFeQFXQVrELkKpHLSTndzLbAs9KzfLO8i78iV6lH1Utqi9q7rU6jCXe/T8fT8e+LhxulvKaRsPdmhWEjuaiCTNSRhX6PtfSoYC09CmvpUXotPep/VEu/809qaV2LQ6qc+pt+l74J0TfmoCJ1zVL90xrjVbG+1nxLv//rEpwGWjgLZaE8XsvUBrFBvD9W2w+xh/goLUd7gj3BC7UFOBsLsOIuZc+xZ/kmrZbV8j+z4/Axj+HMBLJk5ppqNuHO4mERECpF8ig1yuQ2eyz6qlkCS4Y4yc/j5XjFryZj9R1nTrBkQVepq5ql19xsIPSXAlI2D8gBJaD2xXq7r1mvt/V5zGU50gN8uDxcyVFHmEZqo8wTSQGdxKbBJGkanyZPU54w5ZsnW6bb5pA5dAFbDPOlxTi/xfJCpViZry4wFZuKtLnmxZZStpJj/U02001sI2yVnuf6at1zaqBzuWW7bSfZSStZJeyR9vBX5VeVPWql5XXbn9gb8LZ0mFeb3rHVsffhlPQRX2DYRBTV/2mCmSbkVn9z5fw3V6rFhfN/++k8Wkc5TNPbzQoob5qm+xHilu5Hdro6cK+iMpOT2DWnWSPEbnPaid3qtFiJvrNZ0WgsTjSZbKvZ5CBmXgpv28y1+ndkmgktRbVLdrOjzTpUY97NbY7WVhfU1RnrsfoHVQ2t9f2/sgT+Q1i6bgPXZcJV2QTWUC3M6rAmWDOtg7T7tWHWsaax2jSt1LrEutHq0ggygbNstpntYdTDHJKDh2lus9sSaYu0J5NEjPo+ycdT1HYmv5ZoTrQkW9vb2tt9zm7oqZksTUrj3bWu5q6W7tYsW5Y9zXkPCdAAC0BACrTOfrapnzbAOsg2yB5wjiQP0AfYKMiRcniuPErJVR80PYgWMMoyxjbGnuMspIVsijbVNtWe5yxS59vm21eRp00rzCssq6yrbKvsz5nKzGWWLbYt9kpzpWWPbY+9yvmx85KzxTkJ55DbaPC1Sx9qrMOwjcM2Ldr42NCRGXGiZ9DZp3y4cMvAkpHSsKZN8JgelyV97Ux6yfgmIpGkkrsICcnUY3pcelhoGKbvoW4Ft7IiGwu/CZk8zpOR3q0rjUtKTkqleBbXtcvm4cPphzeXDvU+PJDSAWO99y29KT4aAdGCirX3hQknbXGE3bdW6AkxS2DQkDubemf2nTbb08Ez+9G+M5t3wfzZsxcOCqVjojyDfsj1dNDfw8e2jFRDlaFG3dGeEAyXGZnJCIbd8NeH4qZ1WSWYXSBy2rHwiqFSJDQ1SU0SlJ+UZVkNCesw+uH4xEvVk98dn//m/Y8tz8gaO7a7MtTo0tS8T4iNRV16eSIivam5YwP9hm5/r3tW7b39nxhz1/jMMbnLJm259b1q39uNRiOg4Z4dQEYt2PAYZmB7FnWJ96Q1hHBs8mzM39OwXSdE/RBlGEmI1g5bEyHmXhgvMQu0fEKINQdbJSE2JG8biK0aG/ax/0qIA6OyExXi3EuI6yjODt5zIw2PSkhoHSFhSDt8JSERPmx4LxLHjUrEhgVEdAE25DEGacd8SUgs7r3Inxfp+PKw4djxyF880k7AfSLylzSfkHYoT7uL+nfTyJBCxupfG0smlDvN+EJCP6YkFM+Cx4yotH/rMeD1Ya3HEh4XtB5zEk4Xth7LxE2fIfeS6WQGWUCeJFPJZDKFzCY+rNAnkhTcp5M0/GXg0QTs4SPZ2Gc2mYXtSTKJ5JPHSQe8Oog8gf074dE95DH8+cjwW7RmGWeTcD8Jn5mL2wLsqf0PRu16a9SRONJcHGsaPvME9tb5yMdn/ncj9sWjafhcLpmDPSZi33yD2iTjiXxDIh9SeQK3M7DPBKQ7Ffv58PnpOHq+ce/3dEYYVGYhR9Px9+i/uPvPr+YaHM5CutONUdORzwzMDe/s3dYXgsbf8pT+bfx//aOZpJqcxN+7ZDfZSnfimf6t9ky8UsH2kxVIqZq8T0/SVawjXttJrpMz2LOUnITdEqGDcdyT2P88Z+QGHUkOII0srI6yFFki0jDpgDRcqpauSqdJN2mWdFrKk2bRDHiJ5/Kd2LLgA+YiJ4iXVNNLyOsh+A4yoFbqK9nIJTgNu8kVHEVC+ifJOlJJipAXN51OijEdG45XjvPTZAv+puP903QbPYPcHaLLyTnyLEhsINlGz6FcJ8nfyXIYyYrR9DNYIfJ/HGmdxue3kFkS4eeoRgRLxWvIPY41wdjGQEd+zvhdJ8U48khSKWNZryTgKLrGdtL3aYO8kVSQM/AwzIQLdIWUIO2SBpJ1QQ1AHlmHtLfoz8iFdAHKrv+KdOpsnpRHd5PvpDxlAtL+QJcIxzzAhnP9W/labPNkB8rUk66AVcipfjeGnFYGS53xeaSgLEapCZkOmYZ1F5F9ZD/pCOVkHVIy5JW78b/jk1ulr1DmdXQt+zs5jXEuhRRK11DXmMTrX3y+1fYpeAefo4r5BxVUBR4Y7ftwTFzHDr879TkUXxXJqbIu8FW3tOSMlqL4mCoeXQV+tUryJ3z1r25+1bHDkJzRvqrmfn1bqfbL64vXRozGQ/0ML+P1fn2Ne/qgVdyP/4PyqnwTp/iedjyd0ONpx6QeHUk1WxJouSmg0Q3/8MOv6fBLOfzdBj8LuCHgP/3wkw3+Vg7X/fDj0/fwHwVcK4cfyqGhEb5vhP8Q8F0P+DYbrgr4Jh2u1I/gV8qhHjvWj4DLX3fmlxvh687wlYAvBVxKh393wxflcFHABRf8dTGcPwx/EfA5dv98MZw7O4CfWwxnB8CZz6L4GQGfRcGnAj4R8LGAPws4XQ6nTsbyUwJOxsJH6XBCwLEVTn4sGj4IhToB7wt4T8C7Ao4KeEfAEQFvC6gVcFjAISfUlPh5jYDqtzDHE/DWwXH8rcPw1hLp4J/8/OC4QAscDEh/8sMBAW+Ww34BbwioEvC6gH0F8JoN9u7x870FsGe3i+/xw24XvIpMv9oIuwS8ImCngB0uqBTw8ks2/nI6vGSDFwugArtUlMN2AdtesGD1By9YYOvzEXxrATy/xcGfj4AtDnhOg2cFbC638s0Cyq1Qhg+VlcOmjTa+qR1stMEzjbBh/WG+QcD6deP4+sOwfom07o9+vm4crAtIf/TDWgFrVnfiawSs7gRPo5hP3wOrVpr5KjesNEMpXigtgBLUVIkfVjjhDwKWL3Py5QKWOWGpgCUCigUEWp5avJg/JWDxYlhUAEUjPbzIDwsFLBAw3wbzLDBXgzkCZjfCrEZ4shFmNsIMAdMFPCHgsTh4VMA0ZzafNgKmCpiyGCbjSaGASQIKBEwUMEFAfg/Ia4TxFhgn4CEBYwWMGa3xMY0wWoMHQyP4g+mQK2AUjjwqG0Z6YAR18BHhMNwNDwwO4Q8IyDHD/QKG3efgwwTc54ChAobgnSECBg9y8MEhMCjGygc5YKAVBgjoXw79yqGvgHtZR35vI2QfhnuGQEBAHwG973bx3m64u5ed3+2CXj2tvFegxQ49rdBDQJaA7t3cvHsjdOvq4N3c0DXTzLs6INMMXWIhwwrpd5l5uoC7zJDW2czTrNDZDJ06mngnB3Q0QYd0SG3v56kF0D7Fxdv7IcUF7ZL9vN09kOyHJL+ZJ9nBb4ZEAQkC4u0Qh3LGucBXAN5GiEURYgsgxgrRqMFoAVGNEJkNEXgSISC8AMJQU2ECQvGh0AjwCHALCBHgwg4uAU6U1ZkNjsVgLwCbAKsllFsFWLC3JRTMAjQHmASo2E0VoLhBLgAJb0poAR7AqyAwajk46wjUAUQAraYFK9bS1P8f/sj/awb+278YzPFsdD1mfPo3Ss9hjhAb3MO/kULEa8LMMoAqMSZdJU+0fEaaW7TEvBSJ+Cw5hf0KSID4Wlpkt3DTLcrj9DIh/wdSsBIkCmVuZHN0cmVhbQplbmRvYmoKMjAgMCBvYmoKPDwgL1R5cGUgL0ZvbnREZXNjcmlwdG9yCi9GbGFncyAzMgovRm9udE5hbWUgL1NVQkFBQytEZWphVnVTYW5zLUJvbGQKL1N0ZW1WIDEyMAovQXNjZW50IDkyOAovRGVzY2VudCAtMjM2Ci9Gb250QkJveCBbLTEwNjkgLTQxNSAxOTc1IDExNzRdCi9JdGFsaWNBbmdsZSAwCi9DYXBIZWlnaHQgOTI4Ci9Gb250RmlsZTIgMjEgMCBSCj4+CmVuZG9iagoyMSAwIG9iago8PCAvRmlsdGVyIC9GbGF0ZURlY29kZQovTGVuZ3RoMSA5NjA0Ci9MZW5ndGggNjc1NiA+PgpzdHJlYW0KeJzdeXl4VFW279pnnX3q1Km5UlWZayBUIjIkJCQQBikhjEEISGNQggGSYlAgkiDSGAnd7dja4kUQ4gBBGlERY6CRISIqTgyCGrSd2vYaW9suaJ8PEYtk565zKgxt3/7uvX/c733fq509T2v/9pr2CTAAsEEDIJTNq55VZfuR7aeWTykWzaMG+/OmNQDMQ/WecxbOqpGux71UH0T1lnkL627bMc5+guoxqt+zcNZtNcpcpS+AVEf14Nybl0dHhsZQWXoYwNvn5sVzZgEbS+OTD1L//YtmLazOdZfMAkiZQGP8NYtr67pWwfUAaTfr8+fcWhe8am9/ler3U//UaM3chTdIN30HkPFn6t9es6S65vZZm78GyKR+01OTrs3NL79u0FqA8ADqnwj62aSZeyiFGx1Df4CAqhfh/Vd7/3Qh/3FJxw7HMXM1jdU7mTGAUtNCkQngDP245Kd8x7Hu9ku/HsYoNNqtIMNWyv3gpBa7TjlcDSUwGsbBNTAFpsJ8qIFb4ZddnV1dxmy9f6TRP8HonwU3wxJYnujv+veuV7te6TrYdaCrtWtP14tdz3Y93bWtayv0/Ccq/vknEQUycFDAROcxgwYWos9GVDkA5GMQNQa9DaD0B41/DlFlC7TyZdAqxSlPhVbTWWhVelF5KfVHoZzvg+3yASOfwRdCOa6AHKPcAtulA4moToNn9DpfmRiP39Oc12ABvg/p1L6JD4QAxVR5G8xQv4F84xw9cCL0htuIOolwi+jo81NSiHKZWqJinRzlW+g0Jgi0gsyyaYrCsnczld8ryZB7qC3WH5xtsbZYXpIr5AqHXKGoDB21mN7xlVhnsp/7fgkdw0BEo5k5/CStpIGIRCBT4aZMReFapqpxhZv1spQpI6OoZqKMWqZZk2UMazLsUOUHFEkzqyYuSwzMClqcbS0BZBWHkov7Q+7Q9raYuziv9Npyk1M9ZXLyU6bEn1661DK9B4s0pGi52jQtqq2ElWylutJcp92nbdRepnCcwuea061mmAPWsHqlOWgdJ4/mY9Sx5nKcLk/j1ynzcD4BMleptNbBCvZLeSlfodaZ75Xv4nep95o3yOv4WrXRvFvdaz4Cr7PXpSOmV9Vj5o/gA/aB9JHppPqxObcCKlgIQ8wIKI/u3HqjqJd6sSNSL1HfuY1tOMqc4jt+Mt5bCktTEnIQ7fqSz+DfQSoMiaRZnWbwoc2Z6tuBzh3mx3B9WlJfKyi905wdh/L1+2g/E3OKQ87XXe5i+svblZt+Y7rEKsJ2lhUElxNC+b5kVz+W1UPyetwF+UUD+YwFf6kXvxUT2C62tP4vC246UftOLPZO7YmbpgwcxJpYNYuypkEDxZFxJeLcN1+LcyXjjDttpTstpjtVIRn6Rbxw0PwAO+hTJZ8GvK+zH/jMmOLsiHXEXMX0o4uKnYnltZSlMlbBXLRx4YDsLBYKysk+t9cjmRS5F5OG3Rf76dypzh/YOjaVXbNsfjQ6/zbRTGGB3NJxy7ef/+kbljWrrlqce+pp8WN13SwdH6JDPkJ0mKF3xCUfVA5IB+EBlTMT+gA1oiC/7RDtfkbffrfTUmapsRDvJBG3JkKr7OhcIs3t3CBW8pPiM/Gt+Kzz7sS60jBaF6HPi/CKxGg12dlR2uyYWtrsnHpDOelgiAyaHtNPlsd2O3mEl3E0Fm5lq/TF4r0v0PcFrcMhOWLRaZOZDwEVujB9bjtJT4IOtkracv4TfvL8RPFZYp5Jx9cK70QKsMikmqQiJql6hpJZM7MiTTOHNZOEoCK8YuFmFSXGNaW/rPUH4pEOkkxXQkRi+YeolJCRC1KRkIzpPVocSFcSiZolh+owBySvKaAGzF6ttzRcGitNkiaYJmjXSdNN07V50mLTYm2ttFpdbX5aajY1a2kyk0nf+lgqOu1XsjAOZoU4iUVwjDpNnW4us89jc7FaXWCutN+p3md+RE0hCUgKmZl+YpbF9FN/S1c9jf1VPCy2nRHbxBp+suMLDMR7yyUdn2D4fOsFXgOD1zLg7kh+epqUkZrpS/alZCYn+8KpPi0JdpiVHdYHkjVfUgo6M1IVkG3EAclOs8lnwcwEGxArJhd3Q3KG0Cl2F18GCj9FXYRHSoaORyA9JT01LS09PS2jyFvkG+Ud5ZvmneYr81d7q32VfkcFM1i4N0uV6DBB2etRTH6WnBRCEi7p8+Xz5y/fLFZKE1gOS3pw9aT6yLsiunvgLTNx+PVzo+VilTjbSVz7xgcPH+jrXrlKlLPamimGvJOu5AsMXVkQsSsJhhlEvDyIq8620maN2M9usB832K8jZjB3fh5riZgv8bXGK4VLrBROnafPT5RbDJkt7/pSfl1eQXuE4fHIlWYFUgMWeC/5uLLRfsIVPBo4krEx67BrvRWykjHFZrZZhgXQ5hmSTfgdok3yXQlRbj/TQWrmL6fPni5OqJnIhNycYcFhoeE51wSvCVUEK0KLgotCdwTvCNXk3B+8P/R48PHQc8HnQi8FXwp58/15gZH+SOBaf1lgjr8ycKe/IbDGvzqw2b8p0OJvDjgrSEX0ULweH6mJYSzsChWS9uqRXTigZ6hAVxs9FJPrKlYQlKXNNbfcMLn6PjZfPDJ216odHzEH6/H+Xb+rfeMXtd/UsVxmY+cmjC+55qGFve7uXLU1WnFk8+t7Mn4xqV8/5srI/LuByXbSrzMVD1npDBgUSVearCcc0ORd7zic/mTKiVQc4Y6kWM22NKfBRPlnEkLbbpw/b9ckf6VfV68JfWYQjURavq5qieQcL90FQu3y5bW1K1asGL9r6XGmibPHl+4aLxpZ9OttmzZte3rjxqelk7MrxIuik8KLFbM3K56E7tfvq5DuKxUGRtLgKGuT7UdtbVqTS25KpmtKM42wgWfIP+r+M6edPxBhN6av1PU+M3R9N5YDXZdZAblw3JpJ6556at3URyJTn7tOvCueITnMLX9WHiY+zc97/vHHn8/vLz4JBNhA5qUwMJCw5TMILzsR6EzgldwEJ+yuJn5CXW8/zJ5Ejww2KZI2wjJEx0vnGV1Btp9pjznbL+HFdB41blWnpiDfh5cDuJXNE+vHtyw9Ls4y7Xjdzs06fkuWL8dWqfyn2OY5M9g4hhTGVXS8rSOoxwv8zTXCywPpMDISAu8fmfmo2sY3WtmHKRvdh63rM9K9kuq1QYlkcwzJMCg8RAycAK/dQO+0bjUzh2fqZHpDfvaPwAWBJziQax37bY/df8up+pUkZyfE86yU9WAqGyZWL6uc9yunVBC9446RJSKW158VsmTmZoPFq2ui9UsXJe42BwAfIByTYHIkw8mZVW1S2EZ40q7s1KQkE5jM3GYb5bB4nJ+WNtsuSr1Fl/qhsY6hhw65E7KY3xFzns7XVRmL8AZTgyoR3URhJkvogiwXCU1RAT7wxoShLF+8IxpbWo5+oHj+NrBkYhd0bMJKBhNffN64WzGa2+XldLNXQGkkO8OB4D3qa0vd6M4ksTAftu3ManJ/DCcwmzxae8QbHKEM6XVBN+i0tCduWvxw2qUTtGvSlZVXdnsfPaBwgH7TyYUhHUeSDZeBbSFeYE9iANxK135MnGHWY0tbxhMbPCta5x+aM3PXDc1bYotvv6225vbbD8yewUbGz7OrZ8zZ2uES34svgyGWXFTYuAWVLesaN25Zu24L4bud9Kib8E2GwkiKTQamfeI+4T3sfNLOJBuMcdlsDqfuoCQkemiM6Na1W96uytSG1ASLFroGZOck5MdAUhdsH3eL9Tand2y/mgZdhCfvXvTq29IzndMWsw1rFqVl5Ty3ofMjxdO5bXbF6YR+oUSnQ/eje5FG/wx2yk9KnBFJTtXZQba52zeKmMvMleYac4NZvqTHt7OovotolCvPb1I84lODdy6t6X8RnpSYDZzkmyRcEVqGV/Ia3sC7lzGWUDw/xRJ89wz5FbtIRsIwP9Ir3es2yyYIKqYUxyfBE1l42L8zg5jP47aqNmWsx+YeG0i3ZTqzE54P8V+mzn8dQ3XHrtgQnNyhQ9vPDG2PkW1w6caVuNARyMnLKcupyWnIWZ3zfI6pghnIeQ0ILyjwkCsh7/opvUa/vK9kf80rh8V6xkZPjC6WxPrIlLk1VJ034tm5dS24dd7C0192TpPG2jLSlt20bWPnx9LYfTc9/XjnR3Lllhsra+ACNtxN5/v5ve/8z++9/b++d+9/ce+PPnTh3omOxLV366IcosNClPSNJClNbmiyHnavTzGPcEzAEd4hCe+4W22fzts9MGl46pgkvMz26QJxQetQKr2yrL5+2dLbb19Kz4dRYr/4XPxJ7GVjcMWzTU3P6pGBeEvEKLzFBjEPhUEJWraLaXwm0aLr7CGRzEs6+7B9PfsT7swkfR0xNPdlVs7Z3v5ztR2+CEe3fUu6jFTJreNymYnbQiavllT35s5dirblMgOHA3WbZ2ht474wRrS5yMb5XHazYgI8aHnYfti806QpNlCdbp35knTmU7sODpp+yJAYIq3jkMtwPtjPryoZY7kVufet1ekZtbPefeUVmOvzvvD7zg65cs+iauQ6nywg2zqb9s2BTyMRm1WyW4r8AT9XTKqZy1pRIOAPaxZ/QPYadtdz1NuWstElbwyT3b3Cr1kC6Sa4Nn2K3WMq63HNFboObIu160qvuPiCJf5Bt8TuC363XXcvuzPyMlWL7mXONJvJlbdYrBab2cGz0qxptjR7iqOP2s/cT+tn6WftZ+sVLFaHmIdoQyyDrYNtpebx2njLeOtY2zLrMts+dZ95n7bPss+6zxa2K3aTXbWb7ZrNMtA2vNeNvcz6e/OiAxBgss/rkS/TvAOycwk73TiQ8CXXfnBjdE7prOEs6YA4J+KLT9Xf9EXd/AXjFg7/+8EzHXM+Jr/gu7y8gsLe/SzmrE3P7tyVlcWcAwYMLs7Ltan+zb9v2e7XcU2n+9zCnyC7tjCSZueqA5tcbKfaBJpqMUtmYjKn2z7Vc9lTqrTZpSduw77ZDft26JJ9OxQ7M1RXLrpiSRkOw5Mk/ROLw+lwlUllWOatlCoxcVBdYXp8Q6hAvqJu9lxSAbtFPHjVjD3iWNsLLS38CfFqF4jwxIFd8EIb+4QBu8rQGZtIVhW50nhjp5PRY9ZPPCeS1zvZTrciQZLD5hpDusOZlvBoEmqvPf+S5khv0H2tJOI/Dz2rfckJeC/6ONmb2DzJ7vKNId2hK+PJf1j46hG2S9pec4M41e/uZelZ2ds3SL3Ob9psaA8G5GrxVKJHgfcjOZgpc1nKZBLXM5QUUFgmPYjC9PL7nCtcf/XJYHK27dpIE6XuzyKx/H/x3mOR2wlAyUkhKOVJEWmaFJUapNXSJqm5OxykcILCn43wHYVUDmRlUCNpSGIZmCZnQ0/WG3vJRTCAFWOxnKeOhtFsHI6TK9XblLvZPXg3v0dZB+vYBtwgr+WNyjbczfZizwsPQBYKsCi5RT42V4wRy+TKjjgq5zcldHgq+SFuOr+DFUVGOjLBwWWL1Yb2TILg53A4aBTB4QjbNcnJQduofoyw0+mwW8yqjg1xnPPCw8mt85nn0uPdgMpJN0kXSQ+Z/xQxPZLgqgZ0Wy5BF5HKpGqpTrqLwHvYgG/XZfC1dcOXIjMzck7XZOKqj3m5V/GaevKeSk9TIRTSw65IKTIV24sdo2AUG4/j5VF8lDJXqlbuku7idyn32O6xPyKt4+uUDbYN9m3SM/iM/LT9accfWCvukZvNzdrLlr22vfa3pCO2I/a3HR9IX0nFFyG2s96skDhxOEslrPsSu0dFrzEvPXq85oZrkzW5slOVzp0v2r/2b4NLAwbuMwDk0YS7Ge6NpKmZEkqYaVJNYZLendjEVQSZgaIRqwUUh0KsdlQXzG7/5bKHtA5VyXRpmmm+FDUtl241/ca0xqSS1KpORugheSvcacnGXmqupVgdbhmv3mj5nfqE+gK+pB7E4+oJ/Fz9EmNqqnEUr+7IFrLQUny3s0Wa2OGTJnYekSvjnY1dEJei5AXli3Z1t2kelULk14BZJp02nOm+Rs8Lj0VyPzyKgyDJlEgu5Tp5xPkD5w/IZ68sicwkN/hMxVvLo62Tp1UWFkcWjGDzTfOM7hHivb59S68qnv7o7JffvOJK8caIkZNG95sxYPDslVP6PyweY1UXv/tOuBTZPWR0gxTpVYLUhGMpNhC295Nc30axjcT3G/LDfgOgplDcRTRXUjwDoB0EsNB4y2MU/whg3QNgi1A8CmCfR/HPAE4y605y51y3A7gpJtE8z3b9+z5tZoLr9a/ispnoyNM3N8oMfFRLlCVQ2ejuMkKQTewuy1Re1F3mkMJ+111WwM+2wkhYDDWwHJbAfJgL86AOgvRKmAO9KM+HPAoFVJpNI4IwgsbUQS3FJVANs2Ah9KHWcbCIxvczvr3fTCEIUy6uVWvUqimvpjm3UlpFI7X/xq5FF3fVv+LfSnstoDmLaLROxyya8z/bsYRKC2jeNFhKI+bQ2FnGatXGjFnGiYK0yiJKa2jMbFp3Po0L0vzFtPsso+/n61xrrFJLFC2m8VX/ojf43+6fZlBdS3stNijJJ9oLYOA/zLswq+/FWQl2pF/XHfr/eP75R2qigEWhFf5K5WGwGeIY0FUeRKlVz7ezqRCj/tk0cpV8J5tK+UKZ1CL1r5SP6d4mKyBMbqFSWN7CWmEffEWzV7EH+Fh+vT66+78kEpzlr7HveLFUDOXyQnmY3CKvkltoxFI5Kq+CZkqLpXflx+QV8nHy08p1ytgEPRqvqkY2nmVBo9TISki9lUjH4BWD/uGskQ3hR/gROAknWRmN3A7LJI29yb5nuayctdCss3CWBahWKBWy0+xrong9vIvlXINGeJC5qdYKx4jur+B7qKUnRBQe5Cel3vwkvAZfwIfUDrCASZRmYl9+ksJ3sI1wj8EXZJdOKh5TSI5K5yDGfi1tlc6xLCZRcLMAoTkTj8mV8pvyvdRL6DAJCzCAIyidoY/gJ1kjUfGFEmXLaZweVtA+Mek1aQ+d8QB8Ruei3aUZ0gqpET5jO9g+ohjgTrZDrjTNltOhUWmUy+G0jg28Kx0jPMoMPH4Lv1X6w1lZge9wAquUt+mIQZi/QoYhZBqvuMlajzf9GnStNRD0rygAbzPgryQCjVKVTFgn5+ATRLsk1V/AjS2HY1IxzobHjLCG7YE1sIc4j5bA7BdNClltiUGfoLNZCo+rao5MLg++NT3Ut8/PqkGnKdgMZc225cE9XV1l5XI6n97MM5oxrDbL4awv/lXnF337lJaVB/ewK0aVdC87qrKEGq8tp6Jeo2ZqH1Vi9Om7NvMw/Y2rbA7OmRe8z3lf1uD7nNWD+179G6mBZYMAZGF6kSDryUIwGDjrCXGqZYGP0h7dbT2McXoZWdDoD8B+Sv0kaUg+id6bQT4lsnTwU5pmtKQaaYqRJhupz0i9zEPOL2deo6aXkSUZZbeRku2Ceup3GDW9jMzGrHA/tdmMNhscJPtsZRaYTm16D1LaQG0WpkE2tek9SGmE2vQWZGZjpmqkJrAaqT5DaXmkH786iSnGubiRysYoNE4kGS3MSCHSVY9dV6EQ2HG+D+8QeL4PxgX+dG4M/6kez43BH+N4VuAPAs8I/L/78XuB/0fgdwL/7sfTAk/FNH5KYEzDWET+27ca/1s+fqvhX+P4zUM+/o3Ar+P4lzh+RZWvBLYL/FLgvwv8QuCfBX4u8E9x/OzTFP5ZFX6agp9s8vNPqvDjj8L84zh+FMY/vhvmf4zjhx94+Ic+/OCkk3/gwZNObHvfwtuC+L4F36MR78XxXVr/3TCeeNjKT2Th8Xc8/Hg2vnPMzd/x4DE3HqXuo5l4xIOH397PDwt8+60K/vZ+fLtBfivS9WaYv1WBb0XkN8P4hsDXq/DQaic/JPC1DHxV4CsCD748mB+M48vPpfOXB+OBl9L4gXx8qdXFX0rD1v0O3urC/fusfL8D91lxL222V+AegS96cbcb/yBwl8CdAluS8YVUbPbh87TO83HcQdmOOD5H459Lx+2Uba/HZwU+k41PC9wm8CmBWwX+XsMtAp/cbOdPCtxsx80RuYmAaorjJpqyyY8bKdsYxyfo8E9k4OMCH3t0P39M4KONFfzR/fhog9z4YJg3VmBjRN4gcD1xx3qBj/TDdTRxnT/ShWtp6togPmzFNdS0phT/jbJ/E/gQ4fCQD1c78cEw/k7gAwLvF/hbgfcJvFfgPXeH+T0C7w7jXQLvFPibfPz1OvyVwFUCG1JxpYZ3CKwXeLvAFXH8ZRyXC1x261a+TOCtW3FpXTpfGse6dKyN45J6vEVgzeI+fHEfXBTHhXG8OY43CVwgcL7AeXOsfF4+zhUYzcfqKo1XC6zSsCoiz5mt8TlWnK3hrEovn7UOK5mLV3rxRg1nCqwQpJldfIbAG65P5zcIvJ5q16fjdIHlcbxO4DSqR7qmCfyFwKl+vNaDUyan8ilxnEwdk1OxbFIqL4vjpIkuPikVJ7rwGj9OKPXwCV4sHe/ipR4cP87Ox7twnB3HxnHMaA8f48XRHhwVx5KRdl7iwJF2HHF1mI+I49W05tVhjAx38IjA4VfZ+XAHXmXHYUNtfJgPh9pwSBUOFljswUECByZhUWEaLwpj4QAPL0zDwoPyAM3GB3hwQINckG/lBR4siMj5Vuyft5X3F5hH6+dtxVwr9kvCvn0G875x7OMN8z6DsXcVXlmFvQRe4cWcZBfP8WN2EMN+7JlFAPTu6ccsF/YAG+8Rx5ADQxE56MGAhn4/Zmak8swwZjiSeEYqZuwhnfGQnG7DtNRSnlaPqbRpaimmCEx2oY9288XRS23eMHqqMMmFboEuqrsEOqvQYXdyRxI6Dsp2J9obZBv12OJozUcLHc3iQ0uDrNFjNSKbBaoCTQIVrnFFINeQR2Q5jlhFBsnFJUHay8aZC8GGbA+ruvMB1vv/jx/8vybgf/FHz30mr5Va6ZUCvIBvoKo/keOHEJXIG5IsKqIiS5L8NSzqeg86u7Selb3ogWMti46qIn852NWleISHNZoWsi8B/gOwT5X4CmVuZHN0cmVhbQplbmRvYmoKeHJlZgowIDIyCjAwMDAwMDAwMDAgNjU1MzUgZiAKMDAwMDAwMDAwOSAwMDAwMCBuIAowMDAwMDAwMDc0IDAwMDAwIG4gCjAwMDAwMDAxMjAgMDAwMDAgbiAKMDAwMDAwMDI4NSAwMDAwMCBuIAowMDAwMDAwMzE0IDAwMDAwIG4gCjAwMDAwMDA1MjMgMDAwMDAgbiAKMDAwMDAwMDYyNiAwMDAwMCBuIAowMDAwMDAyNzQ2IDAwMDAwIG4gCjAwMDAwMDI4ODggMDAwMDAgbiAKMDAwMDAwMzI4MyAwMDAwMCBuIAowMDAwMDA0MDIwIDAwMDAwIG4gCjAwMDAwMDQwODkgMDAwMDAgbiAKMDAwMDAwNDQ0MCAwMDAwMCBuIAowMDAwMDA0NTg5IDAwMDAwIG4gCjAwMDAwMDQ5ODUgMDAwMDAgbiAKMDAwMDAwNTQ3MyAwMDAwMCBuIAowMDAwMDA1NTQyIDAwMDAwIG4gCjAwMDAwMDU4MzUgMDAwMDAgbiAKMDAwMDAwNjAzNSAwMDAwMCBuIAowMDAwMDE2NTQ4IDAwMDAwIG4gCjAwMDAwMTY3NTQgMDAwMDAgbiAKdHJhaWxlcgo8PAovU2l6ZSAyMgovUm9vdCAxIDAgUgovSW5mbyA1IDAgUgovSURbPDMxZmRhNTUyNzJjZTI2MDdhNmE5OTAwYTBhZDM3MTRjPjwzMWZkYTU1MjcyY2UyNjA3YTZhOTkwMGEwYWQzNzE0Yz5dCj4+CnN0YXJ0eHJlZgoyMzU5OAolJUVPRgo=','2026-11-19 08:55:11','sent','bonghanoylovely9@gmail.com','2026-11-19 00:55:11','2026-11-19 00:55:04','2026-11-19 00:55:11');
INSERT INTO `loan_agreements` VALUES (3,10,'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AAAAAAAAAAAAAAAYO1//vQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAIyB7x9QSAdjCmVuZHN0cmVhbQplbmRvYmoKMTMgMCBvYmoKPDwvVHlwZSAvRm9udAovU3VidHlwZSAvVHlwZTAKL0Jhc2VGb250IC9TVUJBQUMrRGVqYVZ1U2Fucy1Cb2xkCi9FbmNvZGluZyAvSWRlbnRpdHktSAovRGVzY2VuZGFudEZvbnRzIFsxNSAwIFJdCi9Ub1VuaWNvZGUgMTQgMCBSCj4+CmVuZG9iagoxNCAwIG9iago8PC9MZW5ndGggMzQ1ID4+CnN0cmVhbQovQ0lESW5pdCAvUHJvY1NldCBmaW5kcmVzb3VyY2UgYmVnaW4KMTIgZGljdCBiZWdpbgpiZWdpbmNtYXAKL0NJRFN5c3RlbUluZm8KPDwvUmVnaXN0cnkgKEFkb2JlKQovT3JkZXJpbmcgKFVDUykKL1N1cHBsZW1lbnQgMAo+PiBkZWYKL0NNYXBOYW1lIC9BZG9iZS1JZGVudGl0eS1VQ1MgZGVmCi9DTWFwVHlwZSAyIGRlZgoxIGJlZ2luY29kZXNwYWNlcmFuZ2UKPDAwMDA+IDxGRkZGPgplbmRjb2Rlc3BhY2VyYW5nZQoxIGJlZ2luYmZyYW5nZQo8MDAwMD4gPEZGRkY+IDwwMDAwPgplbmRiZnJhbmdlCmVuZGNtYXAKQ01hcE5hbWUgY3VycmVudGRpY3QgL0NNYXAgZGVmaW5lcmVzb3VyY2UgcG9wCmVuZAplbmQKZW5kc3RyZWFtCmVuZG9iagoxNSAwIG9iago8PC9UeXBlIC9Gb250Ci9TdWJ0eXBlIC9DSURGb250VHlwZTIKL0Jhc2VGb250IC9TVUJBQUMrRGVqYVZ1U2Fucy1Cb2xkCi9DSURTeXN0ZW1JbmZvIDE2IDAgUgovRm9udERlc2NyaXB0b3IgMjIgMCBSCi9EVyA1MDAKL1cgWzAgWzYwMF0gMzIgWzM0OF0gNjUgWzc3NF0gNjcgWzczNF0gNjggWzgzMF0gNzAgWzY4M10gNzMgWzM3Ml0gNzYg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','2026-11-21 03:57:23','sent','bonghanoylovely9@gmail.com','2026-11-20 19:57:23','2026-11-20 19:57:18','2026-11-20 19:57:23');
INSERT INTO `loan_agreements` VALUES (8,21,'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L0Jhc2VGb250IC9TVUJBQUIrRGVqYVZ1U2FucwovQ0lEU3lzdGVtSW5mbyAxMSAwIFIKL0ZvbnREZXNjcmlwdG9yIDE4IDAgUgovRFcgNTAwCi9XIFswIFs2MDBdIDMyIFszMThdIDM1IFs4MzhdIDM3IFs5NTBdIDQwIFszOTBdIDQxIFszOTBdIDQ0IFszMThdIDQ1IFszNjFdIDQ2IFszMThdIDQ4IFs2MzZdIDQ5IFs2MzZdIDUwIFs2MzZdIDUxIFs2MzZdIDUyIFs2MzZdIDU0IFs2MzZdIDU1IFs2MzZdIDU3IFs2MzZdIDU4IFszMzddIDY1IFs2ODRdIDY3IFs2OThdIDY4IFs3NzBdIDcwIFs1NzVdIDcyIFs3NTJdIDczIFsyOTVdIDc0IFsyOTVdIDc2IFs1NTddIDc3IFs4NjNdIDc4IFs3NDhdIDc5IFs3ODddIDgwIFs2MDNdIDgyIFs2OTVdIDgzIFs2MzVdIDg0IFs2MTFdIDg2IFs2ODRdIDg3IFs5ODldIDk3IFs2MTNdIDk4IFs2MzVdIDk5IFs1NTBdIDEwMCBbNjM1XSAxMDEgWzYxNV0gMTAyIFszNTJdIDEwMyBbNjM1XSAxMDQgWzYzNF0gMTA1IFsyNzhdIDEwNiBbMjc4XSAxMDcgWzU3OV0gMTA4IFsyNzhdIDEwOSBbOTc0XSAxMTAgWzYzNF0gMTExIFs2MTJdIDExMiBbNjM1XSAxMTQgWzQxMV0gMTE1IFs1MjFdIDExNiBbMzkyXSAxMTcgWzYzNF0gMTE4IFs1OTJdIDExOSBbODE4XSAxMjEgWzU5Ml0gODM2OSBbNjM2XSA2NTUzMyBbMTAyNV0gXQovQ0lEVG9HSURNYXAgMTIgMCBSCj4+CmVuZG9iagoxMSAwIG9iago8PC9SZWdpc3RyeSAoQWRvYmUpCi9PcmRlcmluZyAoVUNTKQovU3VwcGxlbWVudCAwCj4+CmVuZG9iagoxMiAwIG9iago8PAovRmlsdGVyIC9GbGF0ZURlY29kZQovTGVuZ3RoIDI3OD4+CnN0cmVhbQp4nO3P10qDQRAG0I9o7L3ErrHXJPaS+P7P5fDfCd7lQgLnwMw3C8sym4yp1fSpqulmamemydnMZb5yIYtZynJWal7NWvX1bPx6YbNqK9vVO1U72c1e5X4OcpijHNd8km5OK89y/scGF7nMVa5zk9vc5T4P6aWfQR7zlOe81I3XvOU9H/nMV52G434ZAAAAAAAAAAAAAAAAAAAAmDij/14AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAmwPcPpVcHJgplbmRzdHJlYW0KZW5kb2JqCjEzIDAgb2JqCjw8L1R5cGUgL0ZvbnQKL1N1YnR5cGUgL1R5cGUwCi9CYXNlRm9udCAvU1VCQUFDK0RlamFWdVNhbnMtQm9sZAovRW5jb2RpbmcgL0lkZW50aXR5LUgKL0Rlc2NlbmRhbnRGb250cyBbMTUgMCBSXQovVG9Vbmljb2RlIDE0IDAgUgo+PgplbmRvYmoKMTQgMCBvYmoKPDwvTGVuZ3RoIDM0NSA+PgpzdHJlYW0KL0NJREluaXQgL1Byb2NTZXQgZmluZHJlc291cmNlIGJlZ2luCjEyIGRpY3QgYmVnaW4KYmVnaW5jbWFwCi9DSURTeXN0ZW1JbmZvCjw8L1JlZ2lzdHJ5IChBZG9iZSkKL09yZGVyaW5nIChVQ1MpCi9TdXBwbGVtZW50IDAKPj4gZGVmCi9DTWFwTmFtZSAvQWRvYmUtSWRlbnRpdHktVUNTIGRlZgovQ01hcFR5cGUgMiBkZWYKMSBiZWdpbmNvZGVzcGFjZXJhbmdlCjwwMDAwPiA8RkZGRj4KZW5kY29kZXNwYWNlcmFuZ2UKMSBiZWdpbmJmcmFuZ2UKPDAwMDA+IDxGRkZGPiA8MDAwMD4KZW5kYmZyYW5nZQplbmRjbWFwCkNNYXBOYW1lIGN1cnJlbnRkaWN0IC9DTWFwIGRlZmluZXJlc291cmNlIHBvcAplbmQKZW5kCmVuZHN0cmVhbQplbmRvYmoKMTUgMCBvYmoKPDwvVHlwZSAvRm9udAovU3VidHlwZSAvQ0lERm9udFR5cGUyCi9CYXNlRm9udCAvU1VCQUFDK0RlamFWdVNhbnMtQm9sZAovQ0lEU3lzdGVtSW5mbyAxNiAwIFIKL0ZvbnREZXNjcmlwdG9yIDIwIDAgUgovRFcgNTAwCi9XIFswIFs2MDBdIDMyIFszNDhd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','2026-11-23 16:13:22','sent','bonghanoylovely9@gmail.com','2026-11-23 08:13:22','2026-11-23 08:13:17','2026-11-23 08:13:22');
/*!40000 ALTER TABLE `loan_agreements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loan_dss_evaluations`
--

DROP TABLE IF EXISTS `loan_dss_evaluations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `loan_dss_evaluations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `loan_id` bigint(20) unsigned NOT NULL,
  `recommendation` varchar(255) NOT NULL,
  `evaluation_type` varchar(255) NOT NULL DEFAULT 'initial',
  `criteria_version` varchar(255) NOT NULL,
  `evaluated_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`evaluated_values`)),
  `income_evidence` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`income_evidence`)),
  `income_verification_status` varchar(255) DEFAULT NULL,
  `failed_criteria` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`failed_criteria`)),
  `missing_information` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`missing_information`)),
  `evidence_hash` varchar(64) NOT NULL,
  `evaluated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `loan_dss_evaluations_loan_id_evidence_hash_unique` (`loan_id`,`evidence_hash`),
  KEY `loan_dss_evaluations_loan_id_evaluated_at_index` (`loan_id`,`evaluated_at`),
  CONSTRAINT `loan_dss_evaluations_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loan_dss_evaluations`
--

LOCK TABLES `loan_dss_evaluations` WRITE;
/*!40000 ALTER TABLE `loan_dss_evaluations` DISABLE KEYS */;
INSERT INTO `loan_dss_evaluations` VALUES (1,1,'Needs Review','initial','dss-loan-eligibility-2-income-5000','{\"consumer_verified\":true,\"verified_membership\":true,\"late_payments_last_six_months\":0,\"water_bill_outstanding_balance\":0,\"previous_loan_outstanding_balance\":0,\"blocking_loans\":0,\"overdue_approved_loans\":0,\"active_water_connection\":true,\"account_status_recorded\":\"Active\",\"account_number_recorded\":\"15\",\"meter_number_recorded\":\"MT02\",\"membership_type_recorded\":\"Member\",\"membership_verification_status_recorded\":\"Verified\",\"application_status_recorded\":\"Needs Review\",\"declared_monthly_income\":2000,\"verified_monthly_income\":null,\"income_verification_status\":\"Needs Review\",\"income_ocr_status\":\"Unreadable\",\"income_ocr_confidence\":0,\"disconnection_notice_history\":[]}','{\"ocr_status\":\"Unreadable\",\"extracted_name\":null,\"extracted_income_amount\":null,\"extracted_pay_period\":null,\"extracted_monthly_income\":null,\"income_amount_candidates\":[],\"missing_fields\":[\"Applicant name\",\"Income amount\",\"Clear pay period\"],\"ocr_confidence\":0,\"confidence_basis\":\"Readability field extraction only; this score does not verify document authenticity or income source.\",\"message\":\"OCR could not read this image. Staff can inspect the original proof and enter verified details.\",\"applicant_name_match\":null,\"declared_income_match\":null,\"comparison_status\":\"Needs Review\",\"declared_monthly_income\":2000,\"trusted_verification\":{\"status\":\"Needs Review\",\"verified_by\":null,\"verified_at\":null,\"verified_monthly_income\":null,\"verification_notes\":null}}','Needs Review','[]','[\"Trusted income-source verification\",\"Proof of income: Applicant name\",\"Proof of income: Income amount\",\"Proof of income: Clear pay period\"]','b002ee3e702fa1457001049d9b9bdd8e031be739c417ac37f705d5a32993453a','2026-11-10 21:53:46','2026-11-10 21:53:46','2026-11-10 21:53:46');
INSERT INTO `loan_dss_evaluations` VALUES (2,1,'Approved','reassessment','dss-loan-eligibility-2-income-5000','{\"consumer_verified\":true,\"verified_membership\":true,\"late_payments_last_six_months\":0,\"water_bill_outstanding_balance\":0,\"previous_loan_outstanding_balance\":0,\"blocking_loans\":0,\"overdue_approved_loans\":0,\"active_water_connection\":true,\"account_status_recorded\":\"Active\",\"account_number_recorded\":\"15\",\"meter_number_recorded\":\"MT02\",\"membership_type_recorded\":\"Member\",\"membership_verification_status_recorded\":\"Verified\",\"application_status_recorded\":\"Needs Review\",\"declared_monthly_income\":2000,\"verified_monthly_income\":5000,\"income_verification_status\":\"Verified\",\"income_ocr_status\":\"Unreadable\",\"income_ocr_confidence\":0,\"disconnection_notice_history\":[]}','{\"ocr_status\":\"Unreadable\",\"extracted_name\":null,\"extracted_income_amount\":null,\"extracted_pay_period\":null,\"extracted_monthly_income\":null,\"income_amount_candidates\":[],\"missing_fields\":[\"Applicant name\",\"Income amount\",\"Clear pay period\"],\"ocr_confidence\":0,\"confidence_basis\":\"Readability field extraction only; this score does not verify document authenticity or income source.\",\"message\":\"OCR could not read this image. Staff can inspect the original proof and enter verified details.\",\"applicant_name_match\":null,\"declared_income_match\":null,\"comparison_status\":\"Needs Review\",\"declared_monthly_income\":2000,\"staff_verification\":{\"identity_confirmed\":true,\"document_authentic\":true,\"income_source_verified\":true,\"verified_monthly_income\":5000,\"verified_by\":1,\"verified_at\":\"2026-11-11T05:58:14+00:00\",\"notes\":\"CONFIRMED\",\"method\":\"Admin staff inspection of original proof of income\"},\"trusted_verification\":{\"status\":\"Verified\",\"verified_by\":1,\"verified_at\":\"2026-11-11T05:58:14+00:00\",\"verified_monthly_income\":5000,\"verification_notes\":\"CONFIRMED\"}}','Verified','[]','[]','2e226b9ffc5921145e4ff2c2d28d44cf054fab4a465501496489d8036266f6e3','2026-11-10 21:58:14','2026-11-10 21:58:14','2026-11-10 21:58:14');
INSERT INTO `loan_dss_evaluations` VALUES (7,4,'Approved','initial','dss-loan-eligibility-3-payment-history','{\"consumer_verified\":true,\"verified_membership\":true,\"late_payments_last_six_months\":0,\"late_payment_history\":[],\"water_bill_outstanding_balance\":0,\"overdue_water_balance\":0,\"overdue_water_months\":0,\"overdue_water_history\":[],\"previous_loan_outstanding_balance\":0,\"blocking_loans\":0,\"overdue_approved_loans\":0,\"active_water_connection\":true,\"account_status_recorded\":\"Active\",\"account_number_recorded\":\"18\",\"meter_number_recorded\":\"MT-5555\",\"membership_type_recorded\":\"Member\",\"membership_verification_status_recorded\":\"Verified\",\"application_status_recorded\":\"Needs Review\",\"declared_monthly_income\":null,\"verified_monthly_income\":null,\"income_verification_status\":\"Verified\",\"income_ocr_status\":\"Unreadable\",\"income_ocr_confidence\":0,\"disconnection_notice_history\":[],\"approval_reasons\":[\"Proof of income was verified by an administrator.\",\"Your consumer account is verified and activated.\",\"Registered Via Vida Townhomes Homeowners Association Member.\",\"No delayed or late water-bill payments in the last six months.\",\"No overdue water balance or unpaid previous loan.\",\"No active or overdue loan.\",\"Active water connection.\"]}','{\"ocr_status\":\"Unreadable\",\"extracted_name\":null,\"extracted_income_amount\":null,\"extracted_pay_period\":null,\"extracted_monthly_income\":null,\"income_amount_candidates\":[],\"missing_fields\":[\"Applicant name\",\"Income amount\",\"Clear pay period\"],\"ocr_confidence\":0,\"confidence_basis\":\"Readability field extraction only; this score does not verify document authenticity or income source.\",\"message\":\"OCR could not read this image. Staff can inspect the original proof and enter verified details.\",\"applicant_name_match\":null,\"declared_income_match\":null,\"comparison_status\":\"Needs Review\",\"prior_proof_rejections\":[{\"path\":\"loan-income-proofs\\/tRCZiCRjjpkWXFxaobK9CvDAojuru3zqRcTNkU73.png\",\"reason\":\"hanap kaayo\",\"verified_by\":1,\"verified_at\":\"2026-11-18T04:05:26+00:00\",\"verification_notes\":null,\"verified_monthly_income\":null,\"evidence\":{\"ocr_status\":\"Incomplete\",\"extracted_name\":null,\"extracted_income_amount\":null,\"extracted_pay_period\":null,\"extracted_monthly_income\":null,\"income_amount_candidates\":[],\"missing_fields\":[\"Applicant name\",\"Income amount\",\"Clear pay period\"],\"ocr_confidence\":0,\"confidence_basis\":\"Readability field extraction only; this score does not verify document authenticity or income source.\"}},{\"path\":\"loan-income-proofs\\/1qsbSCalNhWVhIx2u9ldsNVu3UiJ8eNRpjijf6fl.png\",\"reason\":\"bzahbxwx\",\"verified_by\":1,\"verified_at\":\"2026-11-18T04:12:58+00:00\",\"verification_notes\":null,\"verified_monthly_income\":null,\"evidence\":{\"ocr_status\":\"Incomplete\",\"extracted_name\":null,\"extracted_income_amount\":null,\"extracted_pay_period\":null,\"extracted_monthly_income\":null,\"income_amount_candidates\":[],\"missing_fields\":[\"Applicant name\",\"Income amount\",\"Clear pay period\"],\"ocr_confidence\":0,\"confidence_basis\":\"Readability field extraction only; this score does not verify document authenticity or income source.\"}}],\"trusted_verification\":{\"status\":\"Verified\",\"verified_by\":1,\"verified_at\":\"2026-11-18T04:14:19+00:00\",\"verified_monthly_income\":null,\"verification_notes\":null}}','Verified','[]','[]','ebc84aa81d668ee9414bccd3980000e0f80979955dafdc0b42ab886b6ba90a24','2026-11-17 20:14:19','2026-11-17 20:14:19','2026-11-17 20:14:19');
INSERT INTO `loan_dss_evaluations` VALUES (17,10,'Approved','initial','dss-loan-eligibility-4-no-membership-gate','{\"consumer_verified\":true,\"late_payments_last_six_months\":0,\"late_payment_history\":[],\"water_bill_outstanding_balance\":0,\"overdue_water_balance\":0,\"overdue_water_months\":0,\"overdue_water_history\":[],\"previous_loan_outstanding_balance\":0,\"blocking_loans\":0,\"overdue_approved_loans\":0,\"active_water_connection\":true,\"account_status_recorded\":\"Active\",\"account_number_recorded\":\"27\",\"meter_number_recorded\":\"MT-6565\",\"application_status_recorded\":\"Needs Review\",\"declared_monthly_income\":null,\"verified_monthly_income\":null,\"income_verification_status\":\"Verified\",\"income_ocr_status\":\"Incomplete\",\"income_ocr_confidence\":0,\"disconnection_notice_history\":[],\"approval_reasons\":[\"Proof of income was verified by an administrator.\",\"Your consumer account is verified and activated.\",\"Late-payment history check is not applicable because this new consumer has no payment records.\",\"No overdue water balance or unpaid previous loan.\",\"No active or overdue loan.\",\"Active water connection.\"]}','{\"ocr_status\":\"Incomplete\",\"extracted_name\":null,\"extracted_income_amount\":null,\"extracted_pay_period\":null,\"extracted_monthly_income\":null,\"income_amount_candidates\":[],\"missing_fields\":[\"Applicant name\",\"Income amount\",\"Clear pay period\"],\"ocr_confidence\":0,\"confidence_basis\":\"Readability field extraction only; this score does not verify document authenticity or income source.\",\"trusted_verification\":{\"status\":\"Verified\",\"verified_by\":1,\"verified_at\":\"2026-11-21T03:54:55+00:00\",\"verified_monthly_income\":null,\"verification_notes\":null}}','Verified','[]','[]','61af2c249de752815bbf02bfec38535e47b1678edcc3aa4d3739f1f4cbd04daf','2026-11-20 19:54:55','2026-11-20 19:54:55','2026-11-20 19:54:55');
INSERT INTO `loan_dss_evaluations` VALUES (24,21,'Approved','initial','dss-loan-eligibility-4-no-membership-gate','{\"consumer_verified\":true,\"late_payments_last_six_months\":0,\"late_payment_history\":[],\"water_bill_outstanding_balance\":0,\"overdue_water_balance\":0,\"overdue_water_months\":0,\"overdue_water_history\":[],\"previous_loan_outstanding_balance\":0,\"blocking_loans\":0,\"overdue_approved_loans\":0,\"active_water_connection\":true,\"account_status_recorded\":\"Active\",\"account_number_recorded\":\"31\",\"meter_number_recorded\":\"MT-5555\",\"application_status_recorded\":\"Needs Review\",\"declared_monthly_income\":null,\"verified_monthly_income\":null,\"income_verification_status\":\"Verified\",\"income_ocr_status\":\"Incomplete\",\"income_ocr_confidence\":0,\"disconnection_notice_history\":[],\"approval_reasons\":[\"Proof of income was verified by an administrator.\",\"Your consumer account is verified and activated.\",\"Late-payment history check is not applicable because this new consumer has no payment records.\",\"No overdue water balance or unpaid previous loan.\",\"No active or overdue loan.\",\"Active water connection.\"]}','{\"ocr_status\":\"Incomplete\",\"extracted_name\":null,\"extracted_income_amount\":null,\"extracted_pay_period\":null,\"extracted_monthly_income\":null,\"income_amount_candidates\":[],\"missing_fields\":[\"Applicant name\",\"Income amount\",\"Clear pay period\"],\"ocr_confidence\":0,\"confidence_basis\":\"Readability field extraction only; this score does not verify document authenticity or income source.\",\"trusted_verification\":{\"status\":\"Verified\",\"verified_by\":1,\"verified_at\":\"2026-11-23T16:12:12+00:00\",\"verified_monthly_income\":null,\"verification_notes\":null}}','Verified','[]','[]','7089c9e267337e257087422c8a1f3d2eb9dce976b0b783eff5af716ed7e83644','2026-11-23 08:12:12','2026-11-23 08:12:12','2026-11-23 08:12:12');
/*!40000 ALTER TABLE `loan_dss_evaluations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loan_installments`
--

DROP TABLE IF EXISTS `loan_installments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `loan_installments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `loan_id` bigint(20) unsigned NOT NULL,
  `bill_id` bigint(20) unsigned DEFAULT NULL,
  `installment_number` tinyint(3) unsigned NOT NULL,
  `billing_period` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `required_amount` decimal(10,2) NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `remaining_balance` decimal(10,2) NOT NULL,
  `payment_date` date DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'Billed',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `loan_installments_loan_id_installment_number_unique` (`loan_id`,`installment_number`),
  UNIQUE KEY `loan_installments_loan_id_billing_period_unique` (`loan_id`,`billing_period`),
  KEY `loan_installments_bill_id_foreign` (`bill_id`),
  KEY `loan_installments_reporting_period_status_index` (`billing_period`,`status`),
  CONSTRAINT `loan_installments_bill_id_foreign` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`id`) ON DELETE SET NULL,
  CONSTRAINT `loan_installments_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loan_installments`
--

LOCK TABLES `loan_installments` WRITE;
/*!40000 ALTER TABLE `loan_installments` DISABLE KEYS */;
INSERT INTO `loan_installments` VALUES (7,1,NULL,1,'2026-12-01','2026-12-11',73.17,0.00,73.17,NULL,'Upcoming','2026-11-17 18:26:05','2026-11-17 18:26:05');
INSERT INTO `loan_installments` VALUES (8,1,NULL,2,'2027-01-01','2027-01-11',73.17,0.00,73.17,NULL,'Upcoming','2026-11-17 18:26:05','2026-11-17 18:26:05');
INSERT INTO `loan_installments` VALUES (9,1,NULL,3,'2027-02-01','2027-02-11',73.17,0.00,73.17,NULL,'Upcoming','2026-11-17 18:26:05','2026-11-17 18:26:05');
INSERT INTO `loan_installments` VALUES (10,1,NULL,4,'2027-03-01','2027-03-11',73.17,0.00,73.17,NULL,'Upcoming','2026-11-17 18:26:05','2026-11-17 18:26:05');
INSERT INTO `loan_installments` VALUES (11,1,NULL,5,'2027-04-01','2027-04-11',73.17,0.00,73.17,NULL,'Upcoming','2026-11-17 18:26:05','2026-11-17 18:26:05');
INSERT INTO `loan_installments` VALUES (12,1,NULL,6,'2027-05-01','2027-05-11',73.15,0.00,73.15,NULL,'Upcoming','2026-11-17 18:26:05','2026-11-17 18:26:05');
INSERT INTO `loan_installments` VALUES (13,4,NULL,1,'2026-12-01','2026-12-18',146.33,146.33,0.00,'2026-10-20','Paid','2026-11-17 21:34:58','2026-11-17 23:22:56');
INSERT INTO `loan_installments` VALUES (14,4,NULL,2,'2027-01-01','2027-01-18',146.33,0.67,145.66,'2026-10-20','Partially Paid','2026-11-17 21:34:58','2026-11-17 23:22:56');
INSERT INTO `loan_installments` VALUES (15,4,NULL,3,'2027-02-01','2027-02-18',146.34,0.00,146.34,NULL,'Upcoming','2026-11-17 21:34:58','2026-11-17 21:34:58');
INSERT INTO `loan_installments` VALUES (25,10,NULL,1,'2026-12-01','2026-12-21',36.58,0.00,36.58,NULL,'Upcoming','2026-11-20 19:57:17','2026-11-20 19:57:17');
INSERT INTO `loan_installments` VALUES (26,10,NULL,2,'2027-01-01','2027-01-21',36.58,0.00,36.58,NULL,'Upcoming','2026-11-20 19:57:17','2026-11-20 19:57:17');
INSERT INTO `loan_installments` VALUES (27,10,NULL,3,'2027-02-01','2027-02-21',36.58,0.00,36.58,NULL,'Upcoming','2026-11-20 19:57:17','2026-11-20 19:57:17');
INSERT INTO `loan_installments` VALUES (28,10,NULL,4,'2027-03-01','2027-03-21',36.58,0.00,36.58,NULL,'Upcoming','2026-11-20 19:57:17','2026-11-20 19:57:17');
INSERT INTO `loan_installments` VALUES (29,10,NULL,5,'2027-04-01','2027-04-21',36.58,0.00,36.58,NULL,'Upcoming','2026-11-20 19:57:17','2026-11-20 19:57:17');
INSERT INTO `loan_installments` VALUES (30,10,NULL,6,'2027-05-01','2027-05-21',36.58,0.00,36.58,NULL,'Upcoming','2026-11-20 19:57:17','2026-11-20 19:57:17');
INSERT INTO `loan_installments` VALUES (31,10,NULL,7,'2027-06-01','2027-06-21',36.58,0.00,36.58,NULL,'Upcoming','2026-11-20 19:57:17','2026-11-20 19:57:17');
INSERT INTO `loan_installments` VALUES (32,10,NULL,8,'2027-07-01','2027-07-21',36.58,0.00,36.58,NULL,'Upcoming','2026-11-20 19:57:17','2026-11-20 19:57:17');
INSERT INTO `loan_installments` VALUES (33,10,NULL,9,'2027-08-01','2027-08-21',36.58,0.00,36.58,NULL,'Upcoming','2026-11-20 19:57:17','2026-11-20 19:57:17');
INSERT INTO `loan_installments` VALUES (34,10,NULL,10,'2027-09-01','2027-09-21',36.58,0.00,36.58,NULL,'Upcoming','2026-11-20 19:57:17','2026-11-20 19:57:17');
INSERT INTO `loan_installments` VALUES (35,10,NULL,11,'2027-10-01','2027-10-21',36.58,0.00,36.58,NULL,'Upcoming','2026-11-20 19:57:17','2026-11-20 19:57:17');
INSERT INTO `loan_installments` VALUES (36,10,NULL,12,'2027-11-01','2027-11-21',36.62,0.00,36.62,NULL,'Upcoming','2026-11-20 19:57:17','2026-11-20 19:57:17');
INSERT INTO `loan_installments` VALUES (52,14,NULL,1,'2026-06-01','2026-06-19',73.17,0.00,73.17,NULL,'Overdue','2026-11-23 00:14:44','2026-11-23 00:14:44');
INSERT INTO `loan_installments` VALUES (53,14,NULL,2,'2026-07-01','2026-07-19',73.17,0.00,73.17,NULL,'Overdue','2026-11-23 00:14:44','2026-11-23 00:14:44');
INSERT INTO `loan_installments` VALUES (54,14,NULL,3,'2026-08-01','2026-08-19',73.17,0.00,73.17,NULL,'Overdue','2026-11-23 00:14:44','2026-11-23 00:14:44');
INSERT INTO `loan_installments` VALUES (55,14,NULL,4,'2026-09-01','2026-09-19',73.17,0.00,73.17,NULL,'Overdue','2026-11-23 00:14:44','2026-11-23 00:14:44');
INSERT INTO `loan_installments` VALUES (56,14,NULL,5,'2026-10-01','2026-10-19',73.17,0.00,73.17,NULL,'Overdue','2026-11-23 00:14:44','2026-11-23 00:14:44');
INSERT INTO `loan_installments` VALUES (57,14,NULL,6,'2026-11-01','2026-11-19',73.15,0.00,73.15,NULL,'Overdue','2026-11-23 00:14:44','2026-11-23 00:14:44');
INSERT INTO `loan_installments` VALUES (70,21,NULL,1,'2026-12-01','2026-12-23',146.33,0.00,146.33,NULL,'Upcoming','2026-11-23 08:13:16','2026-11-23 08:13:16');
INSERT INTO `loan_installments` VALUES (71,21,NULL,2,'2027-01-01','2027-01-23',146.33,0.00,146.33,NULL,'Upcoming','2026-11-23 08:13:16','2026-11-23 08:13:16');
INSERT INTO `loan_installments` VALUES (72,21,NULL,3,'2027-02-01','2027-02-23',146.34,0.00,146.34,NULL,'Upcoming','2026-11-23 08:13:16','2026-11-23 08:13:16');
/*!40000 ALTER TABLE `loan_installments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loan_material_rates`
--

DROP TABLE IF EXISTS `loan_material_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `loan_material_rates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `water_meter_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `hose_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `other_materials_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `configured_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `loan_material_rates_configured_by_user_id_foreign` (`configured_by_user_id`),
  CONSTRAINT `loan_material_rates_configured_by_user_id_foreign` FOREIGN KEY (`configured_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loan_material_rates`
--

LOCK TABLES `loan_material_rates` WRITE;
/*!40000 ALTER TABLE `loan_material_rates` DISABLE KEYS */;
INSERT INTO `loan_material_rates` VALUES (1,0.00,0.00,0.00,0,NULL,'2026-08-03 00:19:08','2026-08-03 00:23:52');
INSERT INTO `loan_material_rates` VALUES (2,150.00,250.00,100.00,0,1,'2026-08-03 00:23:52','2026-08-03 05:25:21');
INSERT INTO `loan_material_rates` VALUES (3,145.00,100.00,40.00,0,1,'2026-08-03 05:25:21','2026-08-03 19:24:13');
INSERT INTO `loan_material_rates` VALUES (4,300.00,99.00,40.00,0,1,'2026-08-03 19:24:13','2026-11-22 18:18:52');
INSERT INTO `loan_material_rates` VALUES (5,300.00,99.00,40.00,0,1,'2026-11-22 18:18:52','2026-11-23 11:08:39');
INSERT INTO `loan_material_rates` VALUES (6,300.00,99.00,40.00,1,1,'2026-11-23 11:08:39','2026-11-23 11:08:39');
/*!40000 ALTER TABLE `loan_material_rates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loan_meter_logs`
--

DROP TABLE IF EXISTS `loan_meter_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `loan_meter_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `loan_meter_id` bigint(20) unsigned NOT NULL,
  `action` varchar(255) NOT NULL,
  `notes` text DEFAULT NULL,
  `performed_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `loan_meter_logs_loan_meter_id_foreign` (`loan_meter_id`),
  KEY `loan_meter_logs_performed_by_foreign` (`performed_by`),
  CONSTRAINT `loan_meter_logs_loan_meter_id_foreign` FOREIGN KEY (`loan_meter_id`) REFERENCES `loan_meters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `loan_meter_logs_performed_by_foreign` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loan_meter_logs`
--

LOCK TABLES `loan_meter_logs` WRITE;
/*!40000 ALTER TABLE `loan_meter_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `loan_meter_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loan_meters`
--

DROP TABLE IF EXISTS `loan_meters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `loan_meters` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `consumer_id` bigint(20) unsigned NOT NULL,
  `meter_number` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'Issued',
  `issue_date` date NOT NULL,
  `expected_return_date` date DEFAULT NULL,
  `returned_date` date DEFAULT NULL,
  `issued_by` bigint(20) unsigned NOT NULL,
  `return_condition_notes` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `loan_meters_consumer_id_foreign` (`consumer_id`),
  KEY `loan_meters_issued_by_foreign` (`issued_by`),
  CONSTRAINT `loan_meters_consumer_id_foreign` FOREIGN KEY (`consumer_id`) REFERENCES `consumers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `loan_meters_issued_by_foreign` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loan_meters`
--

LOCK TABLES `loan_meters` WRITE;
/*!40000 ALTER TABLE `loan_meters` DISABLE KEYS */;
/*!40000 ALTER TABLE `loan_meters` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loan_pricing_settings`
--

DROP TABLE IF EXISTS `loan_pricing_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `loan_pricing_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `meter_base_price` decimal(10,2) NOT NULL,
  `interest_3_months` decimal(10,2) NOT NULL,
  `interest_6_months` decimal(10,2) NOT NULL,
  `interest_12_months` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loan_pricing_settings`
--

LOCK TABLES `loan_pricing_settings` WRITE;
/*!40000 ALTER TABLE `loan_pricing_settings` DISABLE KEYS */;
INSERT INTO `loan_pricing_settings` VALUES (1,5000.00,300.00,600.00,1200.00,'2026-07-29 21:30:39','2026-07-29 21:30:39');
/*!40000 ALTER TABLE `loan_pricing_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loan_repayment_allocations`
--

DROP TABLE IF EXISTS `loan_repayment_allocations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `loan_repayment_allocations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `loan_repayment_id` bigint(20) unsigned NOT NULL,
  `loan_installment_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `installment_balance_after` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `loan_repayment_installment_unique` (`loan_repayment_id`,`loan_installment_id`),
  KEY `loan_repayment_allocations_loan_installment_id_foreign` (`loan_installment_id`),
  CONSTRAINT `loan_repayment_allocations_loan_installment_id_foreign` FOREIGN KEY (`loan_installment_id`) REFERENCES `loan_installments` (`id`),
  CONSTRAINT `loan_repayment_allocations_loan_repayment_id_foreign` FOREIGN KEY (`loan_repayment_id`) REFERENCES `loan_repayments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loan_repayment_allocations`
--

LOCK TABLES `loan_repayment_allocations` WRITE;
/*!40000 ALTER TABLE `loan_repayment_allocations` DISABLE KEYS */;
INSERT INTO `loan_repayment_allocations` VALUES (8,4,13,146.33,0.00,'2026-11-17 23:22:56','2026-11-17 23:22:56');
INSERT INTO `loan_repayment_allocations` VALUES (9,4,14,0.67,145.66,'2026-11-17 23:22:56','2026-11-17 23:22:56');
/*!40000 ALTER TABLE `loan_repayment_allocations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loan_repayments`
--

DROP TABLE IF EXISTS `loan_repayments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `loan_repayments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `loan_id` bigint(20) unsigned NOT NULL,
  `bill_id` bigint(20) unsigned DEFAULT NULL,
  `payment_id` bigint(20) unsigned DEFAULT NULL,
  `recorded_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `loan_balance_before` decimal(10,2) DEFAULT NULL,
  `loan_balance_after` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `loan_repayments_payment_id_unique` (`payment_id`),
  KEY `loan_repayments_loan_id_foreign` (`loan_id`),
  KEY `loan_repayments_recorded_by_user_id_foreign` (`recorded_by_user_id`),
  KEY `loan_repayments_bill_id_foreign` (`bill_id`),
  KEY `loan_repayments_reporting_date_index` (`payment_date`),
  CONSTRAINT `loan_repayments_bill_id_foreign` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`id`) ON DELETE SET NULL,
  CONSTRAINT `loan_repayments_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `loan_repayments_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `loan_repayments_recorded_by_user_id_foreign` FOREIGN KEY (`recorded_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loan_repayments`
--

LOCK TABLES `loan_repayments` WRITE;
/*!40000 ALTER TABLE `loan_repayments` DISABLE KEYS */;
INSERT INTO `loan_repayments` VALUES (4,4,NULL,19,1,147.00,'2026-10-20','Allocated from verified payment PAY-0019','2026-11-17 23:22:56','2026-11-17 23:22:56',439.00,292.00);
/*!40000 ALTER TABLE `loan_repayments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loans`
--

DROP TABLE IF EXISTS `loans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `loans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `consumer_id` bigint(20) unsigned NOT NULL,
  `applied_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `monthly_income` decimal(12,2) DEFAULT NULL,
  `income_verified_monthly_amount` decimal(12,2) DEFAULT NULL,
  `proof_of_income_path` varchar(255) DEFAULT NULL,
  `income_ocr_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`income_ocr_data`)),
  `income_verification_status` varchar(255) NOT NULL DEFAULT 'Needs Review',
  `income_verified_by` bigint(20) unsigned DEFAULT NULL,
  `income_verified_at` timestamp NULL DEFAULT NULL,
  `income_verification_notes` text DEFAULT NULL,
  `review_requested_at` timestamp NULL DEFAULT NULL,
  `review_request_reason` text DEFAULT NULL,
  `meter_base_price` decimal(10,2) DEFAULT NULL,
  `interest_amount` decimal(10,2) DEFAULT NULL,
  `total_payable` decimal(10,2) DEFAULT NULL,
  `term_months` tinyint(3) unsigned NOT NULL,
  `purpose` text NOT NULL,
  `material_meter_cost` decimal(10,2) DEFAULT NULL,
  `material_hose_cost` decimal(10,2) DEFAULT NULL,
  `material_other_cost` decimal(10,2) DEFAULT NULL,
  `installation_cost_total` decimal(10,2) DEFAULT NULL,
  `installment_schedule_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`installment_schedule_snapshot`)),
  `status` varchar(255) NOT NULL DEFAULT 'Pending Review',
  `review_status` varchar(255) NOT NULL DEFAULT 'Pending Review',
  `risk_level` varchar(255) NOT NULL DEFAULT 'Medium Risk',
  `recommendation_notes` text DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `installation_status` varchar(255) NOT NULL DEFAULT 'Not Scheduled',
  `installation_date` date DEFAULT NULL,
  `installation_time` time DEFAULT NULL,
  `installation_notes` text DEFAULT NULL,
  `monthly_installment` decimal(10,2) DEFAULT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `approved_at` timestamp NULL DEFAULT NULL,
  `decision_at` timestamp NULL DEFAULT NULL,
  `next_due_date` date DEFAULT NULL,
  `start_billing_period` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `terms_agreed_at` timestamp NULL DEFAULT NULL,
  `submission_confirmed_at` timestamp NULL DEFAULT NULL,
  `terms_version` varchar(100) DEFAULT NULL,
  `terms_text` text DEFAULT NULL,
  `income_rejection_reason` text DEFAULT NULL,
  `released_at` timestamp NULL DEFAULT NULL,
  `installation_completed_at` timestamp NULL DEFAULT NULL,
  `installation_completed_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `loans_consumer_id_foreign` (`consumer_id`),
  KEY `loans_applied_by_user_id_foreign` (`applied_by_user_id`),
  KEY `loans_reporting_date_status_index` (`applied_at`,`status`),
  KEY `loans_decision_at_index` (`decision_at`),
  KEY `loans_income_verified_by_foreign` (`income_verified_by`),
  KEY `loans_installation_completed_by_foreign` (`installation_completed_by`),
  CONSTRAINT `loans_applied_by_user_id_foreign` FOREIGN KEY (`applied_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `loans_consumer_id_foreign` FOREIGN KEY (`consumer_id`) REFERENCES `consumers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `loans_income_verified_by_foreign` FOREIGN KEY (`income_verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `loans_installation_completed_by_foreign` FOREIGN KEY (`installation_completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loans`
--

LOCK TABLES `loans` WRITE;
/*!40000 ALTER TABLE `loans` DISABLE KEYS */;
INSERT INTO `loans` VALUES (1,76,40,439.00,2000.00,5000.00,'loan-income-proofs/71HPPcXv3smJzJgfNvFR6zGysLrFFGhoaHix7pvL.jpg','{\"ocr_status\":\"Unreadable\",\"extracted_name\":null,\"extracted_income_amount\":null,\"extracted_pay_period\":null,\"extracted_monthly_income\":null,\"income_amount_candidates\":[],\"missing_fields\":[\"Applicant name\",\"Income amount\",\"Clear pay period\"],\"ocr_confidence\":0,\"confidence_basis\":\"Readability field extraction only; this score does not verify document authenticity or income source.\",\"message\":\"OCR could not read this image. Staff can inspect the original proof and enter verified details.\",\"applicant_name_match\":null,\"declared_income_match\":null,\"comparison_status\":\"Needs Review\",\"declared_monthly_income\":2000,\"staff_verification\":{\"identity_confirmed\":true,\"document_authentic\":true,\"income_source_verified\":true,\"verified_monthly_income\":5000,\"verified_by\":1,\"verified_at\":\"2026-11-11T05:58:14+00:00\",\"notes\":\"CONFIRMED\",\"method\":\"Admin staff inspection of original proof of income\"}}','Verified',1,'2026-11-10 21:58:14','CONFIRMED',NULL,NULL,NULL,NULL,NULL,6,'TEST',300.00,99.00,40.00,439.00,'[{\"month\":1,\"amount\":73.17},{\"month\":2,\"amount\":73.17},{\"month\":3,\"amount\":73.17},{\"month\":4,\"amount\":73.17},{\"month\":5,\"amount\":73.17},{\"month\":6,\"amount\":73.15}]','Ready for Scheduling','Installation Scheduled','Medium Risk','Income proof is awaiting trusted source verification and DSS review.',NULL,'Scheduled','2026-11-23','15:13:00',NULL,73.17,'2026-11-21 04:13:37','2026-11-10 21:58:14','2026-11-10 21:58:14',NULL,'2026-12-01','2026-11-10 21:53:46','2026-11-20 20:13:37',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL);
INSERT INTO `loans` VALUES (4,80,43,439.00,NULL,NULL,'loan-income-proofs/LmppX7KfI7yHkUp9DjGKRZc414WKV7MLSxnKhKjV.png','{\"ocr_status\":\"Unreadable\",\"extracted_name\":null,\"extracted_income_amount\":null,\"extracted_pay_period\":null,\"extracted_monthly_income\":null,\"income_amount_candidates\":[],\"missing_fields\":[\"Applicant name\",\"Income amount\",\"Clear pay period\"],\"ocr_confidence\":0,\"confidence_basis\":\"Readability field extraction only; this score does not verify document authenticity or income source.\",\"message\":\"OCR could not read this image. Staff can inspect the original proof and enter verified details.\",\"applicant_name_match\":null,\"declared_income_match\":null,\"comparison_status\":\"Needs Review\",\"prior_proof_rejections\":[{\"path\":\"loan-income-proofs\\/tRCZiCRjjpkWXFxaobK9CvDAojuru3zqRcTNkU73.png\",\"reason\":\"hanap kaayo\",\"verified_by\":1,\"verified_at\":\"2026-11-18T04:05:26+00:00\",\"verification_notes\":null,\"verified_monthly_income\":null,\"evidence\":{\"ocr_status\":\"Incomplete\",\"extracted_name\":null,\"extracted_income_amount\":null,\"extracted_pay_period\":null,\"extracted_monthly_income\":null,\"income_amount_candidates\":[],\"missing_fields\":[\"Applicant name\",\"Income amount\",\"Clear pay period\"],\"ocr_confidence\":0,\"confidence_basis\":\"Readability field extraction only; this score does not verify document authenticity or income source.\"}},{\"path\":\"loan-income-proofs\\/1qsbSCalNhWVhIx2u9ldsNVu3UiJ8eNRpjijf6fl.png\",\"reason\":\"bzahbxwx\",\"verified_by\":1,\"verified_at\":\"2026-11-18T04:12:58+00:00\",\"verification_notes\":null,\"verified_monthly_income\":null,\"evidence\":{\"ocr_status\":\"Incomplete\",\"extracted_name\":null,\"extracted_income_amount\":null,\"extracted_pay_period\":null,\"extracted_monthly_income\":null,\"income_amount_candidates\":[],\"missing_fields\":[\"Applicant name\",\"Income amount\",\"Clear pay period\"],\"ocr_confidence\":0,\"confidence_basis\":\"Readability field extraction only; this score does not verify document authenticity or income source.\"}}]}','Verified',1,'2026-11-17 20:14:19',NULL,NULL,NULL,NULL,NULL,NULL,3,'Wala pakoy kwarta',300.00,99.00,40.00,439.00,'[{\"month\":1,\"amount\":146.33},{\"month\":2,\"amount\":146.33},{\"month\":3,\"amount\":146.34}]','Ready for Scheduling','Installation Scheduled','Medium Risk','Income proof is awaiting trusted source verification and DSS review.',NULL,'Scheduled','2026-11-19','15:17:00','dapat naa ka ani',146.33,'2026-11-18 08:34:11','2026-11-17 20:14:19','2026-11-17 20:14:19','2027-01-18','2026-12-01','2026-11-17 20:04:15','2026-11-18 00:34:11','2026-11-17 20:04:15','2026-11-17 20:04:15','loan-terms-v2','Loan Meter Application Terms and Conditions\n\nI am applying for a loan meter and confirm that I am a registered VHOA member living in Via Vida Subdivision with no overdue water bills or unpaid loans. I understand that the Decision Support System (DSS) checks my eligibility and that the VVWD Administrator makes the final decision on my application.\n\nIf approved, I agree to pay for the water meter in interest-free monthly installments over my selected repayment term of 3, 6, or 12 months. I agree to pay each installment on or before its scheduled due date.\n\nI understand that if I fail to pay my loan meter balance in full by the end of my selected repayment term of 3, 6, or 12 months, I will be ineligible to apply for another loan meter.\n\nI may pay in cash at the VVWD office or pay online and upload clear proof of payment. VVWD will credit my payment and issue a digital receipt after verification.\n\nI understand that the water meter remains the property of VVWD until I pay for it in full, and I agree to keep it at its assigned location and refrain from tampering with it.\n\nI understand that VVWD will use my personal information to process my application and manage my account in accordance with the Data Privacy Act of 2012 (Republic Act No. 10173). VVWD will send application and payment updates to my registered email address.',NULL,'2026-11-18 00:31:54',NULL,NULL);
INSERT INTO `loans` VALUES (10,88,52,439.00,NULL,NULL,'loan-income-proofs/GaMKDCNJ1x077jciqolgGKjgWKQFaLNu6tyGcNrX.png','{\"ocr_status\":\"Incomplete\",\"extracted_name\":null,\"extracted_income_amount\":null,\"extracted_pay_period\":null,\"extracted_monthly_income\":null,\"income_amount_candidates\":[],\"missing_fields\":[\"Applicant name\",\"Income amount\",\"Clear pay period\"],\"ocr_confidence\":0,\"confidence_basis\":\"Readability field extraction only; this score does not verify document authenticity or income source.\"}','Verified',1,'2026-11-20 19:54:55',NULL,NULL,NULL,NULL,NULL,NULL,12,'METER GUBA',300.00,99.00,40.00,439.00,'[{\"month\":1,\"amount\":36.58},{\"month\":2,\"amount\":36.58},{\"month\":3,\"amount\":36.58},{\"month\":4,\"amount\":36.58},{\"month\":5,\"amount\":36.58},{\"month\":6,\"amount\":36.58},{\"month\":7,\"amount\":36.58},{\"month\":8,\"amount\":36.58},{\"month\":9,\"amount\":36.58},{\"month\":10,\"amount\":36.58},{\"month\":11,\"amount\":36.58},{\"month\":12,\"amount\":36.62}]','Ready for Scheduling','Installation Scheduled','Medium Risk','Income proof is awaiting trusted source verification and DSS review.',NULL,'Scheduled','2026-11-22','14:55:00',NULL,36.58,'2026-11-21 03:57:17','2026-11-20 19:54:55','2026-11-20 19:54:55',NULL,'2026-12-01','2026-11-20 19:54:24','2026-11-20 19:57:17','2026-11-20 19:54:24','2026-11-20 19:54:24','loan-terms-v2','Loan Meter Application Terms and Conditions\n\nI am applying for a loan meter and confirm that I am a registered VHOA member living in Via Vida Subdivision with no overdue water bills or unpaid loans. I understand that the Decision Support System (DSS) checks my eligibility and that the VVWD Administrator makes the final decision on my application.\n\nIf approved, I agree to pay for the water meter in interest-free monthly installments over my selected repayment term of 3, 6, or 12 months. I agree to pay each installment on or before its scheduled due date.\n\nI understand that if I fail to pay my loan meter balance in full by the end of my selected repayment term of 3, 6, or 12 months, I will be ineligible to apply for another loan meter.\n\nI may pay in cash at the VVWD office or pay online and upload clear proof of payment. VVWD will credit my payment and issue a digital receipt after verification.\n\nI understand that the water meter remains the property of VVWD until I pay for it in full, and I agree to keep it at its assigned location and refrain from tampering with it.\n\nI understand that VVWD will use my personal information to process my application and manage my account in accordance with the Data Privacy Act of 2012 (Republic Act No. 10173). VVWD will send application and payment updates to my registered email address.',NULL,NULL,NULL,NULL);
INSERT INTO `loans` VALUES (14,76,NULL,439.00,NULL,NULL,NULL,NULL,'Needs Review',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,6,'Development QA: expired six-month meter loan notice',NULL,NULL,NULL,NULL,NULL,'Active','Reviewed','Medium Risk',NULL,NULL,'Not Scheduled',NULL,NULL,NULL,73.17,'2026-05-18 16:00:00','2026-05-18 16:00:00',NULL,'2026-06-19','2026-06-01','2026-11-23 00:14:44','2026-11-23 00:14:44',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL);
INSERT INTO `loans` VALUES (21,109,56,439.00,NULL,NULL,'loan-income-proofs/SNQTzDrTrkGS9DdjgSCC0vfBpkK1ybJHEe4S6XAX.jpg','{\"ocr_status\":\"Incomplete\",\"extracted_name\":null,\"extracted_income_amount\":null,\"extracted_pay_period\":null,\"extracted_monthly_income\":null,\"income_amount_candidates\":[],\"missing_fields\":[\"Applicant name\",\"Income amount\",\"Clear pay period\"],\"ocr_confidence\":0,\"confidence_basis\":\"Readability field extraction only; this score does not verify document authenticity or income source.\"}','Verified',1,'2026-11-23 08:12:12',NULL,NULL,NULL,NULL,NULL,NULL,3,'naguba ako meter',300.00,99.00,40.00,439.00,'[{\"month\":1,\"amount\":146.33},{\"month\":2,\"amount\":146.33},{\"month\":3,\"amount\":146.34}]','Ready for Scheduling','Installation Scheduled','Medium Risk','Income proof is awaiting trusted source verification and DSS review.',NULL,'Completed','2026-11-26','17:13:00','naa ka ani',146.33,'2026-11-23 16:13:32','2026-11-23 08:12:12','2026-11-23 08:12:12',NULL,'2026-12-01','2026-11-23 08:11:57','2026-11-23 08:13:32','2026-11-23 08:11:57','2026-11-23 08:11:57','loan-terms-v3','Loan Meter Application Terms and Conditions\n\nI am applying for a loan meter and confirm that I am a registered VHOA member living in Via Vida Subdivision with no overdue water bills or unpaid loans. I understand that the Decision Support System (DSS) checks my eligibility and that the VVWD Administrator makes the final decision on my application.\n\nIf approved, I agree to pay for the water meter in interest-free monthly installments over my selected repayment term of 3, 6, or 12 months. I agree to pay each installment on or before its scheduled due date.\n\nI understand that if I fail to pay my loan meter balance in full by the end of my selected repayment term of 3, 6, or 12 months, I will be ineligible to apply for another loan meter.\n\nI understand that if I fail to fully pay my outstanding water meter loan balance by the end of my selected repayment term of 3, 6, or 12 months, I will receive a Disconnection Notice. I will be given three (3) days from the issuance of the notice to settle my outstanding balance. Failure to pay within this period may result in the disconnection of my water service, subject to confirmation by the VVWD Administrator.\n\nThe system issues this notice automatically. If the outstanding balance remains unpaid after the three-day grace period, the account will be marked Ready for Disconnection, subject to confirmation by the VVWD Administrator.\n\nI may pay in cash at the VVWD office or pay online and upload clear proof of payment. VVWD will credit my payment and issue a digital receipt after verification.\n\nI understand that the water meter remains the property of VVWD until I pay for it in full, and I agree to keep it at its assigned location and refrain from tampering with it.\n\nI understand that VVWD will use my personal information to process my application and manage my account in accordance with the Data Privacy Act of 2012 (Republic Act No. 10173). VVWD will send application and payment updates to my registered email address.',NULL,'2026-11-23 08:13:32','2026-11-23 08:13:32',1);
/*!40000 ALTER TABLE `loans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `meter_assignments`
--

DROP TABLE IF EXISTS `meter_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `meter_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `consumer_id` bigint(20) unsigned NOT NULL,
  `meter_number` varchar(255) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `retired_at` timestamp NULL DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `replaced_by_assignment_id` bigint(20) unsigned DEFAULT NULL,
  `assigned_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `meter_assignments_replaced_by_assignment_id_foreign` (`replaced_by_assignment_id`),
  KEY `meter_assignments_assigned_by_user_id_foreign` (`assigned_by_user_id`),
  KEY `meter_assignments_consumer_active_index` (`consumer_id`,`retired_at`),
  KEY `meter_assignments_consumer_meter_index` (`consumer_id`,`meter_number`),
  CONSTRAINT `meter_assignments_assigned_by_user_id_foreign` FOREIGN KEY (`assigned_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `meter_assignments_consumer_id_foreign` FOREIGN KEY (`consumer_id`) REFERENCES `consumers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `meter_assignments_replaced_by_assignment_id_foreign` FOREIGN KEY (`replaced_by_assignment_id`) REFERENCES `meter_assignments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `meter_assignments`
--

LOCK TABLES `meter_assignments` WRITE;
/*!40000 ALTER TABLE `meter_assignments` DISABLE KEYS */;
INSERT INTO `meter_assignments` VALUES (2,76,'MT02','2026-11-21 04:13:37','2026-11-20 20:13:37','Loan installation',37,NULL,'2026-11-10 21:32:23','2026-11-20 20:13:37');
INSERT INTO `meter_assignments` VALUES (5,80,'MT-5555','2026-11-18 04:16:08','2026-11-17 20:16:08','Loan installation',8,NULL,'2026-11-17 01:13:22','2026-11-17 20:16:08');
INSERT INTO `meter_assignments` VALUES (8,80,'MT-7777','2026-11-17 20:16:08',NULL,'Loan installation',NULL,1,'2026-11-17 20:16:08','2026-11-17 20:16:08');
INSERT INTO `meter_assignments` VALUES (12,75,'MT-8989','2026-11-17 21:56:21',NULL,'Meter recorded on the account',NULL,NULL,'2026-11-17 21:56:21','2026-11-17 21:56:21');
INSERT INTO `meter_assignments` VALUES (14,87,'MT-9191','2026-11-18 00:41:09',NULL,'Meter recorded on the account',NULL,NULL,'2026-11-18 00:41:09','2026-11-18 00:41:09');
INSERT INTO `meter_assignments` VALUES (16,88,'MT-6565','2026-11-21 03:55:52','2026-11-20 19:55:52','Loan installation',34,NULL,'2026-11-18 01:23:24','2026-11-20 19:55:52');
INSERT INTO `meter_assignments` VALUES (34,88,'MT-0909','2026-11-20 19:55:52',NULL,'Loan installation',NULL,1,'2026-11-20 19:55:52','2026-11-20 19:55:52');
INSERT INTO `meter_assignments` VALUES (37,76,'MT-2121','2026-11-20 20:13:37',NULL,'Loan installation',NULL,1,'2026-11-20 20:13:37','2026-11-20 20:13:37');
INSERT INTO `meter_assignments` VALUES (43,109,'MT-5555','2026-11-23 16:13:16','2026-11-23 08:13:16','Loan installation',44,NULL,'2026-11-23 08:09:57','2026-11-23 08:13:16');
INSERT INTO `meter_assignments` VALUES (44,109,'MT-3333','2026-11-23 08:13:16',NULL,'Loan installation',NULL,1,'2026-11-23 08:13:16','2026-11-23 08:13:16');
/*!40000 ALTER TABLE `meter_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `meter_readings`
--

DROP TABLE IF EXISTS `meter_readings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `meter_readings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `consumer_id` bigint(20) unsigned NOT NULL,
  `meter_number` varchar(255) DEFAULT NULL,
  `reader_id` bigint(20) unsigned NOT NULL,
  `reading_date` date NOT NULL,
  `previous_reading` decimal(10,2) NOT NULL,
  `current_reading` decimal(10,2) NOT NULL,
  `consumption` decimal(10,2) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'Pending',
  `remarks` varchar(255) DEFAULT NULL,
  `meter_photo_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `meter_readings_consumer_id_foreign` (`consumer_id`),
  KEY `meter_readings_reader_id_foreign` (`reader_id`),
  KEY `meter_readings_reporting_date_status_index` (`reading_date`,`status`),
  CONSTRAINT `meter_readings_consumer_id_foreign` FOREIGN KEY (`consumer_id`) REFERENCES `consumers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `meter_readings_reader_id_foreign` FOREIGN KEY (`reader_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `meter_readings`
--

LOCK TABLES `meter_readings` WRITE;
/*!40000 ALTER TABLE `meter_readings` DISABLE KEYS */;
INSERT INTO `meter_readings` VALUES (1,76,'MT02',3,'2026-11-11',0.00,10.00,10.00,'Encoded',NULL,NULL,'2026-11-10 21:39:03','2026-11-10 21:39:03',NULL);
INSERT INTO `meter_readings` VALUES (5,80,'MT-5555',3,'2026-11-17',0.00,15.00,15.00,'Encoded',NULL,NULL,'2026-11-17 01:16:48','2026-11-17 01:16:48','2026-11-17 01:16:48');
INSERT INTO `meter_readings` VALUES (50,88,'MT-0909',3,'2026-11-23',0.00,10.00,10.00,'Encoded',NULL,NULL,'2026-11-23 03:36:50','2026-11-23 03:36:50','2026-11-23 03:36:50');
INSERT INTO `meter_readings` VALUES (51,87,'MT-9191',3,'2026-11-23',0.00,15.00,15.00,'Encoded',NULL,NULL,'2026-11-23 03:37:14','2026-11-23 03:37:14','2026-11-23 03:37:14');
/*!40000 ALTER TABLE `meter_readings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=104 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` VALUES (3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` VALUES (4,'2026_04_01_000000_create_consumers_table',1);
INSERT INTO `migrations` VALUES (5,'2026_04_01_010000_create_bills_table',1);
INSERT INTO `migrations` VALUES (6,'2026_04_01_020000_add_role_to_users_and_user_id_to_consumers',1);
INSERT INTO `migrations` VALUES (7,'2026_04_01_030000_create_meter_readings_table',1);
INSERT INTO `migrations` VALUES (8,'2026_04_01_040000_add_meter_reading_fields_to_bills_table',1);
INSERT INTO `migrations` VALUES (9,'2026_04_01_050000_add_billing_breakdown_fields_to_bills_table',1);
INSERT INTO `migrations` VALUES (10,'2026_04_01_060000_create_payments_table',1);
INSERT INTO `migrations` VALUES (11,'2026_04_01_070000_create_incidents_table',1);
INSERT INTO `migrations` VALUES (12,'2026_04_02_080000_add_verification_and_receipt_fields_to_payments_table',1);
INSERT INTO `migrations` VALUES (13,'2026_04_02_090000_create_notifications_table',1);
INSERT INTO `migrations` VALUES (14,'2026_04_02_100000_create_loans_table',1);
INSERT INTO `migrations` VALUES (15,'2026_04_02_110000_create_loan_repayments_table',1);
INSERT INTO `migrations` VALUES (16,'2026_04_03_090000_create_announcements_table',1);
INSERT INTO `migrations` VALUES (17,'2026_04_05_000000_add_meter_photo_path_to_meter_readings_table',1);
INSERT INTO `migrations` VALUES (18,'2026_04_05_010000_add_membership_fields_to_consumers_table',1);
INSERT INTO `migrations` VALUES (19,'2026_04_05_020000_add_photo_to_incidents_table',1);
INSERT INTO `migrations` VALUES (20,'2026_04_11_010000_make_consumer_account_and_meter_nullable_for_pending_registration',1);
INSERT INTO `migrations` VALUES (21,'2026_04_11_020000_add_email_to_consumers_table',1);
INSERT INTO `migrations` VALUES (22,'2026_04_11_030000_add_installation_date_to_consumers_table',1);
INSERT INTO `migrations` VALUES (23,'2026_04_11_040000_add_connection_request_fields_to_consumers_table',1);
INSERT INTO `migrations` VALUES (24,'2026_04_12_010000_create_service_requests_table',1);
INSERT INTO `migrations` VALUES (25,'2026_06_01_000001_add_document_paths_to_consumers_table',1);
INSERT INTO `migrations` VALUES (26,'2026_06_01_000002_create_loan_meters_table',1);
INSERT INTO `migrations` VALUES (27,'2026_06_08_000001_add_confirmed_at_to_incidents_table',1);
INSERT INTO `migrations` VALUES (28,'2026_07_29_000000_add_membership_started_at_to_consumers_table',1);
INSERT INTO `migrations` VALUES (29,'2026_07_29_010000_allow_duplicate_application_emails_and_add_reference',1);
INSERT INTO `migrations` VALUES (30,'2026_07_29_020000_add_usernames_and_allow_duplicate_user_emails',1);
INSERT INTO `migrations` VALUES (31,'2026_07_29_030000_use_email_as_consumer_username',1);
INSERT INTO `migrations` VALUES (32,'2026_07_30_000001_add_unique_gcash_reference_to_payments_table',2);
INSERT INTO `migrations` VALUES (33,'2026_07_30_000002_create_loan_pricing_settings_and_add_loan_snapshots',3);
INSERT INTO `migrations` VALUES (34,'2026_08_03_000001_add_billing_installment_fields_to_loans_and_bills',4);
INSERT INTO `migrations` VALUES (35,'2026_08_03_000002_add_bill_payment_links_to_loan_repayments',5);
INSERT INTO `migrations` VALUES (36,'2026_08_03_000003_create_loan_material_rates_and_add_cost_snapshots_to_loans',6);
INSERT INTO `migrations` VALUES (37,'2026_08_03_000004_add_installment_schedule_snapshot_to_loans',7);
INSERT INTO `migrations` VALUES (38,'2026_08_03_000005_add_review_and_installation_schedule_fields_to_loans',8);
INSERT INTO `migrations` VALUES (39,'2026_08_03_000006_create_loan_installments_table',9);
INSERT INTO `migrations` VALUES (40,'2026_08_03_000007_add_media_type_to_incidents_table',10);
INSERT INTO `migrations` VALUES (41,'2026_08_04_000001_add_membership_document_photos_to_consumers_table',11);
INSERT INTO `migrations` VALUES (42,'2026_08_10_000001_add_resolution_tracking_to_incidents_table',12);
INSERT INTO `migrations` VALUES (43,'2026_08_10_000002_add_address_block_and_street_to_consumers_table',12);
INSERT INTO `migrations` VALUES (44,'2026_08_10_000003_add_carried_forward_tracking_to_bills_table',12);
INSERT INTO `migrations` VALUES (45,'2026_08_10_000004_create_consumer_notification_logs_table',12);
INSERT INTO `migrations` VALUES (47,'2026_08_10_000005_allow_notification_log_retries',13);
INSERT INTO `migrations` VALUES (50,'2026_08_10_000006_add_carried_forward_target_to_bills_table',14);
INSERT INTO `migrations` VALUES (51,'2026_08_10_000007_add_reconnection_workflow_fields',15);
INSERT INTO `migrations` VALUES (52,'2026_08_10_000008_create_meter_assignments_table',15);
INSERT INTO `migrations` VALUES (53,'2026_08_10_000009_allow_meter_number_reissue',16);
INSERT INTO `migrations` VALUES (54,'2026_10_20_000010_add_membership_verification_fields_to_consumers_table',17);
INSERT INTO `migrations` VALUES (55,'2026_10_20_000011_backfill_pending_connection_applications',18);
INSERT INTO `migrations` VALUES (56,'2026_10_20_000012_reclassify_unverified_member_claims',19);
INSERT INTO `migrations` VALUES (57,'2026_10_22_000001_add_resubmission_fields_to_consumers_table',20);
INSERT INTO `migrations` VALUES (58,'2026_10_22_000002_add_reporting_indexes',21);
INSERT INTO `migrations` VALUES (59,'2026_10_22_000003_add_connection_status_timestamps_to_consumers_table',21);
INSERT INTO `migrations` VALUES (60,'2026_10_22_000004_add_block_and_purok_to_incidents_table',22);
INSERT INTO `migrations` VALUES (61,'2026_10_23_000001_add_consumer_meter_usernames_and_password_change_flag',23);
INSERT INTO `migrations` VALUES (62,'2026_10_23_000002_create_installation_reschedule_requests_table',24);
INSERT INTO `migrations` VALUES (63,'2026_11_02_000001_add_verified_at_to_consumers_table',25);
INSERT INTO `migrations` VALUES (64,'2026_11_05_000001_create_disconnection_notices_table',26);
INSERT INTO `migrations` VALUES (65,'2026_11_05_000002_add_reconnection_fee_to_consumers_table',27);
INSERT INTO `migrations` VALUES (66,'2026_11_05_000003_add_authorized_reconnections',28);
INSERT INTO `migrations` VALUES (67,'2026_11_05_000004_remove_reconnection_fee_charges',29);
INSERT INTO `migrations` VALUES (68,'2026_11_05_000005_add_secure_activation_and_reconnection_rescheduling',30);
INSERT INTO `migrations` VALUES (69,'2026_11_06_000001_track_installation_reschedule_assignments',31);
INSERT INTO `migrations` VALUES (70,'2026_11_06_000002_preserve_disconnection_cycles',32);
INSERT INTO `migrations` VALUES (71,'2026_11_06_000001_create_loan_dss_evaluations_table',33);
INSERT INTO `migrations` VALUES (72,'2026_11_06_000002_add_decision_at_to_loans_table',33);
INSERT INTO `migrations` VALUES (73,'2026_11_06_000003_add_income_review_fields_to_loans',34);
INSERT INTO `migrations` VALUES (74,'2026_11_06_000004_add_income_evidence_to_loan_dss_evaluations',34);
INSERT INTO `migrations` VALUES (75,'2026_11_07_000001_separate_water_and_loan_billing',35);
INSERT INTO `migrations` VALUES (76,'2026_11_06_120000_extend_announcements_for_audiences_and_media',36);
INSERT INTO `migrations` VALUES (77,'2026_11_07_000001_add_soft_deletes_to_users',37);
INSERT INTO `migrations` VALUES (78,'2026_11_07_000002_enforce_single_administrator',38);
INSERT INTO `migrations` VALUES (79,'2026_11_07_000003_add_announcement_scheduling_fields',39);
INSERT INTO `migrations` VALUES (80,'2026_11_07_000004_add_announcement_audit_fields',40);
INSERT INTO `migrations` VALUES (81,'2026_11_10_000001_add_remember_expires_at_to_users_table',41);
INSERT INTO `migrations` VALUES (82,'2026_11_11_000000_add_custom_incident_type_to_incidents',42);
INSERT INTO `migrations` VALUES (83,'2026_11_17_000001_create_consumer_email_verifications_table',43);
INSERT INTO `migrations` VALUES (84,'2026_11_17_000002_add_meter_reading_submitted_at',44);
INSERT INTO `migrations` VALUES (85,'2026_11_17_000003_add_payment_rejection_reason',45);
INSERT INTO `migrations` VALUES (86,'2026_11_17_000001_add_carried_statement_snapshots',46);
INSERT INTO `migrations` VALUES (87,'2026_11_17_000002_add_loan_consent',47);
INSERT INTO `migrations` VALUES (88,'2026_11_18_000001_add_income_rejection_reason_to_loans',48);
INSERT INTO `migrations` VALUES (89,'2026_11_18_000002_create_loan_repayment_allocations',49);
INSERT INTO `migrations` VALUES (90,'2026_11_18_000001_allow_schedule_based_loan_payments',50);
INSERT INTO `migrations` VALUES (91,'2026_11_18_000003_add_reactivation_credentials',51);
INSERT INTO `migrations` VALUES (92,'2026_11_18_000004_add_temporary_password_to_consumer_password_setups',52);
INSERT INTO `migrations` VALUES (93,'2026_11_18_000005_add_connection_approval_credentials_to_consumers',53);
INSERT INTO `migrations` VALUES (94,'2026_11_18_000006_record_loan_release_dates',54);
INSERT INTO `migrations` VALUES (95,'2026_11_18_000007_mark_demo_water_bills',55);
INSERT INTO `migrations` VALUES (96,'2026_11_19_000001_create_loan_agreements_table',56);
INSERT INTO `migrations` VALUES (97,'2026_11_19_000002_add_installation_completion_to_consumers',57);
INSERT INTO `migrations` VALUES (98,'2026_11_21_000001_track_reconnection_completion',58);
INSERT INTO `migrations` VALUES (99,'2026_11_22_000001_add_installation_completion_to_loans',59);
INSERT INTO `migrations` VALUES (100,'2026_11_23_000001_add_classification_to_incidents',60);
INSERT INTO `migrations` VALUES (101,'2026_11_23_000002_add_loan_disconnection_notices',61);
INSERT INTO `migrations` VALUES (102,'2026_11_24_000001_update_incident_priority_classifications',62);
INSERT INTO `migrations` VALUES (103,'2026_11_24_000002_preserve_incident_report_timestamps',63);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` char(36) NOT NULL,
  `type` varchar(255) NOT NULL,
  `notifiable_type` varchar(255) NOT NULL,
  `notifiable_id` bigint(20) unsigned NOT NULL,
  `data` text NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES ('0092b7b9-8e5d-419f-ba6d-8eba6f4d1759','App\\Notifications\\ConnectionStatusNotification','App\\Models\\User',49,'{\"title\":\"Connection Request Approved\",\"message\":\"Your water connection request has been approved. Your account number is 24, installation is scheduled for November 19, 2026.\",\"consumer_id\":75,\"status\":\"Active\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/connection\"}',NULL,'2026-11-17 21:56:21','2026-11-17 21:56:21');
INSERT INTO `notifications` VALUES ('02f3cc43-a97d-4330-8aee-1c24e970f94c','App\\Notifications\\ConsumerNoticeNotification','App\\Models\\User',40,'{\"title\":\"Payment Verified - Ready for Reconnection\",\"message\":\"We have verified your payment of PHP 990.00. Your outstanding balance is now fully settled and your account is ready for reconnection. Please contact the office to arrange authorized reconnection. Please note that your water supply will only be restored after the association completes its reconnection procedure.\",\"kind\":\"consumer_notice\"}',NULL,'2026-11-22 06:24:14','2026-11-22 06:24:14');
INSERT INTO `notifications` VALUES ('04f31297-c3dd-4217-bb5c-ac259579679d','App\\Notifications\\LoanStatusNotification','App\\Models\\User',1,'{\"title\":\"DSS loan evaluation: Approved\",\"message\":\"DSS evaluated loan #4 for Len Adan. Result: Approved.\",\"loan_id\":4,\"consumer_id\":80,\"account_number\":\"18\",\"status\":\"Ready for Scheduling\",\"amount\":\"439.00\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/loans?tab=all\"}','2026-11-19 04:06:26','2026-11-17 20:14:19','2026-11-19 04:06:26');
INSERT INTO `notifications` VALUES ('09344995-8044-4d2b-86f0-3238f09fadfe','App\\Notifications\\ConnectionStatusNotification','App\\Models\\User',51,'{\"title\":\"Connection Request Approved\",\"message\":\"Your water connection request has been approved. Your account number is 26, installation is scheduled for November 19, 2026.\",\"consumer_id\":87,\"status\":\"Active\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/connection\"}','2026-11-18 00:44:47','2026-11-18 00:41:17','2026-11-18 00:44:47');
INSERT INTO `notifications` VALUES ('0e04bfdb-bb1a-4aa6-8ab9-eecb935003c9','App\\Notifications\\NewConnectionRequest','App\\Models\\User',1,'{\"title\":\"New Connection Request\",\"message\":\"Ren Rey has submitted water connection application VVA-20261118-72ZTY32G (Existing VVTHOA member - membership verification pending).\",\"consumer_id\":88,\"application_reference\":\"VVA-20261118-72ZTY32G\",\"membership_type\":\"Member\",\"requested_membership_type\":\"Member\",\"membership_verification_status\":\"Pending\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/consumers?search=VVA-20261118-72ZTY32G\"}','2026-11-19 04:06:26','2026-11-18 01:22:03','2026-11-19 04:06:26');
INSERT INTO `notifications` VALUES ('0e59cad5-93e7-4822-9ed5-af30599f2c8e','App\\Notifications\\PaymentStatusNotification','App\\Models\\User',43,'{\"title\":\"Payment rejected\",\"message\":\"Your payment for PRN-2611-0005 was rejected. Please review the details and resubmit if needed.\",\"payment_id\":5,\"reference_number\":\"PAY-0005\",\"receipt_number\":null,\"status\":\"Rejected\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/payments\"}',NULL,'2026-11-17 01:24:55','2026-11-17 01:24:55');
INSERT INTO `notifications` VALUES ('0f65222d-f5ce-48bc-af65-d15bfac8f741','App\\Notifications\\LoanStatusNotification','App\\Models\\User',52,'{\"title\":\"Loan application Approved\",\"message\":\"Your loan application has been approved. Please wait for the admin to schedule the next step.\",\"loan_id\":10,\"consumer_id\":88,\"account_number\":\"27\",\"status\":\"Ready for Scheduling\",\"amount\":\"439.00\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/loans\"}',NULL,'2026-11-20 19:54:55','2026-11-20 19:54:55');
INSERT INTO `notifications` VALUES ('0feb0b32-519a-4902-9277-ab9b56ec20b2','App\\Notifications\\IncidentStatusNotification','App\\Models\\User',1,'{\"title\":\"New incident reported\",\"message\":\"Leonel Martinez submitted a Water Leak report for Via Vida.\",\"incident_id\":2,\"category\":\"Water Leak\",\"status\":\"Pending\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/incidents\"}','2026-11-19 04:06:26','2026-11-10 21:47:25','2026-11-19 04:06:26');
INSERT INTO `notifications` VALUES ('15b90df7-6aea-416a-baf3-fabcc52e4648','App\\Notifications\\IncidentStatusNotification','App\\Models\\User',1,'{\"title\":\"Incident confirmed\",\"message\":\"LEONEL has confirmed that the Others report at Block 3, Maharlika, Purok 1-A, Via Vida, Panabo City, Philippines has been resolved.\",\"incident_id\":6,\"category\":\"Others\",\"status\":\"Resolved\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/incidents\"}','2026-11-19 04:06:26','2026-11-11 06:04:21','2026-11-19 04:06:26');
INSERT INTO `notifications` VALUES ('19fea35a-c60f-4190-8469-5c6727b764e8','App\\Notifications\\MeterReadingNotification','App\\Models\\User',1,'{\"title\":\"New meter reading submitted\",\"message\":\"Meter Reader One submitted a reading of 15.00 for meter MT-5555.\",\"meter_reading_id\":5,\"meter_number\":\"MT-5555\",\"consumer_name\":\"Len Adan\",\"current_reading\":\"15.00\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/billing\"}','2026-11-19 04:06:26','2026-11-17 01:16:48','2026-11-19 04:06:26');
INSERT INTO `notifications` VALUES ('25c2494c-c5db-4951-aa1b-ba45ac775403','App\\Notifications\\IncidentStatusNotification','App\\Models\\User',1,'{\"title\":\"New incident reported\",\"message\":\"Leonel Martinez submitted a Water Quality Issue report for Via Vida.\",\"incident_id\":3,\"category\":\"Water Quality Issue\",\"status\":\"Pending\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/incidents\"}','2026-11-19 04:06:26','2026-11-10 21:49:19','2026-11-19 04:06:26');
INSERT INTO `notifications` VALUES ('291c3b15-a6be-4cd2-97dd-b8a8a1002924','App\\Notifications\\LoanStatusNotification','App\\Models\\User',1,'{\"title\":\"DSS loan evaluation: Approved\",\"message\":\"DSS evaluated loan #21 for Lenne Lans. Result: Approved.\",\"loan_id\":21,\"consumer_id\":109,\"account_number\":\"31\",\"status\":\"Ready for Scheduling\",\"amount\":\"439.00\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/loans?tab=all\"}',NULL,'2026-11-23 08:12:12','2026-11-23 08:12:12');
INSERT INTO `notifications` VALUES ('2b44c9bd-acfd-4f1e-a266-1b0b98faa2eb','App\\Notifications\\ConnectionStatusNotification','App\\Models\\User',43,'{\"title\":\"Connection Request Approved\",\"message\":\"Your water connection request has been approved. Your account number is 18, installation is scheduled for November 20, 2026.\",\"consumer_id\":80,\"status\":\"Active\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/connection\"}',NULL,'2026-11-17 01:13:27','2026-11-17 01:13:27');
INSERT INTO `notifications` VALUES ('2fa9abc2-2b24-4e79-b0c8-d049bf024521','App\\Notifications\\LoanStatusNotification','App\\Models\\User',1,'{\"title\":\"New loan application\",\"message\":\"Ren Rey submitted a loan application.\",\"loan_id\":10,\"consumer_id\":88,\"account_number\":\"27\",\"status\":\"Needs Review\",\"amount\":\"439.00\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/loans\"}',NULL,'2026-11-20 19:54:24','2026-11-20 19:54:24');
INSERT INTO `notifications` VALUES ('3618891a-9203-4052-9de9-3c74af41b90b','App\\Notifications\\InstallationRescheduleRequested','App\\Models\\User',1,'{\"title\":\"Installation Reschedule Requested\",\"message\":\"Len Adan (Loan Ref: Loan #4) submitted a reschedule request for Loan Installation. Current Installation Date: November 19, 2026 at 3:15 PM. Reason: \\\"wala ko ani, available nko kay 19\\\"\",\"consumer_name\":\"Len Adan\",\"reference\":\"Loan #4\",\"application_reference\":\"VVA-20261117-4UJJOAWQ\",\"loan_reference\":\"Loan #4\",\"current_installation_date\":\"November 19, 2026 at 3:15 PM\",\"preferred_schedule\":null,\"reason\":\"wala ko ani, available nko kay 19\",\"reschedule_request_id\":5,\"consumer_id\":80,\"loan_id\":4,\"reschedule_type\":\"loan\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/loans\"}','2026-11-19 04:06:26','2026-11-17 20:17:13','2026-11-19 04:06:26');
INSERT INTO `notifications` VALUES ('392f2751-add2-4e47-ba5f-851687d0812a','App\\Notifications\\LoanStatusNotification','App\\Models\\User',43,'{\"title\":\"Proof of income needs correction\",\"message\":\"Your loan application remains pending. Please correct and resubmit your proof of income. Reason: hanap kaayo\",\"loan_id\":4,\"consumer_id\":80,\"account_number\":\"18\",\"status\":\"Needs Review\",\"amount\":\"439.00\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/loans\"}','2026-11-19 03:55:30','2026-11-17 20:05:31','2026-11-19 03:55:30');
INSERT INTO `notifications` VALUES ('3c0550d3-e13a-40da-83df-4b82f37340a3','App\\Notifications\\ConnectionStatusNotification','App\\Models\\User',56,'{\"title\":\"Connection Request Approved\",\"message\":\"Your water connection request has been approved. Your account number is 31, installation is scheduled for November 25, 2026.\",\"consumer_id\":109,\"status\":\"Active\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/connection\"}',NULL,'2026-11-23 08:10:03','2026-11-23 08:10:03');
INSERT INTO `notifications` VALUES ('3c7f0af8-2c68-47cc-8bf0-248292b58a6f','App\\Notifications\\PaymentStatusNotification','App\\Models\\User',1,'{\"title\":\"Payment pending verification\",\"message\":\"Len Adan submitted Online GCash for Loan #4.\",\"payment_id\":19,\"reference_number\":\"PAY-0019\",\"receipt_number\":null,\"status\":\"Pending\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/payments\"}','2026-11-19 04:06:26','2026-11-17 23:22:24','2026-11-19 04:06:26');
INSERT INTO `notifications` VALUES ('40b509e9-d62f-454e-9408-552525719227','App\\Notifications\\PaymentStatusNotification','App\\Models\\User',40,'{\"title\":\"Payment verified\",\"message\":\"Your payment for DEMO-DISC-MT02-202610 was verified. Receipt OR-261122-0077 is now available.\",\"payment_id\":77,\"reference_number\":\"PAY-0077\",\"receipt_number\":\"OR-261122-0077\",\"status\":\"Verified\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/payments\\/77\\/receipt\"}',NULL,'2026-11-22 06:24:14','2026-11-22 06:24:14');
INSERT INTO `notifications` VALUES ('453316ef-6929-475c-a029-dde937ab88ae','App\\Notifications\\LoanStatusNotification','App\\Models\\User',1,'{\"title\":\"DSS loan evaluation: Approved\",\"message\":\"DSS evaluated loan #10 for Ren Rey. Result: Approved.\",\"loan_id\":10,\"consumer_id\":88,\"account_number\":\"27\",\"status\":\"Ready for Scheduling\",\"amount\":\"439.00\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/loans?tab=all\"}',NULL,'2026-11-20 19:54:55','2026-11-20 19:54:55');
INSERT INTO `notifications` VALUES ('4987a625-2eb0-4c67-a4d3-6701116d4b8f','App\\Notifications\\IncidentStatusNotification','App\\Models\\User',1,'{\"title\":\"New incident reported\",\"message\":\"Leonel Martinez submitted a Others report for Via Vida.\",\"incident_id\":4,\"category\":\"Others\",\"status\":\"Pending\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/incidents\"}','2026-11-19 04:06:26','2026-11-11 05:59:34','2026-11-19 04:06:26');
INSERT INTO `notifications` VALUES ('49a70a42-8a44-43ac-a958-a841e5ff2a67','App\\Notifications\\LoanStatusNotification','App\\Models\\User',1,'{\"title\":\"New loan application\",\"message\":\"Len Adan submitted a loan application.\",\"loan_id\":4,\"consumer_id\":80,\"account_number\":\"18\",\"status\":\"Needs Review\",\"amount\":\"439.00\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/loans\"}','2026-11-19 04:06:26','2026-11-17 20:04:15','2026-11-19 04:06:26');
INSERT INTO `notifications` VALUES ('4c928920-7fa7-46d6-b920-af4eabb4d028','App\\Notifications\\PaymentStatusNotification','App\\Models\\User',43,'{\"title\":\"Payment verified\",\"message\":\"Your payment for Loan #4 was verified. Receipt OR-261118-0019 is now available.\",\"payment_id\":19,\"reference_number\":\"PAY-0019\",\"receipt_number\":\"OR-261118-0019\",\"status\":\"Verified\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/payments\\/19\\/receipt\"}',NULL,'2026-11-17 23:22:56','2026-11-17 23:22:56');
INSERT INTO `notifications` VALUES ('50e8e076-112f-42f4-886a-e43d21563f69','App\\Notifications\\LoanStatusNotification','App\\Models\\User',43,'{\"title\":\"Loan application Approved\",\"message\":\"Your loan application has been approved. Please wait for the admin to schedule the next step.\",\"loan_id\":4,\"consumer_id\":80,\"account_number\":\"18\",\"status\":\"Ready for Scheduling\",\"amount\":\"439.00\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/loans\"}','2026-11-19 03:41:33','2026-11-17 20:14:19','2026-11-19 03:41:33');
INSERT INTO `notifications` VALUES ('54ef7c2c-a257-4894-800b-93acafb52425','App\\Notifications\\LoanStatusNotification','App\\Models\\User',56,'{\"title\":\"Loan application Approved\",\"message\":\"Your loan application has been approved. Please wait for the admin to schedule the next step.\",\"loan_id\":21,\"consumer_id\":109,\"account_number\":\"31\",\"status\":\"Ready for Scheduling\",\"amount\":\"439.00\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/loans\"}',NULL,'2026-11-23 08:12:12','2026-11-23 08:12:12');
INSERT INTO `notifications` VALUES ('6dcabd14-9b62-4155-8571-e1c8e05f2ae8','App\\Notifications\\MeterReadingNotification','App\\Models\\User',1,'{\"title\":\"New meter reading submitted\",\"message\":\"Meter Reader One submitted a reading of 10.00 for meter MT02.\",\"meter_reading_id\":1,\"meter_number\":\"MT02\",\"consumer_name\":\"Leonel Martinez\",\"current_reading\":\"10.00\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/billing\"}','2026-11-19 04:06:26','2026-11-10 21:39:03','2026-11-19 04:06:26');
INSERT INTO `notifications` VALUES ('70c0bf9a-b998-4105-bb84-a15f4c96d7c2','App\\Notifications\\IncidentStatusNotification','App\\Models\\User',1,'{\"title\":\"New incident reported\",\"message\":\"Leonel Martinez submitted a Water Leak report for Via Vida.\",\"incident_id\":1,\"category\":\"Water Leak\",\"status\":\"Pending\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/incidents\"}','2026-11-19 04:06:26','2026-11-10 21:47:25','2026-11-19 04:06:26');
INSERT INTO `notifications` VALUES ('76183efe-356b-4d2d-a7f6-55b65d5dfa09','App\\Notifications\\LoanStatusNotification','App\\Models\\User',43,'{\"title\":\"Proof of income needs correction\",\"message\":\"Your loan application remains pending. Please correct and resubmit your proof of income. Reason: bzahbxwx\",\"loan_id\":4,\"consumer_id\":80,\"account_number\":\"18\",\"status\":\"Needs Review\",\"amount\":\"439.00\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/loans\"}',NULL,'2026-11-17 20:13:03','2026-11-17 20:13:03');
INSERT INTO `notifications` VALUES ('796596ee-2a78-4356-941f-2a16b2f52be5','App\\Notifications\\LoanStatusNotification','App\\Models\\User',1,'{\"title\":\"Replacement income proof submitted\",\"message\":\"Len Adan resubmitted proof of income for verification.\",\"loan_id\":4,\"consumer_id\":80,\"account_number\":\"18\",\"status\":\"Needs Review\",\"amount\":\"439.00\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/loans?tab=pending&pending=income\"}','2026-11-19 04:06:26','2026-11-17 20:13:42','2026-11-19 04:06:26');
INSERT INTO `notifications` VALUES ('7bb7e7e9-110c-401f-bdba-a4ed4af886c8','App\\Notifications\\MeterReadingNotification','App\\Models\\User',1,'{\"title\":\"New meter reading submitted\",\"message\":\"Meter Reader One submitted a reading of 10.00 for meter MT-0909.\",\"meter_reading_id\":50,\"meter_number\":\"MT-0909\",\"consumer_name\":\"Ren Rey\",\"current_reading\":\"10.00\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/billing\"}',NULL,'2026-11-23 03:36:50','2026-11-23 03:36:50');
INSERT INTO `notifications` VALUES ('7dcc5ed3-8f7b-49d8-a02d-3a36c7315e7d','App\\Notifications\\NewConnectionRequest','App\\Models\\User',1,'{\"title\":\"New Connection Request\",\"message\":\"Lenne Lans has submitted water connection application VVA-20261123-VJF6CYAR (Existing VVTHOA member - membership verification pending).\",\"consumer_id\":109,\"application_reference\":\"VVA-20261123-VJF6CYAR\",\"membership_type\":\"Member\",\"requested_membership_type\":\"Member\",\"membership_verification_status\":\"Pending\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/consumers?search=VVA-20261123-VJF6CYAR\"}',NULL,'2026-11-23 08:09:19','2026-11-23 08:09:19');
INSERT INTO `notifications` VALUES ('7ee490df-fdd8-40f1-9b19-9f1a05c9c89d','App\\Notifications\\NewConnectionRequest','App\\Models\\User',1,'{\"title\":\"New Connection Request\",\"message\":\"Len Adan has submitted water connection application VVA-20261117-4UJJOAWQ (Existing VVTHOA member - membership verification pending).\",\"consumer_id\":80,\"application_reference\":\"VVA-20261117-4UJJOAWQ\",\"membership_type\":\"Member\",\"requested_membership_type\":\"Member\",\"membership_verification_status\":\"Pending\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/consumers?search=VVA-20261117-4UJJOAWQ\"}','2026-11-17 01:12:42','2026-11-17 01:12:12','2026-11-17 01:12:42');
INSERT INTO `notifications` VALUES ('80bb9096-3cec-4d6b-a817-eef0cdfdabb4','App\\Notifications\\LoanStatusNotification','App\\Models\\User',1,'{\"title\":\"New loan application\",\"message\":\"Lenne Lans submitted a loan application.\",\"loan_id\":21,\"consumer_id\":109,\"account_number\":\"31\",\"status\":\"Needs Review\",\"amount\":\"439.00\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/loans\"}',NULL,'2026-11-23 08:11:57','2026-11-23 08:11:57');
INSERT INTO `notifications` VALUES ('81095e9f-3e92-4b26-ba68-6618b536e8ab','App\\Notifications\\LoanStatusNotification','App\\Models\\User',40,'{\"title\":\"Loan application approved\",\"message\":\"Your loan application has been approved. Please wait for the admin to schedule the next step.\",\"loan_id\":1,\"consumer_id\":76,\"account_number\":\"15\",\"status\":\"Ready for Scheduling\",\"amount\":\"439.00\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/loans\"}',NULL,'2026-11-10 21:58:14','2026-11-10 21:58:14');
INSERT INTO `notifications` VALUES ('8cef1a4a-f03f-4fd2-9a32-41da77fc7bd6','App\\Notifications\\NewConnectionRequest','App\\Models\\User',1,'{\"title\":\"New Connection Request\",\"message\":\"Gray Hunt has submitted water connection application VVA-20261118-C0UVAKQU (Existing VVTHOA member - membership verification pending).\",\"consumer_id\":87,\"application_reference\":\"VVA-20261118-C0UVAKQU\",\"membership_type\":\"Member\",\"requested_membership_type\":\"Member\",\"membership_verification_status\":\"Pending\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/consumers?search=VVA-20261118-C0UVAKQU\"}','2026-11-19 04:06:26','2026-11-18 00:40:11','2026-11-19 04:06:26');
INSERT INTO `notifications` VALUES ('95abdba4-10ed-4bbf-a5bc-88f346b2e4b1','App\\Notifications\\InstallationScheduleNotification','App\\Models\\User',40,'{\"title\":\"New Installation Schedule Assigned\",\"message\":\"Your new installation schedule is November 13, 2026 at 6:34 AM. Please be reminded that you are allowed a maximum of 3 installation reschedules.\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/connection\"}',NULL,'2026-11-10 21:35:09','2026-11-10 21:35:09');
INSERT INTO `notifications` VALUES ('9d542335-3842-4cec-88b6-39328d24c6f4','App\\Notifications\\IncidentStatusNotification','App\\Models\\User',1,'{\"title\":\"Incident confirmed\",\"message\":\"Leonel Martinez has confirmed that the Water Quality Issue report at Via Vida has been resolved.\",\"incident_id\":3,\"category\":\"Water Quality Issue\",\"status\":\"Resolved\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/incidents\"}','2026-11-19 04:06:26','2026-11-10 21:50:33','2026-11-19 04:06:26');
INSERT INTO `notifications` VALUES ('9def3772-8003-4b20-b777-07127d69612d','App\\Notifications\\IncidentStatusNotification','App\\Models\\User',1,'{\"title\":\"Incident confirmed\",\"message\":\"Leonel Martinez has confirmed that the Water Leak report at Via Vida has been resolved.\",\"incident_id\":1,\"category\":\"Water Leak\",\"status\":\"Resolved\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/incidents\"}','2026-11-19 04:06:26','2026-11-10 21:47:49','2026-11-19 04:06:26');
INSERT INTO `notifications` VALUES ('a1386080-a0a4-4437-9fa3-e7d83d9fef02','App\\Notifications\\PaymentStatusNotification','App\\Models\\User',43,'{\"title\":\"Payment verified\",\"message\":\"Your payment for PRN-2611-0005 was verified. Receipt OR-261117-0005 is now available.\",\"payment_id\":6,\"reference_number\":\"PAY-0006\",\"receipt_number\":\"OR-261117-0005\",\"status\":\"Verified\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/payments\\/6\\/receipt\"}',NULL,'2026-11-17 01:30:02','2026-11-17 01:30:02');
INSERT INTO `notifications` VALUES ('a230f8d1-dc84-4dd5-8fb2-40646d814bf4','App\\Notifications\\ConsumerNoticeNotification','App\\Models\\User',40,'{\"title\":\"Account Activated - Reconnection Schedule\",\"message\":\"Your account 15 is Active \\/ Pending Reconnection. Scheduled reconnection: November 25, 2026 10:31 PM (Philippine time).\",\"kind\":\"consumer_notice\"}',NULL,'2026-11-22 06:31:48','2026-11-22 06:31:48');
INSERT INTO `notifications` VALUES ('a2dac0bc-24de-4c16-b820-d7ca7e730509','App\\Notifications\\LoanStatusNotification','App\\Models\\User',1,'{\"title\":\"New loan application\",\"message\":\"Leonel Martinez submitted a loan application.\",\"loan_id\":1,\"consumer_id\":76,\"account_number\":\"15\",\"status\":\"Needs Review\",\"amount\":\"439.00\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/loans\"}','2026-11-19 04:06:26','2026-11-10 21:53:46','2026-11-19 04:06:26');
INSERT INTO `notifications` VALUES ('ab492422-f7c1-4e28-85c6-aed8815d718a','App\\Notifications\\InstallationRescheduleRequested','App\\Models\\User',1,'{\"title\":\"Installation Reschedule Requested\",\"message\":\"Leonel Martinez (Application Ref: VVA-20261111-6BQL57ZR) submitted a reschedule request for New Connection Installation. Current Installation Date: November 12, 2026. Reason: \\\"WALA KO SA BALAY\\\"\",\"consumer_name\":\"Leonel Martinez\",\"reference\":\"VVA-20261111-6BQL57ZR\",\"application_reference\":\"VVA-20261111-6BQL57ZR\",\"loan_reference\":null,\"current_installation_date\":\"November 12, 2026\",\"preferred_schedule\":null,\"reason\":\"WALA KO SA BALAY\",\"reschedule_request_id\":1,\"consumer_id\":76,\"loan_id\":null,\"reschedule_type\":\"connection\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/consumers?account_category=pending&pending_type=reschedules\"}','2026-11-19 04:06:26','2026-11-10 21:34:19','2026-11-19 04:06:26');
INSERT INTO `notifications` VALUES ('c1dd5a92-48da-4923-9a2b-3a0cac94eded','App\\Notifications\\PaymentStatusNotification','App\\Models\\User',1,'{\"title\":\"Payment pending verification\",\"message\":\"Len Adan submitted Online GCash for PRN-2611-0005.\",\"payment_id\":5,\"reference_number\":\"PAY-0005\",\"receipt_number\":null,\"status\":\"Pending\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/payments\"}','2026-11-19 04:06:26','2026-11-17 01:23:39','2026-11-19 04:06:26');
INSERT INTO `notifications` VALUES ('cb7d028a-b436-4fc1-bc5c-507f6b29a916','App\\Notifications\\NewConnectionRequest','App\\Models\\User',1,'{\"title\":\"New Connection Request\",\"message\":\"Leonel Martinez has submitted water connection application VVA-20261111-6BQL57ZR (Existing VVTHOA member - membership verification pending).\",\"consumer_id\":76,\"application_reference\":\"VVA-20261111-6BQL57ZR\",\"membership_type\":\"Member\",\"requested_membership_type\":\"Member\",\"membership_verification_status\":\"Pending\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/consumers?search=VVA-20261111-6BQL57ZR\"}','2026-11-19 04:06:26','2026-11-10 21:31:25','2026-11-19 04:06:26');
INSERT INTO `notifications` VALUES ('cd610fc4-460b-4be9-9c15-db10896c1f9e','App\\Notifications\\LoanStatusNotification','App\\Models\\User',1,'{\"title\":\"Replacement income proof submitted\",\"message\":\"Len Adan resubmitted proof of income for verification.\",\"loan_id\":4,\"consumer_id\":80,\"account_number\":\"18\",\"status\":\"Needs Review\",\"amount\":\"439.00\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/loans?tab=pending&pending=income\"}','2026-11-19 04:06:26','2026-11-17 20:11:28','2026-11-19 04:06:26');
INSERT INTO `notifications` VALUES ('d0dec3a5-5e8c-46b2-9ad0-2684ff65be0e','App\\Notifications\\IncidentStatusNotification','App\\Models\\User',40,'{\"title\":\"Incident logged\",\"message\":\"A Others report was logged for your account and is currently marked as Pending.\",\"incident_id\":6,\"category\":\"Others\",\"status\":\"Pending\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/incidents\"}',NULL,'2026-11-11 06:03:31','2026-11-11 06:03:31');
INSERT INTO `notifications` VALUES ('d2f66b31-7e65-459f-bc76-edc817c5c2a6','App\\Notifications\\ConnectionStatusNotification','App\\Models\\User',52,'{\"title\":\"Connection Request Approved\",\"message\":\"Your water connection request has been approved. Your account number is 27, installation is scheduled for November 20, 2026.\",\"consumer_id\":88,\"status\":\"Active\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/connection\"}',NULL,'2026-11-18 01:23:30','2026-11-18 01:23:30');
INSERT INTO `notifications` VALUES ('d9dfb438-eab0-4e61-8db4-7dbf8a0fa19e','App\\Notifications\\PaymentStatusNotification','App\\Models\\User',40,'{\"title\":\"Payment verified\",\"message\":\"Your payment for PRN-2611-0001 was verified. Receipt OR-261111-0001 is now available.\",\"payment_id\":1,\"reference_number\":\"PAY-0001\",\"receipt_number\":\"OR-261111-0001\",\"status\":\"Verified\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/payments\\/1\\/receipt\"}',NULL,'2026-11-10 21:44:11','2026-11-10 21:44:11');
INSERT INTO `notifications` VALUES ('e460cb79-49f5-4201-a90d-219b5d454f62','App\\Notifications\\NewConnectionRequest','App\\Models\\User',1,'{\"title\":\"New Connection Request\",\"message\":\"Mica Oquias has submitted water connection application VVA-20261109-GBQE7PLL (Existing VIOHOA member - membership verification pending).\",\"consumer_id\":75,\"application_reference\":\"VVA-20261109-GBQE7PLL\",\"membership_type\":\"Member\",\"requested_membership_type\":\"Member\",\"membership_verification_status\":\"Pending\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/consumers?search=VVA-20261109-GBQE7PLL\"}','2026-11-10 02:35:45','2026-11-09 00:36:54','2026-11-10 02:35:45');
INSERT INTO `notifications` VALUES ('ec7e748d-63d2-45d9-a9c5-74b444673427','App\\Notifications\\MeterReadingNotification','App\\Models\\User',1,'{\"title\":\"New meter reading submitted\",\"message\":\"Meter Reader One submitted a reading of 15.00 for meter MT-9191.\",\"meter_reading_id\":51,\"meter_number\":\"MT-9191\",\"consumer_name\":\"Gray Hunt\",\"current_reading\":\"15.00\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/billing\"}',NULL,'2026-11-23 03:37:14','2026-11-23 03:37:14');
INSERT INTO `notifications` VALUES ('ef13af65-650e-482b-8c9a-6cb27845cd7b','App\\Notifications\\PaymentStatusNotification','App\\Models\\User',1,'{\"title\":\"Payment pending verification\",\"message\":\"Leonel Martinez submitted Online GCash for PRN-2611-0001.\",\"payment_id\":1,\"reference_number\":\"PAY-0001\",\"receipt_number\":null,\"status\":\"Pending\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/payments\"}','2026-11-19 04:06:26','2026-11-10 21:43:46','2026-11-19 04:06:26');
INSERT INTO `notifications` VALUES ('f3913ab4-ff13-4132-a2fe-7badea3df5f9','App\\Notifications\\ConnectionStatusNotification','App\\Models\\User',40,'{\"title\":\"Connection Request Approved\",\"message\":\"Your water connection request has been approved. Your account number is 15, installation is scheduled for November 12, 2026.\",\"consumer_id\":76,\"status\":\"Active\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/connection\"}',NULL,'2026-11-10 21:32:31','2026-11-10 21:32:31');
INSERT INTO `notifications` VALUES ('f51990d9-3e31-4c4d-a168-da50a6519e19','App\\Notifications\\InstallationScheduleNotification','App\\Models\\User',43,'{\"title\":\"New Installation Schedule Assigned\",\"message\":\"Your new installation schedule is November 19, 2026 at 3:17 PM. Please be reminded that you are allowed a maximum of 3 installation reschedules.\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/loans\"}','2026-11-19 03:41:55','2026-11-17 20:18:07','2026-11-19 03:41:55');
INSERT INTO `notifications` VALUES ('fa5163ac-1d22-4fe0-a228-b303fdcdc003','App\\Notifications\\PaymentStatusNotification','App\\Models\\User',1,'{\"title\":\"Payment pending verification\",\"message\":\"Len Adan submitted Online GCash for PRN-2611-0005.\",\"payment_id\":6,\"reference_number\":\"PAY-0006\",\"receipt_number\":null,\"status\":\"Pending\",\"url\":\"http:\\/\\/127.0.0.1:8000\\/payments\"}','2026-11-19 04:06:26','2026-11-17 01:29:32','2026-11-19 04:06:26');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `bill_id` bigint(20) unsigned DEFAULT NULL,
  `loan_id` bigint(20) unsigned DEFAULT NULL,
  `consumer_id` bigint(20) unsigned NOT NULL,
  `payment_type` varchar(255) NOT NULL DEFAULT 'Water',
  `reference_number` varchar(255) NOT NULL,
  `receipt_number` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` varchar(255) NOT NULL,
  `payment_channel` varchar(255) NOT NULL DEFAULT 'Counter',
  `gateway_reference` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'Pending',
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by` bigint(20) unsigned DEFAULT NULL,
  `proof_path` varchar(255) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `submission_key` char(36) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payments_reference_number_unique` (`reference_number`),
  UNIQUE KEY `payments_receipt_number_unique` (`receipt_number`),
  UNIQUE KEY `payments_gateway_reference_unique` (`gateway_reference`),
  UNIQUE KEY `payments_submission_key_unique` (`submission_key`),
  KEY `payments_bill_id_foreign` (`bill_id`),
  KEY `payments_consumer_id_foreign` (`consumer_id`),
  KEY `payments_verified_by_foreign` (`verified_by`),
  KEY `payments_reporting_date_status_index` (`payment_date`,`status`),
  KEY `payments_reporting_channel_index` (`payment_channel`),
  KEY `payments_loan_id_foreign` (`loan_id`),
  CONSTRAINT `payments_bill_id_foreign` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_consumer_id_foreign` FOREIGN KEY (`consumer_id`) REFERENCES `consumers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`),
  CONSTRAINT `payments_verified_by_foreign` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=96 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (1,1,NULL,76,'Water','PAY-0001','OR-261111-0001',180.00,'2026-10-20','GCash','Online','928457106397','Verified','2026-11-10 21:44:11',1,'payment-proofs/zvODzf4gTtkOU4YAVyMr8q3eneZVLzqYXGlNd5lF.png','Sender Name: Leonel Martinez | Sender Phone: 09535581787','2026-11-10 21:43:46','2026-11-10 21:44:11','01c39857-6d84-42ce-ba84-da3d4444a0b0',NULL);
INSERT INTO `payments` VALUES (5,5,NULL,80,'Water','PAY-0005',NULL,270.00,'2026-10-20','GCash','Online','279GC02E','Rejected',NULL,NULL,'payment-proofs/n5Fr4tLgjhbzp8g9t1DyibEulzJARy1WbQxP8FVR.png','Sender Phone: 09926381779','2026-11-17 01:23:39','2026-11-17 01:24:55','9b8c5049-d71f-4763-93f7-57a5d9c739ab','mali ang na submit na proff');
INSERT INTO `payments` VALUES (6,5,NULL,80,'Water','PAY-0006','OR-261117-0005',270.00,'2026-10-20','GCash','Online','826439176052','Verified','2026-11-17 01:30:02',1,'payment-proofs/t5a8ImuyicJXSsqKC3TmjuFUyfBlb2B8kHzlthN4.png','Sender Phone: 09926381779','2026-11-17 01:29:32','2026-11-17 01:30:02','89442a67-a3b9-4157-a2c1-de4bf3b10156',NULL);
INSERT INTO `payments` VALUES (19,NULL,4,80,'Loan','PAY-0019','OR-261118-0019',147.00,'2026-10-20','GCash','Online','718460923571','Verified','2026-11-17 23:22:56',1,'payment-proofs/pnUCKpDBlemkmTOEB1QgKZ6vujzsHcqmUdwMnusH.png','Sender Name: Len Adan | Sender Phone: 09926381779','2026-11-17 23:22:24','2026-11-17 23:22:56','1dd35dfa-d995-4bfd-8c98-2871826c2316',NULL);
INSERT INTO `payments` VALUES (77,32,NULL,76,'Water','PAY-0077','OR-261122-0077',990.00,'2026-11-22','Cash','Counter',NULL,'Verified','2026-11-22 06:24:08',1,NULL,NULL,'2026-11-22 06:24:08','2026-11-22 06:24:08','69417fce-5a01-476c-869e-e1b6915cefb0',NULL);
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_requests`
--

DROP TABLE IF EXISTS `service_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `consumer_id` bigint(20) unsigned NOT NULL,
  `request_type` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'Pending',
  `current_balance_snapshot` decimal(10,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `proof_path` varchar(255) DEFAULT NULL,
  `information` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `requested_at` timestamp NULL DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `service_requests_consumer_id_foreign` (`consumer_id`),
  KEY `service_requests_reviewed_by_user_id_foreign` (`reviewed_by_user_id`),
  KEY `service_requests_reporting_type_status_reviewed_index` (`request_type`,`status`,`reviewed_at`),
  KEY `service_requests_reporting_requested_index` (`requested_at`),
  CONSTRAINT `service_requests_consumer_id_foreign` FOREIGN KEY (`consumer_id`) REFERENCES `consumers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `service_requests_reviewed_by_user_id_foreign` FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_requests`
--

LOCK TABLES `service_requests` WRITE;
/*!40000 ALTER TABLE `service_requests` DISABLE KEYS */;
INSERT INTO `service_requests` VALUES (1,75,'New Connection','Approved',0.00,'Water connection application VVA-20261109-GBQE7PLL. Declared VIOHOA membership: Member.',NULL,NULL,'Connection approved. VVTHOA membership verified.','2026-11-09 00:36:53','2026-11-17 21:56:21',1,'2026-11-09 00:36:53','2026-11-17 21:56:21');
INSERT INTO `service_requests` VALUES (2,76,'New Connection','Approved',0.00,'Water connection application VVA-20261111-6BQL57ZR. Declared VVTHOA membership: Member.',NULL,NULL,'Connection approved. VVTHOA membership verified.','2026-11-10 21:31:25','2026-11-10 21:32:23',1,'2026-11-10 21:31:25','2026-11-10 21:32:23');
INSERT INTO `service_requests` VALUES (6,80,'New Connection','Approved',0.00,'Water connection application VVA-20261117-4UJJOAWQ. Declared VVTHOA membership: Member.',NULL,NULL,'Connection approved. VVTHOA membership verified.','2026-11-17 01:12:12','2026-11-17 01:13:22',1,'2026-11-17 01:12:12','2026-11-17 01:13:22');
INSERT INTO `service_requests` VALUES (13,87,'New Connection','Approved',0.00,'Water connection application VVA-20261118-C0UVAKQU. Declared VVTHOA membership: Member.',NULL,NULL,'Connection approved. VVTHOA membership verified.','2026-11-18 00:40:11','2026-11-18 00:41:10',1,'2026-11-18 00:40:11','2026-11-18 00:41:10');
INSERT INTO `service_requests` VALUES (14,88,'New Connection','Approved',0.00,'Water connection application VVA-20261118-72ZTY32G. Declared VVTHOA membership: Member.',NULL,NULL,'Connection approved. VVTHOA membership verified.','2026-11-18 01:22:03','2026-11-18 01:23:24',1,'2026-11-18 01:22:03','2026-11-18 01:23:24');
INSERT INTO `service_requests` VALUES (18,109,'New Connection','Approved',0.00,'Water connection application VVA-20261123-VJF6CYAR. Declared VVTHOA membership: Member.',NULL,NULL,'Connection approved. VVTHOA membership verified.','2026-11-23 08:09:19','2026-11-23 08:09:57',1,'2026-11-23 08:09:19','2026-11-23 08:09:57');
/*!40000 ALTER TABLE `service_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `role` varchar(255) NOT NULL DEFAULT 'consumer',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `active_admin_guard` tinyint(4) GENERATED ALWAYS AS (case when `role` = 'admin' and `deleted_at` is null then 1 else NULL end) STORED,
  `remember_expires_at` timestamp NULL DEFAULT NULL,
  `must_change_reactivation_password` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_single_active_admin` (`active_admin_guard`)
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Admin User','admin','admin@example.com','admin',NULL,'$2y$12$TRPkb0iwm1QkLozcBiYlPu/4y6JrZNRKOLwkHOVu9OTH998jhgZJm',0,'1irpQGdbQX4zcyJjcmC3yOq4DUTc9K8INmN1hwimfjRDqBY9935NWdNolBsd','2026-03-31 19:26:37','2026-10-12 20:41:32',NULL,1,NULL,0);
INSERT INTO `users` VALUES (3,'Meter Reader One','meter','reader@example.com','meter_reader',NULL,'$2y$12$GOIk879Oo2sBBw80IFCkku/cVY0DFROT7CVTf016Bm8Z/Uc1rF7l2',0,'6gG0GlCrfmhDbQa04BpaGVBdRbNnsrXWixLbdS4qbtM3ixMRYLyLGCzyMDUG','2026-03-31 21:10:00','2026-11-09 11:28:58',NULL,NULL,'2026-12-09 11:28:58',0);
INSERT INTO `users` VALUES (9,'Meter Reader','meter2','meter.reader@example.com','meter_reader',NULL,'$2y$12$Hdq.Vh0pCpdUynx1oIb2aOE9NzA8uP.W6uE7BzWYkC1TM2B14y97O',0,NULL,'2026-07-28 19:44:52','2026-11-07 05:18:00','2026-11-07 05:18:00',NULL,NULL,0);
INSERT INTO `users` VALUES (24,'Augustus Hettinger','icarroll','swest@example.org','admin','2026-10-12 22:36:15','$2y$12$qCcuQjFJ8TmlgJvVYOa9Du1B2dbbig.faGbqbmpeCfie8/.dVWQQa',0,NULL,'2026-10-12 22:36:15','2026-11-07 05:27:49','2026-11-07 05:27:49',NULL,NULL,0);
INSERT INTO `users` VALUES (27,'Prof. Edison Berge','jovani.wilkinson','kaleb.effertz@example.org','admin','2026-10-12 22:49:43','$2y$12$ZKUvN3D/dp3EX49CuGexMeaAGJucJ1xLzTj8XRcj6X7RDi6wLFEjq',0,NULL,'2026-10-12 22:49:43','2026-11-07 05:27:49','2026-11-07 05:27:49',NULL,NULL,0);
INSERT INTO `users` VALUES (38,'QA Meter Reader','ohara.lonzo','qa.meter.reader@example.com','meter_reader','2026-11-02 23:10:57','$2y$12$Khuxs8pAc5CFK8rV3nUiGeuF5yFyo0iymOAtSUWSbJTyAmQ1w8cjy',0,NULL,'2026-11-02 23:10:57','2026-11-07 05:18:04','2026-11-07 05:18:04',NULL,NULL,0);
INSERT INTO `users` VALUES (40,'Leonel Martinez','MT-2121','bonghanoylovely9@gmail.com','consumer',NULL,'$2y$12$49MJ7b.qWUlLneBLdCdkqub1QBJ4twL1JSFRcHi1H3MA/hKYB33Fe',1,NULL,'2026-11-10 21:32:23','2026-11-20 20:13:37',NULL,NULL,NULL,0);
INSERT INTO `users` VALUES (43,'Len Adan','MT-7777','bonghanoylovely9@gmail.com','consumer',NULL,'$2y$12$B4lfdn1wJi0n2FQFMoo9Hep81YwxvjYkfQmTLy/YkY6PSZFQVXigC',1,NULL,'2026-11-17 01:13:22','2026-11-17 20:16:08',NULL,NULL,NULL,0);
INSERT INTO `users` VALUES (49,'Mica Oquias','MT-8989','leonelmartinez0953@gmail.com','consumer',NULL,'$2y$12$JP6p6PYYrvh/nMrCQ7A98OIKY2kdolBE8UiBANRWapywy5E2OxepO',1,NULL,'2026-11-17 21:56:21','2026-11-17 21:56:21',NULL,NULL,NULL,0);
INSERT INTO `users` VALUES (51,'Gray Hunt','MT-9191','bonghanoylovely9@gmail.com','consumer',NULL,'$2y$12$89ewhxPQ1yscrmplP6/4VuUf7m5yvaVVviNE1GMg65gq3/JC3vRPK',1,NULL,'2026-11-18 00:41:09','2026-11-18 00:41:09',NULL,NULL,NULL,0);
INSERT INTO `users` VALUES (52,'Ren Rey','MT-0909','bonghanoylovely9@gmail.com','consumer',NULL,'$2y$12$lWt8mtj7F7moTVEOj/.N1epMAY7k07SB5HpctCIMMxWpnC0NcbQ0m',1,NULL,'2026-11-18 01:23:24','2026-11-20 19:55:52',NULL,NULL,NULL,0);
INSERT INTO `users` VALUES (56,'Lenne Lans','MT-3333','bonghanoylovely9@gmail.com','consumer',NULL,'$2y$12$lWhqb.cDvz5R54BLttOhBeZ4V69UIirX..W6Pykf1UXM.kZhY.cGi',1,NULL,'2026-11-23 08:09:57','2026-11-23 08:13:16',NULL,NULL,NULL,0);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER users_admin_insert_guard BEFORE INSERT ON users FOR EACH ROW BEGIN IF NEW.role = 'admin' AND NEW.deleted_at IS NULL AND LOWER(NEW.email) <> 'admin@example.com' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only admin@example.com may be an active administrator'; END IF; END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER users_admin_update_guard BEFORE UPDATE ON users FOR EACH ROW BEGIN IF NEW.role = 'admin' AND NEW.deleted_at IS NULL AND LOWER(NEW.email) <> 'admin@example.com' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only admin@example.com may be an active administrator'; END IF; END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Dumping events for database 'water_billing_systems'
--

--
-- Dumping routines for database 'water_billing_systems'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-24 12:16:51
