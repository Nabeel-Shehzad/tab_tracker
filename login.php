<?php
require_once 'common/config_mysql.php';

// If already logged in, redirect to dashboard
if (isAdmin()) {
  header('Location: dashboard.php');
  exit;
}

$error = '';
$success = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // CSRF Protection
  if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    $error = 'Invalid request. Please try again.';
  } else {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
      $error = 'Please enter both username and password';
    } else {
      if (adminLogin($username, $password)) {
        header('Location: dashboard.php');
        exit;
      } else {
        $error = 'Invalid username or password';
        // Log failed login attempt
        error_log("Failed login attempt from IP: " . $_SERVER['REMOTE_ADDR'] . " for username: " . $username);
      }
    }
  }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login - Employee Tracker</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .login-container {
      background: white;
      padding: 50px;
      border-radius: 20px;
      box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
      max-width: 450px;
      width: 100%;
      text-align: center;
    }

    .login-header {
      margin-bottom: 40px;
    }

    .login-header h1 {
      color: #667eea;
      font-size: 32px;
      margin-bottom: 10px;
    }

    .login-header p {
      color: #666;
      font-size: 16px;
    }

    .form-group {
      margin-bottom: 25px;
      text-align: left;
    }

    label {
      display: block;
      margin-bottom: 8px;
      font-weight: 600;
      color: #333;
      font-size: 14px;
    }

    input[type="text"],
    input[type="password"] {
      width: 100%;
      padding: 15px;
      border: 2px solid #e1e5e9;
      border-radius: 10px;
      font-size: 16px;
      transition: border-color 0.3s ease;
    }

    input[type="text"]:focus,
    input[type="password"]:focus {
      outline: none;
      border-color: #667eea;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .login-btn {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 18px 30px;
      border: none;
      border-radius: 10px;
      font-size: 18px;
      font-weight: 600;
      cursor: pointer;
      width: 100%;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .login-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
    }

    .login-btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none;
    }

    .error {
      background: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      text-align: center;
    }

    .success {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      text-align: center;
    }

    .security-notice {
      background: #e3f2fd;
      border: 1px solid #90caf9;
      padding: 20px;
      border-radius: 10px;
      margin-top: 30px;
      text-align: left;
    }

    .security-notice h3 {
      color: #1976d2;
      margin-bottom: 10px;
      text-align: center;
    }

    .security-notice ul {
      color: #1976d2;
      margin: 10px 0 10px 20px;
      font-size: 14px;
    }

    .security-notice li {
      margin-bottom: 5px;
    }

    .credentials {
      background: #fff3cd;
      border: 1px solid #ffeaa7;
      padding: 20px;
      border-radius: 10px;
      margin-top: 20px;
      text-align: left;
    }

    .credentials h3 {
      color: #856404;
      margin-bottom: 10px;
      text-align: center;
    }

    .credentials p {
      color: #856404;
      margin: 5px 0;
      font-family: monospace;
      background: rgba(255, 255, 255, 0.5);
      padding: 5px;
      border-radius: 4px;
    }

    .links {
      margin-top: 30px;
      text-align: center;
    }

    .links a {
      color: #667eea;
      text-decoration: none;
      font-weight: 500;
    }

    .links a:hover {
      text-decoration: underline;
    }

    @media (max-width: 768px) {
      .login-container {
        padding: 30px;
        margin: 10px;
      }

      .login-header h1 {
        font-size: 28px;
      }
    }
  </style>
</head>

<body>
  <div class="login-container">
    <div class="login-header">
      <h1>🔐 Admin Login</h1>
      <p>Employee Tracker Dashboard</p>
    </div>

    <?php if (!empty($error)): ?>
      <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
      <div class="success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="POST" id="loginForm">
      <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

      <div class="form-group">
        <label for="username">👤 Username:</label>
        <input type="text" id="username" name="username" required
          autocomplete="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
      </div>

      <div class="form-group">
        <label for="password">🔑 Password:</label>
        <input type="password" id="password" name="password" required
          autocomplete="current-password">
      </div>

      <button type="submit" class="login-btn" id="loginBtn">
        🚀 Login to Dashboard
      </button>
    </form>
    <div class="links">
      <a href="employee/employee_upload.php">👥 Employee Portal</a>
    </div>
  </div>

  <script>
    // Auto-focus on username field
    document.getElementById('username').focus();

    // Form validation
    document.getElementById('loginForm').addEventListener('submit', function(e) {
      const username = document.getElementById('username').value.trim();
      const password = document.getElementById('password').value;
      const loginBtn = document.getElementById('loginBtn');

      if (!username || !password) {
        e.preventDefault();
        alert('Please enter both username and password');
        return;
      }

      loginBtn.disabled = true;
      loginBtn.textContent = '🔄 Logging in...';

      // Re-enable button after 5 seconds in case of error
      setTimeout(() => {
        loginBtn.disabled = false;
        loginBtn.textContent = '🚀 Login to Dashboard';
      }, 5000);
    });

    // Clear any error messages after user starts typing
    document.getElementById('username').addEventListener('input', clearError);
    document.getElementById('password').addEventListener('input', clearError);

    function clearError() {
      const errorDiv = document.querySelector('.error');
      if (errorDiv) {
        errorDiv.style.display = 'none';
      }
    }
  </script>
</body>

</html>