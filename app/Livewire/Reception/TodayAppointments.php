<?php

namespace App\Livewire\Reception;

use App\Actions\CheckInAppointment;
use App\Models\Appointment;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Component;

/**
 * « Rendez-vous » (addendum v2, point 9) : les rendez-vous du jour, avec
 * l'action « Orienter le patient ».
 *
 * A l'arrivee du patient, on ouvre directement un nouveau passage dans le
 * service prevu, sous son identite existante : pas de reenregistrement
 * complet pour quelqu'un que l'hopital connait deja.
 */
class TodayAppointments extends Component
{
    public string $day = 'today';

    public function checkIn(int $appointmentId, CheckInAppointment $action): void
    {
        $appointment = Appointment::with('patient')->findOrFail($appointmentId);

        try {
            $visit = $action->execute($appointment);
        } catch (InvalidArgumentException $e) {
            session()->flash('reception.error', $e->getMessage());

            return;
        }

        session()->flash('reception.success', sprintf(
            '%s (%s) oriente vers %s : ticket n° %d.',
            $appointment->patient->name,
            $appointment->patient->patient_code,
            $visit->service->name,
            $visit->token,
        ));

        $this->dispatch('patient-enregistre');
    }

    public function markNoShow(int $appointmentId, CheckInAppointment $action): void
    {
        $appointment = Appointment::findOrFail($appointmentId);

        try {
            $action->markNoShow($appointment);
        } catch (InvalidArgumentException $e) {
            session()->flash('reception.error', $e->getMessage());

            return;
        }

        session()->flash('reception.success', 'Rendez-vous marque « non presente ».');
    }

    public function cancel(int $appointmentId, CheckInAppointment $action): void
    {
        $appointment = Appointment::findOrFail($appointmentId);

        try {
            $action->cancel($appointment);
        } catch (InvalidArgumentException $e) {
            session()->flash('reception.error', $e->getMessage());

            return;
        }

        session()->flash('reception.success', 'Rendez-vous annule.');
    }

    public function render(): View
    {
        $appointments = Appointment::query()
            ->with(['patient', 'doctor.user', 'staffMember.user', 'service'])
            ->whereDate('scheduled_at', today())
            ->orderBy('scheduled_at')
            ->get();

        return view('livewire.reception.today-appointments', [
            'appointments' => $appointments,
        ]);
    }
}
