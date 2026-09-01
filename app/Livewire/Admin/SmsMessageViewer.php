<?php

namespace App\Livewire\Admin;

use App\Models\SmsMessage;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Section « SMS » de l'administration (v3.2.8).
 *
 * Lecture seule, comme le journal d'audit : on constate, on ne retouche pas.
 * Ce qui manquait n'etait pas la donnee — les echecs etaient deja ecrits dans
 * `storage/logs` — mais un endroit ou quelqu'un les regarde. D'ou le compteur
 * d'echecs en tete : il se voit sans ouvrir la liste.
 */
class SmsMessageViewer extends Component
{
    use WithPagination;

    public string $status = '';

    public string $search = '';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /** Raccourci depuis le compteur : « 4 echecs » mene a la liste filtree. */
    public function showFailures(): void
    {
        $this->status = SmsMessage::STATUS_FAILED;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['status', 'search']);
        $this->resetPage();
    }

    public function render(): View
    {
        $recherche = trim($this->search);

        $messages = SmsMessage::query()
            ->with('related')
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($recherche !== '', fn ($query) => $query->where(function ($query) use ($recherche): void {
                $query->where('to', 'like', '%'.$recherche.'%')
                    ->orWhere('body', 'like', '%'.$recherche.'%');
            }))
            ->orderByDesc('id')
            ->paginate(25);

        return view('livewire.admin.sms-message-viewer', [
            'messages' => $messages,
            'statuses' => SmsMessage::STATUS_LABELS,
            'failureCount' => SmsMessage::recentFailureCount(),
        ]);
    }
}
