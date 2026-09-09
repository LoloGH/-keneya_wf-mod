<?php

namespace Tests\Feature;

use App\Livewire\Admin\BackupStatus;
use App\Livewire\Admin\GeneralParameters;
use App\Livewire\Admin\UserDirectory;
use App\Models\Setting;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Les trois sections du groupe « Systeme » de /admin.
 *
 * Ce fichier existe parce qu'un controle visuel a trouve ce que la suite ne
 * couvrait pas : l'annuaire des comptes filtrait sur une colonne `users.role`
 * qui n'existe pas : le role est porte par Spatie. En ligne de commande, PHP
 * n'emettait qu'un avertissement ; sous HTTP, le gestionnaire d'erreurs de
 * Laravel le transforme en exception, et la section rendait une page 500.
 * D'ou des tests qui montent reellement chaque composant.
 */
class AdminSystemSectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
    }

    public function test_l_annuaire_des_comptes_liste_les_utilisateurs(): void
    {
        $admin = $this->makeAdmin();
        $admin->update(['name' => 'Fatoumata Sidibe']);

        Livewire::actingAs($admin->refresh())
            ->test(UserDirectory::class)
            ->assertOk()
            ->assertSee('Fatoumata Sidibe')
            ->assertSee($admin->email);
    }

    public function test_le_filtre_par_role_ne_propose_que_des_roles_reellement_portes(): void
    {
        $admin = $this->makeAdmin();

        $composant = Livewire::actingAs($admin->refresh())->test(UserDirectory::class);

        // Un role sans aucun compte ne doit pas figurer dans le filtre : un
        // choix qui ne ramene jamais rien n'aide personne.
        $roles = $composant->instance()->rolesDisponibles();

        $this->assertArrayHasKey(Roles::ADMIN, $roles);
        $this->assertSame(Roles::label(Roles::ADMIN), $roles[Roles::ADMIN]);

        foreach ($roles as $role => $libelle) {
            $this->assertTrue(
                User::role($role)->exists(),
                "Le filtre propose le role {$role}, que personne ne porte.",
            );
        }
    }

    public function test_le_filtre_par_role_restreint_la_liste(): void
    {
        $admin = $this->makeAdmin();
        $admin->update(['name' => 'Une Administratrice']);

        $receptionniste = $this->makeReceptionist();
        $receptionniste->update(['name' => 'Un Receptionniste']);

        Livewire::actingAs($admin->refresh())
            ->test(UserDirectory::class)
            ->set('role', Roles::ADMIN)
            ->assertSee('Une Administratrice')
            ->assertDontSee('Un Receptionniste');
    }

    public function test_la_recherche_porte_sur_le_nom_et_le_courriel(): void
    {
        $admin = $this->makeAdmin();
        $admin->update(['name' => 'Une Administratrice']);

        $autre = $this->makeReceptionist();
        $autre->update(['name' => 'Un Receptionniste']);

        Livewire::actingAs($admin->refresh())
            ->test(UserDirectory::class)
            ->set('recherche', 'Administratrice')
            ->assertSee('Une Administratrice')
            ->assertDontSee('Un Receptionniste');
    }

    public function test_le_delai_de_rappel_devient_modifiable(): void
    {
        // Ce reglage etait lu par le planificateur depuis la v3.2.3 mais
        // modifiable nulle part : il fallait une intervention en base.
        Livewire::actingAs($this->makeAdmin())
            ->test(GeneralParameters::class)
            ->set('reminderMinutes', 90)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('90', Setting::get(Setting::APPOINTMENT_REMINDER_MINUTES));
    }

    public function test_un_delai_de_rappel_absurde_est_refuse(): void
    {
        Livewire::actingAs($this->makeAdmin())
            ->test(GeneralParameters::class)
            ->set('reminderMinutes', 1)
            ->call('save')
            ->assertHasErrors('reminderMinutes');

        $this->assertNull(Setting::get(Setting::APPOINTMENT_REMINDER_MINUTES));
    }

    public function test_l_etat_de_sauvegarde_s_affiche_sans_dossier_de_stockage(): void
    {
        // Le dossier des signatures n'existe qu'au premier depot : son absence
        // est un etat normal, pas une panne.
        Livewire::actingAs($this->makeAdmin())
            ->test(BackupStatus::class)
            ->assertOk()
            ->assertSee('dossiers patients');
    }
}
