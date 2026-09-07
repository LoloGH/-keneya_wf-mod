<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

/**
 * Journal d'audit (addendum v2, point 7).
 *
 * Lecture seule, sans exception : le composant n'expose aucune action de
 * modification ni de suppression, meme pour l'admin — un journal que l'on peut
 * retoucher ne prouve rien.
 */
class ActivityLogViewer extends Component
{
    use WithPagination;

    public ?int $causerId = null;

    public string $event = '';

    public string $from = '';

    public string $to = '';

    public function updatedCauserId(): void
    {
        $this->resetPage();
    }

    public function updatedEvent(): void
    {
        $this->resetPage();
    }

    public function updatedFrom(): void
    {
        $this->resetPage();
    }

    public function updatedTo(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['causerId', 'event', 'from', 'to']);
        $this->resetPage();
    }

    public function render(): View
    {
        $activities = Activity::query()
            ->with('causer')
            // Le journal de WorkFlow et celui du module DME, dans une seule
            // liste : c'est le meme etablissement et le meme personnel.
            ->whereIn('log_name', Audit::LOG_NAMES)
            ->when($this->causerId, fn ($query) => $query->where('causer_id', $this->causerId))
            ->when($this->event !== '', fn ($query) => $query->where('event', $this->event))
            ->when($this->from !== '', fn ($query) => $query->whereDate('created_at', '>=', $this->from))
            ->when($this->to !== '', fn ($query) => $query->whereDate('created_at', '<=', $this->to))
            ->orderByDesc('id')
            ->paginate(25);

        // Seuls les utilisateurs ayant reellement produit une trace sont
        // proposes au filtre.
        $causerIds = Activity::query()
            ->whereIn('log_name', Audit::LOG_NAMES)
            ->whereNotNull('causer_id')
            ->distinct()
            ->pluck('causer_id');

        return view('livewire.admin.activity-log-viewer', [
            'activities' => $activities,
            'causers' => User::whereIn('id', $causerIds)->orderBy('name')->get(),
            'events' => Audit::LABELS,
        ]);
    }
}
