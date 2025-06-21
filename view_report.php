<?php
require_once 'common/config_mysql.php';
requireAdmin();

// Get report ID
$reportId = intval($_GET['id'] ?? 0);
if ($reportId <= 0) {
  header('Location: dashboard.php');
  exit;
}

// Check if raw file download is requested
if (isset($_GET['raw']) && $_GET['raw'] === '1') {
  // Get report info
  $db = getDB();
  $stmt = $db->prepare("SELECT * FROM reports WHERE id = ?");
  $stmt->execute([$reportId]);
  $report = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$report) {
    http_response_code(404);
    echo 'Report not found';
    exit;
  }

  $filePath = DECRYPTED_PATH . $report['decrypted_filename'];
  if (!file_exists($filePath)) {
    http_response_code(404);
    echo 'File not found';
    exit;
  }

  // Set headers for Excel file download
  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment; filename="' . $report['original_filename'] . '"');
  header('Content-Length: ' . filesize($filePath));
  header('Cache-Control: must-revalidate');
  header('Pragma: public');

  // Output file
  readfile($filePath);
  exit;
}

// Get report info
$db = getDB();
$stmt = $db->prepare("SELECT * FROM reports WHERE id = ?");
$stmt->execute([$reportId]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$report) {
  header('Location: dashboard.php');
  exit;
}

$filePath = DECRYPTED_PATH . $report['decrypted_filename'];
if (!file_exists($filePath)) {
  $error = "Report file not found on server";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>View Report - <?php echo htmlspecialchars($report['employee_name']); ?></title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">

  <!-- DataTables CSS -->
  <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.bootstrap5.min.css" rel="stylesheet">

  <!-- Custom CSS -->
  <style>
    .header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    }

    .btn-secondary-custom {
      background: rgba(255, 255, 255, 0.2);
      color: white;
      border: 2px solid rgba(255, 255, 255, 0.3);
      padding: 0.6rem 1.2rem;
      border-radius: 8px;
      font-weight: 600;
      transition: all 0.3s ease;
    }

    .btn-secondary-custom:hover {
      background: rgba(255, 255, 255, 0.3);
      color: white;
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }

    .info-card {
      border: none;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      border-radius: 12px;
      transition: all 0.3s ease;
    }

    .info-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    }

    .stat-icon {
      width: 40px;
      height: 40px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    #excelDataTable thead th {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
      color: white !important;
      border: none !important;
      padding: 1rem 0.75rem;
      font-weight: 600;
    }

    .dataTables_wrapper .dataTables_filter input {
      border: 2px solid #e1e5e9;
      border-radius: 8px;
      padding: 0.5rem 1rem;
      transition: all 0.3s ease;
    }

    .dataTables_wrapper .dataTables_filter input:focus {
      border-color: #667eea;
      box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
      outline: none;
    }

    .dt-button {
      background: white !important;
      border: 1px solid #dee2e6 !important;
      color: #495057 !important;
      padding: 0.5rem 1rem !important;
      border-radius: 6px !important;
      margin: 0 0.25rem !important;
      font-weight: 500 !important;
    }

    .dt-button:hover {
      background: #f8f9fa !important;
      border-color: #667eea !important;
      color: #667eea !important;
    }
  </style>
</head>

<body class="bg-light">
  <!-- Header -->
  <header class="header">
    <div class="container-fluid px-4">
      <div class="d-flex justify-content-between align-items-center py-3">
        <h1 class="h3 mb-0 text-white">
          <i class="bi bi-file-earmark-text me-2"></i>
          Report: <?php echo htmlspecialchars($report['employee_name']); ?>
        </h1>
        <a href="dashboard.php" class="btn btn-secondary-custom">
          <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
        </a>
      </div>
    </div>
  </header>

  <!-- Main Container -->
  <div class="container-fluid px-4 py-4">

    <!-- Report Information -->
    <div class="row mb-4">
      <div class="col-12">
        <div class="card info-card">
          <div class="card-header bg-white border-bottom">
            <h5 class="mb-0 text-dark">
              <i class="bi bi-info-circle me-2 text-primary"></i>
              Report Information
            </h5>
          </div>
          <div class="card-body">
            <div class="row g-4">

              <!-- Employee Info -->
              <div class="col-lg-3 col-md-6">
                <div class="d-flex align-items-center">
                  <div class="stat-icon bg-primary bg-opacity-10 me-3">
                    <i class="bi bi-person-fill text-primary fs-5"></i>
                  </div>
                  <div>
                    <small class="text-muted fw-medium">Employee</small>
                    <div class="fw-semibold text-dark">
                      <?php echo htmlspecialchars($report['employee_name']); ?>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Upload Date -->
              <div class="col-lg-3 col-md-6">
                <div class="d-flex align-items-center">
                  <div class="stat-icon bg-success bg-opacity-10 me-3">
                    <i class="bi bi-calendar-check text-success fs-5"></i>
                  </div>
                  <div>
                    <small class="text-muted fw-medium">Upload Date</small>
                    <div class="fw-semibold text-dark">
                      <?php echo date('M j, Y', strtotime($report['upload_date'])); ?>
                    </div>
                    <small class="text-muted">
                      <?php echo date('g:i A', strtotime($report['upload_date'])); ?>
                    </small>
                  </div>
                </div>
              </div>

              <!-- File Stats -->
              <div class="col-lg-2 col-md-4">
                <div class="text-center">
                  <div class="stat-icon bg-info bg-opacity-10 mx-auto mb-2">
                    <i class="bi bi-table text-info fs-5"></i>
                  </div>
                  <div class="h5 mb-1 text-info fw-bold">
                    <?php echo number_format($report['row_count']); ?>
                  </div>
                  <small class="text-muted fw-medium">Total Rows</small>
                </div>
              </div>

              <div class="col-lg-2 col-md-4">
                <div class="text-center">
                  <div class="stat-icon bg-warning bg-opacity-10 mx-auto mb-2">
                    <i class="bi bi-hdd text-warning fs-5"></i>
                  </div>
                  <div class="h5 mb-1 text-warning fw-bold">
                    <?php echo formatFileSize($report['file_size']); ?>
                  </div>
                  <small class="text-muted fw-medium">File Size</small>
                </div>
              </div>

              <div class="col-lg-2 col-md-4">
                <div class="text-center">
                  <div class="stat-icon bg-secondary bg-opacity-10 mx-auto mb-2">
                    <i class="bi bi-geo-alt text-secondary fs-5"></i>
                  </div>
                  <div class="small text-secondary fw-semibold">
                    <?php echo htmlspecialchars($report['ip_address']); ?>
                  </div>
                  <small class="text-muted fw-medium">Source IP</small>
                </div>
              </div>

            </div>

            <!-- Original Filename -->
            <div class="row mt-3">
              <div class="col-12">
                <div class="bg-light rounded p-3">
                  <div class="d-flex align-items-center">
                    <i class="bi bi-file-earmark-excel text-success me-2 fs-5"></i>
                    <div>
                      <small class="text-muted fw-medium">Original Filename</small>
                      <div class="fw-semibold text-dark">
                        <?php echo htmlspecialchars($report['original_filename']); ?>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Excel Data Viewer -->
    <div class="row">
      <div class="col-12">
        <div class="card info-card">
          <div class="card-header bg-white border-bottom">
            <div class="d-flex justify-content-between align-items-center">
              <h5 class="mb-0 text-dark">
                <i class="bi bi-table me-2 text-info"></i>
                Excel Data Explorer
              </h5>
              <button onclick="downloadExcel()" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-download me-1"></i>Download Original
              </button>
            </div>
          </div>

          <?php if (isset($error)): ?>
            <div class="card-body">
              <div class="text-center py-5">
                <div class="display-1 text-danger mb-4">
                  <i class="bi bi-exclamation-triangle-fill"></i>
                </div>
                <h3 class="text-danger fw-bold mb-3">File Access Error</h3>
                <p class="fs-5 text-muted mb-4"><?php echo htmlspecialchars($error); ?></p>
                <div class="d-flex gap-3 justify-content-center">
                  <a href="dashboard.php" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-2"></i>Return to Dashboard
                  </a>
                  <button onclick="location.reload()" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-clockwise me-2"></i>Retry
                  </button>
                </div>
              </div>
            </div>
          <?php else: ?>
            <div class="card-body">
              <div id="loadingContainer" class="text-center py-5">
                <div class="d-flex flex-column align-items-center">
                  <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;"></div>
                  <h4 class="text-primary fw-bold mb-2">Loading Excel Data</h4>
                  <p class="text-muted">Processing file and preparing interactive table...</p>
                </div>
              </div>

              <div id="dataTableContainer" style="display: none;">
                <div class="table-responsive">
                  <table id="excelDataTable" class="table table-striped table-hover table-bordered mb-0" style="width:100%">
                    <thead>
                      <!-- Headers will be populated by JavaScript -->
                    </thead>
                    <tbody>
                      <!-- Data will be populated by JavaScript -->
                    </tbody>
                  </table>
                </div>
              </div>

              <div id="errorContainer" style="display: none;">
                <div class="text-center py-5">
                  <div class="display-1 text-danger mb-4">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                  </div>
                  <h3 class="text-danger fw-bold mb-3">Failed to Load Excel File</h3>
                  <p class="fs-5 text-muted mb-4" id="errorMessage"></p>
                  <div class="d-flex gap-3 justify-content-center">
                    <button onclick="loadExcelFile()" class="btn btn-outline-primary">
                      <i class="bi bi-arrow-clockwise me-2"></i>Retry Loading
                    </button>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                      <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                    </a>
                  </div>
                </div>
              </div>
            </div>
          <?php endif; ?>
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
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>

  <!-- Excel processing library -->
  <script src="libs/xlsx.full.min.js"></script>

  <script>
    let dataTable = null;
    let workbook = null;

    // Load Excel file and initialize DataTable
    async function loadExcelFile() {
      try {
        showContainer('loadingContainer');

        const response = await fetch('<?php echo "admin/decrypted/" . urlencode($report['decrypted_filename']); ?>');
        if (!response.ok) {
          throw new Error('Failed to load file from server');
        }

        const arrayBuffer = await response.arrayBuffer();
        workbook = XLSX.read(arrayBuffer, {
          type: 'array'
        });

        // Get first worksheet
        const sheetName = workbook.SheetNames[0];
        const worksheet = workbook.Sheets[sheetName];

        // Convert to JSON with headers
        const jsonData = XLSX.utils.sheet_to_json(worksheet, {
          header: 1
        });

        if (jsonData.length === 0) {
          throw new Error('The Excel file appears to be empty');
        }

        // Extract headers and data
        const headers = jsonData[0] || [];
        const rows = jsonData.slice(1);

        // Initialize DataTable
        initializeDataTable(headers, rows);

        showContainer('dataTableContainer');

      } catch (error) {
        console.error('Error loading Excel:', error);
        document.getElementById('errorMessage').textContent = error.message;
        showContainer('errorContainer');
      }
    }

    // Initialize DataTable with Excel data
    function initializeDataTable(headers, data) {
      // Destroy existing DataTable if it exists
      if (dataTable) {
        dataTable.destroy();
      }

      // Build table HTML
      const tableHead = document.querySelector('#excelDataTable thead');
      const tableBody = document.querySelector('#excelDataTable tbody');

      // Clear existing content
      tableHead.innerHTML = '';
      tableBody.innerHTML = '';

      // Add headers
      const headerRow = document.createElement('tr');
      headers.forEach((header, index) => {
        const th = document.createElement('th');
        th.innerHTML = `<i class="bi bi-table me-2"></i>${escapeHtml(header || `Column ${index + 1}`)}`;
        th.style.color = 'white';
        th.style.backgroundColor = '#667eea';
        headerRow.appendChild(th);
      });
      tableHead.appendChild(headerRow);

      // Add data rows
      data.forEach(row => {
        const tr = document.createElement('tr');
        headers.forEach((header, cellIndex) => {
          const td = document.createElement('td');
          const cellValue = String(row[cellIndex] || '');

          // Format cell content
          let cellContent = formatCellContent(cellValue);
          td.innerHTML = cellContent;
          td.style.color = '#212529';
          tr.appendChild(td);
        });
        tableBody.appendChild(tr);
      });

      // Initialize DataTable with advanced features
      dataTable = $('#excelDataTable').DataTable({
        responsive: true,
        pageLength: 25,
        lengthMenu: [
          [10, 25, 50, 100, -1],
          [10, 25, 50, 100, "All"]
        ],
        order: [
          [0, 'asc']
        ],
        dom: 'Bfrtip',
        buttons: [{
            extend: 'copy',
            className: 'btn btn-outline-secondary btn-sm',
            text: '<i class="bi bi-clipboard me-1"></i>Copy'
          },
          {
            extend: 'csv',
            className: 'btn btn-outline-success btn-sm',
            text: '<i class="bi bi-filetype-csv me-1"></i>CSV'
          },
          {
            extend: 'excel',
            className: 'btn btn-outline-success btn-sm',
            text: '<i class="bi bi-file-earmark-excel me-1"></i>Excel'
          },
          {
            extend: 'pdf',
            className: 'btn btn-outline-danger btn-sm',
            text: '<i class="bi bi-file-earmark-pdf me-1"></i>PDF',
            orientation: 'landscape',
            pageSize: 'A4'
          },
          {
            extend: 'print',
            className: 'btn btn-outline-info btn-sm',
            text: '<i class="bi bi-printer me-1"></i>Print'
          }
        ],
        language: {
          search: "Search:",
          lengthMenu: "Show _MENU_ entries",
          info: "Showing _START_ to _END_ of _TOTAL_ entries",
          infoEmpty: "No entries available",
          infoFiltered: "(filtered from _MAX_ total entries)",
          paginate: {
            first: '<i class="bi bi-chevron-double-left"></i>',
            last: '<i class="bi bi-chevron-double-right"></i>',
            next: '<i class="bi bi-chevron-right"></i>',
            previous: '<i class="bi bi-chevron-left"></i>'
          }
        }
      });
    }

    // Format cell content with special handling for URLs, emails, etc.
    function formatCellContent(cellValue) {
      if (!cellValue) return '';

      const isUrl = cellValue.startsWith('http://') || cellValue.startsWith('https://');
      const isEmail = cellValue.includes('@') && cellValue.includes('.') && !cellValue.includes(' ');

      if (isUrl) {
        return `<a href="${cellValue}" target="_blank" class="text-primary text-decoration-none">
          <i class="bi bi-link-45deg me-1"></i>${escapeHtml(cellValue)}
        </a>`;
      } else if (isEmail) {
        return `<a href="mailto:${cellValue}" class="text-primary text-decoration-none">
          <i class="bi bi-envelope me-1"></i>${escapeHtml(cellValue)}
        </a>`;
      } else if (cellValue.length > 50) {
        return `<span title="${escapeHtml(cellValue)}" class="text-truncate d-inline-block" style="max-width: 200px;">
          ${escapeHtml(cellValue)}
        </span>`;
      } else {
        return escapeHtml(cellValue);
      }
    }

    // Show specific container and hide others
    function showContainer(containerId) {
      const containers = ['loadingContainer', 'dataTableContainer', 'errorContainer'];
      containers.forEach(id => {
        const element = document.getElementById(id);
        if (element) {
          element.style.display = id === containerId ? 'block' : 'none';
        }
      });
    }

    // Download Excel file
    function downloadExcel() {
      const link = document.createElement('a');
      link.href = '<?php echo "admin/decrypted/" . urlencode($report['decrypted_filename']); ?>';
      link.download = '<?php echo htmlspecialchars($report['original_filename']); ?>'.replace('.encrypted.xlsx', '.xlsx');
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    }

    // Escape HTML
    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    // Load file on page load
    <?php if (!isset($error)): ?>
      $(document).ready(function() {
        loadExcelFile();
      });
    <?php endif; ?>
  </script>
</body>

</html>