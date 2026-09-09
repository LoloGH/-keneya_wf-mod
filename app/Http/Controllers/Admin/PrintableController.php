<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Keneya\Dme\Models\Prescription;
use Keneya\Dme\Services\Documents\PdfGenerator;

/**
 * Vues imprimables cote /admin (v3.2, point 3).
 *
 * Meme rendu que cote medecin, mais sur des URL propres au role : aucune
 * adresse n'est partagee entre deux interfaces. L'admin a acces a tout
 * dossier, il n'y a donc pas de restriction par service ici : seul le
 * cloisonnement de role, applique par la route, decide.
 */
class PrintableController extends Controller
{
    public function attachment(Attachment $attachment): View
    {
        abort_unless(Storage::disk('attachments')->exists($attachment->path), 404);

        return view('print.attachment', [
            'attachment' => $attachment,
            'hospitalName' => hospital_name(),
            'downloadUrl' => route('admin.attachment', $attachment),
        ]);
    }

    /**
     * L'ordonnance du dossier medical, rendue en HTML pour l'impression
     * navigateur (v3.3.1). Meme composition que le PDF : l'admin, le medecin
     * et le patient lisent le meme document sous le meme numero.
     */
    public function prescription(Prescription $prescription, PdfGenerator $pdfs): View
    {
        return $pdfs->prescriptionView($prescription);
    }
}
