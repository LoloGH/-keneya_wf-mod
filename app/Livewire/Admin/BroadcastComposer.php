<?php

namespace App\Livewire\Admin;

use App\Actions\SendBroadcast;
use App\Livewire\Concerns\NotifiesUser;
use App\Models\BroadcastMessage;
use App\Models\Pathology;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Services\BroadcastRecipients;
use App\Support\BroadcastTarget;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Envoi groupe de SMS (v3.2.9, point 1).
 *
 * L'ecran est construit autour d'une seule idee : on ne s'adresse pas a des
 * centaines de patients sur un clic. Le nombre exact de destinataires est
 * calcule et affiche avant l'envoi, et il faut une seconde action deliberee
 * pour que la diffusion parte.
 */
class BroadcastComposer extends Component
{
    use NotifiesUser;

    public string $content = '';

    public string $targetType = BroadcastMessage::TARGET_PATIENT_GROUP;

    /** Recherche du destinataire nommement designe (personnel ou patient). */
    public string $search = '';

    public ?int $staffUserId = null;

    public ?int $patientId = null;

    public ?int $serviceId = null;

    public ?int $pathologyId = null;

    /**
     * Nombre de destinataires calcule, et donc confirme. Nul tant que
     * l'apercu n'a pas ete demande : c'est ce qui empeche d'envoyer sans
     * avoir regarde.
     */
    public ?int $previewCount = null;

    public function updated(string $champ): void
    {
        // Toute modification de la cible ou du message invalide l'apercu :
        // confirmer un nombre calcule pour d'autres filtres serait le pire
        // des deux mondes.
        if ($champ !== 'previewCount') {
            $this->previewCount = null;
        }

        if ($champ === 'targetType') {
            $this->reset(['search', 'staffUserId', 'patientId', 'serviceId', 'pathologyId']);
        }
    }

    public function selectStaff(int $userId): void
    {
        $this->staffUserId = $userId;
        $this->previewCount = null;
    }

    public function selectPatient(int $patientId): void
    {
        $this->patientId = $patientId;
        $this->previewCount = null;
    }

    /** Calcule le nombre de destinataires, sans rien envoyer. */
    public function preview(BroadcastRecipients $recipients): void
    {
        $this->validate($this->rules(), attributes: $this->validationAttributes());

        $this->previewCount = $recipients->count($this->target());

        if ($this->previewCount === 0) {
            $this->notifyError('Aucun destinataire joignable ne correspond a cette selection.');
        }
    }

    public function send(SendBroadcast $action): void
    {
        $this->validate($this->rules(), attributes: $this->validationAttributes());

        // Sans apercu prealable, rien ne part : c'est la garantie que
        // l'administrateur a vu combien de personnes il s'apprete a joindre.
        if ($this->previewCount === null) {
            $this->notifyError('Affichez d\'abord le nombre de destinataires avant d\'envoyer.');

            return;
        }

        try {
            $diffusion = $action->execute(Auth::user(), $this->content, $this->target());
        } catch (InvalidArgumentException $e) {
            $this->notifyError($e->getMessage());

            return;
        }

        $this->notifySuccess(sprintf(
            '%d SMS mis en file (%s). Leur acheminement se suit dans la section « SMS ».',
            $diffusion->recipient_count,
            $diffusion->targetLabel(),
        ));

        $this->reset(['content', 'search', 'staffUserId', 'patientId', 'serviceId', 'pathologyId', 'previewCount']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            // 480 caracteres : quatre segments de SMS. Au-dela, le message
            // coute cher a l'envoi et se lit mal sur un telephone simple.
            'content' => ['required', 'string', 'min:5', 'max:480'],
            'targetType' => ['required', 'in:'.implode(',', array_keys(BroadcastMessage::TARGET_LABELS))],
            'staffUserId' => [
                $this->targetType === BroadcastMessage::TARGET_STAFF ? 'required' : 'nullable',
                'integer', 'exists:users,id',
            ],
            'patientId' => [
                $this->targetType === BroadcastMessage::TARGET_SINGLE_PATIENT ? 'required' : 'nullable',
                'integer', 'exists:patients,id',
            ],
            'serviceId' => [
                $this->targetType === BroadcastMessage::TARGET_PATIENT_GROUP ? 'required' : 'nullable',
                'integer', 'exists:services,id',
            ],
            'pathologyId' => ['nullable', 'integer', 'exists:pathologies,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'content' => 'message',
            'targetType' => 'type de destinataires',
            'staffUserId' => 'membre du personnel',
            'patientId' => 'patient',
            'serviceId' => 'service',
            'pathologyId' => 'pathologie',
        ];
    }

    private function target(): BroadcastTarget
    {
        return new BroadcastTarget(
            type: $this->targetType,
            staffUserId: $this->staffUserId,
            patientId: $this->patientId,
            serviceId: $this->serviceId,
            pathologyId: $this->pathologyId,
        );
    }

    public function render(): View
    {
        $recherche = trim($this->search);

        return view('livewire.admin.broadcast-composer', [
            'targets' => BroadcastMessage::TARGET_LABELS,
            'services' => Service::careServices()->orderBy('name')->get(),
            'pathologies' => Pathology::orderBy('name')->get(),
            'staffResults' => $this->targetType === BroadcastMessage::TARGET_STAFF && $recherche !== ''
                ? User::where('name', 'like', '%'.$recherche.'%')->orderBy('name')->limit(8)->get()
                : collect(),
            'patientResults' => $this->targetType === BroadcastMessage::TARGET_SINGLE_PATIENT && $recherche !== ''
                ? Patient::where(fn ($query) => $query
                    ->where('name', 'like', '%'.$recherche.'%')
                    ->orWhere('patient_code', 'like', '%'.$recherche.'%'))
                    ->orderBy('name')->limit(8)->get()
                : collect(),
            'selectedStaff' => $this->staffUserId ? User::find($this->staffUserId) : null,
            'selectedPatient' => $this->patientId ? Patient::find($this->patientId) : null,
            'recent' => BroadcastMessage::with('sentBy')->orderByDesc('id')->limit(10)->get(),
        ]);
    }
}
