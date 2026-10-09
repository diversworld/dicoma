<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ScheduleTemplate;
use App\Entity\ScheduleTemplateEntry;
use App\Form\ScheduleTemplateEntryType;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class ScheduleTemplateCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ScheduleTemplate::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Planungsvorlage')
            ->setEntityLabelInPlural('Planungsvorlagen')
            ->setDefaultSort(['name' => 'ASC'])
            ->setSearchFields(['name', 'club.name']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('club', 'Verein')->setRequired(true);
        yield TextField::new('name', 'Vorlagenname')->setRequired(true);
        yield TextareaField::new('description', 'Beschreibung')->hideOnIndex();
        yield CollectionField::new('entries', 'Ausbildungstermine')
            ->setEntryType(ScheduleTemplateEntryType::class)
            ->allowAdd()
            ->allowDelete()
            ->setFormTypeOption('by_reference', false)
            ->onlyOnForms();
    }

    public function configureActions(Actions $actions): Actions
    {
        $duplicate = Action::new(
            'duplicateTemplate',
            'Duplizieren',
            'fa fa-copy'
        )->linkToCrudAction('duplicateTemplate');

        return $actions
            ->add(Crud::PAGE_INDEX, $duplicate)
            ->add(Crud::PAGE_DETAIL, $duplicate);
    }

    public function duplicateTemplate(
        AdminContext $context,
        Request $request,
        EntityManagerInterface $entityManager,
        AdminUrlGenerator $adminUrlGenerator
    ): Response {
        $original = $context->getEntity()?->getInstance();
        if (!$original instanceof ScheduleTemplate || $original->getId() === null) {
            throw $this->createNotFoundException('Planungsvorlage nicht gefunden.');
        }

        $backUrl = $adminUrlGenerator
            ->unsetAll()
            ->setController(self::class)
            ->setAction(Crud::PAGE_INDEX)
            ->generateUrl();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid(
                'duplicate_schedule_template_' . $original->getId(),
                (string) $request->request->get('_token', '')
            )) {
                throw $this->createAccessDeniedException('Ungueltiges CSRF-Token.');
            }

            $name = trim((string) $request->request->get('name', ''));
            if ($name === '' || mb_strlen($name) > 150) {
                $this->addFlash('danger', 'Bitte einen Vorlagennamen mit maximal 150 Zeichen angeben.');
            } elseif ($original->getClub() === null) {
                $this->addFlash('danger', 'Die Originalvorlage hat keinen Verein.');
            } else {
                $copy = new ScheduleTemplate();
                $copy->setClub($original->getClub());
                $copy->setName($name);
                $copy->setDescription($original->getDescription());

                foreach ($original->getEntries() as $entry) {
                    $newEntry = new ScheduleTemplateEntry();
                    $newEntry->setTrainingUnitType($entry->getTrainingUnitType());
                    $newEntry->setSequence($entry->getSequence());
                    $newEntry->setDayOffset($entry->getDayOffset());
                    $newEntry->setDurationMinutes($entry->getDurationMinutes());
                    $newEntry->setStartTime(
                        $entry->getStartTime() === null
                            ? null
                            : \DateTime::createFromInterface($entry->getStartTime())
                    );
                    $copy->addEntry($newEntry);
                }

                $entityManager->persist($copy);
                $entityManager->flush();

                $this->addFlash('success', sprintf(
                    'Planungsvorlage "%s" mit %d Ausbildungsterminen dupliziert.',
                    $name,
                    $copy->getEntries()->count()
                ));

                return $this->redirect($backUrl);
            }
        }

        return $this->render('admin/schedule_template/duplicate.html.twig', [
            'original' => $original,
            'suggestedName' => (string) ($request->request->get('name') ?: ($original->getName() . ' (Kopie)')),
            'backUrl' => $backUrl,
        ]);
    }
}
