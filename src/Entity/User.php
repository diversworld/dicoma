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
    public const ROLE_EQUIPMENT_MANAGER = 'ROLE_EQUIPMENT_MANAGER';
    public const ROLE_COMPRESSOR_OPERATOR = 'ROLE_COMPRESSOR_OPERATOR';
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

    /**
     * @var string|null Das gehashte Passwort
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\OneToOne(mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?Member $member = null;

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
		$roles = $this->roles;

		$roles[] = self::ROLE_MEMBER;

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

		return $this;
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
        if ($member === null && $this->member !== null) {
            $this->member->setUser(null);
        }

        if ($member !== null && $member->getUser() !== $this) {
            $member->setUser($this);
        }

        $this->member = $member;

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
            'Mitglied' => self::ROLE_MEMBER,
            'Ausbilder / Trainer' => self::ROLE_INSTRUCTOR,
            'Gerätewart' => self::ROLE_EQUIPMENT_MANAGER,
            'Kompressorbediener' => self::ROLE_COMPRESSOR_OPERATOR,
            'Kassenwart' => self::ROLE_TREASURER,
            'Vorstand' => self::ROLE_BOARD,
            'Administrator' => self::ROLE_ADMIN,
            'Super-Administrator' => self::ROLE_SUPER_ADMIN,
        ];
    }
}