<?php

namespace App\Livewire\Reception;

use App\Actions\RegisterPatient;
use App\Actions\SendPortalLink;
use App\Models\Patient;
use App\Models\Service;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Enregistrement d'un patient inconnu : cree son identite permanente et son
 * premier passage.
 *
 * Pour un patient deja venu, c'est PatientLookup qu'il faut utiliser — cet
 * ecran cree toujours un nouveau `patient_code`.
 */
class PatientRegistrationForm extends Component
{
    public string $name = '';

    public ?int $age = null;

    public string $gender = 'Homme';

    public string $mobile = '';

    public string $crno = '';

    public ?int $service_id = null;

    public string $reason = '';

    /**
     * Accompagnateurs saisis avec le patient. Facultatif : un patient peut en
     * avoir zero, un ou plusieurs.
     *
     * @var array<int, array{name: string, phone: string, relation: string}>
     */
    public array $companions = [];

    /** Dernier passage ouvert, affiche pour lecture du code et du ticket. */
    public ?array $lastRegistered = null;

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'age' => ['required', 'integer', 'min:0', 'max:130'],
            'gender' => ['required', 'in:Homme,Femme'],
            'mobile' => ['required', 'string', 'max:30'],
            'crno' => ['nullable', 'string', 'max:20'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'reason' => ['nullable', 'string', 'max:500'],
            'companions' => ['array', 'max:5'],
            'companions.*.name' => ['nullable', 'string', 'max:255'],
            'companions.*.phone' => ['nullable', 'string', 'max:30'],
            'companions.*.relation' => ['nullable', 'string', 'max:60'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => 'nom du patient',
            'age' => 'age',
            'gender' => 'sexe',
            'mobile' => 'telephone',
            'crno' => 'numero de dossier papier',
            'service_id' => 'service',
            'reason' => 'motif',
        ];
    }

    public function addCompanion(): void
    {
        if (count($this->companions) >= 5) {
            return;
        }

        $this->companions[] = ['name' => '', 'phone' => '', 'relation' => ''];
    }

    public function removeCompanion(int $index): void
    {
        unset($this->companions[$index]);
        $this->companions = array_values($this->companions);
    }

    public function save(RegisterPatient $register): void
    {
        $data = $this->validate();

        $visit = $register->execute($data, $data['companions'] ?? []);

        $this->lastRegistered = [
            'patient_id' => $visit->patient->getKey(),
            'visit_id' => $visit->getKey(),
            'patient_code' => $visit->patient->patient_code,
            'name' => $visit->patient->name,
            'service' => $visit->service->name,
            'pending' => $visit->pendingNextService?->name,
            'token' => $visit->token,
            // Communique de vive voix ET imprime sur le ticket.
            'access_code' => $visit->patient->access_code,
        ];

        $this->reset(['name', 'age', 'mobile', 'crno', 'reason', 'companions']);
        $this->gender = 'Homme';

        $this->dispatch('patient-enregistre');

        session()->flash('reception.success', sprintf(
            'Patient %s enregistre — dossier %s, ticket n° %d au service %s.',
            $visit->patient->name,
            $visit->patient->patient_code,
            $visit->token,
            $visit->service->name,
        ));
    }

    /**
     * Envoi du lien « mes documents », sur demande explicite — jamais
     * automatiquement a chaque evenement du dossier.
     */
    public function sendPortalLink(SendPortalLink $action): void
    {
        if (! $this->lastRegistered) {
            return;
        }

        $patient = Patient::findOrFail($this->lastRegistered['patient_id']);

        try {
            $action->execute($patient);
        } catch (\InvalidArgumentException $e) {
            session()->flash('reception.error', $e->getMessage());

            return;
        }

        session()->flash('reception.success', sprintf('Lien envoye a %s.', $patient->mobile));
    }

    public function render(): View
    {
        return view('livewire.reception.patient-registration-form', [
            'services' => Service::careServices()->orderBy('name')->get(),
        ]);
    }
}
