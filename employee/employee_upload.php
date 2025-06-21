<?php
// employee_upload.php
require_once '../common/config_mysql.php';
// Optionally, require employee authentication here

$message = '';
$messageType = '';

function sanitize_employee_name($name)
{
  // Remove special characters, allow only letters, numbers, underscore, dash
  $name = preg_replace('/[^A-Za-z0-9_-]/', '', str_replace(' ', '_', trim($name)));
  return $name ?: 'employee';
}

/**
 * Professional XLSX row parsing using SimpleXLSX library
 * @param string $filePath Full path to XLSX file
 * @return int Number of data rows (excluding header)
 */
function parseXLSXRowsWithSimpleXLSX($filePath)
{
  if (!class_exists('ZipArchive')) {
    return 0; // Cannot use SimpleXLSX without ZipArchive
  }

  try {
    // Increase memory limit for large files
    $originalMemoryLimit = ini_get('memory_limit');
    ini_set('memory_limit', '256M');

    require_once '../libs/SimpleXLSX.php';

    // Parse with error handling
    $xlsx = SimpleXLSX::parse($filePath);
    if (!$xlsx) {
      error_log('SimpleXLSX parsing failed: ' . SimpleXLSX::parseError());
      return 0;
    }

    $rows = $xlsx->rows();
    if (!is_array($rows) || empty($rows)) {
      error_log('SimpleXLSX returned empty or invalid rows array');
      return 0;
    }

    // Count data rows (exclude header row if present)
    $totalRows = count($rows);
    $dataRows = max(0, $totalRows - 1); // Assume first row is header

    // Restore original memory limit
    ini_set('memory_limit', $originalMemoryLimit);

    return $dataRows;
  } catch (Exception $e) {
    error_log('SimpleXLSX parsing exception: ' . $e->getMessage());
    return 0;
  } finally {
    // Always restore memory limit
    if (isset($originalMemoryLimit)) {
      ini_set('memory_limit', $originalMemoryLimit);
    }
  }
}

/**
 * Professional XLSX row parsing using direct XML parsing
 * @param string $filePath Full path to XLSX file
 * @return int Number of data rows
 */
function parseXLSXRowsDirectXML($filePath)
{
  if (!class_exists('ZipArchive')) {
    return 0;
  }

  try {
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== TRUE) {
      return 0;
    }

    // Get the main worksheet XML
    $worksheetXML = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($worksheetXML === false) {
      $zip->close();
      return 0;
    }

    $zip->close();

    // Parse XML to count rows
    $xml = simplexml_load_string($worksheetXML);
    if (!$xml) {
      return 0;
    }

    // Count row elements
    $rows = $xml->xpath('//row');
    if (!$rows) {
      return 0;
    }

    $totalRows = count($rows);
    return max(0, $totalRows - 1); // Exclude header row

  } catch (Exception $e) {
    error_log('Direct XML parsing exception: ' . $e->getMessage());
    return 0;
  }
}

/**
 * Professional XLSX row parsing using ZipArchive and shared strings
 * @param string $filePath Full path to XLSX file
 * @return int Number of data rows
 */
function parseXLSXRowsZipArchive($filePath)
{
  if (!class_exists('ZipArchive')) {
    return 0;
  }

  try {
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== TRUE) {
      return 0;
    }

    // Get worksheet dimension from xl/worksheets/sheet1.xml
    $worksheetXML = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($worksheetXML === false) {
      $zip->close();
      return 0;
    }

    $zip->close();

    // Use regex to find dimension or count rows quickly
    if (preg_match('/<dimension ref="[A-Z]+1:[A-Z]+(\d+)"/', $worksheetXML, $matches)) {
      // Found dimension attribute - get last row number
      $lastRow = intval($matches[1]);
      return max(0, $lastRow - 1); // Exclude header row
    }

    // Fallback: count actual row tags
    $rowCount = substr_count($worksheetXML, '<row ');
    return max(0, $rowCount - 1); // Exclude header row

  } catch (Exception $e) {
    error_log('ZipArchive parsing exception: ' . $e->getMessage());
    return 0;
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['encrypted_file'])) {
  try {
    $employeeName = trim($_POST['employee_name'] ?? '');
    if (empty($employeeName)) {
      throw new Exception('Please enter your name.');
    }
    $sanitizedEmployee = sanitize_employee_name($employeeName);
    $uploadedFile = $_FILES['encrypted_file'];

    // Validate file
    if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
      throw new Exception('File upload failed');
    }

    if ($uploadedFile['size'] > MAX_FILE_SIZE) {
      throw new Exception('File size exceeds maximum allowed size');
    }

    $filename = $uploadedFile['name'];
    $fileExt = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    // Check if it's an encrypted Excel file
    if (!str_contains($filename, 'encrypted') || !in_array($fileExt, ['xlsx'])) {
      throw new Exception('Only encrypted Excel files (.encrypted.xlsx) are allowed');
    }

    // Read the encrypted file content
    $encryptedContent = file_get_contents($uploadedFile['tmp_name']);
    if (!$encryptedContent) {
      throw new Exception('Failed to read uploaded file');
    }

    // Check for the encryption signature
    $signatureText = 'ENC_XLSX_nomi311:';
    $signatureLength = strlen($signatureText);

    if (substr($encryptedContent, 0, $signatureLength) !== $signatureText) {
      throw new Exception('Invalid encrypted file format. File must be encrypted with the Chrome extension.');
    }

    // Extract the encrypted data (remove signature)
    $actualEncryptedData = substr($encryptedContent, $signatureLength);

    // Decrypt the file using XOR with the same password as the extension
    $decryptPassword = 'nomi311'; // Hardcoded for now, should match extension
    $passwordBytes = $decryptPassword;
    $passwordLength = strlen($passwordBytes);

    $decryptedContent = '';
    for ($i = 0; $i < strlen($actualEncryptedData); $i++) {
      $decryptedContent .= chr(ord($actualEncryptedData[$i]) ^ ord($passwordBytes[$i % $passwordLength]));
    }

    // Debug: Check first bytes of decrypted content
    $firstBytes = substr($decryptedContent, 0, 4);
    $isValidXLSX = (substr($firstBytes, 0, 2) === "PK"); // XLSX is a ZIP archive
    if (!$isValidXLSX) {
      throw new Exception('Decryption failed: Not a valid XLSX file (does not start with PK).');
    }

    // Save decrypted file to admin/decrypted folder with employee name (binary-safe)
    $saveName = '../admin/decrypted/' . date('Y-m-d_H-i-s_') . $sanitizedEmployee . '_report.xlsx';
    $fp = fopen($saveName, 'wb');
    if (!$fp || fwrite($fp, $decryptedContent) === false) {
      throw new Exception('Failed to save decrypted file.');
    }
    fclose($fp);

    // Professional XLSX row counting with comprehensive error handling
    $rowCount = 0;
    $parseMessage = '';

    // Ensure file is fully written before processing
    clearstatcache();
    if (!file_exists($saveName) || filesize($saveName) === 0) {
      throw new Exception('Failed to save decrypted file properly.');
    }

    // Method 1: Professional SimpleXLSX parsing with proper error handling
    $rowCount = parseXLSXRowsWithSimpleXLSX($saveName);
    if ($rowCount > 0) {
      $parseMessage = '<br><span style="color:#4caf50">✓ Successfully parsed XLSX: ' . number_format($rowCount) . ' data rows found.</span>';
    } else {
      // Method 2: Direct XML parsing (professional fallback)
      $rowCount = parseXLSXRowsDirectXML($saveName);
      if ($rowCount > 0) {
        $parseMessage = '<br><span style="color:#4caf50">✓ Successfully parsed via XML: ' . number_format($rowCount) . ' data rows found.</span>';
      } else {
        // Method 3: ZipArchive native parsing (ultimate fallback)
        $rowCount = parseXLSXRowsZipArchive($saveName);
        if ($rowCount > 0) {
          $parseMessage = '<br><span style="color:#4caf50">✓ Successfully parsed via ZipArchive: ' . number_format($rowCount) . ' data rows found.</span>';
        } else {
          // Only if all professional methods fail
          throw new Exception('Unable to parse XLSX file. The file may be corrupted or in an unsupported format.');
        }
      }
    }

    // Add parse message to main message
    $message .= $parseMessage;

    // Insert into reports table so it appears in dashboard
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO reports (employee_name, original_filename, decrypted_filename, upload_date, file_size, row_count, ip_address, category, admin_notes, is_archived) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, 0)");
    $originalFilename = $filename;
    $decryptedFilename = basename($saveName);
    $fileSize = filesize($saveName);
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    $category = 'General';
    $adminNotes = '';
    $stmt->execute([
      $employeeName,
      $originalFilename,
      $decryptedFilename,
      $fileSize,
      $rowCount,
      $ipAddress,
      $category,
      $adminNotes
    ]);

    $message = 'File uploaded and decrypted successfully! Saved as: ' . htmlspecialchars(basename($saveName));
    $messageType = 'success';
  } catch (Exception $e) {
    $message = 'Error: ' . $e->getMessage();
    $messageType = 'error';
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Employee Upload Encrypted Excel</title>
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

    .upload-container {
      background: white;
      padding: 40px 30px 30px 30px;
      border-radius: 20px;
      box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
      max-width: 450px;
      width: 100%;
      text-align: center;
    }

    .upload-header {
      margin-bottom: 30px;
    }

    .upload-header h1 {
      color: #667eea;
      font-size: 28px;
      margin-bottom: 10px;
    }

    .upload-header p {
      color: #666;
      font-size: 15px;
    }

    .instructions {
      background: #e3f2fd;
      border: 1px solid #90caf9;
      padding: 18px;
      border-radius: 10px;
      margin-bottom: 25px;
      text-align: left;
      font-size: 15px;
      color: #1976d2;
    }

    .form-group {
      margin-bottom: 22px;
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
    input[type="file"] {
      width: 100%;
      padding: 13px;
      border: 2px solid #e1e5e9;
      border-radius: 10px;
      font-size: 15px;
      transition: border-color 0.3s ease;
      background: #f8f9fa;
    }

    input[type="text"]:focus,
    input[type="file"]:focus {
      outline: none;
      border-color: #667eea;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.08);
    }

    .upload-btn {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 15px 0;
      border: none;
      border-radius: 10px;
      font-size: 17px;
      font-weight: 600;
      cursor: pointer;
      width: 100%;
      margin-top: 10px;
      transition: transform 0.3s, box-shadow 0.3s;
    }

    .upload-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(102, 126, 234, 0.18);
    }

    .upload-btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none;
    }

    .alert {
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      text-align: center;
      font-size: 15px;
    }

    .alert.success {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }

    .alert.error {
      background: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }

    .links {
      margin-top: 30px;
      text-align: center;
    }

    .links a {
      color: #667eea;
      text-decoration: none;
      font-weight: 500;
      margin: 0 10px;
    }

    .links a:hover {
      text-decoration: underline;
    }

    @media (max-width: 600px) {
      .upload-container {
        padding: 20px 8px;
      }

      .upload-header h1 {
        font-size: 22px;
      }
    }
  </style>
</head>

<body>
  <div class="upload-container">
    <div class="upload-header">
      <h1>⬆️ Employee File Upload</h1>
      <p>Send your encrypted Excel report to the admin securely.</p>
    </div>
    <div class="instructions">
      <strong>Instructions:</strong>
      <ul style="margin: 10px 0 0 18px;">
        <li>Only encrypted Excel files (.encrypted.xlsx) generated by the Chrome extension are allowed.</li>
        <li>Enter your name exactly as you want it to appear in the report filename.</li>
        <li>Maximum file size: <?php echo number_format(MAX_FILE_SIZE / (1024 * 1024), 1); ?> MB.</li>
        <li>Your file will be decrypted and sent to the admin. The filename will include your name.</li>
      </ul>
    </div>
    <?php if (!empty($message)): ?>
      <div class="alert <?php echo $messageType; ?>"><?php echo $message; ?></div>
    <?php endif; ?>
    <form method="POST" enctype="multipart/form-data" id="uploadForm" autocomplete="off">
      <div class="form-group">
        <label for="employee_name">Your Name:</label>
        <input type="text" id="employee_name" name="employee_name" maxlength="32" required placeholder="e.g. Ali" value="<?php echo htmlspecialchars($_POST['employee_name'] ?? ''); ?>">
      </div>
      <div class="form-group">
        <label for="encrypted_file">Encrypted Excel File (.encrypted.xlsx):</label>
        <input type="file" id="encrypted_file" name="encrypted_file" accept=".xlsx" required>
      </div>
      <button type="submit" class="upload-btn" id="uploadBtn">Upload & Decrypt</button>
    </form>
    <div class="links">
      <a href="../login.php">⬅️ Back to Login</a>
      <a href="./">Employee Portal</a>
    </div>
  </div>
  <script>
    // Autofocus on name
    document.getElementById('employee_name').focus();
    // Upload progress with AJAX
    document.getElementById('uploadForm').addEventListener('submit', function(e) {
      e.preventDefault();
      var name = document.getElementById('employee_name').value.trim();
      var file = document.getElementById('encrypted_file').files[0];
      if (!name || !file) {
        alert('Please enter your name and select a file.');
        return false;
      }
      var btn = document.getElementById('uploadBtn');
      btn.disabled = true;
      btn.textContent = 'Uploading... 0%';
      // Create progress bar if not exists
      var progressBar = document.getElementById('uploadProgressBar');
      if (!progressBar) {
        progressBar = document.createElement('div');
        progressBar.id = 'uploadProgressBar';
        progressBar.style.width = '100%';
        progressBar.style.background = '#e3f2fd';
        progressBar.style.borderRadius = '8px';
        progressBar.style.margin = '18px 0 0 0';
        progressBar.innerHTML = '<div id="uploadProgressInner" style="height:18px;width:0%;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);border-radius:8px;text-align:center;color:#fff;font-weight:600;transition:width 0.2s;line-height:18px;font-size:13px;">0%</div>';
        btn.parentNode.insertBefore(progressBar, btn.nextSibling);
      }
      var inner = document.getElementById('uploadProgressInner');
      if (inner) {
        inner.style.width = '0%';
        inner.textContent = '0%';
      }
      var formData = new FormData(this);
      var xhr = new XMLHttpRequest();
      xhr.upload.addEventListener('progress', function(e) {
        if (e.lengthComputable) {
          var percent = Math.round((e.loaded / e.total) * 100);
          btn.textContent = 'Uploading... ' + percent + '%';
          if (inner) {
            inner.style.width = percent + '%';
            inner.textContent = percent + '%';
          }
        }
      });
      xhr.onreadystatechange = function() {
        if (xhr.readyState === XMLHttpRequest.DONE) {
          btn.disabled = false;
          btn.textContent = 'Upload & Decrypt';
          if (inner) {
            inner.style.width = '100%';
            inner.textContent = '100%';
          }
          // Replace alert if present
          var parser = new DOMParser();
          var doc = parser.parseFromString(xhr.responseText, 'text/html');
          var alert = doc.querySelector('.alert');
          var oldAlert = document.querySelector('.alert');
          if (alert) {
            if (oldAlert) oldAlert.replaceWith(alert);
            else btn.parentNode.parentNode.insertBefore(alert, btn.parentNode);
          }
        }
      };
      xhr.open('POST', '', true);
      xhr.send(formData);
    });
  </script>
</body>

</html>