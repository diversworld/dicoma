<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Admin\DashboardController;
use App\Entity\Club;
use App\Entity\ContributionClass;
use App\Entity\Member;
use App\Entity\MemberDocument;
use App\Entity\MembershipApplication;
use App\Entity\MembershipCharge;
use App\Entity\MembershipEvent;
use App\Entity\MembershipPayment;
use App\Entity\MembershipPolicy;
use App\Entity\BarDutyPolicy;
use App\Entity\WebsiteDisplaySettings;
use App\Entity\Person;
use App\Entity\SepaMandate;
use App\Entity\User;
use App\Enum\MembershipStatus;
use App\Form\BarDutyPolicyType;
use App\Form\ConfigurationClubSelectionType;
use App\Form\ConfigurationYearSelectionType;
use App\Form\ContributionClassType;
use App\Form\MemberContactType;
use App\Form\MembershipPolicyType;
use App\Form\WebsiteDisplaySettingsType;
use App\Security\Voter\MemberManagementVoter;
use App\Service\MembershipFinanceService;
use App\Service\MembershipService;
use App\Service\BarDutyAccess;
use App\Service\BarDutyWorkflow;
use App\Service\PublicWebsite;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type as F;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;

final class MembershipController extends AbstractController
{
    private const ADMIN_ROUTE_DEFAULTS = [
        EA::ROUTE_CREATED_BY_EASYADMIN => true,
        EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class,
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MembershipService $memberships,
        private readonly MembershipFinanceService $finance,
        private readonly FormFactoryInterface $forms,
    ) {}

    #[Route('/membership/apply', name: 'membership_application_clubs', methods: ['GET'])]
    public function applicationClubs(): Response
    {
        return $this->render('membership/clubs.html.twig', ['policies' => $this->em->getRepository(MembershipPolicy::class)->findBy(['applicationsEnabled' => true])]);
    }

    #[Route('/membership/apply/{id}', name: 'membership_apply', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function apply(Club $club, Request $request): Response
    {
        if (!$club->isActive() || !$this->memberships->policy($club)?->isApplicationsEnabled()) { 
            throw $this->createNotFoundException(); 
        }
        
        $application = new MembershipApplication($club);

        $builder = $this->forms->createNamedBuilder('membership_application', F\FormType::class, $application, ['data_class' => MembershipApplication::class]);
        
        foreach (['firstname' => 'Vorname', 'lastname' => 'Nachname', 'street' => 'Straße / Hausnummer', 'postalCode' => 'Postleitzahl', 'city' => 'Ort'] as $name => $label) {
            $builder->add($name, F\TextType::class, ['label' => $label]);
        }

        $builder->add('email', F\EmailType::class, ['label' => 'Kontakt-E-Mail'])
            ->add('birthday', F\DateType::class, ['label' => 'Geburtsdatum', 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('privacy', F\CheckboxType::class, ['label' => 'Ich habe den Hinweis zur Verarbeitung meines Antrags gelesen und beantrage die Aufnahme.', 'mapped' => false, 'constraints' => [new Assert\IsTrue()]]);
        $form = $builder->getForm()->handleRequest($request);

        if ($response = $this->process($form, fn () => $this->memberships->submit($application), 'membership_application_clubs', [], 'Antrag eingereicht. Die Verwaltung prüft Ihre Identität; eine Mitgliedschaft entsteht erst durch Genehmigung.')) {
            return $response;
        }

        return $this->privatePage('membership/form.html.twig', ['title' => 'Mitgliedsantrag – ' . $club->getName(), 'form' => $form->createView(), 'explanation' => 'Ihre Angaben werden ausschließlich für die manuelle Identitätsprüfung und Entscheidung durch den Verein verarbeitet. Der Antrag erstellt weder automatisch ein Konto noch Mitgliedsrechte. Für Auskunft, Berichtigung oder Rücknahme wenden Sie sich an die Vereinsverwaltung.']);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/membership', name: 'membership_admin', defaults: self::ADMIN_ROUTE_DEFAULTS, methods: ['GET'])]
    public function administration(): Response
    {
        $user = $this->account();
        $clubs = $this->isGranted('ROLE_SUPER_ADMIN')
            ? $this->em->getRepository(Club::class)->findAll()
            : array_filter([$user->getMember()?->getClub()]);

        return $this->adminPage('membership/admin_clubs.html.twig', ['clubs' => $clubs]);
    }

    #[Route('/admin/configuration', name: 'admin_configuration', defaults: self::ADMIN_ROUTE_DEFAULTS, methods: ['GET', 'POST'])]
    #[Route('/admin/settings/bar-services', name: 'admin_settings_bar_services', defaults: self::ADMIN_ROUTE_DEFAULTS + ['configuration_section' => 'bar'], methods: ['GET', 'POST'])]
    public function configuration(Request $request, PublicWebsite $website, BarDutyAccess $barAccess, BarDutyWorkflow $barWorkflow): Response
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_PRESS_OFFICER') && !$this->isGranted('ROLE_BAR_MANAGER')) {
            throw $this->createAccessDeniedException();
        }

        $barOnly = $request->attributes->get('configuration_section') === 'bar';
        if ($barOnly && !$barAccess->isManager()) {
            throw $this->createAccessDeniedException('Nur die Thekenleitung darf diese Einstellungen verwalten.');
        }
        $user = $this->account();
        $ownClub = $user->getMember()?->getClub();
        if ($barOnly && $request->query->has('club')) {
            throw $this->createNotFoundException('Für die Thekendiensteinstellungen gilt der eigene Verein.');
        }
        $clubs = $this->isGranted('ROLE_SUPER_ADMIN') ? $this->em->getRepository(Club::class)->findAll() : array_filter([$ownClub]);
        $club = null;

        if ($this->isGranted('ROLE_ADMIN')) {
            $clubId = $request->query->getInt('club', $ownClub?->getId() ?? 0);
            if ($clubId > 0) {
                $club = $this->em->find(Club::class, $clubId) ?? throw $this->createNotFoundException();
                $this->assertClub($club);
            }
        }

        $parameters = $club ? ['club' => $club->getId()] : [];
        $websiteForm = $policyForm = $classForm = null;
        $classForms = [];
        $classEditSubmitted = false;

        if (!$barOnly && $website->canManageEditorial()) {
            $websiteClub = $website->editorialClub();
            $display = $this->em->find(WebsiteDisplaySettings::class, $websiteClub->getId()) ?? new WebsiteDisplaySettings($websiteClub);
            $websiteForm = $this->createForm(WebsiteDisplaySettingsType::class, $display)->handleRequest($request);

            if ($response = $this->process($websiteForm, function () use ($display): void {
                $this->em->persist($display); 
                $this->em->flush();
            }, 
            'admin_configuration', $parameters)) { 
                return $response; 
            }
        }

        if (!$barOnly && $club !== null) {
            $policy = $this->memberships->policy($club) ?? new MembershipPolicy($club);
            $policyForm = $this->policyForm($policy)->handleRequest($request);

            if ($response = $this->process($policyForm, function () use ($policy): void {
                $policy->validateRules(); 
                $this->em->persist($policy); 
                $this->em->flush();
            }, 
            'admin_configuration', $parameters)) { 
                return $response; 
            }
        
            $class = new ContributionClass($club);
            $classForm = $this->contributionForm($class)->handleRequest($request);
        
            if ($response = $this->process($classForm, fn () => $this->saveContribution($class), 'admin_configuration', $parameters)) { 
                return $response; 
            }
        
            foreach ($this->em->getRepository(ContributionClass::class)->findBy(['club' => $club], ['name' => 'ASC']) as $existing) {

                $form = $this->contributionForm($existing, 'contribution_class_'.$existing->getId())->handleRequest($request);

                if ($response = $this->process($form, fn () => $this->saveContribution($existing), 'admin_configuration', $parameters)) { 
                    return $response; 
                }
                
                $classEditSubmitted = $classEditSubmitted || $form->isSubmitted();
                $classForms[] = ['class' => $existing, 'form' => $form->createView()];
            }
        }
        
        $yearRaw = $request->query->getString('year', date('Y'));
        
        if (!preg_match('/^\d{4}$/D', $yearRaw) || (int) $yearRaw < 2000 || (int) $yearRaw > 2100) { 
            throw $this->createNotFoundException('Ungültiges Jahr.'); 
        }
        
        $year = (int) $yearRaw;
        $barPolicy = $barForm = $yearForm = null;
        $clubForm = !$barOnly && $this->isGranted('ROLE_SUPER_ADMIN')
            ? $this->forms->createNamed('', ConfigurationClubSelectionType::class, ['club' => $club, 'year' => (string) $year], ['clubs' => $clubs])
            : null;
        $canBar = $barAccess->isManager() && $ownClub !== null;
        
        if ($barOnly && !$canBar) {
            throw $this->createAccessDeniedException('Vereinszuordnung erforderlich.');
        }
        if ($canBar) {
            $barAccess->requireManager();
            $barPolicy = $this->em->getRepository(BarDutyPolicy::class)->findOneBy(['club' => $barAccess->club(), 'year' => $year]);
            $yearForm = $this->forms->createNamed('', ConfigurationYearSelectionType::class, [
                'club' => !$barOnly && $club ? (string) $club->getId() : null,
                'year' => $year,
            ], ['include_club' => !$barOnly && $club !== null]);
            $barForm = $this->createForm(BarDutyPolicyType::class, [
                'minutes' => $barPolicy?->getRequiredMinutes(),
                'cash' => $barPolicy?->isCashEnabled() ?? false,
                'reason' => '',
            ])->handleRequest($request);

            if ($barForm->isSubmitted()) {
                $submittedBarData = $request->request->all()['bar_duty_policy'] ?? null;
                $token = is_array($submittedBarData) && is_string($submittedBarData['_token'] ?? null) ? $submittedBarData['_token'] : '';
                if (!$this->isCsrfTokenValid('bar_duty_policy_0', $token)) {
                    throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
                }
            }
            if ($response = $this->process($barForm, function () use ($barForm, $barWorkflow, $year): void {
                $data = $barForm->getData();
                $barWorkflow->configure($year, $data['minutes'], $data['cash'], $data['reason']);
            }, $barOnly ? 'admin_settings_bar_services' : 'admin_configuration', ($barOnly ? [] : $parameters) + ['year' => $year], 'Jahresregel gespeichert.')) {
                return $response;
            }
        }
        
        if ($request->isMethod('POST') && !($websiteForm?->isSubmitted() || $policyForm?->isSubmitted() || $classForm?->isSubmitted()
            || $classEditSubmitted || $barForm?->isSubmitted())) {
            throw $this->createAccessDeniedException('Unbekannter oder nicht erlaubter Konfigurationsbereich.');
        }
        
        $response = $this->adminPage('admin/configuration.html.twig', [
            'club' => $club, 
            'ownClub' => $ownClub, 
            'clubForm' => $clubForm?->createView(),
            'websiteForm' => $websiteForm?->createView(), 
            'policyForm' => $policyForm?->createView(),
            'classForm' => $classForm?->createView(), 
            'classForms' => $classForms,
            'canBar' => $canBar, 
            'barPolicy' => $barPolicy, 
            'barForm' => $barForm?->createView(),
            'yearForm' => $yearForm?->createView(),
            'year' => $year,
            'barOnly' => $barOnly,
        ]);

        if ($request->isMethod('POST')) { $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY); }
        
        return $response;
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/membership/club/{id}', name: 'membership_club', requirements: ['id' => '\d+'], defaults: self::ADMIN_ROUTE_DEFAULTS, methods: ['GET', 'POST'])]
    public function club(Club $club, Request $request): Response
    {
        $this->assertClub($club);
        
        $policy = $this->memberships->policy($club) ?? new MembershipPolicy($club);
        $policyForm = $this->policyForm($policy)->handleRequest($request);

        if ($response = $this->process($policyForm, function () use ($policy): void {
            $policy->validateRules();
            $this->em->persist($policy);
            $this->em->flush();
        }, 
        'membership_club', ['id' => $club->getId()])) { 
            return $response; 
        }

        $class = new ContributionClass($club);
        $classForm = $this->contributionForm($class)->handleRequest($request);

        if ($response = $this->process($classForm, function () use ($class, $club): void {
            $this->saveContribution($class);
        }, 
        'membership_club', ['id' => $club->getId()])) { 
            return $response; 
        }
        
        return $this->adminPage('membership/club.html.twig', [
            'club' => $club, 'policyForm' => $policyForm->createView(), 'classForm' => $classForm->createView(),
            'classes' => $this->em->getRepository(ContributionClass::class)->findBy(['club' => $club]),
            'applications' => $this->em->getRepository(MembershipApplication::class)->findBy(['club' => $club], ['id' => 'DESC']),
            'members' => $this->em->getRepository(Member::class)->findBy(['club' => $club], ['id' => 'DESC']),
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/membership/contribution/{id}', name: 'membership_contribution_edit', requirements: ['id' => '\d+'], defaults: self::ADMIN_ROUTE_DEFAULTS, methods: ['GET', 'POST'])]
    public function editContribution(ContributionClass $class, Request $request): Response
    {
        $this->assertClub($class->getClub());

        if ($request->isMethod('GET')) {
            return $this->redirectToRoute('admin_configuration', ['club' => $class->getClub()->getId()]);
        }

        $form = $this->contributionForm($class)->handleRequest($request);
        
        if ($response = $this->process($form, function () use ($class): void {
            $this->saveContribution($class);
        }, 
        'membership_club', ['id' => $class->getClub()->getId()])) { 
            return $response; 
        }
        
        return $this->adminPage(
            'membership/form.html.twig', [
                'title' => 'Beitragsklasse bearbeiten', 
                'form' => $form->createView(), 
                'explanation' => 'Änderungen gelten nur für zukünftige Forderungen. Bereits erhobene Beiträge und Zahlungen bleiben unverändert. Deaktivierte Klassen dürfen nicht für neue Jahresforderungen verwendet werden.'
            ]
        );
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/membership/application/{id}', name: 'membership_application_decide', requirements: ['id' => '\d+'], defaults: self::ADMIN_ROUTE_DEFAULTS, methods: ['GET', 'POST'])]
    public function decide(MembershipApplication $application, Request $request): Response
    {
        $this->assertClub($application->getClub());
        
        $builder = $this->forms->createNamedBuilder('membership_decision')
            ->add('decision', F\ChoiceType::class, [
                'label' => 'Entscheidung', 
                'choices' => ['Genehmigen' => 'approve', 'Ablehnen' => 'reject']
                ]
            )
            ->add('note', F\TextareaType::class, [
                'label' => 'Ergebnis der manuellen Identitätsprüfung / Entscheidungsbegründung', 
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 1000)]
                ]
            )
            ->add('confirmed', F\CheckboxType::class, [
                'label' => 'Ich habe Identität und Entscheidung geprüft.', 
                'constraints' => [new Assert\IsTrue()]
                ]
            );
        
            if ($this->isGranted('ROLE_SUPER_ADMIN')) {
                $builder
                    ->add('person', EntityType::class, [
                        'label' => 'Geprüfte vorhandene Person wiederverwenden (sonst neue Identität)', 
                        'class' => Person::class, 'required' => false
                        ]
                    )
                    ->add('separateIdentity', F\CheckboxType::class, [
                        'label' => 'Eine andere Person mit gemeinsam genutzter Kontaktadresse wurde ausdrücklich geprüft.', 
                        'required' => false
                        ]
                    );
            }
        
        $form = $builder->getForm()->handleRequest($request);
        
        if ($response = $this->process($form, function () use ($application, $form): void {
            $data = $form->getData();
        
            if ($data['decision'] === 'approve') {
                $this->memberships->approve($application, $data['person'] ?? null, $this->account()->getUserIdentifier(), $data['note'], $data['separateIdentity'] ?? false);
            } else {
                $this->memberships->reject($application, $this->account()->getUserIdentifier(), $data['note']);
            }

        }, 'membership_club', ['id' => $application->getClub()->getId()])) { 
                return $response; 
            }
        
        return $this->adminPage('membership/application.html.twig', [
            'application' => $application, 
            'form' => $form->createView()
            ]
        );
    }

    #[IsGranted('ROLE_MEMBER')]
    #[Route('/profile/contact', name: 'membership_contact', methods: ['GET', 'POST'])]
    public function contact(Request $request): Response
    {
        $member = $this->ownMember();
        $form = $this->createForm(MemberContactType::class, $member)->handleRequest($request);
        
        if ($response = $this->process($form, function () use ($member): void {
            $this->em->wrapInTransaction(function () use ($member): void {
                $this->memberships->record($member, 'contact_updated', $this->account()->getUserIdentifier(), 'Eigene Kontakt- und Notfalldaten geändert. Konto-E-Mail und Verwaltungsdaten bleiben unverändert.');
            });
        }, 'membership_self')) { return $response; }
        
        return $this->privatePage('membership/form.html.twig', ['title' => 'Kontakt- und Notfalldaten bearbeiten', 'form' => $form->createView(), 'explanation' => 'Name, Geburtstag, Mitgliedsnummer, Status, Vereinsfunktionen, Beitragszuordnung und Konto-E-Mail werden ausschließlich durch die zuständige Verwaltung geändert. Änderungen der Kontaktadresse betreffen Ihre zentrale Person auch bei weiteren Vereinsmitgliedschaften.']);
    }

    #[IsGranted('ROLE_MEMBER')]
    #[Route('/profile/membership', name: 'membership_self', methods: ['GET', 'POST'])]
    public function selfService(Request $request): Response
    {
        return $this->memberPage($this->ownMember(), $request, false);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/membership/member/{id}', name: 'membership_member', requirements: ['id' => '\d+'], defaults: self::ADMIN_ROUTE_DEFAULTS, methods: ['GET', 'POST'])]
    public function member(Member $member, Request $request): Response
    {
        $this->denyAccessUnlessGranted(MemberManagementVoter::MANAGE, $member);
        return $this->memberPage($member, $request, true);
    }

    private function memberPage(Member $member, Request $request, bool $admin): Response
    {
        $route = $admin ? 'membership_member' : 'membership_self';
        $parameters = $admin ? ['id' => $member->getId()] : [];
        $actor = $this->account()->getUserIdentifier();
        $forms = [];
        $forms['termination'] = $this->forms->createNamedBuilder('membership_termination')->getForm()->handleRequest($request);

        if ($response = $this->process($forms['termination'], fn () => $this->memberships->requestTermination($member, $actor, new \DateTimeImmutable('today')), $route, $parameters)) { 
            return $response; 
        }

        if ($admin) {
            $club = $member->getClub();
            
            if ($club === null) { 
                throw $this->createAccessDeniedException('Keine Vereinszuordnung.'); 
            }

            $forms['status'] = $this->forms->createNamedBuilder('membership_status', F\FormType::class, ['status' => $member->getMembershipStatus()])
                ->add('status', F\ChoiceType::class, ['label' => 'Mitgliedschaftswechsel', 'choices' => array_filter(MembershipStatus::choices(), static fn ($status) => $status !== MembershipStatus::FORMER)])->getForm()->handleRequest($request);

            if ($response = $this->process($forms['status'], fn () => $this->memberships->changeStatus($member, $forms['status']->getData()['status'], $actor), $route, $parameters)) { 
                return $response; 
            }

            $forms['archive'] = $this->forms->createNamedBuilder('membership_archive')->getForm()->handleRequest($request);

            if ($response = $this->process($forms['archive'], fn () => $this->memberships->archive($member, $actor), $route, $parameters)) { 
                return $response; 
            }

            $forms['assignment'] = $this->forms->createNamedBuilder('billing_assignment', F\FormType::class, [
                'class' => $member->getContributionClass(), 'payer' => $member->getFamilyPayer(),
                'discount' => $member->getContributionDiscountCents(), 'reason' => $member->getContributionDiscountReason(),
            ])
                ->add('class', EntityType::class, ['label' => 'Beitragsklasse (beim Familienmitglied leer)', 'class' => ContributionClass::class, 'choices' => $this->em->getRepository(ContributionClass::class)->findBy(['club' => $club, 'active' => true]), 'required' => false])
                ->add('payer', EntityType::class, ['label' => 'Familienzahler (sonst leer)', 'class' => Member::class, 'choices' => $this->em->getRepository(Member::class)->findBy(['club' => $club, 'archivedAt' => null]), 'required' => false])
                ->add('discount', F\MoneyType::class, $this->money('Jährliche Ermäßigung EUR'))
                ->add('reason', F\TextareaType::class, ['label' => 'Ermäßigungsgrund', 'required' => false, 'constraints' => [new Assert\Length(max: 500)]])
                ->getForm()->handleRequest($request);

            if ($response = $this->process($forms['assignment'], function () use ($forms, $member, $actor): void {
                $d = $forms['assignment']->getData();
                $this->finance->assign($member, $d['class'], $d['payer'], $d['discount'], $d['reason'], $actor);
            }, $route, $parameters)) { 
                return $response; 
            }

            $forms['annual'] = $this->forms->createNamedBuilder('annual_charge', F\FormType::class, ['year' => (int) date('Y'), 'due' => new \DateTimeImmutable('today')])
                ->add('year', F\IntegerType::class, ['label' => 'Beitragsjahr', 'constraints' => [new Assert\Range(min: 2000, max: 2200)]])
                ->add('due', F\DateType::class, ['label' => 'Fälligkeit', 'widget' => 'single_text', 'input' => 'datetime_immutable'])->getForm()->handleRequest($request);
            
                if ($response = $this->process($forms['annual'], function () use ($forms, $member, $actor): void {
                $d = $forms['annual']->getData();
                $this->finance->annual($member, $d['year'], $d['due'], $actor);
            }, $route, $parameters)) { return $response; }
            
            $forms['special'] = $this->forms->createNamedBuilder('special_charge')
                ->add('description', F\TextType::class, [
                    'label' => 'Sonderbeitrag / Begründung', 
                    'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 180)]
                    ]
                )
                ->add('amount', F\MoneyType::class, 
                    $this->money('Sonderbeitrag EUR')
                )
                ->add('due', F\DateType::class, [
                    'label' => 'Fälligkeit', 
                    'widget' => 'single_text', 
                    'input' => 'datetime_immutable']
                )
                ->getForm()->handleRequest($request);
            
                if ($response = $this->process($forms['special'], function () use ($forms, $member, $actor): void {
                
                    $d = $forms['special']->getData();
                    $this->finance->special($member, $d['description'], $d['amount'], $d['due'], $actor);
                }, $route, $parameters)) { return $response; }
            $forms['mandate'] = $this->forms->createNamedBuilder('sepa_mandate')
                ->add('reference', F\TextType::class, [
                    'label' => 'Mandatsreferenz', 
                    'constraints' => [new Assert\Length(max: 35)]
                    ]
                )
                ->add('holder', F\TextType::class, [
                    'label' => 'Kontoinhaber', 
                    'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 180)]
                    ]
                )
                ->add('iban', F\TextType::class, [
                    'label' => 'IBAN (verschlüsselt gespeichert)', 
                    'attr' => ['autocomplete' => 'off'], 
                    'constraints' => [new Assert\Length(max: 50)]
                    ]
                )
                ->add('signed', F\DateType::class, [
                    'label' => 'Unterschriftsdatum', 
                    'widget' => 'single_text', 
                    'input' => 'datetime_immutable'
                    ]
                )
                ->add('proof', EntityType::class, [
                    'label' => 'Unterzeichneter Nachweis aus Mitgliederdokumenten', 
                    'class' => MemberDocument::class, 
                    'choices' => $this->em->getRepository(MemberDocument::class)->findBy(['member' => $member]), 
                    'choice_label' => 'originalName'
                    ]
                )
                ->getForm()->handleRequest($request);

            if ($response = $this->process($forms['mandate'], function () use ($forms, $member, $actor): void {
                $d = $forms['mandate']->getData();
                $this->finance->createMandate($member, $d['reference'], $d['holder'], $d['iban'], $d['signed'], $d['proof'], $actor);
            }, $route, $parameters)) { return $response; }
        }
        
        return $this->privatePage('membership/member.html.twig', [
            'member' => $member, 'admin' => $admin,
            'membership_layout' => $admin ? 'admin/membership/layout.html.twig' : 'membership/layout.html.twig',
            'forms' => array_map(static fn (FormInterface $form) => $form->createView(), $forms),
            'events' => $this->em->getRepository(MembershipEvent::class)->findBy(['member' => $member], ['id' => 'DESC']),
            'charges' => $this->em->getRepository(MembershipCharge::class)->findBy(['member' => $member], ['id' => 'DESC']),
            'mandates' => $this->em->getRepository(SepaMandate::class)->findBy(['member' => $member], ['id' => 'DESC']),
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/membership/charge/{id}/payment', name: 'membership_payment', requirements: ['id' => '\d+'], defaults: self::ADMIN_ROUTE_DEFAULTS, methods: ['GET', 'POST'])]
    public function payment(MembershipCharge $charge, Request $request): Response
    {
        $this->denyAccessUnlessGranted(MemberManagementVoter::MANAGE, $charge->getMember());
        $form = $this->forms->createNamedBuilder('membership_payment')
            ->add('amount', F\MoneyType::class, $this->money('Zahlung EUR'))
            ->add('reference', F\TextType::class, ['label' => 'Zahlungsreferenz / Beleg', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 180)]])
            ->getForm()->handleRequest($request);
        if ($response = $this->process($form, function () use ($charge, $form): void {
            $d = $form->getData();
            $this->finance->pay($charge, $d['amount'], $d['reference'], $this->account()->getUserIdentifier());
        }, 'membership_member', ['id' => $charge->getMember()->getId()])) { return $response; }
        return $this->adminPage('membership/form.html.twig', [
            'title' => 'Zahlung erfassen: ' . $charge->getDescription(), 'form' => $form->createView(),
            'explanation' => 'Offen: ' . number_format($charge->getOutstandingCents() / 100, 2, ',', '.') . ' EUR. Jede Zahlung wird mit Referenz und Bearbeiter unveränderlich protokolliert.',
            'payments' => $this->em->getRepository(MembershipPayment::class)->findBy(['charge' => $charge]),
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/membership/mandate/{id}/revoke', name: 'membership_mandate_revoke', requirements: ['id' => '\d+'], defaults: self::ADMIN_ROUTE_DEFAULTS, methods: ['POST'])]
    public function revoke(SepaMandate $mandate, Request $request): Response
    {
        $this->denyAccessUnlessGranted(MemberManagementVoter::MANAGE, $mandate->getMember());
        if (!$this->isCsrfTokenValid('mandate_revoke_' . $mandate->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Ungültiges CSRF-Token.');
        }
        try {
            $this->em->wrapInTransaction(function () use ($mandate): void {
                $mandate->revoke(new \DateTimeImmutable('today'));
                $this->memberships->record($mandate->getMember(), 'sepa_revoked', $this->account()->getUserIdentifier(), 'SEPA-Mandat widerrufen.');
            });
            $this->addFlash('success', 'Mandat widerrufen.');
        } catch (\DomainException $exception) {
            $this->addFlash('danger', $exception->getMessage());
        }
        return $this->redirectToRoute('membership_member', ['id' => $mandate->getMember()->getId()]);
    }

    private function process(FormInterface $form, callable $action, string $route, array $parameters = [], string $success = 'Änderung gespeichert.'): ?Response
    {
        if (!$form->isSubmitted() || !$form->isValid()) { return null; }
        try {
            $action();
            $this->addFlash('success', $success);
        } catch (\DomainException $exception) {
            $this->addFlash('danger', $exception->getMessage());
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException|\Doctrine\ORM\OptimisticLockException) {
            $this->addFlash('danger', 'Der Vorgang wurde zwischenzeitlich geändert oder der eindeutige Datensatz ist bereits vorhanden. Bitte den aktuellen Stand prüfen; keine doppelte Buchung wurde gespeichert.');
        }
        return $this->redirectToRoute($route, $parameters, Response::HTTP_SEE_OTHER);
    }

    private function account(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) { throw $this->createAccessDeniedException(); }
        return $user;
    }

    private function ownMember(): Member
    {
        $member = $this->account()->getMember();
        if ($member === null) { throw $this->createAccessDeniedException(); }
        return $member;
    }

    private function assertClub(Club $club): void
    {
        if (!$this->isGranted('ROLE_SUPER_ADMIN') && $this->account()->getMember()?->getClub()?->getId() !== $club->getId()) {
            throw $this->createAccessDeniedException('Verein nicht im Verwaltungsbereich.');
        }
    }

    private function money(string $label): array
    {
        return ['label' => $label, 'currency' => 'EUR', 'divisor' => 100, 'input' => 'integer', 'constraints' => [new Assert\Range(min: 0, max: 100000000)]];
    }

    private function policyForm(MembershipPolicy $policy): FormInterface
    {
        return $this->forms->createNamed('membership_policy', MembershipPolicyType::class, $policy);
    }

    private function saveContribution(ContributionClass $class): void
    {
        $duplicate = $this->em->getRepository(ContributionClass::class)->findOneBy(['club' => $class->getClub(), 'name' => $class->getName()]);
        if ($duplicate !== null && $duplicate->getId() !== $class->getId()) { throw new \DomainException('Diese Beitragsklasse existiert bereits.'); }
        if ($class->getId() !== null) {
            if (!$class->isFamily()) {
                $dependants = $this->em->getRepository(Member::class)->createQueryBuilder('m')->select('COUNT(m.id)')->join('m.familyPayer', 'p')
                    ->where('p.contributionClass = :class')->setParameter('class', $class)->getQuery()->getSingleScalarResult();
                if ((int) $dependants > 0) { throw new \DomainException('Familienbeitragsklasse mit abhängigen Mitgliedern nicht in einen Einzelbeitrag umwandeln.'); }
            }
            $excessDiscounts = $this->em->getRepository(Member::class)->createQueryBuilder('m')->select('COUNT(m.id)')
                ->where('m.contributionClass = :class')->andWhere('m.contributionDiscountCents > :amount')
                ->setParameter('class', $class)->setParameter('amount', $class->getAnnualCents())->getQuery()->getSingleScalarResult();
            if ((int) $excessDiscounts > 0) { throw new \DomainException('Zuerst Ermäßigungen anpassen, die den neuen Jahresbeitrag übersteigen.'); }
        }
        $this->em->persist($class); $this->em->flush();
    }

    private function contributionForm(ContributionClass $class, string $name = 'contribution_class'): FormInterface
    {
        return $this->forms->createNamed($name, ContributionClassType::class, $class);
    }

    private function adminPage(string $template, array $parameters): Response
    {
        return $this->privatePage($template, ['membership_layout' => 'admin/membership/layout.html.twig'] + $parameters);
    }

    private function privatePage(string $template, array $parameters): Response
    {
        $response = $this->render($template, $parameters);
        $response->headers->set('Cache-Control', 'private, no-store');
        return $response;
    }
}
