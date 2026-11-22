<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\BoardMeeting;
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
final class BoardMeetingCrudController extends AbstractCrudController
{
    public function __construct(private readonly AdminClubContext $clubs, private readonly AuditLogger $audit) {}
    public static function getEntityFqcn(): string { return BoardMeeting::class; }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $this->assertManagementAccess();
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->andWhere('entity.club = :club')->setParameter('club', $this->clubs->getClub());
    }

    public function createEntity(string $entityFqcn): BoardMeeting
    {
        $this->assertManagementAccess();
        $club = $this->clubs->getClub() ?? throw $this->createAccessDeniedException();
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        return new BoardMeeting($club, $user, 'Neue Vorstandssitzung', new \DateTimeImmutable('+7 days 19:00'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield TextField::new('title', 'Sitzung');
        yield DateTimeField::new('startsAt', 'Beginn');
        yield DateTimeField::new('endsAt', 'Ende')->hideOnIndex();
        yield TextField::new('location', 'Ort')->hideOnIndex();
        yield ChoiceField::new('status', 'Status')->setChoices(BoardMeeting::statusChoices());
        yield TextareaField::new('agenda', 'Tagesordnung')->hideOnIndex();
        yield TextareaField::new('minutes', 'Protokoll')->hideOnIndex();
        yield AssociationField::new('participants', 'Teilnehmende')->hideOnIndex()
            ->setFormTypeOption('by_reference', false);
        yield AssociationField::new('resolutions', 'Beschlüsse')->hideOnForm();
        yield AssociationField::new('createdBy', 'Erstellt von')->onlyOnDetail();
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityLabelInSingular('Sitzung')->setEntityLabelInPlural('Sitzungen')
            ->setDefaultSort(['startsAt' => 'DESC'])
            ->setSearchFields(['title', 'agenda', 'minutes']);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->assertEntityScope($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
        $this->audit->record($entityInstance->getClub(), $this->getUser()->getUserIdentifier(), 'governance.meeting.created', $entityInstance);
        $entityManager->flush();
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->assertEntityScope($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
        $this->audit->record($entityInstance->getClub(), $this->getUser()->getUserIdentifier(), 'governance.meeting.updated', $entityInstance, ['status' => $entityInstance->getStatus()]);
        $entityManager->flush();
    }

    private function assertEntityScope(BoardMeeting $meeting): void
    {
        $this->assertManagementAccess();
        if ($meeting->getClub()->getId() !== $this->clubs->getClub()?->getId()) {
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
