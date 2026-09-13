<?php

declare(strict_types=1);

namespace Keneya\Dme\Policies;

use Keneya\Dme\Contracts\DmeUser;
use Keneya\Dme\Models\LabOrder;

/**
 * Laboratoire (§23).
 *
 * Le médecin prescrit les analyses, le laboratoire les exécute : les deux
 * actes reposent sur des permissions différentes.
 */
class LabOrderPolicy extends DomainPolicy
{
    protected string $viewPermission = 'laboratory.view';

    protected string $createPermission = 'laboratory.orders.create';

    protected string $updatePermission = 'laboratory.results.create';

    /** Saisir un résultat pour un examen de cette demande. */
    public function recordResult(DmeUser $user, LabOrder $order): bool
    {
        return $this->allows($user, 'laboratory.results.create')
            && $order->status !== 'cancelled';
    }

    /** Validation biologique du résultat. */
    public function validateResults(DmeUser $user, LabOrder $order): bool
    {
        return $this->allows($user, 'laboratory.results.validate')
            && in_array($order->status, ['in_progress', 'available'], true);
    }
}
