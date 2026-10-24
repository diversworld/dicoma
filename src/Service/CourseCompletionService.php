<?php

namespace App\Service;

use App\Entity\CourseParticipant;
use App\Entity\MemberQualification;
use App\Enum\CourseParticipantStatus;
use App\Repository\MemberQualificationRepository;
use App\Service\TrainingProgressService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CourseCompletionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MemberQualificationRepository $memberQualificationRepository,
        private readonly TrainingProgressService $trainingProgressService,
        private readonly TranslatorInterface $translator,
        private readonly CourseAuditLogger $audit,
        private readonly CourseArchiveService $archive,
    ) {
    }

    /** Guests receive the participant certificate without a synthetic MemberQualification. */
    public function pass(
        CourseParticipant $participant,
        ?\DateTimeInterface $completedAt = null,
        ?string $brevetNumber = null
    ): ?MemberQualification {
        if ($brevetNumber !== null && (trim($brevetNumber) === '' || mb_strlen($brevetNumber) > 120)) {
            throw new \DomainException('Die Brevetnummer muss zwischen 1 und 120 Zeichen lang sein.');
        }
        return $this->entityManager->getConnection()->transactional(
            function () use (
                $participant,
                $completedAt,
                $brevetNumber
            ): ?MemberQualification {
                $course = $participant->getCourse();
                if ($course === null || $course->getId() === null || $participant->getId() === null) {
                    throw new \LogicException('Der Abschluss benötigt eine gespeicherte Kursteilnahme.');
                }
                $this->entityManager->lock($course, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
                $this->entityManager->refresh($course);
                $this->entityManager->refresh($participant);
                $before = $participant->getStatus()->value;
                $result = $this->completeParticipant($participant, $completedAt, $brevetNumber);
                $courseId = $participant->getCourse()?->getId();
                if ($courseId !== null) {
                    $this->audit->record($courseId, $participant->getId(), 'participant_passed', $before, CourseParticipantStatus::PASSED->value);
                }
                $this->entityManager->flush();
                return $result;
            }
        );
    }

    private function completeParticipant(
        CourseParticipant $participant,
        ?\DateTimeInterface $completedAt,
        ?string $brevetNumber
    ): ?MemberQualification {
        $course = $participant->getCourse();
        $member = $participant->getMember();

        if ($course === null) {
            throw new \LogicException(
                'Der Kursteilnehmer ist keinem Kurs zugeordnet.'
            );
        }
        if ($member === null && $participant->getPerson() !== null && $course->getClub() !== null) {
            $member = $this->entityManager->getRepository(\App\Entity\Member::class)->findOneBy([
                'person' => $participant->getPerson(), 'club' => $course->getClub(),
            ]);
        }

        if (!in_array($participant->getStatus(), [CourseParticipantStatus::REGISTERED, CourseParticipantStatus::ACTIVE], true)
            || $course->getStatus() === \App\Enum\CourseStatus::COMPLETED || $participant->getArchivedAt() !== null) {
            throw new \LogicException(
                'Dieser Teilnehmer kann nicht abgeschlossen werden.'
            );
        }

        // Zentrale fachliche Prüfung: auch bei direktem Service-Aufruf.
        // Ohne definierte Pflichtanforderungen wird nicht automatisch bestanden.
        $progress = $this->trainingProgressService->calculate($participant);
        if (!$progress['complete']) {
            $missing = [];
            foreach ($progress['requirements'] as $requirement) {
                if ($requirement['required'] && $requirement['missing'] > 0) {
                    $missing[] = $this->translator->trans('completion.missing_unit', [
                        '%unit%' => $requirement['name'],
                        '%count%' => $requirement['missing'],
                    ]);
                }
            }
            throw new \LogicException(
                $missing === []
                    ? 'Der Kursabschluss ist gesperrt: Es sind keine vollständigen Pflichtanforderungen hinterlegt.'
                    : $this->translator->trans('completion.missing_requirements', ['%requirements%' => implode(', ', $missing)])
            );
        }

        $qualification = $course->getQualification();

        if ($qualification === null) {
            throw new \LogicException(
                'Dem Kurs ist keine Zielqualifikation zugeordnet.'
            );
        }

        /*
         * Mandantentrennung.
         */
        $courseClub = $course->getClub();
        $memberClub = $member?->getClub();

        if (
            $courseClub !== null
            && $memberClub !== null
            && $courseClub !== $memberClub
        ) {
            throw new \LogicException(
                'Kurs und Teilnehmer gehören nicht demselben Verein an.'
            );
        }

        $qualificationClub = $qualification->getClub();

        if (
            $qualificationClub !== null
            && $qualificationClub !== $courseClub
        ) {
            throw new \LogicException(
                'Die Zielqualifikation gehört zu einem anderen Verein.'
            );
        }

        $completionDate = $completedAt !== null
            ? \DateTimeImmutable::createFromInterface(
                $completedAt
            )
            : new \DateTimeImmutable('today');

        $certificateNumber = sprintf('DICOMA-%d-%d', $course->getId(), $participant->getId());
        if ($member === null) {
            $participant->setStatus(CourseParticipantStatus::PASSED)->setCompletedAt($completionDate);
            $participant->archiveTraining($this->archive->snapshot($participant) + ['brevetNumber' => $brevetNumber], $certificateNumber);
            return null;
        }
        $participant->setMember($member);

        /*
         * Vorhandene Qualifikation suchen.
         */
        $memberQualification = $this
            ->memberQualificationRepository
            ->findOneBy([
                'member' => $member,
                'qualification' => $qualification,
            ]);

        if ($memberQualification === null) {
            $memberQualification =
                new MemberQualification();

            $memberQualification
                ->setMember($member)
                ->setQualification($qualification);

            $this->entityManager->persist(
                $memberQualification
            );
        }

        /*
         * Nachweis aktualisieren.
         */
        $memberQualification->setIssuedAt(
            $completionDate
        );
        if ($brevetNumber !== null) { $memberQualification->setCertificateNumber($brevetNumber); }

        if ($qualification->getIssuer() !== null) {
            $memberQualification->setIssuer(
                $qualification->getIssuer()
            );
        }

        $validityMonths =
            $qualification->getValidityMonths();

        if (
            $validityMonths !== null
            && $validityMonths > 0
        ) {
            $memberQualification->setValidUntil(
                $completionDate->modify(
                    sprintf(
                        '+%d months',
                        $validityMonths
                    )
                )
            );
        } else {
            $memberQualification->setValidUntil(null);
        }

        /*
         * Automatisch erzeugte Nachweise gelten zunächst
         * nicht als manuell geprüft.
         */
        $memberQualification->setVerified(false);

        /*
         * Teilnehmer erst innerhalb derselben Transaktion
         * als bestanden markieren.
         */
        $participant
            ->setStatus(
                CourseParticipantStatus::PASSED
            )
            ->setCompletedAt(
                $completionDate
            );
        $participant->archiveTraining($this->archive->snapshot($participant) + ['brevetNumber' => $brevetNumber ?? $memberQualification->getCertificateNumber()], $certificateNumber);

        return $memberQualification;
    }
}