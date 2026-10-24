<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Club;
use App\Entity\Member;
use App\Entity\MembershipApplication;
use App\Entity\MembershipEvent;
use App\Entity\MembershipMessage;
use App\Entity\MembershipPolicy;
use App\Entity\Person;
use App\Entity\User;
use App\Enum\MembershipStatus;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class MembershipService
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly LoggerInterface $logger) {}

    public function policy(Club $club): ?MembershipPolicy
    {
        return $this->em->getRepository(MembershipPolicy::class)->findOneBy(['club' => $club]);
    }

    public function submit(MembershipApplication $application): void
    {
        $policy = $this->policy($application->getClub());
        if ($policy === null || !$policy->isApplicationsEnabled()) {
            throw new \DomainException('Dieser Verein nimmt derzeit keine Online-Anträge entgegen.');
        }
        $policy->validateRules();
        $this->em->wrapInTransaction(function () use ($application, $policy): void {
            $this->em->persist($application);
            $this->em->flush();
            $this->queue('application:' . $application->getId(), $application->getEmail(), 'Mitgliedsantrag eingegangen', 'Ihr Antrag ist eingegangen. Die Verwaltung prüft Ihre Identität und entscheidet über die Aufnahme. Es besteht noch keine Mitgliedschaft.');
            $this->queue('application-admin:' . $application->getId(), (string) $policy->getContactEmail(), 'Neuer Mitgliedsantrag', 'Ein neuer Antrag wartet in der Mitgliederverwaltung auf manuelle Identitätsprüfung und Entscheidung.');
        });
    }

    public function approve(MembershipApplication $application, ?Person $existing, string $actor, string $note, bool $separateIdentityConfirmed = false): Member
    {
        if ($application->getStatus() !== 'pending') {
            throw new \DomainException('Dieser Antrag wurde bereits entschieden.');
        }
        if ($application->getBirthday() === null) {
            throw new \DomainException('Ein vollständiger Antrag mit Geburtsdatum ist erforderlich.');
        }
        if (trim($note) === '' || mb_strlen($note) > 1000) {
            throw new \DomainException('Bitte die manuelle Identitätsprüfung dokumentieren (maximal 1.000 Zeichen).');
        }
        if ($existing === null && !$separateIdentityConfirmed && $this->em->getRepository(Person::class)->findOneBy(['email' => $application->getEmail()]) !== null) {
            throw new \DomainException('Zu dieser Kontaktadresse existiert eine zentrale Person. Ein Superadmin muss diese nach Identitätsprüfung zuordnen oder ausdrücklich eine andere Person mit gemeinsamer Kontaktadresse bestätigen. Es wurde keine zweite Identität angelegt.');
        }
        $person = $existing ?? (new Person())
            ->setFirstname($application->getFirstname())->setLastname($application->getLastname())
            ->setBirthday(\DateTime::createFromImmutable($application->getBirthday()))
            ->setEmail($application->getEmail())->setStreet($application->getStreet())
            ->setPostalCode($application->getPostalCode())->setCity($application->getCity());
        if ($existing !== null && $this->em->getRepository(Member::class)->findOneBy(['person' => $person, 'club' => $application->getClub()]) !== null) {
            throw new \DomainException('Die geprüfte Person hat bereits eine Mitgliedschaft in diesem Verein.');
        }
        return $this->em->wrapInTransaction(function () use ($application, $person, $actor, $note): Member {
            $member = (new Member())->setPerson($person)->setClub($application->getClub())
                ->setMemberNumber('M-' . strtoupper(bin2hex(random_bytes(6))))
                ->setJoinedAt(new \DateTime())->setMembershipStatus(MembershipStatus::ACTIVE);
            $account = $person->getId() !== null ? $this->em->getRepository(User::class)->findOneBy(['person' => $person]) : null;
            if ($account !== null && $account->getMember() === null) {
                $member->setUser($account);
            }
            $this->em->persist($member);
            $application->decide($member, $actor, $note);
            $this->record($member, 'approved', $actor, 'Aufnahme nach manueller Identitätsprüfung.');
            $this->queue('application-approved:' . $application->getId(), $application->getEmail(), 'Mitgliedsantrag genehmigt', 'Ihr Mitgliedsantrag wurde genehmigt. Bitte wenden Sie sich für die Kontozuordnung an die Vereinsverwaltung.');
            return $member;
        });
    }

    public function reject(MembershipApplication $application, string $actor, string $note): void
    {
        $this->em->wrapInTransaction(function () use ($application, $actor, $note): void {
            $application->decide(null, $actor, $note);
            $this->queue('application-rejected:' . $application->getId(), $application->getEmail(), 'Entscheidung zum Mitgliedsantrag', 'Ihr Mitgliedsantrag wurde nicht genehmigt. Für Rückfragen wenden Sie sich bitte an die Vereinsverwaltung.');
        });
    }

    public function requestTermination(Member $member, string $actor, \DateTimeImmutable $received): void
    {
        if ($member->getClub() === null || $member->getArchivedAt() !== null || $member->getLeftAt() !== null
            || $member->getMembershipStatus() === MembershipStatus::FORMER || $member->getTerminationDate() !== null) {
            throw new \DomainException('Für diese Mitgliedschaft kann keine weitere Kündigung erfasst werden.');
        }
        $policy = $this->policy($member->getClub());
        if ($policy === null) {
            throw new \DomainException('Der Verein hat noch keine Kündigungsregel hinterlegt.');
        }
        $date = $policy->cancellationDate($received);
        $this->em->wrapInTransaction(function () use ($member, $date, $actor): void {
            $member->setTerminationDate($date);
            $this->record($member, 'termination_requested', $actor, 'Kündigung zum ' . $date->format('d.m.Y') . '; Mitgliedsrechte bleiben bis zum wirksamen Austritt erhalten.');
        });
    }

    public function changeStatus(Member $member, MembershipStatus $status, string $actor): void
    {
        if ($member->getArchivedAt() !== null || $member->getMembershipStatus() === MembershipStatus::FORMER || $status === MembershipStatus::FORMER) {
            throw new \DomainException('Archivierte/beendete Mitgliedschaften nicht umstellen. Austritte über den Kündigungsprozess erfassen.');
        }
        $this->em->wrapInTransaction(function () use ($member, $status, $actor): void {
            $old = $member->getMembershipStatus()->label();
            $member->setMembershipStatus($status);
            $this->record($member, 'status_changed', $actor, $old . ' → ' . $status->label());
        });
    }

    public function archive(Member $member, string $actor): void
    {
        if ($member->getMembershipStatus() !== MembershipStatus::FORMER || $member->getArchivedAt() !== null) {
            throw new \DomainException('Nur ausgetretene, noch nicht archivierte Mitgliedschaften können archiviert werden.');
        }
        $this->em->wrapInTransaction(function () use ($member, $actor): void {
            $member->setArchivedAt(new \DateTimeImmutable());
            $this->record($member, 'archived', $actor, 'Mitgliedschaft archiviert; Identität, Dokumente und Forderungen bleiben erhalten.');
        });
    }

    public function record(Member $member, string $kind, string $actor, string $description): void
    {
        $this->em->persist(new MembershipEvent($member, $kind, $actor, $description));
        $recipient = $member->getPerson()->getEmail() ?: $member->getUser()?->getEmail();
        if ($recipient !== null && filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->queue('event:' . bin2hex(random_bytes(16)), $recipient, 'Änderung Ihrer Vereinsmitgliedschaft', $description);
        } else {
            $this->logger->warning('Mitgliedschaftsvorgang ohne zustellbare Kontaktadresse.', ['member_id' => $member->getId(), 'kind' => $kind]);
        }
    }

    public function queue(string $key, string $recipient, string $subject, string $body): void
    {
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new \DomainException('Keine gültige Empfängeradresse für die Benachrichtigung.');
        }
        if ($this->em->getRepository(MembershipMessage::class)->findOneBy(['messageKey' => $key]) === null) {
            $this->em->persist(new MembershipMessage($key, $recipient, $subject, $body));
        }
    }
}
