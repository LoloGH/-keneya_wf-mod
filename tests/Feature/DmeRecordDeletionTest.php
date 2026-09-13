<?php

namespace Tests\Feature;

use App\Actions\DeletePatientRecord;
use App\Models\Patient;
use App\Support\Audit;
use App\Support\Dme\PatientProjection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Keneya\Dme\Models\AuditLog;
use Keneya\Dme\Models\Consultation;
use Keneya\Dme\Models\MedicalDocument;
use Keneya\Dme\Models\Patient as DossierMedical;
use Keneya\Dme\Models\Prescription;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * La suppression d'un dossier patient emporte son dossier medical.
 *
 * Elle ne le faisait pas. `DeletePatientRecord` descendait toute la cascade
 * de WorkFlow — passages, renvois, ordonnances, pieces jointes — et
 * s'arretait la. Le dossier medical du module restait entier : consultations,
 * ordonnances, analyses, imagerie, documents sur disque, rattaches a un
 * identifiant externe `keneya_workflow` qui ne designait plus rien.
 *
 * L'enjeu n'est pas de proprete de base. L'administrateur retapait le numero
 * de dossier, fournissait un motif, lisait « dossier supprime » — et les
 * donnees de sante restaient en base, sans plus aucun ecran pour les montrer
 * ni personne pour le savoir.
 *
 * Ces tests tiennent les trois promesses du geste : tout part des deux cotes,
 * un dossier medical sans lien WorkFlow n'est pas touche, et les deux traces
 * d'audit survivent.
 */
class DmeRecordDeletionTest extends TestCase
{
    use RefreshDatabase;

    /** Un patient WorkFlow et son dossier medical, contenu clinique compris. */
    private function patientAvecDossierMedical(): array
    {
        $patient = Patient::factory()->create([
            'name' => 'Aminata Traore',
            'gender' => 'Femme',
            'age' => 34,
        ]);

        $dossier = PatientProjection::resolve($patient);

        Consultation::factory()->create(['patient_id' => $dossier->getKey()]);
        Prescription::factory()->create(['patient_id' => $dossier->getKey()]);

        return [$patient, $dossier->fresh()];
    }

    public function test_supprimer_un_dossier_patient_emporte_le_dossier_medical(): void
    {
        [$patient, $dossier] = $this->patientAvecDossierMedical();
        $admin = $this->makeAdmin();

        app(DeletePatientRecord::class)->execute(
            $patient,
            $admin,
            $patient->patient_code,
            'Dossier cree par erreur.',
        );

        // Des deux cotes, et jusqu'au contenu clinique : c'est lui qui restait.
        $this->assertDatabaseMissing('patients', ['id' => $patient->getKey()]);
        $this->assertDatabaseMissing('dme_patients', ['id' => $dossier->getKey()]);
        $this->assertDatabaseCount('dme_consultations', 0);
        $this->assertDatabaseCount('dme_prescriptions', 0);

        // L'identifiant externe part avec : reste seul, il ferait naitre un
        // second dossier au prochain patient portant le meme numero.
        $this->assertDatabaseCount('dme_patient_identifiers', 0);
    }

    public function test_les_documents_du_dossier_medical_quittent_le_disque(): void
    {
        Storage::fake('attachments');
        Storage::fake('local');

        [$patient, $dossier] = $this->patientAvecDossierMedical();
        $admin = $this->makeAdmin();

        $chemins = [];

        foreach (['compte-rendu', 'echographie', 'bilan'] as $nom) {
            $chemin = 'medical-documents/'.$dossier->getKey().'/'.$nom.'.pdf';
            Storage::disk('local')->put($chemin, 'Contenu du document.');

            MedicalDocument::create([
                'patient_id' => $dossier->getKey(),
                'title' => $nom,
                'type' => 'imported',
                'disk' => 'local',
                'storage_path' => $chemin,
                'status' => 'final',
                'is_generated' => false,
            ]);

            $chemins[] = $chemin;
        }

        app(DeletePatientRecord::class)->execute(
            $patient,
            $admin,
            $patient->patient_code,
            'Dossier cree par erreur.',
        );

        $this->assertDatabaseCount('dme_medical_documents', 0);

        // Tous, et pas seulement le dernier : les trois vivent sur le meme
        // disque, et la sequence les relevait autrefois dans un tableau dont
        // le disque etait la cle.
        foreach ($chemins as $chemin) {
            Storage::disk('local')->assertMissing($chemin);
        }
    }

    /**
     * Un dossier medical ouvert directement dans le module, sans passage par
     * l'accueil de WorkFlow, n'a aucun dossier patient a suivre dans la
     * tombe. La suppression d'un homonyme ne doit pas le frôler.
     */
    public function test_un_dossier_medical_sans_lien_workflow_n_est_pas_touche(): void
    {
        [$patient] = $this->patientAvecDossierMedical();
        $admin = $this->makeAdmin();

        $autonome = DossierMedical::factory()->create([
            'last_name' => 'Traore',
            'first_name' => 'Aminata',
        ]);
        Consultation::factory()->create(['patient_id' => $autonome->getKey()]);

        app(DeletePatientRecord::class)->execute(
            $patient,
            $admin,
            $patient->patient_code,
            'Dossier cree par erreur.',
        );

        $this->assertDatabaseHas('dme_patients', ['id' => $autonome->getKey()]);
        $this->assertSame(1, Consultation::where('patient_id', $autonome->getKey())->count());
    }

    /**
     * Le cas courant : un patient enregistre a l'accueil et jamais vu en
     * consultation n'a pas de dossier medical. La suppression doit passer
     * sans se plaindre — le dossier medical nait au premier acte clinique.
     */
    public function test_un_patient_sans_dossier_medical_se_supprime_sans_erreur(): void
    {
        $patient = Patient::factory()->create(['name' => 'Moussa Keita']);
        $admin = $this->makeAdmin();

        app(DeletePatientRecord::class)->execute(
            $patient,
            $admin,
            $patient->patient_code,
            'Doublon.',
        );

        $this->assertDatabaseMissing('patients', ['id' => $patient->getKey()]);
        $this->assertDatabaseCount('dme_patients', 0);
    }

    /**
     * Les deux journaux survivent, et ils ne disent pas la meme chose : celui
     * de WorkFlow tient le geste administratif, celui du module tient la
     * disparition d'un dossier de sante. Aucun des deux ne reference le
     * patient par cle etrangere.
     */
    public function test_les_deux_traces_d_audit_survivent_a_la_suppression(): void
    {
        [$patient, $dossier] = $this->patientAvecDossierMedical();
        $admin = $this->makeAdmin();
        $code = $patient->patient_code;
        $numero = $dossier->patient_number;

        app(DeletePatientRecord::class)->execute(
            $patient,
            $admin,
            $code,
            'Demande du patient.',
        );

        $cote_workflow = Activity::where('event', Audit::EVENT_PATIENT_DELETED)->latest('id')->firstOrFail();

        $this->assertStringContainsString($code, $cote_workflow->description);
        $this->assertStringContainsString('Demande du patient.', $cote_workflow->description);

        $cote_dme = AuditLog::where('action', 'purged')->latest('id')->firstOrFail();

        $this->assertSame($dossier->getKey(), (int) $cote_dme->patient_id);
        $this->assertStringContainsString($numero, $cote_dme->description);
        $this->assertStringContainsString('Demande du patient.', $cote_dme->description);

        // L'origine, qui est tout l'interet de la ligne : sans elle, on lit
        // qu'un dossier medical a disparu sans savoir pourquoi.
        $this->assertStringContainsString($code, $cote_dme->description);
        $this->assertSame($code, $cote_dme->properties['patient_code_workflow'] ?? null);
    }

    /**
     * La suppression du dossier medical vient apres la cascade WorkFlow, et
     * non dedans : une panne a mi-parcours doit laisser les deux dossiers
     * entiers plutot qu'un patient prive de son contenu clinique.
     */
    public function test_une_confirmation_erronee_ne_touche_ni_l_un_ni_l_autre(): void
    {
        [$patient, $dossier] = $this->patientAvecDossierMedical();
        $admin = $this->makeAdmin();

        try {
            app(DeletePatientRecord::class)->execute($patient, $admin, 'PAT-INEXISTANT', 'Doublon.');
            $this->fail('La suppression aurait du echouer.');
        } catch (\InvalidArgumentException) {
            // Attendu.
        }

        $this->assertDatabaseHas('patients', ['id' => $patient->getKey()]);
        $this->assertDatabaseHas('dme_patients', ['id' => $dossier->getKey()]);
        $this->assertSame(1, Consultation::where('patient_id', $dossier->getKey())->count());
    }
}
