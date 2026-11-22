<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\InternalMessage;
use App\Entity\InternalMessageRecipient;
use App\Entity\Member;
use App\Entity\Sport;
use App\Entity\User;
use App\Service\InternalMessaging;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/my/messages')]
#[IsGranted('ROLE_MEMBER')]
final class InternalMessageController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    #[Route('', name: 'member_messages', methods: ['GET'])]
    public function index(): Response
    {
        $member = $this->member();
        $recipients = $this->em->getRepository(InternalMessageRecipient::class)
            ->createQueryBuilder('recipient')
            ->addSelect('message', 'sender')
            ->join('recipient.message', 'message')
            ->join('message.sender', 'sender')
            ->where('recipient.member = :member')
            ->setParameter('member', $member)
            ->orderBy('message.sentAt', 'DESC')
            ->getQuery()->getResult();
        return $this->render('member_portal/messages/index.html.twig', ['recipients' => $recipients]);
    }

    #[Route('/new', name: 'member_message_new', methods: ['GET', 'POST'])]
    public function create(Request $request, InternalMessaging $messaging): Response
    {
        $member = $this->member();
        $club = $member->getClub() ?? throw $this->createAccessDeniedException();
        $divisions = $messaging->divisionChoices();
        $roles = $messaging->roleChoices();
        $recipients = [];
        foreach ($messaging->directChoices($club) as $choice) {
            $recipients[$choice->getFullName()] = (string) $choice->getId();
        }
        $divisionValues = [];
        foreach ($divisions as $choice) {
            $divisionValues[(string) $choice->getName()] = (string) $choice->getId();
        }
        $audiences = ['Ein Mitglied' => 'member'];
        if ($this->isGranted(User::ROLE_ADMIN) || $this->isGranted(User::ROLE_BOARD)) {
            $audiences += [
                'Alle aktiven Mitglieder' => 'all',
                'Vorstand' => 'board',
                'Jugendabteilung' => 'youth',
                'Ehrenmitglieder' => 'honorary',
            ];
        }
        if ($divisions !== []) { $audiences['Sparte'] = 'division'; }
        if ($roles !== []) { $audiences['Rolle'] = 'role'; }
        $form = $this->createFormBuilder([
            'audience' => 'member',
            'recipient' => null,
            'division' => null,
            'role' => null,
            'subject' => '',
            'body' => '',
        ], ['csrf_token_id' => 'internal_message_new'])
            ->add('audience', ChoiceType::class, ['label' => 'Empfängergruppe', 'choices' => $audiences])
            ->add('recipient', ChoiceType::class, [
                'choices' => $recipients, 'placeholder' => 'Mitglied auswählen', 'required' => false,
            ])
            ->add('division', ChoiceType::class, [
                'choices' => $divisionValues, 'placeholder' => 'Sparte auswählen', 'required' => false,
            ])
            ->add('role', ChoiceType::class, [
                'choices' => $roles, 'placeholder' => 'Rolle auswählen', 'required' => false,
            ])
            ->add('subject', TextType::class, ['label' => 'Betreff', 'attr' => ['maxlength' => 180]])
            ->add('body', TextareaType::class, ['label' => 'Nachricht', 'attr' => ['rows' => 10, 'maxlength' => 20000]])
            ->add('send', SubmitType::class, ['label' => 'Nachricht senden'])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            try {
                $messaging->send(
                    $this->user(),
                    (string) $data['audience'],
                    (string) $data['subject'],
                    (string) $data['body'],
                    $data['recipient'] ? $this->em->find(Member::class, (int) $data['recipient']) : null,
                    $data['division'] ? $this->em->find(Sport::class, (int) $data['division']) : null,
                    $data['role'],
                );
                $this->addFlash('success', 'Nachricht gesendet.');
                return $this->redirectToRoute('member_messages');
            } catch (\DomainException|\InvalidArgumentException $exception) {
                $form->addError(new \Symfony\Component\Form\FormError($exception->getMessage()));
            }
        }
        return $this->render('member_portal/messages/new.html.twig', [
            'form' => $form,
        ], new Response(status: $form->isSubmitted() ? 422 : 200));
    }

    #[Route('/{id}', name: 'member_message_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): Response
    {
        $recipient = $this->em->getRepository(InternalMessageRecipient::class)->findOneBy([
            'message' => $id,
            'member' => $this->member(),
        ]);
        if (!$recipient instanceof InternalMessageRecipient) {
            throw $this->createNotFoundException('Nachricht nicht gefunden.');
        }
        $message = $recipient->getMessage();
        if ($message->getClub()->getId() !== $this->member()->getClub()?->getId()) {
            throw $this->createNotFoundException('Nachricht nicht gefunden.');
        }
        $recipient->markRead();
        $this->em->flush();
        return $this->render('member_portal/messages/show.html.twig', [
            'message' => $message,
            'recipient' => $recipient,
            'canReply' => $message->getSender()->getMember() !== null,
        ]);
    }

    private function member(): Member
    {
        $member = $this->user()->getMember();
        if ($member === null || !$member->isActiveMember() || $member->getClub() === null) {
            throw $this->createAccessDeniedException('Aktives Mitglied erforderlich.');
        }
        return $member;
    }

    private function user(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        return $user;
    }
}
