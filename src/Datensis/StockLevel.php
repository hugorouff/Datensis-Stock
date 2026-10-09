<?php

namespace App\Datensis;

/**
 * Niveaux calculés comme Article.get_niveau_alerte_stock() dans Datensis.
 */
enum StockLevel: string
{
    case Normal = 'NORMAL';
    case Faible = 'FAIBLE';
    case Critique = 'CRITIQUE';
    case Rupture = 'RUPTURE';

    public static function compute(float $stock, ?float $minimum, ?float $critique): self
    {
        return match (true) {
            null !== $critique && 0.0 !== $critique && $stock <= $critique => self::Critique,
            null !== $minimum && 0.0 !== $minimum && $stock <= $minimum => self::Faible,
            $stock <= 0 => self::Rupture,
            default => self::Normal,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'En stock',
            self::Faible => 'Stock faible',
            self::Critique => 'Stock critique',
            self::Rupture => 'Rupture',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Normal => 'text-bg-success',
            self::Faible => 'text-bg-warning',
            self::Critique, self::Rupture => 'text-bg-danger',
        };
    }

    public function isAlert(): bool
    {
        return self::Normal !== $this;
    }
}
