<?php

namespace App\Security;

use Doctrine\DBAL\Connection;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @implements UserProviderInterface<DatensisUser>
 */
final class DatensisUserProvider implements UserProviderInterface
{
    public function __construct(private readonly Connection $datensisConnection)
    {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $row = $this->datensisConnection->fetchAssociative(
            'SELECT id, username, email, first_name, last_name, password, is_active FROM auth_user WHERE username = ?',
            [$identifier],
        );

        if (false === $row) {
            throw new UserNotFoundException(\sprintf('Utilisateur Datensis "%s" introuvable.', $identifier));
        }

        $clients = $this->datensisConnection->fetchAllKeyValue(
            'SELECT c.id, c.company_name
             FROM mydocdev_client c
             INNER JOIN mydocdev_client_users cu ON cu.client_id = c.id
             WHERE cu.user_id = ?
             ORDER BY c.company_name',
            [$row['id']],
        );

        return new DatensisUser(
            (int) $row['id'],
            (string) $row['username'],
            (string) $row['email'],
            (string) $row['first_name'],
            (string) $row['last_name'],
            (string) $row['password'],
            (bool) $row['is_active'],
            array_map('strval', $clients),
        );
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof DatensisUser) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', $user::class));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return DatensisUser::class === $class;
    }
}
