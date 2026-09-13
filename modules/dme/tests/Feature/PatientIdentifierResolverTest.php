<?php

declare(strict_types=1);

namespace Keneya\Dme\Tests\Feature;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Keneya\Dme\Models\Patient;
use Keneya\Dme\Models\PatientIdentifier;
use Keneya\Dme\Patients\PatientIdentifierResolver;
use Keneya\Dme\Tests\TestCase;

/**
 * Liaison entre le patient de l'application hôte et celui du DME (§62).
 *
 * Aucun de ces tests ne suppose l'existence de Keneya Workflow : le
 * résolveur ne dépend que d'un couple (système, valeur) et d'un jeu
 * d'informations de base, exactement ce qu'un hôte transmettra.
 */
class PatientIdentifierResolverTest extends TestCase
{
    use RefreshDatabase;

    private const SYSTEM = 'keneya_workflow';

    private function resolver(): PatientIdentifierResolver
    {
        return app(PatientIdentifierResolver::class);
    }

    // -----------------------------------------------------------------
    // Résolution d'un identifiant déjà connu
    // -----------------------------------------------------------------

    public function test_un_identifiant_connu_retourne_le_patient_existant(): void
    {
        $patient = Patient::factory()->create(['last_name' => 'Traoré', 'first_name' => 'Awa']);

        PatientIdentifier::create([
            'patient_id' => $patient->getKey(),
            'system' => self::SYSTEM,
            'value' => 'WF-000123',
        ]);

        $resolved = $this->resolver()->resolve(self::SYSTEM, 'WF-000123', [
            'last_name' => 'Nom transmis par l\'hôte',
        ]);

        $this->assertTrue($resolved->is($patient));
        $this->assertSame(1, Patient::count());
    }

    public function test_un_identifiant_connu_ne_reecrit_jamais_le_dossier_existant(): void
    {
        $patient = Patient::factory()->create(['last_name' => 'Traoré', 'first_name' => 'Awa']);

        PatientIdentifier::create([
            'patient_id' => $patient->getKey(),
            'system' => self::SYSTEM,
            'value' => 'WF-000123',
        ]);

        // L'hôte transmet une identité divergente : le DME ne s'en sert
        // pas pour modifier un dossier médical déjà constitué.
        $this->resolver()->resolve(self::SYSTEM, 'WF-000123', [
            'last_name' => 'Diarra',
            'first_name' => 'Moussa',
            'phone' => '70 11 22 33',
        ]);

        $patient->refresh();

        $this->assertSame('Traoré', $patient->last_name);
        $this->assertSame('Awa', $patient->first_name);
    }

    // -----------------------------------------------------------------
    // Création automatique
    // -----------------------------------------------------------------

    public function test_un_identifiant_inconnu_cree_le_patient_et_le_rattache(): void
    {
        $patient = $this->resolver()->resolve(self::SYSTEM, 'WF-000900', [
            'last_name' => 'Sidibé',
            'first_name' => 'Fatoumata',
            'sex' => 'F',
            'age' => 34,
            'phone' => '+223 70 00 10 01',
        ]);

        $this->assertSame('Sidibé', $patient->last_name);
        $this->assertSame('Fatoumata', $patient->first_name);
        $this->assertSame('female', $patient->sex);
        $this->assertSame('+223 70 00 10 01', $patient->phone);

        // L'identifiant métier du DME reste attribué normalement.
        $this->assertMatchesRegularExpression('/^PAT-\d{4}-\d{6}$/', $patient->patient_number);

        $this->assertDatabaseHas('dme_patient_identifiers', [
            'patient_id' => $patient->getKey(),
            'system' => self::SYSTEM,
            'value' => 'WF-000900',
        ]);
    }

    public function test_un_age_donne_une_date_de_naissance_explicitement_estimee(): void
    {
        $patient = $this->resolver()->resolve(self::SYSTEM, 'WF-000901', [
            'name' => 'Keïta Souleymane',
            'age' => 40,
        ]);

        $this->assertTrue($patient->birth_date_estimated);
        $this->assertSame(now()->year - 40, $patient->birth_date->year);
    }

    public function test_une_date_de_naissance_transmise_n_est_pas_marquee_estimee(): void
    {
        $patient = $this->resolver()->resolve(self::SYSTEM, 'WF-000902', [
            'last_name' => 'Bah',
            'first_name' => 'Kadiatou',
            'birth_date' => '1990-04-17',
        ]);

        $this->assertFalse($patient->birth_date_estimated);
        $this->assertSame('1990-04-17', $patient->birth_date->toDateString());
    }

    /**
     * Le premier mot est le prénom, le reste le nom de famille : c'est le seul
     * découpage qui laisse `fullName()`, qui rend « prénom nom », restituer
     * exactement la chaîne reçue. L'inverse affichait « Aminata Traoré » en
     * « Traoré Aminata » sur chaque document du dossier.
     */
    public function test_un_nom_complet_est_scinde_faute_de_mieux(): void
    {
        $patient = $this->resolver()->resolve(self::SYSTEM, 'WF-000903', [
            'name' => 'Ibrahim Sekou Coulibaly',
        ]);

        $this->assertSame('Sekou Coulibaly', $patient->last_name);
        $this->assertSame('Ibrahim', $patient->first_name);
        $this->assertSame('Ibrahim Sekou Coulibaly', $patient->fullName());
    }

    public function test_un_patient_sans_nom_est_refuse(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->resolver()->resolve(self::SYSTEM, 'WF-000904', ['age' => 20]);
    }

    public function test_un_identifiant_vide_est_refuse(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->resolver()->resolve(self::SYSTEM, '   ', ['last_name' => 'Diallo']);
    }

    // -----------------------------------------------------------------
    // Unicité
    // -----------------------------------------------------------------

    public function test_deux_appels_avec_les_memes_valeurs_ne_creent_qu_un_patient(): void
    {
        $attributes = ['last_name' => 'Diallo', 'first_name' => 'Aïssatou', 'sex' => 'F'];

        $premier = $this->resolver()->resolve(self::SYSTEM, 'WF-000123', $attributes);
        $second = $this->resolver()->resolve(self::SYSTEM, 'WF-000123', $attributes);

        $this->assertTrue($premier->is($second));
        $this->assertSame(1, Patient::count());
        $this->assertSame(1, PatientIdentifier::count());
    }

    public function test_deux_systemes_differents_designent_deux_patients_differents(): void
    {
        $workflow = $this->resolver()->resolve(self::SYSTEM, 'ID-42', ['last_name' => 'Diallo']);
        $registre = $this->resolver()->resolve('registre_national', 'ID-42', ['last_name' => 'Diallo']);

        $this->assertFalse($workflow->is($registre));
        $this->assertSame(2, Patient::count());
    }

    public function test_le_meme_identifiant_ne_peut_pas_etre_rattache_a_deux_patients(): void
    {
        $premier = $this->resolver()->resolve(self::SYSTEM, 'WF-000123', ['last_name' => 'Diallo']);
        $autre = Patient::factory()->create();

        $this->assertTrue($this->resolver()->link($premier, self::SYSTEM, 'WF-000123')->exists);

        $this->expectException(InvalidArgumentException::class);
        $this->resolver()->link($autre, self::SYSTEM, 'WF-000123');
    }

    public function test_le_rattachement_est_idempotent(): void
    {
        $patient = Patient::factory()->create();

        $this->resolver()->link($patient, self::SYSTEM, 'WF-777');
        $this->resolver()->link($patient, self::SYSTEM, 'WF-777');

        $this->assertSame(1, PatientIdentifier::where('value', 'WF-777')->count());
    }

    // -----------------------------------------------------------------
    // Recherche sans création
    // -----------------------------------------------------------------

    public function test_la_recherche_seule_ne_cree_rien(): void
    {
        $this->assertNull($this->resolver()->find(self::SYSTEM, 'WF-inconnu'));
        $this->assertSame(0, Patient::count());
    }

    public function test_la_recherche_stricte_echoue_sur_un_identifiant_inconnu(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->resolver()->findOrFail(self::SYSTEM, 'WF-inconnu');
    }
}
