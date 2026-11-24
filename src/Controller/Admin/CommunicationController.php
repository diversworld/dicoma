<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\CommunicationTemplate;
use App\Entity\MembershipMessage;
use App\Entity\User;
use App\Service\AdminClubContext;
use App\Service\AuditLogger;
use App\Service\InternalMessaging;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/communication', defaults: DashboardController::ROUTE_DEFAULTS)]
#[IsGranted('ROLE_USER')]
final class CommunicationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AdminClubContext $clubs,
        private readonly InternalMessaging $messaging,
        private readonly AuditLogger $audit,
    ) {}

    #[Route('', name: 'admin_communication', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $this->assertSender();
        $club = $this->clubs->getClub() ?? throw $this->createAccessDeniedException();
        $templates = $this->em->getRepository(CommunicationTemplate::class)->findBy(['club' => $club], ['name' => 'ASC']);
        $preview = null;
        if ($request->isMethod('POST')) {
            $this->csrf($request, 'communication');
            $operation = $request->request->getString('operation');
            if ($operation === 'template') {
                $template = new CommunicationTemplate(
                    $club,
                    $request->request->getString('code'),
                    $request->request->getString('name'),
                    $request->request->getString('subject'),
                    $request->request->getString('body'),
                );
                $this->em->persist($template);
                $this->audit->record($club, $this->getUser()->getUserIdentifier(), 'communication.template.created', $template);
                $this->em->flush();
                $this->addFlash('success', 'Vorlage gespeichert.');
            } elseif ($operation === 'preview') {
                $preview = count($this->recipients($request, $this->sender()));
            } elseif ($operation === 'test') {
                $template = $this->template($templates, $request->request->getInt('templateId'));
                $email = $this->sender()->getEmail();
                if (!is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) { throw $this->createNotFoundException('Absender benötigt eine gültige E-Mail-Adresse.'); }
                $this->em->persist(new MembershipMessage(
                    'communication-test:'.bin2hex(random_bytes(8)),
                    $email,
                    '[TEST] '.$template->getSubject(),
                    $template->getBody(),
                ));
                $this->audit->record($club, $this->getUser()->getUserIdentifier(), 'communication.test.queued', $template);
                $this->em->flush();
                $this->addFlash('success', 'Testnachricht an deine E-Mail-Adresse eingereiht.');
            } elseif ($operation === 'send') {
                $template = $this->template($templates, $request->request->getInt('templateId'));
                $audience = $request->request->getString('audience');
                $recipients = $this->recipients($request, $this->sender());
                $templateCount = count($recipients);
                $message = $this->messaging->send($this->sender(), $audience, $template->getSubject(), $template->getBody());
                $this->audit->record($club, $this->getUser()->getUserIdentifier(), 'communication.sent', $message, [
                    'template' => $template->getCode(),
                    'audience' => $audience,
                    'recipientCount' => $templateCount,
                ]);
                $this->em->flush();
                $this->addFlash('success', $templateCount.' Nachrichten vorbereitet.');
            }
            if ($operation !== 'preview') {
                return $this->redirectToRoute('admin_communication');
            }
        }
        return $this->render('admin/communication/index.html.twig', [
            'templates' => $templates,
            'preview' => $preview,
            'audiences' => [
                'Alle aktiven Mitglieder' => 'all',
                'Vorstand' => 'board',
                'Jugendabteilung' => 'youth',
                'Ehrenmitglieder' => 'honorary',
            ],
        ]);
    }

    private function recipients(Request $request, User $sender): array
    {
        return $this->messaging->previewRecipients($sender, $request->request->getString('audience'));
    }

    private function template(array $templates, int $id): CommunicationTemplate
    {
        foreach ($templates as $template) {
            if ($template->getId() === $id) { return $template; }
        }
        throw $this->createNotFoundException('Vorlage nicht gefunden.');
    }
    private function sender(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) { throw $this->createAccessDeniedException(); }
        return $user;
    }
    private function assertSender(): void
    {
        if (!$this->isGranted(User::ROLE_BOARD) && !$this->isGranted(User::ROLE_ADMIN)) { throw $this->createAccessDeniedException(); }
    }
    private function csrf(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, $request->request->getString('_token'))) { throw $this->createAccessDeniedException(); }
    }
}
