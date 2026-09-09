<?php

namespace App\Livewire\Reception;

use App\Actions\CorrectPatientIdentity;
use App\Actions\OpenNewEpisode;
use App\Livewire\Concerns\RequiresCapability;
use App\Models\Patient;
use App\Models\Service;
use App\Models\StaffType;
use App\Models\Visit;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Recherche prealable a l'enregistrement (addendum v3).
 *
 * Un patient deja connu ne doit jamais recevoir un second `patient_code` :
 * on retrouve son identite, la receptionniste la confirme visuellement, pour
 * eviter toute confusion entre homonymes, puis on ouvre un nouvel episode
 * sous le meme identifiant.
 */
class PatientLookup extends Component
{
    use RequiresCapability;

    public string $search = '';

    /** Patient retenu, en attente de confirmation par la receptionniste. */
    public ?int $selectedPatientId = null;

    public ?int $serviceId = null;

    public string $reason = '';

    /**
     * Correction en cours, et les cinq champs qu'elle porte. Cinq seulement :
     * ce sont ceux que WorkFlow possede et que l'accueil peut constater. Le
     * `patient_code`, lui, ne change jamais.
     */
    public ?int $correctingPatientId = null;

    public string $correctionName = '';

    public string $correctionMobile = '';

    public string $correctionProfession = '';

    public string $correctionGender = 'Homme';

    public string $correctionIdCardNumber = '';

    public function updatedSearch(): void
    {
        $this->selectedPatientId = null;
        $this->resetValidation();
    }

    public function select(int $patientId): void
    {
        $this->selectedPatientId = $patientId;
        $this->serviceId = null;
        $this->reason = '';
        $this->resetValidation();
    }

    public function cancel(): void
    {
        $this->reset(['selectedPatientId', 'serviceId', 'reason']);
        $this->cancelCorrection();
        $this->resetValidation();
    }

    // ------------------------------------------- Correction de l'identite

    /**
     * Un nom mal orthographie, un numero qui a change, une carte d'identite
     * relevee apres coup : sans correction possible, la seule issue serait
     * d'ouvrir un second dossier pour la meme personne (v3.3.1).
     */
    public function startCorrection(int $patientId): void
    {
        $patient = Patient::findOrFail($patientId);

        $this->correctingPatientId = $patient->getKey();
        $this->correctionName = (string) $patient->name;
        $this->correctionMobile = (string) $patient->mobile;
        $this->correctionProfession = (string) $patient->profession;
        $this->correctionGender = (string) $patient->gender;
        $this->correctionIdCardNumber = (string) $patient->id_card_number;
        $this->resetValidation();
    }

    public function cancelCorrection(): void
    {
        $this->reset([
            'correctingPatientId', 'correctionName', 'correctionMobile',
            'correctionProfession', 'correctionGender', 'correctionIdCardNumber',
        ]);
        $this->resetValidation();
    }

    public function saveCorrection(CorrectPatientIdentity $action): void
    {
        $this->assertCapability(StaffType::CAP_REGISTER_PATIENT);

        $this->validate([
            'correctingPatientId' => ['required', 'integer', 'exists:patients,id'],
            'correctionName' => ['required', 'string', 'max:255'],
            'correctionMobile' => ['required', 'string', 'max:30'],
            'correctionProfession' => ['nullable', 'string', 'max:120'],
            'correctionGender' => ['required', 'in:Homme,Femme'],
            'correctionIdCardNumber' => ['nullable', 'string', 'max:60'],
        ], attributes: [
            'correctionName' => 'nom',
            'correctionMobile' => 'telephone',
            'correctionProfession' => 'profession',
            'correctionGender' => 'sexe',
            'correctionIdCardNumber' => 'numero de la carte d\'identite',
        ]);

        $patient = $action->execute(Patient::findOrFail($this->correctingPatientId), [
            'name' => $this->correctionName,
            'mobile' => $this->correctionMobile,
            'profession' => $this->correctionProfession,
            'gender' => $this->correctionGender,
            'id_card_number' => $this->correctionIdCardNumber,
        ]);

        session()->flash('reception.success', sprintf(
            'Dossier %s corrige. Le numero de dossier, lui, ne change jamais.',
            $patient->patient_code,
        ));

        $this->cancelCorrection();
    }

    public function openEpisode(OpenNewEpisode $action): void
    {
        $this->validate([
            'selectedPatientId' => ['required', 'integer', 'exists:patients,id'],
            'serviceId' => ['required', 'integer', 'exists:services,id'],
            'reason' => ['nullable', 'string', 'max:500'],
        ], attributes: [
            'selectedPatientId' => 'patient',
            'serviceId' => 'service',
            'reason' => 'motif',
        ]);

        $patient = Patient::findOrFail($this->selectedPatientId);

        $visit = $action->execute($patient, (int) $this->serviceId, $this->reason ?: null);

        session()->flash('reception.success', sprintf(
            'Nouvel episode ouvert pour %s (dossier %s) : ticket n° %d au service %s.',
            $patient->name,
            $patient->patient_code,
            $visit->token,
            $visit->service->name,
        ));

        $this->reset(['search', 'selectedPatientId', 'serviceId', 'reason']);
        $this->dispatch('patient-enregistre');
    }

    public function render(): View
    {
        $search = trim($this->search);

        $matches = $search === '' ? collect() : Patient::query()
            ->where(function ($query) use ($search) {
                $query->where('patient_code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->limit(10)
            ->get();

        return view('livewire.reception.patient-lookup', [
            'matches' => $matches,
            'selected' => $this->selectedPatientId ? Patient::with('visits.service')->find($this->selectedPatientId) : null,
            'services' => Service::careServices()->orderBy('name')->get(),
            'openStatuses' => [Visit::STATUS_WAITING, Visit::STATUS_CALLED],
        ]);
    }
}
