<?php

namespace App\Livewire\Service;

use App\Actions\CheckInAppointment;
use App\Models\Appointment;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * « Mes rendez-vous » (v3.2, point 4) : les rendez-vous a venir pris par le
 * medecin connecte, tries par date. Se place juste sous « Mes patients ».
 */
class MyAppointments extends Component
{
    public bool $pastToo = false;

    #[On('rendez-vous-cree')]
    public function refreshList(): void
    {
        // Un nouveau rendu suffit.
    }

    public function cancel(int $appointmentId, CheckInAppointment $action): void
    {
        $doctorIds = Auth::user()->doctors()->pluck('id');

        $appointment = Appointment::whereIn('doctor_id', $doctorIds)->findOrFail($appointmentId);

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
        $doctorIds = Auth::user()->doctors()->pluck('id');

        $appointments = Appointment::query()
            ->with(['patient', 'service'])
            ->whereIn('doctor_id', $doctorIds)
            ->when(! $this->pastToo, fn ($q) => $q->where('scheduled_at', '>=', now()->startOfDay()))
            ->orderBy('scheduled_at')
            ->limit(50)
            ->get();

        return view('livewire.service.my-appointments', [
            'appointments' => $appointments,
        ]);
    }
}
