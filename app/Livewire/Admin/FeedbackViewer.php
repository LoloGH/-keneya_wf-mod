<?php

namespace App\Livewire\Admin;

use App\Actions\SendFeedbackInvitation;
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

    /**
     * « Lancer le sondage maintenant » pour un visiteur (v3.2.9, point 3).
     *
     * Sans attendre le delai automatique. Le lien est renvoye meme si un
     * premier est deja parti : c'est un geste volontaire de l'administrateur,
     * et la reponse creera sa propre entree sans ecraser la precedente.
     */
    public function launchVisitorSurvey(int $visitorId, SendFeedbackInvitation $invitation): void
    {
        $visitor = Visitor::findOrFail($visitorId);

        // Une venue, un sondage : si le visiteur a deja repondu, le lien ne lui
        // proposerait plus rien. Sa prochaine venue creera un nouvel
        // enregistrement, donc une nouvelle session.
        if (FeedbackEntry::sondageDejaDeposeParVisiteur($visitor)) {
            $this->notifyError(sprintf(
                '%s a deja donne son avis sur cette venue.',
                $visitor->name,
            ));

            return;
        }

        if (blank($visitor->mobile)) {
            $this->notifyError(sprintf('%s n\'a pas de numero de telephone : le lien ne peut pas partir.', $visitor->name));

            return;
        }

        $invitation->toVisitor($visitor);

        // La marque d'envoi est posee pour que la tache planifiee ne repasse
        // pas derriere avec un second lien automatique.
        if (! $visitor->feedbackLinkSent()) {
            $visitor->forceFill(['feedback_link_sent_at' => now()])->save();
        }

        Audit::log(
            Audit::EVENT_PORTAL_LINK_SENT,
            sprintf('Sondage de satisfaction lance manuellement pour le visiteur %s.', $visitor->visitor_code),
            $visitor,
        );

        $this->notifySuccess(sprintf('Lien du sondage mis en file pour %s.', $visitor->name));
    }

    public function render(): View
    {
        $entries = FeedbackEntry::query()
            ->with(['patient', 'visitor.service', 'service', 'handledBy', 'submittedBy', 'resolvedBy', 'surveyRatings'])
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
            // Les visiteurs recents, pour un declenchement a la demande.
            'recentVisitors' => Visitor::query()
                ->whereNotNull('mobile')->where('mobile', '!=', '')
                ->orderByDesc('id')->limit(10)->get(),
            'visitorsWithoutMobile' => Visitor::query()
                ->whereNull('mobile')
                ->whereNotNull('feedback_link_sent_at')
                ->count(),
        ]);
    }
}
