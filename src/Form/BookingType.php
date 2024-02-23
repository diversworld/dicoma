<?php

namespace App\Form;

use App\Entity\Booking;
use App\Entity\Schedule;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BookingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('bookingnumber', IntegerType::class,[
                'attr' => ['class' => 'form-control'],
            ])
            ->add('bookingdate', DateType::class, [
                'required' => false,
                'widget' => 'single_text', #'choice',
                'input_format' => 'dd.MM.yyyy',
                'format' => 'dd.MM.yyyy',
                'html5' => false,
                'disabled' => !$options['admin_mode'],// Annahme: Nur Admins können den Wert ändern
                'attr' => ['class' => 'js-datepicker form-control']
            ])
            ->add('status', ChoiceType::class, [
                'choices' => [
                    'gebucht' => 'gebucht',
                    'bestätigt' => 'bestätigt',
                    'bezahlt' => 'bezahlt',
                    'storniert' => 'storniert'
                ],
                'disabled' => !$options['admin_mode'], // Annahme: Nur Admins können den Wert ändern
                'placeholder' => 'Status auswählen', // Ein Platzhalter für das Dropdown
                'required' => true, // Oder false, je nach Anforderung
                'attr' => ['class' => 'form-control'],

            ])
            ->add('schedule', EntityType::class, [
                'class' => Schedule::class,
                'choices' => $options['available_schedules'], // Verfügbare Termine laden
                'choice_label' => 'title', // Annahme: Property für die Anzeige im Dropdown-Menü
                'placeholder' => 'Bitte wählen',
                'required' => true,
                'disabled' => !$options['admin_mode'], // Annahme: Nur Admins können den Wert ändern
                'attr' => ['class' => 'form-control'],
            ])
            ->add('students', EntityType::class, [
                'class' => User::class,
                'choices' => $options['available_students'],
                'choice_label' => function ($student) {
                    // Hier sollte die Logik für die Anzeige des Namens stehen
                    return $student->getFirstname() . ' ' . $student->getLastname();
                }, // Annahme: Property für die Anzeige im Dropdown-Menü
                'placeholder' => 'Bitte wählen',
                'required' => true,
                'disabled' => !$options['admin_mode'], // Annahme: Nur Admins können den Wert ändern
                'attr' => ['class' => 'form-control'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Booking::class,
            'available_schedules' => [], // Option für verfügbare Termine
            'available_students' => [], // Option für verfügbare Schüler
            'admin_mode' => false, // Annahme: Standardmäßig kein Admin-Modus
        ]);

        $resolver->setAllowedTypes('admin_mode', 'bool');
        $resolver->setAllowedTypes('available_schedules', 'array'); // Verfügbare Termine als Array erwarten
        $resolver->setAllowedTypes('available_students', 'array'); // Verfügbare Schüler als Array erwarten
    }
}
