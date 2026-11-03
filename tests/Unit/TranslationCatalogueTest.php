<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Validation;

final class TranslationCatalogueTest extends TestCase
{
    public function testMissingValidatorMessagesAreAvailableInEveryLocale(): void
    {
        $keys = [
            'Mindestens einen Ausrüstungsartikel auswählen.',
            'Für dieses Mitglied existiert bereits eine Buchung für diesen Termin.',
            'Bitte eine Ausbildungseinheit auswählen.',
            'Jeder Verleihartikel ist ein einzelner Inventargegenstand.',
            'Jeder Inventargegenstand hat genau ein Exemplar.',
            'Bitte eine Person oder ein Mitglied für die Teilnahme zuordnen.',
            'Dieses Mitglied gehört bereits zum Kader.',
            'Diese Sparte existiert in diesem Verein bereits.',
            'Der Dateiname darf keine Pfade oder Steuerzeichen enthalten.',
            'Diese Person hat bereits eine Mitgliedschaft in diesem Verein.',
            'Diese Mitgliedsnummer ist im Verein bereits vergeben.',
            'Diese Mannschaft existiert bereits in dieser Sportart.',
        ];
        $translator = new Translator('de');
        $translator->addLoader('array', new ArrayLoader());
        foreach (['de', 'en', 'fr', 'es'] as $locale) {
            $catalogue = require dirname(__DIR__, 2).'/translations/validators.'.$locale.'.php';
            $translator->addResource('array', $catalogue, $locale, 'validators');
            foreach ($keys as $key) {
                self::assertArrayHasKey($key, $catalogue);
                self::assertNotSame('', $catalogue[$key]);
                if ($locale !== 'de') {
                    self::assertNotSame($key, $catalogue[$key]);
                }
            }
        }

        $validator = Validation::createValidatorBuilder()->setTranslator($translator)
            ->setTranslationDomain('validators')->getValidator();
        foreach (['de', 'en', 'fr', 'es'] as $locale) {
            $translator->setLocale($locale);
            $violations = $validator->validate([], new Count(min: 1, minMessage: $keys[0]));
            self::assertCount(1, $violations);
            self::assertSame($translator->trans($keys[0], [], 'validators', $locale), $violations[0]->getMessage());
        }
    }
}
