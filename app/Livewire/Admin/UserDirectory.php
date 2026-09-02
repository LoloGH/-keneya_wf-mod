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
 * leur service, leur planning. Cette section-ci regarde les COMPTES — qui peut
 * se connecter, sous quel role, et depuis quand. Les deux repondent a des
 * questions differentes : « qui travaille en echographie ? » d'un cote, « qui
 * a acces a l'administration ? » de l'autre.
 *
 * Volontairement en lecture seule : la creation et la modification d'un agent
 * passent deja par « Personnels », ou le type de personnel choisi decide du
 * role et du rattachement. Un second formulaire capable de creer des comptes
 * en parallele ouvrirait la porte a des comptes sans personne derriere.
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
     * Roles reellement portes par au moins un compte, plutot que la liste
     * complete : proposer un filtre qui ne ramene jamais rien n'aide personne.
     *
     * @return array<string, string>
     */
    public function rolesDisponibles(): array
    {
        return User::query()
            ->distinct()
            ->orderBy('role')
            ->pluck('role')
            ->filter()
            ->mapWithKeys(fn (string $role) => [$role => Roles::LABELS[$role] ?? $role])
            ->all();
    }

    public function render(): View
    {
        $comptes = User::query()
            ->when($this->recherche !== '', function ($query) {
                $terme = '%'.trim($this->recherche).'%';

                $query->where(fn ($q) => $q->where('name', 'like', $terme)->orWhere('email', 'like', $terme));
            })
            ->when($this->role !== '', fn ($query) => $query->where('role', $this->role))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.admin.user-directory', [
            'comptes' => $comptes,
            'roles' => $this->rolesDisponibles(),
        ]);
    }
}
