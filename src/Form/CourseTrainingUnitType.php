<?php

namespace App\Form;

use App\Entity\CourseTrainingUnit;
use App\Entity\TrainingUnitType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

class CourseTrainingUnitType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder
            ->add(
                'trainingUnitType',
                EntityType::class,
                [
                    'class' => TrainingUnitType::class,
                    'label' => 'Ausbildungseinheit',
                    'placeholder' => 'Ausbildungseinheit auswählen',
                    'required' => true,
                ]
            )
            ->add(
                'requiredSessions',
                IntegerType::class,
                [
                    'label' => 'Anzahl Termine',
                    'required' => true,

                    /*
                     * Neue Einträge werden mit 1 vorbelegt.
                     */
                    'empty_data' => '1',

                    'attr' => [
                        'min' => 1,
                    ],

                    'constraints' => [
                        new NotBlank(
                            message: 'Bitte die Anzahl der Termine angeben.'
                        ),
                        new GreaterThanOrEqual(
                            value: 1,
                            message: 'Es muss mindestens ein Termin vorgesehen sein.'
                        ),
                    ],
                ]
            )
            ->add(
                'required',
                CheckboxType::class,
                [
                    'label' => 'Pflicht',
                    'required' => false,
                ]
            )
            ->add(
                'notes',
                TextareaType::class,
                [
                    'label' => 'Bemerkung',
                    'required' => false,
                ]
            );
    }

    public function configureOptions(
        OptionsResolver $resolver
    ): void {
        $resolver->setDefaults([
            'data_class' => CourseTrainingUnit::class,
        ]);
    }
}