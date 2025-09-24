<?php

/**
 * SimpleXLSXGen Stub Class
 * Provides type definitions for development environment
 * 
 * @author Employee Tracker System
 * @version 1.0
 * @created September 24, 2025
 */

namespace Shuchkin;

/**
 * SimpleXLSXGen stub class for development environments
 * This provides type definitions when the actual library is not available
 */
class SimpleXLSXGen
{

  /**
   * Create XLSX from array data
   * @param array $data
   * @return SimpleXLSXGen
   */
  public static function fromArray($data)
  {
    return new self();
  }

  /**
   * Output XLSX file
   * @return void
   */
  public function output()
  {
    throw new \Exception('SimpleXLSXGen library not available - please install the library for XLSX export functionality');
  }

  /**
   * Save XLSX to file
   * @param string $filename
   * @return bool
   */
  public function saveAs($filename)
  {
    throw new \Exception('SimpleXLSXGen library not available - please install the library for XLSX export functionality');
  }
}
