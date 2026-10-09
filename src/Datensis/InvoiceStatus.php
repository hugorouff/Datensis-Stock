<?php

namespace App\Datensis;

/**
 * STATUT_FACTURE_CHOICES de myfacturation.
 */
enum InvoiceStatus: string
{
    case Brouillon = 'BROUILLON';
    case Validee = 'VALIDEE';
    case Envoyee = 'ENVOYEE';
    case PartiellementPayee = 'PARTIELLEMENT_PAYEE';
    case Reglee = 'REGLEE';
    case Impayee = 'IMPAYEE';
    case Annulee = 'ANNULEE';
    case TransfereCompta = 'TRANSFERE_COMPTA';

    public function label(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::Validee => 'Validée',
            self::Envoyee => 'Envoyée',
            self::PartiellementPayee => 'Partiellement payée',
            self::Reglee => 'Réglée',
            self::Impayee => 'Impayée',
            self::Annulee => 'Annulée',
            self::TransfereCompta => 'Transférée en compta',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Brouillon, self::Annulee => 'text-bg-secondary',
            self::Validee => 'text-bg-primary',
            self::Envoyee => 'text-bg-info',
            self::PartiellementPayee => 'text-bg-warning',
            self::Reglee => 'text-bg-success',
            self::Impayee => 'text-bg-danger',
            self::TransfereCompta => 'text-bg-dark',
        };
    }

    public function chartColor(): string
    {
        return match ($this) {
            self::Brouillon, self::Annulee => '#94a3b8',
            self::Validee => '#3b82f6',
            self::Envoyee => '#6366f1',
            self::PartiellementPayee => '#f59e0b',
            self::Reglee => '#10b981',
            self::Impayee => '#ef4444',
            self::TransfereCompta => '#8b5cf6',
        };
    }

    /**
     * Statuts comptés dans le chiffre d'affaires.
     */
    public function isBilled(): bool
    {
        return !\in_array($this, [self::Brouillon, self::Annulee], true);
    }
}
