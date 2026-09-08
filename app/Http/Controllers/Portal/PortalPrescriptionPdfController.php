<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Keneya\Dme\Models\Prescription;
use Keneya\Dme\Services\Documents\PdfGenerator;

/**
 * Telechargement d'une ordonnance en PDF depuis le portail patient.
 *
 * Memes garde-fous que le telechargement des pieces jointes : l'ordonnance
 * doit appartenir au patient designe par le jeton, et le code d'acces doit
 * avoir ete valide dans cette session. Sans cela, connaitre l'identifiant
 * d'une ordonnance suffirait a la lire.
 *
 * Depuis la v3.3.1 l'ordonnance vit dans le dossier medical : l'appartenance
 * se verifie donc contre le dossier du patient, et non plus contre sa fiche
 * WorkFlow. Un patient sans dossier medical n'a aucune ordonnance a lire.
 */
class PortalPrescriptionPdfController extends Controller
{
    public function __invoke(
        Request $request,
        string $token,
        Prescription $prescription,
        PdfGenerator $pdfs,
    ): Response {
        $patient = Patient::where('portal_token', $token)->firstOrFail();

        abort_unless($request->session()->get('portal.'.$patient->getKey()) === true, 403,
            "Saisissez d'abord votre code d'acces.");

        $dossier = $patient->dossierMedical();

        abort_unless($dossier && (int) $prescription->patient_id === (int) $dossier->getKey(), 404);

        $prescription->load(['patient', 'doctor', 'items']);

        return response($pdfs->prescription($prescription), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf(
                'attachment; filename="ordonnance-%s.pdf"',
                $prescription->prescription_number,
            ),
        ]);
    }
}
