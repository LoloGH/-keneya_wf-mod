<?php

namespace App\Livewire\Portal;

use App\Actions\RecordFeedback;
use App\Models\FeedbackEntry;
use App\Models\Patient;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * « Donner votre avis », dans le portail patient (v3.2.8, point 4).
 *
 * Une section du portail existant, et non un second systeme d'acces : le
 * patient y arrive avec le lien et le code qu'il possede deja.
 */
class PatientFeedbackForm extends Component
{
    public int $patientId;

    /** `satisfaction_survey` ou `complaint` : deux formulaires, une table. */
    public string $type = FeedbackEntry::TYPE_SURVEY;

    public ?int $ratingCare = null;

    public ?int $ratingStaff = null;

    public string $content = '';

    public bool $submitted = false;

    public function mount(int $patientId): void
    {
        $this->patientId = $patientId;
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
            // Les notes ne concernent que le sondage ; une reclamation se
            // passe de chiffres, c'est le texte qui compte.
            'ratingCare' => $sondage ? ['required', 'integer', 'min:1', 'max:5'] : ['nullable'],
            'ratingStaff' => $sondage ? ['required', 'integer', 'min:1', 'max:5'] : ['nullable'],
            'content' => $sondage ? ['nullable', 'string', 'max:2000'] : ['required', 'string', 'min:5', 'max:2000'],
        ], attributes: [
            'ratingCare' => 'note sur la prise en charge',
            'ratingStaff' => 'note sur le personnel',
            'content' => 'commentaire',
        ]);

        $action->fromPatient(Patient::findOrFail($this->patientId), $this->type, [
            'rating_care' => $this->ratingCare,
            'rating_staff' => $this->ratingStaff,
            'content' => $this->content ?: null,
        ]);

        $this->reset(['ratingCare', 'ratingStaff', 'content']);
        $this->submitted = true;
    }

    public function render(): View
    {
        return view('livewire.portal.patient-feedback-form');
    }
}
