<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Member;
use App\Entity\MemberDocument;
use App\Entity\User;
use App\Entity\MemberQualification;
use App\Entity\TeamMember;
use App\Service\PrivateMemberDocumentStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_MEMBER')]
final class ProfileController extends AbstractController
{
    private function currentMember(): Member
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        $member = $user->getMember();
        if (!$member instanceof Member) {
            throw $this->createNotFoundException('Dem Benutzer ist kein Mitglied zugeordnet.');
        }
        return $member;
    }

    #[Route('/profile', name: 'app_profile', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $member = $this->currentMember();
        $qualifications = $em->getRepository(MemberQualification::class)->findBy(['member' => $member], ['validUntil' => 'ASC']);
        $groups = $em->getRepository(TeamMember::class)->findBy(['member' => $member]);
        $documents = $em->getRepository(MemberDocument::class)->findBy(['member' => $member], ['createdAt' => 'DESC']);
        $today = new \DateTimeImmutable('today');
        $limit = $today->modify('+30 days');
        $expiring = 0;
        foreach ($qualifications as $qualification) {
            $date = $qualification->getValidUntil();
            if ($date !== null && $date >= $today && $date <= $limit) { ++$expiring; }
        }
        return $this->render('profile/index.html.twig', [
            'member' => $member, 'qualifications' => $qualifications,
            'groups' => $groups, 'documents' => $documents, 'expiringCount' => $expiring,
        ]);
    }

    #[Route('/profile/documents', name: 'app_profile_document_upload', methods: ['POST'])]
    public function upload(Request $request, EntityManagerInterface $em, PrivateMemberDocumentStorage $storage): Response
    {
        $member = $this->currentMember();
        if (!$this->isCsrfTokenValid('member_document_upload', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Ungueltiges CSRF-Token.');
        }
        $file = $request->files->get('document');
        if (!$file instanceof UploadedFile) {
            $this->addFlash('danger', 'Bitte eine Datei auswaehlen.');
            return $this->redirectToRoute('app_profile');
        }
        try {
            $kind = (string) $request->request->get('kind', 'other');
            if (!in_array($kind, MemberDocument::kinds(), true)) {
                throw new \InvalidArgumentException('Unbekannte Dokumentart.');
            }
            [$name, $mime, $size] = $storage->store($file);
            $original = mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 255);
            $document = new MemberDocument($member, $name, $original, $mime, $size);
            $document->setKind($kind);
            try {
                $em->persist($document);
                $em->flush();
            } catch (\Throwable $e) {
                @unlink($storage->path($name));
                throw $e;
            }
            $this->addFlash('success', 'Dokument hochgeladen.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('danger', $e->getMessage());
        }
        return $this->redirectToRoute('app_profile');
    }

    #[Route('/profile/documents/{id}/download', name: 'app_profile_document_download', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function download(MemberDocument $document, PrivateMemberDocumentStorage $storage): BinaryFileResponse
    {
        $member = $this->currentMember();
        if ($document->getMember()?->getId() !== $member->getId()) {
            throw $this->createNotFoundException();
        }
        $path = $storage->path($document->getStorageName());
        if (!is_file($path)) { throw $this->createNotFoundException(); }
        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', $document->getMimeType());
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $document->getOriginalName());
        return $response;
    }

    #[Route('/profile/documents/{id}/delete', name: 'app_profile_document_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(MemberDocument $document, Request $request, EntityManagerInterface $em, PrivateMemberDocumentStorage $storage): Response
    {
        $member = $this->currentMember();
        if ($document->getMember()?->getId() !== $member->getId()) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('delete_member_document_' . $document->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        if ($em->getRepository(\App\Entity\SepaMandate::class)->count(['document' => $document]) > 0) {
            $this->addFlash('danger', 'Ein als SEPA-Nachweis verwendetes Dokument muss aufbewahrt werden und kann hier nicht gelöscht werden.');
            return $this->redirectToRoute('app_profile');
        }
        $path = $storage->path($document->getStorageName());
        $em->remove($document);
        $em->flush();
        if (is_file($path)) { @unlink($path); }
        $this->addFlash('success', 'Dokument entfernt.');
        return $this->redirectToRoute('app_profile');
    }
}
