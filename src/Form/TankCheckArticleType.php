<?php

namespace App\Form;

use App\Entity\TankCheck;
use App\Entity\TankCheckArticle;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TankCheckArticleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class,[
                'label' => 'Artikel',
                'attr' => [
                    'placeholder' => 'Bezeichnung',
                    'class' =>'form-control'
                ]
            ])
            ->add('priceNetto', MoneyType::class,[
                'label' => 'Preis (netto)',
                'required' => false,
                'divisor' => 1,
                'scale' => 2,
                'currency' => 'EUR',
                'attr' => [
                    'placeholder' => 'Preis',
                    'class' =>'form-control'
                ]
            ])
            ->add('priceBrutto', MoneyType::class,[
                'label' => 'Preis (brutto)',
                'required' => false,
                'divisor' => 1,
                'scale' => 2,
                'currency' => 'EUR',
                'attr' => [
                    'placeholder' => 'Preis',
                    'class' =>'form-control'
                ]
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Kosteninformation',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'id' => 'tinymce'
                ],
            ])/*
            ->add('tankChecks', EntityType::class,[
                'label' => 'TÜV Prüfung',
                'class' => TankCheck::class,
                'choice_label' => 'id',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Kurs',
                    'class' =>'form-control'
                ]
            ])*/
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TankCheckArticle::class,
        ]);
    }
}
