<?php

namespace App\Controller\Admin;

use App\Entity\TeamMember;
use App\Entity\Member;
use App\Entity\Team;
use App\Entity\MemberSport;
use App\Enum\TeamMemberRole;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[IsGranted('ROLE_SUPER_ADMIN')]
class TeamMemberCrudController extends AbstractCrudController
{
    public function __construct(
		private readonly EntityManagerInterface $memberEntityManager,
		private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly ValidatorInterface $validator,
        private readonly UrlGeneratorInterface $urlGenerator
	) {
	}

    public function createEntity(string $entityFqcn): TeamMember
    {
        $item = new TeamMember();
        $raw = $this->getContext()?->getRequest()->query->get('memberId');
        if (is_scalar($raw) && ctype_digit((string) $raw) && (int) $raw > 0) {
            $member = $this->memberEntityManager->getRepository(Member::class)->find((int) $raw);
            if ($member instanceof Member) {
                $item->setMember($member);
            }
        }
        $teamRaw = $this->getContext()?->getRequest()->query->get('teamId');
        if (is_scalar($teamRaw) && ctype_digit((string) $teamRaw) && (int) $teamRaw > 0) {
            $team = $this->memberEntityManager->getRepository(Team::class)->find((int) $teamRaw);
            if ($team instanceof Team) {
                $item->setTeam($team);
            }
        }
        return $item;
    }

    protected function getRedirectResponseAfterSave(
        AdminContext $context,
        string $action
    ): RedirectResponse {
        $request = $context->getRequest();
        if ($request->query->get('returnToMember') !== '1') {
            return parent::getRedirectResponseAfterSave($context, $action);
        }

        // Never trust memberId as the destination. Use the actual saved relation.
        $entity = $context->getCrud() !== null
            ? $context->getEntity()?->getInstance()
            : null;

        if (!$entity instanceof TeamMember) {
            return parent::getRedirectResponseAfterSave($context, $action);
        }

        $member = $entity->getMember();
        if (!$member instanceof Member || $member->getId() === null) {
            return parent::getRedirectResponseAfterSave($context, $action);
        }

        $url = (clone $this->adminUrlGenerator)
            ->unsetAll()
            ->setController(MemberCrudController::class)
            ->setAction(Crud::PAGE_DETAIL)
            ->setEntityId($member->getId())
            ->generateUrl();

        return new RedirectResponse($url);
    }

    /**
     * Zeigt einen expliziten, sicheren Rueckweg bei Formularen aus der
     * Mitgliederzentrale. Die EasyAdmin-Standardaktion bleibt unveraendert.
     *
     * Bei EDIT wird die Mitgliedszuordnung aus der Datenbank gelesen,
     * niemals aus einem manipulierten memberId-Parameter.
     */
    private function memberCenterCancelUrl(): ?string
    {
        $request = $this->getContext()?->getRequest();
        if ($request === null || $request->query->get('returnToMember') !== '1') {
            return null;
        }

        $action = $request->query->get('crudAction');
        $member = null;

        if ($action === Crud::PAGE_EDIT) {
            $rawId = $request->query->get('entityId');
            if (!is_scalar($rawId) || !ctype_digit((string) $rawId) || (int) $rawId < 1) {
                return null;
            }
            $record = $this->memberEntityManager
                ->getRepository(\App\Entity\TeamMember::class)
                ->find((int) $rawId);
            $member = $record?->getMember();
        } elseif ($action === Crud::PAGE_NEW) {
            $rawId = $request->query->get('memberId');
            if (!is_scalar($rawId) || !ctype_digit((string) $rawId) || (int) $rawId < 1) {
                return null;
            }
            $member = $this->memberEntityManager
                ->getRepository(Member::class)
                ->find((int) $rawId);
        }

        if (!$member instanceof Member || $member->getId() === null) {
            return null;
        }

        return (clone $this->adminUrlGenerator)
            ->unsetAll()
            ->setController(MemberCrudController::class)
            ->setAction(Crud::PAGE_DETAIL)
            ->setEntityId($member->getId())
            ->generateUrl();
    }

    private function addMemberCenterReturnAction(Actions $actions): Actions
    {
        $url = $this->memberCenterCancelUrl();
        if ($url === null) {
            return $actions;
        }

        $return = Action::new(
            'backToMemberCenter',
            'Zur Mitgliederzentrale (ohne Speichern)',
            'fa fa-arrow-left'
        )
            ->linkToUrl($url)
            ->createAsGlobalAction()
            ->addCssClass('btn btn-secondary');

        return $actions
            ->add(Crud::PAGE_NEW, $return)
            ->add(Crud::PAGE_EDIT, $return);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $this->addMemberCenterReturnAction($actions);
    }

    public static function getEntityFqcn(): string
    {
        return TeamMember::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Gruppenmitglied')
            ->setEntityLabelInPlural('Gruppenmitglieder')
            ->setPageTitle(
                Crud::PAGE_INDEX,
                'Gruppen- und Mannschaftsmitglieder'
            )
            ->setPageTitle(
                Crud::PAGE_NEW,
                'Mitglied einer Gruppe zuordnen'
            )
            ->setPageTitle(
                Crud::PAGE_EDIT,
                'Gruppenzuordnung bearbeiten'
            )
            ->setDefaultSort([
                'id' => 'DESC',
            ])
            ->setSearchFields([
                'member.firstname',
                'member.lastname',
                'member.memberNumber',
                'team.name',
                'team.sport.name',
            ]);
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $assets->addJsFile('js/team-member-sport-check.js?v=73');
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addFieldset('Zuordnung');

        yield AssociationField::new('team', 'Gruppe / Mannschaft')
            ->setRequired(true)
            ->setColumns(4);

        yield AssociationField::new('member', 'Mitglied')
            ->setRequired(true)
            ->setColumns(4);

        if ($pageName === Crud::PAGE_NEW || $pageName === Crud::PAGE_EDIT) {
            yield BooleanField::new('assignMissingSport', 'Fehlende Sportart ebenfalls zuordnen')
                ->setHelp('Nur bei fehlender Sportart und mit ausdruecklicher Zustimmung. Die Zuordnung wird serverseitig geprueft.')
                ->setFormTypeOption('attr', [
                    'data-sport-check-url' => $this->urlGenerator->generate('admin_team_member_sport_check'),
                ])
                ->setColumns(12);
        }

        yield ChoiceField::new('role', 'Funktion')
            ->setChoices(TeamMemberRole::choices())
            ->setRequired(true)
            ->setColumns(4);

        yield DateField::new('joinedAt', 'Seit')
            ->setFormat('dd.MM.yyyy')
            ->setColumns(3);

        yield DateField::new('leftAt', 'Bis')
            ->setFormat('dd.MM.yyyy')
            ->setColumns(3);

        yield TextareaField::new('notes', 'Notizen')
            ->hideOnIndex();
    }
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->saveWithOptionalSport($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->saveWithOptionalSport($entityManager, $entityInstance);
    }

    private function saveWithOptionalSport(EntityManagerInterface $em, $entity): void
    {
        if (!$entity instanceof TeamMember) {
            throw new \InvalidArgumentException('Expected TeamMember.');
        }

        $em->wrapInTransaction(function (EntityManagerInterface $manager) use ($entity): void {
            $team = $entity->getTeam();
            $member = $entity->getMember();
            if ($team === null || $member === null) {
                throw new \LogicException('Gruppe und Mitglied muessen angegeben werden.');
            }

            $sport = $team->getSport();
            if ($sport !== null && $entity->isAssignMissingSport()) {
                if ($sport->getClub() === null || $member->getClub() === null
                    || $sport->getClub()->getId() !== $member->getClub()->getId()) {
                    throw new \LogicException('Mitglied und Sportart gehoeren nicht demselben Verein an.');
                }

                $existing = $manager->getRepository(MemberSport::class)->findOneBy([
                    'member' => $member,
                    'sport' => $sport,
                ]);
                if ($existing === null) {
                    $memberSport = (new MemberSport())->setSport($sport);
                    $member->addSport($memberSport);
                    $manager->persist($memberSport);
                }
            }

            $errors = $this->validator->validate($entity);
            if (count($errors) > 0) {
                throw new \LogicException((string) $errors);
            }
            $manager->persist($entity);
        });
    }
}