<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Compte Django (auth_user) de Datensis et sociétés (mydocdev_client) auxquelles il est rattaché.
 */
final class DatensisUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    /**
     * @param array<int, string> $clients nom de chaque société, indexé par id
     */
    public function __construct(
        private readonly int $id,
        private readonly string $username,
        private readonly string $email,
        private readonly string $firstName,
        private readonly string $lastName,
        private readonly string $password,
        private readonly bool $active,
        private readonly array $clients,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getDisplayName(): string
    {
        $fullName = trim($this->firstName.' '.$this->lastName);

        return '' !== $fullName ? $fullName : $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * @return array<int, string>
     */
    public function getClients(): array
    {
        return $this->clients;
    }

    public function hasClient(int $clientId): bool
    {
        return isset($this->clients[$clientId]);
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }
}
