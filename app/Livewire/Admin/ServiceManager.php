<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\NotifiesUser;
use App\Models\Service;
use App\Models\ServiceKind;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Gestion des services de l'etablissement (creation, renommage, type).
 */
class ServiceManager extends Component
{
    use NotifiesUser;

    public ?int $editingId = null;

    public string $name = '';

    /** Le type est desormais une ligne administrable, plus une valeur d'enum. */
    public ?int $service_kind_id = null;

    #[On('types-de-service-mis-a-jour')]
    public function refreshKinds(): void
    {
        // Un nouveau rendu suffit : la liste des types est relue a chaque rendu.
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Tous les types sont ouverts, Caisse comprise : l'etablissement
            // peut avoir besoin d'un troisieme guichet, et le masquer ne
            // faisait qu'empecher de le declarer.
            'service_kind_id' => ['required', 'integer', 'exists:service_kinds,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['name' => 'nom du service', 'service_kind_id' => 'type de service'];
    }

    public function edit(int $serviceId): void
    {
        $service = Service::findOrFail($serviceId);

        $this->editingId = $service->getKey();
        $this->name = $service->name;
        $this->service_kind_id = $service->service_kind_id;
        $this->resetValidation();
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'name', 'service_kind_id']);
        $this->resetValidation();
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editingId) {
            $service = Service::findOrFail($this->editingId);
            $service->update($data);

            Audit::log(Audit::EVENT_SERVICE_UPDATED, sprintf('Service « %s » modifie.', $service->name), $service);
            $this->notifySuccess('Service mis a jour.');
        } else {
            $service = Service::create($data);

            Audit::log(Audit::EVENT_SERVICE_CREATED, sprintf('Service « %s » cree.', $service->name), $service);
            $this->notifySuccess('Service cree.');
        }

        $this->cancel();
        $this->dispatch('services-mis-a-jour');
    }

    /**
     * Un service encore rattache a un medecin ou a une visite n'est pas
     * supprimable : on romprait des references du dossier patient.
     */
    public function delete(int $serviceId): void
    {
        $service = Service::withCount(['doctors', 'visits'])->findOrFail($serviceId);

        if ($service->doctors_count > 0 || $service->visits_count > 0) {
            $this->notifyError(sprintf(
                'Le service « %s » ne peut pas etre supprime : il compte %d medecin(s) et %d passage(s).',
                $service->name,
                $service->doctors_count,
                $service->visits_count,
            ));

            return;
        }

        $name = $service->name;
        $service->delete();

        Audit::log(Audit::EVENT_SERVICE_DELETED, sprintf('Service « %s » supprime.', $name));

        $this->notifySuccess('Service supprime.');
        $this->dispatch('services-mis-a-jour');
    }

    public function render(): View
    {
        return view('livewire.admin.service-manager', [
            'services' => Service::with('serviceKind')
                ->withCount(['doctors', 'visits'])
                ->orderBy('name')
                ->get(),
            // Tous les types, sans exception : le menu doit refleter la table.
            'kinds' => ServiceKind::orderBy('name')->get(),
        ]);
    }
}
