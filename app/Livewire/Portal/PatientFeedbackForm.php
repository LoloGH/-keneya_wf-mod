<?php

namespace App\Livewire\Portal;

use App\Actions\RecordFeedback;
use App\Models\FeedbackEntry;
use App\Models\Patient;
use App\Services\FeedbackJourney;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
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

    /**
     * Une note par etape du parcours (v3.2.9, point 3), indexee par la cle de
     * l'etape. Remplace la note unique « personnel » : elle melait dans un
     * seul chiffre l'agent d'accueil, le caissier et le medecin.
     *
     * @var array<string, int|string|null>
     */
    public array $stepRatings = [];

    /**
     * @var array<string, string>
     */
    public array $stepComments = [];

    public string $content = '';

    public bool $submitted = false;

    public function mount(int $patientId): void
    {
        $this->patientId = $patientId;
    }

    /**
     * Les etapes reellement traversees, telles que le dossier les porte a cet
     * instant. Un sondage lance avant la cloture n'en montre donc qu'une
     * partie — c'est le comportement voulu, pas un cas a part.
     *
     * @return Collection<int, array{key: string, label: string, user_id: ?int, service_id: ?int}>
     */
    private function steps(): Collection
    {
        $patient = Patient::find($this->patientId);

        return app(FeedbackJourney::class)->steps(
            $patient?->visits()->orderByDesc('opened_at')->first(),
        );
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
            // Chaque etape se note de 1 a 5, mais aucune n'est obligatoire :
            // un patient qui n'a rien a dire d'un guichet doit pouvoir
            // envoyer son avis quand meme.
            'stepRatings.*' => ['nullable', 'integer', 'min:1', 'max:5'],
            'stepComments.*' => ['nullable', 'string', 'max:500'],
            'content' => $sondage ? ['nullable', 'string', 'max:2000'] : ['required', 'string', 'min:5', 'max:2000'],
        ], attributes: [
            'ratingCare' => 'note sur la prise en charge',
            'content' => 'commentaire',
        ]);

        $action->fromPatient(Patient::findOrFail($this->patientId), $this->type, [
            'rating_care' => $this->ratingCare,
            'content' => $this->content ?: null,
            'steps' => $sondage ? $this->notesParEtape() : [],
        ]);

        $this->reset(['ratingCare', 'stepRatings', 'stepComments', 'content']);
        $this->submitted = true;
    }

    /**
     * Les etapes effectivement notees, rapprochees de leur libelle. Les
     * libelles viennent du dossier et non du formulaire : une valeur postee ne
     * doit pas pouvoir inventer un poste.
     *
     * @return array<int, array{label: string, user_id: ?int, rating: int, comment: ?string}>
     */
    private function notesParEtape(): array
    {
        return $this->steps()
            ->filter(fn (array $etape) => filled($this->stepRatings[$etape['key']] ?? null))
            ->map(fn (array $etape) => [
                'label' => $etape['label'],
                'user_id' => $etape['user_id'],
                'rating' => (int) $this->stepRatings[$etape['key']],
                'comment' => ($this->stepComments[$etape['key']] ?? '') ?: null,
            ])
            ->values()
            ->all();
    }

    public function render(): View
    {
        return view('livewire.portal.patient-feedback-form', [
            'steps' => $this->steps(),
        ]);
    }
}
