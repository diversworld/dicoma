<?php
// src/Form/BookCheckType.php

namespace App\Form;

use App\Entity\Tank;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BookCheckType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('tank', EntityType::class, [
                'class' => Tank::class,
                'choice_label' => 'inventory', // Wähle das passende Label für die Anzeige
            ])
            ->add('save', SubmitType::class, ['label' => 'Prüfung buchen']);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            // Set the data class if needed
        ]);
    }
}