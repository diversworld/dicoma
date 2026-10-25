<?php

declare(strict_types=1);

namespace App\Service;

final class SiteTheme
{
    public function __construct(private readonly string $name, private readonly array $themes)
    {
        if (!isset($themes[$name]['layout'], $themes[$name]['stylesheets'])) {
            throw new \InvalidArgumentException('Unknown or incomplete SITE_THEME: '.$name);
        }
    }

    public function getName(): string { return $this->name; }
    public function getLayout(): string { return $this->themes[$this->name]['layout']; }
    public function getStylesheets(): array { return $this->themes[$this->name]['stylesheets']; }
}
