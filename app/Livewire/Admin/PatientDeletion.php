<?php

namespace App\Livewire\Admin;

use App\Actions\DeletePatientRecord;
use App\Livewire\Concerns\NotifiesUser;
use App\Models\Patient;
use App\Support\Roles;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Suppression definitive d'un dossier patient (v3.2, point 8).
 *
 * Deux etapes deliberees : on selectionne le dossier, puis on retape son
 * numero exact et on justifie. Un simple « Etes-vous sur ? » ne protege de
 * rien : on clique oui par reflexe.
 *
 * Reserve a /admin : cette section n'existe dans aucune autre interface.
 */
class PatientDeletion extends Component
{
    use NotifiesUser;

    public string $search = '';

    public ?int $selectedPatientId = null;

    /** Le numero de dossier doit etre retape a l'identique. */
    public string $confirmation = '';

    public string $reason = '';

    /**
     * Garde-fou explicite en plus du cloisonnement de route : pour une
     * operation irreversible, on ne se repose pas sur le seul fait que la
     * section ne soit rendue que dans /admin.
     */
    public function mount(): void
    {
        abort_unless(Auth::user()?->hasRole(Roles::ADMIN), 403);
    }

    public function select(int $patientId): void
    {
        $this->selectedPatientId = $patientId;
        $this->confirmation = '';
        $this->reason = '';
        $this->resetValidation();
    }

    public function cancel(): void
    {
        $this->reset(['selectedPatientId', 'confirmation', 'reason']);
        $this->resetValidation();
    }

    public function delete(DeletePatientRecord $action): void
    {
        $this->validate([
            'selectedPatientId' => ['required', 'integer', 'exists:patients,id'],
            'confirmation' => ['required', 'string'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], attributes: [
            'confirmation' => 'numero de dossier',
            'reason' => 'motif',
        ]);

        abort_unless(Auth::user()?->hasRole(Roles::ADMIN), 403);

        $patient = Patient::findOrFail($this->selectedPatientId);
        $code = $patient->patient_code;

        try {
            $action->execute($patient, Auth::user(), $this->confirmation, $this->reason);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['confirmation' => $e->getMessage()]);
        }

        $this->notifySuccess(sprintf(
            'Dossier %s supprime definitivement. L\'operation est consignee dans le journal d\'audit.',
            $code,
        ));

        $this->reset(['search', 'selectedPatientId', 'confirmation', 'reason']);
    }

    public function render(): View
    {
        $search = trim($this->search);

        return view('livewire.admin.patient-deletion', [
            'matches' => $search === '' ? collect() : Patient::query()
                ->where(function ($q) use ($search) {
                    $q->where('patient_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%");
                })
                ->orderBy('name')
                ->limit(10)
                ->get(),
            'selected' => $this->selectedPatientId
                ? Patient::withCount(['visits', 'attachments', 'prescriptions', 'appointments'])
                    ->find($this->selectedPatientId)
                : null,
        ]);
    }
}
