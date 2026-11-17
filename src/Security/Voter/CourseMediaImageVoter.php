<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Courses;
use App\Entity\FileAction;
use App\Entity\User;
use App\Enum\CourseStatus;
use App\Security\FolderPermissions;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class CourseMediaImageVoter extends Voter
{
    public const VIEW = 'COURSE_MEDIA_IMAGE_VIEW';

    public function __construct(private readonly FolderPermissions $folders) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::VIEW && $subject instanceof Courses;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$subject instanceof Courses || !$user instanceof User) {
            return false;
        }
        $club = $subject->getClub();
        $image = $subject->getMediaImage();
        if ($club?->getId() === null || $subject->getStatus() !== CourseStatus::OPEN
            || $user->getMember()?->getClub()?->getId() !== $club->getId()
            || $image === null || !$image->isImage() || $image->getClub()->getId() !== $club->getId()) {
            return false;
        }
        return $this->folders->can($user, $club, $image->getFolder(), FileAction::READ);
    }
}
