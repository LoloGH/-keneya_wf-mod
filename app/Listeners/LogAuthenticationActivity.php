<?php

namespace App\Listeners;

use App\Support\Audit;
use App\Support\Roles;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

/**
 * Journalise les connexions et deconnexions (addendum v2, point 7).
 */
class LogAuthenticationActivity
{
    public function handleLogin(Login $event): void
    {
        Audit::log(
            Audit::EVENT_LOGIN,
            sprintf('%s s\'est connecte (%s).', $event->user->name, Roles::label($event->user->scopedRole())),
            $event->user,
        );
    }

    public function handleLogout(Logout $event): void
    {
        if (! $event->user) {
            return;
        }

        Audit::log(
            Audit::EVENT_LOGOUT,
            sprintf('%s s\'est deconnecte.', $event->user->name),
            $event->user,
        );
    }
}
