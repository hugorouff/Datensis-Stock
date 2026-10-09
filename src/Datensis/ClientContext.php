<?php

namespace App\Datensis;

use App\Security\DatensisUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Société Datensis (mydocdev_client) sur laquelle travaille l'utilisateur connecté.
 */
final class ClientContext
{
    private const SESSION_KEY = 'datensis_client_id';

    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getUser(): ?DatensisUser
    {
        $user = $this->security->getUser();

        return $user instanceof DatensisUser ? $user : null;
    }

    public function hasClient(): bool
    {
        return null !== $this->findClientId();
    }

    public function getClientId(): int
    {
        return $this->findClientId() ?? throw new AccessDeniedException('Aucune société Datensis disponible.');
    }

    public function getClientName(): string
    {
        return $this->getUser()?->getClients()[$this->getClientId()] ?? '';
    }

    public function switchTo(int $clientId): void
    {
        if (!$this->getUser()?->hasClient($clientId)) {
            throw new AccessDeniedException('Cette société ne fait pas partie de vos accès.');
        }

        $this->requestStack->getSession()->set(self::SESSION_KEY, $clientId);
    }

    private function findClientId(): ?int
    {
        $user = $this->getUser();
        if (null === $user || [] === $user->getClients()) {
            return null;
        }

        $selected = $this->requestStack->getSession()->get(self::SESSION_KEY);
        if (\is_int($selected) && $user->hasClient($selected)) {
            return $selected;
        }

        return array_key_first($user->getClients());
    }
}
