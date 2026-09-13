<?php

declare(strict_types=1);

namespace Keneya\Dme\Tests\Feature;

use Keneya\Dme\Models\AuditLog;
use Keneya\Dme\Support\Rbac;
use Keneya\Dme\Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Keneya\Dme\Tests\TestCase;

/**
 * Matrice rôles / permissions modifiable, et écran Paramètres différencié.
 *
 * Deux propriétés comptent ici : l'administrateur peut réellement changer
 * les droits, et il ne peut pas s'enfermer dehors en le faisant.
 */
class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
    }

    private function permissionsOf(string $role): array
    {
        return Role::where('name', $role)->firstOrFail()
            ->permissions->pluck('name')->sort()->values()->all();
    }

    public function test_un_administrateur_modifie_les_permissions_d_un_role(): void
    {
        $admin = $this->userWithRole(Rbac::ROLE_ADMIN);
        $before = $this->permissionsOf(Rbac::ROLE_RECEPTION);
        $this->assertContains('appointments.manage', $before);

        $this->actingAs($admin)
            ->put(route('dme.settings.roles.update'), [
                'permissions' => [
                    Rbac::ROLE_RECEPTION => array_values(array_diff($before, ['appointments.manage'])),
                ],
            ])
            ->assertRedirect();

        $this->assertNotContains('appointments.manage', $this->permissionsOf(Rbac::ROLE_RECEPTION));
    }

    public function test_la_modification_prend_effet_immediatement(): void
    {
        $admin = $this->userWithRole(Rbac::ROLE_ADMIN);
        $reception = $this->userWithRole(Rbac::ROLE_RECEPTION);
        $this->assertTrue($reception->can('appointments.manage'));

        $this->actingAs($admin)->put(route('dme.settings.roles.update'), [
            'permissions' => [
                Rbac::ROLE_RECEPTION => array_values(array_diff(
                    $this->permissionsOf(Rbac::ROLE_RECEPTION), ['appointments.manage'],
                )),
            ],
        ]);

        $this->assertFalse($reception->fresh()->can('appointments.manage'));
    }

    public function test_l_administrateur_ne_peut_pas_se_retirer_ses_droits_d_administration(): void
    {
        $admin = $this->userWithRole(Rbac::ROLE_ADMIN);

        // Tentative de tout retirer au rôle administrateur.
        $this->actingAs($admin)
            ->put(route('dme.settings.roles.update'), ['permissions' => [Rbac::ROLE_ADMIN => []]])
            ->assertRedirect();

        $remaining = $this->permissionsOf(Rbac::ROLE_ADMIN);

        foreach (Rbac::lockedAdminPermissions() as $locked) {
            $this->assertContains($locked, $remaining, "La permission {$locked} doit rester attachée.");
        }

        $this->assertTrue($admin->fresh()->can('roles.manage'));
    }

    public function test_nul_ne_retire_roles_manage_a_son_propre_role(): void
    {
        Permission::findOrCreate('roles.manage', 'web');
        $role = Role::findOrCreate('superviseur', 'web');
        $role->syncPermissions(['roles.manage', 'patients.view']);

        $user = $this->userWithRole('superviseur');
        $this->assertTrue($user->can('roles.manage'));

        $this->actingAs($user)
            ->put(route('dme.settings.roles.update'), ['permissions' => ['superviseur' => ['patients.view']]])
            ->assertRedirect();

        $this->assertTrue($user->fresh()->can('roles.manage'));
    }

    public function test_un_role_absent_du_formulaire_reste_intact(): void
    {
        $admin = $this->userWithRole(Rbac::ROLE_ADMIN);
        $before = $this->permissionsOf(Rbac::ROLE_NURSE);

        $this->actingAs($admin)->put(route('dme.settings.roles.update'), [
            'permissions' => [Rbac::ROLE_RECEPTION => $this->permissionsOf(Rbac::ROLE_RECEPTION)],
        ]);

        $this->assertSame($before, $this->permissionsOf(Rbac::ROLE_NURSE));
    }

    public function test_une_permission_inconnue_est_ignoree(): void
    {
        $admin = $this->userWithRole(Rbac::ROLE_ADMIN);

        $this->actingAs($admin)->put(route('dme.settings.roles.update'), [
            'permissions' => [Rbac::ROLE_RECEPTION => ['patients.view', 'tout.pouvoir']],
        ]);

        $this->assertSame(['patients.view'], $this->permissionsOf(Rbac::ROLE_RECEPTION));
    }

    public function test_la_modification_est_journalisee_avec_le_detail(): void
    {
        $admin = $this->userWithRole(Rbac::ROLE_ADMIN);

        $this->actingAs($admin)->put(route('dme.settings.roles.update'), [
            'permissions' => [Rbac::ROLE_RECEPTION => ['patients.view']],
        ]);

        $log = AuditLog::where('action', 'role_permissions_updated')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Réception', $log->description);
        $this->assertContains('appointments.manage', $log->properties['retirées'] ?? []);
    }

    public function test_un_role_non_habilite_ne_touche_pas_a_la_matrice(): void
    {
        foreach ([Rbac::ROLE_DOCTOR, Rbac::ROLE_NURSE, Rbac::ROLE_RECEPTION] as $role) {
            $before = $this->permissionsOf(Rbac::ROLE_RECEPTION);

            $this->actingAs($this->userWithRole($role))
                ->put(route('dme.settings.roles.update'), ['permissions' => [Rbac::ROLE_RECEPTION => []]])
                ->assertForbidden();

            $this->assertSame($before, $this->permissionsOf(Rbac::ROLE_RECEPTION));
        }
    }

    public function test_le_retablissement_restaure_la_matrice_d_origine(): void
    {
        $admin = $this->userWithRole(Rbac::ROLE_ADMIN);
        $origine = $this->permissionsOf(Rbac::ROLE_NURSE);

        $this->actingAs($admin)->put(route('dme.settings.roles.update'), [
            'permissions' => [Rbac::ROLE_NURSE => ['patients.view']],
        ]);
        $this->assertSame(['patients.view'], $this->permissionsOf(Rbac::ROLE_NURSE));

        $this->actingAs($admin)->post(route('dme.settings.roles.reset'))->assertRedirect();

        $this->assertSame($origine, $this->permissionsOf(Rbac::ROLE_NURSE));
    }

    public function test_le_seeder_ne_reecrit_pas_les_arbitrages_de_l_administrateur(): void
    {
        $admin = $this->userWithRole(Rbac::ROLE_ADMIN);

        $this->actingAs($admin)->put(route('dme.settings.roles.update'), [
            'permissions' => [Rbac::ROLE_NURSE => ['patients.view']],
        ]);

        // Une mise à jour applicative rejoue les seeders.
        $this->seed(RoleAndPermissionSeeder::class);

        $this->assertSame(['patients.view'], $this->permissionsOf(Rbac::ROLE_NURSE));
    }

    public function test_le_seeder_attribue_les_permissions_nouvellement_apparues(): void
    {
        // Simule une montée de version : la permission n'existait pas encore.
        Role::where('name', Rbac::ROLE_NURSE)->first()
            ->revokePermissionTo('care_orders.execute');
        Permission::where('name', 'care_orders.execute')->delete();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->assertContains('care_orders.execute', $this->permissionsOf(Rbac::ROLE_NURSE));
    }

    // ---- Écran Paramètres différencié ---------------------------------

    public function test_l_ecran_parametres_se_limite_au_compte_sans_settings_manage(): void
    {
        foreach ([Rbac::ROLE_DOCTOR, Rbac::ROLE_NURSE, Rbac::ROLE_RECEPTION] as $role) {
            $response = $this->actingAs($this->userWithRole($role))->get(route('dme.settings.index'));

            $response->assertOk()
                ->assertViewIs('dme::settings.account')
                ->assertSee('Mes informations')
                ->assertSee('Changer mon mot de passe')
                ->assertDontSee('Rôles et permissions');
        }
    }

    public function test_l_administrateur_obtient_l_ecran_complet(): void
    {
        $this->actingAs($this->userWithRole(Rbac::ROLE_ADMIN))
            ->get(route('dme.settings.index'))
            ->assertOk()
            ->assertViewIs('dme::settings.index')
            ->assertSee('Rôles et permissions')
            ->assertSee('Mes informations');
    }

    public function test_chacun_change_son_mot_de_passe_avec_l_ancien(): void
    {
        $user = $this->userWithRole(Rbac::ROLE_NURSE);

        $this->actingAs($user)
            ->put(route('dme.settings.password.update'), [
                'current_password' => 'mauvais',
                'password' => 'NouveauMotDePasse2026!',
                'password_confirmation' => 'NouveauMotDePasse2026!',
            ])
            ->assertSessionHasErrors('current_password');

        $this->actingAs($user)
            ->put(route('dme.settings.password.update'), [
                'current_password' => 'MotDePasseDeTest2026',
                'password' => 'NouveauMotDePasse2026!',
                'password_confirmation' => 'NouveauMotDePasse2026!',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(
            \Illuminate\Support\Facades\Hash::check('NouveauMotDePasse2026!', $user->fresh()->password),
        );
    }

    public function test_le_changement_de_mot_de_passe_est_journalise_sans_le_reveler(): void
    {
        $user = $this->userWithRole(Rbac::ROLE_NURSE);

        $this->actingAs($user)->put(route('dme.settings.password.update'), [
            'current_password' => 'MotDePasseDeTest2026',
            'password' => 'NouveauMotDePasse2026!',
            'password_confirmation' => 'NouveauMotDePasse2026!',
        ]);

        $log = AuditLog::where('action', 'password_changed')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertStringNotContainsString('NouveauMotDePasse2026', json_encode($log->toArray()));
    }

    public function test_la_prise_de_garde_est_journalisee(): void
    {
        $nurse = $this->userWithRole(Rbac::ROLE_NURSE);
        $this->assertFalse($nurse->is_on_duty);

        $this->actingAs($nurse)->patch(route('dme.settings.duty.toggle'))->assertRedirect();

        $this->assertTrue($nurse->fresh()->is_on_duty);
        $this->assertNotNull($nurse->fresh()->on_duty_since);
        $this->assertDatabaseHas('activity_log', ['action' => 'duty_started']);

        $this->actingAs($nurse->fresh())->patch(route('dme.settings.duty.toggle'));
        $this->assertFalse($nurse->fresh()->is_on_duty);
        $this->assertDatabaseHas('activity_log', ['action' => 'duty_ended']);
    }
}
