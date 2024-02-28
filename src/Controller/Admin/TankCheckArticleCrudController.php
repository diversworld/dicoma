<?php

namespace App\Controller\Admin;

use App\Entity\TankCheckArticle;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityUpdatedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Field\CurrencyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class TankCheckArticleCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return TankCheckArticle::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('title')
                ->setColumns(5),
            MoneyField::new('priceNetto', 'Price')
                ->setColumns(2)
                ->setCurrency('EUR'),
            MoneyField::new('priceBrutto', 'Preis')
                ->setColumns(2)
                ->setCurrency('EUR'),
            TextEditorField::new('notes')
                ->setColumns(6)
                ->hideOnIndex(),
        ];
    }

    public static function getSubscribedEvents()
    {
        return [
            BeforeEntityPersistedEvent::class => ['calculatePriceBrutto'],
            BeforeEntityUpdatedEvent::class => ['calculatePriceBrutto'],
        ];
    }

    public function calculatePriceBrutto($event)
    {
        $entity = $event->getEntityInstance();

        if (!($entity instanceof TankCheckArticle)) {
            return;
        }

        $priceNetto = $entity->getPriceNetto();
        $priceBrutto = $priceNetto * 1.19;
        $entity->setPriceBrutto($priceBrutto);
    }
}
