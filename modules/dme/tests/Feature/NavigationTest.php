<?php

declare(strict_types=1);

namespace Keneya\Dme\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Keneya\Dme\Support\Rbac;
use Keneya\Dme\Tests\TestCase;

/**
 * Barre de navigation latérale.
 *
 * Le marquage de l'entrée courante est un repère de lecture : sur un
 * logiciel où l'on navigue toute la journée entre patients, laboratoire et
 * ordonnances, savoir où l'on se trouve n'est pas décoratif. Ce test le
 * vérifie parce qu'un préfixe de route mal calculé avait rendu toutes les
 * entrées actives en même temps : la barre ne disait donc plus rien.
 */
class NavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
    }

    /**
     * Compte les entrées marquées comme courantes dans la page rendue.
     */
    private function entreesActives(string $html): int
    {
        return substr_count($html, 'k-nav-link-active');
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function ecrans(): array
    {
        return [
            'tableau de bord' => ['dme.dashboard', 'Tableau de bord'],
            'patients' => ['dme.patients.index', 'Patients'],
            'consultations' => ['dme.consultations.index', 'Consultations'],
            'laboratoire' => ['dme.laboratory.index', 'Laboratoire'],
            'imagerie' => ['dme.imaging.index', 'Imagerie'],
            'ordonnances' => ['dme.prescriptions.index', 'Ordonnances'],
            'documents' => ['dme.documents.index', 'Documents'],
            'audit' => ['dme.audit.index', 'Audit'],
            'paramètres' => ['dme.settings.index', 'Paramètres'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('ecrans')]
    public function test_une_seule_entree_du_menu_est_marquee_comme_courante(string $route): void
    {
        $reponse = $this->actingAs($this->userWithRole(Rbac::ROLE_ADMIN))
            ->get(route($route))
            ->assertOk();

        $this->assertSame(
            1,
            $this->entreesActives($reponse->getContent()),
            "L'écran {$route} doit marquer exactement une entrée de menu comme courante."
        );
    }

    public function test_l_entree_marquee_est_bien_celle_de_l_ecran_ouvert(): void
    {
        $html = $this->actingAs($this->userWithRole(Rbac::ROLE_ADMIN))
            ->get(route('dme.laboratory.index'))
            ->assertOk()
            ->getContent();

        // Le libellé de l'entrée active suit sa balise ouvrante, derrière
        // l'icône SVG : la fenêtre doit être assez large pour l'atteindre.
        $fragment = substr($html, (int) strpos($html, 'k-nav-link-active'), 1200);

        $this->assertStringContainsString('Laboratoire', $fragment);
        $this->assertStringNotContainsString('Tableau de bord', $fragment);
    }

    public function test_un_ecran_enfant_marque_l_entree_de_sa_famille(): void
    {
        // Créer un patient relève de la famille « patients » : l'entrée
        // Patients doit rester allumée pendant la saisie.
        $html = $this->actingAs($this->userWithRole(Rbac::ROLE_ADMIN))
            ->get(route('dme.patients.create'))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, $this->entreesActives($html));
        $this->assertStringContainsString('Patients', substr($html, (int) strpos($html, 'k-nav-link-active'), 1200));
    }

    public function test_le_menu_se_limite_aux_ecrans_permis(): void
    {
        // Un infirmier n'a ni l'audit, ni les utilisateurs, ni le laboratoire.
        $html = $this->actingAs($this->userWithRole(Rbac::ROLE_NURSE))
            ->get(route('dme.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(route('dme.audit.index'), $html);
        $this->assertStringNotContainsString(route('dme.users.index'), $html);
        $this->assertStringContainsString(route('dme.patients.index'), $html);
    }
}
