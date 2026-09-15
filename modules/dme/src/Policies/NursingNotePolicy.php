<?php

declare(strict_types=1);

namespace Keneya\Dme\Policies;

use Illuminate\Database\Eloquent\Model;
use Keneya\Dme\Contracts\DmeUser;

/** Soins infirmiers (§26) : transmissions non réinscriptibles. */
class NursingNotePolicy extends DomainPolicy
{
    protected string $viewPermission = 'nursing.view';

    protected string $createPermission = 'nursing.create';

    protected string $updatePermission = '';

    public function update(DmeUser $user, Model $model): bool
    {
        return false;
    }
}
