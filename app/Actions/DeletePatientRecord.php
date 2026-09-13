<?php

namespace App\Actions;

use App\Models\CareTask;
use App\Models\FeedbackEntry;
use App\Models\HandoffNote;
use App\Models\Hospitalization;
use App\Models\Patient;
use App\Models\User;
use App\Support\Audit;
use App\Support\Dme\PatientProjection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Keneya\Dme\Services\Patients\PurgePatient;

/**
 * Suppression definitive d'un dossier patient (v3.2, point 8).
 *
 * Operation sensible, donc encadree : l'admin doit retaper le `patient_code`
 * exact et fournir un motif. La suppression est reelle et en cascade.
 *
 * Elle n'est jamais tracee dans `patient_history` : cette table disparait avec
 * le patient. Seul le journal d'audit la conserve, et lui survit puisqu'il ne
 * reference pas le patient par cle etrangere.
 *
 * Elle emporte aussi le dossier medical du module. Elle ne le faisait pas :
 * `dme_patients` gardait consultations, ordonnances, analyses, imagerie et
 * documents sur disque, rattaches a un identifiant externe qui ne designait
 * plus rien. L'administrateur retapait le numero de dossier, fournissait un
 * motif, lisait « dossier supprime » — et les donnees de sante restaient en
 * base, sans plus aucun ecran pour les montrer ni personne pour le savoir.
 */
class DeletePatientRecord
{
    public function __construct(private readonly PurgePatient $purge) {}

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

        // Releve maintenant, tant que le patient existe encore : c'est la
        // table d'identifiants externes du module qui fait la correspondance,
        // et elle part avec le dossier medical. Nul quand aucun acte clinique
        // n'a ete pose — le dossier medical nait au premier formulaire du DME,
        // jamais a l'enregistrement a l'accueil.
        $dossierMedical = PatientProjection::find($patient);

        // Les chemins sont releves maintenant, mais les fichiers ne partiront
        // qu'apres la transaction : le disque, lui, ne sait pas revenir en
        // arriere. Supprimes a l'interieur, ils etaient detruits meme quand la
        // transaction echouait ensuite : l'admin voyait une erreur, croyait
        // que rien n'avait bouge, et le dossier avait perdu ses documents.
        $fichiers = $patient->attachments->pluck('path')->all();

        DB::transaction(function () use ($patient): void {
            // Ordre impose par les cles etrangeres, et il n'a rien d'un choix
            // de style : toutes ces contraintes sont en RESTRICT, la base
            // refuse donc de supprimer une ligne encore designee. Un seul
            // deplacement dans cette liste, et la suppression echoue : sur
            // certains dossiers seulement, ce qui est le pire des cas.
            $patient->attachments()->delete();
            $patient->prescriptions()->delete();
            $patient->payments()->delete();
            $patient->appointments()->delete();
            $patient->companions()->delete();
            $patient->portalAccessAttempt()->delete();

            // Hospitalisations, et ce qui s'y accroche : les soins programmes
            // et les notes de releve designent le sejour, le sejour designe le
            // passage. Sans cette descente, un patient ayant ete hospitalise
            // ne pouvait pas etre supprime du tout.
            $sejours = Hospitalization::where('patient_id', $patient->getKey())->pluck('id');

            if ($sejours->isNotEmpty()) {
                HandoffNote::whereIn('hospitalization_id', $sejours)->delete();
                CareTask::whereIn('hospitalization_id', $sejours)->delete();
                Hospitalization::whereIn('id', $sejours)->delete();
            }

            // Ni un visiteur ni un retour ne sont des donnees du dossier : on
            // les detache plutot que de les detruire. Un retour porte sur un
            // service et sur un moment, il garde son sens, et sa place dans
            // les statistiques, une fois la personne effacee.
            $patient->visitors()->update(['patient_id' => null]);
            FeedbackEntry::where('patient_id', $patient->getKey())->update(['patient_id' => null]);

            // `patient_history` est append-only et son observer refuse toute
            // suppression : c'est ce qui garantit qu'on ne reecrit pas un
            // parcours. La suppression complete d'un dossier est la seule
            // exception prevue, et elle passe donc sous le modele, en SQL
            // direct, plutot que d'affaiblir le garde-fou pour tout le monde.
            //
            // Elle part AVANT les renvois et les passages, parce qu'elle
            // designe les deux. Elle partait apres, et tout dossier portant un
            // renvoi trace, c'est-a-dire tout dossier reellement utilise,
            // refusait d'etre supprime.
            DB::table('patient_history')->where('patient_id', $patient->getKey())->delete();

            $patient->referrals()->delete();
            $patient->visits()->delete();

            $patient->delete();
        });

        // La base a tenu : les fichiers peuvent partir. Si cette ligne echoue,
        // il reste des fichiers que plus aucune ligne ne designe : inertes,
        // car tout acces passe par l'enregistrement. C'est le seul des deux
        // echecs possibles qui ne detruit rien.
        Storage::disk('attachments')->delete($fichiers);

        // Le dossier medical en dernier, et non dans la transaction ci-dessus.
        //
        // Les deux vivent pourtant dans la meme base, et une transaction
        // unique serait plus propre sur le papier. Mais la sequence du module
        // efface des fichiers apres sa propre transaction ; imbriquee, cette
        // transaction n'est plus qu'un point de sauvegarde de la notre, et les
        // documents du dossier medical partiraient du disque alors que la
        // transaction englobante peut encore echouer. C'est exactement le
        // piege que les deux cotes evitent chacun chez eux.
        //
        // Reste a choisir l'ordre, et les deux echecs possibles ne se valent
        // pas. Le dossier medical efface en premier, une panne sur la cascade
        // WorkFlow laisserait un patient bien vivant prive de tout son contenu
        // clinique : une perte seche, sans retour. Efface en dernier, une
        // panne laisse un dossier medical orphelin — des donnees qui auraient
        // du partir, mais qui sont toujours la, reperables par leur
        // identifiant externe et emportees par la commande de reprise
        // `keneya:dossiers-medicaux-orphelins`. On garde le defaut qui se
        // repare.
        if ($dossierMedical !== null) {
            $this->purge->purge(
                patient: $dossierMedical,
                reason: trim($reason),
                origin: sprintf(
                    'suppression du dossier WorkFlow %s par %s',
                    $patient->patient_code,
                    $admin->name,
                ),
                properties: [
                    'patient_code_workflow' => $patient->patient_code,
                    'supprime_par' => $admin->name,
                ],
            );
        }
    }
}
