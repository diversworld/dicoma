<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class ConfigurationYearSelectionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['include_club']) {
            $builder->add('club', HiddenType::class);
        }

        $builder->add('year', IntegerType::class, [
            'label' => 'Kalenderjahr',
            'attr' => ['min' => 2000, 'max' => 2100, 'class' => 'form-control'],
            'constraints' => [new Assert\NotNull(), new Assert\Range(min: 2000, max: 2100)],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['method' => 'GET', 'csrf_protection' => false, 'include_club' => false]);
        $resolver->setAllowedTypes('include_club', 'bool');
    }
}
