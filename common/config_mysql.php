<?php
// Configuration file for Employee Tracker System - MySQL Version

// MySQL Database configuration for cPanel hosting
// define('DB_HOST', 'localhost'); // Usually localhost on cPanel
// define('DB_NAME', 'umarbyhn_tabs_tracker'); // Your database name  
// define('DB_USERNAME', 'umarbyhn_t_fasion'); // Your database username
// define('DB_PASSWORD', '&(7VTig.%KT)'); // Your database password
// define('DB_CHARSET', 'utf8mb4');

define('DB_HOST', 'localhost'); // Usually localhost on cPanel
define('DB_NAME', 'umarbyhn_tabs_tracker'); // Your database name  
define('DB_USERNAME', 'root'); // Your database username
define('DB_PASSWORD', ''); // Your database password
define('DB_CHARSET', 'utf8mb4');


// File paths (adjust for cPanel structure)
define('UPLOAD_PATH', __DIR__ . '/../employee/uploads/');
define('DECRYPTED_PATH', __DIR__ . '/../admin/decrypted/');

// Admin credentials
define('ADMIN_USERNAME', 'office');
define('ADMIN_PASSWORD', 'nomi5900');

// Encryption password
define('DECRYPT_PASSWORD', 'nomi311');

// File settings
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB
define('ALLOWED_EXTENSIONS', ['encrypted.xlsx']);

// Session settings for enhanced security
ini_set('session.gc_maxlifetime', 3600); // 1 hour
ini_set('session.cookie_httponly', 1); // Prevent XSS
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
ini_set('session.use_strict_mode', 1); // Prevent session fixation
session_set_cookie_params([
  'lifetime' => 3600,
  'path' => '/',
  'domain' => '',
  'secure' => false, // Set to true if using HTTPS
  'httponly' => true,
  'samesite' => 'Strict'
]);

// Start session securely
if (session_status() == PHP_SESSION_NONE) {
  session_start();

  // Regenerate session ID periodically for security
  if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
  } else if (time() - $_SESSION['created'] > 1800) { // 30 minutes
    session_regenerate_id(true);
    $_SESSION['created'] = time();
  }
}

// Database initialization for MySQL
function initDatabase()
{
  try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Create reports table with MySQL syntax
    $pdo->exec("
            CREATE TABLE IF NOT EXISTS reports (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_name VARCHAR(100) NOT NULL,
                original_filename VARCHAR(255) NOT NULL,
                decrypted_filename VARCHAR(255) NOT NULL,
                upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                file_size BIGINT,
                row_count INT DEFAULT 0,
                ip_address VARCHAR(45),
                INDEX idx_employee_name (employee_name),
                INDEX idx_upload_date (upload_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

    return $pdo;
  } catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    throw new Exception("Database connection failed. Please check your configuration.");
  }
}

// Get database connection
function getDB()
{
  static $pdo = null;
  if ($pdo === null) {
    try {
      $pdo = initDatabase();
    } catch (Exception $e) {
      throw $e; // Re-throw to be handled by calling code
    }
  }
  return $pdo;
}

// Enhanced admin authentication with session security
function isAdmin()
{
  // Check if session exists and is valid
  if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    return false;
  }

  // Check session timeout
  if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
    session_destroy();
    return false;
  }

  // Update last activity time
  $_SESSION['last_activity'] = time();

  // Check if session IP matches (optional security measure)
  if (isset($_SESSION['user_ip']) && $_SESSION['user_ip'] !== $_SERVER['REMOTE_ADDR']) {
    session_destroy();
    return false;
  }

  return true;
}

// Redirect to login if not admin with proper path handling
function requireAdmin()
{
  if (!isAdmin()) {
    // Clear any invalid session data
    session_destroy();

    // Redirect to login page
    $login_url = 'login.php';

    // Handle different directory structures
    if (!file_exists($login_url)) {
      $login_url = '../admin/login.php';
    }
    if (!file_exists($login_url)) {
      $login_url = '/admin/login.php';
    }

    header('Location: ' . $login_url);
    exit;
  }
}

// Enhanced login function
function adminLogin($username, $password)
{
  if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);

    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_username'] = $username;
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];
    $_SESSION['created'] = time();

    return true;
  }
  return false;
}

// Secure logout function
function adminLogout()
{
  // Clear all session variables
  $_SESSION = array();

  // Delete session cookie
  if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
      session_name(),
      '',
      time() - 42000,
      $params["path"],
      $params["domain"],
      $params["secure"],
      $params["httponly"]
    );
  }

  // Destroy session
  session_destroy();
}

// Sanitize filename for secure file handling
function sanitizeFilename($filename)
{
  $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);
  return substr($filename, 0, 100);
}

// Format file size
function formatFileSize($bytes)
{
  if ($bytes >= 1073741824) {
    return number_format($bytes / 1073741824, 2) . ' GB';
  } elseif ($bytes >= 1048576) {
    return number_format($bytes / 1048576, 2) . ' MB';
  } elseif ($bytes >= 1024) {
    return number_format($bytes / 1024, 2) . ' KB';
  } else {
    return $bytes . ' bytes';
  }
}

// CSRF Protection
function generateCSRFToken()
{
  if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}

function validateCSRFToken($token)
{
  return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Create directories if they don't exist
function ensureDirectories()
{
  $dirs = [UPLOAD_PATH, DECRYPTED_PATH];
  foreach ($dirs as $dir) {
    if (!file_exists($dir)) {
      mkdir($dir, 0755, true);
    }
  }
}

// Initialize directories on first load
ensureDirectories();
