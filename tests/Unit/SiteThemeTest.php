<?php

declare(strict_types=1);
namespace App\Tests\Unit;

use App\Service\SiteTheme;
use PHPUnit\Framework\TestCase;

final class SiteThemeTest extends TestCase
{
    public function testAnotherThemeCanBeSelectedWithoutChangingControllers(): void
    {
        $theme = new SiteTheme('alternative', ['airspace' => ['layout' => 'themes/airspace/layout.html.twig', 'stylesheets' => []],
            'alternative' => ['layout' => 'themes/alternative/layout.html.twig', 'stylesheets' => ['themes/alternative/style.css']]]);
        self::assertSame('alternative', $theme->getName());
        self::assertSame('themes/alternative/layout.html.twig', $theme->getLayout());
        self::assertSame(['themes/alternative/style.css'], $theme->getStylesheets());
        $twig = new \Twig\Environment(new \Twig\Loader\ArrayLoader([
            'base.html.twig' => file_get_contents(dirname(__DIR__, 2).'/templates/base.html.twig'),
            'themes/alternative/layout.html.twig' => '<main data-theme="alternative">{% block body %}{% endblock %}</main>',
            'page.html.twig' => '{% extends "base.html.twig" %}{% block body %}Existing page{% endblock %}',
        ]));
        $twig->addGlobal('site_theme', $theme);
        self::assertSame('<main data-theme="alternative">Existing page</main>', $twig->render('page.html.twig'));
    }

    public function testUnknownThemeIsAnExplicitConfigurationFailure(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SiteTheme('../unknown', []);
    }
}
