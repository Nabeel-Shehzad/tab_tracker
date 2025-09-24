<?php

/**
 * Scraping Worker Process
 * High-performance worker for processing scraping jobs
 * 
 * @author Employee Tracker System
 * @version 1.0
 * @created September 24, 2025
 */

require_once __DIR__ . '/../common/scraping_config.php';
require_once __DIR__ . '/EmailExtractor.php';
require_once __DIR__ . '/UrlFetcher.php';

class ScrapingWorker
{
  private $workerId;
  private $db;
  private $redis;
  private $emailExtractor;
  private $urlFetcher;
  private $running = false;
  private $processedCount = 0;
  private $errorCount = 0;
  private $emailsFound = 0;

  // Performance settings
  private $batchSize;
  private $concurrent;
  private $timeout;
  private $memoryLimit;

  public function __construct($workerId = null)
  {
    $this->workerId = $workerId ?: 'worker_' . uniqid();
    $this->db = getScrapingDB();
    $this->redis = getRedisConnection();
    $this->emailExtractor = new EmailExtractor();
    $this->urlFetcher = new UrlFetcher();

    // Load performance settings
    $this->loadSettings();

    // Register signal handlers for graceful shutdown
    if (function_exists('pcntl_signal')) {
      pcntl_signal(SIGTERM, [$this, 'shutdown']);
      pcntl_signal(SIGINT, [$this, 'shutdown']);
      pcntl_signal(SIGHUP, [$this, 'restart']);
    }

    $this->log("Worker {$this->workerId} initialized", 'info');
  }

  /**
   * Load performance settings
   */
  private function loadSettings()
  {
    $this->batchSize = SCRAPING_BATCH_SIZE;
    $this->concurrent = SCRAPING_CONCURRENT_REQUESTS;
    $this->timeout = SCRAPING_REQUEST_TIMEOUT;
    $this->memoryLimit = ini_get('memory_limit');

    // Set optimal memory limit
    ini_set('memory_limit', '512M');

    $this->log("Settings loaded - Batch: {$this->batchSize}, Concurrent: {$this->concurrent}", 'debug');
  }

  /**
   * Start the worker process
   */
  public function start()
  {
    $this->running = true;

    $this->log("Worker starting - PID: " . getmypid(), 'info');

    // Register worker in Redis
    $this->registerWorker();

    // Main processing loop
    while ($this->running) {
      try {
        // Check memory usage
        $this->checkMemoryUsage();

        // Process next batch
        $processed = $this->processBatch();

        if ($processed === 0) {
          // No work available, wait before checking again
          sleep(5);
        } else {
          // Update statistics
          $this->updateWorkerStats();
        }

        // Handle signals
        if (function_exists('pcntl_signal_dispatch')) {
          pcntl_signal_dispatch();
        }
      } catch (Exception $e) {
        $this->log("Worker error: " . $e->getMessage(), 'error');
        $this->errorCount++;

        // Wait before retrying
        sleep(10);
      }
    }

    $this->shutdown();
  }

  /**
   * Process a batch of URLs
   */
  private function processBatch()
  {
    // Get next batch of URLs
    $urls = $this->getNextBatch();

    if (empty($urls)) {
      return 0;
    }

    $jobId = $urls[0]['job_id'];
    $this->log("Processing batch of " . count($urls) . " URLs for job {$jobId}", 'debug');

    // Mark URLs as processing
    $urlIds = array_column($urls, 'id');
    $this->markUrlsProcessing($urlIds);

    // Fetch content concurrently
    $urlsToFetch = array_map(function ($url) {
      return $url['url'];
    }, $urls);

    $results = $this->urlFetcher->fetchMultiple($urlsToFetch);

    // Process results
    $batchStats = [
      'processed' => 0,
      'successful' => 0,
      'failed' => 0,
      'emails_found' => 0
    ];

    foreach ($urls as $index => $urlData) {
      $result = $results[$index] ?? null;
      $urlId = $urlData['id'];
      $jobId = $urlData['job_id'];

      if ($result && $result['success']) {
        $this->processSuccessfulUrl($urlId, $jobId, $urlData['url'], $result, $batchStats);
      } else {
        $this->processFailedUrl($urlId, $result, $batchStats);
      }
    }

    // Update job progress
    $this->updateJobProgress($jobId, $batchStats);

    $this->processedCount += $batchStats['processed'];
    $this->emailsFound += $batchStats['emails_found'];

    return $batchStats['processed'];
  }

  /**
   * Get next batch of URLs to process
   */
  private function getNextBatch()
  {
    $sql = "SELECT su.id, su.job_id, su.url, su.domain, su.priority
                FROM scraping_urls su
                JOIN scraping_jobs sj ON su.job_id = sj.id
                WHERE su.status = 'pending' 
                AND sj.status = 'running'
                ORDER BY su.priority ASC, su.id ASC
                LIMIT ?";

    $stmt = $this->db->prepare($sql);
    $stmt->execute([$this->batchSize]);

    return $stmt->fetchAll();
  }

  /**
   * Mark URLs as being processed
   */
  private function markUrlsProcessing($urlIds)
  {
    if (empty($urlIds)) return;

    $placeholders = str_repeat('?,', count($urlIds) - 1) . '?';
    $sql = "UPDATE scraping_urls 
                SET status = 'processing', 
                    started_at = CURRENT_TIMESTAMP,
                    worker_id = ?
                WHERE id IN ({$placeholders})";

    $params = array_merge([$this->workerId], $urlIds);
    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);
  }

  /**
   * Process successful URL fetch
   */
  private function processSuccessfulUrl($urlId, $jobId, $url, $result, &$batchStats)
  {
    try {
      // Extract emails from content
      $emails = $this->emailExtractor->extractEmailsFromContent($result['content'], $url);

      // Update URL status
      $sql = "UPDATE scraping_urls 
                    SET status = 'completed', 
                        completed_at = CURRENT_TIMESTAMP,
                        response_code = ?,
                        content_size = ?,
                        emails_found = ?
                    WHERE id = ?";

      $stmt = $this->db->prepare($sql);
      $stmt->execute([
        $result['http_code'],
        strlen($result['content']),
        count($emails),
        $urlId
      ]);

      // Save extracted emails
      if (!empty($emails)) {
        $this->saveEmails($jobId, $urlId, $url, $emails);
      }

      $batchStats['successful']++;
      $batchStats['emails_found'] += count($emails);
    } catch (Exception $e) {
      $this->log("Error processing successful URL {$url}: " . $e->getMessage(), 'error');
      $this->markUrlFailed($urlId, $e->getMessage());
      $batchStats['failed']++;
    }

    $batchStats['processed']++;
  }

  /**
   * Process failed URL fetch
   */
  private function processFailedUrl($urlId, $result, &$batchStats)
  {
    $errorMessage = $result['error'] ?? 'Unknown error';
    $this->markUrlFailed($urlId, $errorMessage);

    $batchStats['processed']++;
    $batchStats['failed']++;
  }

  /**
   * Mark URL as failed
   */
  private function markUrlFailed($urlId, $error)
  {
    $sql = "UPDATE scraping_urls 
                SET status = 'failed', 
                    completed_at = CURRENT_TIMESTAMP,
                    error_message = ?
                WHERE id = ?";

    $stmt = $this->db->prepare($sql);
    $stmt->execute([$error, $urlId]);
  }

  /**
   * Save extracted emails
   */
  private function saveEmails($jobId, $urlId, $url, $emails)
  {
    if (empty($emails)) return;

    $sql = "INSERT IGNORE INTO scraped_emails 
                (job_id, url_id, source_url, email_address, confidence_score, extraction_method, found_context) 
                VALUES ";

    $values = [];
    $params = [];

    foreach ($emails as $email) {
      $values[] = "(?, ?, ?, ?, ?, ?, ?)";
      $params = array_merge($params, [
        $jobId,
        $urlId,
        $url,
        $email['email'],
        $email['confidence'],
        $email['method'],
        $email['context'] ?? ''
      ]);
    }

    $sql .= implode(", ", $values);

    try {
      $stmt = $this->db->prepare($sql);
      $stmt->execute($params);
    } catch (Exception $e) {
      $this->log("Error saving emails for URL {$url}: " . $e->getMessage(), 'error');
    }
  }

  /**
   * Update job progress
   */
  private function updateJobProgress($jobId, $batchStats)
  {
    try {
      // Get current job stats
      $sql = "SELECT processed_urls, successful_urls, failed_urls, total_emails_found
                    FROM scraping_jobs WHERE id = ?";
      $stmt = $this->db->prepare($sql);
      $stmt->execute([$jobId]);
      $currentStats = $stmt->fetch();

      if ($currentStats) {
        $newProcessed = $currentStats['processed_urls'] + $batchStats['processed'];
        $newSuccessful = $currentStats['successful_urls'] + $batchStats['successful'];
        $newFailed = $currentStats['failed_urls'] + $batchStats['failed'];
        $newEmails = $currentStats['total_emails_found'] + $batchStats['emails_found'];

        // Update database
        $sql = "UPDATE scraping_jobs 
                        SET processed_urls = ?, successful_urls = ?, failed_urls = ?, 
                            total_emails_found = ?, last_activity = CURRENT_TIMESTAMP
                        WHERE id = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$newProcessed, $newSuccessful, $newFailed, $newEmails, $jobId]);

        // Update Redis
        if ($this->redis) {
          $this->redis->hmset("job_progress:{$jobId}", [
            'processed' => $newProcessed,
            'successful' => $newSuccessful,
            'failed' => $newFailed,
            'emails_found' => $newEmails,
            'last_update' => time(),
            'worker_id' => $this->workerId
          ]);
        }

        // Check if job is completed
        $this->checkJobCompletion($jobId, $newProcessed);
      }
    } catch (Exception $e) {
      $this->log("Error updating job progress: " . $e->getMessage(), 'error');
    }
  }

  /**
   * Check if job is completed
   */
  private function checkJobCompletion($jobId, $processed)
  {
    $sql = "SELECT total_urls FROM scraping_jobs WHERE id = ?";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([$jobId]);
    $result = $stmt->fetch();

    if ($result && $processed >= $result['total_urls']) {
      // Mark job as completed
      $sql = "UPDATE scraping_jobs 
                    SET status = 'completed', completed_at = CURRENT_TIMESTAMP 
                    WHERE id = ?";

      $stmt = $this->db->prepare($sql);
      $stmt->execute([$jobId]);

      if ($this->redis) {
        $this->redis->hset("job_progress:{$jobId}", 'status', 'completed');
        $this->redis->hset("job_progress:{$jobId}", 'completed_at', time());
      }

      $this->log("Job {$jobId} completed", 'info');
      logScrapingActivity($jobId, 'info', 'Job completed', [
        'processed_urls' => $processed,
        'worker_id' => $this->workerId
      ]);
    }
  }

  /**
   * Register worker in Redis
   */
  private function registerWorker()
  {
    if ($this->redis) {
      $workerData = [
        'id' => $this->workerId,
        'pid' => getmypid(),
        'started_at' => time(),
        'status' => 'running',
        'processed_count' => 0,
        'error_count' => 0,
        'emails_found' => 0
      ];

      $this->redis->hset("worker:{$this->workerId}", $workerData);
      $this->redis->expire("worker:{$this->workerId}", 3600); // 1 hour

      // Add to active workers set
      $this->redis->sadd('active_workers', $this->workerId);
    }
  }

  /**
   * Update worker statistics
   */
  private function updateWorkerStats()
  {
    if ($this->redis) {
      $this->redis->hmset("worker:{$this->workerId}", [
        'processed_count' => $this->processedCount,
        'error_count' => $this->errorCount,
        'emails_found' => $this->emailsFound,
        'last_activity' => time(),
        'memory_usage' => memory_get_usage(true)
      ]);
    }
  }

  /**
   * Check memory usage
   */
  private function checkMemoryUsage()
  {
    $memoryUsage = memory_get_usage(true);
    $memoryLimit = ini_get('memory_limit');

    // Convert to bytes if needed
    if (is_string($memoryLimit)) {
      $memoryLimit = $this->convertToBytes($memoryLimit);
    }

    $usagePercent = ($memoryUsage / $memoryLimit) * 100;

    if ($usagePercent > 80) {
      $this->log("High memory usage: {$usagePercent}%", 'warning');

      // Force garbage collection
      gc_collect_cycles();
    }

    if ($usagePercent > 90) {
      $this->log("Critical memory usage: {$usagePercent}% - Restarting worker", 'error');
      $this->restart();
    }
  }

  /**
   * Convert memory limit string to bytes
   */
  private function convertToBytes($value)
  {
    $unit = strtolower(substr($value, -1));
    $value = (int) substr($value, 0, -1);

    switch ($unit) {
      case 'g':
        return $value * 1024 * 1024 * 1024;
      case 'm':
        return $value * 1024 * 1024;
      case 'k':
        return $value * 1024;
      default:
        return $value;
    }
  }

  /**
   * Shutdown worker gracefully
   */
  public function shutdown($signal = null)
  {
    $this->log("Worker shutting down" . ($signal ? " (signal: {$signal})" : ""), 'info');

    $this->running = false;

    // Update worker status in Redis
    if ($this->redis) {
      $this->redis->hset("worker:{$this->workerId}", [
        'status' => 'stopped',
        'stopped_at' => time(),
        'final_processed' => $this->processedCount,
        'final_errors' => $this->errorCount,
        'final_emails' => $this->emailsFound
      ]);

      // Remove from active workers
      $this->redis->srem('active_workers', $this->workerId);
    }

    exit(0);
  }

  /**
   * Restart worker
   */
  public function restart($signal = null)
  {
    $this->log("Worker restarting" . ($signal ? " (signal: {$signal})" : ""), 'info');

    // Clean shutdown first
    $this->shutdown();

    // The process manager should restart us
  }

  /**
   * Log worker activity
   */
  private function log($message, $level = 'info')
  {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$this->workerId}] [{$level}] {$message}\n";

    // Log to file
    error_log($logMessage, 3, __DIR__ . '/../logs/workers.log');

    // Also log to system log for important messages
    if (in_array($level, ['error', 'warning'])) {
      error_log("[ScrapingWorker] {$message}");
    }

    // Optional: log to Redis for real-time monitoring
    if ($this->redis && in_array($level, ['error', 'info'])) {
      $logEntry = [
        'timestamp' => time(),
        'worker_id' => $this->workerId,
        'level' => $level,
        'message' => $message
      ];

      $this->redis->lpush('worker_logs', json_encode($logEntry));
      $this->redis->ltrim('worker_logs', 0, 999); // Keep last 1000 entries
    }
  }
}

// If called directly, start the worker
if (basename($_SERVER['PHP_SELF']) === 'ScrapingWorker.php') {
  $workerId = $argv[1] ?? null;
  $worker = new ScrapingWorker($workerId);
  $worker->start();
}
