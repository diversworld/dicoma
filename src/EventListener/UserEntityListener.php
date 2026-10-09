<?php
namespace App\EventListener;

use App\Entity\User;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserEntityListener {

    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher) {
        $this->passwordHasher = $passwordHasher;
    }

    public function prePersist(User $user, LifecycleEventArgs $event): void {
        $user->setPassword($this->passwordHasher->hashPassword($user, $user->getPassword()));
    }

    public function preUpdate(User $user, PreUpdateEventArgs $event): void {
        if ($event->hasChangedField('password')) {
            $user->setPassword($this->passwordHasher->hashPassword($user, $user->getPassword()));
        }
    }
}
