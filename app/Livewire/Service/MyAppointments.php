<?php

namespace App\Livewire\Service;

use App\Actions\CheckInAppointment;
use App\Livewire\Concerns\RequiresCapability;
use App\Models\Appointment;
use App\Models\StaffType;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * « Mes rendez-vous » (v3.2, point 4) : les rendez-vous a venir pris par le
 * compte connecte, tries par date. Se place juste sous « Mes patients ».
 *
 * « Pris par moi » ne veut pas dire « pris par un medecin » : depuis la
 * v3.3.1, un type de personnel a interface dediee peut porter la capacite
 * « Donner un rendez-vous », et retrouve ici les siens.
 */
class MyAppointments extends Component
{
    use RequiresCapability;

    public bool $pastToo = false;

    public function mount(): void
    {
        $this->assertCapability(StaffType::CAP_SCHEDULE_APPOINTMENT);
    }

    #[On('rendez-vous-cree')]
    public function refreshList(): void
    {
        // Un nouveau rendu suffit.
    }

    public function cancel(int $appointmentId, CheckInAppointment $action): void
    {
        $appointment = $this->mesRendezVous()->findOrFail($appointmentId);

        try {
            $action->cancel($appointment);
        } catch (InvalidArgumentException $e) {
            session()->flash('service.error', $e->getMessage());

            return;
        }

        session()->flash('service.status', 'Rendez-vous annule.');
    }

    public function showRecord(int $patientId): void
    {
        $this->dispatch('afficher-dossier', patientId: $patientId);
    }

    public function render(): View
    {
        $appointments = $this->mesRendezVous()
            ->with(['patient', 'service'])
            ->when(! $this->pastToo, fn ($q) => $q->where('scheduled_at', '>=', now()->startOfDay()))
            ->orderBy('scheduled_at')
            ->limit(50)
            ->get();

        return view('livewire.service.my-appointments', [
            'appointments' => $appointments,
        ]);
    }

    /**
     * Les rendez-vous signes par le compte connecte, quel que soit son
     * rattachement. Un compte sans aucun des deux ne ramene rien plutot que
     * tout : une requete sans filtre montrerait l'agenda de l'hopital entier.
     */
    private function mesRendezVous(): Builder
    {
        $user = Auth::user();
        $doctorIds = $user->doctors()->pluck('id');
        $staffMemberId = $user->staffMember?->getKey();

        if ($doctorIds->isEmpty() && $staffMemberId === null) {
            return Appointment::query()->whereRaw('1 = 0');
        }

        return Appointment::query()->where(function (Builder $requete) use ($doctorIds, $staffMemberId) {
            if ($doctorIds->isNotEmpty()) {
                $requete->orWhereIn('doctor_id', $doctorIds);
            }

            if ($staffMemberId !== null) {
                $requete->orWhere('staff_member_id', $staffMemberId);
            }
        });
    }
}
