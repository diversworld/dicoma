<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tank;
use App\Entity\TankCheck;
use App\Entity\TankCheckDetail;
use Doctrine\ORM\EntityManagerInterface;

final class TankCheckService
{
    public function __construct(private readonly EntityManagerInterface $entityManager) {}

    public function bookTankInspection(Tank $tank, TankCheck $tankCheck): void
    {
        if ($tank->getId() === null || $tankCheck->getId() === null) {
            throw new \DomainException('Flasche und Prüfung müssen bereits gespeichert sein.');
        }

        $articles = $tankCheck->getArticles();
        if ($articles->isEmpty()) {
            throw new \DomainException('Die Prüfung enthält keine Preispositionen.');
        }

        $this->entityManager->wrapInTransaction(function () use ($tank, $tankCheck, $articles): void {
            foreach ($articles as $article) {
                if ($article->getTankCheck() !== $tankCheck) {
                    throw new \DomainException('Preisposition gehört zu einer anderen Prüfung.');
                }
                $price = $article->getPriceNetto();
                if ($price === null || !preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $price)) {
                    throw new \DomainException('Ungültiger Netto-Preis der Prüfposition.');
                }

                $detail = (new TankCheckDetail())
                    ->setTank($tank)
                    ->setTankCheck($tankCheck)
                    ->setArticle($article)
                    ->setAmount($price);
                $this->entityManager->persist($detail);
            }
            $this->entityManager->flush();
        });
    }
}
