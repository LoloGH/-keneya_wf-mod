<?php

namespace App\Actions;

use App\Models\Attachment;
use App\Models\PatientHistory;
use App\Models\Referral;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Depot d'une piece jointe (image d'echographie, PDF de laboratoire).
 *
 * Les controles sont refaits ici, cote serveur, meme si le formulaire les
 * applique deja : un formulaire se contourne, pas une action.
 *
 * Le fichier atterrit sur le disque `attachments`, qui pointe vers
 * storage/app/attachments — donc sur le volume Docker `keneya_storage`,
 * persistant entre deux redeploiements. Jamais de stockage cloud : la
 * connectivite du site ne le permet pas.
 */
class StoreAttachment
{
    public function execute(
        UploadedFile $file,
        Visit $visit,
        User $uploadedBy,
        ?Referral $referral = null,
        ?PatientHistory $historyEntry = null,
    ): Attachment {
        $this->assertAcceptable($file);

        $path = $file->store((string) $visit->patient_id, 'attachments');

        if ($path === false) {
            throw new InvalidArgumentException("Le fichier n'a pas pu etre enregistre.");
        }

        return Attachment::create([
            'patient_id' => $visit->patient_id,
            'visit_id' => $visit->getKey(),
            'patient_history_id' => $historyEntry?->getKey(),
            'referral_id' => $referral?->getKey(),
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize() ?: 0,
            'uploaded_by_user_id' => $uploadedBy->getKey(),
        ]);
    }

    public function delete(Attachment $attachment): void
    {
        Storage::disk('attachments')->delete($attachment->path);

        $attachment->delete();
    }

    /**
     * Type et taille sont verifies sur le fichier reellement recu — le nom
     * d'origine et l'extension annoncee ne prouvent rien.
     */
    private function assertAcceptable(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new InvalidArgumentException('Le televersement a echoue.');
        }

        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, Attachment::ALLOWED_EXTENSIONS, true)) {
            throw new InvalidArgumentException('Formats acceptes : PDF, JPG, PNG.');
        }

        if (! in_array((string) $file->getMimeType(), Attachment::ALLOWED_MIME_TYPES, true)) {
            throw new InvalidArgumentException('Le contenu du fichier ne correspond pas a un PDF, JPG ou PNG.');
        }

        if ($file->getSize() > Attachment::MAX_SIZE_KB * 1024) {
            throw new InvalidArgumentException('Le fichier depasse la taille maximale de 10 Mo.');
        }
    }
}
