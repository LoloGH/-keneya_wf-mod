<?php

declare(strict_types=1);

namespace Keneya\Dme\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Keneya\Dme\Models\Patient;
use Keneya\Dme\Models\User;
use Keneya\Dme\Support\Rbac;
use Keneya\Dme\Tests\TestCase;

/**
 * Le formulaire patient, aligné sur celui de l'hôte (v3.3.2).
 *
 * Deux manques s'y voyaient. Le numéro de la carte d'identité, relevé à
 * l'accueil de l'hôte, n'avait aucune place au dossier : ni champ, ni
 * affichage. Et la personne à prévenir se saisissait à la modification d'un
 * dossier sans y être jamais enregistrée — le champ s'affichait, se
 * remplissait, et disparaissait à l'envoi.
 */
class PatientFormAlignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $reception;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();

        $this->reception = $this->userWithRole(Rbac::ROLE_RECEPTION);
    }

    public function test_la_carte_d_identite_se_saisit_a_la_creation(): void
    {
        $this->actingAs($this->reception)
            ->post(route('dme.patients.store'), $this->identite(['id_card_number' => 'AB123456']))
            ->assertRedirect();

        $this->assertSame('AB123456', Patient::sole()->id_card_number);
    }

    /**
     * Facultative, et elle doit le rester : un patient arrivé aux urgences
     * sans papiers doit avoir un dossier quand même.
     */
    public function test_la_carte_d_identite_reste_facultative(): void
    {
        $this->actingAs($this->reception)
            ->post(route('dme.patients.store'), $this->identite())
            ->assertRedirect();

        $this->assertNull(Patient::sole()->id_card_number);
    }

    public function test_la_carte_d_identite_se_corrige(): void
    {
        $patient = Patient::factory()->create(['id_card_number' => 'AB123456']);

        $this->actingAs($this->reception)
            ->put(route('dme.patients.update', $patient), $this->identite([
                'id_card_number' => 'CD789012',
            ]))
            ->assertRedirect();

        $this->assertSame('CD789012', $patient->fresh()->id_card_number);
    }

    public function test_la_personne_a_prevenir_s_enregistre_a_la_modification(): void
    {
        $patient = Patient::factory()->create();

        $this->actingAs($this->reception)
            ->put(route('dme.patients.update', $patient), $this->identite([
                'emergency_contact' => [
                    'name' => 'Aminata Traoré',
                    'relationship' => 'Épouse',
                    'phone' => '+22370001002',
                ],
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('dme_emergency_contacts', [
            'patient_id' => $patient->getKey(),
            'name' => 'Aminata Traoré',
            'relationship' => 'Épouse',
            'phone' => '+22370001002',
            'is_primary' => true,
        ]);
    }

    /**
     * Un nom déjà connu met le contact à jour au lieu d'en créer un second :
     * un dossier finissait sinon par porter trois fois la même épouse, avec
     * trois numéros dont on ne savait plus lequel était le bon.
     */
    public function test_le_meme_nom_met_a_jour_le_contact_au_lieu_d_en_creer_un_second(): void
    {
        $patient = Patient::factory()->create();

        foreach (['+22370001002', '+22370009999'] as $numero) {
            $this->actingAs($this->reception)
                ->put(route('dme.patients.update', $patient), $this->identite([
                    'emergency_contact' => [
                        'name' => 'Aminata Traoré',
                        'relationship' => 'Épouse',
                        'phone' => $numero,
                    ],
                ]))
                ->assertRedirect();
        }

        $this->assertSame(1, $patient->emergencyContacts()->count());
        $this->assertSame('+22370009999', $patient->emergencyContacts()->sole()->phone);
    }

    /**
     * L'hôte enregistre des accompagnateurs sans numéro : un nom et un lien de
     * parenté disent déjà qui chercher dans la salle d'attente.
     */
    public function test_la_personne_a_prevenir_s_enregistre_sans_telephone(): void
    {
        $patient = Patient::factory()->create();

        $this->actingAs($this->reception)
            ->put(route('dme.patients.update', $patient), $this->identite([
                'emergency_contact' => ['name' => 'Sékou Diarra', 'relationship' => 'Fils'],
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('dme_emergency_contacts', [
            'patient_id' => $patient->getKey(),
            'name' => 'Sékou Diarra',
            'phone' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function identite(array $extra = []): array
    {
        return array_merge([
            'last_name' => 'Traoré',
            'first_name' => 'Mamadou',
            'sex' => 'male',
        ], $extra);
    }
}
