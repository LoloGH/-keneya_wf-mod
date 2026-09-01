<?php

namespace App\Http\Controllers\Service;

use App\Http\Controllers\Controller;
use App\Models\Prescription;
use App\Support\PrescriptionPdfData;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Export PDF d'une ordonnance, depuis /service.
 */
class PrescriptionPdfController extends Controller
{
    public function __invoke(Request $request, Prescription $prescription): Response
    {
        $doctorIds = $request->user()->doctors()->pluck('id');

        abort_unless($doctorIds->contains($prescription->doctor_id), 403, "Cette ordonnance n'est pas la votre.");

        $prescription->load(['patient', 'doctor.user', 'visit.service']);

        $pdf = Pdf::loadView('pdf.prescription', PrescriptionPdfData::for($prescription))->setPaper('a4');

        return $pdf->download(sprintf('ordonnance-%s-%d.pdf', $prescription->patient->patient_code, $prescription->getKey()));
    }
}
