<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\CourseParticipant;
use App\Entity\TrainingUnitResult;
use App\Enum\TrainingUnitResultStatus;
use App\Repository\ScheduleRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class TrainingUnitResultCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return TrainingUnitResult::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Ausbildungsnachweis')
            ->setEntityLabelInPlural('Ausbildungsnachweise')
            ->setDefaultSort(['id' => 'DESC']);
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $assets->addJsFile('js/training-unit-result-filter.js');
    }

    public function configureFields(string $pageName): iterable
    {
        $isForm = in_array($pageName, [Crud::PAGE_NEW, Crud::PAGE_EDIT], true);

        $participant = AssociationField::new('courseParticipant', 'Kursteilnehmer')
            ->setRequired(true);
        $schedule = AssociationField::new('schedule', 'Ausbildungstermin')
            ->setFormTypeOption('placeholder', 'Bitte Termin wählen')
            ->setRequired(true);

        if ($isForm) {
            // Attribute werden auf dem Select ausgegeben; die JS-Datei erkennt
            // die Felder ansonsten auch ueber ihren Symfony-Feldnamen.
            $participant->setFormTypeOption('attr', ['data-training-participant' => '1']);
            $schedule->setFormTypeOption('attr', ['data-training-schedule' => '1']);
        }

        yield $participant;
        yield $schedule;

        if (in_array($pageName, [Crud::PAGE_INDEX, Crud::PAGE_DETAIL], true)) {
            yield TextField::new('statusLabel', 'Bewertung');
        } else {
            yield ChoiceField::new('status', 'Bewertung')
                ->setChoices(TrainingUnitResultStatus::choices());
        }

        yield DateField::new('assessedAt', 'Bewertet am')
            ->setFormat('dd.MM.yyyy');
        yield AssociationField::new('assessor', 'Pruefer')->setRequired(false);
        yield TextareaField::new('notes', 'Bemerkungen')->hideOnIndex();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('courseParticipant')
            ->add('schedule')
            ->add('status')
            ->add('assessor');
    }

    /**
     * Nur Termine des Kurses des ausgewaehlten Teilnehmers liefern.
     * Die Entity-Validierung prueft die Zuordnung zusaetzlich serverseitig.
     */
    #[Route('/admin/training-unit-result/schedules', name: 'admin_training_result_schedules', methods: ['GET'])]
    public function schedulesForParticipant(
        Request $request,
        ScheduleRepository $scheduleRepository,
        \Doctrine\ORM\EntityManagerInterface $entityManager
    ): JsonResponse {
        $id = filter_var($request->query->get('participant'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($id === false || $id === null) {
            return $this->json(['options' => []]);
        }

        $participant = $entityManager->getRepository(CourseParticipant::class)->find($id);
        if (!$participant instanceof CourseParticipant || $participant->getCourse() === null) {
            return $this->json(['options' => []]);
        }

        $schedules = $scheduleRepository->findBy(
            ['courses' => $participant->getCourse()],
            ['startDate' => 'ASC', 'id' => 'ASC']
        );

        $options = [];
        foreach ($schedules as $schedule) {
            $options[] = [
                'value' => (string) $schedule->getId(),
                'text' => sprintf('%s%s', (string) $schedule, $schedule->getStartDate() ? ' (' . $schedule->getStartDate()->format('d.m.Y') . ')' : ''),
            ];
        }

        return $this->json(['options' => $options]);
    }
}
