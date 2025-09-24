<?php

/**
 * Scraping Job Manager
 * Manages the lifecycle of web scraping jobs
 * 
 * @author Employee Tracker System
 * @version 1.0
 * @created September 24, 2025
 */

require_once __DIR__ . '/../common/scraping_config.php';

class ScrapingJobManager
{
  private $db;
  private $redis;

  public function __construct()
  {
    $this->db = getScrapingDB();
    $this->redis = getRedisConnection();
  }

  /**
   * Create a new scraping job with thousands of URLs
   */
  public function createJob($jobName, $urls, $settings = [], $createdBy = 'admin')
  {
    try {
      $this->db->beginTransaction();

      // Validate and clean URLs in batches
      $validatedUrls = $this->batchValidateUrls($urls);

      if (empty($validatedUrls)) {
        throw new Exception("No valid URLs provided");
      }

      // Create parent job
      $jobId = $this->createParentJob($jobName, count($validatedUrls), $settings, $createdBy);

      // Insert URLs in batches for performance
      $this->insertUrlsBatch($jobId, $validatedUrls);

      // Initialize job queue
      $this->initializeJobQueue($jobId);

      $this->db->commit();

      logScrapingActivity($jobId, 'info', "Job created with " . count($validatedUrls) . " URLs", [
        'job_name' => $jobName,
        'total_urls' => count($validatedUrls),
        'settings' => $settings
      ]);

      return [
        'success' => true,
        'job_id' => $jobId,
        'total_urls' => count($validatedUrls),
        'message' => "Job '{$jobName}' created successfully"
      ];
    } catch (Exception $e) {
      $this->db->rollBack();

      error_log("Failed to create scraping job: " . $e->getMessage());

      return [
        'success' => false,
        'error' => $e->getMessage()
      ];
    }
  }

  /**
   * Create parent job record
   */
  private function createParentJob($jobName, $totalUrls, $settings, $createdBy)
  {
    $sql = "INSERT INTO scraping_jobs (job_name, created_by, total_urls, settings, status) 
                VALUES (?, ?, ?, ?, 'pending')";

    $stmt = $this->db->prepare($sql);
    $stmt->execute([
      $jobName,
      $createdBy,
      $totalUrls,
      json_encode($settings)
    ]);

    return $this->db->lastInsertId();
  }

  /**
   * Validate URLs in batches for performance
   */
  private function batchValidateUrls($urls, $batchSize = 1000)
  {
    $validUrls = [];
    $batches = array_chunk($urls, $batchSize);

    foreach ($batches as $batch) {
      foreach ($batch as $url) {
        $cleanUrl = $this->cleanUrl($url);

        if ($this->isValidUrl($cleanUrl)) {
          $domain = parse_url($cleanUrl, PHP_URL_HOST);
          $urlHash = md5($cleanUrl);

          $validUrls[] = [
            'url' => $cleanUrl,
            'url_hash' => $urlHash,
            'domain' => $domain,
            'priority' => $this->calculateUrlPriority($cleanUrl, $domain)
          ];
        }
      }
    }

    return $validUrls;
  }

  /**
   * Clean and normalize URL
   */
  private function cleanUrl($url)
  {
    $url = trim($url);

    // Add protocol if missing
    if (!preg_match('/^https?:\/\//', $url)) {
      $url = 'https://' . $url;
    }

    // Remove fragment
    $url = strtok($url, '#');

    // Remove common tracking parameters
    $trackingParams = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid'];
    $parsed = parse_url($url);

    if (isset($parsed['query'])) {
      parse_str($parsed['query'], $params);

      foreach ($trackingParams as $param) {
        unset($params[$param]);
      }

      $cleanQuery = http_build_query($params);
      $url = $parsed['scheme'] . '://' . $parsed['host'] .
        (isset($parsed['path']) ? $parsed['path'] : '') .
        ($cleanQuery ? '?' . $cleanQuery : '');
    }

    return $url;
  }

  /**
   * Validate URL format and accessibility
   */
  private function isValidUrl($url)
  {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
      return false;
    }

    $parsed = parse_url($url);

    if (!isset($parsed['scheme'], $parsed['host'])) {
      return false;
    }

    if (!in_array($parsed['scheme'], ['http', 'https'])) {
      return false;
    }

    // Exclude problematic domains
    $excludedDomains = [
      'localhost',
      '127.0.0.1',
      'facebook.com',
      'twitter.com',
      'instagram.com',
      'linkedin.com',
      'youtube.com'
    ];

    $host = strtolower($parsed['host']);
    foreach ($excludedDomains as $excluded) {
      if (strpos($host, $excluded) !== false) {
        return false;
      }
    }

    return true;
  }

  /**
   * Calculate URL processing priority
   */
  private function calculateUrlPriority($url, $domain)
  {
    $priority = 5; // Default priority

    // Higher priority for root domains
    $path = parse_url($url, PHP_URL_PATH);
    if (!$path || $path === '/') {
      $priority = 1;
    }

    // Higher priority for contact/about pages
    $priorityPages = ['/contact', '/about', '/team', '/staff', '/directory'];
    foreach ($priorityPages as $page) {
      if (stripos($path, $page) !== false) {
        $priority = 2;
        break;
      }
    }

    // Lower priority for media/static files
    $lowPriorityExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.pdf', '.doc', '.zip'];
    foreach ($lowPriorityExtensions as $ext) {
      if (stripos($url, $ext) !== false) {
        $priority = 9;
        break;
      }
    }

    return $priority;
  }

  /**
   * Insert URLs in optimized batches
   */
  private function insertUrlsBatch($jobId, $urls, $batchSize = 1000)
  {
    $batches = array_chunk($urls, $batchSize);

    foreach ($batches as $batch) {
      $sql = "INSERT IGNORE INTO scraping_urls (job_id, url, url_hash, domain, priority) VALUES ";
      $values = [];
      $params = [];

      foreach ($batch as $urlData) {
        $values[] = "(?, ?, ?, ?, ?)";
        $params[] = $jobId;
        $params[] = $urlData['url'];
        $params[] = $urlData['url_hash'];
        $params[] = $urlData['domain'];
        $params[] = $urlData['priority'];
      }

      $sql .= implode(", ", $values);

      $stmt = $this->db->prepare($sql);
      $stmt->execute($params);
    }
  }

  /**
   * Initialize job in processing queue
   */
  private function initializeJobQueue($jobId)
  {
    if ($this->redis) {
      // Add job to processing queue
      $jobData = [
        'job_id' => $jobId,
        'status' => 'pending',
        'created_at' => time(),
        'priority' => 'normal'
      ];

      $this->redis->lpush('scraping_jobs_queue', json_encode($jobData));

      // Initialize progress tracking
      $this->redis->hset("job_progress:{$jobId}", [
        'processed' => 0,
        'total' => $this->getTotalUrls($jobId),
        'emails_found' => 0,
        'started_at' => time(),
        'status' => 'pending'
      ]);

      $this->redis->expire("job_progress:{$jobId}", 86400); // 24 hours
    }
  }

  /**
   * Start job processing
   */
  public function startJob($jobId)
  {
    try {
      // Update job status
      $sql = "UPDATE scraping_jobs SET status = 'running', started_at = CURRENT_TIMESTAMP WHERE id = ?";
      $stmt = $this->db->prepare($sql);
      $stmt->execute([$jobId]);

      // Update Redis progress
      if ($this->redis) {
        $this->redis->hset("job_progress:{$jobId}", 'status', 'running');
      }

      logScrapingActivity($jobId, 'info', 'Job started');

      return ['success' => true, 'message' => 'Job started successfully'];
    } catch (Exception $e) {
      return ['success' => false, 'error' => $e->getMessage()];
    }
  }

  /**
   * Pause job processing
   */
  public function pauseJob($jobId)
  {
    try {
      $sql = "UPDATE scraping_jobs SET status = 'paused' WHERE id = ?";
      $stmt = $this->db->prepare($sql);
      $stmt->execute([$jobId]);

      if ($this->redis) {
        $this->redis->hset("job_progress:{$jobId}", 'status', 'paused');
      }

      logScrapingActivity($jobId, 'info', 'Job paused');

      return ['success' => true, 'message' => 'Job paused successfully'];
    } catch (Exception $e) {
      return ['success' => false, 'error' => $e->getMessage()];
    }
  }

  /**
   * Cancel job processing
   */
  public function cancelJob($jobId)
  {
    try {
      $sql = "UPDATE scraping_jobs SET status = 'cancelled' WHERE id = ?";
      $stmt = $this->db->prepare($sql);
      $stmt->execute([$jobId]);

      if ($this->redis) {
        $this->redis->hset("job_progress:{$jobId}", 'status', 'cancelled');
      }

      logScrapingActivity($jobId, 'info', 'Job cancelled');

      return ['success' => true, 'message' => 'Job cancelled successfully'];
    } catch (Exception $e) {
      return ['success' => false, 'error' => $e->getMessage()];
    }
  }

  /**
   * Get job details
   */
  public function getJobDetails($jobId)
  {
    $sql = "SELECT * FROM job_summary WHERE id = ?";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([$jobId]);

    $job = $stmt->fetch();

    if (!$job) {
      return null;
    }

    // Get real-time progress from Redis
    if ($this->redis) {
      $progress = $this->redis->hgetall("job_progress:{$jobId}");
      if ($progress) {
        $job['realtime_progress'] = $progress;
      }
    }

    return $job;
  }

  /**
   * Get all jobs with pagination
   */
  public function getJobs($limit = 20, $offset = 0, $status = null)
  {
    $whereClause = $status ? "WHERE status = ?" : "";
    $params = $status ? [$status] : [];

    $sql = "SELECT * FROM job_summary {$whereClause} ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
  }

  /**
   * Get total URLs for a job
   */
  private function getTotalUrls($jobId)
  {
    $sql = "SELECT total_urls FROM scraping_jobs WHERE id = ?";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([$jobId]);

    $result = $stmt->fetch();
    return $result ? $result['total_urls'] : 0;
  }

  /**
   * Update job progress
   */
  public function updateJobProgress($jobId, $processed, $successful, $failed, $emailsFound)
  {
    try {
      // Update database
      $sql = "UPDATE scraping_jobs 
                    SET processed_urls = ?, successful_urls = ?, failed_urls = ?, 
                        total_emails_found = ?, last_activity = CURRENT_TIMESTAMP 
                    WHERE id = ?";

      $stmt = $this->db->prepare($sql);
      $stmt->execute([$processed, $successful, $failed, $emailsFound, $jobId]);

      // Update Redis
      if ($this->redis) {
        $this->redis->hmset("job_progress:{$jobId}", [
          'processed' => $processed,
          'successful' => $successful,
          'failed' => $failed,
          'emails_found' => $emailsFound,
          'last_update' => time()
        ]);
      }
    } catch (Exception $e) {
      error_log("Failed to update job progress: " . $e->getMessage());
    }
  }

  /**
   * Mark job as completed
   */
  public function completeJob($jobId)
  {
    try {
      $sql = "UPDATE scraping_jobs SET status = 'completed', completed_at = CURRENT_TIMESTAMP WHERE id = ?";
      $stmt = $this->db->prepare($sql);
      $stmt->execute([$jobId]);

      if ($this->redis) {
        $this->redis->hset("job_progress:{$jobId}", 'status', 'completed');
        $this->redis->hset("job_progress:{$jobId}", 'completed_at', time());
      }

      logScrapingActivity($jobId, 'info', 'Job completed successfully');
    } catch (Exception $e) {
      error_log("Failed to mark job as completed: " . $e->getMessage());
    }
  }

  /**
   * Delete job and all associated data
   */
  public function deleteJob($jobId)
  {
    try {
      $this->db->beginTransaction();

      // Foreign key constraints will handle cascading deletes
      $sql = "DELETE FROM scraping_jobs WHERE id = ?";
      $stmt = $this->db->prepare($sql);
      $stmt->execute([$jobId]);

      // Clean up Redis data
      if ($this->redis) {
        $this->redis->del("job_progress:{$jobId}");
      }

      $this->db->commit();

      return ['success' => true, 'message' => 'Job deleted successfully'];
    } catch (Exception $e) {
      $this->db->rollBack();
      return ['success' => false, 'error' => $e->getMessage()];
    }
  }
}
