<?php

declare(strict_types=1);
namespace App\Form;

use App\Entity\ClubEvent;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\{CheckboxType, ChoiceType, DateTimeType, IntegerType, MoneyType, TextareaType, TextType};
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ClubEventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('title', TextType::class, [
            'label' => 'Titel'])
            ->add('description', TextareaType::class, [
                'label' => 'Beschreibung'])
            ->add('image', MediaImageType::class, [
                'club' => $builder->getData()->getClub()])
            ->add('pricing', ChoiceType::class, [
                'label' => 'Veranstaltungsart', 
                'choices' => [
                    'Kostenfrei, nur aktive Mitglieder' => 'members_free',
                    'Kostenpflichtig, Mitglieder und Gäste' => 'paid',
                    ]
                ]
            )
            ->add('memberFeeCents', MoneyType::class, [
                'label' => 'Mitgliederpreis', 
                'attr' => ['class' => 'form-control'],
                'divisor' => 100, 
                'currency' => 'EUR'
                ]
            )
            ->add('guestFeeCents', MoneyType::class, [
                'label' => 'Gästepreis', 
                'attr' => ['class' => 'form-control'],
                'divisor' => 100, 
                'currency' => 'EUR'
                ]
            )
            ->add('startsAt', DateTimeType::class, [
                'label' => 'Beginn', 
                'attr' => ['class' => 'form-control'],
                'widget' => 'single_text', 
                'input' => 'datetime_immutable'
                ]
            )
            ->add('endsAt', DateTimeType::class, [
                'label' => 'Ende', 
                'attr' => ['class' => 'form-control'],
                'widget' => 'single_text', 
                'input' => 'datetime_immutable'
                ]
            )
            ->add('deadline', DateTimeType::class, [
                'label' => 'Anmeldefrist', 
                'attr' => ['class' => 'form-control'],
                'widget' => 'single_text', 
                'input' => 'datetime_immutable', 
                'required' => false
                ]
            )
            ->add('location', TextType::class, [
                'label' => 'Ort',
                'attr' => ['class' => 'form-control'],
                ]
            )
            ->add('capacity', IntegerType::class, [
                'label' => 'Teilnehmerplätze (leer = unbegrenzt)', 
                'required' => false,
                'attr' => ['class' => 'form-control'],
                ]
        )
            ->add('published', CheckboxType::class, [
                'label' => 'Veröffentlichen', 
                'label_attr' => ['class' => 'checkbox-switch'],
                'required' => false
                ]
            );
    }

    public function configureOptions(OptionsResolver $resolver): void { 
        $resolver->setDefaults(['data_class' => ClubEvent::class]); 
    }
}
