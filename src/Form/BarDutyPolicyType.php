<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class BarDutyPolicyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('minutes', IntegerType::class, [
                'label' => 'Pflichtminuten je Mitglied',
                'help' => '0 bedeutet ausdrücklich keine Pflicht.',
                'attr' => ['min' => 0, 'max' => 60000, 'class' => 'form-control'],
                'constraints' => [new Assert\NotNull(), new Assert\Range(min: 0, max: 60000)],
            ])
            ->add('cash', CheckboxType::class, [
                'label' => 'Separate Thekenkasse für dieses Jahr aktivieren',
                'attr' => ['class' => 'form-check-input'],
                'label_attr' => ['class' => 'checkbox-switch'],
                'help' => 'Die Kasse ist von der Gerätewartkasse getrennt. Bestehende Kassen bleiben bei Deaktivierung abschließbar.',
                'required' => false,
            ])
            ->add('reason', TextType::class, [
                'label' => 'Begründung',
                'help' => '10 bis 500 Zeichen.',
                'trim' => false,
                'attr' => ['minlength' => 10, 'maxlength' => 500, 'class' => 'form-control'],
                'constraints' => [
                    new Assert\NotBlank(normalizer: 'trim'),
                    new Assert\Length(min: 10, normalizer: 'trim'),
                    new Assert\Length(max: 500),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['csrf_token_id' => 'bar_duty_policy_0']);
    }
}
