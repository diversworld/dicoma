<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CheckController extends AbstractController
{
    #[Route('/check/book', name: 'book_check', methods: ['GET'])]
    public function bookCheck(): Response
    {
        return $this->redirectToRoute('member_equipment_index', [], Response::HTTP_SEE_OTHER);
    }
}
