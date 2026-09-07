<?php

namespace Database\Seeders;

use App\Support\Roles;
use Illuminate\Database\Seeder;
use Keneya\Dme\Support\Rbac;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions fines du dossier medical, versees dans le RBAC de WorkFlow
 * (v3.3.0).
 *
 * Le module et l'hote partagent les tables de spatie/laravel-permission :
 * c'est ce qui permet a `$user->can('prescriptions.create')` de repondre la
 * meme chose des deux cotes, sans annuaire ni matrice de droits en double.
 *
 * La capacite `can_access_dme` ouvre la porte du module ; ces permissions-ci
 * decident de ce qu'on peut y faire une fois entre. Les deux niveaux sont
 * distincts a dessein : un medecin qui entre dans le dossier n'y a pas pour
 * autant les droits d'un pharmacien ou d'un administrateur.
 *
 * Correspondance de roles retenue, du DME vers WorkFlow :
 *
 *   - `admin` recoit tout, y compris l'administration du module ;
 *   - `doctor` recoit le perimetre clinique du medecin ;
 *   - `receptionist` recoit le perimetre administratif de la reception.
 *
 * Les autres roles du DME (infirmier, laboratoire, radiologie, pharmacien)
 * n'ont pas d'equivalent parmi les quatre roles fixes de WorkFlow : ils
 * relevent des types de personnel generiques, dont les permissions se
 * reglent dans l'ecran des roles du module.
 *
 * Rejouable : rien n'est retire, seules les permissions manquantes sont
 * ajoutees. Un droit revoque a la main dans /admin ne revient donc pas tout
 * seul au prochain `db:seed`.
 */
class DmePermissionSeeder extends Seeder
{
    /**
     * @var array<string, string> role WorkFlow => role du DME dont il herite
     */
    private const ROLE_MAP = [
        Roles::ADMIN => Rbac::ROLE_ADMIN,
        Roles::DOCTOR => Rbac::ROLE_DOCTOR,
        Roles::RECEPTIONIST => Rbac::ROLE_RECEPTION,
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Rbac::allPermissions() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $parPerimetre = Rbac::rolePermissions();

        foreach (self::ROLE_MAP as $roleHote => $roleDme) {
            $role = Role::findOrCreate($roleHote, 'web');

            $role->givePermissionTo($parPerimetre[$roleDme] ?? []);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
