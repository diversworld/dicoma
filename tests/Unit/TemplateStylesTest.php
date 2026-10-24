<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class TemplateStylesTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    public function testTemplatesOnlySupplyRequiredDynamicCssValues(): void
    {
        $allowedProperties = [
            '--dw-progress', '--dw-calendar-hour-offset',
            '--dw-event-top', '--dw-event-height', '--dw-event-left', '--dw-event-width',
        ];
        foreach ($this->files('templates', 'twig') as $path) {
            $source = file_get_contents($path);
            self::assertDoesNotMatchRegularExpression('/<style\b/i', $source, $path);
            preg_match_all("/\\bstyle\\s*=\\s*([\"'])(.*?)\\1/i", $source, $styles);
            foreach ($styles[2] as $style) {
                foreach (explode(';', $style) as $declaration) {
                    [$property, $value] = explode(':', $declaration, 2);
                    self::assertContains(trim($property), $allowedProperties, $path);
                    self::assertStringContainsString('{{', $value, $path);
                    self::assertDoesNotMatchRegularExpression('/#[\da-f]{3,8}\b/i', $value, $path);
                }
            }
            preg_match_all("/asset\\('css\\/([^']+)'\\)/", $source, $links);
            foreach ($links[1] as $link) {
                self::assertFileExists(self::ROOT . '/public/css/' . $link, $path);
            }
            if (str_contains($source, '<html')) {
                self::assertStringContainsString("asset('css/theme-variables.css')", $source, $path);
                self::assertStringContainsString("asset('css/utilities.css')", $source, $path);
            }
        }
    }

    public function testApplicationColorsAreCentralizedAndVariablesHaveNoMissingTargets(): void
    {
        $sources = [];
        $declared = ['--dw-progress', '--dw-calendar-hour-offset', '--dw-event-top', '--dw-event-height', '--dw-event-left', '--dw-event-width'];
        foreach ($this->files('public/css', 'css') as $path) {
            $source = preg_replace('~/\*.*?\*/~s', '', file_get_contents($path));
            $sources[$path] = $source;
            preg_match_all('/(--[\w-]+)\s*:/', $source, $definitions);
            $declared = [...$declared, ...$definitions[1]];
            self::assertDoesNotMatchRegularExpression('/(--[\w-]+)\s*:\s*var\(\1\)/', $source, $path);
            if (basename($path) !== 'theme-variables.css') {
                self::assertDoesNotMatchRegularExpression('/#[\da-f]{3,8}\b|\brgba?\(/i', $source, $path);
                self::assertDoesNotMatchRegularExpression('/:\s*(white|black|red|blue|green)\b/', $source, $path);
            }
        }
        foreach ($sources as $path => $source) {
            preg_match_all('/var\((--dw-[\w-]+)/', $source, $references);
            foreach (array_unique($references[1]) as $reference) {
                self::assertContains($reference, $declared, $path);
            }
        }
        $controller = file_get_contents(self::ROOT . '/src/Controller/Admin/DashboardController.php');
        self::assertStringContainsString("addCssFile('css/theme-variables.css')", $controller);
        self::assertStringContainsString("addCssFile('css/utilities.css')", $controller);
        foreach (['base.html.twig', 'equipment_manager/layout.html.twig', 'instructor_portal/layout.html.twig'] as $layout) {
            self::assertStringContainsString("asset('css/bootstrap-theme.css')", file_get_contents(self::ROOT . '/templates/' . $layout));
        }
    }

    /** @return list<string> */
    private function files(string $directory, string $extension): array
    {
        $paths = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::ROOT . '/' . $directory));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === $extension) {
                $paths[] = $file->getPathname();
            }
        }
        return $paths;
    }
}
