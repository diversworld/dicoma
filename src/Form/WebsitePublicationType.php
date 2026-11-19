<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\{Club, Courses, Sport, Tournament, WebsitePublication};
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\{CheckboxType, ChoiceType, DateTimeType, HiddenType, IntegerType, TextareaType, TextType};
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
                'choices' => $builder->getData()->getId() === null
                    ? ['Artikel' => 'article', 'Seite' => 'page']
                    : ['Artikel' => 'article', 'Seite' => 'page', 'Bestehende Legacy-Veranstaltung' => 'event'],
                ]
            )
            ->add('title', TextType::class, [
                'label' => 'Titel',
                'attr' => ['class' => 'form-control'],
                ]
            )
            ->add('parent', EntityType::class, [
                'class' => WebsitePublication::class,
                'label' => 'Übergeordnete Seite',
                'choice_label' => 'title',
                'required' => false,
                'placeholder' => 'Hauptebene',
                'query_builder' => static fn (EntityRepository $repository) => $repository->createQueryBuilder('parent')
                    ->where('parent.club = :club AND parent.kind = :kind')
                    ->andWhere('parent.id != :current OR :current = 0')
                    ->setParameter('club', $options['club'])
                    ->setParameter('kind', 'page')
                    ->setParameter('current', $builder->getData()->getId() ?? 0)
                    ->orderBy('parent.sortOrder')->addOrderBy('parent.title'),
                'help' => 'Artikel werden einer Seite zugeordnet; Seiten können als Unterseite strukturiert werden.',
            ])
            ->add('sortOrder', IntegerType::class, ['label' => 'Menü- und Seitenreihenfolge'])
            ->add('menuVisible', CheckboxType::class, [
                'label' => 'Im Hauptmenü anzeigen',
                'required' => false,
            ])
            ->add('summary', TextareaType::class, [
                'label' => 'Kurzbeschreibung', 
                'attr' => ['class' => 'form-control', 'data-plain-text' => 'true', 'maxlength' => 500]
                ]
            )
            ->add('body', TextareaType::class, [
                'label' => 'Öffentlicher Text (reiner Text)',
                'attr' => ['class' => 'form-control', 'data-plain-text' => 'true', 'rows' => 12]
                ]
            )
            ->add('division', EntityType::class, [
                'class' => Sport::class,
                'label' => 'Sparte',
                'required' => false,
                'placeholder' => 'Keine Sparte',
                'attr' => ['class' => 'form-control'],
                'choice_label' => static fn (Sport $sport): string => (string) $sport.($sport->isActive() ? '' : ' (inaktiv)'),
                'query_builder' => static fn (EntityRepository $r) => $r->createQueryBuilder('s')
                    ->where('s.club = :club AND (s.active = true OR s = :current)')
                    ->setParameter('club', $options['club'])
                    ->setParameter('current', $builder->getData()->getDivision())
                    ->orderBy('s.sortOrder')->addOrderBy('s.name'),
            ])
            ->add('image', MediaImageType::class, [
                'club' => $options['club'],
                'disabled' => $options['image_locked'],
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
        $resolver->setDefaults(['data_class' => WebsitePublication::class, 'image_locked' => false]);
        $resolver->setAllowedTypes('image_locked', 'bool');
        $resolver->setRequired('club'); $resolver->setAllowedTypes('club', Club::class);
    }
}
