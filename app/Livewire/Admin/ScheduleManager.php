<?php

namespace App\Livewire\Admin;

use App\Models\Schedule;
use App\Models\Service;
use App\Models\User;
use App\Support\Audit;
use App\Support\Roles;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Gestion complete des plannings (addendum v2, point 8) : creation,
 * modification, suppression — exclusivement dans /admin.
 *
 * Chaque membre du personnel ne voit que le sien, en lecture seule, dans sa
 * propre interface.
 */
class ScheduleManager extends Component
{
    public ?int $editingId = null;

    public ?int $user_id = null;

    public string $date = '';

    public string $start_time = '08:00';

    public string $end_time = '14:00';

    public ?int $service_id = null;

    /** Filtre d'affichage de la liste. */
    public ?int $filterUserId = null;

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'user_id' => 'membre du personnel',
            'date' => 'date',
            'start_time' => 'heure de debut',
            'end_time' => 'heure de fin',
            'service_id' => 'service',
        ];
    }

    public function edit(int $scheduleId): void
    {
        $schedule = Schedule::findOrFail($scheduleId);

        $this->editingId = $schedule->getKey();
        $this->user_id = $schedule->user_id;
        $this->date = $schedule->date->format('Y-m-d');
        $this->start_time = substr((string) $schedule->start_time, 0, 5);
        $this->end_time = substr((string) $schedule->end_time, 0, 5);
        $this->service_id = $schedule->service_id;
        $this->resetValidation();
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'user_id', 'date', 'service_id']);
        $this->start_time = '08:00';
        $this->end_time = '14:00';
        $this->resetValidation();
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editingId) {
            $schedule = Schedule::findOrFail($this->editingId);
            $schedule->update($data);
            session()->flash('admin.status', 'Creneau mis a jour.');
        } else {
            $schedule = Schedule::create($data);
            session()->flash('admin.status', 'Creneau ajoute.');
        }

        Audit::log(
            Audit::EVENT_SCHEDULE_CHANGED,
            sprintf('Planning du %s modifie pour %s.', $schedule->date->format('d/m/Y'), $schedule->user->name),
            $schedule,
        );

        $this->cancel();
    }

    public function delete(int $scheduleId): void
    {
        $schedule = Schedule::findOrFail($scheduleId);
        $label = sprintf('%s le %s', $schedule->user->name, $schedule->date->format('d/m/Y'));

        $schedule->delete();

        Audit::log(Audit::EVENT_SCHEDULE_CHANGED, sprintf('Creneau supprime : %s.', $label));

        session()->flash('admin.status', 'Creneau supprime.');
    }

    public function render(): View
    {
        $staff = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', [Roles::DOCTOR, Roles::RECEPTIONIST]))
            ->orderBy('name')
            ->get();

        return view('livewire.admin.schedule-manager', [
            'staff' => $staff,
            'services' => Service::orderBy('name')->get(),
            'schedules' => Schedule::query()
                ->with(['user', 'service'])
                ->when($this->filterUserId, fn ($query) => $query->where('user_id', $this->filterUserId))
                ->where('date', '>=', today()->subWeek())
                ->orderBy('date')
                ->orderBy('start_time')
                ->limit(100)
                ->get(),
        ]);
    }
}
