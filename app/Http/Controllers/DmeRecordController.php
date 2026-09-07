<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Keneya\Dme\Patients\PatientIdentifierResolver;

/**
 * Passage de « Mes patients » au dossier medical complet (v3.3.0).
 *
 * Le point d'entree du module n'est pas une interface de premier niveau :
 * c'est cette redirection, posee sur un patient deja affiche dans /service.
 * Le medecin y arrive avec sa session WorkFlow, et le module la reprend telle
 * quelle — il n'y a pas de seconde authentification, pas de second mot de
 * passe, pas de second annuaire de comptes.
 *
 * Ce que fait cette classe, et rien d'autre :
 *
 *   1. verifier que l'hote accorde bien l'acces au dossier medical — la meme
 *      capacite que celle qui fait apparaitre le bouton, pour qu'une URL
 *      tapee a la main ne contourne pas l'interface ;
 *   2. relier le patient WorkFlow a son dossier du DME, ou le creer si c'est
 *      son premier passage par la ;
 *   3. rediriger vers le module.
 */
class DmeRecordController extends Controller
{
    public function __invoke(Patient $patient, PatientIdentifierResolver $resolver): RedirectResponse
    {
        $user = Auth::user();

        // Refus lisible plutot qu'un 403 : dans un hopital, personne ne doit
        // rester devant une page d'erreur technique. Meme regle que le
        // cloisonnement des roles (EnsureRoleScope).
        if (! $user->canAccessDme()) {
            return redirect()
                ->to($user->homeUrl() ?? route('home'))
                ->with('service.error', "L'acces au dossier medical complet n'est pas active pour votre compte.");
        }

        $dossier = $resolver->resolve(
            system: 'keneya_workflow',
            value: (string) $patient->patient_code,
            attributes: [
                'name' => $patient->name,
                'sex' => $patient->gender,
                'age' => $patient->age,
                'phone' => $patient->mobile,
                'label' => 'Dossier KEneYa WorkFlow',
            ],
        );

        return redirect()->route('dme.patients.show', $dossier);
    }
}
