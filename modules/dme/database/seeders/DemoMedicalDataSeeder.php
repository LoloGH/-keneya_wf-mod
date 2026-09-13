<?php

declare(strict_types=1);

namespace Keneya\Dme\Database\Seeders;

use Keneya\Dme\Models\Allergy;
use Keneya\Dme\Models\Appointment;
use Keneya\Dme\Models\ChronicCondition;
use Keneya\Dme\Models\Consultation;
use Keneya\Dme\Models\Hospitalization;
use Keneya\Dme\Models\ImagingOrder;
use Keneya\Dme\Models\LabOrder;
use Keneya\Dme\Models\LabResult;
use Keneya\Dme\Models\MedicalHistory;
use Keneya\Dme\Models\Medication;
use Keneya\Dme\Models\NursingNote;
use Keneya\Dme\Models\Patient;
use Keneya\Dme\Models\Prescription;
use Keneya\Dme\Models\Service;
use Keneya\Dme\Models\SmsMessage;
use Keneya\Dme\Models\User;
use Keneya\Dme\Support\Rbac;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Données médicales de démonstration (§54).
 *
 * TOUTES LES DONNÉES SONT FICTIVES. Aucune donnée médicale réelle n'est
 * introduite dans ce dépôt (§4, §66). Les noms, numéros de téléphone et
 * résultats sont inventés et ne correspondent à aucune personne.
 *
 * Le jeu de données couvre le parcours complet exigé en §67 :
 * patient -> consultation -> constantes -> diagnostic -> ordonnance ->
 * laboratoire -> résultat -> imagerie -> hospitalisation -> soins ->
 * rendez-vous -> documents -> historique -> audit -> SMS.
 */
class DemoMedicalDataSeeder extends Seeder
{
    private User $doctor;

    private User $cardiologist;

    private User $nurse;

    private User $labTechnician;

    private User $radiologist;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Données de démonstration ignorées : environnement non local.');

            return;
        }

        $this->doctor = User::where('email', 'medecin@keneya.test')->firstOrFail();
        $this->cardiologist = User::where('email', 'cardiologue@keneya.test')->firstOrFail();
        $this->nurse = User::where('email', 'infirmier@keneya.test')->firstOrFail();
        $this->labTechnician = User::where('email', 'laboratoire@keneya.test')->firstOrFail();
        $this->radiologist = User::where('email', 'radiologie@keneya.test')->firstOrFail();

        // Les enregistrements sont attribués au médecin : le journal
        // d'audit reflète ainsi un usage réaliste plutôt qu'un acteur nul.
        Auth::login($this->doctor);

        $this->seedPrimaryPatient();
        $this->seedSecondaryPatients();

        Auth::logout();

        $this->command?->info('Jeu de données médicales fictives créé.');
    }

    /**
     * Patient principal : dossier complet, riche, servant de démonstration
     * du parcours de bout en bout (§54).
     */
    private function seedPrimaryPatient(): void
    {
        $services = Service::pluck('id', 'code');

        $patient = Patient::create([
            'last_name' => 'Traoré',
            'first_name' => 'Mamadou',
            'sex' => 'male',
            'birth_date' => Carbon::today()->subYears(42)->subMonths(3),
            'birth_place' => 'Ségou',
            'nationality' => 'Malienne',
            'marital_status' => 'Marié',
            'occupation' => 'Enseignant',
            'phone' => '+22370001001',
            'email' => 'mamadou.traore@example.test',
            'address' => 'Quartier Badalabougou, rue 27',
            'city' => 'Bamako',
            'country' => 'Mali',
            'blood_group' => 'O+',
            'attending_doctor_id' => $this->doctor->id,
            'status' => 'active',
            'created_by' => $this->doctor->id,
        ]);

        $patient->emergencyContacts()->create([
            'name' => 'Aminata Traoré',
            'relationship' => 'Épouse',
            'phone' => '+22370001002',
            'is_primary' => true,
        ]);

        // --- Allergies (§17) : une allergie sévère alimente les alertes
        Allergy::create([
            'patient_id' => $patient->id,
            'allergen' => 'Pénicilline',
            'allergen_type' => 'medication',
            'reaction' => 'Œdème de Quincke, urticaire généralisée',
            'severity' => 'severe',
            'observed_on' => Carbon::today()->subYears(6),
            'status' => 'active',
            'recorded_by' => $this->doctor->id,
        ]);

        Allergy::create([
            'patient_id' => $patient->id,
            'allergen' => 'Arachide',
            'allergen_type' => 'food',
            'reaction' => 'Prurit buccal',
            'severity' => 'mild',
            'status' => 'active',
            'recorded_by' => $this->doctor->id,
        ]);

        // --- Pathologies chroniques (§13)
        foreach ([
            ['label' => 'Hypertension artérielle essentielle', 'code' => 'I10', 'years' => 5, 'status' => 'controlled'],
            ['label' => 'Diabète de type 2', 'code' => 'E11', 'years' => 3, 'status' => 'active'],
        ] as $condition) {
            ChronicCondition::create([
                'patient_id' => $patient->id,
                'label' => $condition['label'],
                'code' => $condition['code'],
                'code_system' => 'icd10',
                'diagnosed_on' => Carbon::today()->subYears($condition['years']),
                'status' => $condition['status'],
                'recorded_by' => $this->doctor->id,
            ]);
        }

        // --- Antécédents (§16)
        foreach ([
            ['category' => 'personal', 'label' => 'Paludisme grave', 'year' => '2016', 'comment' => 'Hospitalisation de 5 jours, évolution favorable.'],
            ['category' => 'surgical', 'label' => 'Appendicectomie', 'year' => '2009', 'facility' => 'Hôpital Gabriel Touré', 'complications' => 'Aucune'],
            ['category' => 'family', 'label' => 'Diabète de type 2', 'relative' => 'Père', 'comment' => 'Diagnostiqué à 55 ans.'],
            ['category' => 'family', 'label' => 'Accident vasculaire cérébral', 'relative' => 'Mère', 'comment' => 'À 68 ans.'],
            ['category' => 'risk_factor', 'label' => 'Tabac', 'comment' => 'Sevré depuis 2019 (10 paquets-année).'],
            ['category' => 'risk_factor', 'label' => 'Activité physique', 'comment' => 'Marche 30 minutes par jour.'],
        ] as $history) {
            MedicalHistory::create($history + [
                'patient_id' => $patient->id,
                'recorded_by' => $this->doctor->id,
            ]);
        }

        // --- Traitements habituels (§18)
        foreach ([
            ['name' => 'Metformine', 'dosage' => '500 mg', 'frequency' => '2 fois par jour', 'route' => 'Orale', 'months' => 30],
            ['name' => 'Amlodipine', 'dosage' => '5 mg', 'frequency' => '1 fois par jour', 'route' => 'Orale', 'months' => 48],
        ] as $medication) {
            Medication::create([
                'patient_id' => $patient->id,
                'name' => $medication['name'],
                'dosage' => $medication['dosage'],
                'frequency' => $medication['frequency'],
                'route' => $medication['route'],
                'started_on' => Carbon::today()->subMonths($medication['months']),
                'prescriber_id' => $this->doctor->id,
                'status' => 'active',
            ]);
        }

        // --- Historique de constantes (§20, §40) : jamais écrasées
        $weights = [74.5, 74.0, 73.4, 73.0, 72.6, 72.2];
        $systolics = [158, 152, 146, 140, 134, 128];
        $glycemias = [1.62, 1.48, 1.39, 1.30, 1.22, 1.14];

        foreach ($weights as $index => $weight) {
            $patient->vitalSigns()->create([
                'measured_at' => Carbon::now()->subMonths(count($weights) - $index)->setTime(9, 15),
                'temperature' => 36.6 + ($index % 3) * 0.2,
                'systolic' => $systolics[$index],
                'diastolic' => 95 - $index * 2,
                'heart_rate' => 84 - $index,
                'respiratory_rate' => 16,
                'oxygen_saturation' => 97 + ($index % 2),
                'weight' => $weight,
                'height' => 176,
                'glycemia' => $glycemias[$index],
                'recorded_by' => $this->nurse->id,
            ]);
        }

        // --- Consultations (§19) : parcours de suivi sur un an
        $this->seedFollowUpConsultation($patient, $services, Carbon::now()->subMonths(8), 'first');
        $consultation = $this->seedFollowUpConsultation($patient, $services, Carbon::now()->subDays(2), 'recent');

        // --- Ordonnance liée à la consultation récente (§22)
        $prescription = Prescription::create([
            'patient_id' => $patient->id,
            'consultation_id' => $consultation->id,
            'doctor_id' => $this->doctor->id,
            'issued_on' => $consultation->started_at->toDateString(),
            'valid_until' => $consultation->started_at->copy()->addMonths(3)->toDateString(),
            'status' => 'validated',
            'instructions' => 'Contrôle de la tension artérielle à domicile deux fois par semaine.',
            'validated_by' => $this->doctor->id,
            'validated_at' => $consultation->started_at,
        ]);

        foreach ([
            ['medication_name' => 'Metformine', 'dosage' => '500 mg', 'form' => 'Comprimé', 'route' => 'Orale',
             'frequency' => '2 fois par jour', 'duration' => '90 jours', 'quantity' => '180 comprimés',
             'instructions' => 'À prendre au milieu des repas.'],
            ['medication_name' => 'Amlodipine', 'dosage' => '5 mg', 'form' => 'Comprimé', 'route' => 'Orale',
             'frequency' => '1 fois par jour', 'duration' => '90 jours', 'quantity' => '90 comprimés',
             'instructions' => 'Le matin, à heure fixe.'],
        ] as $position => $item) {
            $prescription->items()->create($item + ['position' => $position + 1]);
        }

        // --- Laboratoire (§23) : demande, examens et résultats validés
        $labOrder = LabOrder::create([
            'patient_id' => $patient->id,
            'consultation_id' => $consultation->id,
            'doctor_id' => $this->doctor->id,
            'requested_at' => $consultation->started_at,
            'priority' => 'routine',
            'indication' => 'Suivi trimestriel du diabète et bilan rénal.',
            'status' => 'validated',
            'completed_at' => $consultation->started_at->copy()->addHours(6),
        ]);

        $labExams = [
            ['exam' => 'Hémoglobine glyquée (HbA1c)', 'category' => 'Biochimie', 'parameter' => 'HbA1c',
             'value' => '6.8', 'unit' => '%', 'range' => '4.0 - 6.0', 'flag' => 'high'],
            ['exam' => 'Glycémie à jeun', 'category' => 'Biochimie', 'parameter' => 'Glycémie',
             'value' => '1.14', 'unit' => 'g/L', 'range' => '0.70 - 1.10', 'flag' => 'high'],
            ['exam' => 'Créatininémie', 'category' => 'Biochimie', 'parameter' => 'Créatinine',
             'value' => '9.8', 'unit' => 'mg/L', 'range' => '7.0 - 13.0', 'flag' => 'normal'],
            ['exam' => 'Bilan lipidique', 'category' => 'Biochimie', 'parameter' => 'Cholestérol total',
             'value' => '2.05', 'unit' => 'g/L', 'range' => '< 2.00', 'flag' => 'high'],
        ];

        foreach ($labExams as $exam) {
            $item = $labOrder->items()->create([
                'exam_name' => $exam['exam'],
                'category' => $exam['category'],
                'status' => 'validated',
            ]);

            LabResult::create([
                'lab_order_item_id' => $item->id,
                'patient_id' => $patient->id,
                'parameter' => $exam['parameter'],
                'value' => $exam['value'],
                'unit' => $exam['unit'],
                'reference_range' => $exam['range'],
                'flag' => $exam['flag'],
                'measured_at' => $labOrder->completed_at,
                'performed_by' => $this->labTechnician->id,
                'validated_by' => $this->labTechnician->id,
                'validated_at' => $labOrder->completed_at,
            ]);
        }

        // --- Imagerie (§24) avec compte rendu
        $imaging = ImagingOrder::create([
            'patient_id' => $patient->id,
            'consultation_id' => $consultation->id,
            'doctor_id' => $this->doctor->id,
            'modality' => 'ultrasound',
            'body_site' => 'Abdomen complet',
            'requested_at' => Carbon::now()->subDays(20),
            'scheduled_for' => Carbon::now()->subDays(18),
            'priority' => 'routine',
            'indication' => 'Douleurs de l\'hypochondre droit, bilan de stéatose.',
            'status' => 'reported',
            'accession_number' => 'ACC-'.now()->format('Y').'-'.Str::upper(Str::random(8)),
        ]);

        $imaging->report()->create([
            'patient_id' => $patient->id,
            'radiologist_id' => $this->radiologist->id,
            'technique' => 'Échographie abdominale par sonde convexe 3,5 MHz, patient à jeun.',
            'findings' => "Foie de taille normale, échostructure hyperéchogène homogène évoquant une stéatose de grade 1. "
                ."Vésicule biliaire alithiasique, parois fines. Voies biliaires non dilatées. "
                ."Reins de taille et de différenciation normales, sans dilatation des cavités pyélocalicielles.",
            'conclusion' => 'Stéatose hépatique de grade 1. Absence de lithiase vésiculaire. Reins sans anomalie.',
            'is_abnormal' => true,
            'reported_at' => Carbon::now()->subDays(18)->setTime(15, 40),
            'status' => 'final',
        ]);

        // --- Hospitalisation (§25) avec timeline et soins (§26)
        $this->seedHospitalization($patient, $services);

        // --- Rendez-vous à venir (§27)
        Appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'service_id' => $services['MI'] ?? null,
            'scheduled_for' => Carbon::now()->addDays(8)->setTime(9, 30),
            'duration_minutes' => 30,
            'reason' => 'Consultation de suivi, diabète et hypertension',
            'status' => 'confirmed',
            'created_by' => $this->doctor->id,
        ]);

        // --- SMS déjà émis (§35) : historique réaliste, passerelle « log »
        SmsMessage::create([
            'reference' => 'SMS-'.Str::upper(Str::random(10)),
            'recipient' => '+22370001001',
            'body' => 'Keneya : Votre ordonnance a été enregistrée. Référence : '.$prescription->prescription_number.'.',
            'sender' => config('dme.sms.sender'),
            'patient_id' => $patient->id,
            'context_type' => Prescription::class,
            'context_id' => $prescription->id,
            'status' => 'sent',
            'attempts' => 1,
            'sent_at' => $consultation->started_at->copy()->addMinutes(5),
            'gateway' => 'log',
            'created_by' => $this->doctor->id,
        ]);

        SmsMessage::create([
            'reference' => 'SMS-'.Str::upper(Str::random(10)),
            'recipient' => '+22370001001',
            'body' => 'Keneya : Votre résultat d\'analyse est disponible. Présentez-vous avec votre pièce d\'identité.',
            'sender' => config('dme.sms.sender'),
            'patient_id' => $patient->id,
            'status' => 'failed',
            'attempts' => 1,
            'failed_at' => Carbon::now()->subDay(),
            'error_message' => 'Numéro temporairement injoignable (simulation).',
            'gateway' => 'log',
            'created_by' => $this->labTechnician->id,
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<string, int>  $services
     */
    private function seedFollowUpConsultation(
        Patient $patient,
        $services,
        Carbon $date,
        string $variant,
    ): Consultation {
        $consultation = Consultation::create([
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'service_id' => $services['MI'] ?? null,
            'started_at' => $date->copy()->setTime(14, 20),
            'ended_at' => $date->copy()->setTime(14, 50),
            'type' => 'follow_up',
            'status' => 'completed',
            'reason' => $variant === 'first'
                ? 'Céphalées récurrentes et fatigue à l\'effort'
                : 'Consultation de suivi trimestriel, diabète et hypertension',
            'history_of_illness' => $variant === 'first'
                ? "Patient de 42 ans suivi pour hypertension artérielle depuis 5 ans. Décrit depuis trois semaines "
                    ."des céphalées occipitales matinales et une fatigue à l'effort modéré. Observance thérapeutique "
                    ."déclarée irrégulière."
                : "Patient revu à trois mois. Bonne observance rapportée depuis la dernière consultation. "
                    ."Perte pondérale de 2 kg. Plus de céphalées. Autocontrôles tensionnels à domicile "
                    ."globalement satisfaisants.",
            'treatment_plan' => 'Poursuite de la metformine et de l\'amlodipine aux mêmes posologies.',
            'follow_up' => 'Contrôle clinique et biologique dans trois mois.',
            'recommendations' => 'Réduction de l\'apport sodé, marche quotidienne de 30 minutes, arrêt du tabac maintenu.',
        ]);

        foreach ([
            'general' => $variant === 'first'
                ? 'Patient conscient, orienté. État général conservé. Poids 74,5 kg.'
                : 'Patient en bon état général. Poids 72,2 kg, en baisse. Pas de signe de déshydratation.',
            'cardiovascular' => $variant === 'first'
                ? 'Bruits du cœur réguliers, pas de souffle. TA 158/95 mmHg aux deux bras.'
                : 'Bruits du cœur réguliers. TA 128/83 mmHg. Pouls périphériques perçus et symétriques.',
            'respiratory' => 'Murmure vésiculaire bilatéral et symétrique. Pas de râle surajouté.',
            'abdominal' => 'Abdomen souple, dépressible, indolore. Pas d\'hépatomégalie perçue.',
            'neurological' => 'Pas de déficit sensitivomoteur. Réflexes ostéotendineux présents et symétriques.',
        ] as $system => $content) {
            $consultation->clinicalNotes()->create([
                'system' => $system,
                'content' => $content,
                'is_abnormal' => $system === 'cardiovascular' && $variant === 'first',
            ]);
        }

        foreach ([
            ['label' => 'Hypertension artérielle essentielle', 'code' => 'I10', 'type' => 'primary', 'status' => 'chronic'],
            ['label' => 'Diabète de type 2 non insulinodépendant', 'code' => 'E11', 'type' => 'secondary', 'status' => 'chronic'],
        ] as $diagnosis) {
            $consultation->diagnoses()->create($diagnosis + [
                'patient_id' => $patient->id,
                'doctor_id' => $this->doctor->id,
                'code_system' => 'icd10',
                'diagnosed_on' => $consultation->started_at->toDateString(),
            ]);
        }

        return $consultation;
    }

    /**
     * @param  \Illuminate\Support\Collection<string, int>  $services
     */
    private function seedHospitalization(Patient $patient, $services): void
    {
        $admittedAt = Carbon::now()->subMonths(3)->setTime(8, 15);

        $stay = Hospitalization::create([
            'patient_id' => $patient->id,
            'service_id' => $services['MI'] ?? null,
            'doctor_id' => $this->cardiologist->id,
            'admitted_at' => $admittedAt,
            'admission_reason' => 'Poussée hypertensive avec céphalées intenses et vomissements.',
            'admission_diagnosis' => 'Poussée hypertensive',
            'room' => 'C-204',
            'bed' => 'Lit 2',
            'discharged_at' => $admittedAt->copy()->addDays(3)->setTime(11, 0),
            'discharge_diagnosis' => 'Poussée hypertensive contrôlée sur hypertension artérielle essentielle',
            'discharge_treatment' => 'Amlodipine 5 mg le matin. Metformine 500 mg matin et soir.',
            'discharge_recommendations' => 'Régime hyposodé. Autocontrôle tensionnel. Consultation de suivi à un mois.',
            'discharge_summary' => "Patient admis pour poussée hypertensive à 195/115 mmHg. Mise sous traitement "
                ."antihypertenseur avec normalisation progressive des chiffres tensionnels. Bilan de retentissement "
                ."sans anomalie. Évolution favorable, sortie au troisième jour.",
            'discharge_type' => 'home',
            'status' => 'discharged',
        ]);

        foreach ([
            ['type' => 'admission', 'hours' => 0, 'title' => 'Admission en médecine interne',
             'content' => 'TA à l\'entrée 195/115 mmHg. Patient conscient, céphalées cotées 7/10.'],
            ['type' => 'observation', 'hours' => 4, 'title' => 'Surveillance tensionnelle rapprochée',
             'content' => 'TA 178/104 mmHg après première prise. Céphalées en régression.'],
            ['type' => 'exam', 'hours' => 24, 'title' => 'Bilan de retentissement',
             'content' => 'ECG, fond d\'œil et bilan rénal réalisés : sans anomalie significative.'],
            ['type' => 'treatment', 'hours' => 26, 'title' => 'Adaptation thérapeutique',
             'content' => 'Introduction de l\'amlodipine 5 mg par jour.'],
            ['type' => 'evolution', 'hours' => 48, 'title' => 'Évolution favorable',
             'content' => 'TA 142/88 mmHg. Disparition des céphalées. Patient à nouveau autonome.'],
            ['type' => 'discharge', 'hours' => 74, 'title' => 'Sortie à domicile',
             'content' => 'TA 134/84 mmHg. Ordonnance de sortie remise et expliquée au patient.'],
        ] as $event) {
            $stay->events()->create([
                'type' => $event['type'],
                'occurred_at' => $admittedAt->copy()->addHours($event['hours']),
                'title' => $event['title'],
                'content' => $event['content'],
                'recorded_by' => $this->cardiologist->id,
            ]);
        }

        foreach ([
            ['type' => 'care', 'hours' => 1, 'title' => 'Pose d\'une voie veineuse périphérique',
             'content' => 'Cathéter 20G au pli du coude droit. Pansement propre et sec.', 'severity' => 'info'],
            ['type' => 'medication_administration', 'hours' => 2, 'title' => 'Administration d\'amlodipine',
             'content' => 'Prise vérifiée, bien tolérée.', 'medication' => 'Amlodipine', 'dose' => '5 mg',
             'route' => 'Orale', 'severity' => 'info'],
            ['type' => 'observation', 'hours' => 12, 'title' => 'Constantes de nuit',
             'content' => 'TA 168/98 mmHg, pouls 82/min, patient calme, sommeil conservé.', 'severity' => 'warning'],
            ['type' => 'handover', 'hours' => 36, 'title' => 'Transmission équipe de jour',
             'content' => 'Patient stable, tension en amélioration, autonomie complète pour la toilette.',
             'severity' => 'info'],
        ] as $note) {
            NursingNote::create([
                'patient_id' => $patient->id,
                'hospitalization_id' => $stay->id,
                'type' => $note['type'],
                'occurred_at' => $admittedAt->copy()->addHours($note['hours']),
                'title' => $note['title'],
                'content' => $note['content'],
                'medication_name' => $note['medication'] ?? null,
                'medication_dose' => $note['dose'] ?? null,
                'medication_route' => $note['route'] ?? null,
                'severity' => $note['severity'],
                'nurse_id' => $this->nurse->id,
            ]);
        }
    }

    /**
     * Patients complémentaires : donnent du volume aux listes, aux
     * filtres et au tableau de bord sans dupliquer le dossier principal.
     */
    private function seedSecondaryPatients(): void
    {
        $services = Service::pluck('id', 'code');

        $definitions = [
            ['Sow', 'Awa', 'female', 34, 'A+', 'Asthme persistant léger', 'J45', '+22370001010'],
            ['Ba', 'Ousmane', 'male', 68, 'B+', 'Insuffisance cardiaque chronique', 'I50', '+22370001011'],
            ['Diarra', 'Fatoumata', 'female', 27, 'O-', null, null, '+22370001012'],
            ['Konaté', 'Seydou', 'male', 51, 'AB+', 'Hypertension artérielle', 'I10', '+22370001013'],
            ['Cissé', 'Aminata', 'female', 45, 'A-', 'Diabète de type 2', 'E11', '+22370001014'],
            ['Doumbia', 'Ibrahim', 'male', 8, 'O+', null, null, '+22370001015'],
            ['Touré', 'Kadiatou', 'female', 61, 'B-', 'Arthrose du genou', 'M17', '+22370001016'],
            ['Maïga', 'Boubacar', 'male', 39, 'O+', null, null, '+22370001017'],
        ];

        foreach ($definitions as $index => [$lastName, $firstName, $sex, $age, $blood, $condition, $code, $phone]) {
            $patient = Patient::create([
                'last_name' => $lastName,
                'first_name' => $firstName,
                'sex' => $sex,
                'birth_date' => Carbon::today()->subYears($age)->subDays($index * 37),
                'nationality' => 'Malienne',
                'phone' => $phone,
                'city' => 'Bamako',
                'country' => 'Mali',
                'blood_group' => $blood,
                'attending_doctor_id' => $index % 2 === 0 ? $this->doctor->id : $this->cardiologist->id,
                'status' => 'active',
                'created_by' => $this->doctor->id,
            ]);

            if ($condition !== null) {
                ChronicCondition::create([
                    'patient_id' => $patient->id,
                    'label' => $condition,
                    'code' => $code,
                    'code_system' => 'icd10',
                    'diagnosed_on' => Carbon::today()->subYears(2),
                    'status' => 'active',
                    'recorded_by' => $this->doctor->id,
                ]);
            }

            // Une consultation récente pour alimenter le tableau de bord.
            $consultation = Consultation::create([
                'patient_id' => $patient->id,
                'doctor_id' => $index % 2 === 0 ? $this->doctor->id : $this->cardiologist->id,
                'service_id' => $services[$index % 2 === 0 ? 'MG' : 'CARD'] ?? null,
                'started_at' => Carbon::now()->subDays($index)->setTime(10, 0),
                'ended_at' => Carbon::now()->subDays($index)->setTime(10, 25),
                'type' => 'ambulatory',
                'status' => 'completed',
                'reason' => $condition ? 'Suivi - '.$condition : 'Consultation générale',
                'history_of_illness' => 'Patient vu en consultation externe. Examen clinique sans particularité notable.',
            ]);

            $consultation->vitalSigns()->create([
                'patient_id' => $patient->id,
                'measured_at' => $consultation->started_at,
                'temperature' => 36.5 + ($index % 4) * 0.2,
                'systolic' => 118 + $index * 2,
                'diastolic' => 76 + ($index % 5),
                'heart_rate' => 70 + $index,
                'oxygen_saturation' => 97 + ($index % 3),
                'weight' => 55 + $index * 3,
                'height' => 160 + $index,
                'recorded_by' => $this->nurse->id,
            ]);

            // Rendez-vous à venir pour les premiers patients.
            if ($index < 4) {
                Appointment::create([
                    'patient_id' => $patient->id,
                    'doctor_id' => $patient->attending_doctor_id,
                    'service_id' => $services['MG'] ?? null,
                    'scheduled_for' => Carbon::now()->addDays($index + 1)->setTime(8 + $index, 30),
                    'duration_minutes' => 30,
                    'reason' => 'Consultation de suivi',
                    'status' => $index % 2 === 0 ? 'confirmed' : 'scheduled',
                    'created_by' => $this->doctor->id,
                ]);
            }

            // Une demande de laboratoire en attente pour deux patients.
            if ($index < 2) {
                $order = LabOrder::create([
                    'patient_id' => $patient->id,
                    'consultation_id' => $consultation->id,
                    'doctor_id' => $patient->attending_doctor_id,
                    'requested_at' => $consultation->started_at,
                    'priority' => 'routine',
                    'indication' => 'Bilan biologique de contrôle.',
                    'status' => 'requested',
                ]);

                foreach (['Numération formule sanguine', 'Glycémie à jeun'] as $exam) {
                    $order->items()->create(['exam_name' => $exam, 'category' => 'Biochimie', 'status' => 'requested']);
                }
            }
        }

        // Un patient actuellement hospitalisé, pour l'indicateur du tableau de bord.
        $inpatient = Patient::where('last_name', 'Ba')->firstOrFail();

        Hospitalization::create([
            'patient_id' => $inpatient->id,
            'service_id' => $services['CARD'] ?? null,
            'doctor_id' => $this->cardiologist->id,
            'admitted_at' => Carbon::now()->subDays(2)->setTime(7, 45),
            'admission_reason' => 'Décompensation cardiaque avec dyspnée de stade III.',
            'admission_diagnosis' => 'Insuffisance cardiaque décompensée',
            'room' => 'B-102',
            'bed' => 'Lit 1',
            'status' => 'admitted',
        ]);
    }
}
