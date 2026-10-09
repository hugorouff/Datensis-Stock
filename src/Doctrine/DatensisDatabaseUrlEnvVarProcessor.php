<?php

namespace App\Doctrine;

use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;

/**
 * Sur Scalingo, la base Datensis est celle de l'extension PostgreSQL.
 * En local, on garde DATENSIS_DATABASE_URL.
 */
final class DatensisDatabaseUrlEnvVarProcessor implements EnvVarProcessorInterface
{
    public function getEnv(string $prefix, string $name, \Closure $getEnv): mixed
    {
        $scalingo = $_ENV['SCALINGO_POSTGRESQL_URL'] ?? $_SERVER['SCALINGO_POSTGRESQL_URL'] ?? false;
        if (!\is_string($scalingo) || '' === $scalingo) {
            $fromGetenv = getenv('SCALINGO_POSTGRESQL_URL');
            $scalingo = \is_string($fromGetenv) ? $fromGetenv : '';
        }

        if (str_starts_with($scalingo, 'postgres')) {
            return self::normalize($scalingo);
        }

        return $getEnv($name);
    }

    public static function getProvidedTypes(): array
    {
        return ['datensis_database_url' => 'string'];
    }

    private static function normalize(string $url): string
    {
        $url = preg_replace('#^postgres://#', 'postgresql://', $url) ?? $url;
        if (str_contains($url, 'serverVersion=')) {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').'serverVersion=16&charset=utf8';
    }
}
