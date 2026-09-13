<?php

declare(strict_types=1);

namespace Keneya\Dme\Tests\Feature;

use Keneya\Dme\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Keneya\Dme\Tests\TestCase;

/**
 * Vérifie que chaque écran du module se rend réellement, monté dans une
 * application hôte.
 *
 * Ce test est délibérément large : il attrape les erreurs de vue, de
 * relation manquante et de chargement paresseux (le mode strict
 * `preventLazyLoading` transforme une requête N+1 en exception, §58) sur
 * l'intégralité de la navigation, ce qu'aucun test unitaire ne couvre.
 */
class SmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
    }

    /**
     * @return list<array{0: string}>
     */
    public static function ecransPrincipaux(): array
    {
        return [
            'tableau de bord' => ['dashboard'],
            'patients' => ['patients.index'],
            'nouveau patient' => ['patients.create'],
            'consultations' => ['consultations.index'],
            'rendez-vous' => ['appointments.index'],
            'laboratoire' => ['laboratory.index'],
            'imagerie' => ['imaging.index'],
            'hospitalisations' => ['hospitalizations.index'],
            'ordonnances' => ['prescriptions.index'],
            'documents' => ['documents.index'],
            'sms' => ['sms.index'],
            'notifications' => ['notifications.index'],
            'audit' => ['audit.index'],
            'utilisateurs' => ['users.index'],
            'paramètres' => ['settings.index'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('ecransPrincipaux')]
    public function test_les_ecrans_principaux_se_rendent_pour_un_administrateur(string $route): void
    {
        $admin = $this->userWithRole(Rbac::ROLE_ADMIN);

        $this->actingAs($admin)
            ->get(route('dme.'.$route))
            ->assertOk();
    }

    /**
     * @return list<array{0: string}>
     */
    public static function ongletsDuDossier(): array
    {
        return array_map(fn (string $tab) => [$tab], [
            'resume', 'consultations', 'antecedents', 'allergies', 'medicaments',
            'ordonnances', 'laboratoire', 'imagerie', 'hospitalisations', 'soins',
            'rendez-vous', 'documents', 'historique', 'audit',
        ]);
    }

    /**
     * Chaque onglet du DME doit se rendre sur un dossier réellement
     * peuplé : c'est là que se révèlent les relations manquantes.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('ongletsDuDossier')]
    public function test_chaque_onglet_du_dossier_se_rend(string $tab): void
    {
        $this->seed([
            \Keneya\Dme\Database\Seeders\DemoUserSeeder::class,
            \Keneya\Dme\Database\Seeders\DemoMedicalDataSeeder::class,
        ]);

        $admin = $this->userWithRole(Rbac::ROLE_ADMIN);
        $patient = \Keneya\Dme\Models\Patient::where('last_name', 'Traoré')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('dme.patients.show', ['patient' => $patient, 'tab' => $tab]))
            ->assertOk()
            ->assertSee($patient->patient_number);
    }
}
