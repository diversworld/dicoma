<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\{Courses, FileAction, Schedule, User};
use App\Enum\{CourseStatus, PermissionAction, PermissionModule};
use App\Security\{FolderPermissions, ModuleAccess};
use App\Service\AdminClubContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class CourseMediaImageEditVoter extends Voter
{
    public const EDIT = 'COURSE_MEDIA_IMAGE_EDIT';

    public function __construct(
        private readonly AdminClubContext $clubs,
        private readonly ModuleAccess $modules,
        private readonly FolderPermissions $folders,
        #[Autowire('%env(bool:CLUB_FUNCTION_MEMBERSHIP_ENABLED)%')] private readonly bool $enabled,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::EDIT && ($subject instanceof Courses || $subject instanceof Schedule);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        $club = $this->clubs->getClub();
        if (!$this->enabled || !$user instanceof User || $club?->getId() === null
            || (!$subject instanceof Courses && !$subject instanceof Schedule)
            || !$this->modules->can($user, $club, PermissionModule::TRAINING, PermissionAction::EDIT)) {
            return false;
        }
        $course = $subject instanceof Schedule ? $subject->getCourses() : $subject;
        if ($course?->getClub()?->getId() !== $club->getId()
            || ($subject instanceof Courses && $subject->getStatus() === CourseStatus::COMPLETED)
            || ($subject instanceof Schedule && $subject->getInstructor() !== null
                && $subject->getInstructor()->getClub()?->getId() !== $club->getId())) {
            return false;
        }
        $image = $subject->getMediaImage();
        return $image === null || ($image->isImage() && $image->getClub()->getId() === $club->getId()
            && $this->folders->can($user, $club, $image->getFolder(), FileAction::READ));
    }
}
