<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\InternalMessage;
use App\Entity\InternalMessageRecipient;
use App\Entity\User;
use App\Service\AdminClubContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/board-messages', defaults: DashboardController::ROUTE_DEFAULTS)]
#[IsGranted('ROLE_USER')]
final class BoardMessageController extends AbstractController
{
    #[Route('', name: 'admin_board_messages', methods: ['GET'])]
    public function index(EntityManagerInterface $em, AdminClubContext $clubs): Response
    {
        $club = $clubs->getClub() ?? throw $this->createAccessDeniedException();
        $user = $this->getUser();
        if (!$user instanceof User || (!$this->isGranted(User::ROLE_BOARD) && !$this->isGranted(User::ROLE_ADMIN))) {
            throw $this->createAccessDeniedException();
        }
        $messages = $em->getRepository(InternalMessage::class)->createQueryBuilder('message')
            ->addSelect('sender', 'recipients', 'recipientMember')
            ->join('message.sender', 'sender')
            ->leftJoin('message.recipients', 'recipients')
            ->leftJoin('recipients.member', 'recipientMember')
            ->where('message.club = :club')
            ->andWhere('message.audience = :audience')
            ->setParameter('club', $club)
            ->setParameter('audience', 'board')
            ->orderBy('message.sentAt', 'DESC')
            ->getQuery()->getResult();
        return $this->render('admin/communication/board_inbox.html.twig', [
            'messages' => $messages,
        ]);
    }

    #[Route('/{id}', name: 'admin_board_message_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(InternalMessage $message, EntityManagerInterface $em, AdminClubContext $clubs): Response
    {
        $club = $clubs->getClub() ?? throw $this->createAccessDeniedException();
        if (!$this->isGranted(User::ROLE_BOARD) && !$this->isGranted(User::ROLE_ADMIN)) {
            throw $this->createAccessDeniedException();
        }
        if ($message->getAudience() !== 'board' || $message->getClub()->getId() !== $club->getId()) {
            throw $this->createNotFoundException();
        }
        $recipients = $em->getRepository(InternalMessageRecipient::class)->findBy(['message' => $message], ['id' => 'ASC']);
        return $this->render('admin/communication/board_message.html.twig', [
            'message' => $message,
            'recipients' => $recipients,
        ]);
    }
}
