<?php

namespace App\Form;

use App\Entity\Schedule;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MakeupBookingType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder->add(
            'schedule',
            EntityType::class,
            [
                'class' => Schedule::class,
                'label' => 'Nachholtermin',
                'choices' => $options['schedules'],
                'placeholder' =>
                    'Nachholtermin auswählen',
            ]
        );
    }

    public function configureOptions(
        OptionsResolver $resolver
    ): void {
        $resolver->setDefaults([
            'data_class' => null,
            'schedules' => [],
        ]);

        $resolver->setAllowedTypes(
            'schedules',
            'array'
        );
    }
}