<?php

namespace App\Datensis;

use Doctrine\DBAL\Connection;

/**
 * Permet de dégrader proprement l'affichage sur une base Datensis où un module (facturation…) n'est pas migré.
 */
final class DatensisSchema
{
    /** @var array<string, bool> */
    private array $tables = [];

    public function __construct(private readonly Connection $datensisConnection)
    {
    }

    public function hasTable(string $table): bool
    {
        return $this->tables[$table] ??= $this->datensisConnection->createSchemaManager()->tablesExist([$table]);
    }

    public function hasTables(string ...$tables): bool
    {
        foreach ($tables as $table) {
            if (!$this->hasTable($table)) {
                return false;
            }
        }

        return true;
    }
}
