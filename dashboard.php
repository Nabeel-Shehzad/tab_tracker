<?php
require_once 'common/config_mysql.php';
requireAdmin();

// Log admin activity
function logAdminActivity($action, $details = '')
{
  $db = getDB();
  $stmt = $db->prepare("INSERT INTO admin_activity_log (admin_username, action, details, ip_address) VALUES (?, ?, ?, ?)");
  $stmt->execute([$_SESSION['admin_username'], $action, $details, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
}

// Handle bulk delete action
if (isset($_POST['bulk_delete']) && isset($_POST['selected_reports'])) {
  $selectedIds = array_map('intval', $_POST['selected_reports']);
  $db = getDB();

  foreach ($selectedIds as $reportId) {
    // Get file info before deleting
    $stmt = $db->prepare("SELECT decrypted_filename, employee_name FROM reports WHERE id = ?");
    $stmt->execute([$reportId]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($report) {
      // Delete file
      $filePath = DECRYPTED_PATH . $report['decrypted_filename'];
      if (file_exists($filePath)) {
        unlink($filePath);
      }

      // Delete database record
      $stmt = $db->prepare("DELETE FROM reports WHERE id = ?");
      $stmt->execute([$reportId]);

      logAdminActivity('Bulk Delete Report', "Deleted report ID {$reportId} for {$report['employee_name']}");
    }
  }

  $success_message = "Successfully deleted " . count($selectedIds) . " reports";
}

// Handle bulk archive action
if (isset($_POST['bulk_archive']) && isset($_POST['selected_reports'])) {
  $selectedIds = array_map('intval', $_POST['selected_reports']);
  $db = getDB();

  $stmt = $db->prepare("UPDATE reports SET is_archived = TRUE, archived_date = NOW() WHERE id = ?");
  foreach ($selectedIds as $reportId) {
    $stmt->execute([$reportId]);
  }

  logAdminActivity('Bulk Archive Reports', "Archived " . count($selectedIds) . " reports");
  $success_message = "Successfully archived " . count($selectedIds) . " reports";
}

// Handle bulk unarchive action
if (isset($_POST['bulk_unarchive']) && isset($_POST['selected_reports'])) {
  $selectedIds = array_map('intval', $_POST['selected_reports']);
  $db = getDB();

  $stmt = $db->prepare("UPDATE reports SET is_archived = FALSE, archived_date = NULL WHERE id = ?");
  foreach ($selectedIds as $reportId) {
    $stmt->execute([$reportId]);
  }

  logAdminActivity('Bulk Unarchive Reports', "Unarchived " . count($selectedIds) . " reports");
  $success_message = "Successfully unarchived " . count($selectedIds) . " reports";
}

// Handle individual delete action
if (isset($_POST['delete_report'])) {
  $reportId = intval($_POST['report_id']);
  $db = getDB();

  // Get file info before deleting
  $stmt = $db->prepare("SELECT decrypted_filename, employee_name FROM reports WHERE id = ?");
  $stmt->execute([$reportId]);
  $report = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($report) {
    // Delete file
    $filePath = DECRYPTED_PATH . $report['decrypted_filename'];
    if (file_exists($filePath)) {
      unlink($filePath);
    }

    // Delete database record
    $stmt = $db->prepare("DELETE FROM reports WHERE id = ?");
    $stmt->execute([$reportId]);

    logAdminActivity('Delete Report', "Deleted report for {$report['employee_name']}");
    $success_message = "Report deleted successfully";
  }
}

// Handle archive action
if (isset($_POST['archive_report'])) {
  $reportId = intval($_POST['report_id']);
  $db = getDB();

  $stmt = $db->prepare("UPDATE reports SET is_archived = TRUE, archived_date = NOW() WHERE id = ?");
  $stmt->execute([$reportId]);

  logAdminActivity('Archive Report', "Archived report ID {$reportId}");
  $success_message = "Report archived successfully";
}

// Handle unarchive action
if (isset($_POST['unarchive_report'])) {
  $reportId = intval($_POST['report_id']);
  $db = getDB();

  $stmt = $db->prepare("UPDATE reports SET is_archived = FALSE, archived_date = NULL WHERE id = ?");
  $stmt->execute([$reportId]);

  logAdminActivity('Unarchive Report', "Unarchived report ID {$reportId}");
  $success_message = "Report unarchived successfully";
}

// Handle update category/notes
if (isset($_POST['update_report'])) {
  $reportId = intval($_POST['report_id']);
  $category = $_POST['category'] ?? 'General';
  $notes = $_POST['admin_notes'] ?? '';

  $db = getDB();
  $stmt = $db->prepare("UPDATE reports SET category = ?, admin_notes = ? WHERE id = ?");
  $stmt->execute([$category, $notes, $reportId]);

  logAdminActivity('Update Report', "Updated category/notes for report ID {$reportId}");
  $success_message = "Report updated successfully";
}

// Handle logout
if (isset($_POST['logout'])) {
  logAdminActivity('Logout', 'Admin logged out');
  session_destroy();
  header('Location: login.php');
  exit;
}

// Get filter parameters
$dateRange = $_GET['date_range'] ?? 'all';
$employeeFilter = $_GET['employee'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$sizeFilter = $_GET['size_range'] ?? '';
$searchQuery = $_GET['search'] ?? '';
$showArchived = $_GET['show_archived'] ?? '0';

// Build WHERE clause based on filters
$whereConditions = [];
$params = [];

if ($showArchived === '1') {
  $whereConditions[] = "is_archived = TRUE";
} else {
  $whereConditions[] = "is_archived = FALSE";
}

// Date range filter
if ($dateRange !== 'all') {
  switch ($dateRange) {
    case '7days':
      $whereConditions[] = "upload_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
      break;
    case '30days':
      $whereConditions[] = "upload_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
      break;
    case '90days':
      $whereConditions[] = "upload_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
      break;
    case 'custom':
      if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
        $whereConditions[] = "upload_date BETWEEN ? AND ?";
        $params[] = $_GET['start_date'];
        $params[] = $_GET['end_date'] . ' 23:59:59';
      }
      break;
  }
}

// Employee filter
if (!empty($employeeFilter)) {
  $whereConditions[] = "employee_name = ?";
  $params[] = $employeeFilter;
}

// Category filter
if (!empty($categoryFilter)) {
  $whereConditions[] = "category = ?";
  $params[] = $categoryFilter;
}

// File size filter
if (!empty($sizeFilter)) {
  switch ($sizeFilter) {
    case 'small':
      $whereConditions[] = "file_size < 1048576"; // < 1MB
      break;
    case 'medium':
      $whereConditions[] = "file_size BETWEEN 1048576 AND 10485760"; // 1MB - 10MB
      break;
    case 'large':
      $whereConditions[] = "file_size > 10485760"; // > 10MB
      break;
  }
}

// Search filter
if (!empty($searchQuery)) {
  $whereConditions[] = "(employee_name LIKE ? OR original_filename LIKE ? OR ip_address LIKE ?)";
  $searchParam = '%' . $searchQuery . '%';
  $params[] = $searchParam;
  $params[] = $searchParam;
  $params[] = $searchParam;
}

$whereClause = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);

// Get filtered reports
$db = getDB();
$stmt = $db->prepare("
    SELECT id, employee_name, original_filename, decrypted_filename, 
           upload_date, file_size, row_count, ip_address, category, admin_notes, is_archived
    FROM reports 
    {$whereClause}
    ORDER BY upload_date DESC
");
$stmt->execute($params);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics (active reports only)
$stats = $db->query("
    SELECT 
        COUNT(*) as total_reports,
        COUNT(DISTINCT employee_name) as total_employees,
        SUM(file_size) as total_size,
        SUM(row_count) as total_rows
    FROM reports
    WHERE is_archived = FALSE
")->fetch(PDO::FETCH_ASSOC);

// Get archived count
$archivedCount = $db->query("SELECT COUNT(*) as count FROM reports WHERE is_archived = TRUE")->fetch(PDO::FETCH_ASSOC)['count'];

// Get employees for filter dropdown
$employees = $db->query("SELECT DISTINCT employee_name FROM reports WHERE is_archived = FALSE ORDER BY employee_name")->fetchAll(PDO::FETCH_COLUMN);

// Get categories for filter dropdown
$categories = $db->query("SELECT DISTINCT category FROM reports WHERE category IS NOT NULL ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// Analytics data
// Activity timeline (last 30 days)
$activityData = $db->query("
    SELECT DATE(upload_date) as date, COUNT(*) as count
    FROM reports 
    WHERE upload_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND is_archived = FALSE
    GROUP BY DATE(upload_date)
    ORDER BY date DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Top employees
$topEmployees = $db->query("
    SELECT employee_name, COUNT(*) as upload_count, SUM(file_size) as total_size
    FROM reports 
    WHERE is_archived = FALSE
    GROUP BY employee_name 
    ORDER BY upload_count DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Peak hours
$peakHours = $db->query("
    SELECT HOUR(upload_date) as hour, COUNT(*) as count
    FROM reports 
    WHERE upload_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND is_archived = FALSE
    GROUP BY HOUR(upload_date)
    ORDER BY count DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard - Employee Tracker</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">

  <!-- DataTables CSS -->
  <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.bootstrap5.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/select/1.6.2/css/select.bootstrap5.min.css" rel="stylesheet">

  <!-- Custom CSS -->
  <link href="styles.css" rel="stylesheet">
</head>

<body class="bg-light"> <!-- Header -->
  <header class="header">
    <div class="container-fluid">
      <div class="d-flex justify-content-between align-items-center py-3">
        <h1 class="h2 mb-0 text-white">
          <i class="bi bi-graph-up me-2"></i>Employee Tracker Dashboard
        </h1>
        <div class="header-actions d-flex align-items-center gap-3">
          <span class="text-white">Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
          <form method="POST" class="d-inline">
            <button type="submit" name="logout" class="btn btn-secondary-custom">
              <i class="bi bi-box-arrow-right me-1"></i>Logout
            </button>
          </form>
        </div>
      </div>
    </div>
  </header> <!-- Main Container -->
  <div class="container-xxl py-4">
    <?php if (isset($success_message)): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?> <!-- Filters Section -->
    <div class="card filters-section mb-4">
      <div class="card-body">
        <h5 class="card-title mb-3">
          <i class="bi bi-funnel me-2"></i>Filters & Search
        </h5>
        <form method="GET" id="filtersForm">
          <div class="row g-3 mb-3">
            <div class="col-md-2">
              <label for="date_range" class="form-label">
                <i class="bi bi-calendar3 me-1"></i>Date Range
              </label>
              <select name="date_range" id="date_range" class="form-select" onchange="toggleCustomDate()">
                <option value="all" <?php echo $dateRange === 'all' ? 'selected' : ''; ?>>All Time</option>
                <option value="7days" <?php echo $dateRange === '7days' ? 'selected' : ''; ?>>Last 7 Days</option>
                <option value="30days" <?php echo $dateRange === '30days' ? 'selected' : ''; ?>>Last 30 Days</option>
                <option value="90days" <?php echo $dateRange === '90days' ? 'selected' : ''; ?>>Last 90 Days</option>
                <option value="custom" <?php echo $dateRange === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
              </select>
            </div>

            <div class="col-md-3 <?php echo $dateRange === 'custom' ? '' : 'd-none'; ?>" id="customDateRange">
              <label class="form-label">
                <i class="bi bi-calendar-range me-1"></i>Custom Range
              </label>
              <div class="d-flex gap-2">
                <input type="date" name="start_date" value="<?php echo $_GET['start_date'] ?? ''; ?>" class="form-control">
                <input type="date" name="end_date" value="<?php echo $_GET['end_date'] ?? ''; ?>" class="form-control">
              </div>
            </div>

            <div class="col-md-2">
              <label for="employee" class="form-label">
                <i class="bi bi-person me-1"></i>Employee
              </label>
              <select name="employee" id="employee" class="form-select">
                <option value="">All Employees</option>
                <?php foreach ($employees as $emp): ?>
                  <option value="<?php echo htmlspecialchars($emp); ?>" <?php echo $employeeFilter === $emp ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($emp); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-2">
              <label for="category" class="form-label">
                <i class="bi bi-tag me-1"></i>Category
              </label>
              <select name="category" id="category" class="form-select">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $categoryFilter === $cat ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-2">
              <label for="size_range" class="form-label">
                <i class="bi bi-hdd me-1"></i>File Size
              </label>
              <select name="size_range" id="size_range" class="form-select">
                <option value="">All Sizes</option>
                <option value="small" <?php echo $sizeFilter === 'small' ? 'selected' : ''; ?>>Small (&lt; 1MB)</option>
                <option value="medium" <?php echo $sizeFilter === 'medium' ? 'selected' : ''; ?>>Medium (1-10MB)</option>
                <option value="large" <?php echo $sizeFilter === 'large' ? 'selected' : ''; ?>>Large (&gt; 10MB)</option>
              </select>
            </div>

            <div class="col-md-4">
              <label for="search" class="form-label">
                <i class="bi bi-search me-1"></i>Global Search
              </label>
              <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($searchQuery); ?>"
                class="form-control" placeholder="Search employees, filenames, IP addresses...">
            </div>
          </div>

          <div class="d-flex flex-wrap gap-2 align-items-center">
            <button type="submit" class="btn btn-gradient">
              <i class="bi bi-funnel me-1"></i>Apply Filters
            </button>
            <a href="dashboard.php" class="btn btn-outline-secondary">
              <i class="bi bi-x-circle me-1"></i>Clear All
            </a>
            <div class="form-check ms-3">
              <input type="checkbox" name="show_archived" value="1" <?php echo $showArchived === '1' ? 'checked' : ''; ?>
                onchange="this.form.submit();" class="form-check-input" id="showArchived">
              <label class="form-check-label" for="showArchived">
                Show Archived (<?php echo $archivedCount; ?>)
              </label>
            </div>
          </div>
        </form>
      </div>
    </div> <!-- Analytics Section -->
    <div class="row mb-4">
      <div class="col-lg-8">
        <div class="card chart-container">
          <div class="card-body">
            <h5 class="card-title">
              <i class="bi bi-graph-up me-2"></i>Activity Timeline (Last 30 Days)
            </h5>
            <canvas id="activityChart" width="400" height="200"></canvas>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="card insights-panel h-100">
          <div class="card-body">
            <h5 class="card-title">
              <i class="bi bi-lightbulb me-2"></i>Insights
            </h5>

            <div class="insight-item">
              <div class="insight-title">
                <i class="bi bi-trophy me-1"></i>Top Employee
              </div>
              <div class="insight-value">
                <?php echo !empty($topEmployees) ? htmlspecialchars($topEmployees[0]['employee_name']) . ' (' . $topEmployees[0]['upload_count'] . ' uploads)' : 'No data'; ?>
              </div>
            </div>

            <div class="insight-item">
              <div class="insight-title">
                <i class="bi bi-clock me-1"></i>Peak Hour
              </div>
              <div class="insight-value">
                <?php
                if (!empty($peakHours)) {
                  $hour = $peakHours[0]['hour'];
                  echo sprintf('%02d:00 - %02d:00', $hour, $hour + 1) . ' (' . $peakHours[0]['count'] . ' uploads)';
                } else {
                  echo 'No data';
                }
                ?>
              </div>
            </div>

            <div class="insight-item">
              <div class="insight-title">
                <i class="bi bi-bar-chart me-1"></i>Avg File Size
              </div>
              <div class="insight-value">
                <?php echo $stats['total_reports'] > 0 ? formatFileSize($stats['total_size'] / $stats['total_reports']) : '0 B'; ?>
              </div>
            </div>

            <div class="insight-item">
              <div class="insight-title">
                <i class="bi bi-archive me-1"></i>Archived Reports
              </div>
              <div class="insight-value"><?php echo number_format($archivedCount); ?></div>
            </div>
          </div>
        </div>
      </div>
    </div> <!-- Stats Grid -->
    <div class="row mb-4">
      <div class="col-md-3">
        <div class="card stat-card text-center">
          <div class="card-body">
            <div class="stat-number"><?php echo number_format($stats['total_reports']); ?></div>
            <div class="stat-label">Active Reports</div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card stat-card text-center">
          <div class="card-body">
            <div class="stat-number"><?php echo number_format($stats['total_employees']); ?></div>
            <div class="stat-label">Active Employees</div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card stat-card text-center">
          <div class="card-body">
            <div class="stat-number"><?php echo formatFileSize($stats['total_size']); ?></div>
            <div class="stat-label">Total Data</div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card stat-card text-center">
          <div class="card-body">
            <div class="stat-number"><?php echo number_format($stats['total_rows']); ?></div>
            <div class="stat-label">Website Visits</div>
          </div>
        </div>
      </div>
    </div> <!-- Reports Section -->
    <div class="card reports-section">
      <div class="card-header section-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
          <i class="bi bi-folder me-2"></i>Employee Reports (<?php echo count($reports); ?> found)
        </h5>
        <div class="d-flex gap-2">
          <a href="duplicate_cleaner.php" class="btn btn-warning btn-sm">
            <i class="bi bi-funnel me-1"></i>Clean Duplicates
          </a>
          <a href="decrypt.php" class="btn btn-gradient btn-sm">
            <i class="bi bi-unlock me-1"></i>Decrypt File
          </a>
          <a href="../employee/upload.php" class="btn btn-gradient btn-sm" target="_blank">
            <i class="bi bi-plus-circle me-1"></i>Upload Page
          </a>
        </div>
      </div> <!-- Bulk Actions Bar -->
      <div class="bulk-actions p-3 bg-light" id="bulkActions">
        <form method="POST" class="d-flex flex-wrap gap-2 align-items-center">
          <span id="selectedCount" class="fw-bold text-primary">0 selected</span>
          <button type="submit" name="bulk_delete" class="btn btn-danger btn-sm"
            onclick="return confirm('Are you sure you want to delete selected reports?')">
            <i class="bi bi-trash me-1"></i>Delete Selected
          </button>
          <?php if ($showArchived === '1'): ?>
            <button type="submit" name="bulk_unarchive" class="btn btn-primary btn-sm">
              <i class="bi bi-box-arrow-up me-1"></i>Unarchive Selected
            </button>
          <?php else: ?>
            <button type="submit" name="bulk_archive" class="btn btn-secondary btn-sm">
              <i class="bi bi-archive me-1"></i>Archive Selected
            </button>
          <?php endif; ?>
          <button type="button" onclick="selectAll()" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-check-all me-1"></i>Select All
          </button>
          <button type="button" onclick="clearSelection()" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-x me-1"></i>Clear
          </button>
          <input type="hidden" name="selected_reports" id="selectedReports">
        </form>
      </div>
      <div class="table-responsive">
        <?php if (empty($reports)): ?>
          <div class="empty-state text-center py-5">
            <i class="bi bi-folder-x display-1 text-muted mb-3"></i>
            <h3 class="text-muted">No Reports Found</h3>
            <p class="text-muted">No reports match your current filters.</p>
            <p class="text-muted">Try adjusting your search criteria or clearing filters.</p>
          </div>
        <?php else: ?> <table id="reportsTable" class="table table-hover align-middle" style="width:100%">
            <thead class="table-light">
              <tr>
                <th class="checkbox-column" data-orderable="false">
                  <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll()" class="form-check-input">
                </th>
                <th><i class="bi bi-person me-1"></i>Employee</th>
                <th><i class="bi bi-file-earmark me-1"></i>Original File</th>
                <th><i class="bi bi-tag me-1"></i>Category</th>
                <th><i class="bi bi-calendar3 me-1"></i>Upload Date</th>
                <th><i class="bi bi-bar-chart me-1"></i>Rows</th>
                <th><i class="bi bi-hdd me-1"></i>Size</th>
                <th><i class="bi bi-globe me-1"></i>IP</th>
                <th><i class="bi bi-chat-text me-1"></i>Notes</th>
                <th class="actions-column" data-orderable="false"><i class="bi bi-gear me-1"></i>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($reports as $report): ?>
                <tr class="<?php echo $report['is_archived'] ? 'table-warning' : ''; ?>">
                  <td class="checkbox-column">
                    <input type="checkbox" class="report-checkbox form-check-input" value="<?php echo $report['id']; ?>" onchange="updateBulkActions()">
                  </td>
                  <td class="employee-name">
                    <?php echo htmlspecialchars($report['employee_name']); ?>
                    <?php if ($report['is_archived']): ?>
                      <span class="badge category-badge archived-badge ms-1">Archived</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="text-truncate d-inline-block" style="max-width: 200px;" title="<?php echo htmlspecialchars($report['decrypted_filename']); ?>">
                      <?php echo htmlspecialchars($report['original_filename']); ?>
                    </span>
                  </td>
                  <td>
                    <span class="badge category-badge">
                      <?php echo htmlspecialchars($report['category'] ?: 'General'); ?>
                    </span>
                  </td>
                  <td class="upload-date">
                    <small><?php echo date('M j, Y g:i A', strtotime($report['upload_date'])); ?></small>
                  </td>
                  <td>
                    <span class="badge bg-light text-dark"><?php echo number_format($report['row_count']); ?></span>
                  </td>
                  <td class="file-size">
                    <small class="text-muted"><?php echo formatFileSize($report['file_size']); ?></small>
                  </td>
                  <td>
                    <small class="font-monospace"><?php echo htmlspecialchars($report['ip_address']); ?></small>
                  </td>
                  <td class="notes-cell">
                    <span class="text-truncate d-inline-block" style="max-width: 150px;"
                      title="<?php echo htmlspecialchars($report['admin_notes'] ?: 'No notes'); ?>">
                      <?php echo htmlspecialchars(substr($report['admin_notes'] ?: 'No notes', 0, 30) . (strlen($report['admin_notes'] ?: '') > 30 ? '...' : '')); ?>
                    </span>
                  </td>
                  <td class="actions-cell">
                    <div class="d-flex gap-1 flex-wrap">
                      <a href="view_report.php?id=<?php echo $report['id']; ?>"
                        class="btn btn-primary btn-xs" title="View">
                        <i class="bi bi-eye"></i>
                      </a>

                      <a href="duplicate_cleaner.php?report_id=<?php echo $report['id']; ?>"
                        class="btn btn-warning btn-xs" title="Clean Duplicates">
                        <i class="bi bi-funnel"></i>
                      </a>

                      <button type="button" onclick="editReport(<?php echo $report['id']; ?>, '<?php echo htmlspecialchars($report['category'] ?: 'General'); ?>', '<?php echo htmlspecialchars($report['admin_notes'] ?: ''); ?>')"
                        class="btn btn-secondary btn-xs" title="Edit">
                        <i class="bi bi-pencil"></i>
                      </button>

                      <?php if ($report['is_archived']): ?>
                        <form method="POST" class="d-inline">
                          <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                          <button type="submit" name="unarchive_report" class="btn btn-success btn-xs" title="Unarchive">
                            <i class="bi bi-box-arrow-up"></i>
                          </button>
                        </form>
                      <?php else: ?>
                        <form method="POST" class="d-inline">
                          <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                          <button type="submit" name="archive_report" class="btn btn-warning btn-xs" title="Archive">
                            <i class="bi bi-archive"></i>
                          </button>
                        </form>
                      <?php endif; ?>

                      <form method="POST" class="d-inline"
                        onsubmit="return confirm('Are you sure you want to delete this report?')">
                        <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                        <button type="submit" name="delete_report"
                          class="btn btn-danger btn-xs" title="Delete">
                          <i class="bi bi-trash"></i>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div> <!-- Edit Report Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="editModalLabel">
              <i class="bi bi-pencil me-2"></i>Edit Report
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form method="POST">
            <div class="modal-body">
              <input type="hidden" name="report_id" id="editReportId">
              <div class="mb-3">
                <label for="editCategory" class="form-label">
                  <i class="bi bi-tag me-1"></i>Category
                </label>
                <select name="category" id="editCategory" class="form-select">
                  <option value="General">General</option>
                  <option value="Weekly">Weekly</option>
                  <option value="Monthly">Monthly</option>
                  <option value="Audit">Audit</option>
                  <option value="Special">Special</option>
                </select>
              </div>
              <div class="mb-3">
                <label for="editNotes" class="form-label">
                  <i class="bi bi-chat-text me-1"></i>Admin Notes
                </label>
                <textarea name="admin_notes" id="editNotes" rows="4" class="form-control"
                  placeholder="Add admin notes about this report..."></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" name="update_report" class="btn btn-gradient">
                <i class="bi bi-save me-1"></i>Save Changes
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  <!-- jQuery (required for DataTables) -->
  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

  <!-- DataTables JS -->
  <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>
  <script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
  <script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>
  <script src="https://cdn.datatables.net/select/1.6.2/js/dataTables.select.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>

  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <script>
    // Activity Timeline Chart
    const activityData = <?php echo json_encode($activityData); ?>;
    const ctx = document.getElementById('activityChart').getContext('2d');

    const labels = activityData.map(item => {
      const date = new Date(item.date);
      return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric'
      });
    }).reverse();

    const counts = activityData.map(item => item.count).reverse();

    new Chart(ctx, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: 'Daily Uploads',
          data: counts,
          borderColor: '#667eea',
          backgroundColor: 'rgba(102, 126, 234, 0.1)',
          borderWidth: 2,
          fill: true,
          tension: 0.4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              stepSize: 1
            }
          }
        }
      }
    });

    // Custom date range toggle
    function toggleCustomDate() {
      const range = document.getElementById('date_range').value;
      const customDiv = document.getElementById('customDateRange');
      if (range === 'custom') {
        customDiv.classList.remove('d-none');
      } else {
        customDiv.classList.add('d-none');
      }
    }

    // Bulk operations
    let selectedReports = new Set();

    function updateBulkActions() {
      const checkboxes = document.querySelectorAll('.report-checkbox:checked');
      const count = checkboxes.length;

      selectedReports.clear();
      checkboxes.forEach(cb => selectedReports.add(cb.value));

      document.getElementById('selectedCount').textContent = count + ' selected';
      document.getElementById('selectedReports').value = Array.from(selectedReports).join(',');
      document.getElementById('bulkActions').classList.toggle('active', count > 0);

      // Update select all checkbox
      const allCheckboxes = document.querySelectorAll('.report-checkbox');
      const selectAllCheckbox = document.getElementById('selectAllCheckbox');
      selectAllCheckbox.indeterminate = count > 0 && count < allCheckboxes.length;
      selectAllCheckbox.checked = count === allCheckboxes.length && count > 0;
    }

    function toggleSelectAll() {
      const selectAll = document.getElementById('selectAllCheckbox').checked;
      const checkboxes = document.querySelectorAll('.report-checkbox');

      checkboxes.forEach(cb => {
        cb.checked = selectAll;
      });

      updateBulkActions();
    }

    function selectAll() {
      document.getElementById('selectAllCheckbox').checked = true;
      toggleSelectAll();
    }

    function clearSelection() {
      document.getElementById('selectAllCheckbox').checked = false;
      toggleSelectAll();
    }

    // Edit report modal
    let editModalInstance;

    function editReport(id, category, notes) {
      document.getElementById('editReportId').value = id;
      document.getElementById('editCategory').value = category;
      document.getElementById('editNotes').value = notes;

      if (!editModalInstance) {
        editModalInstance = new bootstrap.Modal(document.getElementById('editModal'));
      }
      editModalInstance.show();
    }

    function closeEditModal() {
      if (editModalInstance) {
        editModalInstance.hide();
      }
    }

    // Auto-submit form on filter changes
    document.getElementById('date_range').addEventListener('change', function() {
      if (this.value !== 'custom') {
        document.getElementById('filtersForm').submit();
      }
    });

    document.getElementById('employee').addEventListener('change', function() {
      document.getElementById('filtersForm').submit();
    });

    document.getElementById('category').addEventListener('change', function() {
      document.getElementById('filtersForm').submit();
    });

    document.getElementById('size_range').addEventListener('change', function() {
      document.getElementById('filtersForm').submit();
    });

    // Search with debounce
    let searchTimeout;
    document.getElementById('search').addEventListener('input', function() {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        document.getElementById('filtersForm').submit();
      }, 500);
    }); // Initialize bulk actions state
    updateBulkActions();

    // Initialize DataTables
    $(document).ready(function() {
      <?php if (!empty($reports)): ?>
        $('#reportsTable').DataTable({
          responsive: true,
          pageLength: 25,
          lengthMenu: [
            [10, 25, 50, 100, -1],
            [10, 25, 50, 100, "All"]
          ],
          order: [
            [4, 'desc']
          ], // Sort by upload date descending
          dom: 'Bfrtip',
          buttons: [{
              extend: 'copy',
              className: 'btn btn-outline-secondary btn-sm',
              text: '<i class="bi bi-clipboard me-1"></i>Copy',
              exportOptions: {
                columns: [1, 2, 3, 4, 5, 6, 7, 8] // Exclude checkbox and actions columns
              }
            },
            {
              extend: 'csv',
              className: 'btn btn-outline-success btn-sm',
              text: '<i class="bi bi-filetype-csv me-1"></i>CSV',
              exportOptions: {
                columns: [1, 2, 3, 4, 5, 6, 7, 8]
              }
            },
            {
              extend: 'excel',
              className: 'btn btn-outline-success btn-sm',
              text: '<i class="bi bi-file-earmark-excel me-1"></i>Excel',
              exportOptions: {
                columns: [1, 2, 3, 4, 5, 6, 7, 8]
              }
            },
            {
              extend: 'pdf',
              className: 'btn btn-outline-danger btn-sm',
              text: '<i class="bi bi-file-earmark-pdf me-1"></i>PDF',
              orientation: 'landscape',
              pageSize: 'A4',
              exportOptions: {
                columns: [1, 2, 3, 4, 5, 6, 7, 8]
              }
            },
            {
              extend: 'print',
              className: 'btn btn-outline-info btn-sm',
              text: '<i class="bi bi-printer me-1"></i>Print',
              exportOptions: {
                columns: [1, 2, 3, 4, 5, 6, 7, 8]
              }
            }
          ],
          columnDefs: [{
              targets: [0, 9], // Checkbox and actions columns
              orderable: false,
              searchable: false
            },
            {
              targets: [5, 6], // Rows and Size columns
              type: 'num'
            }
          ],
          language: {
            search: "Search reports:",
            searchPlaceholder: "Search all fields...",
            lengthMenu: "Show _MENU_ reports per page",
            info: "Showing _START_ to _END_ of _TOTAL_ reports",
            infoEmpty: "No reports available",
            infoFiltered: "(filtered from _MAX_ total reports)",
            paginate: {
              first: '<i class="bi bi-chevron-double-left"></i>',
              last: '<i class="bi bi-chevron-double-right"></i>',
              next: '<i class="bi bi-chevron-right"></i>',
              previous: '<i class="bi bi-chevron-left"></i>'
            }
          },
          drawCallback: function(settings) {
            // Re-bind checkbox events after table redraw
            updateBulkActions();

            // Style pagination buttons
            $('.dataTables_paginate .paginate_button').addClass('btn btn-outline-primary btn-sm mx-1');
            $('.dataTables_paginate .paginate_button.current').removeClass('btn-outline-primary').addClass('btn-primary');
            $('.dataTables_paginate .paginate_button.disabled').addClass('disabled');

            // Style controls
            $('.dataTables_filter input').addClass('form-control form-control-sm');
            $('.dataTables_length select').addClass('form-select form-select-sm');
          },
          initComplete: function() {
            // Style the buttons container
            $('.dt-buttons').addClass('mb-3');
            $('.dt-button').addClass('me-2 mb-2');

            // Add custom styling
            $('.dataTables_wrapper .dataTables_filter').addClass('mb-3');
            $('.dataTables_wrapper .dataTables_length').addClass('mb-3');
          }
        });
      <?php endif; ?>
    });
  </script>
</body>

</html>