<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\DivisionRoleAssignment;
use App\Entity\Member;
use App\Enum\DivisionRole;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class DivisionRoleAssignmentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('member', EntityType::class, [
                'class' => Member::class,
                'choices' => $options['members'],
                'choice_label' => 'fullName',
                'label' => 'Mitglied',
                'placeholder' => 'Mitglied auswählen',
                'disabled' => $options['editing'],
            ])
            ->add('role', EnumType::class, [
                'class' => DivisionRole::class,
                'choice_label' => static fn (DivisionRole $role): string => $role->label(),
                'label' => 'Funktion',
                'placeholder' => 'Funktion auswählen',
                'disabled' => $options['editing'],
            ])
            ->add('startsAt', DateType::class, [
                'label' => 'Beginn',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('endsAt', DateType::class, [
                'label' => 'Ende (einschließlich)',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => DivisionRoleAssignment::class, 'editing' => false]);
        $resolver->setRequired('members');
        $resolver->setAllowedTypes('members', 'array');
        $resolver->setAllowedTypes('editing', 'bool');
    }
}
