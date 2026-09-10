<?php

namespace App\Actions\Dme;

use App\Actions\Dme\Concerns\ResolvesMedicalRecord;
use App\Models\Appointment;
use Keneya\Dme\Models\Appointment as RendezVousDme;

/**
 * Le rendez-vous fixe dans WorkFlow rejoint le dossier medical (v3.3.2).
 *
 * Il etait pris en fin de consultation, inscrit dans `appointments`, affiche a
 * l'accueil — et le dossier du patient n'en savait rien. L'onglet
 * « Rendez-vous » du DME restait vide quoi qu'on fasse, alors que c'est
 * precisement la qu'un soignant va chercher la suite du parcours.
 *
 * Ce que WorkFlow tient, et que le dossier ne tenait pas : le patient revient,
 * quand, et aupres de qui.
 *
 * Le statut suit ensuite les gestes de l'accueil. Les deux vocabulaires ne se
 * recouvrent pas exactement — WorkFlow dit « patient arrive », le DME dit
 * « confirme » — et c'est le plus proche qui est retenu plutot qu'un statut
 * invente : la table du module contraint ses valeurs.
 */
class RecordAppointment
{
    use ResolvesMedicalRecord;

    /**
     * @var array<string, string>
     */
    private const STATUTS = [
        Appointment::STATUS_SCHEDULED => 'scheduled',
        // « Patient arrive » n'existe pas au dossier : sa confirmation est ce
        // qui s'en approche, et elle dit la meme chose, le rendez-vous tient.
        Appointment::STATUS_CHECKED_IN => 'confirmed',
        Appointment::STATUS_NO_SHOW => 'no_show',
        Appointment::STATUS_CANCELLED => 'cancelled',
    ];

    public function execute(Appointment $appointment): ?RendezVousDme
    {
        $patient = $appointment->patient;

        if (! $patient) {
            return null;
        }

        $dossier = $this->dossierDuPatient($patient);
        $auteur = $appointment->doctor ?? $appointment->staffMember;

        $rendezVous = RendezVousDme::create([
            'patient_id' => $dossier->getKey(),
            // Le compte WorkFlow, pas la fiche medecin : `users` est la table
            // partagee, et c'est elle que le DME reference.
            'doctor_id' => $auteur?->user_id,
            'service_id' => $this->serviceDme($appointment->service?->name),
            'scheduled_for' => $appointment->scheduled_at,
            'reason' => 'Suite de consultation',
            'status' => self::STATUTS[$appointment->status] ?? 'scheduled',
            'created_by' => $auteur?->user_id,
        ]);

        $appointment->forceFill(['dme_appointment_id' => $rendezVous->getKey()])->save();

        return $rendezVous;
    }

    /**
     * Reporte au dossier le changement de statut decide a l'accueil.
     *
     * Sans reprojection si la ligne manque : un rendez-vous anterieur a cette
     * version n'a pas de correspondance au dossier, et en fabriquer une au
     * moment ou il est annule inscrirait au dossier un rendez-vous qui n'a
     * jamais eu lieu.
     */
    public function syncStatus(Appointment $appointment): void
    {
        if (! $appointment->dme_appointment_id) {
            return;
        }

        RendezVousDme::whereKey($appointment->dme_appointment_id)->update([
            'status' => self::STATUTS[$appointment->status] ?? 'scheduled',
        ]);
    }
}
