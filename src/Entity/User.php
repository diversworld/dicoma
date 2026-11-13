<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[UniqueEntity(
    fields: ['user'],
    message: 'Für diesen Benutzernamen existiert bereits ein Konto.'
)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const ROLE_USER = 'ROLE_USER';
    public const ROLE_MEMBER = 'ROLE_MEMBER';
    public const ROLE_INSTRUCTOR = 'ROLE_INSTRUCTOR';
    public const ROLE_TRAINING_MANAGER = 'ROLE_TRAINING_MANAGER';
    public const ROLE_COURSE_ADMIN = 'ROLE_COURSE_ADMIN';
    public const ROLE_EQUIPMENT_MANAGER = 'ROLE_EQUIPMENT_MANAGER';
    public const ROLE_EQUIPMENT_CASH = 'ROLE_EQUIPMENT_CASH';
    public const ROLE_COMPRESSOR_OPERATOR = 'ROLE_COMPRESSOR_OPERATOR';
    public const ROLE_BAR_MANAGER = 'ROLE_BAR_MANAGER';
    public const ROLE_PRESS_OFFICER = 'ROLE_PRESS_OFFICER';
    public const ROLE_TREASURER = 'ROLE_TREASURER';
    public const ROLE_BOARD = 'ROLE_BOARD';
    public const ROLE_ADMIN = 'ROLE_ADMIN';
    public const ROLE_SUPER_ADMIN = 'ROLE_SUPER_ADMIN';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $user = null;

    /**
     * @var list<string>
     */
    #[ORM\Column]
    private array $roles = [];

    /** @var array<string, array{startsAt: ?string, endsAt: ?string}>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $roleValidity = null;

    /**
     * @var string|null Das gehashte Passwort
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\OneToOne(mappedBy: 'user', cascade: ['persist'])]
    private ?Member $member = null;

    #[ORM\OneToOne(cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Person $person = null;

    #[ORM\Column(length: 150)]
    private ?string $email = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isVerified = false;

    /**
     * Wird nicht in der Datenbank gespeichert.
     */
    private ?string $plainPassword = null;

    public function __toString(): string
    {
        return (string) $this->user;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?string
    {
        return $this->user;
    }

    public function setUser(string $user): static
    {
        $this->user = trim($user);

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->user;
    }

    /**
     * Liefert die tatsächlich gespeicherten Rollen.
     *
     * Im Gegensatz zu getRoles() werden hier keine
     * Kompatibilitätsrollen ergänzt.
     *
     * @return list<string>
     */
	public function getStoredRoles(): array
	{
		return array_values(
			array_unique($this->roles)
		);
	}

	/**
	 * @return list<string>
	 */
	public function getRoles(): array
	{
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
		$roles = array_values(array_filter(
            $this->roles,
            fn (string $role): bool => $role !== self::ROLE_MEMBER && $this->isRoleValidOn($role, $today)
        ));
        $roles[] = self::ROLE_USER;
        if ($this->member !== null && $this->member->getClub() !== null && $this->member->isActiveMember()) {
            $roles[] = self::ROLE_MEMBER;
        }

		return array_values(
			array_unique($roles)
		);
	}

	/**
	 * @param list<string> $roles
	 */
	public function setRoles(array $roles): static
	{
		$roles = array_filter(
			$roles,
			static fn (mixed $role): bool =>
				is_string($role)
				&& str_starts_with($role, 'ROLE_')
		);

		$this->roles = array_values(
			array_unique($roles)
		);
        if ($this->roleValidity !== null) {
            $this->roleValidity = array_intersect_key($this->roleValidity, array_flip($this->roles));
        }

		return $this;
	}

	public function addRole(string $role): static
	{
		if (!str_starts_with($role, 'ROLE_')) {
			return $this;
		}

		if (!in_array($role, $this->roles, true)) {
			$this->roles[] = $role;
		}

		return $this;
	}

	public function removeRole(string $role): static
	{
		$this->roles = array_values(
			array_filter(
				$this->roles,
				static fn (string $existingRole): bool =>
					$existingRole !== $role
			)
		);
        unset($this->roleValidity[$role]);

		return $this;
	}

    /** @return array<string, array{startsAt: ?string, endsAt: ?string}> */
    public function getRoleValidity(): array
    {
        return $this->roleValidity ?? [];
    }

    /**
     * @param array<string, array{startsAt: ?string, endsAt: ?string}> $validity
     */
    public function setRoleValidity(array $validity): void
    {
        $allowed = array_diff(array_values(self::getAvailableRoles()), [self::ROLE_USER, self::ROLE_MEMBER]);
        foreach ($validity as $role => $window) {
            if (!in_array($role, $allowed, true) || !in_array($role, $this->roles, true)
                || !is_array($window) || !array_key_exists('startsAt', $window) || !array_key_exists('endsAt', $window)) {
                throw new \InvalidArgumentException('Ungültige Rollengültigkeit.');
            }
            foreach (['startsAt', 'endsAt'] as $key) {
                $date = $window[$key];
                if ($date !== null && (!is_string($date) || !$this->isValidDate($date))) {
                    throw new \InvalidArgumentException('Ungültiges Datum für die Rollengültigkeit.');
                }
            }
            if ($window['startsAt'] !== null && $window['endsAt'] !== null && $window['startsAt'] > $window['endsAt']) {
                throw new \InvalidArgumentException('Das Ende darf nicht vor dem Beginn liegen.');
            }
        }
        $this->roleValidity = $validity === [] ? null : $validity;
    }

    private function isRoleValidOn(string $role, string $today): bool
    {
        if (!array_key_exists($role, $this->roleValidity ?? [])) {
            return true;
        }
        $window = $this->roleValidity[$role];
        if (!is_array($window) || !array_key_exists('startsAt', $window) || !array_key_exists('endsAt', $window)
            || ($window['startsAt'] !== null && (!is_string($window['startsAt']) || !$this->isValidDate($window['startsAt'])))
            || ($window['endsAt'] !== null && (!is_string($window['endsAt']) || !$this->isValidDate($window['endsAt'])))
            || ($window['startsAt'] !== null && $window['endsAt'] !== null && $window['startsAt'] > $window['endsAt'])) {
            throw new \UnexpectedValueException('Ungültige gespeicherte Rollengültigkeit.');
        }
        return ($window['startsAt'] === null || $window['startsAt'] <= $today)
            && ($window['endsAt'] === null || $window['endsAt'] >= $today);
    }

    private function isValidDate(string $date): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

	public function hasRole(string $role): bool
	{
		return in_array(
			$role,
			$this->getRoles(),
			true
		);
	}

    public function getPassword(): string
    {
        return (string) $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getSalt(): ?string
    {
        return null;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): static
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }

    public function getMember(): ?Member
    {
        return $this->member;
    }

    public function setMember(?Member $member): static
    {
        if ($this->member === $member) {
            return $this;
        }
        $previous = $this->member;
        $this->member = $member;
        if ($previous !== null && $previous->getUser() === $this) {
            $previous->setUser(null);
        }
        if ($member !== null) {
            $this->person = $member->getPerson();
        }
        if ($member !== null && $member->getUser() !== $this) {
            $member->setUser($this);
        }
        return $this;
    }

    public function getPerson(): ?Person
    {
        return $this->person;
    }

    public function setPerson(?Person $person): static
    {
        if ($this->member !== null && $this->member->getPerson() !== $person) {
            throw new \InvalidArgumentException('Benutzerkonto und Mitgliedschaft müssen dieselbe Person verwenden.');
        }
        $this->person = $person;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = trim($email);

        return $this;
    }

    public function getIsVerified(): bool
    {
        return $this->isVerified;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function displayPassword(): string
    {
        return '******';
    }

    public function getHiddenPassword(): string
    {
        return '******';
    }

    /**
     * Rollen, die in Benutzerverwaltung/Formularen angeboten werden können.
     *
     * @return array<string, string>
     */
    public static function getAvailableRoles(): array
    {
        return [
            'Benutzer' => self::ROLE_USER,
            'Mitglied' => self::ROLE_MEMBER,
            'Ausbilder / Trainer' => self::ROLE_INSTRUCTOR,
            'Ausbildungsleiter' => self::ROLE_TRAINING_MANAGER,
            'Kursverwaltung' => self::ROLE_COURSE_ADMIN,
            'Gerätewart' => self::ROLE_EQUIPMENT_MANAGER,
            'Gerätekasse' => self::ROLE_EQUIPMENT_CASH,
            'Kompressorbediener' => self::ROLE_COMPRESSOR_OPERATOR,
            'Kassenwart' => self::ROLE_TREASURER,
            'Thekenleitung' => self::ROLE_BAR_MANAGER,
            'Pressewart' => self::ROLE_PRESS_OFFICER,
            'Vorstand' => self::ROLE_BOARD,
            'Administrator' => self::ROLE_ADMIN,
            'Super-Administrator' => self::ROLE_SUPER_ADMIN,
        ];
    }
}