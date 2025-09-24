<?php

/**
 * Scraping Job Details View
 * Shows detailed information about a specific scraping job
 */

// Include configuration
require_once __DIR__ . '/common/config_mysql.php';
require_once __DIR__ . '/libs/ScrapingJobManager.php';

$jobId = (int)($_GET['job_id'] ?? 0);

if (!$jobId) {
  http_response_code(400);
  die('Job ID is required');
}

try {
  $jobManager = new ScrapingJobManager();
  $db = getDB();

  // Get job details
  $stmt = $db->prepare("
        SELECT j.*, 
               COUNT(u.id) as total_urls,
               COUNT(CASE WHEN u.status = 'completed' THEN 1 END) as completed_urls,
               COUNT(CASE WHEN u.status = 'failed' THEN 1 END) as failed_urls,
               COUNT(CASE WHEN u.status = 'processing' THEN 1 END) as processing_urls,
               COUNT(CASE WHEN u.status = 'pending' THEN 1 END) as pending_urls,
               COUNT(CASE WHEN e.id IS NOT NULL THEN 1 END) as total_emails
        FROM scraping_jobs j
        LEFT JOIN scraping_urls u ON j.id = u.job_id
        LEFT JOIN scraping_emails e ON u.id = e.url_id
        WHERE j.id = ?
        GROUP BY j.id
    ");
  $stmt->execute([$jobId]);
  $job = $stmt->fetch();

  if (!$job) {
    http_response_code(404);
    die('Job not found');
  }

  // Get recent activity logs
  $stmt = $db->prepare("
        SELECT * FROM scraping_logs 
        WHERE job_id = ? 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
  $stmt->execute([$jobId]);
  $logs = $stmt->fetchAll();

  // Get URL details with pagination
  $page = (int)($_GET['page'] ?? 1);
  $limit = 50;
  $offset = ($page - 1) * $limit;

  $stmt = $db->prepare("
        SELECT u.*, 
               COUNT(e.id) as email_count,
               GROUP_CONCAT(DISTINCT e.email ORDER BY e.email SEPARATOR ', ') as emails_sample
        FROM scraping_urls u
        LEFT JOIN scraping_emails e ON u.id = e.url_id
        WHERE u.job_id = ?
        GROUP BY u.id
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?
    ");
  $stmt->execute([$jobId, $limit, $offset]);
  $urls = $stmt->fetchAll();

  // Get total URL count for pagination
  $stmt = $db->prepare("SELECT COUNT(*) FROM scraping_urls WHERE job_id = ?");
  $stmt->execute([$jobId]);
  $totalUrls = $stmt->fetchColumn();
  $totalPages = ceil($totalUrls / $limit);
} catch (Exception $e) {
  http_response_code(500);
  die('Error loading job details: ' . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Job Details - <?php echo htmlspecialchars($job['job_name']); ?></title> <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body class="bg-light">
  <div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
      <div class="col">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <h1 class="h3 mb-1">Job Details</h1>
            <nav aria-label="breadcrumb">
              <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="scraping_manager.php" class="text-decoration-none">Scraping Manager</a></li>
                <li class="breadcrumb-item active">Job #<?php echo $job['id']; ?></li>
              </ol>
            </nav>
          </div>
          <div>
            <button type="button" class="btn btn-outline-secondary" onclick="window.close()">
              <i class="bi bi-x-lg"></i> Close
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Job Overview -->
    <div class="row mb-4">
      <div class="col-12">
        <div class="card">
          <div class="card-header">
            <h5 class="card-title mb-0">
              <i class="bi bi-info-circle me-2"></i>
              <?php echo htmlspecialchars($job['job_name']); ?>
            </h5>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6">
                <dl class="row">
                  <dt class="col-sm-4">Job ID:</dt>
                  <dd class="col-sm-8">#<?php echo $job['id']; ?></dd>

                  <dt class="col-sm-4">Status:</dt>
                  <dd class="col-sm-8">
                    <?php
                    $statusClass = [
                      'pending' => 'warning',
                      'running' => 'primary',
                      'completed' => 'success',
                      'failed' => 'danger',
                      'paused' => 'secondary'
                    ][$job['status']] ?? 'secondary';
                    ?>
                    <span class="badge bg-<?php echo $statusClass; ?>">
                      <?php echo ucfirst($job['status']); ?>
                    </span>
                  </dd>

                  <dt class="col-sm-4">Priority:</dt>
                  <dd class="col-sm-8">
                    <span class="badge bg-<?php echo $job['priority'] === 'high' ? 'danger' : ($job['priority'] === 'medium' ? 'warning' : 'info'); ?>">
                      <?php echo ucfirst($job['priority']); ?>
                    </span>
                  </dd>

                  <dt class="col-sm-4">Created:</dt>
                  <dd class="col-sm-8"><?php echo date('Y-m-d H:i:s', strtotime($job['created_at'])); ?></dd>
                </dl>
              </div>
              <div class="col-md-6">
                <dl class="row">
                  <dt class="col-sm-4">Progress:</dt>
                  <dd class="col-sm-8">
                    <?php
                    $progress = $job['total_urls'] > 0 ? round(($job['completed_urls'] + $job['failed_urls']) / $job['total_urls'] * 100) : 0;
                    ?>
                    <div class="progress mb-1" style="height: 20px;">
                      <div class="progress-bar bg-success" style="width: <?php echo $progress; ?>%">
                        <?php echo $progress; ?>%
                      </div>
                    </div>
                    <small class="text-muted">
                      <?php echo ($job['completed_urls'] + $job['failed_urls']); ?> / <?php echo $job['total_urls']; ?> URLs processed
                    </small>
                  </dd>

                  <dt class="col-sm-4">Total Emails:</dt>
                  <dd class="col-sm-8">
                    <span class="fw-bold text-primary"><?php echo number_format($job['total_emails']); ?></span>
                  </dd>

                  <dt class="col-sm-4">Started:</dt>
                  <dd class="col-sm-8">
                    <?php echo $job['started_at'] ? date('Y-m-d H:i:s', strtotime($job['started_at'])) : 'Not started'; ?>
                  </dd>

                  <dt class="col-sm-4">Completed:</dt>
                  <dd class="col-sm-8">
                    <?php echo $job['completed_at'] ? date('Y-m-d H:i:s', strtotime($job['completed_at'])) : 'Not completed'; ?>
                  </dd>
                </dl>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
      <div class="col-md-3">
        <div class="card text-center">
          <div class="card-body">
            <div class="display-6 text-primary"><?php echo number_format($job['total_urls']); ?></div>
            <div class="text-muted">Total URLs</div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center">
          <div class="card-body">
            <div class="display-6 text-success"><?php echo number_format($job['completed_urls']); ?></div>
            <div class="text-muted">Completed</div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center">
          <div class="card-body">
            <div class="display-6 text-danger"><?php echo number_format($job['failed_urls']); ?></div>
            <div class="text-muted">Failed</div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center">
          <div class="card-body">
            <div class="display-6 text-warning"><?php echo number_format($job['processing_urls']); ?></div>
            <div class="text-muted">Processing</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content Tabs -->
    <div class="card">
      <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs" id="detailsTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="urls-tab" data-bs-toggle="tab" data-bs-target="#urls" type="button" role="tab">
              <i class="bi bi-link-45deg me-1"></i>
              URLs (<?php echo number_format($job['total_urls']); ?>)
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs" type="button" role="tab">
              <i class="bi bi-list-ul me-1"></i>
              Activity Log
            </button>
          </li>
        </ul>
      </div>
      <div class="card-body">
        <div class="tab-content" id="detailsTabContent">
          <!-- URLs Tab -->
          <div class="tab-pane fade show active" id="urls" role="tabpanel">
            <?php if (empty($urls)): ?>
              <div class="text-center py-4">
                <i class="bi bi-link-45deg display-4 text-muted"></i>
                <p class="text-muted mt-2">No URLs found for this job.</p>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover">
                  <thead>
                    <tr>
                      <th>URL</th>
                      <th>Status</th>
                      <th>Emails Found</th>
                      <th>Processing Time</th>
                      <th>Last Updated</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($urls as $url): ?>
                      <tr>
                        <td>
                          <a href="<?php echo htmlspecialchars($url['url']); ?>" target="_blank" class="text-decoration-none">
                            <?php echo htmlspecialchars(substr($url['url'], 0, 80)) . (strlen($url['url']) > 80 ? '...' : ''); ?>
                          </a>
                        </td>
                        <td>
                          <?php
                          $statusClass = [
                            'pending' => 'warning',
                            'processing' => 'primary',
                            'completed' => 'success',
                            'failed' => 'danger'
                          ][$url['status']] ?? 'secondary';
                          ?>
                          <span class="badge bg-<?php echo $statusClass; ?>">
                            <?php echo ucfirst($url['status']); ?>
                          </span>
                        </td>
                        <td>
                          <span class="fw-bold"><?php echo $url['email_count']; ?></span>
                          <?php if ($url['emails_sample']): ?>
                            <br>
                            <small class="text-muted" title="<?php echo htmlspecialchars($url['emails_sample']); ?>">
                              <?php echo htmlspecialchars(substr($url['emails_sample'], 0, 50)) . (strlen($url['emails_sample']) > 50 ? '...' : ''); ?>
                            </small>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php
                          if ($url['processing_time']) {
                            echo round($url['processing_time'], 2) . 's';
                          } else {
                            echo '-';
                          }
                          ?>
                        </td>
                        <td>
                          <small><?php echo date('Y-m-d H:i:s', strtotime($url['updated_at'])); ?></small>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <!-- Pagination -->
              <?php if ($totalPages > 1): ?>
                <nav aria-label="URL pagination">
                  <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                      <li class="page-item">
                        <a class="page-link" href="?job_id=<?php echo $jobId; ?>&page=<?php echo $page - 1; ?>">Previous</a>
                      </li>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);

                    for ($i = $start; $i <= $end; $i++):
                    ?>
                      <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?job_id=<?php echo $jobId; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                      </li>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                      <li class="page-item">
                        <a class="page-link" href="?job_id=<?php echo $jobId; ?>&page=<?php echo $page + 1; ?>">Next</a>
                      </li>
                    <?php endif; ?>
                  </ul>
                </nav>
              <?php endif; ?>
            <?php endif; ?>
          </div>

          <!-- Logs Tab -->
          <div class="tab-pane fade" id="logs" role="tabpanel">
            <?php if (empty($logs)): ?>
              <div class="text-center py-4">
                <i class="bi bi-list-ul display-4 text-muted"></i>
                <p class="text-muted mt-2">No activity logs found for this job.</p>
              </div>
            <?php else: ?>
              <div class="timeline">
                <?php foreach ($logs as $log): ?>
                  <div class="card mb-3">
                    <div class="card-body py-2">
                      <div class="d-flex justify-content-between align-items-start">
                        <div>
                          <?php
                          $levelClass = [
                            'info' => 'primary',
                            'warning' => 'warning',
                            'error' => 'danger',
                            'success' => 'success'
                          ][$log['level']] ?? 'secondary';
                          ?>
                          <span class="badge bg-<?php echo $levelClass; ?> me-2"><?php echo strtoupper($log['level']); ?></span>
                          <span><?php echo htmlspecialchars($log['message']); ?></span>
                          <?php if ($log['worker_id']): ?>
                            <small class="text-muted">(Worker: <?php echo $log['worker_id']; ?>)</small>
                          <?php endif; ?>
                        </div>
                        <small class="text-muted">
                          <?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?>
                        </small>
                      </div>
                      <?php if ($log['context_data']): ?>
                        <div class="mt-2">
                          <details>
                            <summary class="text-muted small" style="cursor: pointer;">View Details</summary>
                            <pre class="small mt-2 bg-light p-2 rounded"><?php echo htmlspecialchars($log['context_data']); ?></pre>
                          </details>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap 5 JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Auto-refresh for running jobs -->
  <script>
    <?php if (in_array($job['status'], ['running', 'processing'])): ?>
      // Auto-refresh every 30 seconds for active jobs
      setTimeout(() => {
        location.reload();
      }, 30000);
    <?php endif; ?>
  </script>
</body>

</html>