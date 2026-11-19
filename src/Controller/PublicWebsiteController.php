<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\WebsitePublication;
use App\Form\ContactMessageType;
use App\Service\PublicWebsite;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class PublicWebsiteController extends AbstractController
{
    #[Route('/club', name: 'website_club', methods: ['GET'])]
    public function club(PublicWebsite $website): Response
    {
        $club = $website->publicClub() ?? throw $this->createNotFoundException('Kein öffentlicher Verein konfiguriert.');
        return $this->renderClub($club);
    }

    #[Route('/contact', name: 'website_contact', methods: ['GET', 'POST'])]
    public function contact(
        Request $request,
        PublicWebsite $website,
        RateLimiterFactory $contactFormLimiter,
        MailerInterface $mailer,
        LoggerInterface $logger,
        #[Autowire('%env(CONTACT_MAIL_FROM)%')] string $from,
        #[Autowire('%env(MAILER_DSN)%')] string $mailerDsn,
        #[Autowire('%kernel.secret%')] string $secret,
    ): Response {
        $club = $website->publicClub() ?? throw $this->createNotFoundException('Kein öffentlicher Verein konfiguriert.');
        $available = filter_var($from, FILTER_VALIDATE_EMAIL) !== false
            && filter_var($club->getEmail(), FILTER_VALIDATE_EMAIL) !== false
            && trim($mailerDsn) !== ''
            && preg_match('~(?:^|[\s(])null://~i', trim($mailerDsn)) !== 1;
        if (!$available) {
            if ($request->isMethod('POST')) {
                throw new \Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException(null, 'Das Kontaktformular ist derzeit nicht konfiguriert.');
            }
            return $this->renderClub($club, ['contactAvailable' => false]);
        }
        if ($request->isMethod('POST')) {
            $limit = $contactFormLimiter->create(hash_hmac('sha256', $request->getClientIp() ?? 'unknown', $secret))->consume();
            if (!$limit->isAccepted()) {
                throw new \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException(
                    max(1, $limit->getRetryAfter()->getTimestamp() - time()), 'Zu viele Kontaktanfragen. Bitte später erneut versuchen.'
                );
            }
        }
        $form = $this->createForm(ContactMessageType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $email = (new Email())->from($from)->to($club->getEmail())->replyTo($data['email'])
                ->subject('Kontaktanfrage über die Website')
                ->text("Name: ".$data['name']."\nE-Mail: ".$data['email']."\n\n".$data['message']);
            try {
                $mailer->send($email);
            } catch (TransportExceptionInterface $exception) {
                $logger->error('Contact message transport failed.', ['exception' => $exception]);
                $form->addError(new \Symfony\Component\Form\FormError('Die Nachricht konnte nicht gesendet werden. Bitte später erneut versuchen.'));
                return $this->renderClub($club, ['contactAvailable' => true, 'contactForm' => $form], Response::HTTP_SERVICE_UNAVAILABLE);
            }
            $this->addFlash('success', 'Die Nachricht wurde an den Mailserver übergeben.');
            return $this->redirectToRoute('website_contact', [], Response::HTTP_SEE_OTHER);
        }
        return $this->renderClub($club, ['contactAvailable' => true, 'contactForm' => $form], $form->isSubmitted() ? 422 : 200);
    }

    private function renderClub(\App\Entity\Club $club, array $extra = [], int $status = 200): Response
    {
        $url = $club->getWebsite();
        $safeUrl = $url !== null && filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);

        return $this->render('website/club.html.twig', [
            'club' => $club,
            'websiteUrl' => $safeUrl ? $url : null,
        ] + $extra, new Response(status: $status));
    }

    #[Route('/divisions', name: 'website_divisions', methods: ['GET'])]
    public function divisions(PublicWebsite $website): Response
    {
        return $this->render('website/divisions.html.twig', ['divisions' => $website->divisions()]);
    }

    #[Route('/divisions/{id}', name: 'website_division', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function division(int $id, PublicWebsite $website): Response
    {
        $division = $website->divisions()[$id] ?? throw $this->createNotFoundException();
        return $this->render('website/division.html.twig', $division);
    }

    #[Route('/articles', name: 'website_articles', methods: ['GET'])]
    #[Route('/events', name: 'website_events', methods: ['GET'])]
    #[Route('/pages', name: 'website_pages', methods: ['GET'])]
    public function index(\Symfony\Component\HttpFoundation\Request $request, PublicWebsite $website, \App\Service\ClubEventBooking $booking, \App\Service\WebsitePagination $pagination): Response
    {
        $kind = match ($request->attributes->get('_route')) {
            'website_events' => 'event',
            'website_pages' => 'page',
            default => 'article',
        };
        $user = $this->getUser();
        $limits = $pagination->limits();
        $items = $kind === 'event' ? $website->upcomingEvents($booking, $user instanceof \App\Entity\User ? $user : null) : $website->publications($kind);
        $response = $this->render('website/index.html.twig', [
            'kind' => $kind,
            'contentPage' => $pagination->page($items, $limits[$kind === 'event' ? 'events' : 'articles'], $request, 'seite'),
        ]);
        if ($kind === 'event' && $this->getUser() !== null) {
            $response->setPrivate()->headers->addCacheControlDirective('no-store');
        }
        return $response;
    }

    #[Route('/news/{id}', name: 'website_publication', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[Route('/pages/{id}', name: 'website_page', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function detail(int $id, \Symfony\Component\HttpFoundation\Request $request, PublicWebsite $website, EntityManagerInterface $em): Response
    {
        $publication = $em->find(WebsitePublication::class, $id);
        $pageRoute = $request->attributes->get('_route') === 'website_page';
        if ($publication === null || ($publication->getKind() === 'page') !== $pageRoute || !$website->visible($publication)) {
            throw $this->createNotFoundException();
        }
        return $this->render('website/detail.html.twig', [
            'publication' => $publication,
            'articles' => $publication->getKind() === 'page' ? $website->articlesForPage($publication) : [],
            'subpages' => $publication->getKind() === 'page'
                ? array_values(array_filter($website->publications('page'), static fn (WebsitePublication $page): bool => $page->getParent()?->getId() === $publication->getId()))
                : [],
        ]);
    }

    #[Route('/news/{id}/image', name: 'website_publication_image', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function image(int $id, PublicWebsite $website, EntityManagerInterface $em, \App\Service\MediaFileStorage $storage): Response
    {
        $publication = $em->find(WebsitePublication::class, $id);
        $image = $publication?->getImage();
        if ($publication === null || !$website->visible($publication) || $image === null || !$image->isImage()
            || $image->getClub()->getId() !== $publication->getClub()->getId()) { throw $this->createNotFoundException(); }
        return $storage->response($image, \Symfony\Component\HttpFoundation\ResponseHeaderBag::DISPOSITION_INLINE);
    }
}
