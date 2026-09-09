<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Support\AttachmentResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Telechargement d'une piece jointe depuis /admin.
 *
 * L'admin a acces a tout dossier ; la route reste distincte de celle du
 * medecin pour ne jamais partager une URL entre deux roles.
 */
class AttachmentDownloadController extends Controller
{
    public function __invoke(Request $request, Attachment $attachment): StreamedResponse
    {
        return AttachmentResponse::for($attachment, $request->boolean('telecharger'));
    }
}
