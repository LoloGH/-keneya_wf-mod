<?php

namespace App\Actions\Dme;

use App\Actions\Dme\Concerns\ResolvesMedicalRecord;
use App\Models\Doctor;
use App\Models\Hospitalization;
use App\Models\StaffMember;
use Keneya\Dme\Models\Hospitalization as HospitalisationDme;

/**
 * L'hospitalisation ouverte dans WorkFlow rejoint le dossier medical (v3.3.2).
 *
 * C'est l'oubli le plus lourd des trois : un patient pouvait occuper un lit
 * depuis huit jours sans que son dossier medical le mentionne. Un soignant qui
 * ouvrait le dossier voyait un patient de passage, et l'onglet
 * « Hospitalisations » restait vide.
 *
 * La ligne se cree a l'admission et se cloture a la sortie. Elle n'est jamais
 * recreee : c'est tout l'objet de `hospitalizations.dme_hospitalization_id`,
 * sans quoi une sortie ouvrirait un second sejour au dossier.
 *
 * Ce qui n'est pas projete l'est faute de source, non par choix : WorkFlow ne
 * demande ni motif d'admission, ni diagnostic, ni compte rendu de sortie. Ces
 * champs existent au dossier et s'y remplissent depuis le DME. Mieux vaut les
 * laisser vides que d'y verser un texte fabrique a partir d'un nom de service.
 */
class RecordHospitalization
{
    use ResolvesMedicalRecord;

    public function execute(Hospitalization $hospitalization, Doctor|StaffMember $doctor): ?HospitalisationDme
    {
        $patient = $hospitalization->patient;

        if (! $patient) {
            return null;
        }

        $dossier = $this->dossierDuPatient($patient);

        $sejour = HospitalisationDme::create([
            'patient_id' => $dossier->getKey(),
            'service_id' => $this->serviceDme($hospitalization->service?->name),
            'doctor_id' => $doctor->user_id,
            'admitted_at' => $hospitalization->admitted_at,
            'room' => $hospitalization->room?->name,
            'status' => 'admitted',
        ]);

        $hospitalization->forceFill(['dme_hospitalization_id' => $sejour->getKey()])->save();

        return $sejour;
    }

    /**
     * La sortie prononcee dans WorkFlow ferme le sejour au dossier.
     *
     * Le type de sortie reste vide : WorkFlow ne distingue pas un retour a
     * domicile d'un transfert, et choisir « domicile » par defaut ecrirait au
     * dossier une information que personne n'a donnee.
     */
    public function close(Hospitalization $hospitalization): void
    {
        if (! $hospitalization->dme_hospitalization_id) {
            return;
        }

        HospitalisationDme::whereKey($hospitalization->dme_hospitalization_id)->update([
            'status' => 'discharged',
            'discharged_at' => $hospitalization->discharged_at ?? now(),
        ]);
    }
}
