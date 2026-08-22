<?php

namespace App\Livewire\Admin;

use App\Models\Service;
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
            Service::findOrFail($this->editingId)->update($data);
            session()->flash('admin.status', 'Service mis a jour.');
        } else {
            Service::create($data);
            session()->flash('admin.status', 'Service cree.');
        }

        $this->cancel();
        $this->dispatch('services-mis-a-jour');
    }

    public function render(): View
    {
        return view('livewire.admin.service-manager', [
            'services' => Service::withCount(['doctors', 'patients'])->orderBy('name')->get(),
        ]);
    }
}
