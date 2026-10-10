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
    ) {
    }

    public function pass(
        CourseParticipant $participant,
        ?\DateTimeInterface $completedAt = null
    ): MemberQualification {
        return $this->entityManager->wrapInTransaction(
            function () use (
                $participant,
                $completedAt
            ): MemberQualification {
                return $this->completeParticipant(
                    $participant,
                    $completedAt
                );
            }
        );
    }

    private function completeParticipant(
        CourseParticipant $participant,
        ?\DateTimeInterface $completedAt
    ): MemberQualification {
        $course = $participant->getCourse();
        $member = $participant->getMember();

        if ($course === null) {
            throw new \LogicException(
                'Der Kursteilnehmer ist keinem Kurs zugeordnet.'
            );
        }

        if ($member === null) {
            throw new \LogicException(
                'Dem Kursteilnehmer ist kein Mitglied zugeordnet.'
            );
        }

        if ($participant->hasPassed()) {
            throw new \LogicException(
                'Der Teilnehmer hat diesen Kurs bereits bestanden.'
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
        $memberClub = $member->getClub();

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
            && $memberClub !== null
            && $qualificationClub !== $memberClub
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

        /*
         * Kein explizites flush() notwendig:
         * wrapInTransaction() führt beim erfolgreichen Ende
         * den Flush/Commit aus.
         */

        return $memberQualification;
    }
}