<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Support\ServiceProvider;
use Keneya\Dme\Dme;
use Keneya\Dme\Models\User;

/**
 * Câblage de l'application hôte de test.
 *
 * Ce fournisseur montre, en quelques lignes, tout ce qu'une application
 * hôte a réellement à faire pour accueillir le module. Keneya Workflow
 * fera la même chose, avec ses propres règles.
 */
class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 1. L'hôte désigne le modèle utilisateur qui porte la session.
        //    Ici, l'annuaire du module lui-même — faute d'un autre.
        $this->app['config']->set('auth.providers.users.model', User::class);
    }

    public function boot(): void
    {
        // 2. L'hôte décide qui a le droit d'ouvrir un dossier médical.
        //
        //    Cette application de test l'accorde à tout utilisateur actif :
        //    c'est le point que Keneya Workflow remplacera par sa propre
        //    règle, par exemple une permission de son RBAC central.
        Dme::authorizeAccessUsing(
            static fn ($user) => (bool) ($user->is_active ?? false)
        );

        // 3. L'hôte fournirait ici son implémentation de l'envoi de SMS :
        //
        //    $this->app->singleton(
        //        \Keneya\Dme\Contracts\SmsDispatcherContract::class,
        //        \App\Sms\WorkflowSmsDispatcher::class,
        //    );
        //
        //    Tant qu'il n'en fournit aucune, le module retombe sur sa
        //    propre file d'attente (config dme.sms.dispatcher).
    }
}
