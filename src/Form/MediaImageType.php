<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\{Club, MediaFile};
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class MediaImageType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('club');
        $resolver->setAllowedTypes('club', Club::class);
        $resolver->setDefaults([
            'class' => MediaFile::class,
            'label' => 'Bild aus der Dateiverwaltung',
            'required' => false,
            'placeholder' => 'Kein Bild',
            'choice_label' => static fn (MediaFile $file): string => ($file->getFolder() ? $file->getFolder()->getPath().' / ' : '').$file->getOriginalName().' (#'.$file->getId().')',
            'query_builder' => static fn (Options $options) => static fn (EntityRepository $r) => $r->createQueryBuilder('m')
                ->where('m.club = :club AND m.mimeType IN (:types)')
                ->setParameter('club', $options['club'])
                ->setParameter('types', ['image/jpeg', 'image/png', 'image/webp'])
                ->orderBy('m.id', 'DESC'),
        ]);
    }

    public function getParent(): string { return EntityType::class; }
}
