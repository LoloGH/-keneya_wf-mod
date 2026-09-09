<?php

namespace App\Actions\Dme;

use App\Actions\SendReferral;
use App\Models\BillableItem;
use App\Models\Doctor;
use App\Models\Referral;
use App\Models\Service;
use App\Models\StaffMember;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Envoyer un patient faire un examen, et dire lequel (v3.3.1).
 *
 * Demander un examen et envoyer le patient le faire etaient deux gestes sans
 * lien : le medecin remplissait « Examen biologique » dans le dossier medical,
 * puis renvoyait le patient vers le plateau technique par un autre ecran. Le
 * technicien recevait donc un patient sans savoir ce qu'on lui demandait, et
 * la demande dormait dans le dossier sans destinataire.
 *
 * Les deux ne font plus qu'un : la demande nait au moment du renvoi et voyage
 * avec le patient. Elle est posee dans le dossier medical, c'est la que vit
 * la donnee de sante, et le renvoi la designe, pour que l'ecran du technicien
 * la lui montre a l'arrivee.
 *
 * Une seule transaction : un renvoi sans sa demande enverrait le patient au
 * laboratoire les mains vides, et une demande sans renvoi resterait sans
 * destinataire. Les deux, ou aucun.
 */
class ReferForExamination
{
    public function __construct(
        private readonly SendReferral $referrals,
        private readonly OrderLaboratory $laboratory,
        private readonly OrderImaging $imaging,
    ) {}

    /**
     * `$examen` porte les champs du formulaire de demande, tels que le
     * plateau destinataire les attend. Nul quand le service ne realise pas
     * d'examen : le renvoi part alors seul, comme avant.
     *
     * @param  array<string, mixed>|null  $examen
     */
    public function execute(
        Visit $visit,
        Doctor|StaffMember $fromDoctor,
        Service $toService,
        string $instructions,
        ?BillableItem $billableItem = null,
        ?array $examen = null,
    ): Referral {
        $nature = $toService->examKind();

        if ($examen !== null && $nature === null) {
            throw new InvalidArgumentException(
                'Ce service ne realise pas d\'examen : la demande ne peut pas lui etre adressee.'
            );
        }

        return DB::transaction(function () use (
            $visit, $fromDoctor, $toService, $instructions, $billableItem, $examen, $nature
        ): Referral {
            // La demande d'abord : le renvoi doit pouvoir la designer, et le
            // dossier medical du patient est cree au passage s'il n'existe pas.
            $demande = match (true) {
                $examen === null => null,
                $nature === Service::EXAM_LABORATORY => $this->laboratory->execute($visit, $fromDoctor, $examen),
                default => $this->imaging->execute($visit, $fromDoctor, $examen),
            };

            $referral = $this->referrals->execute(
                visit: $visit,
                fromDoctor: $fromDoctor,
                toService: $toService,
                instructions: $instructions,
                billableItem: $billableItem,
            );

            if ($demande !== null) {
                $referral->update([
                    $nature === Service::EXAM_LABORATORY
                        ? 'dme_lab_order_id'
                        : 'dme_imaging_order_id' => $demande->getKey(),
                ]);
            }

            return $referral;
        });
    }
}
