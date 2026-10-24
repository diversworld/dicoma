<?php

namespace App\Controller;

use Symfony\Component\Security\Http\Attribute\IsGranted;

use App\Entity\Member;
use App\Form\MemberType;
use App\Repository\MemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/member')]
#[IsGranted('ROLE_SUPER_ADMIN')]
class MemberController extends AbstractController
{
    #[Route('/', name: 'app_member_index', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function index(MemberRepository $memberRepository): Response
    {
        return $this->render('member/index.html.twig', [
            'members' => $memberRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_member_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $member = new Member();
        $form = $this->createForm(MemberType::class, $member);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($member);
            $entityManager->flush();

            return $this->redirectToRoute('app_member_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('member/new.html.twig', [
            'member' => $member,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_member_show', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function show(Member $member): Response
    {
        return $this->render('member/show.html.twig', [
            'member' => $member,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_member_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, Member $member, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(MemberType::class, $member);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_member_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('member/edit.html.twig', [
            'member' => $member,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_member_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, Member $member): Response
    {
        if (!$this->isCsrfTokenValid('delete'.$member->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }
        $this->addFlash('warning', 'Mitgliedschaften werden nicht gelöscht. Bitte nach dem Austritt über die Mitgliederverwaltung archivieren.');
        return $this->redirectToRoute('membership_member', ['id' => $member->getId()], Response::HTTP_SEE_OTHER);
    }
}
