<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/** Minimaler OOXML-Export ohne zusätzliche Composer-Abhängigkeiten. */
final class TuvXlsxExporter
{
    /** @param array<int, array<string, mixed>> $rows
     *  @param array{club: int, member: int} $counts
     */
    public function download(array $rows, array $counts, int $netCents, int $grossCents, string $filename): BinaryFileResponse
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('PHP-Erweiterung zip (ZipArchive) wird für XLSX benötigt.');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'dicoma_xlsx_');
        if ($tmp === false) {
            throw new \RuntimeException('Temporäre XLSX-Datei konnte nicht angelegt werden.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::OVERWRITE | \ZipArchive::CREATE) !== true) {
            @unlink($tmp);
            throw new \RuntimeException('XLSX-Archiv konnte nicht angelegt werden.');
        }
        try {
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="TÜV-Sammelliste" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
            $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00&quot; €&quot;"/></numFmts><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"/><xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs></styleSheet>');
            $sheet = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cols><col min="1" max="1" width="13" customWidth="1"/><col min="2" max="2" width="19" customWidth="1"/><col min="3" max="3" width="28" customWidth="1"/><col min="4" max="5" width="21" customWidth="1"/><col min="6" max="6" width="18" customWidth="1"/><col min="7" max="8" width="16" customWidth="1"/><col min="9" max="10" width="24" customWidth="1"/></cols><sheetData>';
            $sheet .= self::row(1, ['Anmeldung', 'Flaschentyp', 'Flasche', 'Größe', 'Seriennummer', 'Status', 'Netto EUR', 'Brutto EUR', 'Übergabe', 'Bemerkung'], true);
            $i = 2;
            foreach ($rows as $r) {
                $sheet .= self::row($i++, [
                    (int) $r['id'],
                    $r['type'] === 'club' ? 'Verein' : 'Mitglied',
                    (string) $r['tank'],
                    (string) $r['size'],
                    (string) $r['serial'],
                    (string) $r['status'],
                    ['money' => (int) $r['netCents']],
                    ['money' => (int) $r['grossCents']],
                    '', '',
                ]);
            }
            $sheet .= self::row($i + 1, ['Gesamt', 'Verein: '.$counts['club'].' / Mitglied: '.$counts['member'], '', '', '', '', ['money' => $netCents], ['money' => $grossCents], '', ''], true);
            $sheet .= '</sheetData></worksheet>';
            $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        } finally {
            $zip->close();
        }
        $response = new BinaryFileResponse($tmp);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->deleteFileAfterSend(true);
        return $response;
    }

    /** @param array<int, string|int|array{money: int}> $values */
    private static function row(int $index, array $values, bool $bold = false): string
    {
        $xml = '<row r="'.$index.'">';
        foreach ($values as $column => $value) {
            $ref = self::column($column + 1).$index;
            if (is_array($value)) {
                $xml .= '<c r="'.$ref.'" s="2"><v>'.number_format($value['money'] / 100, 2, '.', '').'</v></c>';
            } elseif (is_int($value)) {
                $xml .= '<c r="'.$ref.'"><v>'.$value.'</v></c>';
            } else {
                // Inline strings werden nie als Excel-Formeln ausgewertet.
                $safe = htmlspecialchars(self::cleanXml($value), ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $xml .= '<c r="'.$ref.'" t="inlineStr"'.($bold ? ' s="1"' : '').'><is><t xml:space="preserve">'.$safe.'</t></is></c>';
            }
        }
        return $xml.'</row>';
    }

    private static function cleanXml(string $text): string
    {
        return preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text) ?? '';
    }

    private static function column(int $n): string
    {
        $label = '';
        while ($n > 0) {
            --$n;
            $label = chr(65 + $n % 26).$label;
            $n = intdiv($n, 26);
        }
        return $label;
    }
}
