<?php

namespace App\Livewire\Service;

use App\Models\Doctor;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Selecteur de service pour le cas rare d'un medecin rattache a plusieurs
 * services. Il ne liste que les services auxquels ce medecin est rattache : ce
 * n'est en aucun cas un acces a la gestion admin.
 */
class ServiceSelector extends Component
{
    public ?int $serviceId = null;

    public function mount(?int $serviceId = null): void
    {
        $this->serviceId = $serviceId ?? $this->assignments()->first()?->service_id;
    }

    public function updatedServiceId($value): void
    {
        $serviceId = (int) $value;

        // On ne propage que des services reellement rattaches a ce medecin.
        if (! Auth::user()?->doctorFor($serviceId)) {
            $this->serviceId = $this->assignments()->first()?->service_id;

            return;
        }

        $this->dispatch('service-change', serviceId: $serviceId);
    }

    /**
     * @return Collection<int, Doctor>
     */
    private function assignments(): Collection
    {
        return Doctor::with('service')
            ->where('user_id', Auth::id())
            ->join('services', 'services.id', '=', 'doctors.service_id')
            ->orderBy('services.name')
            ->select('doctors.*')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.service.service-selector', [
            'assignments' => $this->assignments(),
        ]);
    }
}
