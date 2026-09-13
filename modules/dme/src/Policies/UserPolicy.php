<?php

declare(strict_types=1);

namespace Keneya\Dme\Policies;

use Keneya\Dme\Contracts\DmeUser;

/** Administration des comptes (§31). */
class UserPolicy
{
    public function viewAny(DmeUser $user): bool
    {
        return $user->isActive() && $user->can('users.manage');
    }

    public function view(DmeUser $user, DmeUser $target): bool
    {
        return $this->viewAny($user) || $user->is($target);
    }

    public function create(DmeUser $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(DmeUser $user, DmeUser $target): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Un compte n'est jamais supprimé (il porte l'historique médical) :
     * il est désactivé. On empêche aussi un administrateur de se
     * désactiver lui-même, ce qui pourrait fermer l'accès à l'application.
     */
    public function deactivate(DmeUser $user, DmeUser $target): bool
    {
        return $this->viewAny($user) && ! $user->is($target);
    }
}
