<?php
// src/Controller/CheckController.php

namespace App\Controller;

use App\Entity\Tank;
use App\Entity\TankCheck;
use App\Entity\TankCheckArticle;
use App\Form\BookCheckType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CheckController extends AbstractController
{
    #[Route('/check/book', name: 'book_check')]
    public function bookCheck(Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(BookCheckType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $selectedTank = $data['tank'];

            // Filter the articles based on the size of the tank
            $tankSize = $selectedTank->getSize();
            $queryBuilder = $em->createQueryBuilder();

            $tankCheck = new TankCheck();
            $tankCheck->addTank($selectedTank);
            $em->persist($tankCheck);

            $articles = $queryBuilder
                ->select('a')
                ->from(TankCheckArticle::class, 'a')
                ->where('a.size <= :tankSize OR a.size IS NULL')
                ->setParameter('tankSize', $tankSize)
                ->getQuery()
                ->getResult();

            foreach ($articles as $article) {
                // Add default articles directly
                if ($article->isStandard()) {
                    $tankCheckDetail = new TankCheckDetail();
                    $tankCheckDetail->setTank($selectedTank);
                    $tankCheckDetail->setArticle($article);
                    $tankCheckDetail->setTankCheck($tankCheck);
                    $em->persist($tankCheckDetail);
                }
            }

            $em->flush();

            return $this->redirectToRoute('success_page'); // definiere eine Seite, die bei Erfolg angezeigt wird
        }

        return $this->render('check/book.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}