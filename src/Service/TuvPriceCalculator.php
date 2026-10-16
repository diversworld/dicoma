<?php

declare(strict_types=1);
namespace App\Service;

final class TuvPriceCalculator
{
    public const VAT_PERCENT = 19;

    public static function cents(?string $value): ?int
    {
        if ($value === null || trim($value) === '') return null;
        $value = str_replace(',', '.', trim($value));
        if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $value)) throw new \DomainException('Ungültiger Geldbetrag.');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        return (int)$whole * 100 + (int)str_pad($fraction, 2, '0');
    }

    public static function format(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }

    /** The caller specifies which input the administrator edited. */
    public static function pair(?string $net, ?string $gross, string $basis): array
    {
        if (!in_array($basis, ['net', 'gross'], true)) throw new \DomainException('Preisgrundlage fehlt.');
        $input = self::cents($basis === 'net' ? $net : $gross);
        if ($input === null) throw new \DomainException('Bitte einen Netto- oder Bruttopreis eingeben.');
        if ($basis === 'net') {
            $netCents = $input;
            $grossCents = intdiv($netCents * 119 + 50, 100);
        } else {
            $grossCents = $input;
            $netCents = intdiv($grossCents * 100 + 59, 119);
        }
        return ['net' => self::format($netCents), 'gross' => self::format($grossCents)];
    }

    public static function fromArticle(\App\Entity\TankCheckArticle $article): array
    {
        $net = $article->getPriceNetto();
        $gross = $article->getPriceBrutto();
        // For migrated legacy articles prefer net, which was historically the source.
        return self::pair($net, $gross, $net !== null && $net !== '' ? 'net' : 'gross');
    }
}
