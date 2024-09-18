<?php
// src/Service/TankCheckService.php
namespace App\Service;

use App\Entity\Tank;
use App\Entity\TankCheck;
use App\Entity\TankCheckDetail;
use Doctrine\ORM\EntityManagerInterface;

class TankCheckService
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function bookTankInspection(Tank $tank, TankCheck $tankCheck): void
    {
        // Erstellen des TankCheckDetail Objektes
        $tankCheckDetail = new TankCheckDetail();
        $tankCheckDetail->setTank($tank);
        $tankCheckDetail->setTankCheck($tankCheck);

        // Berechnen und setzen des Betrags (Gesamtpreis)
        $totalPrice = 0;

        foreach ($tankCheck->getArticles() as $article) {
            $tankCheckDetail->setArticle($article);
            $totalPrice += $article->getPriceNetto();
        }

        $tankCheckDetail->setAmount($totalPrice);

        // Speichern des TankCheckDetail Objekts
        $this->entityManager->persist($tankCheckDetail);
        $this->entityManager->flush();
    }
}