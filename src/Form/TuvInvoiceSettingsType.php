<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class TuvInvoiceSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('taxMode', ChoiceType::class, [
                'label' => 'Steuerliche Behandlung',
                'placeholder' => 'Bitte auswählen',
                'choices' => ['Kleinunternehmerregelung (§ 19 UStG)' => 'small_business', 'Regelbesteuerung' => 'standard'],
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('taxNumber', TextType::class, [
                'label' => 'Steuernummer', 'required' => false,
                'attr' => ['maxlength' => 60], 'constraints' => [new Assert\Length(max: 60)],
            ])
            ->add('vatId', TextType::class, [
                'label' => 'Umsatzsteuer-ID', 'required' => false,
                'attr' => ['maxlength' => 40], 'constraints' => [new Assert\Length(max: 40)],
            ])
            ->add('invoiceNote', TextareaType::class, [
                'label' => 'Zusätzlicher Rechnungshinweis', 'required' => false,
                'attr' => ['maxlength' => 2000, 'rows' => 3], 'constraints' => [new Assert\Length(max: 2000)],
            ])
            ->add('memberAgencyEnabled', CheckboxType::class, [
                'label' => 'TÜV-Abwicklung als Vertreter der Mitglieder aktiviert (vertraglich geklärt)',
                'required' => false,
            ]);
    }
}
