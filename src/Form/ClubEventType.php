<?php

declare(strict_types=1);
namespace App\Form;

use App\Entity\ClubEvent;
use App\Entity\Member;
use App\Entity\Sport;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\{CheckboxType, ChoiceType, DateTimeType, DateType, IntegerType, MoneyType, TextareaType, TextType};
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ClubEventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('title', TextType::class, [
            'label' => 'Titel'])
            ->add('eventType', ChoiceType::class, [
                'label' => 'Kategorie',
                'choices' => ['Veranstaltung' => 'event', 'Tauchgang' => 'dive', 'Ausfahrt' => 'trip'],
            ])
            ->add('division', EntityType::class, [
                'class' => Sport::class, 'label' => 'Sparte', 'required' => false,
                'query_builder' => static fn (EntityRepository $repository) => $repository->createQueryBuilder('s')
                    ->where('s.club = :club')->setParameter('club', $builder->getData()->getClub())->andWhere('s.active = true')->orderBy('s.name', 'ASC'),
            ])
            ->add('responsible', EntityType::class, [
                'class' => Member::class, 'label' => 'Verantwortliche Person', 'required' => false,
                'choice_label' => 'fullName',
                'query_builder' => static fn (EntityRepository $repository) => $repository->createQueryBuilder('m')
                    ->where('m.club = :club')->setParameter('club', $builder->getData()->getClub())
                    ->andWhere('m.membershipStatus = :active')->setParameter('active', 'active')
                    ->andWhere('m.archivedAt IS NULL'),
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Beschreibung'])
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
            ->add('recurrence', ChoiceType::class, [
                'label' => 'Wiederholung',
                'required' => false,
                'choices' => ['Keine' => null, 'Wöchentlich' => 'weekly', 'Monatlich' => 'monthly'],
                'placeholder' => false,
            ])
            ->add('recurrenceUntil', DateType::class, [
                'label' => 'Wiederholen bis einschließlich',
                'widget' => 'single_text', 'input' => 'datetime_immutable', 'required' => false,
            ])
            ->add('published', CheckboxType::class, [
                'label' => 'Veröffentlichen', 
                'label_attr' => ['class' => 'checkbox-switch'],
                'required' => false
                ]
            );
        if (!$options['image_locked']) {
            $builder->add('image', MediaImageType::class, ['club' => $builder->getData()->getClub()]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void { 
        $resolver->setDefaults(['data_class' => ClubEvent::class, 'image_locked' => false]);
        $resolver->setAllowedTypes('image_locked', 'bool');
    }
}
