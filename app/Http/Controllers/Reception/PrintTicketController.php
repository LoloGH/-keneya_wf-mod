<?php

namespace App\Http\Controllers\Reception;

use App\Http\Controllers\Controller;
use App\Models\Visit;
use App\Models\Visitor;
use Illuminate\Contracts\View\View;

/**
 * Ticket imprimable, patient ou visiteur (v3.2, point 9).
 *
 * Rendu HTML avec un CSS d'impression, et `window.print()` cote navigateur,
 * pas de PDF cote serveur : l'imprimante est deja branchee au poste de la
 * receptionniste, autant s'en servir directement.
 */
class PrintTicketController extends Controller
{
    public function patient(Visit $visit): View
    {
        $visit->load(['patient', 'service', 'pendingNextService']);

        return view('reception.print-ticket', [
            'kind' => 'patient',
            'hospitalName' => hospital_name(),
            'visit' => $visit,
            'visitor' => null,
        ]);
    }

    public function visitor(Visitor $visitor): View
    {
        $visitor->load(['service', 'patient']);

        return view('reception.print-ticket', [
            'kind' => 'visitor',
            'hospitalName' => hospital_name(),
            'visit' => null,
            'visitor' => $visitor,
        ]);
    }
}
