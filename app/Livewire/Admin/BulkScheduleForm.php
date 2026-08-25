<?php

namespace App\Livewire\Admin;

use App\Actions\BulkCreateSchedule;
use App\Models\Service;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Creation groupee de creneaux (v3.2, point 2).
 *
 * Complete le formulaire jour par jour, qui reste en place pour les
 * ajustements ponctuels.
 */
class BulkScheduleForm extends Component
{
    public ?int $user_id = null;

    public string $from = '';

    public string $to = '';

    /** @var array<int, string> jours ISO coches, 1 = lundi … 7 = dimanche */
    public array $weekdays = ['1', '2', '3', '4', '5'];

    public string $start_time = '08:00';

    public string $end_time = '14:00';

    public ?int $service_id = null;

    /**
     * @var array<int, string>
     */
    public const WEEKDAY_LABELS = [
        1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi',
        5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche',
    ];

    public function mount(): void
    {
        $this->from = today()->toDateString();
        $this->to = today()->addMonth()->toDateString();
    }

    public function generate(BulkCreateSchedule $action): void
    {
        $data = $this->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'weekdays' => ['required', 'array', 'min:1'],
            'weekdays.*' => ['integer', 'between:1,7'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
        ], attributes: [
            'user_id' => 'membre du personnel',
            'from' => 'date de debut',
            'to' => 'date de fin',
            'weekdays' => 'jours de la semaine',
            'start_time' => 'heure de debut',
            'end_time' => 'heure de fin',
        ]);

        try {
            $crees = $action->execute(
                user: User::findOrFail($data['user_id']),
                from: Carbon::parse($data['from']),
                to: Carbon::parse($data['to']),
                weekdays: $data['weekdays'],
                startTime: $data['start_time'],
                endTime: $data['end_time'],
                serviceId: $data['service_id'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['weekdays' => $e->getMessage()]);
        }

        session()->flash('admin.status', $crees > 0
            ? sprintf('%d creneau(x) genere(s).', $crees)
            : 'Aucun creneau a generer : ils existaient deja sur cette periode.');

        $this->dispatch('plannings-mis-a-jour');
    }

    public function render(): View
    {
        return view('livewire.admin.bulk-schedule-form', [
            'staff' => User::query()
                ->whereHas('roles', fn ($q) => $q->whereIn('name', [Roles::DOCTOR, Roles::RECEPTIONIST, Roles::CASHIER]))
                ->orderBy('name')
                ->get(),
            'services' => Service::orderBy('name')->get(),
            'weekdayLabels' => self::WEEKDAY_LABELS,
        ]);
    }
}
