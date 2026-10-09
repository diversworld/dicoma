<?php

namespace App\Form;

use App\Entity\CourseParticipant;
use App\Entity\Member;
use App\Enum\CourseParticipantStatus;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CourseParticipantType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder
            ->add('member', EntityType::class, [
                'class' => Member::class,
                'label' => 'Teilnehmer',
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

            ->add('status', ChoiceType::class, [
				'label' => 'Status',
				'choices' => [
					'Angemeldet' =>
						CourseParticipantStatus::REGISTERED,

					'In Ausbildung' =>
						CourseParticipantStatus::ACTIVE,

					'Nicht bestanden' =>
						CourseParticipantStatus::FAILED,

					'Abgebrochen / storniert' =>
						CourseParticipantStatus::CANCELLED,
				],
				'choice_value' => static function (
					?CourseParticipantStatus $status
				): ?string {
					return $status?->value;
				},
			])

            ->add('startedAt', DateType::class, [
                'label' => 'Begonnen',
                'required' => false,
                'widget' => 'single_text',
            ])

            ->add('completedAt', DateType::class, [
                'label' => 'Abgeschlossen',
                'required' => false,
                'widget' => 'single_text',
            ])

            ->add('notes', TextareaType::class, [
                'label' => 'Notizen',
                'required' => false,
            ]);
    }

    public function configureOptions(
        OptionsResolver $resolver
    ): void {
        $resolver->setDefaults([
            'data_class' => CourseParticipant::class,
        ]);
    }
}