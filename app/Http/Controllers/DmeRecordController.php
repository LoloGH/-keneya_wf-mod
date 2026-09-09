<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Support\Dme\PatientProjection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Passage de « Mes patients » au dossier medical complet (v3.3.0).
 *
 * Le point d'entree du module n'est pas une interface de premier niveau :
 * c'est cette redirection, posee sur un patient deja affiche dans /service.
 * Le medecin y arrive avec sa session WorkFlow, et le module la reprend telle
 * quelle : il n'y a pas de seconde authentification, pas de second mot de
 * passe, pas de second annuaire de comptes.
 *
 * Ce que fait cette classe, et rien d'autre :
 *
 *   1. verifier que l'hote accorde bien l'acces au dossier medical : la meme
 *      capacite que celle qui fait apparaitre le bouton, pour qu'une URL
 *      tapee a la main ne contourne pas l'interface ;
 *   2. relier le patient WorkFlow a son dossier du DME, ou le creer si c'est
 *      son premier passage par la ;
 *   3. rediriger vers le module.
 */
class DmeRecordController extends Controller
{
    public function __invoke(Patient $patient): RedirectResponse
    {
        $user = Auth::user();

        // Refus lisible plutot qu'un 403 : dans un hopital, personne ne doit
        // rester devant une page d'erreur technique. Meme regle, et meme cle
        // de session, que le cloisonnement des roles (EnsureRoleScope) : la
        // cle generique `error` est la seule que la mise en page affiche dans
        // toutes les interfaces. Une cle propre a /service laisserait le
        // message invisible partout ailleurs.
        if (! $user->canAccessDme()) {
            return redirect()
                ->to($user->homeUrl() ?? route('home'))
                ->with('error', "L'acces au dossier medical complet n'est pas active pour votre compte.");
        }

        $dossier = PatientProjection::resolve($patient);

        return redirect()->route('dme.patients.show', $dossier);
    }
}
