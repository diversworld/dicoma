<?php

namespace App\Form;

use App\Entity\Tank;
use App\Entity\TankCheck;
use App\Entity\TankCheckDetail;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TankCheckDetailType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('tankCheck', EntityType::class, [
                'class' => TankCheck::class,
'choice_label' => 'id',
            ])
            ->add('tank', EntityType::class, [
                'class' => Tank::class,
'choice_label' => 'id',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TankCheckDetail::class,
        ]);
    }
}
