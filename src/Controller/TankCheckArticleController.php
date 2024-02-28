<?php

namespace App\Controller;

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
    public function index(TankCheckArticleRepository $tankCheckArticleRepository): Response
    {
        return $this->render('tank_check_article/index.html.twig', [
            'tank_check_articles' => $tankCheckArticleRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_tank_check_article_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $tankCheckArticle = new TankCheckArticle();
        $form = $this->createForm(TankCheckArticleType::class, $tankCheckArticle);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid())
        {
            $tankCheckArticle->setPriceBrutto($tankCheckArticle->getPriceNetto() * 1.19);

            $entityManager->persist($tankCheckArticle);
            $entityManager->flush();

            return $this->redirectToRoute('app_tank_check_article_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('tank_check_article/new.html.twig', [
            'tank_check_article' => $tankCheckArticle,
            'form' => $form,
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

        if ($form->isSubmitted() && $form->isValid())
        {
            $tankCheckArticle->setPriceBrutto($tankCheckArticle->getPriceNetto() * 1.19);

            $entityManager->flush();

            return $this->redirectToRoute('app_tank_check_article_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('tank_check_article/edit.html.twig', [
            'tank_check_article' => $tankCheckArticle,
            'form' => $form,
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
