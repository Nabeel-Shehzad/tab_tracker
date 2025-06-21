<?php
require_once 'libs/SimpleXLSX.php';

$file = 'admin/decrypted/2025-06-21_18-59-57_Khizr_report.xlsx';
echo 'Checking file: ' . $file . PHP_EOL;
echo 'File exists: ' . (file_exists($file) ? 'Yes' : 'No') . PHP_EOL;

if (file_exists($file)) {
  echo 'File size: ' . filesize($file) . ' bytes' . PHP_EOL;

  // Check if it's a valid ZIP (XLSX is a ZIP archive)
  $zip = new ZipArchive();
  $result = $zip->open($file);
  if ($result === TRUE) {
    echo 'ZIP archive opened successfully' . PHP_EOL;
    echo 'Number of files in ZIP: ' . $zip->numFiles . PHP_EOL;

    // List some files in the ZIP
    for ($i = 0; $i < min(5, $zip->numFiles); $i++) {
      echo 'File ' . $i . ': ' . $zip->getNameIndex($i) . PHP_EOL;
    }
    $zip->close();
  } else {
    echo 'Failed to open as ZIP archive. Error code: ' . $result . PHP_EOL;
  }

  // Test with SimpleXLSX
  $xlsx = SimpleXLSX::parse($file);
  if ($xlsx) {
    $rows = $xlsx->rows();
    echo 'Rows found: ' . (is_array($rows) ? count($rows) : 'Not an array') . PHP_EOL;
    if (is_array($rows) && count($rows) > 0) {
      echo 'First few rows:' . PHP_EOL;
      for ($i = 0; $i < min(3, count($rows)); $i++) {
        echo 'Row ' . ($i + 1) . ': ';
        print_r($rows[$i]);
      }
    }
  } else {
    echo 'SimpleXLSX parse error: ' . SimpleXLSX::parseError() . PHP_EOL;
  }
}
