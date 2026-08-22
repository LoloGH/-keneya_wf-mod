<?php

namespace App\Livewire\Admin;

use App\Models\Service;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Gestion des services de l'etablissement (creation, renommage, type).
 */
class ServiceManager extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public string $kind = Service::KIND_CLINIQUE;

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', 'in:'.Service::KIND_CLINIQUE.','.Service::KIND_PLATEAU_TECHNIQUE],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['name' => 'nom du service', 'kind' => 'type de service'];
    }

    public function edit(int $serviceId): void
    {
        $service = Service::findOrFail($serviceId);

        $this->editingId = $service->getKey();
        $this->name = $service->name;
        $this->kind = $service->kind;
        $this->resetValidation();
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'name', 'kind']);
        $this->resetValidation();
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editingId) {
            $service = Service::findOrFail($this->editingId);
            $service->update($data);

            Audit::log(Audit::EVENT_SERVICE_UPDATED, sprintf('Service « %s » modifie.', $service->name), $service);
            session()->flash('admin.status', 'Service mis a jour.');
        } else {
            $service = Service::create($data);

            Audit::log(Audit::EVENT_SERVICE_CREATED, sprintf('Service « %s » cree.', $service->name), $service);
            session()->flash('admin.status', 'Service cree.');
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
            session()->flash('admin.error', sprintf(
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

        session()->flash('admin.status', 'Service supprime.');
        $this->dispatch('services-mis-a-jour');
    }

    public function render(): View
    {
        return view('livewire.admin.service-manager', [
            'services' => Service::withCount(['doctors', 'visits'])->orderBy('name')->get(),
        ]);
    }
}
