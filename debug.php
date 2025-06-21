<?php
require_once 'common/config_mysql.php';

echo "=== Database Connection Test ===" . PHP_EOL;
try {
  $db = getDB();
  echo "Database connected successfully!" . PHP_EOL;

  // Check reports table structure
  echo PHP_EOL . "=== Reports Table Structure ===" . PHP_EOL;
  $stmt = $db->query("DESCRIBE reports");
  while ($row = $stmt->fetch()) {
    echo $row['Field'] . " | " . $row['Type'] . " | " . ($row['Null'] == 'YES' ? 'NULL' : 'NOT NULL') . " | Default: " . $row['Default'] . PHP_EOL;
  }

  // Check current reports
  echo PHP_EOL . "=== Current Reports ===" . PHP_EOL;
  $stmt = $db->query("SELECT id, employee_name, original_filename, decrypted_filename, upload_date, file_size, row_count FROM reports ORDER BY upload_date DESC LIMIT 5");
  while ($row = $stmt->fetch()) {
    echo "ID: {$row['id']} | Employee: {$row['employee_name']} | Original: {$row['original_filename']} | Decrypted: {$row['decrypted_filename']} | Size: {$row['file_size']} | Rows: {$row['row_count']} | Date: {$row['upload_date']}" . PHP_EOL;
  }

  // Check if ZipArchive is available
  echo PHP_EOL . "=== PHP Extensions ===" . PHP_EOL;
  echo "ZipArchive available: " . (class_exists('ZipArchive') ? 'Yes' : 'No') . PHP_EOL;

  if (class_exists('ZipArchive')) {
    // Test the XLSX file
    $file = 'admin/decrypted/2025-06-21_18-59-57_Khizr_report.xlsx';
    if (file_exists($file)) {
      echo PHP_EOL . "=== XLSX File Test ===" . PHP_EOL;
      echo "File: $file" . PHP_EOL;
      echo "Size: " . filesize($file) . " bytes" . PHP_EOL;

      $zip = new ZipArchive();
      if ($zip->open($file) === TRUE) {
        echo "ZIP opened successfully, files: " . $zip->numFiles . PHP_EOL;

        // List key files
        for ($i = 0; $i < $zip->numFiles; $i++) {
          $filename = $zip->getNameIndex($i);
          if (strpos($filename, 'worksheet') !== false || strpos($filename, 'sharedStrings') !== false) {
            echo "Found: $filename" . PHP_EOL;
          }
        }
        $zip->close();
      } else {
        echo "Failed to open as ZIP" . PHP_EOL;
      }
    }
  }
} catch (Exception $e) {
  echo "Error: " . $e->getMessage() . PHP_EOL;
}
