<?php

namespace App\Actions;

use App\Models\Patient;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Suppression definitive d'un dossier patient (v3.2, point 8).
 *
 * Operation sensible, donc encadree : l'admin doit retaper le `patient_code`
 * exact et fournir un motif. La suppression est reelle et en cascade.
 *
 * Elle n'est jamais tracee dans `patient_history` — cette table disparait avec
 * le patient. Seul le journal d'audit la conserve, et lui survit puisqu'il ne
 * reference pas le patient par cle etrangere.
 */
class DeletePatientRecord
{
    public function execute(Patient $patient, User $admin, string $confirmation, string $reason): void
    {
        if ($confirmation !== $patient->patient_code) {
            throw new InvalidArgumentException('Le numero de dossier saisi ne correspond pas.');
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('Un motif est obligatoire pour supprimer un dossier.');
        }

        // Journalise AVANT de supprimer : apres, il ne resterait plus rien a
        // designer. L'entree d'audit n'a pas de cle etrangere vers le patient,
        // elle survit donc a la suppression.
        Audit::log(
            Audit::EVENT_PATIENT_DELETED,
            sprintf(
                'Dossier %s (%s) supprime definitivement par %s. Motif : %s',
                $patient->patient_code,
                $patient->name,
                $admin->name,
                trim($reason),
            ),
            null,
            [
                'patient_code' => $patient->patient_code,
                'nom' => $patient->name,
                'motif' => trim($reason),
                'supprime_par' => $admin->name,
                'supprime_le' => now()->toDateTimeString(),
            ],
        );

        // Les chemins sont releves maintenant, mais les fichiers ne partiront
        // qu'apres la transaction : le disque, lui, ne sait pas revenir en
        // arriere. Supprimes a l'interieur, ils etaient detruits meme quand la
        // transaction echouait ensuite — l'admin voyait une erreur, croyait
        // que rien n'avait bouge, et le dossier avait perdu ses documents.
        $fichiers = $patient->attachments->pluck('path')->all();

        DB::transaction(function () use ($patient): void {
            // Ordre impose par les cles etrangeres : les feuilles d'abord.
            $patient->attachments()->delete();
            $patient->prescriptions()->delete();
            $patient->payments()->delete();
            $patient->appointments()->delete();
            $patient->referrals()->delete();
            $patient->companions()->delete();
            $patient->portalAccessAttempt()->delete();

            // Un visiteur n'est pas une donnee du dossier : on le detache
            // plutot que de supprimer sa fiche.
            $patient->visitors()->update(['patient_id' => null]);

            // `patient_history` est append-only et son observer refuse toute
            // suppression — c'est ce qui garantit qu'on ne reecrit pas un
            // parcours. La suppression complete d'un dossier est la seule
            // exception prevue, et elle passe donc sous le modele, en SQL
            // direct, plutot que d'affaiblir le garde-fou pour tout le monde.
            DB::table('patient_history')->where('patient_id', $patient->getKey())->delete();

            $patient->visits()->delete();

            $patient->delete();
        });

        // La base a tenu : les fichiers peuvent partir. Si cette ligne echoue,
        // il reste des fichiers que plus aucune ligne ne designe — inertes,
        // car tout acces passe par l'enregistrement. C'est le seul des deux
        // echecs possibles qui ne detruit rien.
        Storage::disk('attachments')->delete($fichiers);
    }
}
