<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Club;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ConfigurationClubSelectionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('club', ChoiceType::class, [
                'label' => 'Verein für Mitgliedschaft und Beiträge',
                'choices' => $options['clubs'],
                'choice_label' => 'name',
                'choice_value' => static fn (?Club $club): string => $club ? (string) $club->getId() : '',
                'placeholder' => 'Bitte auswählen',
            ])
            ->add('year', HiddenType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['method' => 'GET', 'csrf_protection' => false]);
        $resolver->setRequired('clubs');
        $resolver->setAllowedTypes('clubs', 'array');
    }
}
