<?php

declare(strict_types=1);

namespace Keneya\Dme\Tests\Feature;

use Keneya\Dme\Models\AuditLog;
use Keneya\Dme\Models\LabOrder;
use Keneya\Dme\Models\MedicalDocument;
use Keneya\Dme\Models\Patient;
use Keneya\Dme\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Keneya\Dme\Tests\TestCase;

/**
 * Tests de sécurité (§57).
 *
 * Accès non autorisé, IDOR, CSRF, XSS, injection SQL, téléchargement de
 * document sans permission, accès direct par URL et élévation de privilège.
 */
class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
    }

    // -----------------------------------------------------------------
    // Accès non authentifié
    // -----------------------------------------------------------------

    public function test_un_visiteur_non_authentifie_est_renvoye_vers_l_authentification_de_l_hote(): void
    {
        $patient = Patient::factory()->create();

        // Le module n'authentifie plus personne : il laisse l'intergiciel
        // `auth` renvoyer le visiteur vers la page de connexion de
        // l'application hôte, déclarée ici comme le ferait un vrai hôte.
        foreach ([
            route('dme.dashboard'),
            route('dme.patients.index'),
            route('dme.patients.show', $patient),
            route('dme.audit.index'),
        ] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_l_api_refuse_une_requete_sans_jeton(): void
    {
        $this->getJson(config('dme.route.api.prefix').'/patients')->assertUnauthorized();
    }

    // -----------------------------------------------------------------
    // Accès direct par URL (§57)
    // -----------------------------------------------------------------

    public function test_l_acces_direct_a_un_dossier_par_url_respecte_les_permissions(): void
    {
        $patient = Patient::factory()->create();

        // La radiologie possède patients.view : l'accès est légitime.
        $this->actingAs($this->userWithRole(Rbac::ROLE_RADIOLOGY))
            ->get(route('dme.patients.show', $patient))
            ->assertOk();

        // Un utilisateur sans aucun rôle n'a aucune permission.
        $this->actingAs(\Keneya\Dme\Models\User::factory()->create())
            ->get(route('dme.patients.show', $patient))
            ->assertForbidden();
    }

    public function test_un_acces_refuse_est_inscrit_au_journal_d_audit(): void
    {
        $patient = Patient::factory()->create();
        $intrus = \Keneya\Dme\Models\User::factory()->create();

        $this->actingAs($intrus)->get(route('dme.patients.show', $patient))->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'patient_id' => $patient->id,
            'causer_id' => $intrus->id,
            'outcome' => 'denied',
        ]);
    }

    // -----------------------------------------------------------------
    // IDOR (§57)
    // -----------------------------------------------------------------

    public function test_un_resultat_ne_peut_pas_etre_rattache_a_une_autre_demande(): void
    {
        $demandeA = LabOrder::factory()->create();
        $demandeB = LabOrder::factory()->create();

        $examenDeB = $demandeB->items()->create(['exam_name' => 'Glycémie à jeun', 'status' => 'requested']);
        $demandeA->items()->create(['exam_name' => 'Créatininémie', 'status' => 'requested']);

        // On tente d'écrire dans la demande A un résultat appartenant à B.
        $this->actingAs($this->userWithRole(Rbac::ROLE_LAB))
            ->post(route('dme.laboratory.results.store', $demandeA), [
                'results' => [[
                    'lab_order_item_id' => $examenDeB->id,
                    'parameter' => 'Glycémie',
                    'value' => '9.99',
                    'flag' => 'critical',
                ]],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('dme_lab_results', 0);
    }

    public function test_une_notification_d_un_autre_utilisateur_ne_peut_pas_etre_marquee_comme_lue(): void
    {
        $proprietaire = $this->userWithRole(Rbac::ROLE_DOCTOR);
        $intrus = $this->userWithRole(Rbac::ROLE_DOCTOR);

        app(\Keneya\Dme\Services\Notifications\NotificationService::class)->store(
            user: $proprietaire,
            category: 'alert',
            title: 'Résultat critique',
            message: 'Message confidentiel',
        );

        $notification = \Illuminate\Support\Facades\DB::table('dme_notifications')->first();

        $this->actingAs($intrus)
            ->post(route('dme.notifications.read', $notification->id))
            ->assertRedirect();

        // La notification de l'autre utilisateur reste non lue.
        $this->assertNull(
            \Illuminate\Support\Facades\DB::table('dme_notifications')->where('id', $notification->id)->value('read_at')
        );
    }

    // -----------------------------------------------------------------
    // Documents (§42, §57)
    // -----------------------------------------------------------------

    public function test_un_document_ne_peut_pas_etre_telecharge_sans_permission(): void
    {
        Storage::fake('local');

        $patient = Patient::factory()->create();
        $medecin = $this->userWithRole(Rbac::ROLE_DOCTOR);

        $document = $this->actingAs($medecin)->uploadDocument($patient);

        // L'infirmier possède documents.view mais pas documents.download.
        $this->actingAs($this->userWithRole(Rbac::ROLE_NURSE))
            ->get(route('dme.documents.download', $document))
            ->assertForbidden();

        $this->actingAs($medecin)
            ->get(route('dme.documents.download', $document))
            ->assertOk();
    }

    public function test_le_chemin_de_stockage_n_est_jamais_expose(): void
    {
        Storage::fake('local');

        $patient = Patient::factory()->create();
        $medecin = $this->userWithRole(Rbac::ROLE_DOCTOR);
        $document = $this->actingAs($medecin)->uploadDocument($patient);

        $response = $this->actingAs($medecin)->get(route('dme.documents.show', $document));

        $response->assertOk();
        $response->assertDontSee($document->storage_path);
        $this->assertArrayNotHasKey('storage_path', $document->toArray());
    }

    public function test_un_telechargement_est_inscrit_au_journal_d_audit(): void
    {
        Storage::fake('local');

        $patient = Patient::factory()->create();
        $medecin = $this->userWithRole(Rbac::ROLE_DOCTOR);
        $document = $this->actingAs($medecin)->uploadDocument($patient);

        $this->actingAs($medecin)->get(route('dme.documents.download', $document))->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'action' => 'downloaded',
            'patient_id' => $patient->id,
            'causer_id' => $medecin->id,
        ]);
    }

    public function test_un_type_de_fichier_non_autorise_est_refuse(): void
    {
        Storage::fake('local');

        $patient = Patient::factory()->create();

        $this->actingAs($this->userWithRole(Rbac::ROLE_DOCTOR))
            ->post(route('dme.documents.store', $patient), [
                'title' => 'Script malveillant',
                'type' => 'imported',
                'file' => UploadedFile::fake()->create('exploit.php', 10, 'application/x-php'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('dme_medical_documents', 0);
    }

    // -----------------------------------------------------------------
    // Injection SQL et XSS (§57)
    // -----------------------------------------------------------------

    public function test_la_recherche_resiste_a_une_tentative_d_injection_sql(): void
    {
        Patient::factory()->count(3)->create();

        $this->actingAs($this->userWithRole(Rbac::ROLE_DOCTOR))
            ->get(route('dme.patients.index', ['q' => "'; DROP TABLE patients; --"]))
            ->assertOk();

        // La table est intacte : la requête est paramétrée.
        $this->assertSame(3, Patient::count());
    }

    public function test_le_contenu_saisi_est_echappe_dans_les_vues(): void
    {
        $patient = Patient::factory()->create([
            'last_name' => '<script>alert("xss")</script>',
            'first_name' => 'Test',
        ]);

        $response = $this->actingAs($this->userWithRole(Rbac::ROLE_DOCTOR))
            ->get(route('dme.patients.show', $patient));

        $response->assertOk();
        $response->assertDontSee('<script>alert("xss")</script>', escape: false);
        $response->assertSee('&lt;script&gt;', escape: false);
    }

    // -----------------------------------------------------------------
    // CSRF (§57)
    // -----------------------------------------------------------------

    /**
     * La protection CSRF ne peut pas être éprouvée par une requête de test :
     * ValidateCsrfToken laisse toujours passer les requêtes émises pendant
     * les tests (`runningUnitTests()`). On vérifie donc les deux conditions
     * qui la rendent effective en production : le middleware est bien
     * appliqué au groupe « web », et les formulaires émettent un jeton.
     */
    public function test_la_protection_csrf_est_active_sur_le_groupe_web(): void
    {
        $middleware = app(\Illuminate\Contracts\Http\Kernel::class)
            ->getMiddlewareGroups()['web'] ?? [];

        $this->assertContains(
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            $middleware,
            'Le middleware CSRF n\'est pas appliqué aux routes web.',
        );
    }

    public function test_les_formulaires_emettent_un_jeton_csrf(): void
    {
        $response = $this->actingAs($this->userWithRole(Rbac::ROLE_DOCTOR))
            ->get(route('dme.patients.create'));

        $response->assertOk();
        $response->assertSee('name="_token"', escape: false);
    }

    // -----------------------------------------------------------------
    // Élévation de privilège (§57)
    // -----------------------------------------------------------------

    public function test_un_utilisateur_ne_peut_pas_s_attribuer_un_role(): void
    {
        $medecin = $this->userWithRole(Rbac::ROLE_DOCTOR);

        $this->actingAs($medecin)
            ->put(route('dme.users.update', $medecin), [
                'first_name' => $medecin->first_name,
                'last_name' => $medecin->last_name,
                'email' => $medecin->email,
                'role' => Rbac::ROLE_ADMIN,
                'is_active' => 1,
            ])
            ->assertForbidden();

        $this->assertTrue($medecin->fresh()->hasRole(Rbac::ROLE_DOCTOR));
        $this->assertFalse($medecin->fresh()->hasRole(Rbac::ROLE_ADMIN));
    }

    public function test_un_administrateur_ne_peut_pas_desactiver_son_propre_compte(): void
    {
        $admin = $this->userWithRole(Rbac::ROLE_ADMIN);

        $this->actingAs($admin)
            ->put(route('dme.users.update', $admin), [
                'first_name' => $admin->first_name,
                'last_name' => $admin->last_name,
                'email' => $admin->email,
                'role' => Rbac::ROLE_ADMIN,
            ])
            ->assertSessionHasErrors('is_active');

        $this->assertTrue($admin->fresh()->is_active);
    }

    // -----------------------------------------------------------------
    // Journal d'audit append-only (§30)
    // -----------------------------------------------------------------

    public function test_une_entree_d_audit_ne_peut_pas_etre_modifiee(): void
    {
        $log = AuditLog::record('viewed', Patient::factory()->create());

        $this->expectException(\RuntimeException::class);

        $log->update(['description' => 'Trace effacée']);
    }

    public function test_une_entree_d_audit_ne_peut_pas_etre_supprimee(): void
    {
        $log = AuditLog::record('viewed', Patient::factory()->create());

        $this->expectException(\RuntimeException::class);

        $log->delete();
    }

    public function test_aucun_role_n_autorise_la_suppression_du_journal(): void
    {
        $log = AuditLog::record('viewed', Patient::factory()->create());

        foreach (array_keys(Rbac::roleLabels()) as $role) {
            $user = $this->userWithRole($role);

            $this->assertFalse($user->can('update', $log), "Le rôle {$role} peut modifier l'audit.");
            $this->assertFalse($user->can('delete', $log), "Le rôle {$role} peut supprimer l'audit.");
        }
    }

    /**
     * Téléverse un document de test dans le dossier d'un patient.
     */
    private function uploadDocument(Patient $patient): MedicalDocument
    {
        $this->post(route('dme.documents.store', $patient), [
            'title' => 'Compte rendu de test',
            'type' => 'imported',
            'file' => UploadedFile::fake()->create('compte-rendu.pdf', 30, 'application/pdf'),
        ])->assertRedirect();

        return MedicalDocument::latest('id')->firstOrFail();
    }
}
