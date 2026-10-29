<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ContributionClass;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class ContributionClassType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Beitragsklasse'])
            ->add('annualCents', MoneyType::class, [
                'label' => 'Jahresbeitrag EUR',
                'currency' => 'EUR',
                'divisor' => 100,
                'input' => 'integer',
                'attr' => ['min' => 0, 'max' => 100000000, 'class' => 'form-control'],
                'constraints' => [new Assert\Range(min: 0, max: 100000000)],
            ])
            ->add('family', CheckboxType::class, [
                'label' => 'Familienbeitrag', 
                'attr' => ['class' => 'form-check-input'],
                'label_attr' => ['class' => 'checkbox-switch'],
                'required' => false
                ]
            )
            ->add('active', CheckboxType::class, [
                'label' => 'Für neue Forderungen aktiv', 
                'attr' => ['class' => 'form-check-input'],
                'label_attr' => ['class' => 'checkbox-switch'],
                'required' => false
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ContributionClass::class]);
    }
}
