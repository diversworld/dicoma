<?php

namespace App\Controller\Admin;

use App\Entity\CourseParticipant;
use Symfony\Component\Translation\TranslatableMessage;
use App\Enum\CourseParticipantStatus;
use App\Enum\CourseStatus;
use App\Service\CourseCompletionService;
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
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class CourseParticipantCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly CourseCompletionService $courseCompletionService,
        private readonly UrlGeneratorInterface $router
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return CourseParticipant::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
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
                'member.firstname',
                'member.lastname',
                'member.memberNumber',
                'course.name',
            ]);
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new(
            'course',
            'Kurs'
        );

        yield AssociationField::new(
            'member',
            'Teilnehmer'
        );

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
            ->linkToCrudAction('passCourse')
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
    public function passCourse(
        AdminContext $context
    ): RedirectResponse {
        $participant = $context
            ->getEntity()
            ->getInstance();

        if (!$participant instanceof CourseParticipant) {
            throw new \LogicException(
                'Ungültiger Kursteilnehmer.'
            );
        }

        /*
         * Abgeschlossene Kurse sind schreibgeschützt.
         */
        $course = $participant->getCourse();

        if (
            $course !== null
            && $course->getStatus() === CourseStatus::COMPLETED
        ) {
            $this->addFlash(
                'warning',
                'Der Kurs ist bereits abgeschlossen. '
                . 'Öffne ihn zuerst wieder, bevor Teilnehmer '
                . 'geändert werden.'
            );

            return $this->redirectToReferrer($context);
        }

        /*
         * Serverseitiger Schutz gegen mehrfachen Abschluss.
         */
        if ($participant->hasPassed()) {
            $this->addFlash(
                'info',
                new TranslatableMessage('participant.already_passed', [
                    '%member%' => $participant->getMember()?->getFullName() ?? '',
                ])
            );

            return $this->redirectToReferrer($context);
        }

        try {
            $memberQualification = $this
                ->courseCompletionService
                ->pass($participant);

            $qualificationName = $memberQualification
                ?->getQualification()
                ?->getName()
                ?? '';

            $this->addFlash(
                'success',
                new TranslatableMessage('participant.completed', [
                    '%member%' => $participant->getMember()?->getFullName() ?? '',
                    '%qualification%' => $qualificationName,
                ])
            );
        } catch (\LogicException $exception) {
            $this->addFlash(
                'danger',
                $exception->getMessage()
            );
        }

        return $this->redirectToReferrer($context);
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
        if ($entityInstance->hasPassed()) {
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
            $course = $entityInstance->getCourse();

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

        if ($entityInstance->hasPassed()) {
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