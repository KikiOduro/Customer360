-- phpMyAdmin SQL Dump
-- version 5.2.1deb3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Apr 01, 2026 at 06:17 PM
-- Server version: 8.0.45-0ubuntu0.24.04.1
-- PHP Version: 8.3.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `customer360`
--

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `id` int NOT NULL,
  `message` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` int NOT NULL,
  `job_id` varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int NOT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `upload_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `output_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `clustering_method` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'kmeans',
  `include_comparison` tinyint(1) DEFAULT '0',
  `column_mapping` text COLLATE utf8mb4_unicode_ci,
  `num_customers` int DEFAULT NULL,
  `num_transactions` int DEFAULT NULL,
  `total_revenue` float DEFAULT NULL,
  `num_clusters` int DEFAULT NULL,
  `silhouette_score` float DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL,
  `is_saved` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `jobs`
--

INSERT INTO `jobs` (`id`, `job_id`, `user_id`, `status`, `error_message`, `original_filename`, `upload_path`, `output_path`, `clustering_method`, `include_comparison`, `column_mapping`, `num_customers`, `num_transactions`, `total_revenue`, `num_clusters`, `silhouette_score`, `created_at`, `completed_at`, `is_saved`) VALUES
(1, '6f162d73-0f82-4dda-8bb9-c018485552cb', 4, 'pending', NULL, 'threetwentyone.csv', '/var/www/customer360/customer360/backend/data/uploads/6f162d73-0f82-4dda-8bb9-c018485552cb/threetwentyone.csv', '/var/www/customer360/customer360/backend/data/outputs/6f162d73-0f82-4dda-8bb9-c018485552cb', 'kmeans', 0, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-16 02:05:15', NULL, 0),
(2, 'c2a693bd-f7c2-4fcb-b8d3-efa6d4ee277f', 2, 'pending', NULL, 'threetwentyone.csv', '/var/www/customer360/customer360/backend/data/uploads/c2a693bd-f7c2-4fcb-b8d3-efa6d4ee277f/threetwentyone.csv', '/var/www/customer360/customer360/backend/data/outputs/c2a693bd-f7c2-4fcb-b8d3-efa6d4ee277f', 'kmeans', 0, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-16 02:07:46', NULL, 0),
(3, 'f05bbe7b-f124-42c4-94c3-a826de574d8e', 2, 'pending', NULL, 'threetwentyone.csv', '/var/www/customer360/customer360/backend/data/uploads/f05bbe7b-f124-42c4-94c3-a826de574d8e/threetwentyone.csv', '/var/www/customer360/customer360/backend/data/outputs/f05bbe7b-f124-42c4-94c3-a826de574d8e', 'kmeans', 0, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-26 00:12:31', NULL, 0),
(4, 'a6625b1f-1c67-42b0-b8d8-b606992fadb5', 2, 'pending', NULL, 'threetwentyone.csv', '/var/www/customer360/customer360/backend/data/uploads/a6625b1f-1c67-42b0-b8d8-b606992fadb5/threetwentyone.csv', '/var/www/customer360/customer360/backend/data/outputs/a6625b1f-1c67-42b0-b8d8-b606992fadb5', 'kmeans', 0, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-26 00:14:50', NULL, 0),
(5, '22b897aa-23c6-4325-b758-41bf6ca68794', 2, 'pending', NULL, 'threetwentyone.csv', '/var/www/customer360/customer360/backend/data/uploads/22b897aa-23c6-4325-b758-41bf6ca68794/threetwentyone.csv', '/var/www/customer360/customer360/backend/data/outputs/22b897aa-23c6-4325-b758-41bf6ca68794', 'kmeans', 0, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-26 00:37:04', NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `company_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hashed_password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `company_name`, `hashed_password`, `created_at`, `is_active`) VALUES
(1, 'egalezoyiku@gmail.com', 'DOXA Research LBG', '$2b$12$k/ulpyq0OSMif7Q.eqGvteSjxZismShKB3T32M6QdxROTAeCruso6', '2026-02-12 19:12:07', 1),
(2, 'akuaoduro4@gmail.com', 'liliesstore', '$2b$12$X4j.H4d38q502eZgR82U/.X6jcO.SBftZiuWZ46mCio5QSCjmsHhy', '2026-02-12 19:46:47', 1),
(3, 'nyopare98@gmail.com', 'GETFLY ENTERPRISE', '$2b$12$DuzC3VtSMPYr17acZ/S5fuZ4FH0qatarQrAknTDR/nUrJ/ii3pi6i', '2026-03-14 18:09:15', 1),
(4, 'emmaoduro4@gmail.com', 'BabyStyleMotherCare', '$2b$12$JURIFLHc3aHTR7T/dkEwnu1KpLbuZjrlK3t4sfJcpoUZPnHaa6hw.', '2026-03-16 02:01:36', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ix_items_id` (`id`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `job_id` (`job_id`),
  ADD KEY `idx_job_id` (`job_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `jobs`
--
ALTER TABLE `jobs`
  ADD CONSTRAINT `jobs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
