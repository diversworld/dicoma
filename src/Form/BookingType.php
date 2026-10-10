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
use App\Entity\Member;
use App\Enum\BookingAttendanceStatus;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

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
            ->add('member', EntityType::class, [
				'class' => Member::class,
				'label' => 'Mitglied',
				'placeholder' => 'Mitglied auswählen',
				'choice_label' => static function (Member $member): string {
					if ($member->getMemberNumber()) {
						return sprintf(
							'%s (%s)',
							$member->getFullName(),
							$member->getMemberNumber()
						);
					}

					return $member->getFullName();
				},
			])
			->add('attendanceStatus', EnumType::class, [
				'class' => BookingAttendanceStatus::class,
				'label' => 'Anwesenheit',
				'choice_label' => static fn (
					BookingAttendanceStatus $status
				): string => $status->label(),
			])

			->add('attendanceNotes', TextareaType::class, [
				'label' => 'Anwesenheitsnotiz',
				'required' => false,
			]);
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
