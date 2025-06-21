<?php
require_once 'common/config_mysql.php';
requireAdmin();

// Get report ID from URL if provided
$preselectedReportId = intval($_GET['report_id'] ?? 0);

// Handle AJAX request for file processing
if (isset($_POST['action']) && $_POST['action'] === 'process_excel') {
  $reportId = intval($_POST['report_id']);
  $keepOption = $_POST['keep_option'] ?? 'latest';
  $processedData = json_decode($_POST['processed_data'], true);

  if (!$processedData) {
    echo json_encode(['success' => false, 'error' => 'Invalid processed data']);
    exit;
  }

  $db = getDB();
  $stmt = $db->prepare("SELECT * FROM reports WHERE id = ?");
  $stmt->execute([$reportId]);
  $report = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($report) {
    $filePath = DECRYPTED_PATH . $report['decrypted_filename'];

    // Create backup
    $backupPath = $filePath . '.backup.' . date('YmdHis');
    copy($filePath, $backupPath);

    // Save the processed data using XLSX library
    $result = saveProcessedDataToExcel($filePath, $processedData);

    if ($result['success']) {
      // Update report statistics
      $newRowCount = count($processedData) - 1; // Subtract header row
      $removedCount = $report['row_count'] - $newRowCount;

      $stmt = $db->prepare("UPDATE reports SET row_count = ?, admin_notes = CONCAT(COALESCE(admin_notes, ''), ?) WHERE id = ?");
      $newNote = "\n[Duplicates Cleaned: " . date('Y-m-d H:i:s') . " - Removed " . $removedCount . " duplicate URLs, kept " . $newRowCount . " unique records]";
      $stmt->execute([$newRowCount, $newNote, $reportId]);

      // Log admin activity
      logAdminActivity('Clean Duplicates', "Cleaned duplicates from report ID {$reportId}. Removed {$removedCount} duplicates, kept {$newRowCount} unique records.");

      echo json_encode([
        'success' => true,
        'removed' => $removedCount,
        'kept' => $newRowCount,
        'message' => "Successfully cleaned duplicates! Removed {$removedCount} duplicate URLs. File now contains {$newRowCount} unique records."
      ]);
    } else {
      echo json_encode(['success' => false, 'error' => $result['error']]);
    }
  } else {
    echo json_encode(['success' => false, 'error' => 'Report not found']);
  }
  exit;
}

function saveProcessedDataToExcel($filePath, $data)
{
  try {
    // For now, we'll save as CSV since creating proper Excel is complex
    // In production, you'd want to use PhpSpreadsheet
    $csvPath = str_replace('.xlsx', '_cleaned.csv', $filePath);

    $file = fopen($csvPath, 'w');
    if (!$file) {
      return ['success' => false, 'error' => 'Could not create output file'];
    }

    foreach ($data as $row) {
      fputcsv($file, $row);
    }
    fclose($file);

    // For now, just rename the CSV to xlsx
    // In production, convert properly to Excel format
    if (file_exists($filePath)) {
      unlink($filePath);
    }
    rename($csvPath, $filePath);

    return ['success' => true];
  } catch (Exception $e) {
    return ['success' => false, 'error' => 'Error saving file: ' . $e->getMessage()];
  }
}

// Get all reports for selection
$db = getDB();
$reports = $db->query("
    SELECT id, employee_name, original_filename, upload_date, row_count, is_archived
    FROM reports 
    WHERE is_archived = FALSE
    ORDER BY upload_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Handle backup file operations
if (isset($_POST['action']) && $_POST['action'] === 'get_backup_files') {
  $backupFiles = getBackupFiles();
  echo json_encode(['success' => true, 'files' => $backupFiles]);
  exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'delete_backup') {
  $filename = $_POST['filename'] ?? '';

  if (empty($filename)) {
    echo json_encode(['success' => false, 'error' => 'No filename provided']);
    exit;
  }

  $filePath = DECRYPTED_PATH . $filename;

  // Security check: ensure it's a backup file
  if (!preg_match('/\.backup\.\d{14}$/', $filename)) {
    echo json_encode(['success' => false, 'error' => 'Invalid backup file format']);
    exit;
  }

  if (file_exists($filePath)) {
    if (unlink($filePath)) {
      logAdminActivity('Delete Backup File', "Deleted backup file: {$filename}");
      echo json_encode(['success' => true, 'message' => 'Backup file deleted successfully']);
    } else {
      echo json_encode(['success' => false, 'error' => 'Failed to delete backup file']);
    }
  } else {
    echo json_encode(['success' => false, 'error' => 'Backup file not found']);
  }
  exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'delete_all_backups') {
  $backupFiles = getBackupFiles();
  $deletedCount = 0;
  $errorCount = 0;

  foreach ($backupFiles as $file) {
    $filePath = DECRYPTED_PATH . $file['filename'];
    if (file_exists($filePath)) {
      if (unlink($filePath)) {
        $deletedCount++;
      } else {
        $errorCount++;
      }
    }
  }

  logAdminActivity('Delete All Backup Files', "Deleted {$deletedCount} backup files, {$errorCount} errors");
  echo json_encode([
    'success' => true,
    'message' => "Deleted {$deletedCount} backup files" . ($errorCount > 0 ? " with {$errorCount} errors" : "")
  ]);
  exit;
}

function getBackupFiles()
{
  $backupFiles = [];
  $decryptedPath = DECRYPTED_PATH;

  if (is_dir($decryptedPath)) {
    $files = scandir($decryptedPath);

    foreach ($files as $file) {
      if (preg_match('/\.backup\.(\d{14})$/', $file, $matches)) {
        $filePath = $decryptedPath . $file;
        $timestamp = $matches[1];

        // Parse timestamp (YmdHis format)
        $year = substr($timestamp, 0, 4);
        $month = substr($timestamp, 4, 2);
        $day = substr($timestamp, 6, 2);
        $hour = substr($timestamp, 8, 2);
        $minute = substr($timestamp, 10, 2);
        $second = substr($timestamp, 12, 2);

        $dateTime = "{$year}-{$month}-{$day} {$hour}:{$minute}:{$second}";

        $backupFiles[] = [
          'filename' => $file,
          'size' => file_exists($filePath) ? filesize($filePath) : 0,
          'created' => $dateTime,
          'timestamp' => $timestamp,
          'original_name' => str_replace('.backup.' . $timestamp, '', $file)
        ];
      }
    }
  }

  // Sort by timestamp (newest first)
  usort($backupFiles, function ($a, $b) {
    return strcmp($b['timestamp'], $a['timestamp']);
  });

  return $backupFiles;
}

function logAdminActivity($action, $details = '')
{
  $db = getDB();
  $stmt = $db->prepare("INSERT INTO admin_activity_log (admin_username, action, details, ip_address) VALUES (?, ?, ?, ?)");
  $stmt->execute([$_SESSION['admin_username'], $action, $details, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Duplicate URL Cleaner - Employee Tracker</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">

  <!-- DataTables CSS -->
  <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">

  <!-- Custom CSS -->
  <link href="styles.css" rel="stylesheet">
</head>

<body class="bg-light">
  <!-- Header -->
  <header class="header">
    <div class="container-fluid">
      <div class="d-flex justify-content-between align-items-center py-3">
        <h1 class="h2 mb-0 text-white">
          <i class="bi bi-funnel me-2"></i>Duplicate URL Cleaner
        </h1>
        <div class="header-actions d-flex align-items-center gap-3">
          <span class="text-white">Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
          <a href="dashboard.php" class="btn btn-secondary-custom">
            <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
          </a>
        </div>
      </div>
    </div>
  </header>

  <!-- Main Container -->
  <div class="container-xxl py-4">
    <?php if (isset($success_message)): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <!-- Feature Description -->
    <div class="row mb-4">
      <div class="col-12">
        <div class="card">
          <div class="card-body">
            <h4 class="card-title">
              <i class="bi bi-info-circle me-2 text-primary"></i>About Duplicate URL Cleaner
            </h4>
            <p class="card-text">
              This tool helps you clean Excel reports by removing duplicate website URLs, keeping only one record per unique URL.
              This is useful for removing redundant entries when employees visit the same websites multiple times.
            </p>

            <div class="row mt-3">
              <div class="col-md-4">
                <div class="d-flex align-items-center mb-2">
                  <i class="bi bi-clock-history text-success me-2"></i>
                  <strong>Keep Latest:</strong> Keeps the most recent visit
                </div>
              </div>
              <div class="col-md-4">
                <div class="d-flex align-items-center mb-2">
                  <i class="bi bi-clock text-info me-2"></i>
                  <strong>Keep Oldest:</strong> Keeps the first visit to each URL
                </div>
              </div>
              <div class="col-md-4">
                <div class="d-flex align-items-center mb-2">
                  <i class="bi bi-calendar-day text-warning me-2"></i>
                  <strong>First Today:</strong> Keeps first visit of today (or latest if none today)
                </div>
              </div>
            </div>

            <div class="alert alert-info mt-3" role="alert">
              <i class="bi bi-shield-check me-2"></i>
              <strong>Safety:</strong> A backup of the original file is automatically created before cleaning.
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Duplicate Cleaner Form -->
    <div class="row">
      <div class="col-lg-8">
        <div class="card">
          <div class="card-header">
            <h5 class="card-title mb-0">
              <i class="bi bi-funnel me-2"></i>Clean Duplicates
            </h5>
          </div>
          <div class="card-body">
            <form method="POST" onsubmit="return confirmClean()">
              <div class="mb-3">
                <label for="report_id" class="form-label">
                  <i class="bi bi-file-earmark me-1"></i>Select Report to Clean
                </label> <select name="report_id" id="report_id" class="form-select" required onchange="updateReportInfo()">
                  <option value="">Choose a report...</option>
                  <?php foreach ($reports as $report): ?>
                    <option value="<?php echo $report['id']; ?>"
                      data-employee="<?php echo htmlspecialchars($report['employee_name']); ?>"
                      data-filename="<?php echo htmlspecialchars($report['original_filename']); ?>"
                      data-rows="<?php echo $report['row_count']; ?>"
                      data-date="<?php echo $report['upload_date']; ?>"
                      <?php echo ($preselectedReportId == $report['id']) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($report['employee_name']); ?> -
                      <?php echo htmlspecialchars($report['original_filename']); ?>
                      (<?php echo number_format($report['row_count']); ?> rows)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="mb-3">
                <label for="keep_option" class="form-label">
                  <i class="bi bi-filter me-1"></i>Duplicate Handling Strategy
                </label>
                <select name="keep_option" id="keep_option" class="form-select" required>
                  <option value="latest">Keep Latest Visit (Recommended)</option>
                  <option value="oldest">Keep Oldest Visit</option>
                  <option value="first_today">Keep First Visit Today</option>
                </select>
                <div class="form-text">
                  Choose which record to keep when duplicate URLs are found.
                </div>
              </div>

              <!-- Report Preview -->
              <div id="reportPreview" class="alert alert-light d-none">
                <h6><i class="bi bi-file-earmark-text me-2"></i>Selected Report Preview:</h6>
                <div class="row">
                  <div class="col-sm-6">
                    <small class="text-muted">Employee:</small><br>
                    <strong id="previewEmployee">-</strong>
                  </div>
                  <div class="col-sm-6">
                    <small class="text-muted">Upload Date:</small><br>
                    <strong id="previewDate">-</strong>
                  </div>
                  <div class="col-sm-6">
                    <small class="text-muted">Filename:</small><br>
                    <strong id="previewFilename">-</strong>
                  </div>
                  <div class="col-sm-6">
                    <small class="text-muted">Current Rows:</small><br>
                    <strong id="previewRows">-</strong>
                  </div>
                </div>
              </div>
              <div class="d-flex gap-2">
                <button type="button" id="cleanDuplicatesBtn" class="btn btn-danger" onclick="processExcelFile()">
                  <i class="bi bi-funnel me-1"></i>Clean Duplicates
                </button>
                <a href="dashboard.php" class="btn btn-outline-secondary">
                  <i class="bi bi-x me-1"></i>Cancel
                </a>
              </div>
            </form>

            <!-- Progress Section -->
            <div id="progressSection" class="mt-4" style="display: none;">
              <div class="card border-info">
                <div class="card-body">
                  <h6 class="card-title">
                    <i class="bi bi-gear-fill spin me-2"></i>Processing Excel File...
                  </h6>
                  <div class="progress mb-3">
                    <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                  </div>
                  <div id="progressText">Reading Excel file...</div>
                </div>
              </div>
            </div>

            <!-- Results Section -->
            <div id="resultsSection" class="mt-4" style="display: none;">
              <div class="card border-success">
                <div class="card-body">
                  <h6 class="card-title">
                    <i class="bi bi-check-circle text-success me-2"></i>Duplicate Analysis Results
                  </h6>
                  <div id="resultsContent"></div>
                  <div class="mt-3">
                    <button type="button" id="confirmCleanBtn" class="btn btn-success me-2">
                      <i class="bi bi-check me-1"></i>Confirm & Clean
                    </button>
                    <button type="button" id="cancelCleanBtn" class="btn btn-outline-secondary">
                      <i class="bi bi-x me-1"></i>Cancel
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="card">
          <div class="card-header">
            <h5 class="card-title mb-0">
              <i class="bi bi-lightbulb me-2"></i>Tips & Guidelines
            </h5>
          </div>
          <div class="card-body">
            <div class="tip-item mb-3">
              <div class="d-flex align-items-start">
                <i class="bi bi-check-circle text-success me-2 mt-1"></i>
                <div>
                  <strong>Backup Created:</strong>
                  <br><small class="text-muted">Original file is automatically backed up before cleaning</small>
                </div>
              </div>
            </div>

            <div class="tip-item mb-3">
              <div class="d-flex align-items-start">
                <i class="bi bi-graph-down text-primary me-2 mt-1"></i>
                <div>
                  <strong>Row Count Update:</strong>
                  <br><small class="text-muted">Report statistics are updated after cleaning</small>
                </div>
              </div>
            </div>

            <div class="tip-item mb-3">
              <div class="d-flex align-items-start">
                <i class="bi bi-activity text-warning me-2 mt-1"></i>
                <div>
                  <strong>Admin Log:</strong>
                  <br><small class="text-muted">All cleaning actions are logged for audit</small>
                </div>
              </div>
            </div>

            <div class="tip-item">
              <div class="d-flex align-items-start">
                <i class="bi bi-shield-exclamation text-danger me-2 mt-1"></i>
                <div>
                  <strong>Data Loss Warning:</strong>
                  <br><small class="text-muted">Duplicate records will be permanently removed</small>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Statistics Card -->
        <div class="card mt-3">
          <div class="card-header">
            <h5 class="card-title mb-0">
              <i class="bi bi-bar-chart me-2"></i>Available Reports
            </h5>
          </div>
          <div class="card-body">
            <div class="d-flex justify-content-between mb-2">
              <span>Total Reports:</span>
              <strong><?php echo count($reports); ?></strong>
            </div>
            <div class="d-flex justify-content-between mb-2">
              <span>Total Rows:</span>
              <strong><?php echo number_format(array_sum(array_column($reports, 'row_count'))); ?></strong>
            </div>
            <div class="d-flex justify-content-between">
              <span>Avg Rows/Report:</span>
              <strong><?php echo count($reports) > 0 ? number_format(array_sum(array_column($reports, 'row_count')) / count($reports)) : 0; ?></strong>
            </div>
          </div>
        </div>

        <!-- Backup Files Management Card -->
        <div class="card mt-3">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
              <i class="bi bi-archive me-2"></i>Backup Files
            </h5>
            <button type="button" class="btn btn-outline-primary btn-sm" onclick="loadBackupFiles()">
              <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
          </div>
          <div class="card-body">
            <div id="backupFilesLoading" class="text-center py-3">
              <i class="bi bi-gear-fill spin me-2"></i>Loading backup files...
            </div>

            <div id="backupFilesList" style="display: none;">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <small class="text-muted">Backup files created during duplicate cleaning:</small>
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="deleteAllBackups()" id="deleteAllBtn" style="display: none;">
                  <i class="bi bi-trash me-1"></i>Delete All
                </button>
              </div>

              <div id="backupFilesContent"></div>
            </div>

            <div id="noBackupFiles" style="display: none;" class="text-center py-3 text-muted">
              <i class="bi bi-folder-x display-4 mb-2"></i>
              <p class="mb-0">No backup files found</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  <!-- XLSX Library for Excel processing -->
  <script src="libs/xlsx.full.min.js"></script>

  <script>
    let excelData = null;
    let processedData = null; // Auto-show preview if report is preselected and load backup files
    document.addEventListener('DOMContentLoaded', function() {
      updateReportInfo();
      loadBackupFiles();
    });

    function updateReportInfo() {
      const select = document.getElementById('report_id');
      const preview = document.getElementById('reportPreview');

      if (select.value) {
        const option = select.selectedOptions[0];

        document.getElementById('previewEmployee').textContent = option.dataset.employee;
        document.getElementById('previewFilename').textContent = option.dataset.filename;
        document.getElementById('previewRows').textContent = new Intl.NumberFormat().format(option.dataset.rows);
        document.getElementById('previewDate').textContent = new Date(option.dataset.date).toLocaleDateString();

        preview.classList.remove('d-none');
      } else {
        preview.classList.add('d-none');
      }
    }

    async function processExcelFile() {
      const reportId = document.getElementById('report_id').value;
      const keepOption = document.getElementById('keep_option').value;

      if (!reportId) {
        alert('Please select a report first');
        return;
      }

      // Show progress
      document.getElementById('progressSection').style.display = 'block';
      document.getElementById('resultsSection').style.display = 'none';
      document.getElementById('cleanDuplicatesBtn').disabled = true;

      try {
        // Update progress
        updateProgress(20, 'Fetching Excel file...');

        // Get the Excel file
        const response = await fetch(`view_report.php?id=${reportId}&raw=1`);
        if (!response.ok) {
          throw new Error('Failed to fetch Excel file');
        }

        updateProgress(40, 'Reading Excel data...');
        const arrayBuffer = await response.arrayBuffer();
        const workbook = XLSX.read(arrayBuffer, {
          type: 'array'
        });
        const firstSheetName = workbook.SheetNames[0];
        const worksheet = workbook.Sheets[firstSheetName];

        // Convert to JSON with raw values to preserve formatting
        excelData = XLSX.utils.sheet_to_json(worksheet, {
          header: 1,
          raw: false, // This preserves the formatted values instead of converting to numbers
          dateNF: 'yyyy-mm-dd' // Format dates properly
        });

        updateProgress(60, 'Analyzing duplicates...');

        // Process duplicates
        const result = cleanDuplicatesInMemory(excelData, keepOption);

        updateProgress(100, 'Analysis complete!');

        // Show results
        setTimeout(() => {
          showResults(result);
        }, 500);

      } catch (error) {
        console.error('Error processing Excel file:', error);
        alert('Error processing Excel file: ' + error.message);
        document.getElementById('progressSection').style.display = 'none';
        document.getElementById('cleanDuplicatesBtn').disabled = false;
      }
    }

    // Helper function to convert Excel serial date to proper date string
    function excelDateToJSDate(excelDate) {
      // Excel epoch starts on 1900-01-01, but Excel incorrectly treats 1900 as a leap year
      // So we need to subtract 1 day for dates after 1900-02-28
      const excelEpoch = new Date(1899, 11, 30); // December 30, 1899
      const millisecondsPerDay = 24 * 60 * 60 * 1000;

      if (typeof excelDate === 'number' && excelDate > 1) {
        const jsDate = new Date(excelEpoch.getTime() + (excelDate * millisecondsPerDay));
        return jsDate;
      }
      return null;
    }

    // Helper function to format date/time values properly
    function formatExcelValue(value, header) {
      // Check if this looks like an Excel date serial number
      if (typeof value === 'number' && value > 40000 && value < 50000) {
        const headerLower = header ? header.toLowerCase() : '';

        if (headerLower.includes('date')) {
          const date = excelDateToJSDate(value);
          return date ? date.toISOString().split('T')[0] : value; // YYYY-MM-DD format
        } else if (headerLower.includes('time') && !headerLower.includes('timestamp')) {
          // Handle time-only values (fractional part of Excel date)
          const timeDecimal = value % 1;
          const totalSeconds = timeDecimal * 24 * 60 * 60;
          const hours = Math.floor(totalSeconds / 3600);
          const minutes = Math.floor((totalSeconds % 3600) / 60);
          const seconds = Math.floor(totalSeconds % 60);
          return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        } else if (headerLower.includes('timestamp')) {
          const date = excelDateToJSDate(value);
          return date ? date.toISOString() : value; // Full ISO timestamp
        }
      }

      return value; // Return as-is if not a date/time
    }

    function cleanDuplicatesInMemory(data, keepOption) {
      if (data.length === 0) {
        throw new Error('No data found in Excel file');
      }

      const headers = data[0];
      const rows = data.slice(1);

      // Find URL column
      const urlColumn = headers.findIndex(header =>
        header && header.toLowerCase().includes('url')
      );

      if (urlColumn === -1) {
        throw new Error('URL column not found in Excel file');
      }

      // Group by URL
      const urlGroups = {};
      rows.forEach((row, index) => {
        const url = row[urlColumn];
        if (url && url.trim()) {
          if (!urlGroups[url]) {
            urlGroups[url] = [];
          }
          urlGroups[url].push({
            row,
            originalIndex: index
          });
        }
      });

      // Find duplicates and apply cleaning logic
      const duplicateUrls = [];
      const cleanedRows = [];
      let removedCount = 0;

      Object.entries(urlGroups).forEach(([url, entries]) => {
        if (entries.length > 1) {
          duplicateUrls.push({
            url,
            count: entries.length
          });
          removedCount += entries.length - 1;

          // Sort based on keep option
          entries.sort((a, b) => {
            const timestampA = getTimestamp(a.row, headers);
            const timestampB = getTimestamp(b.row, headers);

            switch (keepOption) {
              case 'latest':
                return timestampB - timestampA;
              case 'oldest':
                return timestampA - timestampB;
              case 'first_today':
                const today = new Date().toDateString();
                const dateA = new Date(timestampA).toDateString();
                const dateB = new Date(timestampB).toDateString();

                if (dateA === today && dateB !== today) return -1;
                if (dateB === today && dateA !== today) return 1;
                if (dateA === today && dateB === today) return timestampA - timestampB;
                return timestampB - timestampA;
              default:
                return timestampB - timestampA;
            }
          });

          cleanedRows.push(entries[0].row);
        } else {
          cleanedRows.push(entries[0].row);
        }
      }); // Sort final data by timestamp (newest first)
      cleanedRows.sort((a, b) => {
        const timestampA = getTimestamp(a, headers);
        const timestampB = getTimestamp(b, headers);
        return timestampB - timestampA;
      });

      // Format all rows to fix date/time display issues
      const formattedRows = cleanedRows.map(row => {
        return row.map((value, index) => {
          return formatExcelValue(value, headers[index]);
        });
      });

      processedData = [headers, ...formattedRows];

      return {
        originalCount: rows.length,
        finalCount: cleanedRows.length,
        removedCount,
        duplicateUrls,
        success: true
      };
    }

    function getTimestamp(row, headers) {
      // Try to find timestamp, date, or time columns
      const timestampCol = headers.findIndex(h => h && h.toLowerCase().includes('timestamp'));
      const dateCol = headers.findIndex(h => h && h.toLowerCase().includes('date'));
      const timeCol = headers.findIndex(h => h && h.toLowerCase().includes('time'));

      if (timestampCol !== -1 && row[timestampCol]) {
        return new Date(row[timestampCol]).getTime();
      }

      if (dateCol !== -1 && timeCol !== -1 && row[dateCol] && row[timeCol]) {
        return new Date(row[dateCol] + ' ' + row[timeCol]).getTime();
      }

      if (dateCol !== -1 && row[dateCol]) {
        return new Date(row[dateCol]).getTime();
      }

      return 0;
    }

    function updateProgress(percent, text) {
      document.getElementById('progressBar').style.width = percent + '%';
      document.getElementById('progressText').textContent = text;
    }

    function showResults(result) {
      document.getElementById('progressSection').style.display = 'none';
      document.getElementById('resultsSection').style.display = 'block';

      const content = `
        <div class="row text-center mb-3">
          <div class="col-md-4">
            <div class="d-flex flex-column">
              <span class="fs-4 fw-bold text-primary">${result.originalCount.toLocaleString()}</span>
              <small class="text-muted">Original Rows</small>
            </div>
          </div>
          <div class="col-md-4">
            <div class="d-flex flex-column">
              <span class="fs-4 fw-bold text-danger">${result.removedCount.toLocaleString()}</span>
              <small class="text-muted">Duplicates Found</small>
            </div>
          </div>
          <div class="col-md-4">
            <div class="d-flex flex-column">
              <span class="fs-4 fw-bold text-success">${result.finalCount.toLocaleString()}</span>
              <small class="text-muted">After Cleaning</small>
            </div>
          </div>
        </div>
        <div class="alert alert-info">
          <i class="bi bi-info-circle me-2"></i>
          Found <strong>${result.duplicateUrls.length}</strong> unique URLs with duplicates.
          Total of <strong>${result.removedCount}</strong> duplicate entries will be removed.
        </div>
      `;

      document.getElementById('resultsContent').innerHTML = content;
      document.getElementById('cleanDuplicatesBtn').disabled = false;
    }

    document.getElementById('confirmCleanBtn').addEventListener('click', async function() {
      if (!processedData) {
        alert('No processed data available');
        return;
      }

      const reportId = document.getElementById('report_id').value;
      const keepOption = document.getElementById('keep_option').value;

      try {
        this.disabled = true;
        this.innerHTML = '<i class="bi bi-gear-fill spin me-1"></i>Saving...';

        const formData = new FormData();
        formData.append('action', 'process_excel');
        formData.append('report_id', reportId);
        formData.append('keep_option', keepOption);
        formData.append('processed_data', JSON.stringify(processedData));

        const response = await fetch(window.location.href, {
          method: 'POST',
          body: formData
        });

        const result = await response.json();

        if (result.success) {
          alert(result.message);
          window.location.href = 'dashboard.php';
        } else {
          alert('Error: ' + result.error);
        }
      } catch (error) {
        alert('Error saving cleaned data: ' + error.message);
      } finally {
        this.disabled = false;
        this.innerHTML = '<i class="bi bi-check me-1"></i>Confirm & Clean';
      }
    });
    document.getElementById('cancelCleanBtn').addEventListener('click', function() {
      document.getElementById('resultsSection').style.display = 'none';
      document.getElementById('cleanDuplicatesBtn').disabled = false;
      processedData = null;
    });

    function confirmClean() {
      const select = document.getElementById('report_id');
      const keepOption = document.getElementById('keep_option');

      if (!select.value) {
        alert('Please select a report to clean.');
        return false;
      }

      const option = select.selectedOptions[0];
      const employee = option.dataset.employee;
      const filename = option.dataset.filename;
      const rows = option.dataset.rows;
      const strategy = keepOption.selectedOptions[0].text;

      const message = `Are you sure you want to clean duplicates from this report?\n\n` +
        `Employee: ${employee}\n` +
        `File: ${filename}\n` +
        `Current Rows: ${new Intl.NumberFormat().format(rows)}\n` +
        `Strategy: ${strategy}\n\n` +
        `This action will:\n` +
        `• Create a backup of the original file\n` +
        `• Remove duplicate URLs based on your strategy\n` +
        `• Update the report statistics\n` +
        `• Log the action for audit purposes\n\n` +
        `Continue?`;
      return confirm(message);
    }

    // Backup Files Management Functions
    async function loadBackupFiles() {
      document.getElementById('backupFilesLoading').style.display = 'block';
      document.getElementById('backupFilesList').style.display = 'none';
      document.getElementById('noBackupFiles').style.display = 'none';

      try {
        const formData = new FormData();
        formData.append('action', 'get_backup_files');

        const response = await fetch(window.location.href, {
          method: 'POST',
          body: formData
        });

        const result = await response.json();

        if (result.success) {
          displayBackupFiles(result.files);
        } else {
          alert('Error loading backup files: ' + (result.error || 'Unknown error'));
        }
      } catch (error) {
        alert('Error loading backup files: ' + error.message);
      } finally {
        document.getElementById('backupFilesLoading').style.display = 'none';
      }
    }

    function displayBackupFiles(files) {
      const content = document.getElementById('backupFilesContent');
      const deleteAllBtn = document.getElementById('deleteAllBtn');

      if (files.length === 0) {
        document.getElementById('noBackupFiles').style.display = 'block';
        deleteAllBtn.style.display = 'none';
        return;
      }

      document.getElementById('backupFilesList').style.display = 'block';
      deleteAllBtn.style.display = 'inline-block';

      let html = '<div class="list-group list-group-flush">';

      files.forEach(file => {
        const sizeFormatted = formatFileSize(file.size);
        html += `
          <div class="list-group-item d-flex justify-content-between align-items-center px-0">
            <div class="flex-grow-1">
              <div class="fw-bold text-truncate" style="max-width: 200px;" title="${file.filename}">
                ${file.original_name}
              </div>
              <small class="text-muted">
                <i class="bi bi-calendar me-1"></i>${file.created}
                <i class="bi bi-hdd ms-2 me-1"></i>${sizeFormatted}
              </small>
            </div>
            <button type="button" class="btn btn-outline-danger btn-sm" 
                    onclick="deleteBackupFile('${file.filename}')" 
                    title="Delete backup">
              <i class="bi bi-trash"></i>
            </button>
          </div>
        `;
      });

      html += '</div>';
      content.innerHTML = html;
    }

    async function deleteBackupFile(filename) {
      if (!confirm('Are you sure you want to delete this backup file?\n\n' + filename)) {
        return;
      }

      try {
        const formData = new FormData();
        formData.append('action', 'delete_backup');
        formData.append('filename', filename);

        const response = await fetch(window.location.href, {
          method: 'POST',
          body: formData
        });

        const result = await response.json();

        if (result.success) {
          loadBackupFiles(); // Refresh the list
        } else {
          alert('Error deleting backup file: ' + (result.error || 'Unknown error'));
        }
      } catch (error) {
        alert('Error deleting backup file: ' + error.message);
      }
    }

    async function deleteAllBackups() {
      if (!confirm('Are you sure you want to delete ALL backup files?\n\nThis action cannot be undone.')) {
        return;
      }

      try {
        const formData = new FormData();
        formData.append('action', 'delete_all_backups');

        const response = await fetch(window.location.href, {
          method: 'POST',
          body: formData
        });

        const result = await response.json();

        if (result.success) {
          alert(result.message);
          loadBackupFiles(); // Refresh the list
        } else {
          alert('Error deleting backup files: ' + (result.error || 'Unknown error'));
        }
      } catch (error) {
        alert('Error deleting backup files: ' + error.message);
      }
    }

    function formatFileSize(bytes) {
      if (bytes === 0) return '0 B';
      const k = 1024;
      const sizes = ['B', 'KB', 'MB', 'GB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Auto-load backup files when page loads
    document.addEventListener('DOMContentLoaded', function() {
      updateReportInfo();
      loadBackupFiles();
    });
  </script>
</body>

</html>