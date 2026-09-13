<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Support\Dme\PatientProjection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Keneya\Dme\Models\AuditLog;
use Keneya\Dme\Models\Consultation;
use Keneya\Dme\Models\MedicalDocument;
use Keneya\Dme\Models\Patient as DossierMedical;
use Keneya\Dme\Patients\PatientIdentifierResolver;
use Tests\TestCase;

/**
 * Reprise des dossiers medicaux restes orphelins.
 *
 * La suppression d'un dossier patient emporte desormais le dossier medical,
 * mais elle ne peut rien pour ceux qui ont ete supprimes avant : la base de
 * demonstration en contient, PAT-2026-000001 rattache a HFD-00001, dossier
 * WorkFlow qui n'existe plus. Rien dans l'application ne les montre, et sans
 * cette commande ils resteraient.
 *
 * Ce qui est verifie : la commande les trouve, elle ne detruit rien tant
 * qu'on ne le lui demande pas, elle laisse tranquilles les dossiers medicaux
 * qui n'ont jamais eu de dossier WorkFlow, et la trace d'audit survit.
 */
class DossiersMedicauxOrphelinsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Un dossier medical rattache a un numero de dossier WorkFlow qui
     * n'existe pas : exactement l'etat que laissait l'ancienne suppression.
     */
    private function orphelin(string $code = 'HFD-00001'): DossierMedical
    {
        $dossier = app(PatientIdentifierResolver::class)->resolve(
            system: PatientProjection::SYSTEM,
            value: $code,
            attributes: ['last_name' => 'Traore', 'first_name' => 'Aminata', 'sex' => 'Femme'],
        );

        Consultation::factory()->create(['patient_id' => $dossier->getKey()]);

        return $dossier->fresh();
    }

    public function test_sans_force_la_commande_liste_et_ne_supprime_rien(): void
    {
        $orphelin = $this->orphelin();

        // Deux attentes portant sur la meme ligne ne se verifient pas : la
        // ligne du tableau ne satisfait que la premiere. On assure donc le
        // numero de dossier WorkFlow, qui est ce que la commande apporte, et
        // la phrase de fin, qui vit sur une autre ligne.
        $this->artisan('keneya:dossiers-medicaux-orphelins')
            ->expectsOutputToContain('HFD-00001')
            ->expectsOutputToContain("Rien n'a ete supprime")
            ->assertSuccessful();

        // Le defaut protege : la commande efface des donnees de sante sans
        // retour possible, personne ne doit pouvoir le declencher en tapant
        // son nom pour voir ce qu'elle fait.
        $this->assertDatabaseHas('dme_patients', ['id' => $orphelin->getKey()]);
        $this->assertSame(1, Consultation::where('patient_id', $orphelin->getKey())->count());
    }

    public function test_avec_force_le_dossier_orphelin_et_ses_fichiers_partent(): void
    {
        Storage::fake('local');

        $orphelin = $this->orphelin();

        $chemin = 'medical-documents/'.$orphelin->getKey().'/compte-rendu.pdf';
        Storage::disk('local')->put($chemin, 'Contenu du document.');

        MedicalDocument::create([
            'patient_id' => $orphelin->getKey(),
            'title' => 'Compte rendu',
            'type' => 'imported',
            'disk' => 'local',
            'storage_path' => $chemin,
            'status' => 'final',
            'is_generated' => false,
        ]);

        $this->artisan('keneya:dossiers-medicaux-orphelins --force')->assertSuccessful();

        $this->assertDatabaseMissing('dme_patients', ['id' => $orphelin->getKey()]);
        $this->assertDatabaseCount('dme_consultations', 0);
        $this->assertDatabaseCount('dme_medical_documents', 0);
        $this->assertDatabaseCount('dme_patient_identifiers', 0);

        Storage::disk('local')->assertMissing($chemin);
    }

    /**
     * Le cas qu'il ne faut surtout pas emporter : un dossier ouvert
     * directement dans le module, sans passage par l'accueil de WorkFlow.
     * Il n'a pas de dossier WorkFlow a avoir perdu, et il est absent de la
     * liste par construction — la commande part des identifiants externes,
     * pas des dossiers.
     */
    public function test_un_dossier_medical_sans_identifiant_workflow_est_epargne(): void
    {
        $autonome = DossierMedical::factory()->create([
            'last_name' => 'Diarra',
            'first_name' => 'Fatoumata',
        ]);
        Consultation::factory()->create(['patient_id' => $autonome->getKey()]);

        $this->artisan('keneya:dossiers-medicaux-orphelins --force')
            ->expectsOutputToContain('Aucun dossier medical orphelin')
            ->assertSuccessful();

        $this->assertDatabaseHas('dme_patients', ['id' => $autonome->getKey()]);
        $this->assertSame(1, Consultation::where('patient_id', $autonome->getKey())->count());
    }

    /** Un dossier medical dont le dossier WorkFlow existe toujours reste. */
    public function test_un_dossier_medical_encore_rattache_est_epargne(): void
    {
        $patient = Patient::factory()->create(['name' => 'Moussa Keita']);
        $dossier = PatientProjection::resolve($patient);

        $this->artisan('keneya:dossiers-medicaux-orphelins --force')
            ->expectsOutputToContain('Aucun dossier medical orphelin')
            ->assertSuccessful();

        $this->assertDatabaseHas('dme_patients', ['id' => $dossier->getKey()]);
    }

    /**
     * Un dossier medical en suppression douce garde tout son contenu clinique
     * en base : c'est celui qu'il serait le plus facile de manquer, et le
     * plus grave d'oublier.
     */
    public function test_un_dossier_orphelin_en_suppression_douce_est_emporte(): void
    {
        $orphelin = $this->orphelin();
        $orphelin->delete();

        $this->artisan('keneya:dossiers-medicaux-orphelins --force')->assertSuccessful();

        $this->assertDatabaseMissing('dme_patients', ['id' => $orphelin->getKey()]);
        $this->assertDatabaseCount('dme_consultations', 0);
    }

    public function test_la_trace_d_audit_survit_a_la_reprise(): void
    {
        $orphelin = $this->orphelin();
        $numero = $orphelin->patient_number;

        $this->artisan('keneya:dossiers-medicaux-orphelins --force --motif="Reprise v3.4.1."')
            ->assertSuccessful();

        $trace = AuditLog::where('action', 'purged')->latest('id')->firstOrFail();

        $this->assertSame($orphelin->getKey(), (int) $trace->patient_id);
        $this->assertStringContainsString($numero, $trace->description);
        $this->assertStringContainsString('Reprise v3.4.1.', $trace->description);

        // L'origine dit d'ou vient le geste : sans elle, on lit qu'un dossier
        // medical a disparu sans savoir ni pourquoi ni par quoi.
        $this->assertStringContainsString('HFD-00001', $trace->description);
        $this->assertSame('HFD-00001', $trace->properties['patient_code_workflow'] ?? null);
    }

    /** Plusieurs orphelins partent en une passe. */
    public function test_la_commande_emporte_tous_les_orphelins(): void
    {
        $premier = $this->orphelin('HFD-00001');
        $second = $this->orphelin('HFD-00002');

        $this->artisan('keneya:dossiers-medicaux-orphelins --force')->assertSuccessful();

        $this->assertDatabaseMissing('dme_patients', ['id' => $premier->getKey()]);
        $this->assertDatabaseMissing('dme_patients', ['id' => $second->getKey()]);
    }
}
