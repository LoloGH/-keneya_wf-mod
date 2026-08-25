<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Prescription;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;

/**
 * Vues imprimables cote /admin (v3.2, point 3).
 *
 * Meme rendu que cote medecin, mais sur des URL propres au role : aucune
 * adresse n'est partagee entre deux interfaces. L'admin a acces a tout
 * dossier, il n'y a donc pas de restriction par service ici — seul le
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

    public function prescription(Prescription $prescription): View
    {
        $prescription->load(['patient', 'doctor.user', 'visit.service']);

        return view('print.prescription', [
            'prescription' => $prescription,
            'hospitalName' => hospital_name(),
            // Le telechargement PDF passe par la route du medecin, inaccessible
            // a l'admin : la vue imprimable se suffit a elle-meme ici.
            'pdfUrl' => null,
        ]);
    }
}
