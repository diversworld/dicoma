<?php

namespace App\Controller\Admin;

use App\Entity\TankCheckArticle;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityUpdatedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\ChoiceList\Factory\Cache\ChoiceFieldName;

class TankCheckArticleCrudController extends AbstractCrudController implements EventSubscriberInterface
{
    public static function getEntityFqcn(): string
    {
        return TankCheckArticle::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            FormField::addRow(breakpointName: 'md' ),
            TextField::new('title', 'Name')
                ->setColumns(6),
            ChoiceField::new('size', 'Größe')
                ->setColumns(2)
                ->setChoices([
                    '3 L'  => '3',
                    '5 L'  => '5',
                    '7 L'  => '7',
                    '8 L'  => '8',
                    '10 L' => '10',
                    '12 L' => '12',
                    '15 L' => '15',
                    '18 L' => '18',
                    '20 L' => '20',
                    '40 cuft' => '40',
                    '80 cuft' => '80'
                ]),
            BooleanField::new('isDefault','Standard')
                ->renderAsSwitch(true)
                ->setColumns(2),
            BooleanField::new('standard','Standard')
                ->renderAsSwitch(true)
                ->setColumns(2),
            MoneyField::new('priceNetto', 'Preis (netto)')
                ->setColumns(3)
                ->setCurrency('EUR'),
            MoneyField::new('priceBrutto', 'Preis (brutto)')
                ->setColumns(3)
                ->setCurrency('EUR'),
            FormField::addRow(breakpointName: 'md' ),
            TextEditorField::new('notes', 'Notizen')
                ->setColumns(12)
                ->hideOnIndex(),
        ];
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeEntityPersistedEvent::class => 'calculatePriceBrutto',
            BeforeEntityUpdatedEvent::class => 'calculatePriceBrutto',
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