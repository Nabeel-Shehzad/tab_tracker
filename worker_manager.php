<?php

/**
 * Scraping Worker Manager
 * Manages multiple worker processes for high-performance scraping
 * 
 * @author Employee Tracker System
 * @version 1.0
 * @created September 24, 2025
 */

require_once __DIR__ . '/libs/ScrapingWorker.php';

class WorkerManager
{
  private $workers = [];
  private $running = false;
  private $maxWorkers;
  private $redis;

  public function __construct($maxWorkers = null)
  {
    $this->maxWorkers = $maxWorkers ?: SCRAPING_MAX_WORKERS;
    $this->redis = getRedisConnection();

    // Register signal handlers
    if (function_exists('pcntl_signal')) {
      pcntl_signal(SIGTERM, [$this, 'shutdown']);
      pcntl_signal(SIGINT, [$this, 'shutdown']);
      pcntl_signal(SIGCHLD, [$this, 'handleChildExit']);
    }

    echo "Worker Manager initialized with {$this->maxWorkers} max workers\n";
  }

  /**
   * Start the worker manager
   */
  public function start()
  {
    $this->running = true;

    echo "Starting Worker Manager...\n";

    // Start initial workers
    $this->startWorkers();

    // Main management loop
    while ($this->running) {
      // Check worker health
      $this->checkWorkers();

      // Maintain worker count
      $this->maintainWorkers();

      // Handle signals
      if (function_exists('pcntl_signal_dispatch')) {
        pcntl_signal_dispatch();
      }

      // Wait before next check
      sleep(10);
    }

    $this->shutdown();
  }

  /**
   * Start initial worker processes
   */
  private function startWorkers()
  {
    for ($i = 1; $i <= $this->maxWorkers; $i++) {
      $this->startWorker($i);
    }
  }

  /**
   * Start a single worker
   */
  private function startWorker($workerId = null)
  {
    $workerId = $workerId ?: uniqid('worker_');

    if (function_exists('pcntl_fork')) {
      // Use forking on Unix-like systems
      $pid = pcntl_fork();

      if ($pid === -1) {
        echo "Failed to fork worker process\n";
        return false;
      } elseif ($pid === 0) {
        // Child process - become worker
        $worker = new ScrapingWorker($workerId);
        $worker->start();
        exit(0);
      } else {
        // Parent process - track worker
        $this->workers[$workerId] = [
          'pid' => $pid,
          'started_at' => time(),
          'status' => 'running'
        ];

        echo "Started worker {$workerId} with PID {$pid}\n";
        return true;
      }
    } else {
      // Fallback for Windows - use proc_open
      return $this->startWorkerProc($workerId);
    }
  }

  /**
   * Start worker using proc_open (Windows compatible)
   */
  private function startWorkerProc($workerId)
  {
    $cmd = 'php "' . __DIR__ . '/libs/ScrapingWorker.php" ' . escapeshellarg($workerId);

    $descriptors = [
      0 => ['pipe', 'r'],
      1 => ['pipe', 'w'],
      2 => ['pipe', 'w']
    ];

    $process = proc_open($cmd, $descriptors, $pipes);

    if (is_resource($process)) {
      // Close pipes
      fclose($pipes[0]);
      fclose($pipes[1]);
      fclose($pipes[2]);

      $status = proc_get_status($process);

      $this->workers[$workerId] = [
        'pid' => $status['pid'],
        'process' => $process,
        'started_at' => time(),
        'status' => 'running'
      ];

      echo "Started worker {$workerId} with PID {$status['pid']}\n";
      return true;
    }

    echo "Failed to start worker {$workerId}\n";
    return false;
  }

  /**
   * Check worker health and status
   */
  private function checkWorkers()
  {
    foreach ($this->workers as $workerId => $worker) {
      if (isset($worker['process'])) {
        // Check proc_open process
        $status = proc_get_status($worker['process']);

        if (!$status['running']) {
          echo "Worker {$workerId} (PID {$worker['pid']}) has stopped\n";
          proc_close($worker['process']);
          unset($this->workers[$workerId]);
        }
      } elseif (function_exists('posix_kill') && !posix_kill($worker['pid'], 0)) {
        // Check forked process
        echo "Worker {$workerId} (PID {$worker['pid']}) is no longer running\n";
        unset($this->workers[$workerId]);
      }
    }
  }

  /**
   * Maintain the desired number of workers
   */
  private function maintainWorkers()
  {
    $activeWorkers = count($this->workers);

    if ($activeWorkers < $this->maxWorkers) {
      $needed = $this->maxWorkers - $activeWorkers;
      echo "Need {$needed} more workers, starting them...\n";

      for ($i = 0; $i < $needed; $i++) {
        $this->startWorker();
        sleep(1); // Stagger starts
      }
    }
  }

  /**
   * Handle child process exit
   */
  public function handleChildExit($signal)
  {
    if (function_exists('pcntl_waitpid')) {
      while (($pid = pcntl_waitpid(-1, $status, WNOHANG)) > 0) {
        // Find worker by PID
        foreach ($this->workers as $workerId => $worker) {
          if ($worker['pid'] === $pid) {
            echo "Worker {$workerId} (PID {$pid}) exited with status {$status}\n";
            unset($this->workers[$workerId]);
            break;
          }
        }
      }
    }
  }

  /**
   * Get worker status
   */
  public function getWorkerStatus()
  {
    $status = [
      'active_workers' => count($this->workers),
      'max_workers' => $this->maxWorkers,
      'workers' => []
    ];

    foreach ($this->workers as $workerId => $worker) {
      $status['workers'][$workerId] = [
        'pid' => $worker['pid'],
        'started_at' => $worker['started_at'],
        'uptime' => time() - $worker['started_at'],
        'status' => $worker['status']
      ];
    }

    // Add Redis stats if available
    if ($this->redis) {
      $activeFromRedis = $this->redis->smembers('active_workers');
      $status['redis_active'] = count($activeFromRedis);

      foreach ($activeFromRedis as $workerId) {
        $workerStats = $this->redis->hgetall("worker:{$workerId}");
        if ($workerStats) {
          $status['workers'][$workerId]['stats'] = $workerStats;
        }
      }
    }

    return $status;
  }

  /**
   * Stop a specific worker
   */
  public function stopWorker($workerId)
  {
    if (!isset($this->workers[$workerId])) {
      return false;
    }

    $worker = $this->workers[$workerId];

    if (isset($worker['process'])) {
      // Terminate proc_open process
      proc_terminate($worker['process']);
      proc_close($worker['process']);
    } elseif (function_exists('posix_kill')) {
      // Send SIGTERM to forked process
      posix_kill($worker['pid'], SIGTERM);
    }

    unset($this->workers[$workerId]);
    echo "Stopped worker {$workerId}\n";

    return true;
  }

  /**
   * Restart a specific worker
   */
  public function restartWorker($workerId)
  {
    $this->stopWorker($workerId);
    sleep(2);
    return $this->startWorker($workerId);
  }

  /**
   * Shutdown all workers gracefully
   */
  public function shutdown($signal = null)
  {
    echo "Shutting down Worker Manager" . ($signal ? " (signal: {$signal})" : "") . "...\n";

    $this->running = false;

    // Stop all workers
    foreach (array_keys($this->workers) as $workerId) {
      $this->stopWorker($workerId);
    }

    // Wait for workers to stop
    sleep(3);

    // Force kill if necessary
    foreach ($this->workers as $workerId => $worker) {
      if (function_exists('posix_kill')) {
        posix_kill($worker['pid'], SIGKILL);
      }
    }

    echo "Worker Manager shutdown complete\n";
    exit(0);
  }
}

// Command line interface
if (php_sapi_name() === 'cli') {
  $command = $argv[1] ?? 'start';

  switch ($command) {
    case 'start':
      $maxWorkers = isset($argv[2]) ? (int)$argv[2] : null;
      $manager = new WorkerManager($maxWorkers);
      $manager->start();
      break;

    case 'status':
      $manager = new WorkerManager();
      $status = $manager->getWorkerStatus();
      echo "Worker Status:\n";
      echo "Active Workers: {$status['active_workers']}/{$status['max_workers']}\n";

      if (!empty($status['workers'])) {
        echo "\nWorker Details:\n";
        foreach ($status['workers'] as $id => $worker) {
          echo "  {$id}: PID {$worker['pid']}, Uptime: {$worker['uptime']}s\n";
        }
      }
      break;

    default:
      echo "Usage: php worker_manager.php [start|status] [max_workers]\n";
      break;
  }
}
