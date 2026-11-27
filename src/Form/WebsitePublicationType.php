<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\{Club, Courses, Sport, Tournament, WebsitePublication};
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\{CheckboxType, DateTimeType, HiddenType, IntegerType, TextareaType, TextType};
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class WebsitePublicationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $kind = $options['publication_kind'];
        $builder
            ->add('kind', HiddenType::class)
            ->add('title', TextType::class, ['label' => 'Titel'])
            ->add('summary', TextareaType::class, [
                'label' => 'Kurzbeschreibung',
                'attr' => ['data-plain-text' => 'true', 'maxlength' => 500, 'rows' => 3],
            ])
            ->add('body', TextareaType::class, [
                'label' => 'Öffentlicher Text (reiner Text)',
                'attr' => ['data-plain-text' => 'true', 'rows' => 12],
            ]);

        if ($kind !== 'event') {
            $builder->add('parent', EntityType::class, [
                'class' => WebsitePublication::class,
                'label' => 'Übergeordnete Seite',
                'choice_label' => 'title',
                'required' => false,
                'placeholder' => $kind === 'page' ? 'Hauptebene' : 'Keine Zuordnung',
                'query_builder' => static fn (EntityRepository $repository) => $repository->createQueryBuilder('parent')
                    ->where('parent.club = :club AND parent.kind = :kind')
                    ->andWhere('parent.id != :current OR :current = 0')
                    ->setParameter('club', $options['club'])
                    ->setParameter('kind', 'page')
                    ->setParameter('current', $builder->getData()->getId() ?? 0)
                    ->orderBy('parent.sortOrder')->addOrderBy('parent.title'),
                'help' => $kind === 'page'
                    ? 'Unterseiten werden unter dieser Seite im Menü angezeigt.'
                    : 'Der Artikel erscheint auf der ausgewählten Seite.',
            ]);
        }
        if ($kind === 'page') {
            $builder
                ->add('sortOrder', IntegerType::class, ['label' => 'Reihenfolge im Menü'])
                ->add('menuVisible', CheckboxType::class, ['label' => 'Im Hauptmenü anzeigen', 'required' => false]);
        }

        $builder
            ->add('division', EntityType::class, [
                'class' => Sport::class,
                'label' => 'Sparte',
                'required' => false,
                'placeholder' => 'Keine Sparte',
                'choice_label' => static fn (Sport $sport): string => $sport->getName().($sport->isActive() ? '' : ' (inaktiv)'),
                'query_builder' => static fn (EntityRepository $repository) => $repository->createQueryBuilder('s')
                    ->where('s.club = :club AND (s.active = true OR s = :current)')
                    ->setParameter('club', $options['club'])
                    ->setParameter('current', $builder->getData()->getDivision())
                    ->orderBy('s.sortOrder')->addOrderBy('s.name'),
            ])
            ->add('image', MediaImageType::class, [
                'club' => $options['club'],
                'disabled' => $options['image_locked'],
            ])
            ->add('published', CheckboxType::class, [
                'label' => 'Zur Veröffentlichung freigeben',
                'label_attr' => ['class' => 'checkbox-switch'],
                'required' => false,
            ])
            ->add('publishAt', DateTimeType::class, [
                'label' => 'Öffentlich ab',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ]);

        if ($kind === 'event') {
            $builder
                ->add('startsAt', DateTimeType::class, ['label' => 'Beginn', 'widget' => 'single_text', 'input' => 'datetime_immutable', 'required' => false])
                ->add('endsAt', DateTimeType::class, ['label' => 'Ende', 'widget' => 'single_text', 'input' => 'datetime_immutable', 'required' => false])
                ->add('location', TextType::class, ['label' => 'Öffentlicher Veranstaltungsort', 'required' => false, 'empty_data' => ''])
                ->add('course', EntityType::class, [
                    'class' => Courses::class,
                    'label' => 'Geplanten Tauchkurs ankündigen',
                    'required' => false,
                    'placeholder' => 'Kein Kurs',
                    'query_builder' => static fn (EntityRepository $repository) => $repository->createQueryBuilder('source')
                        ->where('source.club = :club AND source.status NOT IN (:hidden)')
                        ->setParameter('club', $options['club'])->setParameter('hidden', ['draft', 'cancelled']),
                ])
                ->add('tournament', EntityType::class, [
                    'class' => Tournament::class,
                    'label' => 'Geplantes Turnier ankündigen',
                    'required' => false,
                    'placeholder' => 'Kein Turnier',
                    'query_builder' => static fn (EntityRepository $repository) => $repository->createQueryBuilder('source')
                        ->where('source.club = :club AND source.status NOT IN (:hidden)')
                        ->setParameter('club', $options['club'])->setParameter('hidden', ['draft', 'cancelled']),
                ]);
        }

        $builder->add('version', HiddenType::class, [
            'mapped' => false,
            'data' => (string) $builder->getData()->getVersion(),
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => WebsitePublication::class,
            'image_locked' => false,
            'publication_kind' => 'article',
        ]);
        $resolver->setRequired('club');
        $resolver->setAllowedTypes('club', Club::class);
        $resolver->setAllowedTypes('image_locked', 'bool');
        $resolver->setAllowedValues('publication_kind', ['page', 'article', 'event']);
    }
}
