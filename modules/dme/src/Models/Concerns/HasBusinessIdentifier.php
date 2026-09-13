<?php

declare(strict_types=1);

namespace Keneya\Dme\Models\Concerns;

use Keneya\Dme\Services\Identifiers\IdentifierGenerator;

/**
 * Attribue automatiquement l'identifiant métier lisible (§37) à la
 * création du modèle, s'il n'a pas été fourni explicitement.
 *
 * Le modèle utilisateur doit déclarer :
 *   - `identifierPrefixKey()` : la clé du préfixe dans config('dme.identifiers')
 *   - `identifierColumn()`    : la colonne portant l'identifiant
 *
 * L'identifiant est stable : il est écrit une seule fois et n'est jamais
 * recalculé, y compris si le modèle change d'année ou de service.
 */
trait HasBusinessIdentifier
{
    protected static function bootHasBusinessIdentifier(): void
    {
        static::creating(function ($model): void {
            $column = $model->identifierColumn();

            if (blank($model->{$column})) {
                $model->{$column} = app(IdentifierGenerator::class)
                    ->next($model->identifierPrefixKey());
            }
        });
    }

    abstract public function identifierPrefixKey(): string;

    public function identifierColumn(): string
    {
        return 'reference';
    }
}
