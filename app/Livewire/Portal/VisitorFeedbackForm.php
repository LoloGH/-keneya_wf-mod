<?php

namespace App\Livewire\Portal;

use App\Actions\RecordFeedback;
use App\Models\FeedbackEntry;
use App\Models\Visitor;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Page de retour du visiteur (v3.2.8, point 4).
 *
 * Le jeton de l'URL fait office d'adresse, sans code supplementaire : un
 * visiteur n'a pas de dossier medical a proteger, et rien de medical ne
 * s'affiche ici. La route est neanmoins limitee en debit, un identifiant long
 * n'etant pas une raison de laisser essayer indefiniment.
 */
class VisitorFeedbackForm extends Component
{
    public string $token;

    public string $type = FeedbackEntry::TYPE_SURVEY;

    public ?int $ratingCare = null;

    public ?int $ratingStaff = null;

    public string $content = '';

    public bool $submitted = false;

    public function mount(string $token): void
    {
        $this->token = $token;

        if ($this->sondageDejaDonne()) {
            $this->type = FeedbackEntry::TYPE_COMPLAINT;
        }
    }

    /**
     * Le sondage de cette venue est-il deja donne ?
     *
     * Pas de colonne supplementaire ici : chaque venue d'un visiteur cree son
     * propre enregistrement, avec son propre jeton de sondage. L'enregistrement
     * EST la session, et le lien du jour ne rouvre pas celui d'hier.
     */
    public function sondageDejaDonne(): bool
    {
        return FeedbackEntry::sondageDejaDeposeParVisiteur($this->visitor());
    }

    public function selectType(string $type): void
    {
        $sondage = $type !== FeedbackEntry::TYPE_COMPLAINT;

        if ($sondage && $this->sondageDejaDonne()) {
            return;
        }

        $this->type = $sondage ? FeedbackEntry::TYPE_SURVEY : FeedbackEntry::TYPE_COMPLAINT;

        $this->submitted = false;
        $this->resetValidation();
    }

    public function submit(RecordFeedback $action): void
    {
        $sondage = $this->type === FeedbackEntry::TYPE_SURVEY;

        // Verifie avant d'ecrire : le lien du sondage est envoye par SMS et
        // reste cliquable, y compris apres reponse.
        if ($sondage && $this->sondageDejaDonne()) {
            $this->type = FeedbackEntry::TYPE_COMPLAINT;

            return;
        }

        $this->validate([
            'ratingCare' => $sondage ? ['required', 'integer', 'min:1', 'max:5'] : ['nullable'],
            'ratingStaff' => $sondage ? ['required', 'integer', 'min:1', 'max:5'] : ['nullable'],
            'content' => $sondage ? ['nullable', 'string', 'max:2000'] : ['required', 'string', 'min:5', 'max:2000'],
        ], attributes: [
            'ratingCare' => 'note sur la prise en charge',
            'ratingStaff' => 'note sur le personnel',
            'content' => 'commentaire',
        ]);

        $action->fromVisitor($this->visitor(), $this->type, [
            'rating_care' => $this->ratingCare,
            'rating_staff' => $this->ratingStaff,
            'content' => $this->content ?: null,
        ]);

        $this->reset(['ratingCare', 'ratingStaff', 'content']);
        $this->submitted = true;

        if ($sondage) {
            $this->type = FeedbackEntry::TYPE_COMPLAINT;
        }
    }

    private function visitor(): Visitor
    {
        return Visitor::where('feedback_token', $this->token)->firstOrFail();
    }

    public function render(): View
    {
        return view('livewire.portal.visitor-feedback-form', [
            'visitor' => $this->visitor(),
            'sondageDonne' => $this->sondageDejaDonne(),
        ]);
    }
}
