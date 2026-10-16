<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\EquipmentBookingItem;
use App\Entity\EquipmentItem;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class EquipmentBookingItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('equipmentItem', EntityType::class, [
            'class' => EquipmentItem::class, 'label' => 'Artikel', 'required' => true,
        ])->add('quantity', IntegerType::class, [
            'label' => 'Menge', 'required' => true, 'attr' => ['min' => 1],
        ]);
    }
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => EquipmentBookingItem::class]);
    }
}
