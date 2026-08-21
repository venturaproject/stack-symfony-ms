<?php

declare(strict_types=1);

namespace App\Domain\Auth\Entity;

use App\Domain\Auth\Event\UserActivated;
use App\Domain\Auth\Event\UserCreated;
use App\Domain\Auth\Event\UserDeactivated;
use App\Domain\Auth\Event\UserPasswordChanged;
use App\Domain\Auth\Event\UserTotpDisabled;
use App\Domain\Auth\Event\UserTotpEnabled;
use App\Domain\Auth\Event\UserUpdated;
use App\Domain\Auth\Exception\TotpNotEnabledException;
use App\Domain\Auth\Exception\TotpSetupNotInitiatedException;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use InvalidArgumentException;
use SharedKernel\Domain\Shared\HasDomainEvents;
use SharedKernel\Domain\Shared\RecordsDomainEvents;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface, PasswordAuthenticatedUserInterface, HasDomainEvents
{
    use RecordsDomainEvents;

    private ?string $id = null;
    private string $email;
    private string $passwordHash;
    private string $firstName;
    private string $lastName;
    private ?string $avatarUrl = null;
    private bool $active = true;
    private string $preferredLanguage = 'es';
    /** Set once setup is initiated; only meaningful together with $totpEnabled — see initiateTotpSetup(). */
    private ?string $totpSecret = null;
    private bool $totpEnabled = false;
    /** @var string[] sha256 hashes — never store backup codes in plaintext */
    private array $totpBackupCodeHashes = [];
    /** @var Collection<int, Role> */
    private Collection $roles;
    /** @var Collection<int, Permission> */
    private Collection $permissions;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->roles = new ArrayCollection();
        $this->permissions = new ArrayCollection();
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $hash): void
    {
        $this->passwordHash = $hash;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getAvatarUrl(): ?string
    {
        return $this->avatarUrl;
    }

    public function setAvatarUrl(?string $avatarUrl): void
    {
        $this->avatarUrl = $avatarUrl;
    }

    public function getPreferredLanguage(): string
    {
        return $this->preferredLanguage;
    }

    public function setPreferredLanguage(string $preferredLanguage): void
    {
        if (!\in_array($preferredLanguage, ['en', 'es'], true)) {
            throw new InvalidArgumentException(\sprintf('Unsupported language "%s"', $preferredLanguage));
        }

        $this->preferredLanguage = $preferredLanguage;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    /** @return string[] */
    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];
        foreach ($this->roles as $role) {
            $roles[] = 'ROLE_'.$role->getName();
            foreach ($role->getPermissions() as $permission) {
                $roles[] = $permission->getName();
            }
        }
        foreach ($this->permissions as $permission) {
            $roles[] = $permission->getName();
        }

        return array_unique($roles);
    }

    /** @return Collection<int, Role> */
    public function getRoleEntities(): Collection
    {
        return $this->roles;
    }

    public function addRole(Role $role): void
    {
        $this->roles->add($role);
    }

    /** @param Collection<int, Role> $roles */
    public function setRoles(Collection $roles): void
    {
        $this->roles = $roles;
    }

    /** @return Collection<int, Permission> */
    public function getDirectPermissions(): Collection
    {
        return $this->permissions;
    }

    /** @param Collection<int, Permission> $permissions */
    public function setDirectPermissions(Collection $permissions): void
    {
        $this->permissions = $permissions;
    }

    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function eraseCredentials(): void
    {
    }

    public function recordCreated(): void
    {
        $this->raise(new UserCreated($this->id, $this->email));
    }

    public function recordUpdated(): void
    {
        $this->raise(new UserUpdated($this->id, $this->email));
    }

    public function changeEmail(string $newEmail): void
    {
        $this->email = $newEmail;
    }

    public function changePassword(string $hashedPassword): void
    {
        $this->passwordHash = $hashedPassword;
        $this->raise(new UserPasswordChanged($this->id, $this->email));
    }

    public function deactivate(): void
    {
        $this->active = false;
        $this->raise(new UserDeactivated($this->id, $this->email));
    }

    public function activate(): void
    {
        $this->active = true;
        $this->raise(new UserActivated($this->id, $this->email));
    }

    public function isTotpEnabled(): bool
    {
        return $this->totpEnabled;
    }

    public function getTotpSecret(): ?string
    {
        return $this->totpSecret;
    }

    /**
     * @return string[]
     */
    public function getTotpBackupCodeHashes(): array
    {
        return $this->totpBackupCodeHashes;
    }

    /**
     * Stores a freshly generated secret pending confirmation — not active until
     * confirmTotpSetup() verifies the user actually scanned it and can produce a
     * valid code. Calling this again before confirming just replaces the pending
     * secret (e.g. the user reloaded the QR page), which is fine.
     */
    public function initiateTotpSetup(string $secret): void
    {
        $this->totpSecret = $secret;
    }

    /**
     * @param string[] $backupCodeHashes sha256 hashes of the plaintext codes shown
     *                                   to the user exactly once by the caller
     */
    public function confirmTotpSetup(array $backupCodeHashes): void
    {
        if (null === $this->totpSecret) {
            throw new TotpSetupNotInitiatedException();
        }

        $this->totpEnabled = true;
        $this->totpBackupCodeHashes = $backupCodeHashes;
        $this->raise(new UserTotpEnabled($this->id, $this->email));
    }

    public function disableTotp(): void
    {
        if (!$this->totpEnabled) {
            throw new TotpNotEnabledException();
        }

        $this->totpSecret = null;
        $this->totpEnabled = false;
        $this->totpBackupCodeHashes = [];
        $this->raise(new UserTotpDisabled($this->id, $this->email));
    }

    /**
     * Backup codes are single-use: removes the matching hash if present.
     */
    public function consumeBackupCode(string $codeHash): bool
    {
        $index = array_search($codeHash, $this->totpBackupCodeHashes, true);
        if (false === $index) {
            return false;
        }

        unset($this->totpBackupCodeHashes[$index]);
        $this->totpBackupCodeHashes = array_values($this->totpBackupCodeHashes);

        return true;
    }
}
