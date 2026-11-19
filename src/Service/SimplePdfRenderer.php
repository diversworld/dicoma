<?php

declare(strict_types=1);

namespace App\Service;

final class SimplePdfRenderer
{
    public function render(string $title, array $lines): string
    {
        $safe = static fn (string $value): string => str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\(', '\)'],
            (string) iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $value),
        );
        $content = "BT\n/F1 16 Tf\n50 790 Td\n(".$safe($title).") Tj\n/F1 10 Tf\n";
        $lineNumber = 0;
        foreach (array_merge([''], $lines) as $line) {
            foreach (str_split($safe($line), 95) ?: [''] as $wrapped) {
                $content .= "\n0 -17 Td\n(".$wrapped.") Tj";
                if (++$lineNumber > 42) {
                    break 2;
                }
            }
        }
        $content .= "\nET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Length '.strlen($content)." >>\nstream\n".$content."\nendstream",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        for ($index = 1; $index <= count($objects); ++$index) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$index]);
        }
        return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
    }
}
