<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Referral;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\StaffNotification;
use App\Models\StaffType;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Fabrique des notifications (v3.2.3, point 2).
 *
 * Un seul point d'ecriture, comme PatientHistoryRecorder pour le dossier : les
 * declencheurs disent ce qui vient d'arriver, c'est ici qu'on decide qui est
 * concerne — et le ciblage passe toujours par OnDutyRoster, jamais par une
 * requete ecrite sur place.
 *
 * Le titre est redige ici, en francais, une fois pour toutes : la cloche
 * n'aura rien a recomposer a chaque affichage.
 */
class StaffNotifier
{
    public function __construct(private readonly OnDutyRoster $roster) {}

    /**
     * Un patient ou un visiteur vient d'entrer dans une file.
     *
     * Personne n'est prevenu si personne n'est de garde : une notification que
     * nul ne lira n'a pas a exister.
     */
    public function queueEntry(?int $serviceId, string $qui): int
    {
        $service = $serviceId ? Service::find($serviceId) : null;

        return $this->push(
            $this->roster->for($serviceId),
            StaffNotification::TYPE_NEW_QUEUE_ENTRY,
            sprintf('%s entre dans la file de %s.', $qui, $service?->name ?? 'votre service'),
        );
    }

    /**
     * Le resultat d'un renvoi est revenu : seul le prescripteur est prevenu,
     * pas tout le service. C'est lui qui attend la reponse.
     */
    public function referralResult(Referral $referral): int
    {
        $prescripteur = Doctor::with('user')->find($referral->from_doctor_id)?->user;

        if (! $prescripteur) {
            return 0;
        }

        return $this->push(
            collect([$prescripteur]),
            StaffNotification::TYPE_REFERRAL_RESULT,
            sprintf(
                'Resultat recu pour %s (%s).',
                $referral->visit?->patient?->name ?? 'un patient',
                $referral->toService()->first()?->name ?? 'renvoi',
            ),
        );
    }

    /**
     * Des soins viennent d'etre prescrits : le personnel de garde du service
     * qui porte la capacite « soins », et lui seul.
     */
    public function careTasksPrescribed(int $serviceId, string $patient, int $combien): int
    {
        return $this->push(
            $this->roster->for($serviceId, StaffType::CAP_CARE_TASKS),
            StaffNotification::TYPE_CARE_TASK_ASSIGNED,
            sprintf('%d soin(s) programme(s) pour %s.', $combien, $patient),
        );
    }

    /** Un rendez-vous approche : le medecin concerne, nommement. */
    public function appointmentReminder(Appointment $appointment): int
    {
        $medecin = Doctor::with('user')->find($appointment->doctor_id)?->user;

        if (! $medecin) {
            return 0;
        }

        return $this->push(
            collect([$medecin]),
            StaffNotification::TYPE_APPOINTMENT_REMINDER,
            sprintf(
                'Rendez-vous a %s : %s.',
                $appointment->scheduled_at->format('H:i'),
                $appointment->patient?->name ?? 'patient',
            ),
        );
    }

    /**
     * Un planning vient d'etre publie : les personnes concernees par les
     * lignes creees, qu'elles soient de garde ou non — c'est justement leurs
     * heures a venir qu'on leur annonce.
     *
     * @param  Collection<int, Schedule>|array<int, Schedule>  $schedules
     */
    public function schedulePublished(Collection|array $schedules): int
    {
        $schedules = collect($schedules);

        if ($schedules->isEmpty()) {
            return 0;
        }

        $envoyees = 0;

        foreach ($schedules->groupBy('user_id') as $userId => $lignes) {
            $user = User::find($userId);

            if (! $user) {
                continue;
            }

            $premiere = $lignes->sortBy('date')->first();

            $envoyees += $this->push(
                collect([$user]),
                StaffNotification::TYPE_SCHEDULE_PUBLISHED,
                sprintf(
                    '%d creneau(x) ajoute(s) a votre planning, a partir du %s.',
                    $lignes->count(),
                    $premiere->date->format('d/m/Y'),
                ),
            );
        }

        return $envoyees;
    }

    /**
     * @param  Collection<int, User>  $destinataires
     * @return int nombre de notifications ecrites
     */
    private function push(Collection $destinataires, string $type, string $title): int
    {
        $lignes = [];

        foreach ($destinataires as $user) {
            $lignes[] = [
                'user_id' => $user->getKey(),
                'type' => $type,
                'title' => $title,
                // Le lien mene a l'interface du destinataire : la navigation
                // interne est en onglets, sans URL propre par section — mieux
                // vaut un lien juste qu'un lien precis mais faux.
                'link' => $user->homeUrl(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($lignes === []) {
            return 0;
        }

        StaffNotification::insert($lignes);

        return count($lignes);
    }
}
