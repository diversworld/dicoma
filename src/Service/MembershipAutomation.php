<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Member;
use App\Entity\MemberDocument;
use App\Entity\MemberQualification;
use App\Enum\MembershipStatus;
use App\Enum\QualificationType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class MembershipAutomation
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MembershipService $memberships,
        private readonly LoggerInterface $logger,
    ) {}

    public function run(\DateTimeImmutable $today): int
    {
        $processed = 0;
        foreach ($this->em->getRepository(Member::class)->findBy(['archivedAt' => null]) as $member) {
            $this->em->wrapInTransaction(function () use ($member, $today, &$processed): void {
                $termination = $member->getTerminationDate();
                if ($termination !== null && $termination < $today && $member->getLeftAt() === null) {
                    $member->setMembershipStatus(MembershipStatus::FORMER);
                    $member->setLeftAt(\DateTime::createFromImmutable($termination));
                    $this->memberships->record($member, 'terminated', 'system', 'Mitgliedschaft zum ' . $termination->format('d.m.Y') . ' beendet.');
                    ++$processed;
                }
                $payer = $member->getFamilyPayer();
                if ($payer !== null && $member->getMembershipStatus() !== MembershipStatus::FORMER
                    && ($payer->getArchivedAt() !== null || $payer->getMembershipStatus() === MembershipStatus::FORMER || $payer->getLeftAt() !== null)) {
                    $contact = $member->getClub() !== null ? $this->memberships->policy($member->getClub())?->getContactEmail() : null;
                    $this->logger->warning('Familienzahler ausgeschieden; Beitragszuordnung muss geprüft werden.', ['member_id' => $member->getId(), 'payer_id' => $payer->getId()]);
                    if ($contact !== null) {
                        $this->memberships->queue('family-payer:' . $member->getId() . ':' . $payer->getId() . ':' . $today->format('Y-m'), $contact, 'Familienbeitrag neu zuordnen', 'Der Familienzahler für Mitglied ' . $member->getMemberNumber() . ' ist ausgeschieden. Bitte die Beitragszuordnung prüfen; es wurde keine automatische Einzelbeitragsforderung erstellt.');
                    }
                }
                if ($member->getMembershipStatus() === MembershipStatus::FORMER || $member->getClub() === null) {
                    return;
                }
                $recipient = $member->getPerson()->getEmail() ?: $member->getUser()?->getEmail();
                if ($recipient === null || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    $this->logger->warning('Mitgliedererinnerung ohne zustellbare Kontaktadresse.', ['member_id' => $member->getId()]);
                    return;
                }
                $proofs = $this->em->getRepository(MemberQualification::class)->createQueryBuilder('mq')
                    ->join('mq.qualification', 'q')->where('mq.member = :member')
                    ->andWhere('q.type = :type')->andWhere('mq.verified = true')
                    ->setParameter('member', $member)->setParameter('type', QualificationType::MEDICAL_FITNESS->value)
                    ->orderBy('mq.validUntil', 'DESC')->getQuery()->getResult();
                $validUntil = null;
                foreach ($proofs as $proof) {
                    if ($proof->getValidUntil() === null) {
                        $validUntil = null;
                        break;
                    }
                    if ($validUntil === null || $proof->getValidUntil() > $validUntil) { $validUntil = $proof->getValidUntil(); }
                }
                if ($validUntil !== null && $validUntil <= $today->modify('+30 days')) {
                    $this->memberships->queue('medical:' . $member->getId() . ':' . $validUntil->format('Y-m-d'), $recipient, 'Tauchtauglichkeit prüfen', 'Ihr hinterlegter Tauchtauglichkeitsnachweis läuft am ' . $validUntil->format('d.m.Y') . ' ab oder ist bereits abgelaufen. Bitte wenden Sie sich an die Vereinsverwaltung.');
                }
                $policy = $this->memberships->policy($member->getClub());
                if ($policy !== null) {
                    $documents = $this->em->getRepository(MemberDocument::class)->findBy(['member' => $member]);
                    $present = array_map(static fn (MemberDocument $document) => $document->getKind(), $documents);
                    $missing = array_diff($policy->getRequiredDocuments(), $present);
                    if ($missing !== []) {
                        $labels = array_flip(MemberDocument::kinds());
                        $this->memberships->queue('documents:' . $member->getId() . ':' . $today->format('Y-m'), $recipient, 'Fehlende Mitgliedsunterlagen', 'Bitte reichen Sie folgende Unterlagen im privaten Mitgliederbereich nach: ' . implode(', ', array_map(static fn ($kind) => $labels[$kind], $missing)) . '.');
                    }
                }
            });
        }
        return $processed;
    }
}
