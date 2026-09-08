<?php

namespace App\Http\Controllers\Service;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Keneya\Dme\Models\Prescription;
use Keneya\Dme\Services\Documents\PdfGenerator;

/**
 * Vues imprimables d'une piece jointe ou d'une ordonnance (v3.2, point 3).
 *
 * Un PDF s'imprime directement depuis le visualiseur du navigateur ; une image
 * a besoin d'une page qui la cadre proprement. Une ordonnance est rendue en
 * HTML imprimable, coherent avec le PDF deja genere par dompdf.
 */
class PrintableController extends Controller
{
    public function attachment(Request $request, Attachment $attachment): View
    {
        $this->assertDoctorMayRead($request, $attachment);

        return view('print.attachment', [
            'attachment' => $attachment,
            'hospitalName' => hospital_name(),
            'downloadUrl' => route('service.attachment', $attachment),
        ]);
    }

    /**
     * Le meme document que le PDF, rendu en HTML (v3.3.1) : le patient doit
     * voir l'ordonnance de son medecin, quel que soit le rendu.
     */
    public function prescription(Request $request, Prescription $prescription, PdfGenerator $pdfs): View
    {
        // L'ordonnance designe un compte, non plus une fiche de service.
        abort_unless(
            (int) $prescription->doctor_id === (int) $request->user()->getKey(),
            403,
            "Cette ordonnance n'est pas la votre.",
        );

        return $pdfs->prescriptionView($prescription);
    }

    private function assertDoctorMayRead(Request $request, Attachment $attachment): void
    {
        $user = $request->user();

        $allowed = $attachment->visit
            && $user->doctors()->pluck('service_id')->contains($attachment->visit->service_id);

        if (! $allowed) {
            $allowed = $attachment->patient
                ->history()
                ->whereIn('doctor_id', $user->doctors()->pluck('id'))
                ->exists();
        }

        abort_unless($allowed, 403, 'Cette piece jointe ne releve pas de vos services.');
        abort_unless(Storage::disk('attachments')->exists($attachment->path), 404);
    }
}
