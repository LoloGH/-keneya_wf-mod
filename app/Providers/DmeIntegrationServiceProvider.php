<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Dme\WorkflowSmsDispatcher;
use Illuminate\Support\ServiceProvider;
use Keneya\Dme\Contracts\SmsDispatcherContract;
use Keneya\Dme\Dme;

/**
 * Montage du module Dossier Medical Electronique (v3.3.0).
 *
 * Tout ce que WorkFlow doit dire au module tient ici : qui a le droit d'y
 * entrer, et par ou partent ses SMS. Le module, lui, n'a rien a savoir de
 * WorkFlow.
 *
 * Ce fournisseur est declare dans bootstrap/providers.php, donc enregistre
 * apres celui du package : ses liaisons remplacent celles du module, ce qui
 * est exactement le comportement documente par DmeServiceProvider.
 */
class DmeIntegrationServiceProvider extends ServiceProvider
{
    /**
     * Un seul chemin d'envoi de SMS dans l'etablissement.
     *
     * Le module lie lui-meme `SmsDispatcherContract` a sa file interne
     * (`QueuedSmsDispatcher`), en repli. Cette liaison-ci, enregistree apres,
     * l'ecrase : les SMS du dossier medical passent donc par `SendSmsJob` et
     * atterrissent dans la table `sms_messages` de WorkFlow.
     *
     * Effet de bord voulu : le module constate que sa file interne n'est plus
     * l'implementation retenue, et cesse alors d'enregistrer ses commandes de
     * suivi d'acheminement ainsi que la tache planifiee qui les appelle.
     */
    public function register(): void
    {
        $this->app->singleton(SmsDispatcherContract::class, WorkflowSmsDispatcher::class);
    }

    /**
     * La decision d'acces de haut niveau appartient a WorkFlow.
     *
     * Elle se lit dans le systeme de types de personnel deja en place
     * (v3.2.2) : la capacite `can_access_dme`, cochee dans /admin. Un compte
     * qui ne la porte pas ne voit pas l'action « Dossier medical complet »
     * dans « Mes patients », et une URL du module tapee a la main lui est
     * refusee par le meme critere — une seule regle, pas deux.
     */
    public function boot(): void
    {
        Dme::authorizeAccessUsing(
            fn ($user) => $user instanceof User && $user->canAccessDme()
        );
    }
}
