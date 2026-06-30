<?php
declare(strict_types=1);

namespace App\Helpers;

/**
 * XlsxWriter — minimal, dependency-free XLSX generator.
 *
 * Writes a single-sheet workbook using PHP's built-in ZipArchive.
 * No composer packages required (PhpSpreadsheet not available in this env).
 *
 * Usage:
 *   $w = new XlsxWriter(['Period','Tenant','Amount']);
 *   $w->addRow(['Jun 2025', 'John Doe', 5000]);
 *   $w->save('/path/to/file.xlsx');
 */
class XlsxWriter
{
    private array $headers;
    private array $rows = [];

    public function __construct(array $headers)
    {
        $this->headers = $headers;
    }

    public function addRow(array $row): void
    {
        $this->rows[] = $row;
    }

    public function save(string $path): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Could not create XLSX at {$path}");
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->relsXml());
        $zip->addFromString('docProps/app.xml', $this->appXml());
        $zip->addFromString('docProps/core.xml', $this->coreXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetXml());

        $zip->close();
    }

    // ─── Sheet data ──────────────────────────────────────────────────────────

    private function sheetXml(): string
    {
        $rowsXml = '';
        $rowIdx  = 1;

        // Header row (bold style index 1)
        $rowsXml .= $this->rowXml($rowIdx++, $this->headers, true);

        foreach ($this->rows as $row) {
            $rowsXml .= $this->rowXml($rowIdx++, $row, false);
        }

        $colCount = count($this->headers);
        $lastCol  = $this->colLetter($colCount);
        $dim      = "A1:{$lastCol}{$rowIdx}";

        $colsXml = '';
        for ($i = 1; $i <= $colCount; $i++) {
            $colsXml .= '<col min="' . $i . '" max="' . $i . '" width="20" customWidth="1"/>';
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<dimension ref="{$dim}"/>
<sheetViews><sheetView workbookViewId="0"/></sheetViews>
<sheetFormatPr defaultRowHeight="15"/>
<cols>{$colsXml}</cols>
<sheetData>{$rowsXml}</sheetData>
</worksheet>
XML;
    }

    private function rowXml(int $rowIdx, array $cells, bool $isHeader): string
    {
        $cellsXml = '';
        $colIdx   = 1;
        foreach ($cells as $value) {
            $ref = $this->colLetter($colIdx) . $rowIdx;
            $cellsXml .= $this->cellXml($ref, $value, $isHeader);
            $colIdx++;
        }
        return "<row r=\"{$rowIdx}\">{$cellsXml}</row>";
    }

    private function cellXml(string $ref, mixed $value, bool $isHeader): string
    {
        $style = $isHeader ? ' s="1"' : '';

        if (is_numeric($value) && !is_string($value)) {
            return "<c r=\"{$ref}\"{$style}><v>" . $value . "</v></c>";
        }
        if (is_numeric($value) && is_string($value) && !preg_match('/^0[0-9]/', $value)) {
            // numeric-looking strings (but preserve leading-zero strings like phone numbers)
            return "<c r=\"{$ref}\"{$style}><v>" . $value . "</v></c>";
        }

        $escaped = htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        return "<c r=\"{$ref}\" t=\"inlineStr\"{$style}><is><t xml:space=\"preserve\">{$escaped}</t></is></c>";
    }

    private function colLetter(int $index): string
    {
        $letter = '';
        while ($index > 0) {
            $rem    = ($index - 1) % 26;
            $letter = chr(65 + $rem) . $letter;
            $index  = intdiv($index - 1, 26);
        }
        return $letter;
    }

    // ─── Boilerplate XML parts ─────────────────────────────────────────────

    private function contentTypesXml(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
</Types>
XML;
    }

    private function relsXml(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>
XML;
    }

    private function workbookXml(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets>
</workbook>
XML;
    }

    private function workbookRelsXml(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML;
    }

    private function stylesXml(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<fonts count="2">
<font><sz val="11"/><name val="Calibri"/></font>
<font><b/><sz val="11"/><name val="Calibri"/><color rgb="FFFFFFFF"/></font>
</fonts>
<fills count="2">
<fill><patternFill patternType="none"/></fill>
<fill><patternFill patternType="solid"><fgColor rgb="FF0F6E56"/><bgColor indexed="64"/></patternFill></fill>
</fills>
<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>
<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
<cellXfs count="2">
<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
<xf numFmtId="0" fontId="1" fillId="1" borderId="0" xfId="0" applyFont="1" applyFill="1"/>
</cellXfs>
</styleSheet>
XML;
    }

    private function coreXml(): string
    {
        $now = date('Y-m-d\TH:i:s\Z');
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
<dc:creator>RentOps</dc:creator>
<dcterms:created xsi:type="dcterms:W3CDTF">{$now}</dcterms:created>
</cp:coreProperties>
XML;
    }

    private function appXml(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">
<Application>RentOps</Application>
</Properties>
XML;
    }
}
