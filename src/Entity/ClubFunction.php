<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PermissionAction;
use App\Enum\PermissionModule;
use App\Security\ModulePermission;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_club_function_name', columns: ['club_id', 'name'])]
class ClubFunction
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Club $club;

    #[ORM\Column(length: 120)]
    private string $name;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $permissions = [];

    /** @var list<string>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $modulePermissions = null;

    #[ORM\Column]
    private bool $successorRequired = false;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $startsAt = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $endsAt = null;

    #[ORM\Version, ORM\Column(type: 'integer')]
    private int $version = 1;

    #[ORM\Column(nullable: true)]
    private ?bool $rootRead = null;
    #[ORM\Column(nullable: true)]
    private ?bool $rootUpload = null;
    #[ORM\Column(nullable: true)]
    private ?bool $rootEdit = null;
    #[ORM\Column(nullable: true)]
    private ?bool $rootDelete = null;

    public function __construct(Club $club, string $name)
    {
        $this->club = $club;
        $this->rename($name);
    }

    public function getId(): ?int { return $this->id; }
    public function getClub(): Club { return $this->club; }
    public function getName(): string { return $this->name; }
    public function __toString(): string { return $this->name; }

    public function rename(string $name): void
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 120 || preg_match('/[\x00-\x1f\x7f]/u', $name) !== 0) {
            throw new \InvalidArgumentException('Der Funktionsname muss 1 bis 120 Zeichen lang sein und darf keine Steuerzeichen enthalten.');
        }
        $this->name = $name;
    }

    /** @return list<string> */
    public function getPermissions(): array
    {
        self::assertPermissions($this->permissions);
        return $this->permissions;
    }

    /** @param list<string> $permissions */
    public function setPermissions(array $permissions): void
    {
        self::assertPermissions($permissions);
        $this->permissions = $permissions;
    }

    /** @return list<string> */
    public function getModulePermissions(): array
    {
        $permissions = $this->modulePermissions ?? [];
        ModulePermission::validate($permissions);
        return $permissions;
    }

    /** @param list<string> $permissions */
    public function setModulePermissions(array $permissions): void
    {
        ModulePermission::validate($permissions);
        $this->modulePermissions = $permissions;
    }

    public function hasModulePermission(PermissionModule $module, PermissionAction $action): bool
    {
        return in_array(ModulePermission::key($module, $action), $this->getModulePermissions(), true);
    }

    /** @param list<string> $permissions */
    private static function assertPermissions(array $permissions): void
    {
        $allowed = array_diff(array_values(User::getAvailableRoles()), [User::ROLE_USER, User::ROLE_MEMBER, User::ROLE_SUPER_ADMIN]);
        if (!array_is_list($permissions) || array_filter($permissions, static fn (mixed $permission): bool => !is_string($permission)) !== []
            || count(array_unique($permissions)) !== count($permissions)
            || array_diff($permissions, $allowed) !== []) {
            throw new \InvalidArgumentException('Ungültige Funktionsberechtigung.');
        }
    }

    public function isSuccessorRequired(): bool { return $this->successorRequired; }
    public function setSuccessorRequired(bool $required): void { $this->successorRequired = $required; }
    public function getStartsAt(): ?\DateTimeImmutable { return $this->startsAt; }
    public function getEndsAt(): ?\DateTimeImmutable { return $this->endsAt; }
    public function getVersion(): int { return $this->version; }

    public function setValidity(?\DateTimeImmutable $startsAt, ?\DateTimeImmutable $endsAt): void
    {
        self::assertValidity($startsAt, $endsAt);
        $this->startsAt = $startsAt?->setTime(0, 0);
        $this->endsAt = $endsAt?->setTime(0, 0);
    }

    public function isValidOn(\DateTimeImmutable $date): bool
    {
        return self::withinValidity($date, $this->startsAt, $this->endsAt);
    }

    public static function assertValidity(?\DateTimeImmutable $startsAt, ?\DateTimeImmutable $endsAt): void
    {
        if ($startsAt !== null && $endsAt !== null && $startsAt->format('Y-m-d') > $endsAt->format('Y-m-d')) {
            throw new \InvalidArgumentException('Das Ende darf nicht vor dem Beginn liegen.');
        }
    }

    public static function withinValidity(\DateTimeImmutable $date, ?\DateTimeImmutable $startsAt, ?\DateTimeImmutable $endsAt): bool
    {
        $day = $date->format('Y-m-d');
        return ($startsAt === null || $startsAt->format('Y-m-d') <= $day)
            && ($endsAt === null || $endsAt->format('Y-m-d') >= $day);
    }

    public function rootPermission(FileAction $action): ?bool
    {
        return match ($action) {
            FileAction::READ => $this->rootRead,
            FileAction::UPLOAD => $this->rootUpload,
            FileAction::EDIT => $this->rootEdit,
            FileAction::DELETE => $this->rootDelete,
        };
    }

    public function setRootPermission(FileAction $action, ?bool $value): void
    {
        match ($action) {
            FileAction::READ => $this->rootRead = $value,
            FileAction::UPLOAD => $this->rootUpload = $value,
            FileAction::EDIT => $this->rootEdit = $value,
            FileAction::DELETE => $this->rootDelete = $value,
        };
    }
}
