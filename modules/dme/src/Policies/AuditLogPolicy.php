<?php

declare(strict_types=1);

namespace Keneya\Dme\Policies;

use Keneya\Dme\Contracts\DmeUser;
use Keneya\Dme\Models\AuditLog;

/**
 * Journal d'audit (§30) : append-only.
 *
 * Aucune permission de modification ni de suppression n'existe : pour un
 * utilisateur standard comme pour un administrateur, le journal est en
 * lecture seule. Le modèle AuditLog bloque en outre ces opérations au
 * niveau Eloquent, ce qui protège aussi les accès programmatiques.
 */
class AuditLogPolicy
{
    public function viewAny(DmeUser $user): bool
    {
        return $user->isActive() && $user->can('audit.view');
    }

    public function view(DmeUser $user, AuditLog $log): bool
    {
        return $this->viewAny($user);
    }

    public function create(DmeUser $user): bool
    {
        return false;
    }

    public function update(DmeUser $user, AuditLog $log): bool
    {
        return false;
    }

    public function delete(DmeUser $user, AuditLog $log): bool
    {
        return false;
    }
}
