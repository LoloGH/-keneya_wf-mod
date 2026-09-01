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
    }

    public function selectType(string $type): void
    {
        $this->type = $type === FeedbackEntry::TYPE_COMPLAINT
            ? FeedbackEntry::TYPE_COMPLAINT
            : FeedbackEntry::TYPE_SURVEY;

        $this->submitted = false;
        $this->resetValidation();
    }

    public function submit(RecordFeedback $action): void
    {
        $sondage = $this->type === FeedbackEntry::TYPE_SURVEY;

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
    }

    private function visitor(): Visitor
    {
        return Visitor::where('feedback_token', $this->token)->firstOrFail();
    }

    public function render(): View
    {
        return view('livewire.portal.visitor-feedback-form', [
            'visitor' => $this->visitor(),
        ]);
    }
}
