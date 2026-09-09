<?php

namespace App\Livewire\Shared;

use App\Actions\RecordFeedback;
use App\Livewire\Concerns\NotifiesUser;
use App\Models\FeedbackEntry;
use App\Models\Patient;
use App\Models\Service;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Depot d'un constat par un membre du personnel (v3.2.8, point 4).
 *
 * Present dans les quatre interfaces metier, sous la meme forme : un constat se
 * redige la ou l'on travaille, pas dans une cinquieme application. Le patient
 * concerne est facultatif : un constat peut porter sur une observation
 * generale, sans viser personne.
 */
class IncidentReportForm extends Component
{
    use NotifiesUser;

    /** Cle de session utilisee pour le message de confirmation. */
    public string $flashKey = 'admin.status';

    public string $patientCode = '';

    public ?int $serviceId = null;

    public string $content = '';

    public function mount(string $flashKey = 'admin.status'): void
    {
        $this->flashKey = $flashKey;
    }

    public function submit(RecordFeedback $action): void
    {
        $this->validate([
            'patientCode' => ['nullable', 'string', 'max:40'],
            'serviceId' => ['nullable', 'integer', 'exists:services,id'],
            'content' => ['required', 'string', 'min:10', 'max:2000'],
        ], attributes: [
            'patientCode' => 'dossier concerne',
            'serviceId' => 'service',
            'content' => 'constat',
        ]);

        // Un code de dossier saisi mais introuvable n'est pas une raison de
        // perdre le constat : on l'enregistre sans rattachement et on le dit.
        $patient = filled($this->patientCode)
            ? Patient::where('patient_code', trim($this->patientCode))->first()
            : null;

        $action->fromStaff(Auth::user(), [
            'patient_id' => $patient?->getKey(),
            'service_id' => $this->serviceId,
            'content' => $this->content,
        ]);

        $this->reset(['patientCode', 'serviceId', 'content']);

        $this->notifySuccess(
            $patient || blank($this->patientCode)
                ? 'Constat transmis a la direction.'
                : 'Constat transmis, mais aucun dossier ne porte ce code : il est enregistre sans rattachement.',
            $this->flashKey,
        );
    }

    public function render(): View
    {
        return view('livewire.shared.incident-report-form', [
            'services' => Service::orderBy('name')->get(),
            'recents' => FeedbackEntry::query()
                ->where('submitted_by_user_id', Auth::id())
                ->orderByDesc('id')
                ->limit(5)
                ->get(),
        ]);
    }
}
