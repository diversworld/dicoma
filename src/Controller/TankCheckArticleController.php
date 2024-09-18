<?php

namespace App\Controller;

use App\Entity\TankCheck;
use App\Entity\TankCheckArticle;
use App\Form\TankCheckArticleType;
use App\Repository\TankCheckArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/tank/check/article')]
class TankCheckArticleController extends AbstractController
{
    #[Route('/', name: 'app_tank_check_article_index', methods: ['GET'])]
    public function index(TankCheckArticleRepository $tankCheckArticleRepository, EntityManagerInterface $entityManager): Response
    {
        // Hier wird ein Beispielwert für tank_check_id gesetzt.
        // Passen Sie dies nach Bedarf an, zum Beispiel durch eine Abfrage nach dem ersten TankCheck in der Datenbank.
        $tankCheck = $entityManager->getRepository(TankCheck::class)->findOneBy([]);
        $tank_check_id = $tankCheck ? $tankCheck->getId() : null;

        return $this->render('tank_check_article/index.html.twig', [
            'tank_check_articles' => $tankCheckArticleRepository->findAll(),
            'tank_check_id' => $tank_check_id,
        ]);
    }

    #[Route('/new/{tank_check_id}', name: 'app_tank_check_article_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, int $tank_check_id): Response
    {
        $tankCheckArticle = new TankCheckArticle();
        $form = $this->createForm(TankCheckArticleType::class, $tankCheckArticle);

        // Set TankCheck to TankCheckArticle
        $tankCheck = $entityManager->getRepository(TankCheck::class)->find($tank_check_id);
        $tankCheckArticle->setTankCheck($tankCheck);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid())
        {
            $tankCheckArticle->setPriceBrutto($tankCheckArticle->getPriceNetto() * 1.19);
            $entityManager->persist($tankCheckArticle);
            $entityManager->flush();

            return $this->redirectToRoute('app_tank_check_show', ['id' => $tank_check_id], Response::HTTP_SEE_OTHER);
        }

        return $this->render('tank_check_article/new.html.twig', [
            'tank_check_article' => $tankCheckArticle,
            'form' => $form,
            'tank_check_id' => $tank_check_id,
        ]);
    }

    #[Route('/{id}', name: 'app_tank_check_article_show', methods: ['GET'])]
    public function show(TankCheckArticle $tankCheckArticle): Response
    {
        return $this->render('tank_check_article/show.html.twig', [
            'tank_check_article' => $tankCheckArticle,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_tank_check_article_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, TankCheckArticle $tankCheckArticle, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(TankCheckArticleType::class, $tankCheckArticle);
        $form->handleRequest($request);

        // Laden des TankCheck-Objekts
        $tankCheck = $tankCheckArticle->getTankCheck();

        if ($form->isSubmitted() && $form->isValid())
        {
            $tankCheckArticle->setPriceBrutto($tankCheckArticle->getPriceNetto() * 1.19);

            $entityManager->flush();

            return $this->redirectToRoute('app_tank_check_article_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('tank_check_article/edit.html.twig', [
            'tank_check_article' => $tankCheckArticle,
            'form' => $form,
            'tank_check' => $tankCheck, // Übergabe der TankCheck-Variable an das Template
        ]);
    }

    #[Route('/{id}', name: 'app_tank_check_article_delete', methods: ['POST'])]
    public function delete(Request $request, TankCheckArticle $tankCheckArticle, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$tankCheckArticle->getId(), $request->request->get('_token'))) {
            $entityManager->remove($tankCheckArticle);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_tank_check_article_index', [], Response::HTTP_SEE_OTHER);
    }
}
