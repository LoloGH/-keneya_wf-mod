<?php

namespace App\Http\Controllers\Service;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Keneya\Dme\Models\Prescription;
use Keneya\Dme\Services\Documents\PdfGenerator;

/**
 * Export PDF d'une ordonnance, depuis /service.
 *
 * Le document est celui du dossier medical (v3.3.1) : sa mise en page vient
 * du module, la signature du prescripteur et les cachets viennent de
 * WorkFlow. Un seul document, quel que soit l'ecran d'ou on l'imprime.
 */
class PrescriptionPdfController extends Controller
{
    public function __invoke(Request $request, Prescription $prescription, PdfGenerator $pdfs): Response
    {
        // L'ordonnance designe un compte, non plus une fiche de service : la
        // table du dossier medical ne connait que `users`.
        abort_unless(
            (int) $prescription->doctor_id === (int) $request->user()->getKey(),
            403,
            "Cette ordonnance n'est pas la votre.",
        );

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
