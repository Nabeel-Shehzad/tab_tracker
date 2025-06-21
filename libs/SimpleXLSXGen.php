<?php

/**
 * Class SimpleXLSXGen
 * Generate Excel XLSX files
 */
class SimpleXLSXGen
{

  private $data;

  public function __construct($data = [])
  {
    $this->data = $data;
  }

  public static function fromArray($data)
  {
    return new self($data);
  }

  public function saveAs($filename)
  {
    try {
      // This is a basic implementation
      // In production, use PhpSpreadsheet or similar robust library

      $zip = new ZipArchive();
      $temp_file = tempnam(sys_get_temp_dir(), 'xlsx');

      if ($zip->open($temp_file, ZipArchive::CREATE) !== TRUE) {
        return false;
      }

      // Add required files
      $this->addContentTypes($zip);
      $this->addRels($zip);
      $this->addApp($zip);
      $this->addCore($zip);
      $this->addWorkbookRels($zip);
      $this->addWorkbook($zip);
      $this->addWorksheet($zip);
      $this->addStyles($zip);

      $zip->close();

      // Copy temp file to destination
      $result = copy($temp_file, $filename);
      unlink($temp_file);

      return $result;
    } catch (Exception $e) {
      return false;
    }
  }

  private function addContentTypes($zip)
  {
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
</Types>';
    $zip->addFromString('[Content_Types].xml', $xml);
  }

  private function addRels($zip)
  {
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>';
    $zip->addFromString('_rels/.rels', $xml);
  }

  private function addApp($zip)
  {
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
<Application>Employee Tracker</Application>
<DocSecurity>0</DocSecurity>
<ScaleCrop>false</ScaleCrop>
<Company>Employee Tracker System</Company>
<LinksUpToDate>false</LinksUpToDate>
<SharedDoc>false</SharedDoc>
<HyperlinksChanged>false</HyperlinksChanged>
<AppVersion>1.0</AppVersion>
</Properties>';
    $zip->addFromString('docProps/app.xml', $xml);
  }

  private function addCore($zip)
  {
    $created = date('Y-m-d\TH:i:s\Z');
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
<dc:creator>Employee Tracker</dc:creator>
<cp:lastModifiedBy>Employee Tracker</cp:lastModifiedBy>
<dcterms:created xsi:type="dcterms:W3CDTF">' . $created . '</dcterms:created>
<dcterms:modified xsi:type="dcterms:W3CDTF">' . $created . '</dcterms:modified>
</cp:coreProperties>';
    $zip->addFromString('docProps/core.xml', $xml);
  }

  private function addWorkbookRels($zip)
  {
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';
    $zip->addFromString('xl/_rels/workbook.xml.rels', $xml);
  }

  private function addWorkbook($zip)
  {
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets>
<sheet name="Sheet1" sheetId="1" r:id="rId1"/>
</sheets>
</workbook>';
    $zip->addFromString('xl/workbook.xml', $xml);
  }

  private function addWorksheet($zip)
  {
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheetData>';

    $row_num = 1;
    foreach ($this->data as $row) {
      $xml .= '<row r="' . $row_num . '">';
      $col_num = 0;
      foreach ($row as $cell) {
        $col_letter = $this->columnLetter($col_num);
        $cell_ref = $col_letter . $row_num;
        $xml .= '<c r="' . $cell_ref . '" t="inlineStr"><is><t>' . htmlspecialchars($cell) . '</t></is></c>';
        $col_num++;
      }
      $xml .= '</row>';
      $row_num++;
    }

    $xml .= '</sheetData>
</worksheet>';
    $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
  }

  private function addStyles($zip)
  {
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<fonts count="1">
<font>
<sz val="11"/>
<color theme="1"/>
<name val="Calibri"/>
<family val="2"/>
<scheme val="minor"/>
</font>
</fonts>
<fills count="2">
<fill>
<patternFill patternType="none"/>
</fill>
<fill>
<patternFill patternType="gray125"/>
</fill>
</fills>
<borders count="1">
<border>
<left/>
<right/>
<top/>
<bottom/>
<diagonal/>
</border>
</borders>
<cellStyleXfs count="1">
<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
</cellStyleXfs>
<cellXfs count="1">
<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
</cellXfs>
<cellStyles count="1">
<cellStyle name="Normal" xfId="0" builtinId="0"/>
</cellStyles>
</styleSheet>';
    $zip->addFromString('xl/styles.xml', $xml);
  }

  private function columnLetter($index)
  {
    $letters = '';
    while ($index >= 0) {
      $letters = chr(65 + ($index % 26)) . $letters;
      $index = intval($index / 26) - 1;
    }
    return $letters;
  }
}
