<?php

namespace App\Providers;

use App\Models\Doctor;
use App\Models\Setting;
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

        Dme::signaturesUsing(fn ($sujet) => $this->signatures($sujet));

        // L'en-tete des documents du module doit porter les coordonnees
        // saisies dans /admin, non celles du fichier de configuration : c'est
        // la que l'etablissement les tient a jour. Le module appelle ceci au
        // rendu, jamais a l'amorcage — aucune requete ajoutee par page.
        // La porte de sortie du module (v3.3.1). On y entre depuis « Mes
        // patients » ; sans ce lien, on n'en ressort que par le bouton
        // « precedent » du navigateur — ou par la deconnexion, ce qui est
        // pire. L'adresse depend du role, que le module ne connait pas.
        Dme::returnLinkUsing(fn ($user) => $user instanceof User ? [
            'label' => 'Retour a '.config('keneya.name'),
            'url' => $user->homeUrl() ?? route('home'),
        ] : null);

        Dme::facilityUsing(fn () => [
            'name' => hospital_name(),
            'address' => Setting::get(Setting::HOSPITAL_ADDRESS),
            'phone' => Setting::get(Setting::HOSPITAL_PHONE),
            'email' => Setting::get(Setting::HOSPITAL_EMAIL),
            'logo' => $this->monogrammeImprime(),
        ]);
    }

    /**
     * Le monogramme qui figurait deja en tete des documents imprimes.
     *
     * Un chemin absolu, comme la signature et les cachets : dompdf lit le
     * disque, et le module se charge d'encoder l'image quand il rend le
     * document pour un navigateur.
     */
    private function monogrammeImprime(): ?string
    {
        $chemin = public_path('images/keneya-icone-impression.png');

        return is_file($chemin) ? $chemin : null;
    }

    /**
     * Signature du prescripteur et cachets, pour un document du module.
     *
     * C'est l'autre moitie de la fusion decidee au §4 du chantier v3.3.1 :
     * l'ordonnance prend la forme du dossier medical, et la fonction que
     * WorkFlow avait seul — apposer la signature du medecin, son cachet et
     * celui de l'etablissement. Le module ne sait rien de tout cela ; il
     * demande, WorkFlow repond depuis ses propres tables.
     *
     * Le document designe un compte (`users`), la signature est deposee sur
     * une fiche `doctors` : {@see User::ficheSignataire()} fait le pont.
     *
     * @return array<string, ?string>
     */
    private function signatures(mixed $sujet): array
    {
        $auteur = $sujet?->doctor ?? null;
        $fiche = $auteur instanceof User ? $auteur->ficheSignataire() : null;

        return [
            'doctorSignature' => $fiche?->signatureFile(),
            'doctorStamp' => $fiche?->stampFile(),
            'facilityStamp' => Doctor::fichierExistant(Setting::get(Setting::HOSPITAL_STAMP_PATH)),
        ];
    }
}
