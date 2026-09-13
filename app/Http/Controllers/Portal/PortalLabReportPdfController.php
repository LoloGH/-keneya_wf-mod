<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Keneya\Dme\Models\LabOrder;
use Keneya\Dme\Services\Documents\PdfGenerator;

/**
 * Telechargement d'un compte rendu d'analyses en PDF depuis le portail (v3.4).
 *
 * Le patient lit ses resultats a l'ecran, mais repart souvent avec une feuille
 * a montrer ailleurs : un autre medecin, un employeur, une assurance. Le PDF
 * est celui que le module genere pour le laboratoire, avec son en-tete
 * d'etablissement et son QR code de verification — pas une seconde mise en
 * page qui divergerait de la premiere.
 *
 * Memes garde-fous que l'ordonnance : la demande doit appartenir au dossier
 * medical du patient designe par le jeton, le code doit avoir ete valide, et
 * une demande encore au laboratoire n'est pas telechargeable.
 */
class PortalLabReportPdfController extends Controller
{
    /** Les etats d'une demande rendue, et donc lisible par le patient. */
    public const ETATS_RENDUS = ['available', 'validated'];

    public function __invoke(
        Request $request,
        string $token,
        LabOrder $labOrder,
        PdfGenerator $pdfs,
    ): Response {
        $patient = Patient::where('portal_token', $token)->firstOrFail();

        abort_unless($request->session()->get('portal.'.$patient->getKey()) === true, 403,
            "Saisissez d'abord votre code d'acces.");

        $dossier = $patient->dossierMedical();

        abort_unless($dossier && (int) $labOrder->patient_id === (int) $dossier->getKey(), 404);
        abort_unless(in_array($labOrder->status, self::ETATS_RENDUS, true), 404);

        return response($pdfs->labReport($labOrder), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf(
                'attachment; filename="analyses-%s.pdf"',
                $labOrder->order_number,
            ),
        ]);
    }
}
