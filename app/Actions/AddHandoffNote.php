<?php

namespace App\Actions;

use App\Models\HandoffNote;
use App\Models\Hospitalization;
use App\Models\PatientHistory;
use App\Models\User;
use App\Services\PatientHistoryRecorder;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Ecriture d'une note de releve (v3.2.3, point 4).
 *
 * Meme regle d'acces que les soins : le personnel de garde sur le service, quel
 * que soit son type. Ni le medecin admettant ni un infirmier nommement designe
 * n'ont de privilege ici : c'est precisement l'equipe qui change qui a besoin
 * d'ecrire et de lire ces notes.
 */
class AddHandoffNote
{
    public function __construct(private readonly PatientHistoryRecorder $history) {}

    public function execute(Hospitalization $hospitalization, User $user, string $content): HandoffNote
    {
        $content = trim($content);

        if ($content === '') {
            throw new InvalidArgumentException('Une note de releve ne peut pas etre vide.');
        }

        if (! $hospitalization->isActive()) {
            throw new InvalidArgumentException('Cette hospitalisation est cloturee : on n\'y ajoute plus de note.');
        }

        if (! $user->isOnDutyFor($hospitalization->service_id)) {
            throw new InvalidArgumentException("Vous n'etes pas de garde sur ce service en ce moment.");
        }

        $note = DB::transaction(function () use ($hospitalization, $user, $content): HandoffNote {
            $note = HandoffNote::create([
                'hospitalization_id' => $hospitalization->getKey(),
                'written_by_user_id' => $user->getKey(),
                'content' => $content,
            ]);

            // La note rejoint la frise du dossier : elle fait partie du
            // parcours du patient, pas d'un carnet parallele.
            if ($visit = $hospitalization->visit()->first()) {
                $this->history->record(
                    visit: $visit,
                    type: PatientHistory::TYPE_HANDOFF_NOTE,
                    description: sprintf('Note de releve de %s : %s', $user->name, $content),
                    serviceId: $hospitalization->service_id,
                    doctor: $user->doctorFor($hospitalization->service_id) ?? $user->staffMember,
                );
            }

            return $note;
        });

        Audit::log(
            Audit::EVENT_HANDOFF_NOTE,
            sprintf(
                'Note de releve laissee par %s pour %s.',
                $user->name,
                $hospitalization->patient()->first()?->patient_code ?? 'un patient',
            ),
            $note,
        );

        return $note;
    }
}
