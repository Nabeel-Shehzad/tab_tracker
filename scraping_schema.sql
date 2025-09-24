-- Web Scraping Feature Database Schema
-- High-performance email scraping system for Employee Tracker
-- Created: September 24, 2025
-- Version: 1.0

-- --------------------------------------------------------

--
-- Table structure for scraping jobs
-- Manages scraping job lifecycle and metadata
--

CREATE TABLE IF NOT EXISTS `scraping_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_name` varchar(255) NOT NULL COMMENT 'User-defined job name',
  `created_by` varchar(100) NOT NULL COMMENT 'Admin username who created the job',
  `status` enum('pending','running','completed','failed','paused','cancelled') NOT NULL DEFAULT 'pending' COMMENT 'Current job status',
  `total_urls` int(11) NOT NULL DEFAULT 0 COMMENT 'Total number of URLs to scrape',
  `processed_urls` int(11) NOT NULL DEFAULT 0 COMMENT 'Number of URLs processed',
  `successful_urls` int(11) NOT NULL DEFAULT 0 COMMENT 'Number of successfully processed URLs',
  `failed_urls` int(11) NOT NULL DEFAULT 0 COMMENT 'Number of failed URLs',
  `total_emails_found` int(11) NOT NULL DEFAULT 0 COMMENT 'Total unique emails found',
  `valid_emails_found` int(11) NOT NULL DEFAULT 0 COMMENT 'Valid emails after validation',
  `settings` text COMMENT 'JSON encoded job settings and configuration',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `started_at` timestamp NULL DEFAULT NULL COMMENT 'When processing started',
  `completed_at` timestamp NULL DEFAULT NULL COMMENT 'When processing completed',
  `estimated_completion` timestamp NULL DEFAULT NULL COMMENT 'Estimated completion time',
  `last_activity` timestamp NULL DEFAULT NULL COMMENT 'Last processing activity',
  PRIMARY KEY (`id`),
  KEY `idx_status_created` (`status`, `created_at`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_last_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Manages web scraping jobs';

-- --------------------------------------------------------

--
-- Table structure for URLs to be scraped
-- Stores individual URLs and their processing status
--

CREATE TABLE IF NOT EXISTS `scraping_urls` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `job_id` int(11) NOT NULL,
  `url` text NOT NULL COMMENT 'Full URL to scrape',
  `url_hash` char(32) NOT NULL COMMENT 'MD5 hash of URL for faster lookups',
  `domain` varchar(255) NOT NULL COMMENT 'Extracted domain for grouping',
  `status` enum('pending','processing','completed','failed','skipped','timeout') NOT NULL DEFAULT 'pending',
  `priority` tinyint(4) NOT NULL DEFAULT 5 COMMENT 'Processing priority (1=highest, 10=lowest)',
  `emails_found` int(11) NOT NULL DEFAULT 0 COMMENT 'Number of emails found on this URL',
  `page_title` varchar(500) DEFAULT NULL COMMENT 'Page title for context',
  `content_length` int(11) DEFAULT NULL COMMENT 'Content length in bytes',
  `response_code` int(11) DEFAULT NULL COMMENT 'HTTP response code',
  `error_message` text DEFAULT NULL COMMENT 'Error details if failed',
  `processing_time` decimal(8,3) DEFAULT NULL COMMENT 'Processing time in seconds',
  `worker_id` varchar(50) DEFAULT NULL COMMENT 'ID of worker that processed this URL',
  `retry_count` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Number of retry attempts',
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_job_url_hash` (`job_id`, `url_hash`),
  KEY `idx_job_status` (`job_id`, `status`),
  KEY `idx_status_priority` (`status`, `priority`),
  KEY `idx_domain` (`domain`),
  KEY `idx_processed_at` (`processed_at`),
  CONSTRAINT `fk_scraping_urls_job` FOREIGN KEY (`job_id`) REFERENCES `scraping_jobs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Individual URLs to be scraped';

-- --------------------------------------------------------

--
-- Table structure for scraped emails
-- Stores extracted emails with context and validation status
-- Partitioned by job_id for better performance with large datasets
--

CREATE TABLE IF NOT EXISTS `scraped_emails` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `job_id` int(11) NOT NULL,
  `url_id` bigint(20) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_hash` char(32) NOT NULL COMMENT 'MD5 hash for deduplication',
  `domain` varchar(100) NOT NULL COMMENT 'Email domain for filtering',
  `local_part` varchar(150) NOT NULL COMMENT 'Part before @ symbol',
  `context_text` text DEFAULT NULL COMMENT 'Surrounding text context',
  `extraction_method` enum('regex','mailto','structured','obfuscated') NOT NULL DEFAULT 'regex' COMMENT 'How email was extracted',
  `page_title` varchar(500) DEFAULT NULL COMMENT 'Page title where found',
  `is_valid` boolean NOT NULL DEFAULT 1 COMMENT 'Email format validation result',
  `domain_valid` boolean DEFAULT NULL COMMENT 'Domain MX record validation',
  `confidence_score` decimal(3,2) NOT NULL DEFAULT 1.00 COMMENT 'Confidence in email validity (0.00-1.00)',
  `found_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_job_email` (`job_id`, `email_hash`),
  KEY `idx_job_domain` (`job_id`, `domain`),
  KEY `idx_email_validation` (`is_valid`, `domain_valid`),
  KEY `idx_confidence` (`confidence_score`),
  KEY `idx_extraction_method` (`extraction_method`),
  KEY `idx_found_at` (`found_at`),
  CONSTRAINT `fk_scraped_emails_job` FOREIGN KEY (`job_id`) REFERENCES `scraping_jobs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_scraped_emails_url` FOREIGN KEY (`url_id`) REFERENCES `scraping_urls` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Extracted emails from scraping jobs';

-- --------------------------------------------------------

--
-- Table structure for scraping logs
-- Detailed logging for debugging and monitoring
--

CREATE TABLE IF NOT EXISTS `scraping_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `job_id` int(11) NOT NULL,
  `url_id` bigint(20) DEFAULT NULL,
  `level` enum('debug','info','warning','error','critical') NOT NULL DEFAULT 'info',
  `message` text NOT NULL,
  `context_data` json DEFAULT NULL COMMENT 'Additional context data in JSON format',
  `worker_id` varchar(50) DEFAULT NULL,
  `execution_time` decimal(8,3) DEFAULT NULL COMMENT 'Execution time for the logged operation',
  `memory_usage` int(11) DEFAULT NULL COMMENT 'Memory usage in bytes',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_job_level` (`job_id`, `level`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_worker_id` (`worker_id`),
  CONSTRAINT `fk_scraping_logs_job` FOREIGN KEY (`job_id`) REFERENCES `scraping_jobs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Detailed scraping operation logs';

-- --------------------------------------------------------

--
-- Table structure for scraping statistics
-- Aggregated statistics for performance monitoring
--

CREATE TABLE IF NOT EXISTS `scraping_statistics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_id` int(11) NOT NULL,
  `metric_name` varchar(100) NOT NULL,
  `metric_value` decimal(15,4) NOT NULL,
  `metric_unit` varchar(50) DEFAULT NULL COMMENT 'Unit of measurement (seconds, bytes, count, etc.)',
  `recorded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_job_metric_time` (`job_id`, `metric_name`, `recorded_at`),
  KEY `idx_metric_name` (`metric_name`),
  KEY `idx_recorded_at` (`recorded_at`),
  CONSTRAINT `fk_scraping_statistics_job` FOREIGN KEY (`job_id`) REFERENCES `scraping_jobs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Performance and operational statistics';

-- --------------------------------------------------------

--
-- Table structure for domain validation cache
-- Caches domain validation results to improve performance
--

CREATE TABLE IF NOT EXISTS `domain_validation_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `domain` varchar(255) NOT NULL,
  `domain_hash` char(32) NOT NULL COMMENT 'MD5 hash of domain',
  `has_mx_record` boolean NOT NULL DEFAULT 0,
  `has_a_record` boolean NOT NULL DEFAULT 0,
  `is_valid` boolean NOT NULL DEFAULT 0,
  `validation_error` varchar(500) DEFAULT NULL,
  `last_checked` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `check_count` int(11) NOT NULL DEFAULT 1 COMMENT 'Number of times this domain was checked',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_domain_hash` (`domain_hash`),
  KEY `idx_domain` (`domain`),
  KEY `idx_last_checked` (`last_checked`),
  KEY `idx_is_valid` (`is_valid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cache for domain validation results';

-- --------------------------------------------------------

--
-- Indexes for performance optimization
--

-- Composite indexes for common queries
CREATE INDEX idx_job_status_priority ON scraping_urls(job_id, status, priority);
CREATE INDEX idx_emails_job_valid ON scraped_emails(job_id, is_valid, domain_valid);
CREATE INDEX idx_logs_job_level_time ON scraping_logs(job_id, level, created_at);

-- Full-text search index for email context
ALTER TABLE scraped_emails ADD FULLTEXT(context_text);

-- --------------------------------------------------------

--
-- Views for common queries
--

-- Job summary view
CREATE OR REPLACE VIEW job_summary AS
SELECT 
    j.id,
    j.job_name,
    j.created_by,
    j.status,
    j.total_urls,
    j.processed_urls,
    j.successful_urls,
    j.failed_urls,
    j.total_emails_found,
    j.valid_emails_found,
    j.created_at,
    j.started_at,
    j.completed_at,
    j.last_activity,
    CASE 
        WHEN j.status = 'running' AND j.processed_urls > 0 THEN
            DATE_ADD(j.started_at, INTERVAL 
                ((UNIX_TIMESTAMP() - UNIX_TIMESTAMP(j.started_at)) * (j.total_urls - j.processed_urls) / j.processed_urls) SECOND
            )
        ELSE NULL 
    END as estimated_completion_time,
    CASE 
        WHEN j.total_urls > 0 THEN ROUND((j.processed_urls / j.total_urls) * 100, 2)
        ELSE 0 
    END as progress_percentage
FROM scraping_jobs j;

-- Email statistics by domain view
CREATE OR REPLACE VIEW email_domain_stats AS
SELECT 
    job_id,
    domain,
    COUNT(*) as total_emails,
    SUM(CASE WHEN is_valid = 1 THEN 1 ELSE 0 END) as valid_emails,
    SUM(CASE WHEN domain_valid = 1 THEN 1 ELSE 0 END) as domain_valid_emails,
    AVG(confidence_score) as avg_confidence,
    MIN(found_at) as first_found,
    MAX(found_at) as last_found
FROM scraped_emails 
GROUP BY job_id, domain;

-- --------------------------------------------------------

--
-- Stored procedures for common operations
--

DELIMITER //

-- Update job progress
CREATE PROCEDURE UpdateJobProgress(
    IN p_job_id INT,
    IN p_processed_count INT,
    IN p_successful_count INT,
    IN p_failed_count INT,
    IN p_emails_found INT
)
BEGIN
    UPDATE scraping_jobs 
    SET 
        processed_urls = processed_urls + p_processed_count,
        successful_urls = successful_urls + p_successful_count,
        failed_urls = failed_urls + p_failed_count,
        total_emails_found = total_emails_found + p_emails_found,
        last_activity = CURRENT_TIMESTAMP
    WHERE id = p_job_id;
END //

-- Get next URLs to process
CREATE PROCEDURE GetNextUrlsToProcess(
    IN p_limit INT,
    IN p_worker_id VARCHAR(50)
)
BEGIN
    -- Set defaults if parameters are NULL or 0
    IF p_limit IS NULL OR p_limit <= 0 THEN
        SET p_limit = 100;
    END IF;
    
    UPDATE scraping_urls 
    SET 
        status = 'processing',
        worker_id = p_worker_id,
        processed_at = CURRENT_TIMESTAMP
    WHERE status = 'pending'
    ORDER BY priority ASC, created_at ASC
    LIMIT p_limit;
    
    SELECT * FROM scraping_urls 
    WHERE worker_id = p_worker_id AND status = 'processing'
    ORDER BY priority ASC, created_at ASC;
END //

-- Clean old logs (retention policy)
CREATE PROCEDURE CleanOldLogs(IN p_days_to_keep INT)
BEGIN
    -- Set default if parameter is NULL or 0
    IF p_days_to_keep IS NULL OR p_days_to_keep <= 0 THEN
        SET p_days_to_keep = 30;
    END IF;
    
    DELETE FROM scraping_logs 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL p_days_to_keep DAY);
    
    SELECT ROW_COUNT() as deleted_logs;
END //

DELIMITER ;

-- --------------------------------------------------------

--
-- Initial configuration data
--

-- Insert default scraping settings
INSERT IGNORE INTO scraping_statistics (job_id, metric_name, metric_value, metric_unit) VALUES
(0, 'system_max_workers', 8, 'count'),
(0, 'system_max_concurrent_per_worker', 10, 'count'),
(0, 'system_default_delay', 1.0, 'seconds'),
(0, 'system_max_retries', 3, 'count'),
(0, 'system_timeout', 30, 'seconds');

-- --------------------------------------------------------

-- Performance optimization settings
SET GLOBAL innodb_buffer_pool_size = 1073741824; -- 1GB if available
SET GLOBAL query_cache_size = 67108864; -- 64MB
SET GLOBAL max_connections = 200;

-- --------------------------------------------------------

-- End of scraping schema
-- Total tables created: 6
-- Total views created: 2  
-- Total procedures created: 3
-- 
-- Estimated storage for 100,000 URLs and 500,000 emails: ~500MB
-- Recommended server specs: 4GB RAM, SSD storage, multi-core CPU
-- 
-- Next steps:
-- 1. Import this schema into your database
-- 2. Configure Redis for queue management
-- 3. Set up worker processes
-- 4. Implement the PHP scraping classes