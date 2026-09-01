<?php

namespace App\Actions;

use App\Models\Doctor;
use App\Models\PatientHistory;
use App\Models\StaffMember;
use App\Models\Visit;
use App\Services\PatientHistoryRecorder;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Cloture de l'episode de soins (addendum v2, point 2, corrige par v3).
 *
 * Distinct de la cloture d'un renvoi : ici c'est le passage entier du patient
 * qui se termine. La visite sort de la file active, mais son dossier reste
 * integralement consultable — le statut ne filtre jamais la lecture.
 */
class CloseVisit
{
    public function __construct(
        private readonly PatientHistoryRecorder $history,
        private readonly SendFeedbackInvitation $invitation,
    ) {}

    public function execute(Visit $visit, Doctor|StaffMember $doctor): Visit
    {
        if ($visit->isClosed()) {
            throw new InvalidArgumentException('Ce dossier est deja cloture.');
        }

        if ($visit->status !== Visit::STATUS_CALLED) {
            throw new InvalidArgumentException('Le patient doit avoir ete appele avant que son dossier puisse etre cloture.');
        }

        // On ne ferme pas un dossier qui attend encore un resultat d'examen.
        if ($visit->hasPendingReferral()) {
            throw new InvalidArgumentException("Ce dossier attend le resultat d'un renvoi : traitez-le avant de cloturer.");
        }

        $visit = DB::transaction(function () use ($visit, $doctor): Visit {
            $visit->update([
                'status' => Visit::STATUS_CLOSED,
                'closed_at' => now(),
            ]);

            $visit->load('service');

            $this->history->record(
                visit: $visit,
                type: PatientHistory::TYPE_DOSSIER_CLOSED,
                description: sprintf(
                    'Dossier cloture au service %s par %s.',
                    $visit->service->name,
                    $doctor->name(),
                ),
                doctor: $doctor,
            );

            return $visit;
        });

        // Journalise seulement une fois la transaction validee.
        Audit::log(
            Audit::EVENT_VISIT_CLOSED,
            sprintf('Dossier de la visite #%d cloture par %s.', $visit->getKey(), $doctor->name()),
            $visit,
        );

        // L'invitation a donner son avis part maintenant, et non plus sur
        // demande du personnel (v3.2.8, point 4) : un sondage qu'il faut penser
        // a envoyer n'est jamais envoye. Le SMS passe par la file, la cloture
        // ne l'attend donc pas.
        $this->invitation->toPatient($visit->patient()->firstOrFail());

        return $visit;
    }
}
