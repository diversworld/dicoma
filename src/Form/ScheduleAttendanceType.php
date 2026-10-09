<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ScheduleAttendanceType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder
            ->add(
                'bookings',
                CollectionType::class,
                [
                    'entry_type' =>
                        BookingAttendanceType::class,

                    'entry_options' => [
                        'label' => false,
                    ],

                    'allow_add' => false,
                    'allow_delete' => false,
                    'by_reference' => true,
                    'label' => false,
                ]
            )

            ->add(
                'markAllPresent',
                SubmitType::class,
                [
                    'label' => 'Alle anwesend',
                    'attr' => [
                        'class' => 'btn btn-secondary',
                    ],
                ]
            )

            ->add(
                'save',
                SubmitType::class,
                [
                    'label' => 'Anwesenheit speichern',
                    'attr' => [
                        'class' => 'btn btn-primary',
                    ],
                ]
            );
    }

    public function configureOptions(
        OptionsResolver $resolver
    ): void {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}