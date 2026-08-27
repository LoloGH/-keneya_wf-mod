<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\NotifiesUser;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\User;
use App\Services\StaffNotifier;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\On;
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
    use NotifiesUser;

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
            $this->notifySuccess('Creneau mis a jour.');
        } else {
            $schedule = Schedule::create($data);
            $this->notifySuccess('Creneau ajoute.');

            // Un creneau ajoute a la main vaut publication de planning, comme
            // une generation groupee (v3.2.3, point 2).
            app(StaffNotifier::class)->schedulePublished([$schedule]);
        }

        Audit::log(
            Audit::EVENT_SCHEDULE_CHANGED,
            sprintf('Planning du %s modifie pour %s.', $schedule->date->format('d/m/Y'), $schedule->user->name),
            $schedule,
        );

        $this->cancel();
    }

    #[On('plannings-mis-a-jour')]
    public function refreshSchedules(): void
    {
        // Un nouveau rendu suffit : la liste est relue a chaque rendu. La
        // selection est videe, elle porterait sur un ensemble qui a change.
        $this->selected = [];
    }

    /**
     * Creneaux coches pour une suppression groupee.
     *
     * @var array<int, string>
     */
    public array $selected = [];

    /**
     * Coche ou decoche tout ce qui est affiche.
     *
     * Volontairement limite a la page visible : « tout selectionner » qui
     * emporterait aussi des creneaux hors ecran est un piege, pas un raccourci.
     */
    public function toggleAll(): void
    {
        $affiches = $this->visibleSchedules()->pluck('id')->map(fn ($id) => (string) $id)->all();

        $this->selected = count($this->selected) === count($affiches) ? [] : $affiches;
    }

    /**
     * Suppression groupee : une generation produit des dizaines de creneaux,
     * les retirer un par un n'est pas une option praticable.
     */
    public function deleteSelected(): void
    {
        $ids = array_map('intval', $this->selected);

        if ($ids === []) {
            $this->notifyError('Aucun creneau selectionne.');

            return;
        }

        // On ne supprime que ce qui est reellement affiche : une selection
        // gardee en memoire apres un changement de filtre ne doit pas emporter
        // des creneaux que l'admin ne voit plus.
        $creneaux = $this->visibleSchedules()->whereIn('id', $ids);

        if ($creneaux->isEmpty()) {
            $this->notifyError('Aucun creneau selectionne.');

            return;
        }

        $resume = $creneaux
            ->groupBy(fn (Schedule $creneau) => $creneau->user?->name ?? 'Compte supprime')
            ->map(fn ($lignes, $nom) => sprintf('%s (%d)', $nom, $lignes->count()))
            ->implode(', ');

        $supprimes = Schedule::whereIn('id', $creneaux->pluck('id'))->delete();

        Audit::log(
            Audit::EVENT_SCHEDULE_CHANGED,
            sprintf('%d creneau(x) supprimes en une fois : %s.', $supprimes, $resume),
        );

        // Le formulaire en cours d'edition pourrait porter un creneau efface.
        if ($this->editingId && in_array((int) $this->editingId, $ids, true)) {
            $this->cancel();
        }

        $this->selected = [];

        $this->notifySuccess(sprintf('%d creneau(x) supprime(s).', $supprimes));
    }

    public function delete(int $scheduleId): void
    {
        $schedule = Schedule::findOrFail($scheduleId);
        $label = sprintf('%s le %s', $schedule->user->name, $schedule->date->format('d/m/Y'));

        $schedule->delete();

        Audit::log(Audit::EVENT_SCHEDULE_CHANGED, sprintf('Creneau supprime : %s.', $label));

        $this->notifySuccess('Creneau supprime.');
    }

    /**
     * Les creneaux affiches — une seule definition, partagee par le rendu et
     * par la suppression groupee : elles doivent porter exactement sur le meme
     * ensemble.
     *
     * @return Collection<int, Schedule>
     */
    private function visibleSchedules(): Collection
    {
        return Schedule::query()
            ->with(['user', 'service'])
            ->when($this->filterUserId, fn ($query) => $query->where('user_id', $this->filterUserId))
            ->where('date', '>=', today()->subWeek())
            ->orderBy('date')
            ->orderBy('start_time')
            ->limit(100)
            ->get();
    }

    public function render(): View
    {
        // Meme source que la generation groupee : deux listes differentes sur
        // le meme ecran finissaient forcement par diverger — celle-ci oubliait
        // en plus les caissiers.
        $staff = User::staff()->orderBy('name')->get();

        return view('livewire.admin.schedule-manager', [
            'staff' => $staff,
            'services' => Service::orderBy('name')->get(),
            'schedules' => $this->visibleSchedules(),
        ]);
    }
}
