<?php

namespace App\Livewire\Service\Concerns;

use App\Models\Doctor;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Rattache un composant de l'interface /service a un service du medecin
 * connecte, et refuse tout autre service — y compris si l'identifiant est
 * force cote client.
 */
trait ScopedToOwnService
{
    public int $serviceId;

    public function mount(int $serviceId): void
    {
        $this->serviceId = $this->assertOwnService($serviceId);
    }

    public function onServiceChanged(int $serviceId): void
    {
        $this->serviceId = $this->assertOwnService($serviceId);
        $this->resetServiceState();
    }

    /**
     * Surchargeable par les composants qui gardent un formulaire ouvert.
     */
    protected function resetServiceState(): void {}

    protected function currentDoctor(): Doctor
    {
        return $this->resolveDoctor($this->serviceId);
    }

    private function assertOwnService(int $serviceId): int
    {
        $this->resolveDoctor($serviceId);

        return $serviceId;
    }

    private function resolveDoctor(int $serviceId): Doctor
    {
        $doctor = Auth::user()?->doctorFor($serviceId);

        if (! $doctor) {
            throw new HttpException(403, "Vous n'etes pas rattache a ce service.");
        }

        return $doctor;
    }
}
