<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\BoardTask;
use App\Entity\User;
use App\Service\AdminClubContext;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class BoardTaskCrudController extends AbstractCrudController
{
    public function __construct(private readonly AdminClubContext $clubs, private readonly AuditLogger $audit) {}

    public static function getEntityFqcn(): string { return BoardTask::class; }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $this->assertManagementAccess();
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->andWhere('entity.club = :club')->setParameter('club', $this->clubs->getClub());
    }

    public function createEntity(string $entityFqcn): BoardTask
    {
        $this->assertManagementAccess();
        $club = $this->clubs->getClub() ?? throw $this->createAccessDeniedException();
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        return new BoardTask($club, $user, 'Neue Aufgabe');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield TextField::new('title', 'Aufgabe');
        yield TextareaField::new('description', 'Beschreibung')->hideOnIndex();
        yield ChoiceField::new('priority', 'Priorität')->setChoices(BoardTask::priorityChoices())->renderAsBadges();
        yield ChoiceField::new('status', 'Status')->setChoices(BoardTask::statusChoices())->renderAsBadges();
        yield DateTimeField::new('dueAt', 'Frist');
        yield DateTimeField::new('remindAt', 'Erinnerung')->hideOnIndex();
        yield DateTimeField::new('reminderSentAt', 'Erinnerung gesendet')->onlyOnDetail();
        yield AssociationField::new('assignee', 'Verantwortlich')
            ->setFormTypeOption('query_builder', fn ($repository) => $repository->createQueryBuilder('u')
                ->leftJoin('u.member', 'm')
                ->where('m.club = :club OR u = :actor')
                ->setParameter('club', $this->clubs->getClub())->setParameter('actor', $this->getUser()));
        yield AssociationField::new('resolution', 'Beschluss')->hideOnForm();
        yield AssociationField::new('createdBy', 'Erstellt von')->onlyOnDetail();
        yield DateTimeField::new('createdAt', 'Erstellt am')->onlyOnDetail();
        yield DateTimeField::new('completedAt', 'Erledigt am')->onlyOnDetail();
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityLabelInSingular('Aufgabe')->setEntityLabelInPlural('Aufgaben')
            ->setDefaultSort(['status' => 'ASC', 'dueAt' => 'ASC'])
            ->setSearchFields(['title', 'description', 'assignee.user']);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->assertEntityScope($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
        $this->audit->record($entityInstance->getClub(), $this->getUser()->getUserIdentifier(), 'governance.task.created', $entityInstance);
        $entityManager->flush();
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->assertEntityScope($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
        $this->audit->record($entityInstance->getClub(), $this->getUser()->getUserIdentifier(), 'governance.task.updated', $entityInstance, ['status' => $entityInstance->getStatus()]);
        $entityManager->flush();
    }

    private function assertEntityScope(BoardTask $task): void
    {
        $this->assertManagementAccess();
        if ($task->getClub()->getId() !== $this->clubs->getClub()?->getId()) {
            throw $this->createAccessDeniedException();
        }
    }

    private function assertManagementAccess(): void
    {
        if (!$this->isGranted(User::ROLE_BOARD)
            && !$this->isGranted(User::ROLE_SECRETARY)
            && !$this->isGranted(User::ROLE_ADMIN)) {
            throw $this->createAccessDeniedException();
        }
    }
}
