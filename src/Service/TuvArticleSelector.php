<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\MemberTank;
use App\Entity\Tank;
use App\Entity\TankCheck;
use App\Entity\TankCheckArticle;

final class TuvArticleSelector
{
    public function volume(MemberTank $tank): ?float
    {
        $capacity = $tank->getWaterCapacityLiters();
        if ($capacity !== null && is_numeric($capacity)) {
            $liters = (float) $capacity;
            return $liters > 0 && $liters <= 50 ? $liters : null;
        }

        // Legacy liter-labelled tanks may use their nominal volume as capacity.
        // For cuft cylinders never estimate the water capacity from the gas volume.
        $raw = trim((string) $tank->getVolume());
        if (!preg_match('/^(\d{1,2})(?:[.,](\d+))?\s*(?:l|liter)?$/iuD', $raw, $match)) {
            return null;
        }
        // Allow trailing unit while retaining decimal fraction.
        $liters = (float) ($match[1] . (isset($match[2]) ? '.' . $match[2] : ''));
        return $liters > 0 && $liters <= 50 ? $liters : null;
    }

    public function isCuft(MemberTank $tank): bool
    {
        return preg_match('/^(40|80)\s*cu\s*ft$/iuD', trim((string) $tank->getVolume())) === 1;
    }

    private function matches(TankCheckArticle $article, ?float $volume, ?string $cuft): bool
    {
        $rule = $article->getVolumeRule();
        if ($rule === 'any') {
            return true;
        }
        $size = $article->getSize();
        // 40/80 are reserved for cuft tariffs in the current admin size list.
        // Never compare these numerically against liter volumes.
        if ($cuft !== null) {
            return $rule === 'exact' && (string) $size === $cuft;
        }
        if (in_array((string) $size, ['40', '80'], true)) {
            return false;
        }
        if ($volume === null || $size === null || !is_numeric($size) || (float) $size <= 0) {
            return false;
        }
        $limit = (float) $size;
        return match ($rule) {
            'exact' => (float) $volume === $limit,
            'up_to' => $volume <= $limit,
            'from' => $volume >= $limit,
            default => false,
        };
    }

    /**
     * Returns articles indexed by database ID.
     * All size-independent mandatory services are included; exactly one best
     * matching size-dependent mandatory tariff is selected.
     *
     * @return array<int, TankCheckArticle>
     */
    public function eligible(TankCheck $check, MemberTank $tank): array
    {
        $volume = $this->volume($tank);
        // Prefer actual stamped water capacity for liter-based price tiers.
        // Only use legacy cuft-specific tariffs if capacity has not been entered.
        $cuft = $volume === null && $this->isCuft($tank) ? (str_starts_with(trim((string) $tank->getVolume()), '40') ? '40' : '80') : null;
        return $this->eligibleForDimensions($check, $volume, $cuft);
    }

    /** Vereinsflaschen verwenden ihre hinterlegte Größe in Litern. */
    public function eligibleForClubTank(TankCheck $check, Tank $tank): array
    {
        $size = $tank->getSize();
        if ($size === null || $size <= 0 || $size > 50 || in_array($size, [40, 80], true)) {
            throw new \DomainException('Für diese Vereinsflasche ist keine eindeutige Wasserraumgröße hinterlegt (40/80 sind als cuft-Tarife reserviert).');
        }
        return $this->eligibleForDimensions($check, (float) $size, null);
    }

    /** @return array<int, TankCheckArticle> */
    private function eligibleForDimensions(TankCheck $check, ?float $volume, ?string $cuft): array
    {
        $result = [];
        $requiredTariffs = [];

        foreach ($check->getArticles() as $article) {
            if (!$article instanceof TankCheckArticle || $article->getId() === null || !$this->matches($article, $volume, $cuft)) {
                continue;
            }

            if ($article->getSelectionMode() !== 'required') {
                $result[$article->getId()] = $article;
                continue;
            }

            // General mandatory services (e.g. transport) must not compete
            // with the size-specific inspection tariff.
            if ($article->getVolumeRule() === 'any') {
                $result[$article->getId()] = $article;
                continue;
            }

            $requiredTariffs[] = $article;
        }

        if ($requiredTariffs !== []) {
            $scored = [];
            foreach ($requiredTariffs as $article) {
                $rule = $article->getVolumeRule();
                $size = (float) ($article->getSize() ?? 0);
                $score = match ($rule) {
                    'exact' => [0, 0],
                    'up_to' => [1, $size],
                    'from' => [2, -$size],
                    default => [3, 0],
                };
                $scored[] = ['article' => $article, 'score' => $score];
            }

            usort($scored, static fn (array $a, array $b): int => $a['score'] <=> $b['score']);
            if (isset($scored[1]) && $scored[0]['score'] === $scored[1]['score']) {
                throw new \DomainException('Mehrere gleich passende Pflichtpreise für diese Flaschengröße. Bitte TÜV-Artikel im Adminbereich korrigieren.');
            }

            $winner = $scored[0]['article'];
            $result[$winner->getId()] = $winner;
        }

        return $result;
    }

    /**
     * @param array<int, TankCheckArticle> $eligible
     * @param array<mixed> $posted
     * @return array<int, TankCheckArticle>
     */
    public function effectiveMode(TankCheckArticle $article): string
    {
        return $article->getSelectionMode() === 'required' ? 'required' : 'optional';
    }

    public function selected(array $eligible, array $posted): array
    {
        $ids = [];
        foreach ($posted as $value) {
            if (!is_string($value) || !ctype_digit($value)) {
                throw new \DomainException('Ungültige Artikelwahl.');
            }
            $id = (int) $value;
            if (!isset($eligible[$id])) {
                throw new \DomainException('Artikel passt nicht zur Flasche oder zum Termin.');
            }
            $ids[$id] = true;
        }

        // Disabled checkboxes are not sent by browsers: always include every
        // eligible mandatory article on the server.
        foreach ($eligible as $id => $article) {
            if ($article->getSelectionMode() === 'required') {
                $ids[$id] = true;
            }
        }

        if ($ids === []) {
            throw new \DomainException('Bitte mindestens eine Leistung wählen.');
        }
        return array_intersect_key($eligible, $ids);
    }
}
