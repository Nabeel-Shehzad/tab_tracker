<?php

/**
 * Scraping System API
 * Handles all API requests for the web scraping system
 * 
 * @author Employee Tracker System
 * @version 1.0
 * @created September 24, 2025
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

// Include configuration
require_once __DIR__ . '/common/config_mysql.php';

// Include SimpleXLSXGen stub for type definitions
if (!class_exists('Shuchkin\SimpleXLSXGen') && file_exists(__DIR__ . '/libs/SimpleXLSXGen_stub.php')) {
  require_once __DIR__ . '/libs/SimpleXLSXGen_stub.php';
}

require_once __DIR__ . '/libs/ScrapingJobManager.php';

try {
  $jobManager = new ScrapingJobManager();

  // Get action from URL parameters or JSON body
  $action = $_GET['action'] ?? $_POST['action'] ?? '';

  // If no action found in URL/POST, try JSON body
  if (empty($action)) {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
  }

  switch ($action) {
    case 'stats':
      handleStats($jobManager);
      break;

    case 'jobs':
      handleJobsList($jobManager);
      break;

    case 'create_job':
      handleCreateJob($jobManager);
      break;

    case 'upload_urls':
      handleUploadUrls();
      break;

    case 'start_job':
      handleStartJob($jobManager);
      break;

    case 'process_job':
      handleProcessJob();
      break;

    case 'pause_job':
      handlePauseJob($jobManager);
      break;

    case 'cancel_job':
      handleCancelJob($jobManager);
      break;

    case 'job_details':
      handleJobDetails($jobManager);
      break;

    case 'worker_status':
      handleWorkerStatus();
      break;

    case 'export':
      handleExport($jobManager);
      break;

    default:
      throw new Exception('Invalid action specified');
  }
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'error' => $e->getMessage()
  ]);
}

/**
 * Handle file upload for URLs
 */
function handleUploadUrls()
{
  if (!isset($_FILES['urls_file'])) {
    throw new Exception('No file uploaded');
  }

  $file = $_FILES['urls_file'];

  if ($file['error'] !== UPLOAD_ERR_OK) {
    throw new Exception('File upload error: ' . $file['error']);
  }

  // Check file size (max 10MB)
  if ($file['size'] > 10 * 1024 * 1024) {
    throw new Exception('File too large. Maximum size is 10MB');
  }

  // Check file extension
  $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, ['txt', 'csv'])) {
    throw new Exception('Invalid file type. Only .txt and .csv files are allowed');
  }

  // Read file contents
  $content = file_get_contents($file['tmp_name']);
  if ($content === false) {
    throw new Exception('Failed to read uploaded file');
  }

  // Split into lines and clean up
  $urls = array_map('trim', explode("\n", $content));
  $urls = array_filter($urls, function ($url) {
    return !empty($url) && substr($url, 0, 1) !== '#'; // Remove empty lines and comments
  });

  // Validate URL count
  if (count($urls) === 0) {
    throw new Exception('No valid URLs found in the uploaded file');
  }

  if (count($urls) > 50000) {
    throw new Exception('Maximum 50,000 URLs allowed per file');
  }

  echo json_encode([
    'success' => true,
    'urls' => array_values($urls),
    'total_count' => count($urls),
    'filename' => $file['name']
  ]);
}

/**
 * Get system statistics
 */
function handleStats($jobManager)
{
  $db = getScrapingDB();

  // Get job statistics
  $sql = "SELECT 
                COUNT(*) as total_jobs,
                SUM(CASE WHEN status IN ('running', 'pending') THEN 1 ELSE 0 END) as active_jobs,
                SUM(processed_urls) as total_urls,
                SUM(total_emails_found) as total_emails
            FROM scraping_jobs";

  $stmt = $db->prepare($sql);
  $stmt->execute();
  $stats = $stmt->fetch();

  // Get worker count from Redis
  $redis = getRedisConnectionForAPI();
  $activeWorkers = 0;

  if ($redis) {
    $activeWorkers = $redis->scard('active_workers') ?: 0;
  }

  $stats['active_workers'] = $activeWorkers;

  // Get recent activity
  $sql = "SELECT COUNT(*) as recent_emails 
            FROM scraped_emails 
            WHERE found_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)";

  $stmt = $db->prepare($sql);
  $stmt->execute();
  $recent = $stmt->fetch();
  $stats['emails_last_hour'] = $recent['recent_emails'];

  echo json_encode([
    'success' => true,
    'stats' => $stats
  ]);
}

/**
 * Get jobs list with pagination
 */
function handleJobsList($jobManager)
{
  $limit = (int)($_GET['limit'] ?? 20);
  $offset = (int)($_GET['offset'] ?? 0);
  $status = $_GET['status'] ?? null;

  $jobs = $jobManager->getJobs($limit, $offset, $status);

  // Calculate additional metrics
  foreach ($jobs as &$job) {
    $job['progress_percent'] = $job['total_urls'] > 0 ?
      ($job['processed_urls'] / $job['total_urls']) * 100 : 0;

    $job['success_rate'] = $job['processed_urls'] > 0 ?
      ($job['successful_urls'] / $job['processed_urls']) * 100 : 0;

    // Add real-time progress if available
    if (isset($job['realtime_progress'])) {
      $progress = $job['realtime_progress'];
      $job['processed_urls'] = $progress['processed'] ?? $job['processed_urls'];
      $job['total_emails_found'] = $progress['emails_found'] ?? $job['total_emails_found'];
      $job['progress_percent'] = $job['total_urls'] > 0 ?
        ($job['processed_urls'] / $job['total_urls']) * 100 : 0;
    }
  }

  echo json_encode([
    'success' => true,
    'jobs' => $jobs
  ]);
}

/**
 * Create new scraping job
 */
function handleCreateJob($jobManager)
{
  $input = json_decode(file_get_contents('php://input'), true);

  if (!$input) {
    throw new Exception('Invalid JSON input');
  }

  $jobName = trim($input['job_name'] ?? '');
  $urls = $input['urls'] ?? [];
  $priority = $input['priority'] ?? 'normal';

  if (empty($jobName)) {
    throw new Exception('Job name is required');
  }

  if (empty($urls) || !is_array($urls)) {
    throw new Exception('URLs list is required');
  }

  if (count($urls) > 50000) {
    throw new Exception('Maximum 50,000 URLs per job allowed');
  }

  $settings = [
    'priority' => $priority,
    'created_via' => 'web_interface'
  ];

  $result = $jobManager->createJob($jobName, $urls, $settings, 'admin');

  echo json_encode($result);
}

/**
 * Start a job
 */
function handleStartJob($jobManager)
{
  $input = json_decode(file_get_contents('php://input'), true);
  $jobId = (int)($input['job_id'] ?? 0);

  if (!$jobId) {
    throw new Exception('Job ID is required');
  }

  $result = $jobManager->startJob($jobId);

  echo json_encode($result);
}

/**
 * Process a job (immediate processing)
 */
function handleProcessJob()
{
  require_once __DIR__ . '/simple_processor.php';

  $input = json_decode(file_get_contents('php://input'), true);
  $jobId = (int)($input['job_id'] ?? 0);
  $limit = (int)($input['limit'] ?? 10);

  if (!$jobId) {
    throw new Exception('Job ID is required');
  }

  try {
    // Set time limit for processing
    set_time_limit(300); // 5 minutes

    // Start output buffering to capture processing output
    ob_start();

    $processor = new SimpleJobProcessor();
    $processor->processJob($jobId, $limit);

    $output = ob_get_clean();

    echo json_encode([
      'success' => true,
      'message' => "Processed job $jobId with limit $limit URLs",
      'output' => $output
    ]);
  } catch (Exception $e) {
    ob_end_clean();
    throw $e;
  }
}

/**
 * Pause a job
 */
function handlePauseJob($jobManager)
{
  $input = json_decode(file_get_contents('php://input'), true);
  $jobId = (int)($input['job_id'] ?? 0);

  if (!$jobId) {
    throw new Exception('Job ID is required');
  }

  $result = $jobManager->pauseJob($jobId);

  echo json_encode($result);
}

/**
 * Cancel a job
 */
function handleCancelJob($jobManager)
{
  $input = json_decode(file_get_contents('php://input'), true);
  $jobId = (int)($input['job_id'] ?? 0);

  if (!$jobId) {
    throw new Exception('Job ID is required');
  }

  $result = $jobManager->cancelJob($jobId);

  echo json_encode($result);
}

/**
 * Get detailed job information
 */
function handleJobDetails($jobManager)
{
  $jobId = (int)($_GET['job_id'] ?? 0);

  if (!$jobId) {
    throw new Exception('Job ID is required');
  }

  $job = $jobManager->getJobDetails($jobId);

  if (!$job) {
    throw new Exception('Job not found');
  }

  // Get additional details
  $db = getScrapingDB();

  // Get URL status breakdown
  $sql = "SELECT status, COUNT(*) as count 
            FROM scraping_urls 
            WHERE job_id = ? 
            GROUP BY status";

  $stmt = $db->prepare($sql);
  $stmt->execute([$jobId]);
  $urlStats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

  // Get recent emails
  $sql = "SELECT email_address, confidence_score, extraction_method, found_at,
                   source_url, found_context
            FROM scraped_emails 
            WHERE job_id = ? 
            ORDER BY found_at DESC 
            LIMIT 100";

  $stmt = $db->prepare($sql);
  $stmt->execute([$jobId]);
  $recentEmails = $stmt->fetchAll();

  // Get domain breakdown
  $sql = "SELECT domain, COUNT(*) as count,
                   SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                   SUM(emails_found) as emails
            FROM scraping_urls 
            WHERE job_id = ? 
            GROUP BY domain 
            ORDER BY emails DESC, count DESC 
            LIMIT 50";

  $stmt = $db->prepare($sql);
  $stmt->execute([$jobId]);
  $domainStats = $stmt->fetchAll();

  $job['url_stats'] = $urlStats;
  $job['recent_emails'] = $recentEmails;
  $job['domain_stats'] = $domainStats;

  echo json_encode([
    'success' => true,
    'job' => $job
  ]);
}

/**
 * Get worker status
 */
function handleWorkerStatus()
{
  $redis = getRedisConnectionForAPI();
  $workers = [];

  if ($redis) {
    $activeWorkers = $redis->smembers('active_workers');

    foreach ($activeWorkers as $workerId) {
      $workerData = $redis->hgetall("worker:{$workerId}");

      if ($workerData) {
        $workers[] = [
          'worker_id' => $workerId,
          'pid' => $workerData['pid'] ?? 0,
          'status' => $workerData['status'] ?? 'unknown',
          'started_at' => $workerData['started_at'] ?? 0,
          'uptime' => time() - ($workerData['started_at'] ?? time()),
          'processed_count' => $workerData['processed_count'] ?? 0,
          'error_count' => $workerData['error_count'] ?? 0,
          'emails_found' => $workerData['emails_found'] ?? 0,
          'last_activity' => $workerData['last_activity'] ?? 0,
          'memory_usage' => $workerData['memory_usage'] ?? 0
        ];
      }
    }
  }

  echo json_encode([
    'success' => true,
    'workers' => $workers
  ]);
}

/**
 * Export job results
 */
function handleExport($jobManager)
{
  $jobId = (int)($_GET['job_id'] ?? 0);
  $format = $_GET['format'] ?? 'csv';

  if (!$jobId) {
    throw new Exception('Job ID is required');
  }

  $job = $jobManager->getJobDetails($jobId);

  if (!$job) {
    throw new Exception('Job not found');
  }

  // Get all emails for this job
  $db = getScrapingDB();
  $sql = "SELECT 
                email_address,
                source_url,
                confidence_score,
                extraction_method,
                found_context,
                found_at
            FROM scraped_emails 
            WHERE job_id = ? 
            ORDER BY confidence_score DESC, found_at DESC";

  $stmt = $db->prepare($sql);
  $stmt->execute([$jobId]);
  $emails = $stmt->fetchAll();

  if (empty($emails)) {
    throw new Exception('No emails found for this job');
  }

  $filename = sanitizeFilenameForExport($job['job_name']) . '_emails_' . date('Y-m-d_H-i-s');

  switch ($format) {
    case 'csv':
      exportCSV($emails, $filename, $job);
      break;
    case 'json':
      exportJSON($emails, $filename, $job);
      break;
    case 'xlsx':
      // Check if XLSX library is available
      if (file_exists(__DIR__ . '/libs/SimpleXLSXGen.php')) {
        exportXLSX($emails, $filename, $job);
      } else {
        // Fallback to CSV if XLSX not available
        exportCSV($emails, $filename . '_xlsx_fallback', $job);
      }
      break;
    default:
      throw new Exception('Unsupported export format');
  }
}

/**
 * Export as CSV
 */
function exportCSV($emails, $filename, $job)
{
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="' . $filename . '.csv"');

  $output = fopen('php://output', 'w');

  // Add BOM for UTF-8
  fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

  // Write header
  fputcsv($output, [
    'Email Address',
    'Source URL',
    'Confidence Score',
    'Extraction Method',
    'Context',
    'Found Date',
    'Job Name'
  ]);

  // Write data
  foreach ($emails as $email) {
    fputcsv($output, [
      $email['email_address'],
      $email['source_url'],
      $email['confidence_score'],
      $email['extraction_method'],
      $email['found_context'],
      $email['found_at'],
      $job['job_name']
    ]);
  }

  fclose($output);
}

/**
 * Export as JSON
 */
function exportJSON($emails, $filename, $job)
{
  header('Content-Type: application/json');
  header('Content-Disposition: attachment; filename="' . $filename . '.json"');

  $data = [
    'job' => [
      'id' => $job['id'],
      'name' => $job['job_name'],
      'created_at' => $job['created_at'],
      'total_urls' => $job['total_urls'],
      'total_emails' => count($emails)
    ],
    'emails' => $emails,
    'exported_at' => date('Y-m-d H:i:s'),
    'total_count' => count($emails)
  ];

  echo json_encode($data, JSON_PRETTY_PRINT);
}

/**
 * Export as XLSX
 */
function exportXLSX($emails, $filename, $job)
{
  if (!file_exists(__DIR__ . '/libs/SimpleXLSXGen.php')) {
    // Include stub for type definitions
    require_once __DIR__ . '/libs/SimpleXLSXGen_stub.php';
    throw new Exception('SimpleXLSXGen library not found - CSV export will be used instead');
  }

  require_once __DIR__ . '/libs/SimpleXLSXGen.php';

  if (!class_exists('Shuchkin\SimpleXLSXGen')) {
    require_once __DIR__ . '/libs/SimpleXLSXGen_stub.php';
    throw new Exception('SimpleXLSXGen class not available - CSV export will be used instead');
  }

  $data = [
    ['Email Address', 'Source URL', 'Confidence Score', 'Extraction Method', 'Context', 'Found Date', 'Job Name']
  ];

  foreach ($emails as $email) {
    $data[] = [
      $email['email_address'],
      $email['source_url'],
      $email['confidence_score'],
      $email['extraction_method'],
      $email['found_context'],
      $email['found_at'],
      $job['job_name']
    ];
  }

  $xlsx = \Shuchkin\SimpleXLSXGen::fromArray($data);

  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');

  $xlsx->output();
}

/**
 * Sanitize filename for download
 */
function sanitizeFilenameForExport($filename)
{
  // Remove or replace invalid characters
  $filename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $filename);
  $filename = preg_replace('/_+/', '_', $filename);
  $filename = trim($filename, '_');

  return substr($filename, 0, 50); // Limit length
}

/**
 * Get Redis connection (with fallback for development)
 */
function getRedisConnectionForAPI()
{
  static $redis = null;

  if ($redis === null) {
    try {
      if (class_exists('Redis')) {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        $redis->select(1); // Use database 1 for scraping
      } else {
        // Redis not available, use fallback
        $redis = false;
      }
    } catch (Exception $e) {
      // Redis connection failed, use fallback
      error_log("Redis connection failed: " . $e->getMessage());
      $redis = false;
    }
  }

  return $redis;
}
