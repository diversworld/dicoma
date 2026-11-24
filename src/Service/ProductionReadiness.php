<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Club;
use App\Entity\ProductionApproval;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ProductionReadiness
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DocumentMalwareScanner $scanner,
        #[Autowire('%kernel.environment%')] private readonly string $environment,
    ) {}

    /** @return list<string> */
    public function blockers(Club $club): array
    {
        $approval = $this->em->getRepository(ProductionApproval::class)->findOneBy(['club' => $club]);
        $blockers = [];
        if ($approval?->getSepaApprovedAt() === null) { $blockers[] = 'Bankfreigabe des pain.008-Profils fehlt.'; }
        if ($approval?->getChartApprovedAt() === null) { $blockers[] = 'Steuerberater-Freigabe der internen SKR42-Unterkonten fehlt.'; }
        if (!$this->scanner->isConfigured()) { $blockers[] = 'ClamAV/Malware-Scanner ist nicht konfiguriert oder nicht ausführbar.'; }
        if ($approval?->getRetentionApprovedAt() === null) { $blockers[] = 'Fachliche Freigabe der Aufbewahrungsfristen fehlt.'; }
        if ($approval?->getWcagApprovedAt() === null) { $blockers[] = 'Dokumentierte manuelle WCAG-2.2-AA-Abnahme fehlt.'; }
        return $blockers;
    }

    public function requireApproved(Club $club, string $area): void
    {
        if ($this->environment !== 'prod') {
            return;
        }
        $approval = $this->em->getRepository(ProductionApproval::class)->findOneBy(['club' => $club]);
        $approved = match ($area) {
            'sepa' => $approval?->getSepaApprovedAt() !== null,
            'chart' => $approval?->getChartApprovedAt() !== null,
            'retention' => $approval?->getRetentionApprovedAt() !== null,
            'wcag' => $approval?->getWcagApprovedAt() !== null,
            default => throw new \LogicException('Unbekannter Freigabebereich.'),
        };
        if (!$approved) {
            throw new \DomainException('Produktivfreigabe fehlt. Bitte den Nachweis im Bereich Audit und Datenschutz dokumentieren.');
        }
    }
}
