-- Employee Tracker System Database Schema
-- MySQL Database Setup for cPanel Hosting
-- 
-- Instructions:
-- 1. Create a MySQL database in cPanel (e.g., 'umarbyhn_tabs_tracker')
-- 2. Import this SQL file or run these commands manually
-- 3. Update the database credentials in common/config_mysql.php
--
-- Database: umarbyhn_tabs_tracker
-- Created: June 18, 2025
-- Version: 1.0

-- --------------------------------------------------------

--
-- Table structure for table `reports`
-- This table stores all uploaded employee reports
--

CREATE TABLE IF NOT EXISTS `reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_name` varchar(100) NOT NULL COMMENT 'Name of the employee who uploaded the report',
  `original_filename` varchar(255) NOT NULL COMMENT 'Original filename of the uploaded encrypted file',
  `decrypted_filename` varchar(255) NOT NULL COMMENT 'Filename of the decrypted Excel file',
  `upload_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date and time when the file was uploaded',
  `file_size` bigint(20) DEFAULT NULL COMMENT 'Size of the uploaded file in bytes',
  `row_count` int(11) DEFAULT 0 COMMENT 'Number of data rows in the Excel file',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP address of the uploader',
  PRIMARY KEY (`id`),
  KEY `idx_employee_name` (`employee_name`),
  KEY `idx_upload_date` (`upload_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores employee website tracking reports';

-- --------------------------------------------------------

--
-- Insert sample data (optional - remove if not needed)
--

-- INSERT INTO `reports` (`employee_name`, `original_filename`, `decrypted_filename`, `file_size`, `row_count`, `ip_address`) VALUES
-- ('John Doe', 'john_doe_report_2025-06-18.encrypted.xlsx', 'john_doe_report_2025-06-18.xlsx', 45678, 125, '192.168.1.100'),
-- ('Jane Smith', 'jane_smith_report_2025-06-18.encrypted.xlsx', 'jane_smith_report_2025-06-18.xlsx', 52341, 98, '192.168.1.101');

-- --------------------------------------------------------

--
-- Additional indexes for performance optimization
--

-- Index for faster searches by employee name and date range
CREATE INDEX idx_employee_date ON `reports` (`employee_name`, `upload_date`);

-- Index for file size queries (useful for storage management)
CREATE INDEX idx_file_size ON `reports` (`file_size`);

-- --------------------------------------------------------

--
-- Database configuration notes:
--
-- 1. Character Set: utf8mb4 (supports full UTF-8 including emojis)
-- 2. Collation: utf8mb4_unicode_ci (case-insensitive Unicode sorting)
-- 3. Engine: InnoDB (supports transactions and foreign keys)
-- 4. Auto-increment starts at 1
--
-- Security considerations:
-- - All user inputs are sanitized through PHP PDO prepared statements
-- - File uploads are restricted to .encrypted.xlsx extension
-- - File sizes are limited to 50MB maximum
-- - IP addresses are logged for audit purposes
--
-- Storage estimates:
-- - Average row size: ~500 bytes
-- - 1000 reports ≈ 500KB
-- - 10,000 reports ≈ 5MB
-- - 100,000 reports ≈ 50MB
--
-- Backup recommendations:
-- - Regular database backups via cPanel
-- - Export reports table monthly
-- - Monitor disk space usage
--
-- --------------------------------------------------------

-- End of SQL file
