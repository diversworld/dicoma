<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\{Club, Courses, Tournament, WebsitePublication};
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\{CheckboxType, ChoiceType, DateTimeType, HiddenType, TextareaType, TextType};
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class WebsitePublicationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('kind', ChoiceType::class, [
                'label' => 'Inhaltstyp', 
                'attr' => [
                    'class' => 'form-control'],
                'choices' => ['Artikel' => 'article', 'Veranstaltung' => 'event']
                ]
            )
            ->add('title', TextType::class, [
                'label' => 'Titel',
                'attr' => ['class' => 'form-control'],
                ]
            )
            ->add('summary', TextareaType::class, [
                'label' => 'Kurzbeschreibung', 
                'attr' => ['class' => 'form-control', 'data-plain-text' => 'true', 'maxlength' => 500]
                ]
            )
            ->add('body', TextareaType::class, [
                'label' => 'Artikel / öffentliche Beschreibung (reiner Text)', 
                'attr' => ['class' => 'form-control', 'data-plain-text' => 'true', 'rows' => 12]
                ]
            )
            ->add('image', MediaImageType::class, [
                'club' => $options['club']
                ]
            )
            ->add('published', CheckboxType::class, [
                'label' => 'Zur Veröffentlichung freigeben',
                'label_attr' => ['class' => 'checkbox-switch'],
                'required' => false,
            ])
            ->add('publishAt', DateTimeType::class, [
                'label' => 'Öffentlich ab', 
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'], 
                'input' => 'datetime_immutable'
                ]
            )
            ->add('startsAt', DateTimeType::class, [
                'label' => 'Veranstaltungsbeginn', 
                'widget' => 'single_text', 
                'attr' => ['class' => 'form-control'],
                'input' => 'datetime_immutable', 
                'required' => false
                ]
            )
            ->add('endsAt', DateTimeType::class, [
                'label' => 'Veranstaltungsende', 
                'widget' => 'single_text', 
                'attr' => ['class' => 'form-control'],
                'input' => 'datetime_immutable', 
                'required' => false
                ]
            )
            ->add('location', TextType::class, [
                'label' => 'Öffentlicher Veranstaltungsort', 
                'attr' => ['class' => 'form-control'],
                'required' => false, 
                'empty_data' => ''
                ]
            )
            ->add('course', EntityType::class, [
                'class' => Courses::class, 
                'label' => 'Geplanten Tauchkurs ankündigen', 
                'attr' => [
                    'class' => 'form-control'],
                'required' => false, 
                'placeholder' => 'Kein Kurs',
                'query_builder' => static fn (EntityRepository $r) => $r->createQueryBuilder('s')->where('s.club = :club AND s.status NOT IN (:hidden)')->setParameter('club', $options['club'])->setParameter('hidden', ['draft', 'cancelled'])
                ]
            )
            ->add('tournament', EntityType::class, [
                'class' => Tournament::class, 
                'label' => 'Geplantes Turnier ankündigen', 
                'required' => false, 
                'attr' => [
                    'class' => 'form-control'],
                'placeholder' => 'Kein Turnier',
                'query_builder' => static fn (EntityRepository $r) => $r->createQueryBuilder('s')->where('s.club = :club AND s.status NOT IN (:hidden)')->setParameter('club', $options['club'])->setParameter('hidden', ['draft', 'cancelled'])
                ]
            )
            ->add('version', HiddenType::class, [
                'attr' => ['class' => 'form-control'],
                'mapped' => false, 
                'data' => (string) $builder->getData()->getVersion()
                ]
            );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => WebsitePublication::class]);
        $resolver->setRequired('club'); $resolver->setAllowedTypes('club', Club::class);
    }
}
