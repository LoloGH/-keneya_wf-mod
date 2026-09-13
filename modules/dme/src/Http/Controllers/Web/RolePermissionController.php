<?php

declare(strict_types=1);

namespace Keneya\Dme\Http\Controllers\Web;

use Keneya\Dme\Http\Controllers\Controller;
use Keneya\Dme\Models\AuditLog;
use Keneya\Dme\Support\Rbac;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Matrice rôles / permissions, modifiable par l'administrateur.
 *
 * Jusqu'ici la matrice vivait dans Rbac::rolePermissions() et n'était
 * modifiable qu'en redéployant. Elle est désormais éditable en ligne ;
 * le tableau PHP ne sert plus qu'à l'amorçage.
 *
 * Deux garde-fous, tous deux côté serveur :
 *
 *  1. Le rôle administrateur conserve toujours les permissions
 *     d'administration. Les retirer fermerait l'écran qui permet de les
 *     rendre, sans autre issue qu'une intervention en base.
 *  2. Un administrateur ne peut pas retirer `roles.manage` à son propre
 *     rôle : la même impasse, atteinte par un autre chemin.
 *
 * Toute modification est journalisée permission par permission : savoir
 * qu'une matrice a changé ne suffit pas, il faut savoir quoi.
 */
class RolePermissionController extends Controller
{
    /**
     * Nouveau rôle, sans permission au départ : l'administrateur les
     * accorde ensuite depuis la matrice, ligne par ligne. Le nom
     * technique (utilisé par hasRole()) est dérivé du libellé et n'est
     * plus modifiable une fois créé : le renommer reviendrait à changer
     * silencieusement l'identité d'un rôle déjà attribué.
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('roles.manage'), 403);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:60'],
        ], [], ['label' => 'nom du rôle']);

        $name = Str::slug($data['label'], '_');

        if ($name === '') {
            return back()->withErrors(['label' => 'Ce nom ne produit aucun identifiant valide.'])->withInput();
        }

        if (Role::where('name', $name)->exists()) {
            return back()->withErrors(['label' => 'Un rôle équivalent existe déjà.'])->withInput();
        }

        $role = Role::create(['name' => $name, 'guard_name' => 'web', 'label' => $data['label']]);

        AuditLog::record(
            action: 'role_created',
            subject: $role,
            description: 'A créé le rôle '.$data['label'],
        );

        return back()->with('success', 'Rôle « '.$data['label'].' » créé. Accordez-lui ses permissions ci-dessous.');
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('roles.manage'), 403);

        $known = Rbac::allPermissions();
        $roles = Role::with('permissions:id,name')->get()->keyBy('name');

        $data = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['array'],
            'permissions.*.*' => ['string'],
        ]);

        $submitted = $data['permissions'];
        $changes = [];

        DB::transaction(function () use ($submitted, $roles, $known, $request, &$changes): void {
            foreach ($roles as $name => $role) {
                if (! array_key_exists($name, $submitted)) {
                    continue;   // Rôle absent du formulaire : on n'y touche pas.
                }

                $wanted = array_values(array_intersect($known, array_unique($submitted[$name])));

                if ($name === Rbac::ROLE_ADMIN) {
                    $wanted = array_values(array_unique([...$wanted, ...Rbac::lockedAdminPermissions()]));
                }

                // Nul ne se retire le droit de rendre les droits.
                if ($request->user()->hasRole($name) && ! in_array('roles.manage', $wanted, true)) {
                    $wanted[] = 'roles.manage';
                }

                $current = $role->permissions->pluck('name')->all();
                $added = array_values(array_diff($wanted, $current));
                $removed = array_values(array_diff($current, $wanted));

                if ($added === [] && $removed === []) {
                    continue;
                }

                $role->syncPermissions($wanted);
                $changes[$name] = ['ajoutées' => $added, 'retirées' => $removed];
            }
        });

        // Les permissions sont mises en cache : sans cela, l'écran
        // afficherait le nouvel état alors que les policies appliqueraient
        // encore l'ancien jusqu'à expiration.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if ($changes === []) {
            return back()->with('success', 'Aucune modification à enregistrer.');
        }

        $labels = Rbac::roleLabels();

        foreach ($changes as $role => $delta) {
            AuditLog::record(
                action: 'role_permissions_updated',
                properties: $delta,
                description: 'A modifié les permissions du rôle '.($labels[$role] ?? $role),
            );
        }

        return back()->with('success', count($changes) === 1
            ? 'Permissions mises à jour pour 1 rôle.'
            : 'Permissions mises à jour pour '.count($changes).' rôles.');
    }

    /**
     * Rétablir la matrice d'origine, telle que définie dans Rbac.
     *
     * Utile quand une modification a coupé un rôle de son périmètre métier
     * et qu'il faut repartir d'une base connue.
     */
    public function reset(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('roles.manage'), 403);

        DB::transaction(function (): void {
            foreach (Rbac::rolePermissions() as $name => $permissions) {
                Role::where('name', $name)->first()?->syncPermissions($permissions);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        AuditLog::record(
            action: 'role_permissions_reset',
            description: 'A rétabli la matrice de rôles et permissions d\'origine',
        );

        return back()->with('success', 'Matrice rétablie dans sa configuration d\'origine.');
    }
}
