<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Support\Roles;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Annuaire des comptes (refonte visuelle, groupe « Systeme »).
 *
 * A ne pas confondre avec « Personnels », qui gere les personnes : leur type,
 * leur service, leur planning. Cette section-ci regarde les COMPTES : qui peut
 * se connecter, sous quel role, et depuis quand. Les deux repondent a des
 * questions differentes : « qui travaille en echographie ? » d'un cote, « qui
 * a acces a l'administration ? » de l'autre.
 *
 * Volontairement en lecture seule : la creation et la modification d'un agent
 * passent deja par « Personnels », ou le type de personnel choisi decide du
 * role et du rattachement. Un second formulaire capable de creer des comptes
 * en parallele ouvrirait la porte a des comptes sans personne derriere.
 *
 * Le role n'est PAS une colonne de `users` : il est porte par Spatie. Le
 * filtre passe donc par le scope `role()` du paquet, et non par un `where`.
 */
class UserDirectory extends Component
{
    use WithPagination;

    public string $recherche = '';

    /** Filtre par role, vide pour tous. */
    public string $role = '';

    public function updatedRecherche(): void
    {
        $this->resetPage();
    }

    public function updatedRole(): void
    {
        $this->resetPage();
    }

    /**
     * Roles fixes reellement portes par au moins un compte, plutot que la
     * liste complete : proposer un filtre qui ne ramene jamais rien n'aide
     * personne.
     *
     * @return array<string, string>
     */
    public function rolesDisponibles(): array
    {
        $disponibles = [];

        foreach (Roles::all() as $role) {
            if (User::role($role)->exists()) {
                $disponibles[$role] = Roles::label($role) ?: $role;
            }
        }

        return $disponibles;
    }

    public function render(): View
    {
        // Pas de `with('staffType')` : `staffType()` n'est pas une relation
        // Eloquent mais un accesseur qui resout le type par le role. Le
        // precharger echouerait, et il n'y a rien a precharger.
        $comptes = User::query()
            ->when($this->recherche !== '', function ($query) {
                $terme = '%'.trim($this->recherche).'%';

                $query->where(fn ($q) => $q->where('name', 'like', $terme)->orWhere('email', 'like', $terme));
            })
            ->when($this->role !== '', fn ($query) => $query->role($this->role))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.admin.user-directory', [
            'comptes' => $comptes,
            'roles' => $this->rolesDisponibles(),
        ]);
    }
}
