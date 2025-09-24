-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Sep 24, 2025 at 03:23 AM
-- Server version: 11.4.8-MariaDB-cll-lve-log
-- PHP Version: 8.3.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `umarbyhn_tabs_tracker`
--

--
-- Dumping data for table `admin_activity_log`
--

INSERT INTO `admin_activity_log` (`id`, `admin_username`, `action`, `details`, `ip_address`, `created_at`) VALUES
(1, 'office', 'Archive Report', 'Archived report ID 8', '39.35.156.162', '2025-06-19 09:26:04'),
(2, 'office', 'Unarchive Report', 'Unarchived report ID 8', '39.35.156.162', '2025-06-19 09:29:54'),
(3, 'office', 'Clean Duplicates', 'Cleaned duplicates from report ID 7. Removed 2728 duplicates, kept 2273 unique records.', '39.35.156.162', '2025-06-19 10:27:15'),
(4, 'office', 'Clean Duplicates', 'Cleaned duplicates from report ID 8. Removed 247 duplicates, kept 2120 unique records.', '39.35.156.162', '2025-06-19 10:32:40'),
(5, 'office', 'Clean Duplicates', 'Cleaned duplicates from report ID 6. Removed 2774 duplicates, kept 2227 unique records.', '39.35.156.162', '2025-06-19 10:33:51'),
(6, 'office', 'Clean Duplicates', 'Cleaned duplicates from report ID 5. Removed 2158 duplicates, kept 950 unique records.', '39.35.156.162', '2025-06-19 10:34:42'),
(7, 'office', 'Clean Duplicates', 'Cleaned duplicates from report ID 11. Removed 2948 duplicates, kept 1911 unique records.', '39.35.156.162', '2025-06-19 10:35:05'),
(8, 'office', 'Clean Duplicates', 'Cleaned duplicates from report ID 10. Removed 571 duplicates, kept 2272 unique records.', '39.35.156.162', '2025-06-19 10:35:44'),
(9, 'office', 'Delete Report', 'Deleted report for sayyamam', '39.35.156.162', '2025-06-19 10:36:09'),
(10, 'office', 'Delete Report', 'Deleted report for Nabeel', '39.35.156.162', '2025-06-19 10:36:26'),
(11, 'office', 'Delete Report', 'Deleted report for Nabeel', '39.35.156.162', '2025-06-19 10:36:31'),
(12, 'office', 'Delete Report', 'Deleted report for sayyam', '39.35.156.162', '2025-06-19 10:36:34'),
(13, 'office', 'Delete All Backup Files', 'Deleted 6 backup files, 0 errors', '39.35.156.162', '2025-06-19 10:46:51'),
(14, 'office', 'Logout', 'Admin logged out', '36.255.33.109', '2025-06-21 12:58:18'),
(15, 'office', 'Delete Report', 'Deleted report for Test User', '36.255.33.109', '2025-06-21 12:58:44'),
(16, 'office', 'Delete Report', 'Deleted report for Test User', '36.255.33.109', '2025-06-21 12:58:48'),
(17, 'office', 'Delete Report', 'Deleted report for Test User', '36.255.33.109', '2025-06-21 12:58:54'),
(18, 'office', 'Delete Report', 'Deleted report for Nabeel', '36.255.33.109', '2025-06-21 13:18:22'),
(19, 'office', 'Delete Report', 'Deleted report for Nabeel', '36.255.33.109', '2025-06-21 13:33:47'),
(20, 'office', 'Delete Report', 'Deleted report for Nabeel', '36.255.33.109', '2025-06-21 13:34:57'),
(21, 'office', 'Delete Report', 'Deleted report for Nabeel', '36.255.33.109', '2025-06-21 13:40:48'),
(22, 'office', 'Delete Report', 'Deleted report for Nabeel', '36.255.33.109', '2025-06-21 13:42:42'),
(23, 'office', 'Clean Duplicates', 'Cleaned duplicates from report ID 47. Removed 3015 duplicates, kept 1985 unique records.', '154.80.3.187', '2025-06-23 10:52:37'),
(24, 'office', 'Logout', 'Admin logged out', '223.123.4.127', '2025-08-13 08:23:37');

--
-- Dumping data for table `reports`
--

INSERT INTO `reports` (`id`, `employee_name`, `original_filename`, `decrypted_filename`, `upload_date`, `file_size`, `row_count`, `ip_address`, `category`, `admin_notes`, `is_archived`, `archived_date`) VALUES
(541, 'ali', 'Website_Tracking_ali_2025-08-12_05-03-42.encrypted.xlsx', '2025-08-13_12-27-28_ali_report.xlsx', '2025-08-13 12:27:28', 2172964, 4725, '39.35.147.100', 'General', '', 0, NULL),
(542, 'sayyam', 'Website_Tracking_sayyam_2025-08-13_17-52-55.encrypted.xlsx', '2025-08-13_12-53-26_sayyam_report.xlsx', '2025-08-13 12:53:27', 2432775, 5000, '39.35.147.100', 'General', '', 0, NULL),
(543, 'Waleed', 'Website_Tracking_HASSAN_2025-08-13_17-53-13.encrypted.xlsx', '2025-08-13_12-54-11_Waleed_report.xlsx', '2025-08-13 12:54:11', 584578, 1281, '39.35.147.100', 'General', '', 0, NULL),
(544, 'AHSAN', 'Website_Tracking_AHSAN_2025-08-13_05-55-36.encrypted.xlsx', '2025-08-13_12-55-56_AHSAN_report.xlsx', '2025-08-13 12:55:56', 1090634, 2354, '39.35.147.100', 'General', '', 0, NULL),
(545, 'naini', 'Website_Tracking_naini_2025-08-13_18-04-48.encrypted.xlsx', '2025-08-13_13-05-12_naini_report.xlsx', '2025-08-13 13:05:12', 944487, 2080, '39.35.165.239', 'General', '', 0, NULL),
(546, 'khizr', 'Website_Tracking_khizr_2025-08-13_18-37-17.encrypted.xlsx', '2025-08-13_13-37-54_khizr_report.xlsx', '2025-08-13 13:37:54', 1251116, 2715, '39.35.147.100', 'General', '', 0, NULL),
(547, 'ASAD', 'Website_Tracking_ASAD_2025-08-13_18-48-17.encrypted.xlsx', '2025-08-13_13-48-42_ASAD_report.xlsx', '2025-08-13 13:48:44', 1481386, 3248, '39.35.165.239', 'General', '', 0, NULL),
(548, 'ADAN', 'Website_Tracking_ADAN_2025-08-14_13-06-48.encrypted.xlsx', '2025-08-14_08-07-09_ADAN_report.xlsx', '2025-08-14 08:07:09', 758717, 1652, '39.35.186.226', 'General', '', 0, NULL),
(549, 'ZAIN', 'Website_Tracking_ZAIN_2025-08-14_16-37-55.encrypted.xlsx', '2025-08-14_11-38-21_ZAIN_report.xlsx', '2025-08-14 11:38:21', 2292870, 5000, '39.35.186.226', 'General', '', 0, NULL),
(550, 'ASAD', 'Website_Tracking_ASAD_2025-08-14_16-38-05.encrypted.xlsx', '2025-08-14_11-38-30_ASAD_report.xlsx', '2025-08-14 11:38:30', 1005521, 2216, '39.35.186.226', 'General', '', 0, NULL),
(551, 'AHSAN', 'Website_Tracking_AHSAN_2025-08-14_04-38-44.encrypted.xlsx', '2025-08-14_11-39-04_AHSAN_report.xlsx', '2025-08-14 11:39:04', 901873, 1963, '39.35.130.73', 'General', '', 0, NULL),
(552, 'MULLHA', 'Website_Tracking_MULLHA_2025-08-14_16-38-53.encrypted.xlsx', '2025-08-14_11-39-24_MULLHA_report.xlsx', '2025-08-14 11:39:24', 1526929, 3306, '39.35.186.226', 'General', '', 0, NULL),
(553, 'naini', 'Website_Tracking_naini_2025-08-14_16-40-11.encrypted.xlsx', '2025-08-14_11-40-32_naini_report.xlsx', '2025-08-14 11:40:32', 752370, 1658, '39.35.186.226', 'General', '', 0, NULL),
(554, 'saad', 'Website_Tracking_saad_2025-08-14_05-03-11.encrypted.xlsx', '2025-08-14_12-03-43_saad_report.xlsx', '2025-08-14 12:03:43', 980506, 2118, '39.35.130.73', 'General', '', 0, NULL),
(555, 'Fahad', 'Website_Tracking_Fahad_2025-08-15_17-02-27.encrypted.xlsx', '2025-08-14_12-03-54_Fahad_report.xlsx', '2025-08-14 12:03:54', 779871, 1692, '39.35.130.73', 'General', '', 0, NULL),
(556, 'khizr', 'Website_Tracking_khizr_2025-08-14_17-19-31.encrypted.xlsx', '2025-08-14_12-20-00_khizr_report.xlsx', '2025-08-14 12:20:00', 1840228, 4001, '39.35.130.73', 'General', '', 0, NULL),
(557, 'Waleed', 'Website_Tracking_Waleed_2025-08-14_17-30-00.encrypted.xlsx', '2025-08-14_12-30-24_Waleed_report.xlsx', '2025-08-14 12:30:24', 678184, 1488, '39.35.186.226', 'General', '', 0, NULL),
(558, 'ali', 'Website_Tracking_ali_2025-08-14_07-57-07.encrypted.xlsx', '2025-08-14_14-57-46_ali_report.xlsx', '2025-08-14 14:57:46', 1158757, 2521, '103.120.71.77', 'General', '', 0, NULL),
(559, 'AHSAN', 'Website_Tracking_AHSAN_2025-09-04_22-41-27.encrypted.xlsx', '2025-09-04_05-41-44_AHSAN_report.xlsx', '2025-09-04 05:41:45', 1284320, 2780, '39.35.131.248', 'General', '', 0, NULL),
(560, 'AHSAN', 'Website_Tracking_AHSAN_2025-09-04_07-00-34.encrypted.xlsx', '2025-09-04_14-01-58_AHSAN_report.xlsx', '2025-09-04 14:01:58', 1841478, 4010, '39.35.150.36', 'General', '', 0, NULL),
(561, 'AHSAN', 'Website_Tracking_AHSAN_2025-09-05_07-43-41.encrypted.xlsx', '2025-09-05_14-44-05_AHSAN_report.xlsx', '2025-09-05 14:44:05', 1394362, 3039, '39.35.167.182', 'General', '', 0, NULL),
(562, 'AHSAN', 'Website_Tracking_AHSAN_2025-09-06_07-18-14.encrypted.xlsx', '2025-09-06_14-18-45_AHSAN_report.xlsx', '2025-09-06 14:18:45', 1529139, 3302, '39.35.149.253', 'General', '', 0, NULL),
(563, 'Fahad', 'Website_Tracking_Fahad_2025-09-08_16-08-01.encrypted.xlsx', '2025-09-08_11-10-09_Fahad_report.xlsx', '2025-09-08 11:10:09', 620153, 1329, '39.35.165.104', 'General', '', 0, NULL),
(564, 'Fahad', 'Website_Tracking_Fahad_2025-09-08_16-08-01.encrypted.xlsx', '2025-09-08_11-10-34_Fahad_report.xlsx', '2025-09-08 11:10:34', 620153, 1329, '39.35.165.104', 'General', '', 0, NULL),
(565, 'saad', 'Website_Tracking_SAAD_2025-09-08_16-42-11.encrypted.xlsx', '2025-09-08_11-42-37_saad_report.xlsx', '2025-09-08 11:42:37', 604197, 1288, '39.35.165.104', 'General', '', 0, NULL),
(566, 'waleed', 'Website_Tracking_WALEED_2025-09-08_17-56-43.encrypted.xlsx', '2025-09-08_13-00-19_waleed_report.xlsx', '2025-09-08 13:00:19', 2314211, 5000, '39.35.165.104', 'General', '', 0, NULL),
(567, 'ASAD', 'Website_Tracking_ASAD_2025-09-08_18-59-55.encrypted.xlsx', '2025-09-08_14-00-17_ASAD_report.xlsx', '2025-09-08 14:00:17', 1267549, 2744, '39.35.165.104', 'General', '', 0, NULL),
(568, 'saad', 'Website_Tracking_SAAD_2025-09-08_19-00-07.encrypted.xlsx', '2025-09-08_14-00-36_saad_report.xlsx', '2025-09-08 14:00:36', 909681, 1948, '39.35.165.104', 'General', '', 0, NULL),
(569, 'ZAIN', 'Website_Tracking_ZAIN_2025-09-08_14-00-38.encrypted.xlsx', '2025-09-08_14-02-42_ZAIN_report.xlsx', '2025-09-08 14:02:42', 225592, 469, '39.35.165.104', 'General', '', 0, NULL),
(570, 'ZAIN', 'Website_Tracking_ZAIN_2025-09-08_19-02-22.encrypted.xlsx', '2025-09-08_14-02-57_ZAIN_report.xlsx', '2025-09-08 14:02:57', 1250289, 2708, '39.35.165.104', 'General', '', 0, NULL),
(571, 'AHSAN', 'Website_Tracking_AHSAN_2025-09-08_07-05-56.encrypted.xlsx', '2025-09-08_14-06-35_AHSAN_report.xlsx', '2025-09-08 14:06:35', 1029262, 2220, '39.35.165.104', 'General', '', 0, NULL),
(572, 'saad', 'Website_Tracking_saad_2025-09-09_03-44-56.encrypted.xlsx', '2025-09-09_10-45-31_saad_report.xlsx', '2025-09-09 10:45:32', 354378, 755, '39.35.139.228', 'General', '', 0, NULL),
(573, 'ZAIN', 'Website_Tracking_ZAIN_2025-09-09_15-43-55.encrypted.xlsx', '2025-09-09_10-46-07_ZAIN_report.xlsx', '2025-09-09 10:46:07', 383711, 817, '39.35.139.228', 'General', '', 0, NULL),
(574, 'ASAD', 'Website_Tracking_ASAD_2025-09-09_15-47-40.encrypted.xlsx', '2025-09-09_10-47-58_ASAD_report.xlsx', '2025-09-09 10:47:58', 373125, 792, '39.35.129.201', 'General', '', 0, NULL),
(575, 'saad', 'Website_Tracking_saad_2025-09-09_07-04-27.encrypted.xlsx', '2025-09-09_14-04-58_saad_report.xlsx', '2025-09-09 14:04:58', 935459, 2053, '39.35.138.14', 'General', '', 0, NULL),
(576, 'WALEED', 'Website_Tracking_WALEED_2025-09-08_17-56-43.encrypted.xlsx', '2025-09-09_14-05-15_WALEED_report.xlsx', '2025-09-09 14:05:15', 2314211, 5000, '39.35.129.201', 'General', '', 0, NULL),
(577, 'khizr', 'Website_Tracking_khizr_2025-09-09_19-31-14.encrypted.xlsx', '2025-09-09_14-32-03_khizr_report.xlsx', '2025-09-09 14:32:03', 2266315, 5000, '39.35.138.14', 'General', '', 0, NULL),
(578, 'ASAD', 'Website_Tracking_ASAD_2025-09-09_19-40-15.encrypted.xlsx', '2025-09-09_14-40-50_ASAD_report.xlsx', '2025-09-09 14:40:50', 1021361, 2239, '39.35.129.201', 'General', '', 0, NULL),
(579, 'ZAIN', 'Website_Tracking_ZAIN_2025-09-09_19-42-30.encrypted.xlsx', '2025-09-09_14-42-53_ZAIN_report.xlsx', '2025-09-09 14:42:53', 1266135, 2834, '39.35.129.201', 'General', '', 0, NULL),
(580, 'AHSAN', 'Website_Tracking_AHSAN_2025-09-09_07-49-40.encrypted.xlsx', '2025-09-09_14-50-13_AHSAN_report.xlsx', '2025-09-09 14:50:13', 1917820, 4254, '39.35.129.201', 'General', '', 0, NULL),
(581, 'Ali', 'Website_Tracking_Ali_2025-09-09_03-57-49.encrypted.xlsx', '2025-09-09_15-09-05_Ali_report.xlsx', '2025-09-09 15:09:05', 468393, 1020, '103.120.71.77', 'General', '', 0, NULL),
(582, 'Ali', 'Website_Tracking_Ali_2025-09-09_07-37-55.encrypted.xlsx', '2025-09-09_15-09-26_Ali_report.xlsx', '2025-09-09 15:09:27', 2243844, 5000, '103.120.71.77', 'General', '', 0, NULL),
(583, 'waleed', 'Website_Tracking_WALEED_2025-09-10_17-47-08.encrypted.xlsx', '2025-09-10_12-47-32_waleed_report.xlsx', '2025-09-10 12:47:33', 1800698, 3950, '39.35.144.33', 'General', '', 0, NULL),
(584, 'Ali', 'Website_Tracking_Ali_2025-09-10_09-21-15.encrypted.xlsx', '2025-09-10_16-22-41_Ali_report.xlsx', '2025-09-10 16:22:42', 2248678, 5000, '39.35.158.78', 'General', '', 0, NULL),
(585, 'AHSAN', 'Website_Tracking_AHSAN_2025-09-10_09-24-52.encrypted.xlsx', '2025-09-10_16-26-29_AHSAN_report.xlsx', '2025-09-10 16:26:30', 2244791, 5000, '39.35.158.78', 'General', '', 0, NULL),
(586, 'AHSAN', 'Website_Tracking_AHSAN_2025-09-11_03-14-42.encrypted.xlsx', '2025-09-11_10-15-24_AHSAN_report.xlsx', '2025-09-11 10:15:24', 2243022, 5000, '39.35.158.78', 'General', '', 0, NULL),
(587, 'waleed', 'Website_Tracking_WALEED_2025-09-12_15-31-33.encrypted.xlsx', '2025-09-12_10-32-09_waleed_report.xlsx', '2025-09-12 10:32:09', 2282720, 5000, '39.35.144.33', 'General', '', 0, NULL),
(588, 'waleed', 'Website_Trackingwaleed _2025-09-13_18-27-30.encrypted.xlsx', '2025-09-12_13-31-32_waleed_report.xlsx', '2025-09-12 13:31:32', 708602, 1552, '39.35.135.52', 'General', '', 0, NULL),
(589, 'AHSAN', 'Website_Tracking_AHSAN_2025-09-12_06-50-16.encrypted.xlsx', '2025-09-12_13-50-49_AHSAN_report.xlsx', '2025-09-12 13:50:50', 972071, 2104, '39.35.135.52', 'General', '', 0, NULL),
(590, 'khizr', 'Website_Tracking_khizr_2025-09-12_19-53-51.encrypted.xlsx', '2025-09-12_14-54-55_khizr_report.xlsx', '2025-09-12 14:54:55', 2263237, 5000, '39.35.173.190', 'General', '', 0, NULL),
(591, 'Ali', 'Website_Tracking_Ali_2025-09-13_21-23-28.encrypted.xlsx', '2025-09-13_04-24-28_Ali_report.xlsx', '2025-09-13 04:24:28', 2021015, 4464, '103.120.71.77', 'General', '', 0, NULL),
(592, 'AHSAN', 'Website_Tracking_AHSAN_2025-09-13_07-03-34.encrypted.xlsx', '2025-09-13_14-03-54_AHSAN_report.xlsx', '2025-09-13 14:03:54', 359022, 763, '39.35.135.52', 'General', '', 0, NULL),
(593, 'AHSAN', 'Website_Tracking_Ahsan_2025-09-15_19-05-02.encrypted.xlsx', '2025-09-15_14-05-13_AHSAN_report.xlsx', '2025-09-15 14:05:14', 403050, 854, '39.35.130.164', 'General', '', 0, NULL),
(594, 'Ali', 'Website_Tracking_Ali_2025-09-16_22-58-52.encrypted.xlsx', '2025-09-16_05-59-42_Ali_report.xlsx', '2025-09-16 05:59:42', 687135, 1490, '39.35.130.164', 'General', '', 0, NULL),
(595, 'AHSAN', 'Website_Tracking_Ahsan_2025-09-15_19-05-02.encrypted.xlsx', '2025-09-17_07-43-33_AHSAN_report.xlsx', '2025-09-17 07:43:33', 403050, 854, '39.35.191.92', 'General', '', 0, NULL),
(596, 'AHSAN', 'Website_Tracking_Ahsan_2025-09-15_19-05-02.encrypted.xlsx', '2025-09-20_12-42-21_AHSAN_report.xlsx', '2025-09-20 12:42:21', 403050, 854, '39.35.182.188', 'General', '', 0, NULL);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
