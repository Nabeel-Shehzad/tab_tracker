<?php

/**
 * High-Performance Email Extractor
 * Advanced email extraction with multiple detection methods
 * 
 * @author Employee Tracker System
 * @version 1.0
 * @created September 24, 2025
 */

require_once __DIR__ . '/../common/scraping_config.php';

class EmailExtractor
{
  private $emailRegexPatterns = [
    // Standard email pattern
    '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/',
    // Obfuscated patterns
    '/\b[A-Za-z0-9._%+-]+\s*\[\s*at\s*\]\s*[A-Za-z0-9.-]+\s*\[\s*dot\s*\]\s*[A-Za-z]{2,}\b/i',
    '/\b[A-Za-z0-9._%+-]+\s*@\s*[A-Za-z0-9.-]+\s*\.\s*[A-Za-z]{2,}\b/',
  ];

  private $domainCache = [];
  private $extractionStats = [
    'total_processed' => 0,
    'emails_found' => 0,
    'valid_emails' => 0,
    'extraction_time' => 0,
    'validation_time' => 0
  ];

  /**
   * Extract emails from HTML content with multiple methods
   */
  public function extractEmailsFromContent($html, $url, $pageTitle = '')
  {
    $startTime = microtime(true);
    $this->extractionStats['total_processed']++;

    $emails = [];

    try {
      // Method 1: Regex extraction (fastest)
      $regexEmails = $this->extractWithRegex($html);
      $emails = array_merge($emails, $regexEmails);

      // Method 2: HTML parsing for mailto links and structured data
      $htmlEmails = $this->extractFromHtmlStructure($html);
      $emails = array_merge($emails, $htmlEmails);

      // Method 3: JavaScript and obfuscated email detection
      $obfuscatedEmails = $this->extractObfuscatedEmails($html);
      $emails = array_merge($emails, $obfuscatedEmails);

      // Method 4: Extract from JSON-LD structured data
      $structuredEmails = $this->extractFromStructuredData($html);
      $emails = array_merge($emails, $structuredEmails);

      // Deduplicate and clean
      $emails = $this->deduplicateEmails($emails);

      $this->extractionStats['emails_found'] += count($emails);
      $this->extractionStats['extraction_time'] += microtime(true) - $startTime;

      // Validate emails
      $validationStartTime = microtime(true);
      $validatedEmails = $this->validateEmails($emails, $url, $pageTitle);
      $this->extractionStats['validation_time'] += microtime(true) - $validationStartTime;
      $this->extractionStats['valid_emails'] += count($validatedEmails);

      return $validatedEmails;
    } catch (Exception $e) {
      error_log("Email extraction error for {$url}: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Extract emails using regex patterns
   */
  private function extractWithRegex($html)
  {
    $emails = [];

    foreach ($this->emailRegexPatterns as $pattern) {
      if (preg_match_all($pattern, $html, $matches)) {
        $emails = array_merge($emails, $matches[0]);
      }
    }

    return $emails;
  }

  /**
   * Extract emails from HTML structure
   */
  private function extractFromHtmlStructure($html)
  {
    $emails = [];

    try {
      // Create DOM document
      $dom = new DOMDocument();
      libxml_use_internal_errors(true);
      $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
      libxml_clear_errors();

      $xpath = new DOMXPath($dom);

      // Extract from mailto links
      $mailtoLinks = $xpath->query('//a[starts-with(@href, "mailto:")]');
      foreach ($mailtoLinks as $link) {
        /** @var DOMElement $link */
        $href = $link->getAttribute('href');
        $email = str_replace('mailto:', '', $href);
        $email = strtok($email, '?'); // Remove query parameters
        if ($email) {
          $emails[] = $email;
        }
      }

      // Extract from common email containers
      $emailSelectors = [
        '//span[contains(@class, "email")]',
        '//div[contains(@class, "email")]',
        '//div[contains(@class, "contact")]',
        '//footer//text()',
        '//*[@id="contact"]//text()',
        '//*[contains(@class, "contact-info")]//text()'
      ];

      foreach ($emailSelectors as $selector) {
        $nodes = $xpath->query($selector);
        foreach ($nodes as $node) {
          $text = $node->textContent;
          if (preg_match_all($this->emailRegexPatterns[0], $text, $matches)) {
            $emails = array_merge($emails, $matches[0]);
          }
        }
      }
    } catch (Exception $e) {
      error_log("HTML parsing error: " . $e->getMessage());
    }

    return $emails;
  }

  /**
   * Extract obfuscated emails
   */
  private function extractObfuscatedEmails($html)
  {
    $emails = [];

    // Pattern 1: "user [at] domain [dot] com"
    if (preg_match_all('/([a-zA-Z0-9._%+-]+)\s*\[\s*at\s*\]\s*([a-zA-Z0-9.-]+)\s*\[\s*dot\s*\]\s*([a-zA-Z]{2,})/i', $html, $matches, PREG_SET_ORDER)) {
      foreach ($matches as $match) {
        $email = $match[1] . '@' . $match[2] . '.' . $match[3];
        $emails[] = $email;
      }
    }

    // Pattern 2: "user (at) domain (dot) com"
    if (preg_match_all('/([a-zA-Z0-9._%+-]+)\s*\(\s*at\s*\)\s*([a-zA-Z0-9.-]+)\s*\(\s*dot\s*\)\s*([a-zA-Z]{2,})/i', $html, $matches, PREG_SET_ORDER)) {
      foreach ($matches as $match) {
        $email = $match[1] . '@' . $match[2] . '.' . $match[3];
        $emails[] = $email;
      }
    }

    // Pattern 3: JavaScript encoded emails
    if (preg_match_all('/[\'"]([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})[\'"]/', $html, $matches)) {
      $emails = array_merge($emails, $matches[1]);
    }

    // Pattern 4: ROT13 or other simple encodings
    $this->extractEncodedEmails($html, $emails);

    return $emails;
  }

  /**
   * Extract from JSON-LD structured data
   */
  private function extractFromStructuredData($html)
  {
    $emails = [];

    try {
      // Extract JSON-LD blocks
      if (preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches)) {
        foreach ($matches[1] as $jsonContent) {
          $data = json_decode($jsonContent, true);
          if ($data) {
            $this->extractEmailsFromStructuredArray($data, $emails);
          }
        }
      }

      // Extract from microdata
      if (preg_match_all('/itemprop=["\']email["\'][^>]*content=["\']([^"\']+)["\']/', $html, $matches)) {
        $emails = array_merge($emails, $matches[1]);
      }
    } catch (Exception $e) {
      error_log("Structured data parsing error: " . $e->getMessage());
    }

    return $emails;
  }

  /**
   * Recursively extract emails from structured data arrays
   */
  private function extractEmailsFromStructuredArray($data, &$emails)
  {
    if (is_array($data)) {
      foreach ($data as $key => $value) {
        if (is_string($key) && (strpos(strtolower($key), 'email') !== false)) {
          if (is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $emails[] = $value;
          }
        } elseif (is_array($value) || is_object($value)) {
          $this->extractEmailsFromStructuredArray($value, $emails);
        }
      }
    }
  }

  /**
   * Extract encoded emails (ROT13, base64, etc.)
   */
  private function extractEncodedEmails($html, &$emails)
  {
    // ROT13 encoded emails
    if (preg_match_all('/[a-zA-Z0-9._%+-]{5,}@[a-zA-Z0-9.-]{3,}\.[a-zA-Z]{2,}/', str_rot13($html), $matches)) {
      foreach ($matches[0] as $email) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
          $emails[] = $email;
        }
      }
    }

    // Base64 encoded patterns
    if (preg_match_all('/[A-Za-z0-9+\/]{20,}={0,2}/', $html, $matches)) {
      foreach ($matches[0] as $encoded) {
        $decoded = base64_decode($encoded, true);
        if ($decoded && preg_match($this->emailRegexPatterns[0], $decoded, $emailMatches)) {
          $emails = array_merge($emails, $emailMatches);
        }
      }
    }
  }

  /**
   * Deduplicate emails and clean formatting
   */
  private function deduplicateEmails($emails)
  {
    $cleanEmails = [];

    foreach ($emails as $email) {
      // Clean and normalize
      $email = trim(strtolower($email));
      $email = preg_replace('/[^\w@.-]/', '', $email);

      if (!empty($email) && !in_array($email, $cleanEmails)) {
        $cleanEmails[] = $email;
      }
    }

    return $cleanEmails;
  }

  /**
   * Validate emails with context information
   */
  private function validateEmails($emails, $url, $pageTitle)
  {
    $validatedEmails = [];

    foreach ($emails as $email) {
      $validationResult = $this->validateSingleEmail($email, $url, $pageTitle);
      if ($validationResult) {
        $validatedEmails[] = $validationResult;
      }
    }

    return $validatedEmails;
  }

  /**
   * Validate a single email with detailed information
   */
  private function validateSingleEmail($email, $url, $pageTitle)
  {
    // Basic format validation
    if (!isValidEmailFormat($email)) {
      return null;
    }

    $domain = substr(strrchr($email, "@"), 1);
    $localPart = substr($email, 0, strrpos($email, '@'));

    // Domain validation with caching
    $domainValid = isValidDomain($domain);

    // Calculate confidence score
    $confidence = $this->calculateConfidenceScore($email, $domain, $url);

    return [
      'email' => $email,
      'domain' => $domain,
      'local_part' => $localPart,
      'is_valid' => true,
      'domain_valid' => $domainValid,
      'confidence_score' => $confidence,
      'page_title' => $pageTitle,
      'extraction_method' => $this->getExtractionMethod($email),
      'context_text' => $this->getEmailContext($email, $url)
    ];
  }

  /**
   * Calculate confidence score for email validity
   */
  private function calculateConfidenceScore($email, $domain, $url)
  {
    $score = 1.0;

    // Reduce score for common false positives
    $localPart = substr($email, 0, strrpos($email, '@'));
    $commonFakeUsers = ['admin', 'test', 'demo', 'example', 'sample'];
    if (in_array($localPart, $commonFakeUsers)) {
      $score -= 0.3;
    }

    // Reduce score for very common domains
    $commonDomains = ['gmail.com', 'yahoo.com', 'hotmail.com'];
    if (in_array($domain, $commonDomains)) {
      $score -= 0.1;
    }

    // Increase score if domain matches URL domain
    $urlDomain = parse_url($url, PHP_URL_HOST);
    if ($urlDomain && strpos($domain, str_replace('www.', '', $urlDomain)) !== false) {
      $score += 0.2;
    }

    // Ensure score is between 0.0 and 1.0
    return max(0.0, min(1.0, $score));
  }

  /**
   * Determine extraction method used
   */
  private function getExtractionMethod($email)
  {
    // This is simplified - in practice you'd track during extraction
    if (strpos($email, 'mailto:') !== false) {
      return 'mailto';
    } elseif (preg_match('/\[at\]|\(at\)/', $email)) {
      return 'obfuscated';
    } else {
      return 'regex';
    }
  }

  /**
   * Get context text around email
   */
  private function getEmailContext($email, $html)
  {
    $position = strpos($html, $email);
    if ($position === false) {
      return null;
    }

    // Extract 100 characters before and after
    $start = max(0, $position - 100);
    $length = min(strlen($html) - $start, 200);
    $context = substr($html, $start, $length);

    // Clean HTML tags and normalize whitespace
    $context = strip_tags($context);
    $context = preg_replace('/\s+/', ' ', $context);

    return trim($context);
  }

  /**
   * Get extraction statistics
   */
  public function getStats()
  {
    return $this->extractionStats;
  }

  /**
   * Reset statistics
   */
  public function resetStats()
  {
    $this->extractionStats = [
      'total_processed' => 0,
      'emails_found' => 0,
      'valid_emails' => 0,
      'extraction_time' => 0,
      'validation_time' => 0
    ];
  }
}
