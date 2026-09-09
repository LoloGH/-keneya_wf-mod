<?php

namespace App\Actions\Dme;

use App\Actions\CompleteReferral;
use App\Models\Doctor;
use App\Models\Referral;
use App\Models\StaffMember;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Keneya\Dme\Models\ImagingOrder;

/**
 * Le technicien rend son resultat : au dossier, puis au prescripteur (v3.3.1).
 *
 * Quatre choses en un geste, parce qu'elles n'ont de sens qu'ensemble :
 *
 *  1. la conclusion est ecrite **sur la demande elle-meme**, la ou le medecin
 *     ira la relire : le compte rendu d'imagerie pour une echographie, la
 *     conclusion du biologiste pour des analyses. Sans cela, la fiche de
 *     l'examen resterait « compte rendu non redige » alors que le resultat
 *     existe ;
 *  2. les fichiers sont verses au **dossier medical**, rattaches a la demande
 *     qu'ils documentent — sinon la fiche de l'examen les ignore ;
 *  3. la demande passe de « demandee » a rendue, pour qu'elle cesse
 *     d'apparaitre comme en attente dans le dossier ;
 *  4. le renvoi est clos, ce qui **ramene le patient** dans la file du
 *     medecin qui l'a envoye ({@see CompleteReferral}).
 *
 * Une seule transaction : un resultat verse au dossier sans que le patient
 * revienne le laisserait indefiniment dans la file du plateau technique, et un
 * retour sans resultat au dossier perdrait le compte rendu.
 */
class CompleteExaminationReferral
{
    public function __construct(
        private readonly CompleteReferral $referrals,
        private readonly StoreMedicalDocument $documents,
    ) {}

    /**
     * `$fichiers` porte les comptes rendus a verser au dossier. Facultatifs :
     * un resultat peut tenir dans le texte, et une machine en panne ne doit
     * pas empecher de rendre une conclusion.
     *
     * @param  array<int, UploadedFile>  $fichiers
     */
    public function execute(
        Referral $referral,
        Doctor|StaffMember $completedBy,
        string $resultText,
        array $fichiers = [],
        ?string $titre = null,
    ): Referral {
        return DB::transaction(function () use ($referral, $completedBy, $resultText, $fichiers, $titre): Referral {
            $visit = $referral->visit()->firstOrFail();
            $demande = $this->rendLaDemande($referral, $completedBy, $resultText);

            foreach ($fichiers as $fichier) {
                $this->documents->execute(
                    visit: $visit,
                    doctor: $completedBy,
                    fichier: $fichier,
                    type: $this->typeDeDocument($referral),
                    titre: $titre,
                    source: $demande,
                );
            }

            // En dernier : c'est lui qui deplace le patient, et il ne doit
            // partir que si tout le reste a tenu.
            return $this->referrals->execute($referral, $completedBy, $resultText);
        });
    }

    /**
     * Le compte rendu prend la nature de la demande : un resultat de
     * laboratoire ne se classe pas avec une echographie. Sans demande
     * attachee — un renvoi ordinaire — le document est simplement importe.
     */
    private function typeDeDocument(Referral $referral): string
    {
        return match (true) {
            $referral->dme_lab_order_id !== null => 'lab_result',
            $referral->dme_imaging_order_id !== null => 'imaging_report',
            default => 'imported',
        };
    }

    /**
     * Ecrit la conclusion sur la demande et la marque rendue.
     *
     * Les deux tables du module n'ont ni le meme vocabulaire de statut ni la
     * meme forme de compte rendu : une analyse devient « disponible » et porte
     * sa conclusion sur la demande, une imagerie devient « compte rendu
     * disponible » et la porte dans une table dediee, avec sa technique et son
     * statut de redaction propres. On respecte leur vocabulaire plutot que
     * d'en imposer un troisieme.
     *
     * Une demande introuvable — dossier purge, module remonte — n'interrompt
     * rien : le patient doit revenir chez son medecin quoi qu'il arrive.
     */
    private function rendLaDemande(
        Referral $referral,
        Doctor|StaffMember $completedBy,
        string $resultText,
    ): ?Model {
        if ($demande = $referral->labOrder()->first()) {
            $demande->update([
                'conclusion' => $resultText,
                'status' => 'available',
                'completed_at' => now(),
            ]);

            return $demande;
        }

        if ($demande = $referral->imagingOrder()->first()) {
            $this->ecritLeCompteRendu($demande, $completedBy, $resultText);
            $demande->update(['status' => 'reported']);

            return $demande;
        }

        return null;
    }

    /**
     * Le compte rendu d'imagerie, cree ou repris.
     *
     * `findings` et `conclusion` recoivent le meme texte : le technicien saisit
     * une conclusion unique depuis WorkFlow, et le gabarit du module n'affiche
     * que les champs remplis. Les distinguer demanderait deux zones de saisie
     * la ou une suffit — le radiologue qui veut detailler dispose du formulaire
     * complet dans le module.
     */
    private function ecritLeCompteRendu(
        ImagingOrder $demande,
        Doctor|StaffMember $completedBy,
        string $resultText,
    ): void {
        $demande->report()->updateOrCreate([], [
            'patient_id' => $demande->patient_id,
            'radiologist_id' => $completedBy->user_id,
            'findings' => $resultText,
            'conclusion' => $resultText,
            'reported_at' => now(),
            'status' => 'final',
        ]);
    }
}
