<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$failures = [];
$templates = [
    'templates/admin/accounting/index.html.twig',
    'templates/admin/privacy/index.html.twig',
    'templates/admin/communication/index.html.twig',
    'templates/membership/member.html.twig',
];
foreach ($templates as $template) {
    $source = file_get_contents($root.'/'.$template);
    if ($source === false) { $failures[] = $template.' missing'; continue; }
    preg_match_all('/<(input|select|textarea)\b/i', $source, $controls);
    preg_match_all('/<label\b/i', $source, $labels);
    $hidden = substr_count(strtolower($source), 'type="hidden"');
    if (count($labels[0]) < max(0, count($controls[0]) - $hidden)) {
        $failures[] = $template.' has fewer labels than visible controls';
    }
}
$css = file_get_contents($root.'/public/css/accessibility.css');
if ($css === false || !str_contains($css, 'outline: 3px solid #8a4b08')) {
    $failures[] = 'visible high-contrast focus style missing';
}
if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures).PHP_EOL);
    exit(1);
}
