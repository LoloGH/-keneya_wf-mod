<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Telechargement d'une piece jointe depuis /admin.
 *
 * L'admin a acces a tout dossier ; la route reste distincte de celle du
 * medecin pour ne jamais partager une URL entre deux roles.
 */
class AttachmentDownloadController extends Controller
{
    public function __invoke(Attachment $attachment): StreamedResponse
    {
        abort_unless(Storage::disk('attachments')->exists($attachment->path), 404);

        return Storage::disk('attachments')->download($attachment->path, $attachment->original_name);
    }
}
