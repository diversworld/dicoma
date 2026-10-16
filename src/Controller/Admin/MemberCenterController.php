<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Member;
use App\Entity\MemberDocument;
use App\Entity\MemberQualification;
use App\Entity\CourseParticipant;
use App\Entity\TeamMember;
use App\Service\PrivateMemberDocumentStorage;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class MemberCenterController extends AbstractController
{
    #[Route('/admin/member/{id}/center', name: 'admin_member_center', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function center(Member $member, AdminUrlGenerator $urlGenerator): RedirectResponse
    {
        $url = (clone $urlGenerator)->unsetAll()
            ->setController(MemberCrudController::class)
            ->setAction(Crud::PAGE_DETAIL)
            ->setEntityId($member->getId())
            ->generateUrl();
        return $this->redirect($url, Response::HTTP_SEE_OTHER);
    }

    #[Route('/admin/member/{id}/document/{documentId}/download', name: 'admin_member_document_download', requirements: ['id' => '\\d+', 'documentId' => '\\d+'], methods: ['GET'])]
    public function download(Member $member, int $documentId, EntityManagerInterface $em, PrivateMemberDocumentStorage $storage): Response
    {
        $document = $em->getRepository(MemberDocument::class)->find($documentId);
        if (!$document instanceof MemberDocument || $document->getMember()?->getId() !== $member->getId()) {
            throw $this->createNotFoundException('Dokument nicht gefunden.');
        }
        $path = $storage->path($document->getStorageName());
        if (!is_file($path)) {
            throw $this->createNotFoundException('Datei nicht gefunden.');
        }
        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $document->getOriginalName());
        return $response;
    }
}
