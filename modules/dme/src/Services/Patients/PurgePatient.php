<?php

declare(strict_types=1);

namespace Keneya\Dme\Services\Patients;

use Keneya\Dme\Models\AuditLog;
use Keneya\Dme\Models\Patient;
use Keneya\Dme\Models\SmsMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Suppression définitive d'un dossier médical, contenu clinique compris (§40).
 *
 * Ce geste se déclenche depuis deux endroits, et c'est la raison d'être de
 * cette classe : l'écran « Supprimer définitivement » du module, et la
 * suppression d'un dossier patient dans l'application hôte, qui doit emporter
 * le dossier médical correspondant. Recopiée des deux côtés, la séquence
 * aurait divergé — et l'écart se serait vu sous la pire forme possible, des
 * données de santé restées en base après qu'un administrateur a lu
 * « dossier supprimé ».
 *
 * La séquence, et l'ordre compte à chaque étape :
 *
 *   1. journaliser AVANT, parce qu'après il ne reste plus rien à désigner.
 *      Le journal ne porte aucune clé étrangère vers le patient, il lui
 *      survit donc ;
 *   2. relever les chemins des documents pendant qu'ils sont encore lisibles ;
 *   3. effacer, dans une transaction, ce que la cascade de la base n'emporte
 *      pas — SMS et notifications ne portent qu'un index — puis le dossier
 *      lui-même, dont la cascade emporte tout le reste ;
 *   4. seulement après la transaction, effacer les fichiers. Le disque ne sait
 *      pas revenir en arrière : supprimés à l'intérieur, ils seraient détruits
 *      même quand la transaction échoue ensuite, et l'administrateur verrait
 *      une erreur en concluant que rien n'a bougé.
 *
 * Cette classe ne décide de rien : ni permission, ni confirmation, ni passage
 * préalable par l'archive. Ces garde-fous appartiennent à l'écran qui appelle,
 * et ils ne sont pas les mêmes des deux côtés — l'hôte fait retaper son propre
 * numéro de dossier, le module le sien.
 */
class PurgePatient
{
    /**
     * @param  string  $reason  Motif saisi par l'administrateur ; obligatoire.
     * @param  string|null  $origin  D'où vient le geste, quand ce n'est pas de
     *                               l'écran du module (« suppression du dossier
     *                               WorkFlow HFD-00001 par Awa Diarra »).
     * @param  array<string, mixed>  $properties  Complément journalisé.
     */
    public function purge(
        Patient $patient,
        string $reason,
        ?string $origin = null,
        array $properties = [],
    ): void {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException(
                'Un motif est obligatoire pour supprimer définitivement un dossier médical.'
            );
        }

        $numero = (string) $patient->patient_number;

        AuditLog::record(
            action: 'purged',
            patientId: $patient->getKey(),
            description: $this->description($patient, $reason, $origin),
            properties: array_merge([
                'patient_number' => $numero,
                'nom' => $patient->fullName(),
                'motif' => $reason,
                'origine' => $origin,
                'supprime_le' => now()->toDateTimeString(),
            ], $properties),
        );

        // Deux colonnes, et non `pluck('storage_path', 'disk')` : la clé d'un
        // pluck est unique, et tous les documents d'un dossier vivent sur le
        // même disque. Un dossier de dix documents n'en voyait donc qu'un seul
        // partir du disque — les neuf autres restaient, lisibles pour qui a
        // accès au volume, après la disparition de la ligne qui les désignait.
        $fichiers = $patient->documents()
            ->get(['disk', 'storage_path'])
            ->map(fn ($document) => [
                'disk' => $document->disk ?: (string) config('dme.documents.disk', 'local'),
                'path' => (string) $document->storage_path,
            ])
            ->filter(fn (array $fichier) => $fichier['path'] !== '')
            ->all();

        DB::transaction(function () use ($patient): void {
            // Ni SMS ni notifications ne portent de clé étrangère vers le
            // patient, seulement un index. La cascade ne les emporte donc
            // pas, et ils resteraient à désigner un dossier disparu.
            SmsMessage::where('patient_id', $patient->getKey())->delete();
            DB::table('dme_notifications')->where('patient_id', $patient->getKey())->delete();

            // `forceDelete()` et non `delete()` : le dossier est en suppression
            // douce, et un dossier simplement marqué supprimé garde tout son
            // contenu clinique en base.
            $patient->forceDelete();
        });

        // La base a tenu : les fichiers peuvent partir. Si cet effacement
        // échoue, il reste des fichiers que plus aucune ligne ne désigne :
        // inertes, car tout accès passe par l'enregistrement. C'est le seul
        // des deux échecs possibles qui ne détruit rien.
        foreach ($fichiers as $fichier) {
            Storage::disk($fichier['disk'])->delete($fichier['path']);
        }
    }

    private function description(Patient $patient, string $reason, ?string $origin): string
    {
        $description = sprintf(
            'Dossier %s (%s) supprimé définitivement. Motif : %s',
            $patient->patient_number,
            $patient->fullName(),
            $reason,
        );

        return $origin === null ? $description : $description.' Origine : '.$origin;
    }
}
