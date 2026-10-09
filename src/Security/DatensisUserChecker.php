<?php

namespace App\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Vérifications faites après le contrôle du mot de passe, pour ne pas révéler l'existence d'un compte.
 */
final class DatensisUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if (!$user instanceof DatensisUser) {
            return;
        }

        if (!$user->isActive()) {
            throw new CustomUserMessageAccountStatusException('Votre compte Datensis est désactivé.');
        }

        if ([] === $user->getClients()) {
            throw new CustomUserMessageAccountStatusException("Votre compte n'est rattaché à aucune société dans Datensis.");
        }
    }
}
