<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\{Club, FileAction, MediaFile, MediaFolder, User};
use App\Security\FolderPermissions;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class MediaImageType extends AbstractType
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly FolderPermissions $permissions,
        #[Autowire('%env(bool:CLUB_FUNCTION_MEMBERSHIP_ENABLED)%')] private readonly bool $functionModelEnabled,
    ) {}

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
            'query_builder' => fn (Options $options) => function (EntityRepository $repository) use ($options) {
                $query = $repository->createQueryBuilder('m')
                    ->where('m.club = :club AND m.mimeType IN (:types)')
                    ->setParameter('club', $options['club'])
                    ->setParameter('types', ['image/jpeg', 'image/png', 'image/webp'])
                    ->orderBy('m.id', 'DESC');
                if (!$this->functionModelEnabled) {
                    return $query;
                }
                $user = $this->security->getUser();
                if (!$user instanceof User) {
                    return $query->andWhere('1 = 0');
                }
                $club = $options['club'];
                $root = $this->permissions->can($user, $club, null, FileAction::READ);
                $ids = [];
                foreach ($this->em->getRepository(MediaFolder::class)->findBy(['club' => $club]) as $folder) {
                    if ($this->permissions->can($user, $club, $folder, FileAction::READ)) {
                        $ids[] = $folder->getId();
                    }
                }
                if ($ids !== []) {
                    $query->setParameter('readableFolders', $ids);
                    return $query->andWhere($root ? '(m.folder IS NULL OR IDENTITY(m.folder) IN (:readableFolders))' : 'IDENTITY(m.folder) IN (:readableFolders)');
                }
                return $query->andWhere($root ? 'm.folder IS NULL' : '1 = 0');
            },
        ]);
    }

    public function getParent(): string { return EntityType::class; }
}
