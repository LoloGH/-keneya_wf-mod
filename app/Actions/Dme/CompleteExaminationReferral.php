<?php

namespace App\Actions\Dme;

use App\Actions\CompleteReferral;
use App\Models\Doctor;
use App\Models\Referral;
use App\Models\StaffMember;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Keneya\Dme\Models\ImagingOrder;

/**
 * Le technicien rend son resultat : au dossier, puis au prescripteur (v3.3.1).
 *
 * Trois choses en un geste, parce qu'elles n'ont de sens qu'ensemble :
 *
 *  1. le compte rendu est verse au **dossier medical** du patient, ou il
 *     restera — et non en piece jointe d'un renvoi, qui n'est qu'un
 *     mouvement du parcours ;
 *  2. la demande d'examen passe de « demandee » a rendue, pour qu'elle cesse
 *     d'apparaitre comme en attente dans le dossier ;
 *  3. le renvoi est clos, ce qui **ramene le patient** dans la file du
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

            foreach ($fichiers as $fichier) {
                $this->documents->execute(
                    visit: $visit,
                    doctor: $completedBy,
                    fichier: $fichier,
                    type: $this->typeDeDocument($referral),
                    titre: $titre,
                );
            }

            $this->marqueLaDemandeRendue($referral);

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
     * La demande cesse d'etre en attente.
     *
     * Les deux tables du module n'ont pas le meme vocabulaire de statut : une
     * analyse devient « disponible », une imagerie « compte rendu
     * disponible ». On respecte le leur plutot que d'en imposer un troisieme.
     *
     * Une demande introuvable — dossier purge, module remonte — n'interrompt
     * rien : le patient doit revenir chez son medecin quoi qu'il arrive.
     */
    private function marqueLaDemandeRendue(Referral $referral): void
    {
        if ($demande = $referral->labOrder()->first()) {
            $demande->update(['status' => 'available', 'completed_at' => now()]);

            return;
        }

        if ($demande = $referral->imagingOrder()->first()) {
            $demande->update(['status' => array_key_exists('reported', ImagingOrder::STATUSES) ? 'reported' : 'performed']);
        }
    }
}
