<?php

declare(strict_types=1);

namespace Keneya\Dme\Database\Seeders;

use Keneya\Dme\Support\Rbac;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Rôles et permissions (§31-32).
 *
 * Idempotent, et non destructif : depuis que la matrice est modifiable
 * dans l'écran Paramètres, rejouer le seeder ne doit pas effacer les
 * arbitrages de l'administrateur. Une mise à jour applicative exécute
 * `migrate --seed` ; si ce seeder resynchronisait chaque rôle sur le
 * tableau PHP, chaque déploiement réinitialiserait silencieusement les
 * droits de l'établissement.
 *
 * La règle est donc :
 *
 *  - les permissions déclarées dans Rbac sont créées si elles manquent,
 *    y compris celles ajoutées par une nouvelle version ;
 *  - un rôle sans aucune permission reçoit sa dotation d'origine : c'est
 *    le premier amorçage, ou un rôle nouvellement introduit ;
 *  - un rôle déjà doté conserve ses attributions. Il reçoit néanmoins les
 *    permissions qui viennent d'être créées et que la matrice d'origine
 *    lui destine : sur une permission qui n'existait pas hier,
 *    l'administrateur n'a rien arbitré, et la laisser sans titulaire
 *    rendrait la fonctionnalité livrée inaccessible ;
 *  - le rôle administrateur récupère toujours les permissions sans
 *    lesquelles l'administration deviendrait impossible.
 *
 * Pour repartir de la matrice d'origine, l'écran Paramètres propose
 * « Rétablir la configuration d'origine » : c'est un acte délibéré,
 * journalisé, et non un effet de bord de déploiement.
 */
class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $existing = Permission::where('guard_name', 'web')->pluck('name')->all();
        $fresh = [];

        foreach (Rbac::allPermissions() as $permission) {
            if (! in_array($permission, $existing, true)) {
                $fresh[] = $permission;
            }

            Permission::findOrCreate($permission, 'web');
        }

        foreach (Rbac::rolePermissions() as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $held = $role->permissions()->pluck('name')->all();

            if ($held === []) {
                $role->syncPermissions($permissions);

                continue;
            }

            $toGrant = array_intersect($fresh, $permissions);

            if ($roleName === Rbac::ROLE_ADMIN) {
                $toGrant = [...$toGrant, ...Rbac::lockedAdminPermissions()];
            }

            $toGrant = array_values(array_diff(array_unique($toGrant), $held));

            if ($toGrant !== []) {
                $role->givePermissionTo($toGrant);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
