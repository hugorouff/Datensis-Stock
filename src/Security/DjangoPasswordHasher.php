<?php

namespace App\Security;

use Symfony\Component\PasswordHasher\Exception\InvalidPasswordException;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

/**
 * Format de Django « pbkdf2_sha256$<itérations>$<sel>$<hash base64> », pour accepter les mots de passe Datensis tels quels.
 */
final class DjangoPasswordHasher implements PasswordHasherInterface
{
    private const ALGORITHM = 'pbkdf2_sha256';
    private const ITERATIONS = 870000;

    public function hash(string $plainPassword): string
    {
        if (\strlen($plainPassword) > self::MAX_PASSWORD_LENGTH) {
            throw new InvalidPasswordException();
        }

        return $this->encode($plainPassword, bin2hex(random_bytes(11)), self::ITERATIONS);
    }

    public function verify(string $hashedPassword, string $plainPassword): bool
    {
        if ('' === $plainPassword || \strlen($plainPassword) > self::MAX_PASSWORD_LENGTH) {
            return false;
        }

        $parts = explode('$', $hashedPassword, 4);
        if (4 !== \count($parts) || self::ALGORITHM !== $parts[0] || !ctype_digit($parts[1])) {
            return false;
        }

        return hash_equals($hashedPassword, $this->encode($plainPassword, $parts[2], (int) $parts[1]));
    }

    public function needsRehash(string $hashedPassword): bool
    {
        return false;
    }

    private function encode(string $plainPassword, string $salt, int $iterations): string
    {
        $hash = base64_encode(hash_pbkdf2('sha256', $plainPassword, $salt, $iterations, 32, true));

        return \sprintf('%s$%d$%s$%s', self::ALGORITHM, $iterations, $salt, $hash);
    }
}
