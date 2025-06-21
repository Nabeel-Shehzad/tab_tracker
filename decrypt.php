<?php
require_once 'common/config_mysql.php';
requireAdmin();

// Handle download request
if (isset($_GET['download']) && isset($_SESSION['temp_decrypted_data'])) {
  $decryptedData = base64_decode($_SESSION['temp_decrypted_data']);
  $filename = $_SESSION['temp_filename'] ?? 'decrypted_file.xlsx';

  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  header('Content-Length: ' . strlen($decryptedData));

  echo $decryptedData;
  exit;
}

// Handle clear request
if (isset($_GET['clear'])) {
  unset($_SESSION['temp_decrypted_data']);
  unset($_SESSION['temp_filename']);
  header('Location: decrypt.php');
  exit;
}

$message = '';
$messageType = '';
$decryptedData = '';
$originalFilename = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['encrypted_file'])) {
  try {
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
    }    // Read the encrypted file content
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
    $decryptPassword = DECRYPT_PASSWORD;
    $passwordBytes = $decryptPassword;
    $passwordLength = strlen($passwordBytes);

    $decryptedContent = '';
    for ($i = 0; $i < strlen($actualEncryptedData); $i++) {
      $decryptedContent .= chr(ord($actualEncryptedData[$i]) ^ ord($passwordBytes[$i % $passwordLength]));
    }

    // Store decrypted data in session for viewing/download
    $_SESSION['temp_decrypted_data'] = base64_encode($decryptedContent);
    $_SESSION['temp_filename'] = str_replace('.encrypted.xlsx', '.xlsx', $filename);

    $originalFilename = $_SESSION['temp_filename'];
    $message = 'File decrypted successfully! You can now view or download it below.';
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
  <title>Decrypt Excel File - Admin</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: #f8f9fa;
      color: #333;
    }

    .header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 20px 0;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .header-content {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .header h1 {
      font-size: 24px;
    }

    .btn {
      padding: 10px 20px;
      border: none;
      border-radius: 6px;
      font-size: 14px;
      font-weight: 500;
      cursor: pointer;
      text-decoration: none;
      display: inline-block;
      transition: transform 0.2s ease;
      color: white;
    }

    .btn:hover {
      transform: translateY(-1px);
    }

    .btn-secondary {
      background: rgba(255, 255, 255, 0.2);
      border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .btn-primary {
      background: #007bff;
      color: white;
    }

    .container {
      max-width: 800px;
      margin: 0 auto;
      padding: 30px 20px;
    }

    .upload-section {
      background: white;
      padding: 30px;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      margin-bottom: 30px;
    }

    .section-title {
      font-size: 20px;
      margin-bottom: 20px;
      color: #333;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .form-group {
      margin-bottom: 20px;
    }

    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: 600;
      color: #555;
    }

    .file-input-container {
      position: relative;
      display: inline-block;
      width: 100%;
    }

    .file-input {
      width: 100%;
      padding: 12px;
      border: 2px dashed #ddd;
      border-radius: 8px;
      background: #f9f9f9;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .file-input:hover {
      border-color: #667eea;
      background: #f0f4ff;
    }

    .file-input[type="file"] {
      opacity: 0;
      position: absolute;
      z-index: -1;
    }

    .file-input-label {
      display: block;
      padding: 15px;
      text-align: center;
      cursor: pointer;
      border: 2px dashed #ddd;
      border-radius: 8px;
      background: #f9f9f9;
      transition: all 0.3s ease;
    }

    .file-input-label:hover {
      border-color: #667eea;
      background: #f0f4ff;
    }

    .file-input-label.has-file {
      border-color: #28a745;
      background: #f0fff4;
      color: #28a745;
    }

    .excel-viewer {
      background: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      overflow: hidden;
      margin-top: 20px;
    }

    .viewer-header {
      padding: 15px;
      background: #f8f9fa;
      border-bottom: 1px solid #e1e5e9;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .search-box {
      padding: 8px 12px;
      border: 1px solid #e1e5e9;
      border-radius: 4px;
      font-size: 14px;
    }

    .table-container {
      overflow: auto;
      max-height: 400px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 14px;
    }

    th,
    td {
      padding: 8px 12px;
      text-align: left;
      border-bottom: 1px solid #e1e5e9;
      white-space: nowrap;
    }

    th {
      background: #f8f9fa;
      font-weight: 600;
      position: sticky;
      top: 0;
    }

    tr:hover {
      background: #f8f9fa;
    }

    .loading {
      text-align: center;
      padding: 40px 20px;
      color: #666;
    }

    .upload-info {
      background: #f8f9fa;
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: 14px;
      color: #666;
    }

    .upload-info ul {
      margin: 10px 0;
      padding-left: 20px;
    }

    .message {
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      text-align: center;
    }

    .message.success {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }

    .message.error {
      background: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }

    .message a {
      color: inherit;
      font-weight: bold;
    }

    .decrypt-tool {
      background: white;
      padding: 30px;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .tool-description {
      background: #e3f2fd;
      padding: 20px;
      border-radius: 8px;
      margin-bottom: 20px;
      border-left: 4px solid #2196f3;
    }

    .tool-description h3 {
      color: #1976d2;
      margin-bottom: 10px;
    }

    @media (max-width: 768px) {
      .header-content {
        flex-direction: column;
        gap: 15px;
      }

      .container {
        padding: 20px 10px;
      }

      .upload-section,
      .decrypt-tool {
        padding: 20px;
      }
    }
  </style>
</head>

<body>
  <header class="header">
    <div class="header-content">
      <h1>🔓 Decrypt Excel File</h1>
      <a href="dashboard.php" class="btn btn-secondary">⬅️ Back to Dashboard</a>
    </div>
  </header>

  <div class="container">
    <?php if (!empty($message)): ?>
      <div class="message <?php echo $messageType; ?>">
        <?php echo $message; ?>
      </div>
    <?php endif; ?>

    <div class="decrypt-tool">
      <div class="tool-description">
        <h3>📋 Manual File Upload & Processing</h3>
        <p>Upload Excel files (.xlsx, .xls) directly to the system. This tool is useful for:</p>
        <ul>
          <li>Testing file uploads without the Chrome extension</li>
          <li>Processing files manually received from employees</li>
          <li>Importing historical tracking data</li>
          <li>Debugging file processing issues</li>
        </ul>
      </div>

      <h2 class="section-title">
        📁 Upload Excel File
      </h2>

      <form method="POST" enctype="multipart/form-data" id="uploadForm">
        <div class="form-group">
          <label for="encrypted_file">Select Excel File:</label> <input type="file" id="encrypted_file" name="encrypted_file" accept=".xlsx" required style="display: none;">
          <label for="encrypted_file" class="file-input-label" id="fileLabel">
            <span id="fileText">📎 Click to select encrypted Excel file (.encrypted.xlsx)</span>
          </label>
        </div>

        <div class="upload-info">
          <strong>📝 Upload Information:</strong>
          <ul>
            <li>Maximum file size: <?php echo number_format(MAX_FILE_SIZE / (1024 * 1024), 1); ?>MB</li>
            <li>Supported formats: Encrypted Excel (.encrypted.xlsx)</li>
            <li>Files are decrypted in memory only - not saved permanently</li>
            <li>You can view and download the decrypted file immediately</li>
          </ul>
        </div><button type="submit" class="btn btn-primary" id="uploadBtn">
          🚀 Decrypt & Process File
        </button>
      </form>
    </div>

    <?php if (isset($_SESSION['temp_decrypted_data']) && !empty($_SESSION['temp_decrypted_data'])): ?>
      <div class="decrypt-tool">
        <h2 class="section-title">
          📊 Decrypted File Viewer
        </h2>

        <div style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center;">
          <strong>File: <?php echo htmlspecialchars($originalFilename ?: $_SESSION['temp_filename']); ?></strong>
          <a href="?download=1" class="btn btn-primary" style="margin-left: auto;">
            📥 Download Decrypted File
          </a>
          <a href="?clear=1" class="btn btn-secondary">
            🗑️ Clear
          </a>
        </div>

        <div class="excel-viewer">
          <div class="viewer-header">
            <h3>📈 Excel Data</h3>
            <input type="text" id="searchBox" placeholder="Search..." class="search-box">
          </div>

          <div id="excelContainer">
            <div class="loading">
              <h3>📊 Loading Excel Data...</h3>
              <p>Please wait while we process the file</p>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <script src="libs/xlsx.full.min.js"></script>
  <script>
    // Handle file input display
    const fileInput = document.getElementById('encrypted_file');
    const fileLabel = document.getElementById('fileLabel');
    const fileText = document.getElementById('fileText');
    const uploadBtn = document.getElementById('uploadBtn');

    fileInput.addEventListener('change', function(e) {
      if (e.target.files.length > 0) {
        const fileName = e.target.files[0].name;
        const fileSize = (e.target.files[0].size / (1024 * 1024)).toFixed(2);
        fileText.textContent = `📄 ${fileName} (${fileSize}MB)`;
        fileLabel.classList.add('has-file');
      } else {
        fileText.textContent = '📎 Click to select encrypted Excel file (.encrypted.xlsx)';
        fileLabel.classList.remove('has-file');
      }
    });

    // Handle form submission
    document.getElementById('uploadForm').addEventListener('submit', function(e) {
      uploadBtn.disabled = true;
      uploadBtn.textContent = '⏳ Processing...';
    });

    // Drag and drop functionality
    fileLabel.addEventListener('dragover', function(e) {
      e.preventDefault();
      fileLabel.style.borderColor = '#667eea';
      fileLabel.style.background = '#f0f4ff';
    });

    fileLabel.addEventListener('dragleave', function(e) {
      e.preventDefault();
      if (!fileLabel.classList.contains('has-file')) {
        fileLabel.style.borderColor = '#ddd';
        fileLabel.style.background = '#f9f9f9';
      }
    });

    fileLabel.addEventListener('drop', function(e) {
      e.preventDefault();
      const files = e.dataTransfer.files;
      if (files.length > 0) {
        fileInput.files = files;
        fileInput.dispatchEvent(new Event('change'));
      }
      fileLabel.style.borderColor = '#28a745';
      fileLabel.style.background = '#f0fff4';
    });
    <?php if (isset($_SESSION['temp_decrypted_data'])): ?>
      // Excel viewer functionality
      function initializeExcelViewer() {
        // Load Excel data if available
        if (typeof XLSX !== 'undefined') {
          loadDecryptedExcel();
        } else {
          // Wait for XLSX library to load
          setTimeout(function() {
            if (typeof XLSX !== 'undefined') {
              loadDecryptedExcel();
            } else {
              document.getElementById('excelContainer').innerHTML = `
              <div style="text-align: center; padding: 40px; color: #dc3545;">
                <h3>❌ XLSX Library Not Loaded</h3>
                <p>Unable to load Excel viewer. Please refresh the page.</p>
              </div>
            `;
            }
          }, 1000);
        }
      }

      async function loadDecryptedExcel() {
        try {
          // Get decrypted data from PHP session
          const base64Data = '<?php echo $_SESSION['temp_decrypted_data']; ?>';
          const binaryString = atob(base64Data);
          const bytes = new Uint8Array(binaryString.length);
          for (let i = 0; i < binaryString.length; i++) {
            bytes[i] = binaryString.charCodeAt(i);
          }

          const workbook = XLSX.read(bytes, {
            type: 'array'
          });
          const sheetName = workbook.SheetNames[0];
          const worksheet = workbook.Sheets[sheetName];
          const data = XLSX.utils.sheet_to_json(worksheet, {
            header: 1
          });

          displayExcelData(data);
        } catch (error) {
          console.error('Error loading Excel:', error);
          document.getElementById('excelContainer').innerHTML = `
          <div style="text-align: center; padding: 40px; color: #dc3545;">
            <h3>❌ Error Loading Excel Data</h3>
            <p>${error.message}</p>
          </div>
        `;
        }
      }

      function displayExcelData(data) {
        if (data.length === 0) {
          document.getElementById('excelContainer').innerHTML = `
          <div style="text-align: center; padding: 40px; color: #666;">
            <h3>📋 No Data Found</h3>
            <p>The Excel file appears to be empty</p>
          </div>
        `;
          return;
        }

        let tableHTML = '<div class="table-container"><table>';

        // Headers
        if (data.length > 0) {
          tableHTML += '<thead><tr>';
          const headers = data[0];
          headers.forEach(header => {
            tableHTML += `<th>${escapeHtml(header || '')}</th>`;
          });
          tableHTML += '</tr></thead>';
        }

        // Rows (limit to first 100 rows for performance)
        tableHTML += '<tbody>';
        const maxRows = Math.min(data.length, 101); // 1 header + 100 data rows
        for (let i = 1; i < maxRows; i++) {
          tableHTML += '<tr>';
          data[i].forEach(cell => {
            tableHTML += `<td>${escapeHtml(String(cell || ''))}</td>`;
          });
          tableHTML += '</tr>';
        }
        tableHTML += '</tbody></table></div>';

        if (data.length > 101) {
          tableHTML += `<div style="padding: 10px; text-align: center; color: #666; border-top: 1px solid #eee;">
          Showing first 100 rows of ${data.length - 1} total rows. Download the file to see all data.
        </div>`;
        }

        document.getElementById('excelContainer').innerHTML = tableHTML;
      }

      function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
      }

      // Search functionality
      const searchBox = document.getElementById('searchBox');
      if (searchBox) {
        searchBox.addEventListener('input', function(e) {
          const searchTerm = e.target.value.toLowerCase();
          const rows = document.querySelectorAll('#excelContainer tbody tr');

          rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
          });
        });
      }

      // Initialize Excel viewer when DOM is ready
      document.addEventListener('DOMContentLoaded', function() {
        initializeExcelViewer();
      });

      // Also try to initialize after a delay in case DOMContentLoaded already fired
      setTimeout(initializeExcelViewer, 500);
    <?php endif; ?>
  </script>
</body>

</html>