<?php

declare(strict_types=1);

namespace Keneya\Dme\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Keneya\Dme\Models\AuditLog;
use Keneya\Dme\Models\Consultation;
use Keneya\Dme\Models\MedicalDocument;
use Keneya\Dme\Models\Patient;
use Keneya\Dme\Models\PatientIdentifier;
use Keneya\Dme\Models\Prescription;
use Keneya\Dme\Models\SmsMessage;
use Keneya\Dme\Services\Patients\PurgePatient;
use Keneya\Dme\Support\Rbac;
use Keneya\Dme\Tests\TestCase;

/**
 * Retirer un dossier du DME (§40).
 *
 * Deux gestes, et l'écart entre eux est tout le sujet : archiver range un
 * dossier sans rien détruire et se défait ; supprimer détruit le dossier et
 * tout son contenu clinique, sans retour possible.
 *
 * Le second exige donc davantage : une permission distincte, un dossier déjà
 * archivé, le numéro retapé à l'identique et un motif.
 */
class PatientRemovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    /** Un dossier avec de quoi vérifier que la cascade emporte tout. */
    private function dossierComplet(): Patient
    {
        $patient = Patient::factory()->create(['last_name' => 'Traore', 'first_name' => 'Aminata']);

        Consultation::factory()->create(['patient_id' => $patient->getKey()]);
        Prescription::factory()->create(['patient_id' => $patient->getKey()]);

        PatientIdentifier::create([
            'patient_id' => $patient->getKey(),
            'system' => 'keneya_workflow',
            'value' => 'HFD-00001',
        ]);

        return $patient;
    }

    // ---------------------------------------------------------- Archivage

    public function test_un_dossier_s_archive_et_se_restaure(): void
    {
        $patient = Patient::factory()->create();
        $admin = $this->userWithRole(Rbac::ROLE_ADMIN);

        $this->actingAs($admin)
            ->patch(route('dme.patients.archive', $patient))
            ->assertRedirect(route('dme.patients.show', $patient));

        $this->assertSame('archived', $patient->fresh()->status);

        // Rien n'a ete detruit : le dossier reste entierement consultable.
        $this->actingAs($admin)->get(route('dme.patients.show', $patient))->assertOk();

        $this->actingAs($admin)->patch(route('dme.patients.restore', $patient));

        $this->assertSame('active', $patient->fresh()->status);
    }

    public function test_un_dossier_archive_sort_de_la_liste_sans_disparaitre(): void
    {
        $actif = Patient::factory()->create(['last_name' => 'Diallo']);
        $archive = Patient::factory()->create(['last_name' => 'Sidibe', 'status' => 'archived']);
        $admin = $this->userWithRole(Rbac::ROLE_ADMIN);

        $this->actingAs($admin)->get(route('dme.patients.index'))
            ->assertSee($actif->patient_number)
            ->assertDontSee($archive->patient_number);

        // Il reste atteignable en le demandant explicitement.
        $this->actingAs($admin)->get(route('dme.patients.index', ['status' => 'archived']))
            ->assertSee($archive->patient_number)
            ->assertDontSee($actif->patient_number);
    }

    public function test_archiver_demande_la_permission(): void
    {
        $patient = Patient::factory()->create();

        // Le medecin soigne, il n'archive pas : `patients.delete` ne lui est
        // pas attribuee a l'amorcage.
        $this->actingAs($this->userWithRole(Rbac::ROLE_DOCTOR))
            ->patch(route('dme.patients.archive', $patient))
            ->assertForbidden();

        $this->assertSame('active', $patient->fresh()->status);
    }

    // ------------------------------------------------ Suppression definitive

    public function test_un_dossier_actif_ne_se_supprime_pas(): void
    {
        $patient = $this->dossierComplet();

        // Le passage par l'archive n'est pas une formalite : il laisse le
        // temps de se raviser, et rend le geste delibere.
        $this->actingAs($this->userWithRole(Rbac::ROLE_ADMIN))
            ->delete(route('dme.patients.destroy', $patient), [
                'patient_number' => $patient->patient_number,
                'reason' => 'Doublon.',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('dme_patients', 1);
    }

    public function test_un_numero_mal_retape_bloque_la_suppression(): void
    {
        $patient = $this->dossierComplet();
        $patient->update(['status' => 'archived']);

        $this->actingAs($this->userWithRole(Rbac::ROLE_ADMIN))
            ->delete(route('dme.patients.destroy', $patient), [
                'patient_number' => 'PAT-2026-999999',
                'reason' => 'Doublon.',
            ])
            ->assertSessionHasErrors('patient_number');

        $this->assertDatabaseCount('dme_patients', 1);
    }

    public function test_un_motif_vide_bloque_la_suppression(): void
    {
        $patient = $this->dossierComplet();
        $patient->update(['status' => 'archived']);

        $this->actingAs($this->userWithRole(Rbac::ROLE_ADMIN))
            ->delete(route('dme.patients.destroy', $patient), [
                'patient_number' => $patient->patient_number,
                'reason' => '',
            ])
            ->assertSessionHasErrors('reason');

        $this->assertDatabaseCount('dme_patients', 1);
    }

    public function test_seul_l_administrateur_supprime_definitivement(): void
    {
        $patient = $this->dossierComplet();
        $patient->update(['status' => 'archived']);

        // `patients.purge` n'est attribuee qu'au role administrateur.
        $this->actingAs($this->userWithRole(Rbac::ROLE_DOCTOR))
            ->delete(route('dme.patients.destroy', $patient), [
                'patient_number' => $patient->patient_number,
                'reason' => 'Doublon.',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('dme_patients', 1);
    }

    public function test_la_suppression_emporte_tout_le_contenu_clinique(): void
    {
        $patient = $this->dossierComplet();
        $patient->update(['status' => 'archived']);

        $this->actingAs($this->userWithRole(Rbac::ROLE_ADMIN))
            ->delete(route('dme.patients.destroy', $patient), [
                'patient_number' => $patient->patient_number,
                'reason' => 'Dossier cree par erreur.',
            ])
            ->assertRedirect(route('dme.patients.index'));

        // Tout part par la cascade de la base, y compris la liaison vers
        // l'application hote : c'est elle qui, restee seule, produirait un
        // second dossier au prochain acces depuis WorkFlow.
        $this->assertDatabaseCount('dme_patients', 0);
        $this->assertDatabaseCount('dme_consultations', 0);
        $this->assertDatabaseCount('dme_prescriptions', 0);
        $this->assertDatabaseCount('dme_patient_identifiers', 0);
    }

    public function test_la_trace_d_audit_survit_a_la_suppression(): void
    {
        $patient = $this->dossierComplet();
        $patient->update(['status' => 'archived']);
        $numero = $patient->patient_number;

        $this->actingAs($this->userWithRole(Rbac::ROLE_ADMIN))
            ->delete(route('dme.patients.destroy', $patient), [
                'patient_number' => $numero,
                'reason' => 'Dossier cree par erreur.',
            ]);

        // Le journal ne reference le patient par aucune cle etrangere : il
        // lui survit, motif compris. C'est la seule chose qui reste.
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'medical',
            'action' => 'purged',
            'patient_id' => $patient->getKey(),
        ]);

        $trace = AuditLog::where('action', 'purged')->firstOrFail();

        $this->assertStringContainsString($numero, $trace->description);
        $this->assertStringContainsString('Dossier cree par erreur.', $trace->description);
    }

    // ------------------------------------------------ Contenu hors cascade

    /**
     * Le contenu que la cascade de la base n'emporte pas.
     *
     * Trois choses portent un `patient_id` sans cle etrangere : les SMS, les
     * notifications et le journal d'audit. Le journal doit survivre, les deux
     * autres doivent partir — ils ne le faisaient pas avant que la sequence
     * soit ecrite, et restaient a designer un dossier disparu.
     */
    public function test_la_suppression_emporte_les_sms_et_les_notifications(): void
    {
        $patient = $this->dossierComplet();
        $patient->update(['status' => 'archived']);

        SmsMessage::create([
            'reference' => 'SMS-TEST-0001',
            'recipient' => '+22376000000',
            'body' => 'Votre resultat est disponible.',
            'patient_id' => $patient->getKey(),
            'status' => 'sent',
        ]);

        DB::table('dme_notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'lab_result',
            'notifiable_type' => 'user',
            'notifiable_id' => 1,
            'data' => '{}',
            'patient_id' => $patient->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->userWithRole(Rbac::ROLE_ADMIN))
            ->delete(route('dme.patients.destroy', $patient), [
                'patient_number' => $patient->patient_number,
                'reason' => 'Dossier cree par erreur.',
            ])
            ->assertRedirect(route('dme.patients.index'));

        $this->assertDatabaseCount('dme_sms_messages', 0);
        $this->assertDatabaseCount('dme_notifications', 0);
    }

    /**
     * Tous les documents quittent le disque, pas seulement un.
     *
     * La sequence relevait les chemins par `pluck('storage_path', 'disk')` :
     * la cle d'un pluck etant unique et tous les documents d'un dossier
     * vivant sur le meme disque, un dossier de trois documents n'en voyait
     * qu'un seul partir. Les deux autres restaient sur le volume, lisibles
     * pour qui y a acces, apres la disparition de la ligne qui les designait.
     */
    public function test_la_suppression_emporte_tous_les_fichiers_du_disque(): void
    {
        Storage::fake('local');

        $patient = $this->dossierComplet();
        $patient->update(['status' => 'archived']);

        $chemins = [];

        foreach (['compte-rendu', 'echographie', 'bilan'] as $rang => $nom) {
            $chemin = 'medical-documents/'.$patient->getKey().'/'.$nom.'.pdf';
            Storage::disk('local')->put($chemin, 'Contenu du document.');

            MedicalDocument::create([
                'patient_id' => $patient->getKey(),
                'title' => $nom,
                'type' => 'imported',
                'disk' => 'local',
                'storage_path' => $chemin,
                'status' => 'final',
                'is_generated' => false,
            ]);

            $chemins[] = $chemin;
        }

        $this->actingAs($this->userWithRole(Rbac::ROLE_ADMIN))
            ->delete(route('dme.patients.destroy', $patient), [
                'patient_number' => $patient->patient_number,
                'reason' => 'Dossier cree par erreur.',
            ])
            ->assertRedirect(route('dme.patients.index'));

        $this->assertDatabaseCount('dme_medical_documents', 0);

        foreach ($chemins as $chemin) {
            Storage::disk('local')->assertMissing($chemin);
        }
    }

    /**
     * L'echec de la transaction ne doit detruire aucun fichier.
     *
     * Les fichiers partent apres la transaction, et non dedans : le disque ne
     * sait pas revenir en arriere. Supprimes a l'interieur, ils seraient
     * detruits meme quand la transaction echoue ensuite — l'administrateur
     * verrait une erreur, en conclurait que rien n'a bouge, et le dossier
     * aurait perdu ses documents.
     */
    public function test_une_suppression_qui_echoue_ne_detruit_aucun_fichier(): void
    {
        Storage::fake('local');

        $patient = $this->dossierComplet();
        $chemin = 'medical-documents/'.$patient->getKey().'/compte-rendu.pdf';
        Storage::disk('local')->put($chemin, 'Contenu du document.');

        MedicalDocument::create([
            'patient_id' => $patient->getKey(),
            'title' => 'Compte rendu',
            'type' => 'imported',
            'disk' => 'local',
            'storage_path' => $chemin,
            'status' => 'final',
            'is_generated' => false,
        ]);

        // Le dossier est encore actif : la policy refuse la purge, et le
        // service n'est jamais atteint. Rien ne doit avoir bouge sur le
        // disque non plus.
        $this->actingAs($this->userWithRole(Rbac::ROLE_ADMIN))
            ->delete(route('dme.patients.destroy', $patient), [
                'patient_number' => $patient->patient_number,
                'reason' => 'Doublon.',
            ])
            ->assertForbidden();

        Storage::disk('local')->assertExists($chemin);
    }

    public function test_un_motif_vide_ne_passe_pas_par_le_service(): void
    {
        $patient = $this->dossierComplet();

        // Le service refuse aussi de son cote : l'ecran valide deja le motif,
        // mais il n'est plus le seul appelant depuis que l'application hote
        // supprime le dossier medical avec le dossier patient.
        $this->expectException(\InvalidArgumentException::class);

        app(PurgePatient::class)->purge($patient, '   ');
    }
}
