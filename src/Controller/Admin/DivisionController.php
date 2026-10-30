<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Club;
use App\Entity\DivisionRoleAssignment;
use App\Entity\Member;
use App\Entity\Sport;
use App\Entity\User;
use App\Form\DivisionType;
use App\Form\DivisionRoleAssignmentType;
use App\Service\AdminClubContext;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class DivisionController extends AbstractController
{
    public function __construct(private readonly AdminClubContext $clubContext) {}

    #[Route('/admin/settings/sports', name: 'admin_settings_sports_configuration', defaults: DashboardController::ROUTE_DEFAULTS, methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $club = $this->clubContext->getClub();
        $requestedClub = $request->query->getString('club');
        if ($requestedClub !== '' && (!ctype_digit($requestedClub) || (int) $requestedClub !== $club?->getId())) {
            throw $this->createAccessDeniedException('Bitte zuerst den Verein im Benutzermenü auswählen.');
        }
        return $this->redirectToRoute('admin_sport_index', ['_locale' => $request->getLocale()]);
    }

    #[Route('/admin/divisions/{id}/functions', name: 'admin_division_functions', requirements: ['id' => '\d+'], defaults: DashboardController::ROUTE_DEFAULTS, methods: ['GET'])]
    public function functions(Sport $sport, EntityManagerInterface $em): Response
    {
        $this->assertClub($sport->getClub() ?? throw $this->createNotFoundException());
        return $this->render('admin/division/functions.html.twig', [
            'sport' => $sport,
            'assignments' => $em->getRepository(DivisionRoleAssignment::class)->findBy(['sport' => $sport], ['startsAt' => 'DESC', 'id' => 'DESC']),
        ]);
    }

    #[Route('/admin/divisions/club/{id}/new', name: 'admin_division_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function create(Club $club, Request $request, EntityManagerInterface $em): Response
    {
        $this->assertClub($club);
        return $this->editForm((new Sport())->setClub($club), $request, $em);
    }

    #[Route('/admin/divisions/{id}/edit', name: 'admin_division_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Sport $sport, Request $request, EntityManagerInterface $em): Response
    {
        $club = $sport->getClub();
        if ($club === null) { throw $this->createNotFoundException(); }
        $this->assertClub($club);
        return $this->editForm($sport, $request, $em);
    }

    #[Route('/admin/divisions/{id}/functions/new', name: 'admin_division_function_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function createFunction(Sport $sport, Request $request, EntityManagerInterface $em): Response
    {
        $this->assertClub($sport->getClub() ?? throw $this->createNotFoundException());
        return $this->functionForm(new DivisionRoleAssignment($sport), $request, $em);
    }

    #[Route('/admin/divisions/functions/{id}/edit', name: 'admin_division_function_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editFunction(DivisionRoleAssignment $assignment, Request $request, EntityManagerInterface $em): Response
    {
        $this->assertClub($assignment->getSport()->getClub() ?? throw $this->createNotFoundException());
        return $this->functionForm($assignment, $request, $em);
    }

    private function functionForm(DivisionRoleAssignment $assignment, Request $request, EntityManagerInterface $em): Response
    {
        $sport = $assignment->getSport();
        $form = $this->createForm(DivisionRoleAssignmentType::class, $assignment, [
            'members' => $em->getRepository(Member::class)->findBy(['club' => $sport->getClub()]),
            'editing' => $assignment->getId() !== null,
        ])->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($assignment);
            $em->flush();
            $this->addFlash('success', 'Spartenfunktion gespeichert.');
            return $this->redirectToRoute('admin_division_functions', ['id' => $sport->getId()], Response::HTTP_SEE_OTHER);
        }
        return $this->render('admin/division/function.html.twig', [
            'form' => $form->createView(), 'sport' => $sport,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    private function editForm(Sport $sport, Request $request, EntityManagerInterface $em): Response
    {
        $club = $sport->getClub() ?? throw $this->createNotFoundException();
        $form = $this->createForm(DivisionType::class, $sport)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $existing = $em->getRepository(Sport::class)->findOneBy(['club' => $club, 'name' => $sport->getName()]);
            if ($existing !== null && $existing->getId() !== $sport->getId()) {
                $form->get('name')->addError(new FormError('Diese Sparte existiert in diesem Verein bereits.'));
            } else {
                try {
                    $em->persist($sport);
                    $em->flush();
                    $this->addFlash('success', 'Sparte gespeichert.');
                    return $this->redirectToRoute('admin_settings_sports_configuration', [], Response::HTTP_SEE_OTHER);
                } catch (UniqueConstraintViolationException) {
                    $form->get('name')->addError(new FormError('Diese Sparte existiert in diesem Verein bereits.'));
                }
            }
        }

        return $this->render('admin/division/form.html.twig', [
            'form' => $form->createView(),
            'club' => $club,
            'sport' => $sport,
            'assignments' => $sport->getId() === null ? [] : $em->getRepository(DivisionRoleAssignment::class)->findBy(['sport' => $sport], ['startsAt' => 'DESC', 'id' => 'DESC']),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    private function assertClub(Club $club): void
    {
        $user = $this->getUser();
        if (!$user instanceof User || $this->clubContext->getClub()?->getId() !== $club->getId()) {
            throw $this->createAccessDeniedException('Verein nicht im Verwaltungsbereich.');
        }
    }
}
