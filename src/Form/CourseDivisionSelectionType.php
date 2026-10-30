<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Sport;
use App\Form\Model\CourseDivisionSelection;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CourseDivisionSelectionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('scope', ChoiceType::class, [
                'label' => 'Zuordnung',
                'choices' => ['Noch zu prüfen' => 'unreviewed', 'Vereinsweit' => 'club', 'Ausgewählte Sparten' => 'divisions'],
                'expanded' => true,
            ])
            ->add('divisions', EntityType::class, [
                'class' => Sport::class,
                'label' => 'Sparten',
                'choices' => $options['divisions'],
                'choice_label' => static fn (Sport $sport): string => $sport->getName().($sport->isActive() ? '' : ' (inaktiv)'),
                'multiple' => true,
                'required' => false,
                'help' => 'Nur für „Ausgewählte Sparten“; mehrere Sparten sind möglich.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CourseDivisionSelection::class]);
        $resolver->setRequired('divisions');
        $resolver->setAllowedTypes('divisions', 'array');
    }
}
