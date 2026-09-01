<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\NotifiesUser;
use App\Models\Pathology;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Catalogue de pathologies (v3.2.9, point 1).
 *
 * Meme forme que les autres catalogues administrables. Il sert au filtrage
 * d'une diffusion, pas au codage d'un diagnostic.
 */
class PathologyManager extends Component
{
    use NotifiesUser;

    public ?int $editingId = null;

    public string $name = '';

    public function save(): void
    {
        $data = $this->validate(
            ['name' => ['required', 'string', 'max:255']],
            attributes: ['name' => 'nom de la pathologie'],
        );

        if ($this->editingId) {
            $pathologie = Pathology::findOrFail($this->editingId);
            $pathologie->update($data);

            Audit::log(Audit::EVENT_UPDATED, sprintf('Pathologie « %s » renommee.', $pathologie->name), $pathologie);
            $this->notifySuccess('Pathologie mise a jour.');
        } else {
            $pathologie = Pathology::create($data);

            Audit::log(Audit::EVENT_CREATED, sprintf('Pathologie « %s » creee.', $pathologie->name), $pathologie);
            $this->notifySuccess('Pathologie creee.');
        }

        $this->cancel();
    }

    public function edit(int $id): void
    {
        $pathologie = Pathology::findOrFail($id);

        $this->editingId = $pathologie->getKey();
        $this->name = $pathologie->name;
        $this->resetValidation();
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'name']);
        $this->resetValidation();
    }

    public function delete(int $id): void
    {
        $pathologie = Pathology::withCount('visits')->findOrFail($id);

        // Une pathologie deja posee sur des visites ne se supprime pas : le
        // dossier d'un patient ne doit pas perdre retroactivement le motif
        // note par son medecin.
        if ($pathologie->visits_count > 0) {
            $this->notifyError(sprintf(
                'La pathologie « %s » est notee sur %d visite(s) : renommez-la plutot que de la supprimer.',
                $pathologie->name,
                $pathologie->visits_count,
            ));

            return;
        }

        $nom = $pathologie->name;
        $pathologie->delete();

        Audit::log(Audit::EVENT_DELETED, sprintf('Pathologie « %s » supprimee.', $nom));
        $this->notifySuccess('Pathologie supprimee.');
    }

    public function render(): View
    {
        return view('livewire.admin.pathology-manager', [
            'pathologies' => Pathology::withCount('visits')->orderBy('name')->get(),
        ]);
    }
}
