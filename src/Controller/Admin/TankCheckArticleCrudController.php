<?php

namespace App\Controller\Admin;

use App\Entity\TankCheckArticle;
use App\Service\TuvPriceCalculator;
use Doctrine\ORM\EntityManagerInterface;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\ChoiceList\Factory\Cache\ChoiceFieldName;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
class TankCheckArticleCrudController extends AbstractCrudController 
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
			ChoiceField::new('selectionMode', 'Auswahlmodus')->setChoices([
				'Pflichtleistung' => 'required',
				'Standard (abwählbar)' => 'default',
				'Optional' => 'optional',
			]),
			ChoiceField::new('volumeRule', 'Volumenregel')->setChoices([
				'Alle Flaschengrößen' => 'any',
				'Exakte Größe' => 'exact',
				'Bis einschließlich' => 'up_to',
				'Ab einschließlich' => 'from',
			]),
			AssociationField::new('tankCheck', 'TÜV-Termin')
    			->setRequired(true),
            ChoiceField::new('priceInputBasis', 'Preis eingeben als')
				->setChoices([
					'Nettopreis' => 'net',
					'Bruttopreis' => 'gross',
				])
				->setFormTypeOption('placeholder', false)
				->setFormTypeOption('empty_data', 'net')
				->setRequired(true)
				->onlyOnForms(),
			TextField::new('priceNetto', 'Netto EUR')
				->setRequired(false)
				->setHelp('Bei Preis-Eingabe Netto ausfüllen; Komma oder Punkt erlaubt.'),
			TextField::new('priceBrutto', 'Brutto EUR')
				->setRequired(false)
				->setHelp('Bei Preis-Eingabe Brutto ausfüllen; Komma oder Punkt erlaubt.'),

            FormField::addRow(breakpointName: 'md' ),
            TextEditorField::new('notes', 'Notizen')
                ->setColumns(12)
                ->hideOnIndex(),
        ];
    }
	
	private function normalizePrice(TankCheckArticle $article): void
	{
		$pair = TuvPriceCalculator::pair($article->getPriceNetto(), $article->getPriceBrutto(), $article->getPriceInputBasis());
		$article->setPriceNetto($pair['net']);
		$article->setPriceBrutto($pair['gross']);
	}
	
	public function persistEntity(
		EntityManagerInterface $entityManager,
		$entityInstance
	): void {
		if ($entityInstance instanceof TankCheckArticle) {
			$this->validateArticle($entityInstance);
			$this->normalizePrice($entityInstance);
		}

		parent::persistEntity($entityManager, $entityInstance);
	}
	
	private function validateArticle(TankCheckArticle $article): void
	{
		if ($article->getTankCheck() === null) {
			throw new \DomainException(
				'Bitte ordne den Artikel einem TÜV-Termin zu.'
			);
		}
	}
	
	public function updateEntity(
		EntityManagerInterface $entityManager,
		$entityInstance
	): void {
		if ($entityInstance instanceof TankCheckArticle) {
			$this->validateArticle($entityInstance);
			$this->normalizePrice($entityInstance);
		}

		parent::updateEntity($entityManager, $entityInstance);
	}

}