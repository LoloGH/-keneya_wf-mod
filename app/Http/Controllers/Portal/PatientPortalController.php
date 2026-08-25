<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Contracts\View\View;

/**
 * Portail patient public (v3.2, point 7).
 *
 * Aucune authentification : le lien lui-meme, porteur d'un UUID, tient lieu
 * d'adresse. Rien n'est affiche tant que le code a quatre chiffres n'a pas ete
 * valide — c'est le composant Livewire qui s'en charge.
 */
class PatientPortalController extends Controller
{
    public function __invoke(string $token): View
    {
        // Le jeton doit exister, sinon la page n'a pas lieu d'etre. On ne
        // distingue pas « jeton inconnu » de « jeton mal forme » : les deux
        // meritent la meme reponse.
        $patient = Patient::where('portal_token', $token)->firstOrFail();

        return view('pages.portal', ['token' => $patient->portal_token]);
    }
}
