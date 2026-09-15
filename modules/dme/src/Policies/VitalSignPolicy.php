<?php

declare(strict_types=1);

namespace Keneya\Dme\Policies;

use Illuminate\Database\Eloquent\Model;
use Keneya\Dme\Contracts\DmeUser;

/**
 * Constantes vitales.
 *
 * Historisation (§40) : un relevé n'est jamais modifié ni supprimé. Une
 * erreur de saisie se corrige par un nouveau relevé commenté, ce qui
 * préserve la traçabilité clinique.
 */
class VitalSignPolicy extends DomainPolicy
{
    protected string $viewPermission = 'vitals.view';

    protected string $createPermission = 'vitals.create';

    protected string $updatePermission = '';

    public function update(DmeUser $user, Model $model): bool
    {
        return false;
    }
}
