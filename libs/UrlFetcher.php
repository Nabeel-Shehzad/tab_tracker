<?php

/**
 * High-Performance URL Fetcher
 * Concurrent HTTP requests with advanced error handling
 * 
 * @author Employee Tracker System
 * @version 1.0
 * @created September 24, 2025
 */

require_once __DIR__ . '/../common/scraping_config.php';

class UrlFetcher
{
  private $maxConcurrent;
  private $timeout;
  private $maxRedirects;
  private $userAgent;
  private $requestDelay;
  private $retryAttempts;

  private $stats = [
    'requests_made' => 0,
    'successful_requests' => 0,
    'failed_requests' => 0,
    'total_bytes_downloaded' => 0,
    'total_time' => 0,
    'average_response_time' => 0
  ];

  public function __construct($config = [])
  {
    $this->maxConcurrent = $config['max_concurrent'] ?? SCRAPING_MAX_CONCURRENT_PER_WORKER;
    $this->timeout = $config['timeout'] ?? SCRAPING_TIMEOUT;
    $this->maxRedirects = $config['max_redirects'] ?? SCRAPING_MAX_REDIRECTS;
    $this->userAgent = $config['user_agent'] ?? SCRAPING_USER_AGENT;
    $this->requestDelay = $config['delay'] ?? SCRAPING_DEFAULT_DELAY;
    $this->retryAttempts = $config['retries'] ?? SCRAPING_MAX_RETRIES;
  }

  /**
   * Fetch multiple URLs concurrently
   */
  public function fetchUrls($urls)
  {
    $results = [];
    $chunks = array_chunk($urls, $this->maxConcurrent);

    foreach ($chunks as $chunk) {
      $chunkResults = $this->fetchUrlChunk($chunk);
      $results = array_merge($results, $chunkResults);

      // Respectful delay between chunks
      if ($this->requestDelay > 0) {
        usleep($this->requestDelay * 1000000);
      }
    }

    return $results;
  }

  /**
   * Fetch a chunk of URLs concurrently using cURL multi
   */
  private function fetchUrlChunk($urls)
  {
    $multiHandle = curl_multi_init();
    $curlHandles = [];
    $results = [];

    // Set concurrent limits
    curl_multi_setopt($multiHandle, CURLMOPT_MAXCONNECTS, $this->maxConcurrent);

    // Initialize cURL handles
    foreach ($urls as $index => $urlData) {
      $url = is_array($urlData) ? $urlData['url'] : $urlData;
      $urlId = is_array($urlData) ? $urlData['id'] : $index;

      $curlHandle = $this->createCurlHandle($url);
      $curlHandles[$urlId] = [
        'handle' => $curlHandle,
        'url' => $url,
        'start_time' => microtime(true)
      ];

      curl_multi_add_handle($multiHandle, $curlHandle);
    }

    // Execute requests
    $running = null;
    do {
      $mrc = curl_multi_exec($multiHandle, $running);

      if ($mrc == CURLM_OK) {
        // Process completed requests
        while (($info = curl_multi_info_read($multiHandle)) !== false) {
          if ($info['msg'] == CURLMSG_DONE) {
            $handle = $info['handle'];
            $result = $this->processCompletedRequest($handle, $curlHandles);

            if ($result) {
              $results[] = $result;
            }

            curl_multi_remove_handle($multiHandle, $handle);
            curl_close($handle);
          }
        }

        if ($running > 0) {
          curl_multi_select($multiHandle, 0.1);
        }
      }
    } while ($running > 0);

    curl_multi_close($multiHandle);

    return $results;
  }

  /**
   * Create optimized cURL handle
   */
  private function createCurlHandle($url)
  {
    $handle = curl_init();

    curl_setopt_array($handle, [
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_MAXREDIRS => $this->maxRedirects,
      CURLOPT_TIMEOUT => $this->timeout,
      CURLOPT_CONNECTTIMEOUT => min($this->timeout, 10),
      CURLOPT_USERAGENT => $this->userAgent,
      CURLOPT_SSL_VERIFYPEER => false,
      CURLOPT_SSL_VERIFYHOST => false,
      CURLOPT_ENCODING => '', // Accept all encodings
      CURLOPT_HTTPHEADER => [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.5',
        'Accept-Encoding: gzip, deflate',
        'DNT: 1',
        'Connection: keep-alive'
      ],

      // Performance optimizations
      CURLOPT_TCP_NODELAY => true,
      CURLOPT_FORBID_REUSE => false,
      CURLOPT_FRESH_CONNECT => false,
      CURLOPT_BUFFERSIZE => 16384,

      // Prevent memory issues with large files
      CURLOPT_MAXFILESIZE => 10 * 1024 * 1024, // 10MB max

      // Get response headers
      CURLOPT_HEADER => false,
      CURLOPT_NOBODY => false,

      // Handle redirects manually if needed
      CURLOPT_AUTOREFERER => true,

      // Prevent hanging on slow servers
      CURLOPT_LOW_SPEED_LIMIT => 1024, // bytes per second
      CURLOPT_LOW_SPEED_TIME => 30     // seconds
    ]);

    return $handle;
  }

  /**
   * Process completed cURL request
   */
  private function processCompletedRequest($handle, &$curlHandles)
  {
    $handleData = null;
    $urlId = null;

    // Find the handle data
    foreach ($curlHandles as $id => $data) {
      if ($data['handle'] === $handle) {
        $handleData = $data;
        $urlId = $id;
        unset($curlHandles[$id]);
        break;
      }
    }

    if (!$handleData) {
      return null;
    }

    $url = $handleData['url'];
    $startTime = $handleData['start_time'];
    $responseTime = microtime(true) - $startTime;

    $content = curl_multi_getcontent($handle);
    $info = curl_getinfo($handle);
    $error = curl_error($handle);

    $this->stats['requests_made']++;
    $this->stats['total_time'] += $responseTime;

    // Check for errors
    if ($error || $info['http_code'] >= 400) {
      $this->stats['failed_requests']++;

      return [
        'url_id' => $urlId,
        'url' => $url,
        'success' => false,
        'error' => $error ?: "HTTP {$info['http_code']}",
        'http_code' => $info['http_code'],
        'response_time' => $responseTime,
        'content' => null,
        'content_length' => 0,
        'content_type' => $info['content_type'] ?? null
      ];
    }

    // Successful request
    $this->stats['successful_requests']++;
    $this->stats['total_bytes_downloaded'] += strlen($content);

    // Extract page title
    $pageTitle = $this->extractPageTitle($content);

    return [
      'url_id' => $urlId,
      'url' => $url,
      'success' => true,
      'content' => $content,
      'content_length' => strlen($content),
      'content_type' => $info['content_type'] ?? null,
      'http_code' => $info['http_code'],
      'response_time' => $responseTime,
      'page_title' => $pageTitle,
      'final_url' => $info['url'], // After redirects
      'redirect_count' => $info['redirect_count'] ?? 0
    ];
  }

  /**
   * Extract page title from HTML content
   */
  private function extractPageTitle($html)
  {
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
      $title = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
      $title = trim(preg_replace('/\s+/', ' ', $title));
      return substr($title, 0, 500); // Limit length
    }

    return null;
  }

  /**
   * Fetch a single URL with retry logic
   */
  public function fetchSingleUrl($url, $maxRetries = null)
  {
    $maxRetries = $maxRetries ?? $this->retryAttempts;
    $lastError = null;

    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
      try {
        $results = $this->fetchUrls([['url' => $url, 'id' => 0]]);

        if (!empty($results) && $results[0]['success']) {
          return $results[0];
        }

        $lastError = $results[0]['error'] ?? 'Unknown error';

        // Wait before retry (exponential backoff)
        if ($attempt < $maxRetries) {
          sleep(min(pow(2, $attempt - 1), 10));
        }
      } catch (Exception $e) {
        $lastError = $e->getMessage();

        if ($attempt < $maxRetries) {
          sleep(min(pow(2, $attempt - 1), 10));
        }
      }
    }

    return [
      'url' => $url,
      'success' => false,
      'error' => "Failed after {$maxRetries} attempts. Last error: {$lastError}",
      'attempts' => $maxRetries
    ];
  }

  /**
   * Check if URL is accessible (HEAD request)
   */
  public function checkUrlAccessibility($url)
  {
    $handle = curl_init();

    curl_setopt_array($handle, [
      CURLOPT_URL => $url,
      CURLOPT_NOBODY => true,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 10,
      CURLOPT_CONNECTTIMEOUT => 5,
      CURLOPT_USERAGENT => $this->userAgent,
      CURLOPT_SSL_VERIFYPEER => false,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_MAXREDIRS => 3
    ]);

    curl_exec($handle);
    $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $error = curl_error($handle);
    curl_close($handle);

    return [
      'accessible' => ($httpCode >= 200 && $httpCode < 400 && !$error),
      'http_code' => $httpCode,
      'error' => $error
    ];
  }

  /**
   * Validate and clean URL
   */
  public function validateUrl($url)
  {
    // Basic format validation
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
      return false;
    }

    $parsed = parse_url($url);

    // Must have scheme and host
    if (!isset($parsed['scheme']) || !isset($parsed['host'])) {
      return false;
    }

    // Only HTTP/HTTPS
    if (!in_array($parsed['scheme'], ['http', 'https'])) {
      return false;
    }

    // Exclude localhost and local IPs
    $host = $parsed['host'];
    if (
      in_array($host, ['localhost', '127.0.0.1']) ||
      preg_match('/^192\.168\./', $host) ||
      preg_match('/^10\./', $host) ||
      preg_match('/^172\.(1[6-9]|2[0-9]|3[01])\./', $host)
    ) {
      return false;
    }

    return true;
  }

  /**
   * Get statistics
   */
  public function getStats()
  {
    $stats = $this->stats;

    if ($stats['requests_made'] > 0) {
      $stats['average_response_time'] = $stats['total_time'] / $stats['requests_made'];
      $stats['success_rate'] = ($stats['successful_requests'] / $stats['requests_made']) * 100;
    }

    return $stats;
  }

  /**
   * Reset statistics
   */
  public function resetStats()
  {
    $this->stats = [
      'requests_made' => 0,
      'successful_requests' => 0,
      'failed_requests' => 0,
      'total_bytes_downloaded' => 0,
      'total_time' => 0,
      'average_response_time' => 0
    ];
  }

  /**
   * Fetch multiple URLs concurrently
   */
  public function fetchMultiple($urls)
  {
    if (empty($urls)) {
      return [];
    }

    $results = [];
    $batches = array_chunk($urls, $this->maxConcurrent);

    foreach ($batches as $batch) {
      $batchResults = $this->fetchBatch($batch);
      $results = array_merge($results, $batchResults);

      // Add delay between batches
      if ($this->requestDelay > 0) {
        usleep($this->requestDelay * 1000000);
      }
    }

    return $results;
  }

  /**
   * Fetch a batch of URLs concurrently
   */
  private function fetchBatch($urls)
  {
    $multiHandle = curl_multi_init();
    $curlHandles = [];
    $results = [];

    // Initialize all cURL handles
    foreach ($urls as $index => $url) {
      $ch = $this->createCurlHandle($url);
      $curlHandles[$index] = $ch;
      curl_multi_add_handle($multiHandle, $ch);
    }

    // Execute all handles
    $running = null;
    do {
      curl_multi_exec($multiHandle, $running);
      curl_multi_select($multiHandle);
    } while ($running > 0);

    // Collect results
    foreach ($curlHandles as $index => $ch) {
      $url = $urls[$index];
      $content = curl_multi_getcontent($ch);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $error = curl_error($ch);

      if ($content !== false && $httpCode < 400 && !$error) {
        $results[$index] = [
          'success' => true,
          'url' => $url,
          'content' => $content,
          'http_code' => $httpCode,
          'size' => strlen($content)
        ];
        $this->stats['successful_requests']++;
      } else {
        $results[$index] = [
          'success' => false,
          'url' => $url,
          'error' => $error ?: "HTTP {$httpCode}",
          'http_code' => $httpCode
        ];
        $this->stats['failed_requests']++;
      }

      curl_multi_remove_handle($multiHandle, $ch);
      curl_close($ch);
      $this->stats['requests_made']++;
    }

    curl_multi_close($multiHandle);

    return $results;
  }

  /**
   * Set request delay
   */
  public function setDelay($seconds)
  {
    $this->requestDelay = max(0, $seconds);
  }

  /**
   * Get robots.txt content (for compliance checking)
   */
  public function getRobotsTxt($url)
  {
    $parsed = parse_url($url);
    $robotsUrl = $parsed['scheme'] . '://' . $parsed['host'] . '/robots.txt';

    $result = $this->fetchSingleUrl($robotsUrl);

    return $result['success'] ? $result['content'] : null;
  }
}
