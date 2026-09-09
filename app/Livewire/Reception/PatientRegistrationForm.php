<?php

namespace App\Livewire\Reception;

use App\Actions\OpenNewEpisode;
use App\Actions\RegisterPatient;
use App\Actions\SendPortalLink;
use App\Livewire\Concerns\RequiresCapability;
use App\Models\Patient;
use App\Models\Service;
use App\Models\StaffType;
use App\Services\DuplicatePatientFinder;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Enregistrement d'un patient inconnu : cree son identite permanente et son
 * premier passage.
 *
 * Pour un patient deja venu, c'est PatientLookup qu'il faut utiliser : cet
 * ecran cree toujours un nouveau `patient_code`.
 */
class PatientRegistrationForm extends Component
{
    use RequiresCapability;

    public string $name = '';

    public ?int $age = null;

    public string $gender = 'Homme';

    public string $profession = '';

    public string $mobile = '';

    public string $crno = '';

    public ?int $service_id = null;

    public string $reason = '';

    /** Mot de l'accueil au service : « malentendant », « vient de loin »... */
    public string $note = '';

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
     * Dossiers existants qui pourraient etre la meme personne (v3.2.8, point 1).
     *
     * Tant que ce tableau n'est pas vide, `save()` refuse de creer : la
     * receptionniste doit d'abord trancher entre « c'est la meme personne » et
     * « c'est quelqu'un d'autre ».
     *
     * @var array<int, array{id: int, name: string, age: int, patient_code: string, mobile: string, last_visit: ?string}>
     */
    public array $duplicateCandidates = [];

    /**
     * Passe outre la detection : positionne uniquement par le bouton « c'est
     * une personne differente », jamais par defaut.
     */
    public bool $forceCreation = false;

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'age' => ['required', 'integer', 'min:0', 'max:130'],
            'gender' => ['required', 'in:Homme,Femme'],
            'profession' => ['nullable', 'string', 'max:120'],
            'mobile' => ['required', 'string', 'max:30'],
            'crno' => ['nullable', 'string', 'max:20'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'reason' => ['nullable', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:1000'],
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
            'profession' => 'profession',
            'mobile' => 'telephone',
            'crno' => 'numero de dossier papier',
            'service_id' => 'service',
            'reason' => 'motif',
            'note' => 'note',
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

    /**
     * Le formulaire est desormais atteignable depuis /staff/{slug}
     * (v3.3.1), ou la capacite decide de tout. Masquer la section ne
     * suffit pas : un composant Livewire s'appelle sans passer par le
     * menu.
     */
    public function save(RegisterPatient $register, DuplicatePatientFinder $finder): void
    {
        $this->assertCapability(StaffType::CAP_REGISTER_PATIENT);

        $data = $this->validate();

        // Verification prealable (v3.2.8, point 1) : rien ne l'assurait, et la
        // meme personne pouvait repartir avec un second `patient_code`.
        if (! $this->forceCreation) {
            $candidats = $finder->search($data['mobile'], $data['name'], $data['age']);

            if ($candidats->isNotEmpty()) {
                $this->duplicateCandidates = $candidats
                    ->map(fn (Patient $patient): array => $this->resume($patient))
                    ->all();

                return;
            }
        }

        $visit = $register->execute($data, $data['companions'] ?? []);

        // Creation forcee malgre une correspondance : on ne l'empeche pas, la
        // receptionniste a la personne devant elle, mais on trace qui a decide
        // quoi, et face a quel dossier propose.
        if ($this->forceCreation && $this->duplicateCandidates !== []) {
            Audit::log(
                Audit::EVENT_DUPLICATE_OVERRIDDEN,
                sprintf(
                    'Dossier %s cree pour %s malgre %d dossier(s) existant(s) proposes : %s.',
                    $visit->patient->patient_code,
                    $visit->patient->name,
                    count($this->duplicateCandidates),
                    implode(', ', array_column($this->duplicateCandidates, 'patient_code')),
                ),
                $visit->patient,
                ['dossiers_proposes' => $this->duplicateCandidates],
            );
        }

        $this->duplicateCandidates = [];
        $this->forceCreation = false;

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

        $this->reinitialiserSaisie();

        $this->dispatch('patient-enregistre');

        session()->flash('reception.success', sprintf(
            'Patient %s enregistre : dossier %s, ticket n° %d au service %s.',
            $visit->patient->name,
            $visit->patient->patient_code,
            $visit->token,
            $visit->service->name,
        ));
    }

    /**
     * « C'est la meme personne » : on ouvre un nouvel episode sur le dossier
     * existant, par le mecanisme de reprise deja en place, aucun second
     * `patient_code` n'est genere.
     */
    public function openEpisodeForExisting(int $patientId, OpenNewEpisode $action): void
    {
        $this->validateOnly('service_id');

        $patient = Patient::findOrFail($patientId);

        $visit = $action->execute($patient, (int) $this->service_id, $this->reason ?: null);

        $this->lastRegistered = [
            'patient_id' => $patient->getKey(),
            'visit_id' => $visit->getKey(),
            'patient_code' => $patient->patient_code,
            'name' => $patient->name,
            'service' => $visit->service->name,
            'pending' => $visit->pendingNextService?->name,
            'token' => $visit->token,
            'access_code' => $patient->access_code,
        ];

        $this->reinitialiserSaisie();
        $this->dispatch('patient-enregistre');

        session()->flash('reception.success', sprintf(
            'Nouvel episode ouvert sur le dossier existant %s (%s) : ticket n° %d au service %s.',
            $patient->patient_code,
            $patient->name,
            $visit->token,
            $visit->service->name,
        ));
    }

    /**
     * « C'est une personne differente » : la creation reprend son cours, et la
     * decision sera journalisee par `save()`.
     */
    public function createAnyway(RegisterPatient $register, DuplicatePatientFinder $finder): void
    {
        $this->forceCreation = true;

        $this->save($register, $finder);
    }

    /** Retour a la saisie sans rien creer. */
    public function dismissDuplicates(): void
    {
        $this->duplicateCandidates = [];
        $this->forceCreation = false;
    }

    /**
     * Envoi du lien « mes documents », sur demande explicite : jamais
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

    private function reinitialiserSaisie(): void
    {
        $this->reset(['name', 'age', 'profession', 'mobile', 'crno', 'reason', 'note', 'companions']);
        $this->gender = 'Homme';
        $this->duplicateCandidates = [];
        $this->forceCreation = false;
    }

    /**
     * De quoi reconnaitre la personne sans ouvrir son dossier : c'est ce que
     * la receptionniste lit pour trancher.
     *
     * @return array{id: int, name: string, age: int, patient_code: string, mobile: string, last_visit: ?string}
     */
    private function resume(Patient $patient): array
    {
        return [
            'id' => $patient->getKey(),
            'name' => $patient->name,
            'age' => (int) $patient->age,
            'patient_code' => $patient->patient_code,
            'mobile' => (string) $patient->mobile,
            'profession' => $patient->profession,
            'last_visit' => $patient->visits()->latest('opened_at')->first()?->opened_at?->format('d/m/Y'),
        ];
    }

    public function render(): View
    {
        return view('livewire.reception.patient-registration-form', [
            'services' => Service::careServices()->orderBy('name')->get(),
        ]);
    }
}
