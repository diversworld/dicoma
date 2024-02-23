<?php

namespace App\Form;

use App\Entity\Tank;
use App\Entity\TankCheck;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TankType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('inventory', TextType::class, [
                'label' => 'Inventarnummer',
                'attr' => [
                    'class' => 'form-control'],
            ])
            ->add('serialnumber', TextType::class, [
                'label' => 'Seriennummer',
                'attr' => [
                    'class' => 'form-control'],
            ])
            ->add('size', ChoiceType::class,[
                'label' => 'Größe',
                'choices' => [
                    '3L' => '3',
                    '5L' => '5',
                    '7L' => '7',
                    '8L' => '8',
                    '10L' => '10',
                    '12L' => '12',
                    '15L' => '15',
                    '18L' => '18',
                    '20L' => '20'
                ],
            ])
            ->add('buyDate', DateType::class, [
                'label' => 'Kaufdatum',
                'required' => false,
                'widget' => 'single_text', #'choice',
                'input_format' => 'dd.MM.yyyy',
                'format' => 'dd.MM.yyyy',
                'html5' => false,
                'attr' => ['class' => 'js-datepicker form-control']
            ])
            ->add('lastCheckDate', DateType::class, [
                'label' => 'letzte Prüfung',
                'attr' => [
                    'class' => 'form-control'],
                'required' => false,
                'widget' => 'single_text', #'choice',
                'input_format' => 'dd.MM.yyyy',
                // this is actually the default format for single_text
                'format' => 'dd.MM.yyyy',
                'html5' => false,
            ])
            ->add('nextCheckDate', DateType::class, [
                'label' => 'nächste Prüfung',
                'attr' => [
                    'class' => 'form-control'],
                'required' => false,
                'widget' => 'single_text', //'choice',
                'input_format' => 'dd.MM.yyyy',
                'format' => 'dd.MM.yyyy',
                'html5' => false,
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Bemerkungen',
                'attr' => [
                    'class' => 'form-control',
                    'id' => 'tinymce'
                ],
                'required' => false,
            ])
            ->add('oxigenClean', CheckboxType::class, [
                'label' => 'Sauerstoffrein',
                'required' => false,
                'attr' => [
                    'class' => 'form-control'],
            ])
            ->add('tankChecks', EntityType::class, [
                'label' => 'Prüfungen',
                'choices' => $options['available_checks'],
                'required' => false,
                'placeholder' => '',
                'class' => TankCheck::class,
                'choice_label' => 'id',
                'multiple' => true,
                'attr' => [
                    'class' =>'form-control'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Tank::class,
            'available_checks' => [],
        ]);

        $resolver->setAllowedTypes('available_checks', 'array');
    }
}
