<?php

namespace App\Form;

use App\Entity\Tank;
use App\Entity\TankCheck;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TankCheckType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('checkDate', DateType::class, [
                'label' => 'Prüfdatum',
                'attr' => [
                    'class' => 'form-control'],
                'required' => false,
                'widget' => 'single_text', //'choice',
                'input_format' => 'dd.MM.yyyy',
                'format' => 'dd.MM.yyyy',
                'html5' => false,
            ])
            ->add('vendorName', TextType::class, [
                'label' => 'Prüfer',
                'attr' => [
                    'class' => 'form-control'],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Bemerkungen',
                'attr' => [
                    'class' => 'form-control',
                    'id' => 'tinymce'
                ],
                'required' => false,
            ])
            ->add('costInformation', TextareaType::class, [
                'label' => 'Kosteninformation',
                'attr' => [
                    'class' => 'form-control',
                    'id' => 'tinymce'
                ],
                'required' => false,
            ])
            ->add('tank', EntityType::class, [
                'class' => Tank::class,
                'choice_label' => 'serialnumber',
                'choices' => $options['available_tanks'],
                'multiple' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TankCheck::class,
            'available_tanks' => [],
        ]);

        $resolver->setAllowedTypes('available_tanks', 'array');
    }
}
