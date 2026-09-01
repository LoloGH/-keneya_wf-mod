<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\NotifiesUser;
use App\Models\FeedbackEntry;
use App\Models\Setting;
use App\Models\Visitor;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * « Retours et incidents » (v3.2.8, point 4).
 *
 * Les trois natures de retour dans une meme liste, filtrable. Chaque entree
 * porte le contexte d'accueil de la personne — nom, telephone, service, date de
 * passage : sans lui, il faudrait croiser a la main avec une autre page pour
 * savoir de qui l'on parle, et l'entree resterait inexploitable.
 */
class FeedbackViewer extends Component
{
    use NotifiesUser;
    use WithPagination;

    public string $type = '';

    public string $status = '';

    /** Entree en cours de traitement, et sa note de resolution. */
    public ?int $resolvingId = null;

    public string $resolutionNotes = '';

    public string $targetStatus = FeedbackEntry::STATUS_RESOLVED;

    /** Delai avant l'envoi du lien aux visiteurs, en heures. */
    public ?int $delayHours = null;

    public function mount(): void
    {
        $this->delayHours = (int) Setting::get(
            Setting::VISITOR_FEEDBACK_DELAY_HOURS,
            (string) Setting::DEFAULT_VISITOR_FEEDBACK_DELAY_HOURS,
        );
    }

    public function updatedType(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['type', 'status']);
        $this->resetPage();
    }

    public function startResolution(int $entryId, string $status = FeedbackEntry::STATUS_RESOLVED): void
    {
        $this->resolvingId = $entryId;
        $this->targetStatus = in_array($status, [FeedbackEntry::STATUS_REVIEWED, FeedbackEntry::STATUS_RESOLVED], true)
            ? $status
            : FeedbackEntry::STATUS_RESOLVED;
        $this->resolutionNotes = '';
        $this->resetValidation();
    }

    public function cancelResolution(): void
    {
        $this->reset(['resolvingId', 'resolutionNotes']);
        $this->targetStatus = FeedbackEntry::STATUS_RESOLVED;
        $this->resetValidation();
    }

    /**
     * Changer de statut exige de dire comment l'entree a ete traitee : un
     * passage silencieux a « resolu » ne prouve rien et n'apprend rien a la
     * personne qui relira le dossier dans six mois.
     */
    public function resolve(): void
    {
        $this->validate([
            'resolvingId' => ['required', 'integer', 'exists:feedback_entries,id'],
            'resolutionNotes' => ['required', 'string', 'min:5', 'max:2000'],
        ], attributes: [
            'resolvingId' => 'entree',
            'resolutionNotes' => 'note de resolution',
        ]);

        $entry = FeedbackEntry::findOrFail($this->resolvingId);

        $entry->update([
            'status' => $this->targetStatus,
            'resolution_notes' => $this->resolutionNotes,
            'resolved_by_user_id' => Auth::id(),
            'resolved_at' => now(),
        ]);

        Audit::log(
            Audit::EVENT_FEEDBACK_RESOLVED,
            sprintf(
                '%s de %s passe a « %s » : %s',
                $entry->typeLabel(),
                $entry->authorName(),
                $entry->statusLabel(),
                $this->resolutionNotes,
            ),
            $entry,
        );

        $this->notifySuccess('Retour traite.');
        $this->cancelResolution();
    }

    public function saveDelay(): void
    {
        $this->validate(
            ['delayHours' => ['required', 'integer', 'min:0', 'max:168']],
            attributes: ['delayHours' => "delai d'envoi"],
        );

        Setting::put(Setting::VISITOR_FEEDBACK_DELAY_HOURS, (string) $this->delayHours);

        Audit::log(
            Audit::EVENT_UPDATED,
            sprintf('Delai d\'envoi du lien de retour aux visiteurs fixe a %d heure(s).', $this->delayHours),
        );

        $this->notifySuccess('Delai enregistre.');
    }

    public function render(): View
    {
        $entries = FeedbackEntry::query()
            ->with(['patient', 'visitor.service', 'service', 'handledBy', 'submittedBy', 'resolvedBy'])
            ->when($this->type !== '', fn ($query) => $query->where('type', $this->type))
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->orderByDesc('id')
            ->paginate(20);

        return view('livewire.admin.feedback-viewer', [
            'entries' => $entries,
            'types' => FeedbackEntry::TYPE_LABELS,
            'statuses' => FeedbackEntry::STATUS_LABELS,
            'openCount' => FeedbackEntry::query()->open()->count(),
            // Le cas qui serait invisible autrement : un visiteur sans numero
            // ne recevra jamais de lien, et rien ne le dirait.
            'visitorsWithoutMobile' => Visitor::query()
                ->whereNull('mobile')
                ->whereNotNull('feedback_link_sent_at')
                ->count(),
        ]);
    }
}
