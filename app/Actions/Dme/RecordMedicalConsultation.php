<?php

namespace App\Actions\Dme;

use App\Actions\Dme\Concerns\ResolvesMedicalRecord;
use App\Models\Doctor;
use App\Models\PatientHistory;
use App\Models\StaffMember;
use App\Models\Visit;
use App\Services\PatientHistoryRecorder;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Keneya\Dme\Models\ClinicalNote;
use Keneya\Dme\Models\Consultation;
use Keneya\Dme\Models\Service as ServiceDme;

/**
 * Consultation medicale redigee depuis /service (v3.3.1).
 *
 * Le formulaire est celui de WorkFlow, la donnee est celle du DME : motif,
 * constantes, examen par appareil et diagnostics atterrissent dans
 * `dme_consultations` et ses tables filles, jamais dans le dossier WorkFlow.
 *
 * C'est le sens de tout le chantier v3.3.1 — deplacer le point de saisie
 * plutot que recopier apres coup. Une donnee de sante nait dans le dossier
 * medical, une seule fois, sans correspondance approximative entre un texte
 * libre et une structure.
 *
 * WorkFlow ne garde qu'un renvoi vers l'acte : une ligne de `patient_history`
 * qui dit qu'une consultation existe et sous quel numero. Elle ne porte aucun
 * contenu clinique — ce serait precisement la fuite que le cloisonnement
 * cherche a empecher — mais sans elle, la frise du parcours resterait muette
 * sur le passage le plus important de la journee du patient.
 */
class RecordMedicalConsultation
{
    use ResolvesMedicalRecord;

    public function __construct(private readonly PatientHistoryRecorder $history) {}

    /**
     * @param  array{
     *     started_at: string,
     *     type: string,
     *     reason?: ?string,
     *     history_of_illness?: ?string,
     *     treatment_plan?: ?string,
     *     follow_up?: ?string,
     *     recommendations?: ?string,
     *     vitals?: array<string, mixed>,
     *     exam?: array<string, ?string>,
     *     diagnoses?: array<int, array<string, ?string>>,
     * }  $data
     */
    public function execute(Visit $visit, Doctor|StaffMember $doctor, array $data): Consultation
    {
        $dossier = $this->dossierMedical($visit);

        $consultation = DB::transaction(function () use ($visit, $doctor, $dossier, $data): Consultation {
            $consultation = $dossier->consultations()->create([
                // Le compte WorkFlow, pas la fiche medecin : `users` est la
                // table partagee, et c'est elle que le DME reference.
                'doctor_id' => $doctor->user_id,
                'service_id' => $this->serviceDme($visit),
                'started_at' => $data['started_at'],
                'type' => $data['type'],
                'status' => 'completed',
                'reason' => $data['reason'] ?? null,
                'history_of_illness' => $data['history_of_illness'] ?? null,
                'treatment_plan' => $data['treatment_plan'] ?? null,
                'follow_up' => $data['follow_up'] ?? null,
                'recommendations' => $data['recommendations'] ?? null,
            ]);

            $this->attachVitals($consultation, $doctor, $data['vitals'] ?? []);
            $this->attachExam($consultation, $data['exam'] ?? []);
            $this->attachDiagnoses($consultation, $doctor, $data['diagnoses'] ?? []);

            $this->history->record(
                visit: $visit,
                type: PatientHistory::TYPE_CONSULTATION,
                description: 'Consultation medicale enregistree au dossier ('.$consultation->consultation_number.').',
                doctor: $doctor,
            );

            return $consultation;
        });

        Audit::log(
            Audit::EVENT_MEDICAL_CONSULTATION,
            sprintf(
                'Consultation %s redigee par %s pour %s.',
                $consultation->consultation_number,
                $doctor->name(),
                $visit->patient->patient_code,
            ),
            $visit,
            ['consultation' => $consultation->consultation_number],
        );

        return $consultation;
    }

    /**
     * Le service du DME correspondant a celui de la visite, s'il existe.
     *
     * Rapprochement par le nom, et jamais de creation : les deux applications
     * tiennent chacune leur liste de services, et fabriquer ici un service du
     * DME au vu d'un nom rendrait la correspondance encore plus incertaine.
     * A defaut, la consultation reste sans service — c'est une information de
     * moins, pas une information fausse.
     */
    private function serviceDme(Visit $visit): ?int
    {
        $nom = $visit->service?->name;

        if (blank($nom)) {
            return null;
        }

        return ServiceDme::where('name', $nom)->value('id');
    }

    /**
     * @param  array<string, mixed>  $vitals
     */
    private function attachVitals(Consultation $consultation, Doctor|StaffMember $doctor, array $vitals): void
    {
        $mesures = array_filter($vitals, static fn ($valeur) => $valeur !== null && $valeur !== '');

        if ($mesures === []) {
            return;
        }

        $consultation->vitalSigns()->create($mesures + [
            'patient_id' => $consultation->patient_id,
            'measured_at' => $consultation->started_at,
            'recorded_by' => $doctor->user_id,
        ]);
    }

    /**
     * Examen clinique par appareil. Une note par appareil renseigne, et rien
     * pour les autres : un dossier ne se remplit pas de lignes vides.
     *
     * @param  array<string, ?string>  $exam
     */
    private function attachExam(Consultation $consultation, array $exam): void
    {
        foreach ($exam as $appareil => $contenu) {
            if (blank($contenu) || ! array_key_exists($appareil, ClinicalNote::SYSTEMS)) {
                continue;
            }

            $consultation->clinicalNotes()->create([
                'system' => $appareil,
                'content' => $contenu,
            ]);
        }
    }

    /**
     * @param  array<int, array<string, ?string>>  $diagnoses
     */
    private function attachDiagnoses(Consultation $consultation, Doctor|StaffMember $doctor, array $diagnoses): void
    {
        foreach ($diagnoses as $ligne) {
            if (blank($ligne['label'] ?? null)) {
                continue;
            }

            $consultation->diagnoses()->create([
                'patient_id' => $consultation->patient_id,
                'doctor_id' => $doctor->user_id,
                'label' => $ligne['label'],
                'code' => $ligne['code'] ?? null,
                'code_system' => 'icd10',
                'type' => $ligne['type'] ?? 'primary',
                'status' => $ligne['status'] ?? 'suspected',
                'diagnosed_on' => $consultation->started_at->toDateString(),
                'comment' => $ligne['comment'] ?? null,
            ]);
        }
    }
}
