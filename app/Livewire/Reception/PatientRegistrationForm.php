<?php

namespace App\Livewire\Reception;

use App\Actions\RegisterPatient;
use App\Models\Service;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class PatientRegistrationForm extends Component
{
    public string $name = '';

    public ?int $age = null;

    public string $gender = 'Homme';

    public string $mobile = '';

    public string $crno = '';

    public ?int $service_id = null;

    /** Dernier patient enregistre, affiche a la receptionniste pour lecture du code. */
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
        ];
    }

    public function save(RegisterPatient $register): void
    {
        $data = $this->validate();

        $patient = $register->execute($data);

        $this->lastRegistered = [
            'patient_code' => $patient->patient_code,
            'name' => $patient->name,
            'service' => $patient->service->name,
            'token' => $patient->token,
        ];

        $this->reset(['name', 'age', 'mobile', 'crno']);
        $this->gender = 'Homme';

        $this->dispatch('patient-enregistre');

        session()->flash('reception.success', sprintf(
            'Patient %s enregistre — dossier %s, ticket n° %d au service %s.',
            $patient->name,
            $patient->patient_code,
            $patient->token,
            $patient->service->name,
        ));
    }

    public function render(): View
    {
        return view('livewire.reception.patient-registration-form', [
            'services' => Service::orderBy('name')->get(),
        ]);
    }
}
