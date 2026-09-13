<?php

declare(strict_types=1);

namespace Keneya\Dme\Http\Controllers\Web;

use Keneya\Dme\Dme;
use Keneya\Dme\Http\Controllers\Controller;
use Keneya\Dme\Models\Appointment;
use Keneya\Dme\Models\Patient;
use Keneya\Dme\Models\Service;
use Keneya\Dme\Services\Notifications\NotificationService;
use Keneya\Dme\Support\Rbac;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Rendez-vous (§27) : vues jour, semaine et mois.
 *
 * La création déclenche une confirmation SMS et programme un rappel la
 * veille, via le service de notification (§53). L'échec d'un SMS n'a
 * jamais d'effet sur le rendez-vous lui-même.
 */
class AppointmentController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Appointment::class);

        $view = $request->string('view')->toString() ?: 'week';
        $anchor = $this->anchorDate($request);
        [$start, $end] = $this->range($view, $anchor);

        $appointments = Appointment::query()
            ->with([
                'patient:id,patient_number,first_name,last_name,phone',
                'doctor:id,name,first_name,last_name,title',
                'service:id,name',
            ])
            ->whereBetween('scheduled_for', [$start, $end])
            ->when($request->string('doctor')->toString(), fn ($q, $d) => $q->where('doctor_id', $d))
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->orderBy('scheduled_for')
            ->get();

        return view('dme::appointments.index', [
            'appointments' => $appointments,
            'grouped' => $appointments->groupBy(fn (Appointment $a) => $a->scheduled_for->format('Y-m-d')),
            'view' => $view,
            'anchor' => $anchor,
            'start' => $start,
            'end' => $end,
            'filters' => $request->only(['doctor', 'status']),
            'doctors' => Dme::usersWithRole(Rbac::ROLE_DOCTOR)->where('is_active', true)
                ->orderBy('last_name')->get(['id', 'name', 'first_name', 'last_name', 'title']),
        ]);
    }

    public function store(
        Request $request,
        Patient $patient,
        NotificationService $notifications,
    ): RedirectResponse {
        $this->authorize('create', Appointment::class);

        $data = $request->validate([
            'doctor_id' => ['nullable', 'exists:users,id'],
            'service_id' => ['nullable', 'exists:dme_services,id'],
            'scheduled_for' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['required', 'integer', 'between:5,480'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'reminder_enabled' => ['nullable', 'boolean'],
        ], [
            'scheduled_for.after' => 'Un rendez-vous doit être programmé dans le futur.',
        ], [
            'scheduled_for' => 'date et heure',
            'duration_minutes' => 'durée',
        ]);

        $appointment = $patient->appointments()->create($data + [
            'status' => 'scheduled',
            'created_by' => $request->user()->id,
        ]);

        // Confirmation immédiate puis rappel programmé la veille (§36).
        $notifications->appointmentScheduled($appointment);

        if ($appointment->reminder_enabled) {
            $notifications->appointmentReminder($appointment);
        }

        return back()->with('success', 'Rendez-vous '.$appointment->appointment_number.' programmé.');
    }

    public function show(Appointment $appointment): View
    {
        $this->authorize('view', $appointment);

        $appointment->load([
            'patient:id,patient_number,first_name,last_name,phone,birth_date,sex',
            'doctor:id,name,first_name,last_name,title',
            'service:id,name',
        ]);

        return view('dme::appointments.show', ['appointment' => $appointment]);
    }

    public function updateStatus(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->authorize('update', $appointment);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Appointment::STATUSES))],
        ]);

        $appointment->update($data);

        return back()->with('success', 'Statut du rendez-vous mis à jour.');
    }

    private function anchorDate(Request $request): Carbon
    {
        $date = $request->string('date')->toString();

        return $date !== '' ? Carbon::parse($date) : Carbon::today();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(string $view, Carbon $anchor): array
    {
        return match ($view) {
            'day' => [$anchor->copy()->startOfDay(), $anchor->copy()->endOfDay()],
            'month' => [$anchor->copy()->startOfMonth()->startOfWeek(), $anchor->copy()->endOfMonth()->endOfWeek()],
            default => [$anchor->copy()->startOfWeek(), $anchor->copy()->endOfWeek()],
        };
    }
}
