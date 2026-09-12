<?php

namespace App\Http\Controllers;

use App\Services\DocumentVerificationService;
use Illuminate\Contracts\View\View;

/**
 * Verification publique d'un document du dossier medical par QR code
 * (v3.4).
 *
 * Point d'entree volontairement mince : la resolution de la reference
 * appartient a DocumentVerificationService, cette classe ne fait que la
 * demander et choisir la vue. Aucune authentification, aucune ecriture :
 * une personne qui scanne le QR code d'une ordonnance ne doit jamais
 * croiser autre chose que la preuve que le document existe.
 */
class DocumentVerificationController extends Controller
{
    public function __invoke(string $reference, DocumentVerificationService $service): View
    {
        return view('pages.document-verification', [
            'result' => $service->verify($reference),
        ]);
    }
}
