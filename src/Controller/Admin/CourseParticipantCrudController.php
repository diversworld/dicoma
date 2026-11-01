<?php

namespace App\Controller\Admin;

use App\Entity\CourseParticipant;
use App\Entity\Courses;
use App\Entity\Member;
use Doctrine\ORM\EntityRepository;
use App\Service\AdminClubContext;
use App\Security\Voter\CourseManagementVoter;
use App\Security\Voter\MemberManagementVoter;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use Symfony\Component\Translation\TranslatableMessage;
use App\Enum\CourseParticipantStatus;
use App\Enum\CourseStatus;
use App\Service\CourseCompletionService;
use App\Service\ScheduleParticipantSyncService;
use App\Repository\ScheduleRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class CourseParticipantCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly CourseCompletionService $courseCompletionService,
        private readonly UrlGeneratorInterface $router,
        private readonly ScheduleRepository $scheduleRepository,
        private readonly ScheduleParticipantSyncService $participantSyncService,
        private readonly AdminClubContext $clubContext
    ) {
    }

    /** Schritt 37: SQL-basierter Vereinsfilter fuer Teilnehmerlisten. */
    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $clubId = $this->clubContext->getClub()?->getId();
        if ($clubId === null) {
            $qb->andWhere('1 = 0');
        } else {
            $qb->join('entity.course', 'managedParticipantCourse')
                ->leftJoin('entity.member', 'managedParticipantMember')
                ->andWhere('managedParticipantCourse.club = :participantManagedClub')
                ->andWhere('(entity.member IS NULL OR managedParticipantMember.club = :participantManagedClub)')
                ->setParameter('participantManagedClub', $clubId);
        }
        return $qb;
    }

    /** Schritte 38-40: Objektberechtigung und Zuordnungspruefung. */
    private function assertParticipantAccess(CourseParticipant $participant): void
    {
        $course = $participant->getCourse();
        if ($course === null) {
            throw $this->createAccessDeniedException('Kurszuordnung fehlt.');
        }
        $this->denyAccessUnlessGranted(CourseManagementVoter::MANAGE, $course);
        $clubId = $this->clubContext->getClub()?->getId();
        if ($clubId === null || $course->getClub()?->getId() !== $clubId) {
            throw $this->createAccessDeniedException('Kurs gehört nicht zum ausgewählten Verein.');
        }
        $member = $participant->getMember();
        if ($member === null) {
            if ($participant->getPerson() === null) {
                throw $this->createAccessDeniedException('Personenzuordnung fehlt.');
            }
            return;
        }
        $this->denyAccessUnlessGranted(MemberManagementVoter::MANAGE, $member);
        if ($member->getClub()?->getId() !== $clubId) {
            throw $this->createAccessDeniedException('Mitglied gehört nicht zum ausgewählten Verein.');
        }
    }

    public static function getEntityFqcn(): string
    {
        return CourseParticipant::class;
    }

    public function batchDelete(AdminContext $context, BatchActionDto $batchActionDto): Response
    {
        if ($batchActionDto->getEntityFqcn() !== CourseParticipant::class) {
            throw new BadRequestHttpException('Ungültiger Teilnehmer-Stapel.');
        }
        $em = $this->container->get('doctrine')->getManagerForClass(CourseParticipant::class);
        foreach ($batchActionDto->getEntityIds() as $id) {
            if ((!is_string($id) && !is_int($id)) || !ctype_digit((string) $id) || (int) $id < 1) {
                throw new BadRequestHttpException('Ungültige Teilnehmer-ID.');
            }
            $participant = $em->find(CourseParticipant::class, (int) $id);
            if ($participant !== null) {
                $this->assertParticipantAccess($participant);
                if ($participant->hasPassed() || $participant->getArchivedAt() !== null
                    || $participant->getCourse()?->getStatus() === CourseStatus::COMPLETED) {
                    throw $this->createAccessDeniedException('Archivierte Teilnahmen oder Teilnahmen abgeschlossener Kurse dürfen nicht gelöscht werden.');
                }
            }
        }
        return parent::batchDelete($context, $batchActionDto);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setFormOptions(['csrf_token_id' => 'participant_crud_'.($this->clubContext->getClub()?->getId() ?? 'none')])
            ->setEntityLabelInSingular('Kursteilnehmer')
            ->setEntityLabelInPlural('Kursteilnehmer')
            ->setPageTitle(
                Crud::PAGE_INDEX,
                'Kursteilnehmer'
            )
            ->setPageTitle(
                Crud::PAGE_NEW,
                'Kursteilnehmer anlegen'
            )
            ->setPageTitle(
                Crud::PAGE_EDIT,
                'Kursteilnehmer bearbeiten'
            )
            ->setPageTitle(
                Crud::PAGE_DETAIL,
                'Kursteilnehmer'
            )
            ->setDefaultSort([
                'registeredAt' => 'DESC',
            ])
            ->setSearchFields([
                'person.firstname',
                'person.lastname',
                'member.memberNumber',
                'course.title',
            ]);
    }

    public function configureFields(string $pageName): iterable
    {
        if ($pageName === Crud::PAGE_EDIT || $pageName === Crud::PAGE_DETAIL) {
            $participant = $this->getContext()?->getEntity()?->getInstance();
            if ($participant instanceof CourseParticipant) {
                $this->assertParticipantAccess($participant);
            }
        }

        // Auswahlfilter sind UI-Schutz; die Voter in persist/update/delete
        // bleiben die verbindliche serverseitige Zugriffskontrolle.
        $isForm = $pageName === Crud::PAGE_NEW || $pageName === Crud::PAGE_EDIT;
        $clubId = $this->clubContext->getClub()?->getId();

        $courseField = AssociationField::new('course', 'Kurs');
        $memberField = AssociationField::new('member', 'Teilnehmer');
        if ($isForm) {
            $courseField->setFormTypeOption('query_builder', static function (EntityRepository $repository) use ($clubId): QueryBuilder {
                $qb = $repository->createQueryBuilder('courseChoice');
                return $clubId === null
                    ? $qb->andWhere('1 = 0')
                    : $qb->andWhere('courseChoice.club = :choiceClub')->setParameter('choiceClub', $clubId);
            });
            $memberField->setFormTypeOption('query_builder', static function (EntityRepository $repository) use ($clubId): QueryBuilder {
                $qb = $repository->createQueryBuilder('memberChoice');
                return $clubId === null
                    ? $qb->andWhere('1 = 0')
                    : $qb->andWhere('memberChoice.club = :choiceClub')->setParameter('choiceClub', $clubId);
            });
            if ($pageName === Crud::PAGE_EDIT) {
                // Bestehende Zuordnungen duerfen nicht durch Formularmanipulation
                // umgehaengt werden; updateEntity() prueft Original und Ziel.
                $courseField->setFormTypeOption('disabled', true);
                $memberField->setFormTypeOption('disabled', true);
            }
        }
        yield $courseField;
        yield $memberField->setRequired(false);
        yield TextField::new('fullName', 'Teilnehmername')->hideOnForm();

        /*
         * In der Übersicht zeigen wir die deutsche
         * Statusbezeichnung an.
         */
        if ($pageName === Crud::PAGE_INDEX) {
            yield TextField::new(
                'statusLabel',
                'Status'
            );
        } else {
            $statusChoices = CourseParticipantStatus::choices();

            $participant = $this
                ->getContext()
                ?->getEntity()
                ?->getInstance();

            $isAlreadyPassed =
                $participant instanceof CourseParticipant
                && $participant->hasPassed();

            /*
             * PASSED darf nicht manuell gesetzt werden.
             *
             * Bei einem bereits bestandenen Teilnehmer muss der
             * gespeicherte Wert aber weiterhin dargestellt werden.
             */
            if (!$isAlreadyPassed) {
                unset($statusChoices['Bestanden']);
            }

            yield ChoiceField::new(
                'status',
                'Status'
            )
                ->setChoices($statusChoices)
                ->setFormTypeOption(
                    'disabled',
                    $isAlreadyPassed
                );
        }

        yield DateTimeField::new(
            'registeredAt',
            'Angemeldet'
        )
            ->hideOnForm();

        yield DateField::new(
            'startedAt',
            'Begonnen'
        )
            ->setFormat('dd.MM.yyyy');

        yield DateField::new(
            'completedAt',
            'Abgeschlossen'
        )
            ->setFormat('dd.MM.yyyy');

        yield TextareaField::new(
            'notes',
            'Notizen'
        )
            ->hideOnIndex();
    }

    public function configureActions(Actions $actions): Actions
    {
        /*
         * Erfolgreicher Abschluss eines einzelnen Teilnehmers.
         *
         * Dabei wird über CourseCompletionService auch die
         * MemberQualification erzeugt bzw. aktualisiert.
         */
        $passAction = Action::new(
            'passCourse',
            'Bestanden',
            'fa fa-graduation-cap'
        )
            ->linkToRoute('admin_participant_pass_confirm', static fn (CourseParticipant $participant): array => ['id' => $participant->getId()])
            ->displayIf(
                static function (
                    CourseParticipant $participant
                ): bool {
                    if ($participant->hasPassed()) {
                        return false;
                    }

                    $course = $participant->getCourse();

                    if (
                        $course !== null
                        && $course->getStatus()
                            === CourseStatus::COMPLETED
                    ) {
                        return false;
                    }

                    return true;
                }
            );

        /*
         * Bestandene Teilnehmer und Teilnehmer abgeschlossener
         * Kurse sollen auch im UI nicht löschbar sein.
         *
         * Die serverseitige Prüfung in deleteEntity() bleibt
         * trotzdem bestehen.
         */
        $actions->update(
            Crud::PAGE_INDEX,
            Action::DELETE,
            static fn (Action $action): Action =>
                $action->displayIf(
                    static function (
                        CourseParticipant $participant
                    ): bool {
                        if ($participant->hasPassed()) {
                            return false;
                        }

                        $course = $participant->getCourse();

                        return $course === null
                            || $course->getStatus()
                                !== CourseStatus::COMPLETED;
                    }
                )
        );

        /*
         * Teilnehmer abgeschlossener Kurse sollen auch nicht mehr
         * über die Listenansicht bearbeitet werden können.
         */
        $actions->update(
            Crud::PAGE_INDEX,
            Action::EDIT,
            static fn (Action $action): Action =>
                $action->displayIf(
                    static function (
                        CourseParticipant $participant
                    ): bool {
                        $course = $participant->getCourse();

                        return $course === null
                            || $course->getStatus()
                                !== CourseStatus::COMPLETED;
                    }
                )
        );

        $progressAction = Action::new(
            'trainingProgress',
            'Ausbildungsfortschritt',
            'fa fa-chart-bar'
        )->linkToUrl(fn (CourseParticipant $participant): string =>
            $this->router->generate('admin_training_progress', [
                'id' => $participant->getId(),
            ])
        );

        $actions->add(Crud::PAGE_INDEX, $progressAction);
        $actions->add(Crud::PAGE_DETAIL, $progressAction);

        return $actions
            ->add(
                Crud::PAGE_INDEX,
                $passAction
            )
            ->add(
                Crud::PAGE_DETAIL,
                $passAction
            );
    }

    /**
     * Teilnehmer erfolgreich abschließen.
     */
    /** Legacy EasyAdmin action: GET never changes qualification or participant state. */
    public function passCourse(
        ?AdminContext $context,
        Request $request,
        EntityManagerInterface $entityManager
    ): RedirectResponse {
        $rawId = $request->query->get('entityId');
        if (!is_scalar($rawId) || !ctype_digit((string) $rawId) || (int) $rawId < 1) {
            throw $this->createNotFoundException('Kursteilnehmer-ID fehlt.');
        }
        $participant = $entityManager->getRepository(CourseParticipant::class)->find((int) $rawId);
        if (!$participant instanceof CourseParticipant) {
            throw $this->createNotFoundException('Kursteilnehmer nicht gefunden.');
        }
        return $this->redirectToRoute('admin_participant_pass_confirm', ['id' => $participant->getId()]);
    }

    /**
     * Bestehenden Teilnehmer bearbeiten.
     *
     * Teilnehmer abgeschlossener Kurse sind schreibgeschützt.
     */
    public function updateEntity(
        EntityManagerInterface $entityManager,
        $entityInstance
    ): void {
        if (!$entityInstance instanceof CourseParticipant) {
            parent::updateEntity(
                $entityManager,
                $entityInstance
            );

            return;
        }

        $original = $entityManager->getUnitOfWork()->getOriginalEntityData($entityInstance);
        $originalCourse = $original['course'] ?? null;
        $originalMember = $original['member'] ?? null;
        if (!$originalCourse instanceof Courses || ($originalMember !== null && !$originalMember instanceof Member)) {
            throw $this->createAccessDeniedException('Urspruengliche Teilnehmerzuordnung fehlt.');
        }
        $this->denyAccessUnlessGranted(CourseManagementVoter::MANAGE, $originalCourse);
        $clubId = $this->clubContext->getClub()?->getId();
        if ($clubId === null || $originalCourse->getClub()?->getId() !== $clubId
            || ($originalMember !== null && $originalMember->getClub()?->getId() !== $clubId)) {
            throw $this->createAccessDeniedException('Teilnehmerzuordnung gehört nicht zum ausgewählten Verein.');
        }
        if ($originalMember !== null) {
            $this->denyAccessUnlessGranted(MemberManagementVoter::MANAGE, $originalMember);
        }
        $entityInstance->setCourse($originalCourse);
        $entityInstance->setMember($originalMember);
        $this->assertParticipantAccess($entityInstance);
        $course = $entityInstance->getCourse();

        if (
            $course !== null
            && $course->getStatus() === CourseStatus::COMPLETED
        ) {
            throw new \LogicException(
                'Teilnehmer eines abgeschlossenen Kurses '
                . 'können nicht mehr geändert werden. '
                . 'Öffne den Kurs zuerst wieder.'
            );
        }

        /*
         * Bestandene Teilnehmer werden ebenfalls nicht mehr über
         * das normale Formular verändert.
         */
        if ($entityInstance->hasPassed() || $entityInstance->getArchivedAt() !== null) {
            throw new \LogicException(
                'Ein bestandener Kursteilnehmer kann nicht mehr '
                . 'über das normale Formular geändert werden.'
            );
        }

        parent::updateEntity(
            $entityManager,
            $entityInstance
        );
    }

    /**
     * Neuen Teilnehmer speichern.
     */
    public function persistEntity(
        EntityManagerInterface $entityManager,
        $entityInstance
    ): void {
        if ($entityInstance instanceof CourseParticipant) {
            $this->assertParticipantAccess($entityInstance);
            $course = $entityInstance->getCourse();
            if ($course !== null) {
                $entityInstance->setFeeCents(\App\Service\FrontendCourseRegistrationService::moneyToCents($course->getCourseFee()));
                if (!$course->hasFreePlaces() && $entityInstance->getStatus() === CourseParticipantStatus::REGISTERED) {
                    $entityInstance->setStatus(CourseParticipantStatus::WAITLISTED);
                }
            }

            if (
                $course !== null
                && $course->getStatus() === CourseStatus::COMPLETED
            ) {
                throw new \LogicException(
                    'Zu einem abgeschlossenen Kurs können '
                    . 'keine Teilnehmer hinzugefügt werden.'
                );
            }
        }

        parent::persistEntity(
            $entityManager,
            $entityInstance
        );

        // Only after the course participant was persisted successfully.
        // Existing bookings and attendance values remain unchanged.
        if ($entityInstance instanceof CourseParticipant
            && $entityInstance->getCourse() !== null
            && $entityInstance->getMember() !== null
            && $entityInstance->getStatus() !== CourseParticipantStatus::CANCELLED
        ) {
            $created = 0;
            foreach ($this->scheduleRepository->findBy(['courses' => $entityInstance->getCourse()]) as $schedule) {
                $created += $this->participantSyncService->sync($schedule);
            }
            if ($created > 0) {
                $this->addFlash('success', sprintf('%d fehlende Terminbuchung(en) automatisch angelegt.', $created));
            }
        }
    }

    /**
     * Teilnehmer löschen.
     *
     * Bestandene Teilnehmer bleiben als Ausbildungshistorie
     * erhalten.
     */
    public function deleteEntity(
        EntityManagerInterface $entityManager,
        $entityInstance
    ): void {
        if (!$entityInstance instanceof CourseParticipant) {
            parent::deleteEntity(
                $entityManager,
                $entityInstance
            );

            return;
        }

        // Objektberechtigung auch beim direkten POST mit gueltigem CSRF-Token
        // erzwingen. UI-Filter und ausgeblendete Aktionen genuegen nicht.
        $this->assertParticipantAccess($entityInstance);

        if ($entityInstance->hasPassed() || $entityInstance->getArchivedAt() !== null) {
            throw new \LogicException(
                'Ein bestandener Kursteilnehmer kann nicht gelöscht '
                . 'werden, da der Abschluss zur Ausbildungshistorie gehört.'
            );
        }

        $course = $entityInstance->getCourse();

        if (
            $course !== null
            && $course->getStatus() === CourseStatus::COMPLETED
        ) {
            throw new \LogicException(
                'Teilnehmer eines abgeschlossenen Kurses '
                . 'können nicht gelöscht werden. '
                . 'Öffne den Kurs zuerst wieder.'
            );
        }

        parent::deleteEntity(
            $entityManager,
            $entityInstance
        );
    }

    /**
     * Zur aufrufenden EasyAdmin-Seite zurückkehren.
     */
    private function redirectToReferrer(
        AdminContext $context
    ): RedirectResponse {
        $referrer = $context->getReferrer();

        if ($referrer !== null && $referrer !== '') {
            return $this->redirect($referrer);
        }

        return $this->redirectToRoute('admin');
    }
}