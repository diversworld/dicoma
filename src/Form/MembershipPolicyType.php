<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\MemberDocument;
use App\Entity\MembershipPolicy;
use App\Entity\ContributionClass;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Entity\Club;
use App\Entity\User;

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
            ->add('requiredApprovalCount', IntegerType::class, [
                'label' => 'Erforderliche Freigaben',
                'attr' => ['min' => 1, 'max' => 10],
                'help' => 'Jede berechtigte Person kann genau eine Freigabe geben. Die letzte erforderliche Freigabe startet das konfigurierte Onboarding.',
            ])
            ->add('approvalRole', ChoiceType::class, [
                'label' => 'Berechtigte Freigabegruppe',
                'choices' => [
                    'Vorstand' => User::ROLE_BOARD,
                    'Mitgliederverwaltung' => User::ROLE_ADMIN,
                    'Superadministration' => User::ROLE_SUPER_ADMIN,
                ],
            ])
            ->add('onboardingContributionClass', EntityType::class, [
                'label' => 'Beitragsart beim Onboarding',
                'class' => ContributionClass::class,
                'required' => false,
                'query_builder' => fn (EntityRepository $repository) => $repository
                    ->createQueryBuilder('contribution')
                    ->andWhere('contribution.active = true')
                    ->andWhere('contribution.club = :club')
                    ->setParameter('club', $options['club'])
                    ->orderBy('contribution.name', 'ASC'),
            ])
            ->add('onboardingIssueAnnualCharge', CheckboxType::class, [
                'label' => 'Jahresbeitrag beim Onboarding automatisch anlegen',
                'label_attr' => ['class' => 'checkbox-switch'],
                'required' => false,
            ])
            ->add('onboardingChargeDue', TextType::class, [
                'label' => 'Fälligkeit der ersten Jahresforderung (MM-TT)',
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
        $resolver->setRequired('club');
        $resolver->setAllowedTypes('club', Club::class);
    }
}
