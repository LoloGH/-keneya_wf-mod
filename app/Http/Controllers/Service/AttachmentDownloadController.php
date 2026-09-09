<?php

namespace App\Http\Controllers\Service;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Support\AttachmentResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Telechargement d'une piece jointe depuis l'interface /service.
 *
 * Route propre au role `doctor` : l'admin dispose de la sienne. Aucune route
 * de telechargement n'est partagee entre deux roles.
 */
class AttachmentDownloadController extends Controller
{
    public function __invoke(Request $request, Attachment $attachment): StreamedResponse
    {
        $user = $request->user();

        // Le medecin doit avoir croise ce patient : soit la piece jointe releve
        // d'un de ses services, soit il a une trace de prise en charge.
        $allowed = $attachment->visit
            && $user->doctors()->pluck('service_id')->contains($attachment->visit->service_id);

        if (! $allowed) {
            $allowed = $attachment->patient
                ->history()
                ->whereIn('doctor_id', $user->doctors()->pluck('id'))
                ->exists();
        }

        abort_unless($allowed, 403, 'Cette piece jointe ne releve pas de vos services.');

        return AttachmentResponse::for($attachment, $request->boolean('telecharger'));
    }
}
