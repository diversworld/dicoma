<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postLoad, method: 'enable', entity: User::class)]
#[AsEntityListener(event: Events::postPersist, method: 'enable', entity: User::class)]
final class FunctionModelUserListener
{
    public function __construct(private readonly bool $enabled) {}

    public function enable(User $user): void
    {
        if ($this->enabled) {
            $user->enableFunctionModel();
        }
    }
}
