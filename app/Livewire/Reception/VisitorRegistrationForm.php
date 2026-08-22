<?php

namespace App\Livewire\Reception;

use App\Actions\RegisterVisitor;
use App\Models\Service;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class VisitorRegistrationForm extends Component
{
    public string $name = '';

    public string $mobile = '';

    public ?int $service_id = null;

    public string $reason = '';

    public ?array $lastRegistered = null;

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => 'nom du visiteur',
            'mobile' => 'telephone',
            'service_id' => 'service',
            'reason' => 'motif',
        ];
    }

    public function save(RegisterVisitor $register): void
    {
        $data = $this->validate();

        $visitor = $register->execute($data);

        $this->lastRegistered = [
            'visitor_code' => $visitor->visitor_code,
            'name' => $visitor->name,
            'service' => $visitor->service->name,
        ];

        $this->reset(['name', 'mobile', 'reason']);

        $this->dispatch('visiteur-enregistre');
    }

    public function render(): View
    {
        return view('livewire.reception.visitor-registration-form', [
            'services' => Service::orderBy('name')->get(),
        ]);
    }
}
