<?php

/**
 * Web Scraping Configuration
 * High-performance email scraping system configuration
 * 
 * @author Employee Tracker System
 * @version 1.0
 * @created September 24, 2025
 */

// Include main config
require_once __DIR__ . '/config_mysql.php';

// Redis stub for type checking when extension not available
if (!extension_loaded('redis')) {
  /**
   * Redis stub class for development environments
   */
  class Redis
  {
    public function connect($host, $port)
    {
      return false;
    }
    public function auth($password)
    {
      return false;
    }
    public function select($database)
    {
      return false;
    }
    public function set($key, $value, $ttl = null)
    {
      return false;
    }
    public function get($key)
    {
      return false;
    }
    public function del($key)
    {
      return false;
    }
    public function hset($key, $field, $value = null)
    {
      return false;
    }
    public function hget($key, $field)
    {
      return false;
    }
    public function hgetall($key)
    {
      return [];
    }
    public function hmset($key, $array)
    {
      return false;
    }
    public function scard($key)
    {
      return 0;
    }
    public function smembers($key)
    {
      return [];
    }
    public function sadd($key, $member)
    {
      return false;
    }
    public function srem($key, $member)
    {
      return false;
    }
    public function lpush($key, $value)
    {
      return false;
    }
    public function ltrim($key, $start, $end)
    {
      return false;
    }
    public function expire($key, $seconds)
    {
      return false;
    }
  }
}

// Scraping System Configuration
define('SCRAPING_ENABLED', true);
define('SCRAPING_MAX_WORKERS', 8);
define('SCRAPING_MAX_CONCURRENT_PER_WORKER', 10);
define('SCRAPING_BATCH_SIZE', 50);
define('SCRAPING_CONCURRENT_REQUESTS', 10);
define('SCRAPING_REQUEST_TIMEOUT', 30);
define('SCRAPING_DEFAULT_DELAY', 1.0); // seconds between requests
define('SCRAPING_MAX_RETRIES', 3);
define('SCRAPING_TIMEOUT', 30); // seconds
define('SCRAPING_MAX_REDIRECTS', 5);
define('SCRAPING_USER_AGENT', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');

// File paths
define('SCRAPING_LOGS_PATH', __DIR__ . '/../admin/scraping/logs/');
define('SCRAPING_EXPORTS_PATH', __DIR__ . '/../admin/scraping/exports/');
define('SCRAPING_TEMP_PATH', __DIR__ . '/../admin/scraping/temp/');

// Redis configuration (if available)
define('REDIS_ENABLED', extension_loaded('redis'));
define('REDIS_HOST', '127.0.0.1');
define('REDIS_PORT', 6379);
define('REDIS_PASSWORD', null);
define('REDIS_DATABASE', 1); // Use database 1 for scraping

// Email validation settings
define('EMAIL_VALIDATION_ENABLED', true);
define('DNS_VALIDATION_ENABLED', true);
define('DNS_CACHE_TTL', 3600); // 1 hour
define('BATCH_SIZE', 1000);

// Performance settings
define('MEMORY_LIMIT', '512M');
define('MAX_EXECUTION_TIME', 300); // 5 minutes per batch

// Blacklisted domains/emails
define('EMAIL_BLACKLIST', [
  'example.com',
  'test.com',
  'localhost.com',
  'your-email.com',
  'email.com',
  'noreply.com',
  'no-reply.com'
]);

// Common false positive patterns
define('FALSE_POSITIVE_PATTERNS', [
  '/.*@(sentry\.io|bugsnag\.com|raygun\.com)/',
  '/.*@(facebook\.com|twitter\.com|linkedin\.com|youtube\.com)/',
  '/.*@(google\.com|microsoft\.com|apple\.com|amazon\.com)/',
  '/^(admin|info|support|noreply|no-reply|postmaster|webmaster)@/'
]);

/**
 * Initialize scraping directories
 */
function initializeScrapingDirectories()
{
  $directories = [
    SCRAPING_LOGS_PATH,
    SCRAPING_EXPORTS_PATH,
    SCRAPING_TEMP_PATH
  ];

  foreach ($directories as $dir) {
    if (!file_exists($dir)) {
      if (!mkdir($dir, 0755, true)) {
        throw new Exception("Failed to create directory: {$dir}");
      }
    }
  }
}

/**
 * Get Redis connection
 * @return Redis|null
 */
function getRedisConnection()
{
  static $redis = null;

  if (!REDIS_ENABLED) {
    return null;
  }

  if ($redis === null) {
    try {
      if (!extension_loaded('redis')) {
        error_log("Redis extension not available");
        return null;
      }

      $redis = new Redis();
      $redis->connect(REDIS_HOST, REDIS_PORT);

      if (REDIS_PASSWORD !== null) {
        $redis->auth(REDIS_PASSWORD);
      }

      $redis->select(REDIS_DATABASE);
    } catch (Exception $e) {
      error_log("Redis connection failed: " . $e->getMessage());
      return null;
    }
  }

  return $redis;
}

/**
 * Enhanced database connection for scraping
 */
function getScrapingDB()
{
  static $pdo = null;

  if ($pdo === null) {
    try {
      $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
      $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'"
      ];

      $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);

      // Optimize for bulk operations
      $pdo->exec("SET SESSION innodb_lock_wait_timeout = 300");
      $pdo->exec("SET SESSION bulk_insert_buffer_size = 16777216"); // 16MB

    } catch (PDOException $e) {
      throw new Exception("Scraping database connection failed: " . $e->getMessage());
    }
  }

  return $pdo;
}

/**
 * Log scraping activity
 */
function logScrapingActivity($jobId, $level, $message, $contextData = null, $workerId = null, $urlId = null)
{
  try {
    $db = getScrapingDB();

    $sql = "INSERT INTO scraping_logs (job_id, url_id, level, message, context_data, worker_id, execution_time, memory_usage) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $db->prepare($sql);
    $stmt->execute([
      $jobId,
      $urlId,
      $level,
      $message,
      $contextData ? json_encode($contextData) : null,
      $workerId,
      null, // execution_time - to be set by caller if needed
      memory_get_usage(true)
    ]);
  } catch (Exception $e) {
    error_log("Failed to log scraping activity: " . $e->getMessage());
  }
}

/**
 * Validate email format
 */
function isValidEmailFormat($email)
{
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    return false;
  }

  // Check against blacklisted domains
  $domain = substr(strrchr($email, "@"), 1);
  if (in_array(strtolower($domain), EMAIL_BLACKLIST)) {
    return false;
  }

  // Check against false positive patterns
  foreach (FALSE_POSITIVE_PATTERNS as $pattern) {
    if (preg_match($pattern, strtolower($email))) {
      return false;
    }
  }

  return true;
}

/**
 * Validate domain DNS records with caching
 */
function isValidDomain($domain, $useCache = true)
{
  static $cache = [];

  $domain = strtolower(trim($domain));
  $domainHash = md5($domain);

  if ($useCache && isset($cache[$domainHash])) {
    return $cache[$domainHash];
  }

  // Check database cache first
  if ($useCache && DNS_VALIDATION_ENABLED) {
    try {
      $db = getScrapingDB();
      $stmt = $db->prepare("SELECT is_valid, last_checked FROM domain_validation_cache WHERE domain_hash = ? AND last_checked > DATE_SUB(NOW(), INTERVAL ? SECOND)");
      $stmt->execute([$domainHash, DNS_CACHE_TTL]);

      if ($row = $stmt->fetch()) {
        $cache[$domainHash] = (bool)$row['is_valid'];
        return $cache[$domainHash];
      }
    } catch (Exception $e) {
      error_log("Domain cache lookup failed: " . $e->getMessage());
    }
  }

  // Perform actual DNS validation
  $isValid = false;
  $hasMX = false;
  $hasA = false;
  $error = null;

  try {
    if (DNS_VALIDATION_ENABLED) {
      $hasMX = checkdnsrr($domain, "MX");
      $hasA = checkdnsrr($domain, "A");
      $isValid = $hasMX || $hasA;
    } else {
      $isValid = true; // Skip DNS validation if disabled
    }
  } catch (Exception $e) {
    $error = $e->getMessage();
    $isValid = false;
  }

  // Cache the result
  $cache[$domainHash] = $isValid;

  // Store in database cache
  if ($useCache) {
    try {
      $db = getScrapingDB();
      $sql = "INSERT INTO domain_validation_cache (domain, domain_hash, has_mx_record, has_a_record, is_valid, validation_error) 
                    VALUES (?, ?, ?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE 
                    has_mx_record = VALUES(has_mx_record), 
                    has_a_record = VALUES(has_a_record), 
                    is_valid = VALUES(is_valid), 
                    validation_error = VALUES(validation_error),
                    last_checked = CURRENT_TIMESTAMP,
                    check_count = check_count + 1";

      $stmt = $db->prepare($sql);
      $stmt->execute([$domain, $domainHash, $hasMX, $hasA, $isValid, $error]);
    } catch (Exception $e) {
      error_log("Failed to cache domain validation: " . $e->getMessage());
    }
  }

  return $isValid;
}

/**
 * Generate unique worker ID
 */
function generateWorkerId()
{
  return 'worker_' . gethostname() . '_' . getmypid() . '_' . time();
}

/**
 * Set optimal PHP settings for scraping
 */
function optimizeForScraping()
{
  ini_set('memory_limit', MEMORY_LIMIT);
  ini_set('max_execution_time', MAX_EXECUTION_TIME);
  ini_set('user_agent', SCRAPING_USER_AGENT);
  ini_set('default_socket_timeout', SCRAPING_TIMEOUT);

  // Increase limits for bulk operations
  ini_set('mysql.connect_timeout', 300);
  ini_set('mysql.trace_mode', false);

  // Optimize garbage collection
  if (function_exists('gc_enable')) {
    gc_enable();
  }
}

/**
 * Clean temporary files older than specified time
 */
function cleanTempFiles($maxAge = 3600)
{
  $tempDir = SCRAPING_TEMP_PATH;

  if (!is_dir($tempDir)) {
    return;
  }

  $files = glob($tempDir . '*');
  $cutoff = time() - $maxAge;

  foreach ($files as $file) {
    if (is_file($file) && filemtime($file) < $cutoff) {
      unlink($file);
    }
  }
}

/**
 * Get system resource usage
 */
function getSystemResourceUsage()
{
  return [
    'memory_current' => memory_get_usage(true),
    'memory_peak' => memory_get_peak_usage(true),
    'cpu_load' => function_exists('sys_getloadavg') ? sys_getloadavg()[0] : null,
    'disk_free' => disk_free_space(__DIR__),
    'timestamp' => microtime(true)
  ];
}

// Initialize directories on include
try {
  initializeScrapingDirectories();
} catch (Exception $e) {
  error_log("Failed to initialize scraping directories: " . $e->getMessage());
}

// Set optimal settings
optimizeForScraping();
