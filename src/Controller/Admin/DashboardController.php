<?php

namespace App\Controller\Admin;

use App\Entity\Courses;
use App\Entity\Member;
use App\Entity\Schedule;
use App\Entity\Tank;
use App\Entity\TankCheck;
use App\Entity\TankCheckArticle;
use App\Entity\TankCheckDetail;
use App\Entity\User;
use App\Entity\Club;
use App\Entity\Sport;
use App\Entity\MemberSport;
use App\Entity\Vendor;
use App\Entity\Team;
use App\Entity\Qualification;
use App\Entity\TeamMember;
use App\Entity\MemberQualification;
use App\Entity\CourseParticipant;
use App\Entity\TrainingUnitType;
use App\Entity\ScheduleTemplate;
use App\Entity\TrainingUnitResult;
use App\Entity\MembershipApplication;
use App\Entity\WebsitePublication;
use App\Entity\FileAction;
use App\Enum\CourseStatus;
use App\Enum\PermissionAction;
use App\Controller\Admin\MemberCrudController;
use \App\Entity\TuvInvoiceSettings;
use App\Controller\Admin\MemberQualificationCrudController;
use App\Controller\Admin\EquipmentBookingCrudController;
use App\Controller\Admin\MemberTankCheckBookingCrudController;
use App\Service\TankInspectionOverview;
use App\Service\PublicWebsite;
use App\Service\AdminClubContext;
use App\Service\MembershipRoleContinuity;
use App\Service\BarDutyAccess;
use App\Security\EditorialAccess;
use App\Security\FolderPermissions;
use App\Repository\MemberQualificationRepository;
use App\Form\WebsitePublicationType;
use App\Form\TuvInvoiceSettingsType;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Locale;
use EasyCorp\Bundle\EasyAdminBundle\Dto\MenuItemDto;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\FormError;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Doctrine\DBAL\LockMode;
use App\Service\TuvMessageRenderer;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use \Doctrine\ORM\EntityManagerInterface;

#[AdminDashboard(
	routePath: '/admin/{_locale}', 
	routeName: 'admin', 
	routeOptions: [
		'requirements' => ['_locale' => 'de|en|fr|es'], 
		'defaults' => ['_locale' => 'de'],
		'methods' => ['GET'],],
	)
]
class DashboardController extends AbstractDashboardController
{
    use EquipmentBookingBasketTrait;

    public const ROUTE_DEFAULTS = [
        'routeCreatedByEasyAdmin' => true,
        'dashboardControllerFqcn' => self::class,
    ];

    #[AdminRoute(path: '/equipment-center', name: 'equipment_center')]
    public function equipmentCenter(TankInspectionOverview $overview): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $club = $this->clubContext->getClub() ?? throw $this->createAccessDeniedException('Kein Verein ausgewählt.');
        $tankUrl = (clone $this->adminUrlGenerator)
            ->unsetAll()->setController(TankCrudController::class)
            ->setAction(Crud::PAGE_INDEX)->generateUrl();
        $checkUrl = (clone $this->adminUrlGenerator)
            ->unsetAll()->setController(TankCheckCrudController::class)
            ->setAction(Crud::PAGE_INDEX)->generateUrl();

        return $this->render('admin/equipment/center.html.twig', [
            'overview' => $overview->getOverview($club),
            'tankUrl' => $tankUrl,
            'checkUrl' => $checkUrl,
        ]);
    }

	public function __construct(
		private readonly MemberQualificationRepository $memberQualificationRepository, 
		private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly EntityManagerInterface $em,
        private readonly PublicWebsite $publicWebsite,
        private readonly AdminClubContext $clubContext,
        private readonly BarDutyAccess $barAccess,
        private readonly EditorialAccess $editorialAccess,
        private readonly FolderPermissions $folderPermissions,
        private readonly MembershipRoleContinuity $continuity,
        #[Autowire('%env(bool:CLUB_FUNCTION_MEMBERSHIP_ENABLED)%')] private readonly bool $functionModelEnabled,
	) {
	}
	
	public function index(): Response
	{
        $club = $this->clubContext->getClub();
        if ($club === null && !$this->isGranted('ROLE_SUPER_ADMIN')) {
            throw $this->createAccessDeniedException('Keine Vereinszuordnung vorhanden.');
        }
        $qualificationUrl = (clone $this->adminUrlGenerator)->unsetAll()
			->setController(MemberQualificationCrudController::class)
			->setAction(Crud::PAGE_INDEX);
        $canManageQualifications = $this->isGranted('ROLE_SUPER_ADMIN');
        $now = new \DateTimeImmutable();
        $activeStatuses = [CourseStatus::OPEN->value, CourseStatus::RUNNING->value];
        $courses = $this->em->getRepository(Courses::class)->createQueryBuilder('c')
            ->select('COUNT(c.id)')->where('c.status IN (:statuses)')
            ->setParameter('statuses', $activeStatuses);
        $schedules = $this->em->getRepository(Schedule::class)->createQueryBuilder('s')
            ->addSelect('c')->innerJoin('s.courses', 'c')
            ->where('c.status IN (:statuses)')
            ->andWhere('s.startDate > :today OR (s.startDate = :today AND (s.startTime IS NULL OR s.startTime >= :now))')
            ->setParameter('statuses', $activeStatuses)
            ->setParameter('today', $now->format('d.m.Y'))
            ->setParameter('now', $now->format('H:i:s'))
            ->orderBy('s.startDate', 'ASC')->addOrderBy('s.startTime', 'ASC')
            ->addOrderBy('s.id', 'ASC')->setMaxResults(5);
        $applicationCriteria = ['status' => 'pending'];
        if ($club !== null) {
            $courses->andWhere('c.club = :club')->setParameter('club', $club);
            $schedules->andWhere('c.club = :club')->setParameter('club', $club);
            $applicationCriteria['club'] = $club;
        } else {
            $courses->andWhere('1 = 0');
            $schedules->andWhere('1 = 0');
        }
        $applications = $this->em->getRepository(MembershipApplication::class);

        $membersUrl = (clone $this->adminUrlGenerator)->unsetAll()
            ->setController(MemberCrudController::class)->setAction(Crud::PAGE_INDEX)->generateUrl();

		return $this->render('admin/dashboard/dashboard.html.twig', [
            'dashboardScope' => $club?->getName(),
            'activeCourseCount' => (int) $courses->getQuery()->getSingleScalarResult(),
            'upcomingSchedules' => $schedules->getQuery()->getResult(),
            'pendingApplicationCount' => $club === null ? 0 : $applications->count($applicationCriteria),
            'pendingApplications' => $club === null ? [] : $applications->findBy($applicationCriteria, ['submittedAt' => 'ASC', 'id' => 'ASC'], 5),
            'quickLinks' => [
                [
                    'label' => 'Mitglieder verwalten', 
                    'icon' => 'fa-users',
                    'url' => $membersUrl
                ],
                [
                    'label' => 'Mitgliedschaften und Beiträge', 
                    'icon' => 'fa-address-card', 
                    'url' => $this->generateUrl('membership_admin')
                ],
                [
                    'label' => 'Ausbildungsportal', 
                    'icon' => 'fa-graduation-cap', 
                    'url' => $this->generateUrl('admin_portal_training_portal')
                ],
                [
                    'label' => 'Kurskalender', 
                    'icon' => 'fa-calendar', 
                    'url' => $this->generateUrl('admin_course_calendar')
                ],
            ],
			'expiredQualifications' => $club === null ? [] :
				$this->memberQualificationRepository
					->findExpired($club, 5),

			'expiringQualifications' => $club === null ? [] :
				$this->memberQualificationRepository
					->findExpiringWithinDays(30, $club, 5),

			'expiredQualificationCount' => $club === null ? 0 :
				$this->memberQualificationRepository
					->countExpired($club),

			'expiringQualificationCount' => $club === null ? 0 :
				$this->memberQualificationRepository
					->countExpiringWithinDays(30, $club),
			
			'expiredQualificationsUrl' =>
				$canManageQualifications ? (clone $qualificationUrl)->set('validity', 'expired')->generateUrl() : null,

			'expiringQualificationsUrl' =>
				$canManageQualifications ? (clone $qualificationUrl)->set('validity', 'expiring')->generateUrl() : null,

			'allQualificationsUrl' =>
				$canManageQualifications ? (clone $qualificationUrl)->generateUrl() : null,
		]);
	}

    #[AdminRoute(path: '/settings/tuv-notifications', name: 'settings_tuv_notifications', options: ['methods' => ['GET']])]
    public function tuvNotificationSettings(Request $request, TuvMessageRenderer $renderer): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $kind = (string) $request->query->get('kind', 'confirmation');
        if (!isset(TuvMessageRenderer::LABELS[$kind])) {
            throw $this->createNotFoundException();
        }
        return $this->render('admin/tuv/message_templates.html.twig', [
            'kinds' => TuvMessageRenderer::LABELS,
            'kind' => $kind,
            'template' => $renderer->getTemplate($kind),
        ]);
    }

    #[AdminRoute(path: '/settings/tuv-notifications/save', name: 'settings_tuv_notifications_save', options: ['methods' => ['POST']])]
    public function saveTuvNotificationSettings(Request $request, TuvMessageRenderer $renderer): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        if (!$this->isCsrfTokenValid('tuv_message_template', (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException();
        }
        $kind = (string) $request->request->get('kind', '');
        try {
            $renderer->save($kind, trim((string) $request->request->get('subject', '')), (string) $request->request->get('body', ''), $request->request->has('enabled'));
            $this->addFlash('success', 'Benachrichtigungsvorlage gespeichert.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('danger', $e->getMessage());
        }
        return $this->redirectToRoute('admin_settings_tuv_notifications', [
            'kind' => isset(TuvMessageRenderer::LABELS[$kind]) ? $kind : 'confirmation',
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('<h4>DiveClubManager</h4>')
            ->setFaviconPath('images/favicon.ico')
            ->setTranslationDomain('messages')
            ->setLocales([
                Locale::new('de', 'DE Deutsch', 'locale-flag locale-flag-de'), // locale without custom options
                Locale::new('en', 'GB English', 'locale-flag locale-flag-gb'), // custom label and icon
				Locale::new('fr', 'FR Français', 'locale-flag locale-flag-fr'), // custom label and icon
                Locale::new('es', 'ES Español', 'locale-flag locale-flag-es') // custom label and icon
            ])
            ;
    }

    public function configureAssets(): Assets
    {
        return Assets::new()
           ->addCssFile('css/theme-variables.css')
		   ->addCssFile('css/admin.css')
           ->addCssFile('css/admin-theme.css')
			->addCssFile('css/admin-logout.css')
			->addCssFile('css/member-center.css?v=3')
            ->addCssFile('css/utilities.css');
	}

    #[AdminRoute(path: '/editorial', name: 'website_editorial', options: ['methods' => ['GET']])]
    public function websiteEditorial(PublicWebsite $website): Response
    {
        $club = $this->editorialClub($website, PermissionAction::READ);

        return $this->render('admin/editorial/index.html.twig', [
            'publications' => $this->em->getRepository(WebsitePublication::class)
                ->findBy(['club' => $club], ['id' => 'DESC']),
            'canCreate' => $this->editorialAccess->can(PermissionAction::CREATE),
            'canEdit' => $this->editorialAccess->can(PermissionAction::EDIT),
            'canDelete' => $this->functionModelEnabled && $this->editorialAccess->can(PermissionAction::DELETE),
        ]);
    }

    #[AdminRoute(path: '/editorial/{entityId}/delete', name: 'website_editorial_delete', options: ['methods' => ['POST'], 'requirements' => ['entityId' => '\d+']])]
    public function deleteWebsitePublication(int $entityId, Request $request, PublicWebsite $website, \Psr\Log\LoggerInterface $logger): Response
    {
        if (!$this->functionModelEnabled) {
            throw $this->createAccessDeniedException('Das Löschen ist erst mit dem Funktionsmodell verfügbar.');
        }
        $clubId = null;
        $response = $this->withEditorialWriteLock($request, function () use ($entityId, $request, $website, &$clubId): Response {
            $club = $this->editorialClub($website, PermissionAction::DELETE);
            $publication = $this->em->getRepository(WebsitePublication::class)->findOneBy(['id' => $entityId, 'club' => $club]);
            if ($publication === null) { throw $this->createNotFoundException(); }
            if (!$this->isCsrfTokenValid('website_publication_delete_'.$entityId, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
            }
            $version = $request->request->getString('version');
            if (!preg_match('/^[1-9][0-9]{0,9}$/D', $version)) {
                throw new BadRequestHttpException('Ungültiger Versionswert.');
            }
            $this->continuity->refresh($publication);
            try {
                $this->em->lock($publication, LockMode::OPTIMISTIC, (int) $version);
            } catch (OptimisticLockException) {
                $this->addFlash('danger', 'Der Beitrag wurde zwischenzeitlich geändert. Bitte den aktuellen Stand prüfen und erneut versuchen.');
                return $this->editorialRedirect($request);
            }
            $this->em->remove($publication);
            $this->em->flush();
            $clubId = $club->getId();
            return $this->editorialRedirect($request);
        });
        if ($clubId !== null) {
            $logger->notice('Website publication deleted.', [
                'publication_id' => $entityId,
                'club_id' => $clubId,
                'actor' => $this->getUser()?->getUserIdentifier(),
            ]);
            $this->addFlash('success', 'Beitrag gelöscht.');
        }
        return $response;
    }

    #[AdminRoute(path: '/editorial/{entityId}/publication', name: 'website_editorial_publication', options: ['methods' => ['POST'], 'requirements' => ['entityId' => '\d+']])]
    public function changeWebsitePublication(int $entityId, Request $request, PublicWebsite $website, ValidatorInterface $validator): Response
    {
        return $this->withEditorialWriteLock($request, fn (): Response => $this->applyWebsitePublicationChange($entityId, $request, $website, $validator));
    }

    private function applyWebsitePublicationChange(int $entityId, Request $request, PublicWebsite $website, ValidatorInterface $validator): Response
    {
        $club = $this->editorialClub($website, PermissionAction::EDIT);
        $publication = $this->em->getRepository(WebsitePublication::class)->findOneBy(['id' => $entityId, 'club' => $club]);
        if ($publication === null) { throw $this->createNotFoundException(); }
        if (!$this->isCsrfTokenValid('website_publication_toggle_'.$entityId, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }
        if ($this->functionModelEnabled) {
            $this->continuity->refresh($publication);
        }
        $version = $request->request->getString('version');
        $value = $request->request->getString('published', '0');
        if (!preg_match('/^[1-9][0-9]{0,9}$/D', $version) || !in_array($value, ['0', '1'], true)) {
            throw new BadRequestHttpException('Ungültiger Veröffentlichungsstatus oder Versionswert.');
        }
        try {
            $this->em->lock($publication, LockMode::OPTIMISTIC, (int) $version);
            $published = $value === '1';

            if ($publication->isPublished() !== $published) {
                if ($published) {
                    $candidate = clone $publication;
                    $candidate->setPublished(true);
                    $errors = $validator->validate($candidate);
                    if (count($errors) > 0) {
                        foreach ($errors as $error) { $this->addFlash('danger', (string) $error->getMessage()); }
                        return $this->editorialRedirect($request);
                    }
                }
                $publication->setPublished($published);
                $publication->record($this->getUser()->getUserIdentifier());
                $this->em->flush();
            }
            
            $this->addFlash('success', $published
                ? 'Beitrag freigegeben. Sichtbar ab dem Veröffentlichungszeitpunkt, sofern die verknüpfte Veranstaltung freigegeben ist.'
                : 'Freigabe zurückgenommen; der Beitrag ist nicht öffentlich sichtbar.');
        } catch (OptimisticLockException) {
            $this->addFlash('danger', 'Der Beitrag wurde zwischenzeitlich geändert. Bitte den aktuellen Stand prüfen und erneut versuchen.');
        }
        return $this->editorialRedirect($request);
    }

    #[AdminRoute(path: '/editorial/display-settings', name: 'website_display_settings', options: ['methods' => ['GET']])]
    public function websiteDisplaySettings(PublicWebsite $website): Response
    {
        if ($this->functionModelEnabled) {
            if (!$this->editorialAccess->canViewSettings()) {
                throw $this->createAccessDeniedException();
            }
            return $this->redirectToRoute('admin_settings_website');
        }
        $this->denyAccessUnlessGranted(User::ROLE_PRESS_OFFICER);
        if (!$this->isGranted('ROLE_SUPER_ADMIN')) {
            $website->editorialClub();
        }
        return $this->redirectToRoute('admin_settings_website');
    }

    #[AdminRoute(path: '/editorial/new', name: 'website_editorial_new', options: ['methods' => ['GET', 'POST']])]
    public function newWebsitePublication(Request $request, PublicWebsite $website): Response
    {
        return $this->withEditorialWriteLock($request, fn (): Response => $this->renderWebsitePublicationForm($request, $website));
    }

    #[AdminRoute(path: '/editorial/{entityId}/edit', name: 'website_editorial_edit', options: ['methods' => ['GET', 'POST'], 'requirements' => ['entityId' => '\d+']])]
    public function editWebsitePublication(
        Request $request,
        PublicWebsite $website,
        int $entityId
    ): Response {
        return $this->withEditorialWriteLock($request, fn (): Response => $this->renderWebsitePublicationForm($request, $website, $entityId));
    }

    private function renderWebsitePublicationForm(
        Request $request,
        PublicWebsite $website,
        ?int $entityId = null
    ): Response {
        $club = $this->editorialClub($website, $entityId === null ? PermissionAction::CREATE : PermissionAction::EDIT);
        $publication = $entityId === null
            ? new WebsitePublication($club)
            : $this->em->getRepository(WebsitePublication::class)->findOneBy(['id' => $entityId, 'club' => $club]);
        if ($publication === null) {
            throw $this->createNotFoundException();
        }
        if ($this->functionModelEnabled && $entityId !== null && $request->isMethod('POST')) {
            $this->continuity->refresh($publication);
        }

        $image = $publication->getImage();
        $user = $this->getUser();
        $canReadImage = !$this->functionModelEnabled || $image === null
            || ($user instanceof User && $image->getClub()->getId() === $club->getId()
                && $this->folderPermissions->can($user, $club, $image->getFolder(), FileAction::READ));
        $form = $this->createForm(WebsitePublicationType::class, $publication, [
            'club' => $club,
            'image_locked' => !$canReadImage,
        ]);
        $form->handleRequest($request);
        $conflict = false;
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                if ($publication->getKind() === 'event' && $publication->getId() === null) {
                    throw new \DomainException('Neue Veranstaltungen werden zentral unter „Veranstaltungsbuchungen“ angelegt. Hier bleiben nur bestehende Legacy-Veranstaltungen bearbeitbar.');
                }
                if ($entityId !== null && (string) $form->get('version')->getData() !== (string) $publication->getVersion()) {
                    throw new \DomainException('Der Beitrag wurde inzwischen geändert. Bitte neu laden.');
                }
                $publication->record($this->getUser()->getUserIdentifier());
                $this->em->persist($publication);
                $this->em->flush();
                $this->addFlash('success', $publication->isPublished()
                    ? 'Beitrag freigegeben. Sichtbar ab dem Veröffentlichungszeitpunkt, sofern die verknüpfte Veranstaltung freigegeben ist.'
                    : 'Entwurf gespeichert; nicht öffentlich sichtbar.');

                return $this->editorialRedirect($request);
            } catch (OptimisticLockException|\DomainException) {
                $form->addError(new FormError('Gleichzeitige Änderung: Bitte den Beitrag neu laden. Nicht gespeichert.'));
                $conflict = true;
            }
        }

        return $this->render('admin/editorial/edit.html.twig', [
            'form' => $form,
            'publication' => $publication,
            'canRead' => $this->editorialAccess->can(PermissionAction::READ),
            'canReadImage' => $canReadImage,
        ], new Response(status: $conflict ? 409 : ($form->isSubmitted() ? 422 : 200)));
    }

    private function editorialClub(PublicWebsite $website, PermissionAction $action): Club
    {
        if (!$this->editorialAccess->can($action)) {
            throw $this->createAccessDeniedException('Keine Berechtigung für diese Aktion der Website-Redaktion.');
        }
        return $website->club() ?? throw $this->createAccessDeniedException('Kein Website-Verein konfiguriert.');
    }

    private function withEditorialWriteLock(Request $request, callable $operation): Response
    {
        if (!$this->functionModelEnabled || !$request->isMethod('POST')) {
            return $operation();
        }
        $club = $this->clubContext->getClub() ?? throw $this->createAccessDeniedException('Keine Vereinszuordnung vorhanden.');
        return $this->em->getConnection()->transactional(function () use ($club, $operation): Response {
            $this->continuity->lockClub($club);
            $this->em->clear();
            return $operation();
        });
    }

    private function editorialRedirect(Request $request): Response
    {
        if ($this->editorialAccess->can(PermissionAction::READ)) {
            return $this->redirectToRoute('admin_website_editorial', ['_locale' => $request->getLocale()], Response::HTTP_SEE_OTHER);
        }
        return $this->redirectToRoute('app_portal', ['_locale' => $request->getLocale()], Response::HTTP_SEE_OTHER);
    }

    // DICOMA invoice settings EasyAdmin integration v1
    #[AdminRoute(path: '/invoice-settings', name: 'invoice_settings_overview', options: ['methods' => ['GET']])]
    public function invoiceSettingsOverview(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        return $this->render('admin/tuv_invoice_settings_overview_ea.html.twig', [
            'clubs' => array_filter([$this->clubContext->getClub()]),
        ]);
    }

    #[AdminRoute(path: '/invoice-settings/{id}', name: 'invoice_settings_edit', options: ['methods' => ['GET', 'POST']])]
    public function invoiceSettingsEdit(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $club = $em->getRepository(Club::class)->find($id);

        if (!$club) { 
            throw $this->createNotFoundException(); 
        }
        
        if ($this->clubContext->getClub()?->getId() !== $id) {
            throw $this->createAccessDeniedException('Verein entspricht nicht der aktuellen Auswahl.');
        }

        $settings = $em->getRepository(TuvInvoiceSettings::class)->find($id);
        
        if (!$settings) { 
            $settings = new TuvInvoiceSettings($club); 
        }
        
        $form = $this->createForm(TuvInvoiceSettingsType::class, [
            'taxMode' => $settings->getTaxMode() === 'unconfigured' ? null : $settings->getTaxMode(),
            'taxNumber' => $settings->getTaxNumber(),
            'vatId' => $settings->getVatId(),
            'invoiceNote' => $settings->getInvoiceNote(),
            'memberAgencyEnabled' => $settings->isMemberAgencyEnabled(),
        ], ['csrf_token_id' => 'invoice_settings_'.$id])->handleRequest($request);

        if ($request->isMethod('POST') && !$form->isSubmitted()) {
            throw new BadRequestHttpException('Ungültiges Rechnungsformular.');
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            try {
                $settings->configure(
                    $data['taxMode'], $data['taxNumber'], $data['vatId'], $data['invoiceNote']
                );
                
                $settings->setMemberAgencyEnabled($data['memberAgencyEnabled']);
                $em->persist($settings);
                $em->flush();
                
                $this->addFlash('success', 'Rechnungseinstellungen gespeichert.');
                
                return $this->redirectToRoute('admin_invoice_settings_edit', ['id' => $id]);

            } catch (\InvalidArgumentException $e) {
                $form->addError(new FormError($e->getMessage()));
            }
        }

        return $this->render('admin/tuv_invoice_settings_ea.html.twig', [
            'club' => $club, 'form' => $form->createView(),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[AdminRoute(path: '/invoice-agency-confirmations', name: 'invoice_agency_confirmations')]
    public function invoiceAgencyConfirmations(\Doctrine\ORM\EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        
        $groups = $em->getRepository(\App\Entity\TuvBookingGroup::class)->createQueryBuilder('g')
            ->where('g.agencyConfirmedAt IS NOT NULL')
            ->orderBy('g.agencyConfirmedAt', 'DESC')
            ->setMaxResults(200)->getQuery()->getResult();
        
        return $this->render('admin/tuv_agency_confirmations_ea.html.twig', ['groups' => $groups]);
    }

    #[AdminRoute(path: '/training-workflow/{id}/{step}', name: 'training_workflow', options: ['methods' => ['GET']])]
    public function trainingWorkflow(
        int $id,
        string $step
    ): Response {
        
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $route = match ($step) {
            'import' => 'admin_course_import_confirm',
            'generate' => 'admin_course_generate_confirm',
            'plan' => 'admin_course_plan',
            default => throw $this->createNotFoundException('Unbekannte Kursaktion.'),
        };

        return $this->redirectToRoute($route, ['id' => $id], Response::HTTP_SEE_OTHER);
    }

    #[AdminRoute(path: '/training-overview', name: 'training_overview')]
    public function trainingOverview(EntityManagerInterface $em): Response
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_TRAINING_MANAGER')) {
            throw $this->createAccessDeniedException();
        }

        $courses = array_filter($em->getRepository(Courses::class)->findAll(),
            fn (Courses $course): bool => $this->isGranted(\App\Security\Voter\CourseManagementVoter::MANAGE, $course));
        $rows = [];
        $statuses = [];
        $instructorLoad = [];
        $approvalQueue = [];
        $exams = [];
        $qualificationAlerts = [];
        $now = new \DateTimeImmutable('today');
        $active = 0;
        
        foreach ($courses as $course) {
            $status = $course->getStatus()->value;
            $statuses[$status] = ($statuses[$status] ?? 0) + 1;
            $participants = $course->getParticipants()->count();
            $capacity = $course->getMaxParticipants();
            $appointments = 0;
        
            foreach ($course->getSchedule() as $appointment) {
        
                if ($appointment->getStartDate() && $appointment->getStartDate()->format('Y-m-d') >= $now->format('Y-m-d')) {
                    ++$appointments;
                    if ($appointment->getTrainingUnitType()?->isExamination()) {
                        $results = $em->getRepository(\App\Entity\TrainingUnitResult::class)->findBy(['schedule' => $appointment]);
                        $exams[] = [
                            'id' => $appointment->getId(),
                            'course' => (string) $course,
                            'title' => (string) $appointment,
                            'date' => $appointment->getStartDate()->format('d.m.Y'),
                            'results' => count($results),
                            'participants' => $participants,
                        ];
                    }
                }
            
                $instructor = $appointment->getInstructor();
            
                if ($instructor !== null) {
                    $id = $instructor->getId();

                    if ($id !== null) {
                        if (!isset($instructorLoad[$id])) {
                            $instructorLoad[$id] = ['name' => (string) $instructor, 'appointments' => 0, 'hours' => 0.0];
                        }
                        ++$instructorLoad[$id]['appointments'];
                        $instructorLoad[$id]['hours'] += $appointment->getDurationHours() ?? 0.0;
                    }
                }
            }

            if ($appointments > 0) ++$active;
            if ($appointments > 0 && $course->getSchedulePublishedAt() === null) {
                $approvalQueue[] = ['id' => $course->getId(), 'title' => (string) $course, 'appointments' => $appointments];
            }
            foreach ($course->getParticipants() as $participant) {
                $member = $participant->getMember();
                if ($member === null) {
                    continue;
                }
                $qualifications = $em->getRepository(\App\Entity\MemberQualification::class)->findBy(['member' => $member]);
                foreach ($qualifications as $qualification) {
                    if ($qualification->isExpired() || $qualification->expiresWithinDays(90)) {
                        $qualificationAlerts[$participant->getId().':'.$qualification->getId()] = [
                            'participant' => $participant->getFullName(),
                            'qualification' => (string) $qualification->getQualification(),
                            'validUntil' => $qualification->getValidUntil()?->format('d.m.Y'),
                            'expired' => $qualification->isExpired(),
                        ];
                    }
                }
            }
            
            $rows[] = ['id' => $course->getId(), 'title' => (string) $course,
                'status' => $status, 'participants' => $participants,
                'capacity' => $capacity, 'upcoming' => $appointments,
                'occupancy' => $capacity && $capacity > 0 ? round(100 * $participants / $capacity) : null];
        }

        usort($rows, static fn (array $a, array $b): int => $b['upcoming'] <=> $a['upcoming']);
        uasort($instructorLoad, static fn (array $a, array $b): int => $b['hours'] <=> $a['hours']);
        usort($exams, static fn (array $first, array $second): int => strcmp($first['date'], $second['date']));
        
        return $this->render('admin/training/overview.html.twig', [
            'courses' => $rows, 'statuses' => $statuses,
            'instructors' => $instructorLoad, 'active' => $active,
            'total' => count($rows),
            'approvalQueue' => $approvalQueue,
            'exams' => $exams,
            'qualificationAlerts' => array_values($qualificationAlerts),
        ]);
    }

    public function configureMenuItems(): iterable
    {
        $editorialRoute = $this->editorialAccess->can(PermissionAction::READ)
            ? 'admin_website_editorial'
            : ($this->editorialAccess->can(PermissionAction::CREATE) ? 'admin_website_editorial_new' : null);
        $websiteSettings = $this->functionModelEnabled
            ? $this->editorialAccess->canViewSettings()
            : $this->publicWebsite->canManageEditorial();
        $eventRoute = $this->editorialAccess->can(PermissionAction::READ)
            ? 'admin_club_events'
            : ($this->editorialAccess->can(PermissionAction::CREATE) ? 'admin_club_event_new' : null);
        if (!$this->isGranted('ROLE_ADMIN')) {
            return [
                ...($websiteSettings
                    ? [MenuItem::linkToRoute('Website-Anzeige', 'fa fa-globe', 'admin_settings_website')] : []),
                ...($this->barAccess->isManager() && $this->barAccess->selectedClubIsMembershipClub()
                    ? [MenuItem::linkToRoute('Thekendienst-Jahresregel', 'fa fa-mug-hot', 'admin_settings_bar_services')] : []),
                ...($editorialRoute !== null ? [
                    MenuItem::section('Website & Redaktion'),
                    MenuItem::linkToRoute('Seiten, Artikel und Veranstaltungen', 'fa fa-newspaper', $editorialRoute),
                ] : []),
                ...($this->functionModelEnabled && $eventRoute !== null
                    ? [MenuItem::linkToRoute('Veranstaltungsbuchungen', 'fa fa-calendar-check', $eventRoute)] : []),
                MenuItem::linkToRoute('Dateiverwaltung', 'fa fa-folder-open', 'admin_media_index')
                    ->setPermission('ROLE_PRESS_OFFICER'),
            ];
        }

        return [
            MenuItem::linkToDashboard('Dashboard', 'fa fa-house'),
            MenuItem::linkToRoute('Veranstaltungskalender', 'fa fa-calendar-days', 'club_calendar'),
            ...($editorialRoute !== null ? [
                MenuItem::section('Website & Redaktion'),
                MenuItem::linkToRoute('Seiten, Artikel und Veranstaltungen', 'fa fa-newspaper', $editorialRoute),
                ] : []),

            MenuItem::section('Tägliche Verwaltung'),
            ...(!$this->functionModelEnabled || $eventRoute !== null
                ? [MenuItem::linkToRoute('Veranstaltungsbuchungen', 'fa fa-calendar-check', $eventRoute ?? 'admin_club_events')] : []),
            MenuItem::linkToRoute('Dateiverwaltung', 'fa fa-folder-open', 'admin_media_index'),
            MenuItem::linkToRoute('Thekendienste und Thekenkasse', 'fa fa-mug-hot', 'admin_portal_bar_duty')
                ->setPermission('ROLE_BAR_MANAGER'),

            MenuItem::subMenu('Mitglieder & Verein', 'fa fa-building')
                ->setSubItems([
                    MenuItem::linkTo(MemberCrudController::class, 'Mitglieder', 'fa fa-users'),
                    MenuItem::linkToRoute('Rollenvergabe', 'fa fa-users-gear', 'admin_delegated_roles'),
                    MenuItem::linkTo(PersonCrudController::class, 'Nichtmitglieder und Gäste', 'fa fa-address-book')
                        ->setPermission('ROLE_SUPER_ADMIN'),
                    MenuItem::linkTo(MemberQualificationCrudController::class, 'Mitgliedsqualifikationen', 'fa fa-id-card')
                        ->setPermission('ROLE_SUPER_ADMIN'),
            ]),

            MenuItem::subMenu('Mannschaften & Turniere', 'fa fa-trophy')
                ->setSubItems([
                    MenuItem::linkToRoute('Mannschaften und Kader', 'fa fa-people-group', 'competition_teams'),
                    MenuItem::linkToRoute('Turniere und Historie', 'fa fa-trophy', 'competition_tournaments'),
                ]),

            MenuItem::subMenu('Ausbildung & Kurse', 'fa fa-graduation-cap')
                ->setSubItems([
                    MenuItem::linkToRoute('Ausbildungsportal', 'fa fa-graduation-cap', 'admin_portal_training_portal'),
                    MenuItem::linkTo(CoursesCrudController::class, 'Kurse verwalten', 'fa fa-book-open'),
                    MenuItem::linkTo(CourseParticipantCrudController::class, 'Kursteilnehmer', 'fa fa-users'),
                    MenuItem::linkToRoute('Kurskalender', 'fa fa-calendar', 'admin_course_calendar'),
                    MenuItem::linkToRoute('Ausbildungsübersicht', 'fa fa-chart-line', 'admin_training_overview'),
                    MenuItem::linkToRoute('Ausbildungsaufgaben', 'fa fa-list-check', 'admin_instructor_tasks')
                        ->setPermission('ROLE_SUPER_ADMIN'),
                    MenuItem::linkTo(ScheduleCrudController::class, 'Kurstermine', 'fa fa-calendar-days')
                        ->setPermission('ROLE_SUPER_ADMIN'),
                    MenuItem::linkTo(BookingCrudController::class, 'Terminbuchungen', 'fa fa-ticket')
                        ->setPermission('ROLE_SUPER_ADMIN'),
                    MenuItem::linkTo(TrainingUnitResultCrudController::class, 'Ausbildungsnachweise', 'fa fa-clipboard-check')
                        ->setPermission('ROLE_SUPER_ADMIN'),
            ]),

            MenuItem::subMenu('Ausrüstung & Verleih', 'fa fa-toolbox')
                ->setPermission('ROLE_SUPER_ADMIN')
                ->setSubItems([
                    MenuItem::linkTo(EquipmentItemCrudController::class, 'Equipment', 'fa fa-boxes-stacked')
                        ->setPermission('ROLE_SUPER_ADMIN'),
                    MenuItem::linkTo(RegulatorCrudController::class, 'Atemregler', 'fa fa-gauge')
                        ->setPermission('ROLE_SUPER_ADMIN'),
                    MenuItem::linkTo(TankCrudController::class, 'Tauchgeräte', 'fa fa-gauge')
                        ->setPermission('ROLE_SUPER_ADMIN'),
                    MenuItem::linkTo(EquipmentBookingCrudController::class, 'Reservierungen', 'fa fa-layer-group')
                        ->setPermission('ROLE_SUPER_ADMIN'),
            ]),

            MenuItem::subMenu('Flaschenprüfungen (TÜV)', 'fa fa-clipboard-check')
                ->setPermission('ROLE_SUPER_ADMIN')
                ->setSubItems([
                    MenuItem::linkToRoute('Prüfungsübersicht','fa fa-gauge-high','admin_equipment_center')
                        ->setPermission('ROLE_SUPER_ADMIN'),
                    MenuItem::linkTo(MemberTankCheckBookingCrudController::class,'TÜV-Anmeldungen','fa fa-clipboard-check')
                        ->setAction(Action::INDEX)
                        ->setPermission('ROLE_SUPER_ADMIN'),
                    MenuItem::linkTo(TankCheckCrudController::class,'TÜV-Termine','fa fa-calendar-check')
                        ->setPermission('ROLE_SUPER_ADMIN'),
                    MenuItem::linkTo(TankCheckArticleCrudController::class, 'TÜV-Preise', 'fa fa-tags')
                        ->setPermission('ROLE_SUPER_ADMIN'),
                    MenuItem::linkTo(TuvNotificationCrudController::class,'TÜV-Nachrichten / Postausgang','fa fa-envelope-open-text')
                        ->setPermission('ROLE_SUPER_ADMIN'),
            ]),

            MenuItem::section('Stammdaten & Einstellungen')
                ->setPermission('ROLE_SUPER_ADMIN'),

            MenuItem::subMenu('Stammdaten Ausbildung', 'fa fa-layer-group')
                ->setPermission('ROLE_SUPER_ADMIN')
                ->setSubItems([
                    MenuItem::linkTo(ScheduleTemplateCrudController::class, 'Planungsvorlagen', 'fa fa-layer-group'),
                    MenuItem::linkTo(TrainingUnitTypeCrudController::class, 'Ausbildungseinheiten', 'fa fa-list-check'),
                    MenuItem::linkTo(QualificationCrudController::class, 'Qualifikationskatalog', 'fa fa-certificate'),
            ]),

            MenuItem::subMenu('Stammdaten Ausrüstung', 'fa fa-boxes-stacked')
                ->setPermission('ROLE_SUPER_ADMIN')
                ->setSubItems([
                    MenuItem::linkTo(EquipmentLocationCrudController::class, 'Lagerorte', 'fa fa-location-dot'),
                    MenuItem::linkTo(EquipmentCategoryCrudController::class, 'Ausrüstungsarten', 'fa fa-list')
                        ->setPermission('ROLE_SUPER_ADMIN'),
                    MenuItem::linkTo(EquipmentSubTypeCrudController::class, 'Ausrüstungsunterarten', 'fa fa-sitemap')
                        ->setPermission('ROLE_SUPER_ADMIN'),
                    MenuItem::linkTo(EquipmentManufacturerCrudController::class, 'Hersteller je Verein', 'fa fa-industry')
                        ->setPermission('ROLE_SUPER_ADMIN'),
                    MenuItem::linkTo(EquipmentSizeCrudController::class, 'Größen je Hersteller', 'fa fa-ruler')
                        ->setPermission('ROLE_SUPER_ADMIN'),
                    MenuItem::linkTo(EquipmentModelCrudController::class, 'Ausrüstungsmodelle', 'fa fa-tags')
                        ->setPermission('ROLE_SUPER_ADMIN'),
                    MenuItem::linkTo(RegulatorModelCrudController::class, 'Atemreglermodelle', 'fa fa-gears')
                        ->setPermission('ROLE_SUPER_ADMIN'),
                    MenuItem::linkTo(EquipmentAttributeCrudController::class, 'Kategorie-Merkmale', 'fa fa-sliders')
                        ->setPermission('ROLE_SUPER_ADMIN'),
                    MenuItem::linkTo(VendorCrudController::class, 'Lieferanten', 'fa fa-truck')
                        ->setPermission('ROLE_SUPER_ADMIN'),
            ]),
			
			MenuItem::subMenu('Einstellungen','fa fa-gear')
                ->setSubItems([
                    MenuItem::linkTo(ClubCrudController::class, 'Vereinsdaten', 'fa fa-building')
                        ->setPermission('ROLE_SUPER_ADMIN'),
                    MenuItem::linkToRoute('Vereinsfunktionen verwalten', 'fa fa-user-shield', 'admin_club_functions')
                        ->setPermission('ROLE_SUPER_ADMIN'),
//                    MenuItem::linkTo(MemberSportCrudController::class, 'Sportzuordnungen', 'fa fa-link')
//                        ->setPermission('ROLE_SUPER_ADMIN'),
                    MenuItem::linkTo(UserCrudController::class, 'Benutzerverwaltung', 'fa fa-users-gear')
                        ->setPermission('ROLE_SUPER_ADMIN'),
                    MenuItem::linkToRoute('Mitgliedschaftsregeln', 'fa fa-address-card', 'admin_settings_membership')
                        ->setPermission('ROLE_SUPER_ADMIN'),
                    MenuItem::linkToRoute('Beitragsklassen', 'fa fa-coins', 'admin_settings_contributions')
                        ->setPermission('ROLE_SUPER_ADMIN'),
                    MenuItem::linkToRoute('Mitgliedschaften und Beiträge', 'fa fa-address-card', 'membership_admin')
                        ->setPermission('ROLE_SUPER_ADMIN'),
                    MenuItem::linkToRoute('Sportkonfiguration', 'fa fa-futbol', 'admin_settings_sports_configuration')
                        ->setPermission('ROLE_SUPER_ADMIN'),
                    MenuItem::linkToRoute('TÜV-Benachrichtigungen', 'fa fa-bell', 'admin_settings_tuv_notifications')
                        ->setPermission('ROLE_SUPER_ADMIN'),
                    ...($this->clubContext->getClub() !== null
                        && ($websiteSettings || (!$this->functionModelEnabled && $this->isGranted('ROLE_SUPER_ADMIN')))
                        ? [MenuItem::linkToRoute('Website', 'fa fa-globe', 'admin_settings_website')
                            ->setPermission('ROLE_SUPER_ADMIN')] : []),
                    ...($this->barAccess->isManager() && $this->barAccess->selectedClubIsMembershipClub()
                        ? [MenuItem::linkToRoute('Thekendienst', 'fa fa-mug-hot', 'admin_settings_bar_services')
                            ->setPermission('ROLE_SUPER_ADMIN')] : []),
                    MenuItem::linkToRoute('TÜV-Rechnungseinstellungen', 'fa fa-file-invoice', 'admin_invoice_settings_overview')
                        ->setPermission('ROLE_SUPER_ADMIN'),
//                    MenuItem::linkToRoute('Kurs-Spartenzuordnungen prüfen', 'fa fa-list-check', 'admin_course_division_review'),
//                    MenuItem::linkTo(MemberCrudController::class, 'Mitglieder ohne Vereinszuordnung prüfen', 'fa fa-user-check')
//                        ->setQueryParameter('unassigned', '1')
//                        ->setPermission('ROLE_SUPER_ADMIN'),
//                    MenuItem::linkTo(CoursesCrudController::class, 'Kurse ohne Vereinszuordnung prüfen', 'fa fa-book-open')
//                        ->setQueryParameter('unassigned', '1')
//                        ->setPermission('ROLE_SUPER_ADMIN'),
                ]),
        ];
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        $name = $user->getUserIdentifier();
        if ($user instanceof User && $user->getPerson()?->getFullName()) {
            $name = $user->getPerson()->getFullName();
        }

        $menu = parent::configureUserMenu($user)
            ->setName($name)
            ->setAvatarUrl('/images/images/Diversworld_Viking.png')
            ->addMenuItems([
                MenuItem::linkToUrl('Mein Konto', 'fa fa-user', $this->generateUrl('admin_account_profile')),
                MenuItem::linkToUrl('Kennwort ändern', 'fa fa-key', $this->generateUrl('admin_account_password')),
            ]);
        $dto = $menu->getAsDto();
        $dto->setItems(array_values(array_filter($dto->getItems(), static fn (MenuItemDto $item): bool =>
            $item->getType() !== MenuItemDto::TYPE_LOGOUT
            && !($item->getType() === MenuItemDto::TYPE_SECTION && in_array($item->getLabel(), [null, ''], true))
        )));
        return $menu;
    }

	public function configureCrud(): Crud
	{
		return Crud::new()
			->overrideTemplate(
				'layout',
				'admin/layout.html.twig'
			);
	}
}
