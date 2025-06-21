<?php

/**
 * Class SimpleXLSX
 * Parse and retrieve data from Excel XLSx files
 */
class SimpleXLSX
{

  public $rows;
  public $header;
  public static $error = false;

  public function __construct() {}

  public static function parse($filename)
  {
    $xlsx = new self();

    if (!file_exists($filename) || !is_readable($filename)) {
      self::$error = 'File not found or not readable';
      return false;
    }

    // For this implementation, we'll use a simple approach
    // In a production environment, you'd want to use a more robust library
    try {
      // This is a simplified implementation
      // You would typically use PhpSpreadsheet or similar
      $xlsx->rows = $xlsx->parseXLSXFile($filename);
      return $xlsx;
    } catch (Exception $e) {
      self::$error = $e->getMessage();
      return false;
    }
  }

  public static function parseError()
  {
    return self::$error;
  }

  public function rows()
  {
    return $this->rows;
  }

  private function parseXLSXFile($filename)
  {
    // This is a basic implementation
    // In production, use PhpSpreadsheet or similar robust library

    $zip = new ZipArchive();
    if ($zip->open($filename) === TRUE) {
      $xml_string = $zip->getFromName('xl/sharedStrings.xml');
      $xml_string_worksheet = $zip->getFromName('xl/worksheets/sheet1.xml');
      $zip->close();

      if ($xml_string && $xml_string_worksheet) {
        return $this->parseWorksheet($xml_string_worksheet, $xml_string);
      }
    }

    throw new Exception('Could not parse XLSX file');
  }

  private function parseWorksheet($worksheet_xml, $shared_strings_xml)
  {
    // Basic XML parsing for XLSX
    // This is a simplified version - use PhpSpreadsheet for production

    $shared_strings = [];
    if ($shared_strings_xml) {
      $xml = simplexml_load_string($shared_strings_xml);
      foreach ($xml->si as $si) {
        $shared_strings[] = (string)$si->t;
      }
    }

    $rows = [];
    $xml = simplexml_load_string($worksheet_xml);

    if (!$xml) {
      throw new Exception('Invalid worksheet XML');
    }

    foreach ($xml->sheetData->row as $row) {
      $row_data = [];
      foreach ($row->c as $cell) {
        $value = '';
        if (isset($cell->v)) {
          $value = (string)$cell->v;
          // If it's a shared string reference
          if (isset($cell['t']) && (string)$cell['t'] === 's') {
            $value = isset($shared_strings[(int)$value]) ? $shared_strings[(int)$value] : '';
          }
        }
        $row_data[] = $value;
      }
      $rows[] = $row_data;
    }

    return $rows;
  }
}
