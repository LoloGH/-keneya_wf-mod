<?php

declare(strict_types=1);

namespace Keneya\Dme\Tests\Feature;

use Keneya\Dme\Models\LabOrder;
use Keneya\Dme\Models\Patient;
use Keneya\Dme\Models\Prescription;
use Keneya\Dme\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Keneya\Dme\Tests\TestCase;

/**
 * Tests d'autorisation par rôle (§56).
 *
 * Chaque rôle est confronté à l'ensemble des écrans : on vérifie autant
 * ce qu'il peut atteindre que ce qui doit lui être refusé. C'est le
 * garde-fou contre l'élargissement accidentel d'une permission.
 */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
    }

    /**
     * Matrice d'accès aux écrans : [rôle, route, accès attendu].
     *
     * @return list<array{0: string, 1: string, 2: bool}>
     */
    public static function matriceDAcces(): array
    {
        return [
            // La réception gère l'administratif : patients et rendez-vous,
            // mais aucun contenu clinique.
            [Rbac::ROLE_RECEPTION, 'patients.index', true],
            [Rbac::ROLE_RECEPTION, 'patients.create', true],
            [Rbac::ROLE_RECEPTION, 'appointments.index', true],
            [Rbac::ROLE_RECEPTION, 'consultations.index', false],
            [Rbac::ROLE_RECEPTION, 'prescriptions.index', false],
            [Rbac::ROLE_RECEPTION, 'laboratory.index', false],
            [Rbac::ROLE_RECEPTION, 'audit.index', false],
            [Rbac::ROLE_RECEPTION, 'users.index', false],

            // L'infirmier travaille sur les constantes et les soins.
            [Rbac::ROLE_NURSE, 'patients.index', true],
            [Rbac::ROLE_NURSE, 'consultations.index', true],
            [Rbac::ROLE_NURSE, 'hospitalizations.index', true],
            [Rbac::ROLE_NURSE, 'prescriptions.index', false],
            [Rbac::ROLE_NURSE, 'laboratory.index', false],
            [Rbac::ROLE_NURSE, 'users.index', false],

            // Le médecin couvre le parcours clinique.
            [Rbac::ROLE_DOCTOR, 'patients.index', true],
            [Rbac::ROLE_DOCTOR, 'consultations.index', true],
            [Rbac::ROLE_DOCTOR, 'prescriptions.index', true],
            [Rbac::ROLE_DOCTOR, 'laboratory.index', true],
            [Rbac::ROLE_DOCTOR, 'imaging.index', true],
            [Rbac::ROLE_DOCTOR, 'users.index', false],
            [Rbac::ROLE_DOCTOR, 'audit.index', false],

            // Le laboratoire ne voit que son périmètre.
            [Rbac::ROLE_LAB, 'laboratory.index', true],
            [Rbac::ROLE_LAB, 'patients.index', true],
            [Rbac::ROLE_LAB, 'imaging.index', false],
            [Rbac::ROLE_LAB, 'prescriptions.index', false],
            [Rbac::ROLE_LAB, 'consultations.index', false],

            // La radiologie ne voit que l'imagerie.
            [Rbac::ROLE_RADIOLOGY, 'imaging.index', true],
            [Rbac::ROLE_RADIOLOGY, 'laboratory.index', false],
            [Rbac::ROLE_RADIOLOGY, 'prescriptions.index', false],

            // Le pharmacien ne voit que les ordonnances.
            [Rbac::ROLE_PHARMACIST, 'prescriptions.index', true],
            [Rbac::ROLE_PHARMACIST, 'patients.index', true],
            [Rbac::ROLE_PHARMACIST, 'consultations.index', false],
            [Rbac::ROLE_PHARMACIST, 'laboratory.index', false],

            // L'administrateur a l'accès complet.
            [Rbac::ROLE_ADMIN, 'users.index', true],
            [Rbac::ROLE_ADMIN, 'audit.index', true],
            [Rbac::ROLE_ADMIN, 'laboratory.index', true],
        ];
    }

    #[DataProvider('matriceDAcces')]
    public function test_la_matrice_des_acces_par_role_est_respectee(
        string $role,
        string $route,
        bool $autorise,
    ): void {
        $response = $this->actingAs($this->userWithRole($role))->get(route('dme.'.$route));

        $autorise
            ? $response->assertOk()
            : $response->assertForbidden();
    }

    public function test_un_pharmacien_ne_peut_pas_creer_d_ordonnance(): void
    {
        $patient = Patient::factory()->create();

        $this->actingAs($this->userWithRole(Rbac::ROLE_PHARMACIST))
            ->get(route('dme.prescriptions.create', $patient))
            ->assertForbidden();
    }

    public function test_un_medecin_ne_peut_pas_delivrer_une_ordonnance(): void
    {
        $prescription = Prescription::factory()->validated()->create();

        $this->actingAs($this->userWithRole(Rbac::ROLE_DOCTOR))
            ->post(route('dme.prescriptions.dispense', $prescription))
            ->assertForbidden();
    }

    public function test_un_pharmacien_peut_delivrer_une_ordonnance_validee(): void
    {
        $prescription = Prescription::factory()->validated()->create();

        $this->actingAs($this->userWithRole(Rbac::ROLE_PHARMACIST))
            ->post(route('dme.prescriptions.dispense', $prescription))
            ->assertRedirect();

        $this->assertSame('dispensed', $prescription->fresh()->status);
    }

    public function test_un_infirmier_ne_peut_pas_saisir_de_resultat_de_laboratoire(): void
    {
        $order = LabOrder::factory()->create();
        $order->items()->create(['exam_name' => 'Glycémie à jeun', 'status' => 'requested']);

        $this->actingAs($this->userWithRole(Rbac::ROLE_NURSE))
            ->post(route('dme.laboratory.results.store', $order), [
                'results' => [[
                    'lab_order_item_id' => $order->items->first()->id,
                    'parameter' => 'Glycémie',
                    'value' => '1.05',
                    'flag' => 'normal',
                ]],
            ])
            ->assertForbidden();
    }

    public function test_un_infirmier_peut_enregistrer_des_constantes(): void
    {
        $patient = Patient::factory()->create();

        $this->actingAs($this->userWithRole(Rbac::ROLE_NURSE))
            ->post(route('dme.record.vitals.store', $patient), [
                'measured_at' => now()->format('Y-m-d H:i:s'),
                'temperature' => 37.2,
                'systolic' => 122,
                'diastolic' => 78,
            ])
            ->assertRedirect();

        $this->assertSame(1, $patient->vitalSigns()->count());
    }

    public function test_un_compte_desactive_perd_ses_permissions(): void
    {
        $user = $this->userWithRole(Rbac::ROLE_ADMIN, ['is_active' => false]);

        $this->actingAs($user)->get(route('dme.patients.index'))->assertForbidden();
    }
}
