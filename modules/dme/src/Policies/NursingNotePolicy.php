<?php

declare(strict_types=1);

namespace Keneya\Dme\Policies;

use Keneya\Dme\Contracts\DmeUser;
use Illuminate\Database\Eloquent\Model;

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
