<?php

namespace App\Actions;

use App\Models\Attachment;
use App\Models\Patient;
use App\Models\PatientHistory;
use App\Models\Referral;
use App\Models\User;
use App\Models\Visit;
use App\Support\Audit;
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

    /**
     * Depot depuis le dossier d'un patient, sans renvoi ni entree d'historique
     * en contexte (v3.2, point 3). `patient_id` reste le point d'ancrage ; la
     * visite n'est renseignee que si le patient en a une.
     */
    public function executeForPatient(
        UploadedFile $file,
        Patient $patient,
        User $uploadedBy,
        ?Visit $visit = null,
    ): Attachment {
        $this->assertAcceptable($file);

        $path = $file->store((string) $patient->getKey(), 'attachments');

        if ($path === false) {
            throw new InvalidArgumentException("Le fichier n'a pas pu etre enregistre.");
        }

        $attachment = Attachment::create([
            'patient_id' => $patient->getKey(),
            'visit_id' => $visit?->getKey(),
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize() ?: 0,
            'uploaded_by_user_id' => $uploadedBy->getKey(),
        ]);

        Audit::log(
            Audit::EVENT_ATTACHMENT_ADDED,
            sprintf('Piece jointe « %s » ajoutee au dossier %s.', $attachment->original_name, $patient->patient_code),
            $attachment,
        );

        return $attachment;
    }

    /**
     * L'enregistrement d'abord, le fichier ensuite — jamais l'inverse.
     *
     * Le disque ne sait pas revenir en arriere. Detruire le fichier en premier
     * laisse, si la suppression en base echoue, une piece jointe que le
     * dossier affiche toujours et que le serveur ne peut plus servir. Dans cet
     * ordre-ci, le pire cas est un fichier que plus rien ne designe.
     */
    public function delete(Attachment $attachment): void
    {
        $chemin = $attachment->path;

        $attachment->delete();

        Storage::disk('attachments')->delete($chemin);
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
