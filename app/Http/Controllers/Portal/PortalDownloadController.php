<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
        abort_unless(Storage::disk('attachments')->exists($attachment->path), 404);

        return Storage::disk('attachments')->download($attachment->path, $attachment->original_name);
    }
}
