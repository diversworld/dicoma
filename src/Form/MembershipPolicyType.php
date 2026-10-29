<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\MemberDocument;
use App\Entity\MembershipPolicy;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class MembershipPolicyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('applicationsEnabled', CheckboxType::class, [
                'label' => 'Öffentliche Anträge erlauben',
                'label_attr' => ['class' => 'checkbox-switch'],
                'required' => false,
            ])
            ->add('contactEmail', EmailType::class, [
                'label' => 'Kontaktadresse der Verwaltung',
                'attr' => ['class' => 'form-control'],
                'required' => false,
            ])
            ->add('cancellationCutoff', TextType::class, [
                'label' => 'Kündigungsstichtag (MM-TT, z. B. 09-30)',
                'attr' => ['class' => 'form-control'],
                'required' => false,
            ])
            ->add('membershipYearEnd', TextType::class, [
                'label' => 'Mitgliedschaftsjahresende (MM-TT, z. B. 12-31)',
                'attr' => ['class' => 'form-control'],
                'required' => false,
            ])
            ->add('requiredDocuments', ChoiceType::class, [
                'label' => 'Erforderliche Unterlagen',
                'attr' => ['class' => 'form-control'],
                'choices' => MemberDocument::kinds(),
                'multiple' => true,
                'required' => false,
                ]
            )
            ->add('creditorIdentifier', TextType::class, [
                'label' => 'SEPA-Gläubiger-ID',
                'attr' => ['class' => 'form-control'],
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => MembershipPolicy::class]);
    }
}
