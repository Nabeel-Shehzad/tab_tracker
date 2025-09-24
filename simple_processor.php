<?php

/**
 * Simple Job Processor
 * Processes scraping jobs synchronously for testing
 */

require_once __DIR__ . '/common/config_mysql.php';
require_once __DIR__ . '/common/scraping_config.php';

class SimpleJobProcessor
{
  private $db;

  public function __construct()
  {
    $this->db = getScrapingDB();
  }

  public function processJob($jobId, $limitUrls = 10)
  {
    echo "Processing job $jobId (limit: $limitUrls URLs)\n";

    // Set job status to running when processing starts
    $this->updateJobStatusOnly($jobId, 'running');

    // Get pending URLs for this job
    $stmt = $this->db->prepare("
            SELECT id, url, job_id 
            FROM scraping_urls 
            WHERE job_id = ? AND status = 'pending' 
            ORDER BY created_at ASC 
            LIMIT ?
        ");
    $stmt->execute([$jobId, $limitUrls]);
    $urls = $stmt->fetchAll();

    if (empty($urls)) {
      echo "No pending URLs found for job $jobId\n";
      return;
    }

    echo "Found " . count($urls) . " URLs to process\n";

    foreach ($urls as $url) {
      $this->processUrl($url);
    }

    // Update job status if all URLs are processed
    $this->updateJobStatus($jobId);

    echo "Job processing completed\n";
  }

  private function processUrl($urlData)
  {
    $urlId = $urlData['id'];
    $url = $urlData['url'];
    $jobId = $urlData['job_id'];

    echo "Processing: $url\n";

    // Mark as processing
    $this->updateUrlStatus($urlId, 'processing');

    try {
      // Fetch the webpage content
      $content = $this->fetchUrl($url);

      if ($content === false) {
        $this->updateUrlStatus($urlId, 'failed', 'Failed to fetch URL');
        return;
      }

      // Extract emails
      $emails = $this->extractEmails($content, $url);

      // Save emails
      $emailCount = $this->saveEmails($urlId, $jobId, $emails);

      // Mark as completed
      $this->updateUrlStatus($urlId, 'completed', null, $emailCount);

      echo "  -> Found $emailCount emails\n";
    } catch (Exception $e) {
      $this->updateUrlStatus($urlId, 'failed', $e->getMessage());
      echo "  -> Error: " . $e->getMessage() . "\n";
    }
  }

  private function fetchUrl($url)
  {
    $context = stream_context_create([
      'http' => [
        'timeout' => 10,
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'follow_location' => true,
        'max_redirects' => 3
      ]
    ]);

    return @file_get_contents($url, false, $context);
  }

  private function extractEmails($content, $url)
  {
    // Simple email extraction regex
    $pattern = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/';
    preg_match_all($pattern, $content, $matches);

    $emails = array_unique($matches[0]);

    // Filter out common false positives
    $filtered = [];
    foreach ($emails as $email) {
      $email = strtolower(trim($email));

      // Skip common false positives
      if (
        !preg_match('/\.(png|jpg|gif|css|js|svg|ico)@/i', $email) &&
        !preg_match('/@(example|test|localhost)/i', $email) &&
        strlen($email) > 5
      ) {
        $filtered[] = $email;
      }
    }

    return $filtered;
  }

  private function saveEmails($urlId, $jobId, $emails)
  {
    $count = 0;

    foreach ($emails as $email) {
      $emailHash = md5($email);

      try {
        $stmt = $this->db->prepare("
                    INSERT IGNORE INTO scraping_emails 
                    (job_id, url_id, email, email_hash, domain, local_part, found_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");

        $parts = explode('@', $email);
        $localPart = $parts[0] ?? '';
        $domain = $parts[1] ?? '';

        $stmt->execute([$jobId, $urlId, $email, $emailHash, $domain, $localPart]);

        if ($stmt->rowCount() > 0) {
          $count++;
        }
      } catch (Exception $e) {
        // Ignore duplicate email errors
        if (strpos($e->getMessage(), 'Duplicate entry') === false) {
          throw $e;
        }
      }
    }

    return $count;
  }

  private function updateUrlStatus($urlId, $status, $errorMessage = null, $emailsFound = 0)
  {
    $stmt = $this->db->prepare("
            UPDATE scraping_urls 
            SET status = ?, error_message = ?, emails_found = ?, updated_at = NOW() 
            WHERE id = ?
        ");
    $stmt->execute([$status, $errorMessage, $emailsFound, $urlId]);
  }

  private function updateJobStatus($jobId)
  {
    // Get job statistics
    $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                SUM(emails_found) as total_emails
            FROM scraping_urls 
            WHERE job_id = ?
        ");
    $stmt->execute([$jobId]);
    $stats = $stmt->fetch();

    // Update job with current stats
    $status = 'running';
    if ($stats['processing'] == 0 && ($stats['completed'] + $stats['failed']) == $stats['total']) {
      $status = 'completed';
    }

    $stmt = $this->db->prepare("
            UPDATE scraping_jobs 
            SET 
                status = ?,
                processed_urls = ?,
                successful_urls = ?,
                failed_urls = ?,
                total_emails_found = ?,
                last_activity = NOW()
            WHERE id = ?
        ");
    $stmt->execute([
      $status,
      $stats['completed'] + $stats['failed'],
      $stats['completed'],
      $stats['failed'],
      $stats['total_emails'],
      $jobId
    ]);

    echo "Job stats - Total: {$stats['total']}, Completed: {$stats['completed']}, Failed: {$stats['failed']}, Emails: {$stats['total_emails']}\n";
  }

  private function updateJobStatusOnly($jobId, $status)
  {
    $stmt = $this->db->prepare("
      UPDATE scraping_jobs 
      SET status = ?, last_activity = NOW() 
      WHERE id = ?
    ");
    $stmt->execute([$status, $jobId]);
  }
}

// CLI usage
if (php_sapi_name() === 'cli') {
  if ($argc < 2) {
    echo "Usage: php simple_processor.php <job_id> [limit]\n";
    exit(1);
  }

  $jobId = (int)$argv[1];
  $limit = isset($argv[2]) ? (int)$argv[2] : 10;

  $processor = new SimpleJobProcessor();
  $processor->processJob($jobId, $limit);
}
