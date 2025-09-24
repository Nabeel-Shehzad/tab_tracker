-- Create the correct scraping tables to match PHP code expectations
-- Based on scraping_schema.sql but with correct table names

CREATE TABLE IF NOT EXISTS `scraping_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_by` varchar(100) NOT NULL,
  `status` enum('pending','running','completed','failed','paused','cancelled') NOT NULL DEFAULT 'pending',
  `total_urls` int(11) NOT NULL DEFAULT 0,
  `processed_urls` int(11) NOT NULL DEFAULT 0,
  `successful_urls` int(11) NOT NULL DEFAULT 0,
  `failed_urls` int(11) NOT NULL DEFAULT 0,
  `total_emails_found` int(11) NOT NULL DEFAULT 0,
  `priority` enum('low','normal','medium','high') NOT NULL DEFAULT 'normal',
  `settings` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `last_activity` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status_created` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `scraping_urls` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `job_id` int(11) NOT NULL,
  `url` text NOT NULL,
  `url_hash` char(32) NOT NULL,
  `domain` varchar(255) NOT NULL,
  `status` enum('pending','processing','completed','failed','skipped','timeout') NOT NULL DEFAULT 'pending',
  `priority` tinyint(4) NOT NULL DEFAULT 5,
  `emails_found` int(11) NOT NULL DEFAULT 0,
  `page_title` varchar(500) DEFAULT NULL,
  `response_code` int(11) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `processing_time` decimal(8,3) DEFAULT NULL,
  `worker_id` varchar(50) DEFAULT NULL,
  `retry_count` tinyint(4) NOT NULL DEFAULT 0,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_job_url_hash` (`job_id`, `url_hash`),
  KEY `idx_job_status` (`job_id`, `status`),
  CONSTRAINT `fk_scraping_urls_job` FOREIGN KEY (`job_id`) REFERENCES `scraping_jobs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `scraping_emails` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `job_id` int(11) NOT NULL,
  `url_id` bigint(20) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_hash` char(32) NOT NULL,
  `domain` varchar(100) NOT NULL,
  `local_part` varchar(150) NOT NULL,
  `context_text` text DEFAULT NULL,
  `extraction_method` enum('regex','mailto','structured','obfuscated') NOT NULL DEFAULT 'regex',
  `is_valid` boolean NOT NULL DEFAULT 1,
  `confidence_score` decimal(3,2) NOT NULL DEFAULT 1.00,
  `found_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_job_email` (`job_id`, `email_hash`),
  KEY `idx_job_url` (`job_id`, `url_id`),
  CONSTRAINT `fk_scraping_emails_job` FOREIGN KEY (`job_id`) REFERENCES `scraping_jobs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_scraping_emails_url` FOREIGN KEY (`url_id`) REFERENCES `scraping_urls` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `scraping_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `job_id` int(11) NOT NULL,
  `url_id` bigint(20) DEFAULT NULL,
  `level` enum('debug','info','warning','error','critical') NOT NULL DEFAULT 'info',
  `message` text NOT NULL,
  `context_data` text DEFAULT NULL,
  `worker_id` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_job_level` (`job_id`, `level`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_scraping_logs_job` FOREIGN KEY (`job_id`) REFERENCES `scraping_jobs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `scraping_workers` (
  `id` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `status` enum('active','idle','offline','error') NOT NULL DEFAULT 'idle',
  `current_job_id` int(11) DEFAULT NULL,
  `processed_urls` int(11) NOT NULL DEFAULT 0,
  `emails_found` int(11) NOT NULL DEFAULT 0,
  `last_activity` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_last_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `scraping_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_key` varchar(100) NOT NULL,
  `config_value` text NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_config_key` (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default configuration
INSERT IGNORE INTO `scraping_config` (`config_key`, `config_value`, `description`) VALUES
('max_workers', '8', 'Maximum number of concurrent workers'),
('max_concurrent_per_worker', '10', 'Maximum concurrent requests per worker'),
('default_delay', '1.0', 'Default delay between requests in seconds'),
('max_retries', '3', 'Maximum retry attempts for failed URLs'),
('timeout', '30', 'Request timeout in seconds'),
('user_agent', 'ScrapingBot/1.0 (+https://example.com/bot)', 'User agent string for requests');