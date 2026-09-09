<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Patient;
use App\Support\AttachmentResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Telechargement d'une piece jointe depuis le portail patient.
 *
 * Deux conditions : la piece doit appartenir au patient designe par le jeton,
 * et le code a quatre chiffres doit avoir ete valide dans cette session.
 */
class PortalDownloadController extends Controller
{
    public function __invoke(Request $request, string $token, Attachment $attachment): StreamedResponse
    {
        $patient = Patient::where('portal_token', $token)->firstOrFail();

        abort_unless($request->session()->get('portal.'.$patient->getKey()) === true, 403,
            "Saisissez d'abord votre code d'acces.");

        abort_unless((int) $attachment->patient_id === (int) $patient->getKey(), 404);

        return AttachmentResponse::for($attachment, $request->boolean('telecharger'));
    }
}
