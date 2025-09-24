<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Web Scraping Manager - Employee Tracker</title>

  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    /* Custom styles for scraping manager */
    .scraping-header {
      background: linear-gradient(135deg, #6c63ff 0%, #764ba2 100%);
      color: white;
      padding: 3rem 2rem;
      border-radius: 15px;
      margin-bottom: 2rem;
      text-align: center;
    }

    .scraping-header h1 {
      font-size: 3rem;
      margin-bottom: 0.5rem;
      font-weight: 700;
    }

    .scraping-header p {
      font-size: 1.2rem;
      opacity: 0.9;
      margin-bottom: 0;
    }

    .stat-card {
      border-left: 4px solid #6c63ff;
      transition: all 0.3s ease;
    }

    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(108, 99, 255, 0.15);
    }

    .stat-value {
      font-size: 2.5rem;
      font-weight: 700;
      color: #2d3436;
    }

    .stat-label {
      color: #636e72;
      font-weight: 500;
    }

    .file-upload-area {
      border: 2px dashed #dee2e6;
      border-radius: 8px;
      padding: 3rem;
      text-align: center;
      cursor: pointer;
      transition: all 0.3s ease;
      background: #f8f9fa;
    }

    .file-upload-area:hover {
      border-color: #6c63ff;
      background: #f0f4ff;
      transform: scale(1.02);
    }

    .file-upload-area.dragover {
      border-color: #6c63ff;
      background: #e8f2ff;
      transform: scale(1.05);
    }

    .upload-icon {
      font-size: 4rem;
      margin-bottom: 1rem;
      opacity: 0.6;
    }

    .progress-custom {
      height: 12px;
      border-radius: 6px;
      background: #e9ecef;
    }

    .progress-bar-custom {
      background: linear-gradient(45deg, #28a745, #20c997);
      border-radius: 6px;
    }

    .job-item {
      border-bottom: 1px solid #dee2e6;
      padding: 1.5rem;
      transition: background 0.2s ease;
    }

    .job-item:hover {
      background: #f8f9fa;
    }

    .job-item:last-child {
      border-bottom: none;
    }

    .status-badge {
      padding: 0.5rem 1rem;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 600;
      text-transform: uppercase;
    }

    .worker-card {
      padding: 1rem;
      border: 1px solid #dee2e6;
      border-radius: 8px;
      background: #f8f9fa;
      transition: all 0.3s ease;
    }

    .worker-card.active {
      border-color: #28a745;
      background: #e8f5e8;
      box-shadow: 0 4px 12px rgba(40, 167, 69, 0.15);
    }

    .priority-explanation {
      background: #f8f9fa;
      border: 1px solid #dee2e6;
      border-left: 4px solid #6c63ff;
      border-radius: 4px;
      padding: 1rem;
      margin-top: 0.75rem;
    }

    .spinner-border-sm {
      width: 1rem;
      height: 1rem;
    }

    .btn-gradient {
      background: linear-gradient(45deg, #6c63ff, #764ba2);
      border: none;
      color: white;
    }

    .btn-gradient:hover {
      background: linear-gradient(45deg, #5a52ff, #6a42a0);
      color: white;
      transform: translateY(-1px);
    }
  </style>
</head>

<body class="bg-light">
  <div class="container-xl py-4">
    <!-- Header -->
    <div class="scraping-header">
      <h1><i class="bi bi-globe"></i> Web Scraping Manager</h1>
      <p>High-performance email extraction from thousands of websites</p>
    </div>

    <!-- Statistics Dashboard -->
    <div class="row g-4 mb-4" id="statsContainer">
      <!-- Stats will be loaded here -->
    </div>

    <!-- Alerts Container -->
    <div id="alertContainer"></div>

    <!-- Job Creation Form -->
    <div class="card mb-4">
      <div class="card-header">
        <h4 class="card-title mb-0">
          <i class="bi bi-plus-circle text-primary"></i> Create New Scraping Job
        </h4>
      </div>
      <div class="card-body">
        <form id="jobForm">
          <div class="mb-3">
            <label for="jobName" class="form-label fw-bold">Job Name</label>
            <input type="text" class="form-control" id="jobName" name="jobName" required
              placeholder="e.g., Company Email Collection - Jan 2024">
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold">Website URLs</label>
            <div class="border rounded">
              <!-- Input Method Tabs -->
              <ul class="nav nav-tabs nav-fill" role="tablist">
                <li class="nav-item" role="presentation">
                  <button class="nav-link active" id="manual-tab" data-bs-toggle="tab"
                    data-bs-target="#manual-input" type="button" role="tab">
                    <i class="bi bi-keyboard"></i> Manual Entry
                  </button>
                </li>
                <li class="nav-item" role="presentation">
                  <button class="nav-link" id="file-tab" data-bs-toggle="tab"
                    data-bs-target="#file-input" type="button" role="tab">
                    <i class="bi bi-upload"></i> Upload File
                  </button>
                </li>
              </ul>

              <!-- Tab Content -->
              <div class="tab-content p-3">
                <!-- Manual URL input -->
                <div class="tab-pane fade show active" id="manual-input" role="tabpanel">
                  <textarea id="urlList" name="urlList" class="form-control" rows="8"
                    placeholder="Enter URLs, one per line:&#10;https://example1.com&#10;https://example2.com&#10;example3.com"></textarea>
                  <div class="form-text">
                    You can paste thousands of URLs here. One URL per line. HTTP/HTTPS is optional.
                  </div>
                </div>

                <!-- File upload input -->
                <div class="tab-pane fade" id="file-input" role="tabpanel">
                  <div class="file-upload-area" onclick="document.getElementById('urlFile').click()">
                    <div class="upload-icon">📁</div>
                    <div>
                      <strong>Click to upload a file</strong> or drag and drop
                      <br><small class="text-muted">Accepts .txt and .csv files (max 10MB, up to 50,000 URLs)</small>
                    </div>
                  </div>
                  <input type="file" id="urlFile" accept=".txt,.csv" style="display: none;" onchange="handleFileUpload(event)">

                  <div id="fileInfo" class="alert alert-success mt-3 d-none">
                    <strong>File loaded:</strong> <span id="fileName"></span><br>
                    <strong>URLs found:</strong> <span id="urlCount"></span>
                    <button type="button" class="btn btn-sm btn-outline-success ms-2" onclick="clearFile()">
                      <i class="bi bi-x-lg"></i> Clear
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="mb-3">
            <label for="priority" class="form-label fw-bold">Job Priority</label>
            <select id="priority" name="priority" class="form-select">
              <option value="high">🚀 High Priority - Fastest processing (uses more workers)</option>
              <option value="normal" selected>⚡ Normal Priority - Balanced processing (recommended)</option>
              <option value="low">🐌 Low Priority - Background processing (uses fewer resources)</option>
            </select>
            <div class="priority-explanation">
              <strong>Priority levels:</strong><br>
              • <strong>High:</strong> Your job gets priority in the queue and uses more worker threads for faster completion<br>
              • <strong>Normal:</strong> Standard processing speed with balanced resource usage<br>
              • <strong>Low:</strong> Runs in background with minimal resource usage, good for large jobs that aren't urgent
            </div>
          </div>

          <button type="submit" class="btn btn-gradient btn-lg">
            <div class="spinner-border spinner-border-sm d-none me-2" id="createSpinner" role="status"></div>
            <i class="bi bi-rocket-takeoff"></i> Create Scraping Job
          </button>
        </form>
      </div>
    </div>

    <!-- Jobs List -->
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="card-title mb-0">
          <i class="bi bi-list-task text-primary"></i> Active Scraping Jobs
        </h4>
        <button class="btn btn-outline-primary btn-sm" onclick="refreshJobs()">
          <i class="bi bi-arrow-clockwise"></i> Refresh
        </button>
      </div>
      <div class="card-body p-0">
        <div id="jobsList">
          <div class="text-center p-4">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
            <div class="mt-2">Loading jobs...</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Worker Status -->
    <div class="card">
      <div class="card-header">
        <h4 class="card-title mb-0">
          <i class="bi bi-cpu text-primary"></i> Worker Status
        </h4>
      </div>
      <div class="card-body">
        <div id="workerStatus">
          <div class="text-center p-4">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
            <div class="mt-2">Loading worker information...</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap 5 JavaScript -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    // Global state
    let refreshInterval;
    let uploadedUrls = [];

    // Initialize page
    document.addEventListener('DOMContentLoaded', function() {
      loadStats();
      loadJobs();
      loadWorkerStatus();

      // Start auto-refresh
      refreshInterval = setInterval(() => {
        loadStats();
        loadJobs();
        loadWorkerStatus();
      }, 5000); // Refresh every 5 seconds for better real-time feel

      // Job form submission
      document.getElementById('jobForm').addEventListener('submit', createJob);

      // File upload drag and drop
      setupFileUpload();
    });

    // Load system statistics
    function loadStats() {
      fetch('scraping_api.php?action=stats')
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            updateStatsDisplay(data.stats);
          }
        })
        .catch(error => console.error('Error loading stats:', error));
    }

    // Update statistics display
    function updateStatsDisplay(stats) {
      const container = document.getElementById('statsContainer');
      container.innerHTML = `
                <div class="col-md">
                    <div class="card stat-card h-100">
                        <div class="card-body text-center">
                            <div class="stat-value">${stats.total_jobs || 0}</div>
                            <div class="stat-label">Total Jobs</div>
                        </div>
                    </div>
                </div>
                <div class="col-md">
                    <div class="card stat-card h-100">
                        <div class="card-body text-center">
                            <div class="stat-value">${stats.active_jobs || 0}</div>
                            <div class="stat-label">Active Jobs</div>
                        </div>
                    </div>
                </div>
                <div class="col-md">
                    <div class="card stat-card h-100">
                        <div class="card-body text-center">
                            <div class="stat-value">${formatNumber(stats.total_urls || 0)}</div>
                            <div class="stat-label">URLs Processed</div>
                        </div>
                    </div>
                </div>
                <div class="col-md">
                    <div class="card stat-card h-100">
                        <div class="card-body text-center">
                            <div class="stat-value">${formatNumber(stats.total_emails || 0)}</div>
                            <div class="stat-label">Emails Found</div>
                        </div>
                    </div>
                </div>
                <div class="col-md">
                    <div class="card stat-card h-100">
                        <div class="card-body text-center">
                            <div class="stat-value">${stats.active_workers || 0}</div>
                            <div class="stat-label">Active Workers</div>
                        </div>
                    </div>
                </div>
            `;
    }

    // Load jobs list
    function loadJobs() {
      fetch('scraping_api.php?action=jobs')
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            updateJobsDisplay(data.jobs);
          }
        })
        .catch(error => console.error('Error loading jobs:', error));
    }

    // Update jobs display
    function updateJobsDisplay(jobs) {
      const container = document.getElementById('jobsList');

      if (jobs.length === 0) {
        container.innerHTML = `
          <div class="text-center p-5">
            <i class="bi bi-inbox display-1 text-muted"></i>
            <h5 class="mt-3 text-muted">No scraping jobs yet</h5>
            <p class="text-muted">Create your first job above!</p>
          </div>
        `;
        return;
      }

      container.innerHTML = jobs.map(job => `
        <div class="job-item row align-items-center">
          <div class="col-md-3">
            <h6 class="fw-bold mb-1">${escapeHtml(job.job_name)}</h6>
            <small class="text-muted">
              Created: ${formatDate(job.created_at)} | ${formatNumber(job.total_urls)} URLs
            </small>
          </div>
          <div class="col-md-2">
            <div class="progress progress-custom mb-1">
              <div class="progress-bar progress-bar-custom" role="progressbar" 
                style="width: ${job.progress_percent}%"></div>
            </div>
            <small class="text-muted">
              ${job.processed_urls}/${job.total_urls} (${job.progress_percent.toFixed(1)}%)
            </small>
          </div>
          <div class="col-md-2 text-center">
            <div class="fw-bold text-success fs-5">${formatNumber(job.total_emails_found)}</div>
            <small class="text-muted">emails found</small>
            <br><small class="text-muted">Success: ${job.success_rate.toFixed(1)}%</small>
          </div>
          <div class="col-md-2 text-center">
            <span class="badge status-badge ${getStatusClass(job.status)}">${job.status}</span>
          </div>
          <div class="col-md-3 text-end">
            <div class="btn-group btn-group-sm" role="group">
              ${getJobActionButtons(job)}
            </div>
          </div>
        </div>
      `).join('');
    }

    // Get status class for badge
    function getStatusClass(status) {
      const statusClasses = {
        'pending': 'bg-warning',
        'running': 'bg-primary',
        'completed': 'bg-success',
        'paused': 'bg-secondary',
        'failed': 'bg-danger',
        'cancelled': 'bg-secondary'
      };
      return statusClasses[status] || 'bg-secondary';
    }

    // Get action buttons for job
    function getJobActionButtons(job) {
      const buttons = [];

      if (job.status === 'pending') {
        buttons.push(`<button class="btn btn-success btn-sm" onclick="startJob(${job.id})" title="Start Job">
          <i class="bi bi-play-fill"></i>
        </button>`);
        buttons.push(`<button class="btn btn-info btn-sm" onclick="processJob(${job.id})" title="Process Now (10 URLs)">
          <i class="bi bi-lightning-charge"></i>
        </button>`);
      }

      if (job.status === 'running') {
        buttons.push(`<button class="btn btn-warning btn-sm" onclick="pauseJob(${job.id})" title="Pause Job">
          <i class="bi bi-pause-fill"></i>
        </button>`);
        buttons.push(`<button class="btn btn-info btn-sm" onclick="processJob(${job.id})" title="Process Now (10 URLs)">
          <i class="bi bi-lightning-charge"></i>
        </button>`);
      }

      if (job.status === 'paused') {
        buttons.push(`<button class="btn btn-success btn-sm" onclick="startJob(${job.id})" title="Resume Job">
          <i class="bi bi-play-fill"></i>
        </button>`);
      }

      if (['running', 'paused'].includes(job.status)) {
        buttons.push(`<button class="btn btn-danger btn-sm" onclick="cancelJob(${job.id})" title="Cancel Job">
          <i class="bi bi-x-lg"></i>
        </button>`);
      }

      if (job.status === 'completed') {
        buttons.push(`<button class="btn btn-primary btn-sm" onclick="downloadResults(${job.id})" title="Download Results">
          <i class="bi bi-download"></i>
        </button>`);
      }

      buttons.push(`<button class="btn btn-outline-secondary btn-sm" onclick="viewJobDetails(${job.id})" title="View Details">
        <i class="bi bi-eye"></i>
      </button>`);

      return buttons.join('');
    }

    // Load worker status
    function loadWorkerStatus() {
      fetch('scraping_api.php?action=worker_status')
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            updateWorkerDisplay(data.workers);
          }
        })
        .catch(error => console.error('Error loading worker status:', error));
    }

    // Update worker display
    function updateWorkerDisplay(workers) {
      const container = document.getElementById('workerStatus');

      if (workers.length === 0) {
        container.innerHTML = `
          <div class="text-center p-4">
            <i class="bi bi-cpu display-1 text-muted"></i>
            <h6 class="mt-3 text-muted">No active workers found</h6>
            <p class="text-muted">Start workers using the command line interface.</p>
          </div>
        `;
        return;
      }

      container.innerHTML = `
        <div class="row g-3">
          ${workers.map(worker => `
            <div class="col-md-6 col-lg-4">
              <div class="worker-card ${worker.status === 'running' ? 'active' : ''}">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <strong class="text-truncate">${worker.worker_id}</strong>
                  <span class="badge ${worker.status === 'running' ? 'bg-success' : 'bg-secondary'}">${worker.status}</span>
                </div>
                <div class="row g-2 small">
                  <div class="col-6">PID: ${worker.pid}</div>
                  <div class="col-6">Processed: ${worker.processed_count || 0}</div>
                  <div class="col-6">Emails: ${worker.emails_found || 0}</div>
                  <div class="col-6">Uptime: ${formatDuration(worker.uptime || 0)}</div>
                </div>
              </div>
            </div>
          `).join('')}
        </div>
      `;
    }

    // Create new job
    function createJob(event) {
      event.preventDefault();

      const formData = new FormData(event.target);
      let urls = [];

      // Check if using uploaded file or manual input
      if (uploadedUrls.length > 0) {
        urls = uploadedUrls;
      } else {
        urls = formData.get('urlList').split('\n')
          .map(url => url.trim())
          .filter(url => url.length > 0);
      }

      if (urls.length === 0) {
        showAlert('Please enter URLs manually or upload a file with URLs', 'danger');
        return;
      }

      const spinner = document.getElementById('createSpinner');
      const submitBtn = event.target.querySelector('button[type="submit"]');

      spinner.classList.remove('d-none');
      submitBtn.disabled = true;

      fetch('scraping_api.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            action: 'create_job',
            job_name: formData.get('jobName'),
            urls: urls,
            priority: formData.get('priority')
          })
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            showAlert(`Job created successfully! Processing ${data.total_urls} URLs.`, 'success');
            document.getElementById('jobForm').reset();
            uploadedUrls = []; // Clear uploaded URLs
            clearFile();
            // Reset to manual input tab
            const manualTab = document.getElementById('manual-tab');
            const manualTabInstance = new bootstrap.Tab(manualTab);
            manualTabInstance.show();
            loadJobs();
            loadStats();
          } else {
            showAlert('Error creating job: ' + data.error, 'danger');
          }
        })
        .catch(error => {
          showAlert('Error creating job: ' + error.message, 'danger');
        })
        .finally(() => {
          spinner.classList.add('d-none');
          submitBtn.disabled = false;
        });
    }

    // Setup file upload functionality
    function setupFileUpload() {
      const uploadArea = document.querySelector('.file-upload-area');

      // Drag and drop events
      uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.classList.add('dragover');
      });

      uploadArea.addEventListener('dragleave', () => {
        uploadArea.classList.remove('dragover');
      });

      uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.classList.remove('dragover');

        const files = e.dataTransfer.files;
        if (files.length > 0) {
          handleFileSelection(files[0]);
        }
      });
    }

    // Handle file upload
    function handleFileUpload(event) {
      const file = event.target.files[0];
      if (file) {
        handleFileSelection(file);
      }
    }

    // Handle file selection (from click or drag)
    function handleFileSelection(file) {
      // Validate file type
      const validTypes = ['text/plain', 'text/csv', 'application/csv'];
      const validExtensions = ['.txt', '.csv'];
      const fileExt = '.' + file.name.split('.').pop().toLowerCase();

      if (!validTypes.includes(file.type) && !validExtensions.includes(fileExt)) {
        showAlert('Invalid file type. Please upload a .txt or .csv file.', 'danger');
        return;
      }

      // Validate file size (10MB max)
      if (file.size > 10 * 1024 * 1024) {
        showAlert('File too large. Maximum size is 10MB.', 'danger');
        return;
      }

      // Upload file
      const formData = new FormData();
      formData.append('urls_file', file);

      fetch('scraping_api.php?action=upload_urls', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            uploadedUrls = data.urls;
            displayFileInfo(data.filename, data.total_count);
            showAlert(`File uploaded successfully! Found ${data.total_count} URLs.`, 'success');
          } else {
            showAlert('Error uploading file: ' + data.error, 'danger');
          }
        })
        .catch(error => {
          showAlert('Error uploading file: ' + error.message, 'danger');
        });
    }

    // Display file information
    function displayFileInfo(filename, count) {
      document.getElementById('fileName').textContent = filename;
      document.getElementById('urlCount').textContent = formatNumber(count);
      document.getElementById('fileInfo').classList.remove('d-none');
    }

    // Clear uploaded file
    function clearFile() {
      uploadedUrls = [];
      document.getElementById('urlFile').value = '';
      document.getElementById('fileInfo').classList.add('d-none');
    }

    // Job control functions
    function startJob(jobId) {
      jobAction(jobId, 'start');
    }

    function pauseJob(jobId) {
      jobAction(jobId, 'pause');
    }

    function cancelJob(jobId) {
      if (confirm('Are you sure you want to cancel this job?')) {
        jobAction(jobId, 'cancel');
      }
    }

    function processJob(jobId) {
      if (confirm('Process 10 URLs immediately? This may take a few minutes.')) {
        const btn = event.target.closest('button');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i>';
        btn.disabled = true;

        fetch('scraping_api.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify({
              action: 'process_job',
              job_id: jobId,
              limit: 10
            })
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              showAlert(`Job processing completed. ${data.output}`, 'success');
              loadJobs(); // Refresh job list
            } else {
              showAlert(`Processing failed: ${data.error}`, 'danger');
            }
          })
          .catch(error => {
            showAlert(`Processing error: ${error.message}`, 'danger');
          })
          .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
          });
      }
    }

    function jobAction(jobId, action) {
      fetch('scraping_api.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            action: action + '_job',
            job_id: jobId
          })
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            showAlert(data.message, 'success');
            loadJobs();
            loadStats();
          } else {
            showAlert('Error: ' + data.error, 'danger');
          }
        })
        .catch(error => {
          showAlert('Error: ' + error.message, 'danger');
        });
    }

    // Download job results
    function downloadResults(jobId) {
      window.open(`scraping_api.php?action=export&job_id=${jobId}`, '_blank');
    }

    // View job details
    function viewJobDetails(jobId) {
      window.open(`scraping_details.php?job_id=${jobId}`, '_blank');
    }

    // Refresh jobs manually
    function refreshJobs() {
      loadStats();
      loadJobs();
      loadWorkerStatus();
      showAlert('Data refreshed successfully', 'info');
    }

    // Utility functions
    function showAlert(message, type) {
      const container = document.getElementById('alertContainer');
      const alertDiv = document.createElement('div');
      alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
      alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      `;

      container.appendChild(alertDiv);

      // Auto-dismiss after 5 seconds
      setTimeout(() => {
        if (alertDiv.parentElement) {
          alertDiv.remove();
        }
      }, 5000);
    }

    function formatNumber(num) {
      return new Intl.NumberFormat().format(num);
    }

    function formatDate(dateString) {
      return new Date(dateString).toLocaleDateString() + ' ' +
        new Date(dateString).toLocaleTimeString();
    }

    function formatDuration(seconds) {
      const hours = Math.floor(seconds / 3600);
      const minutes = Math.floor((seconds % 3600) / 60);
      const secs = seconds % 60;

      if (hours > 0) {
        return `${hours}h ${minutes}m`;
      } else if (minutes > 0) {
        return `${minutes}m ${secs}s`;
      } else {
        return `${secs}s`;
      }
    }

    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    // Cleanup on page unload
    window.addEventListener('beforeunload', () => {
      if (refreshInterval) {
        clearInterval(refreshInterval);
      }
    });
  </script>
</body>

</html>