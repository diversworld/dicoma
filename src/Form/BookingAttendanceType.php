<?php

namespace App\Form;

use App\Entity\Booking;
use App\Enum\BookingAttendanceStatus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BookingAttendanceType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder
            ->add('attendanceStatus', ChoiceType::class, [
                'label' => false,
                'choices' =>
                    BookingAttendanceStatus::choices(),
            ])

            ->add('attendanceNotes', TextareaType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'rows' => 1,
                    'placeholder' => 'Bemerkung',
                ],
            ]);
    }

    public function configureOptions(
        OptionsResolver $resolver
    ): void {
        $resolver->setDefaults([
            'data_class' => Booking::class,
        ]);
    }
}