<?php

namespace App\Livewire\Service\Concerns;

use App\Models\Doctor;
use App\Models\StaffMember;
use App\Support\Caregiver;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Rattache un composant a un service du compte connecte, et refuse tout autre
 * service : y compris si l'identifiant est force cote client.
 *
 * Deux rattachements valent : la fiche `doctors` d'un medecin, et la fiche
 * `staff_members` d'un type de personnel a interface dediee. Les memes ecrans
 * servent les deux interfaces (v3.3.1) : un echographiste a qui l'admin a
 * coche « Demander un examen d'imagerie » doit pouvoir le demander depuis
 * /staff/echographie, sans qu'on lui fabrique une fausse fiche medecin.
 *
 * Les colonnes `*_doctor_id` de WorkFlow restent pour autant reservees aux
 * vrais medecins : c'est {@see Caregiver} qui traduit « qui agit » vers le bon
 * couple de colonnes. Un composant qui a besoin d'un medecin, et il en reste,
 * appelle `currentDoctor()`, qui refuse net les autres.
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

    /**
     * L'agent connecte dans ce service, medecin ou personnel generique.
     */
    protected function currentAgent(): Doctor|StaffMember
    {
        return $this->resolveAgent($this->serviceId);
    }

    protected function currentCaregiver(): Caregiver
    {
        return Caregiver::of($this->currentAgent());
    }

    /**
     * Reserve aux ecrans qui ecrivent une colonne de praticien : on ne
     * fabrique pas de faux medecin dans un dossier.
     */
    protected function currentDoctor(): Doctor
    {
        $agent = $this->resolveAgent($this->serviceId);

        if (! $agent instanceof Doctor) {
            throw new HttpException(403, 'Cette action est reservee aux medecins du service.');
        }

        return $agent;
    }

    protected function assertOwnService(int $serviceId): int
    {
        $this->resolveAgent($serviceId);

        return $serviceId;
    }

    private function resolveAgent(int $serviceId): Doctor|StaffMember
    {
        $user = Auth::user();

        if ($doctor = $user?->doctorFor($serviceId)) {
            return $doctor;
        }

        $member = $user?->staffMember;

        if ($member && (int) $member->service_id === $serviceId) {
            return $member;
        }

        throw new HttpException(403, "Vous n'etes pas rattache a ce service.");
    }
}
