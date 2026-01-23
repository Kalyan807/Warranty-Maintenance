-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 23, 2026 at 06:08 AM
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
-- Database: `warrantymaintenance`
--

-- --------------------------------------------------------

--
-- Table structure for table `issues`
--

CREATE TABLE `issues` (
  `id` int(11) NOT NULL,
  `appliance` varchar(50) DEFAULT NULL,
  `model_number` varchar(100) DEFAULT NULL,
  `reported_by` int(11) DEFAULT NULL,
  `issue_description` text DEFAULT NULL,
  `status` enum('Pending','In Progress','Resolved','Closed') DEFAULT 'Pending',
  `assigned_technician_id` int(11) DEFAULT NULL,
  `supervisor_comment` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `issues`
--

INSERT INTO `issues` (`id`, `appliance`, `model_number`, `reported_by`, `issue_description`, `status`, `assigned_technician_id`, `supervisor_comment`, `created_at`, `updated_at`) VALUES
(13, 'Television', NULL, 9, 'not working properly', 'Pending', NULL, NULL, '2026-01-07 09:21:55', '2026-01-07 09:21:55'),
(14, 'Television', NULL, 9, 'not working properly', 'Pending', NULL, NULL, '2026-01-07 09:24:19', '2026-01-07 09:24:19'),
(15, 'Television', NULL, 9, 'not working properly', 'Pending', NULL, NULL, '2026-01-07 09:24:51', '2026-01-07 09:24:51'),
(16, 'Television', NULL, 9, 'not working properly', 'Pending', NULL, NULL, '2026-01-07 09:25:01', '2026-01-07 09:25:01'),
(17, 'Air Conditioner', NULL, 9, 'not working properly', 'Pending', NULL, NULL, '2026-01-08 10:18:05', '2026-01-08 10:18:05');

-- --------------------------------------------------------

--
-- Table structure for table `service_reports`
--

CREATE TABLE `service_reports` (
  `id` int(11) NOT NULL,
  `issue_id` int(11) NOT NULL,
  `work_done` text NOT NULL,
  `parts_replaced` varchar(255) DEFAULT NULL,
  `service_cost` decimal(10,2) NOT NULL,
  `before_photo` varchar(255) DEFAULT NULL,
  `after_photo` varchar(255) DEFAULT NULL,
  `additional_photos` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `supervisors`
--

CREATE TABLE `supervisors` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supervisors`
--

INSERT INTO `supervisors` (`id`, `full_name`, `email`, `phone`, `address`, `password`, `created_at`) VALUES
(2, 'Keerthipati Kalyan', 'varmakalyan493@gmail.com', '8688435348', 'chennai- 408', '$2y$10$ys66zM0PfVITxxrlG4RrzuJZEuw1awctTgI8MUo76JQ6cFYuPGuyC', '2026-01-07 03:30:22');

-- --------------------------------------------------------

--
-- Table structure for table `technicians`
--

CREATE TABLE `technicians` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(150) NOT NULL,
  `experience_years` int(11) NOT NULL DEFAULT 0,
  `specialization` varchar(50) NOT NULL,
  `address` text NOT NULL,
  `id_proof_path` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `password` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `technicians`
--

INSERT INTO `technicians` (`id`, `name`, `phone`, `email`, `experience_years`, `specialization`, `address`, `id_proof_path`, `created_at`, `password`) VALUES
(9, 'umar', '8643584569', '0', 2, 'AC Technician', 'chennai', 'uploads/technician_ids/id_1767756704_df1fe8301bd3.jpg', '2026-01-07 09:01:44', NULL),
(10, 'umar', '6484565585', 'umar@gmail.com', 0, 'General', 'chennai-67', NULL, '2026-01-07 09:07:48', '$2y$10$S0.NbW/v3Nz0USgSvIFFQetYtjF2tbAEw5LeH5gbgkmwz6/INqvgK'),
(11, 'kalyan', '8688435348', '0', 2, 'AC Technician', 'gshsbsv', 'uploads/technician_ids/id_1767847548_32e73db1e030.jpg', '2026-01-08 10:15:48', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reset_token_hash` varchar(64) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `role` varchar(20) DEFAULT 'USER'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `phone`, `password`, `created_at`, `reset_token_hash`, `reset_expires`, `role`) VALUES
(9, 'praveen', 'praveen@gmail.com', '8973456290', '$2y$10$6xL8hHC9UnM4vhlpxGvgjuEu0o3A23.tuN.ndsAQ7SMxe/v44/Npu', '2026-01-07 03:47:54', NULL, NULL, 'USER');

-- --------------------------------------------------------

--
-- Table structure for table `warranty_records`
--

CREATE TABLE `warranty_records` (
  `id` int(11) NOT NULL,
  `appliance` varchar(50) NOT NULL,
  `model_number` varchar(100) NOT NULL,
  `purchase_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `maintenance_frequency` varchar(100) NOT NULL,
  `notes` text DEFAULT NULL,
  `document_path` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `warranty_records`
--

INSERT INTO `warranty_records` (`id`, `appliance`, `model_number`, `purchase_date`, `expiry_date`, `maintenance_frequency`, `notes`, `document_path`, `created_at`) VALUES
(5, 'Television', 'lg2000', '2026-01-01', '2026-01-10', 'every 2 months', 'dhjskdnsjsjs', 'uploads/warranty_docs/doc_1767756881_563f4e3be71b.jpg', '2026-01-07 09:04:41'),
(6, 'Air Conditioner', 'lg1155', '2026-01-08', '2026-01-08', '3 months', 'food', 'uploads/warranty_docs/doc_1767847588_7c4328f5ddba.jpg', '2026-01-08 10:16:28');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `issues`
--
ALTER TABLE `issues`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `service_reports`
--
ALTER TABLE `service_reports`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `supervisors`
--
ALTER TABLE `supervisors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `technicians`
--
ALTER TABLE `technicians`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `warranty_records`
--
ALTER TABLE `warranty_records`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `issues`
--
ALTER TABLE `issues`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `service_reports`
--
ALTER TABLE `service_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `supervisors`
--
ALTER TABLE `supervisors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `technicians`
--
ALTER TABLE `technicians`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `warranty_records`
--
ALTER TABLE `warranty_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
