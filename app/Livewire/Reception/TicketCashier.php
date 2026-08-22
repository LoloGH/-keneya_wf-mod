<?php

namespace App\Livewire\Reception;

use App\Actions\RecordPayment;
use App\Models\Payment;
use App\Models\Visit;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * « Caisse Ticket » (addendum v2, point 9) : encaissement du ticket de
 * consultation, a l'enregistrement ou plus tard dans la journee.
 *
 * Ce n'est pas un service de la table `services` — un paiement n'a ni file
 * d'attente ni renvoi — mais une section de l'interface d'accueil.
 */
class TicketCashier extends Component
{
    public ?int $visitId = null;

    public ?int $amount = null;

    #[On('patient-enregistre')]
    public function refreshPanel(): void
    {
        // Un nouveau rendu suffit.
    }

    public function record(RecordPayment $action): void
    {
        $this->validate([
            'visitId' => ['required', 'integer', 'exists:visits,id'],
            'amount' => ['required', 'integer', 'min:1', 'max:9999999999'],
        ], attributes: ['visitId' => 'patient', 'amount' => 'montant']);

        $visit = Visit::with('patient')->findOrFail($this->visitId);

        try {
            $payment = $action->execute(
                visit: $visit,
                recordedBy: Auth::user(),
                type: Payment::TYPE_TICKET,
                amount: (int) $this->amount,
            );
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        session()->flash('reception.success', sprintf(
            'Ticket de %s encaisse pour %s (%s).',
            $payment->formattedAmount(),
            $visit->patient->name,
            $visit->patient->patient_code,
        ));

        $this->reset(['visitId', 'amount']);
    }

    public function render(): View
    {
        return view('livewire.reception.ticket-cashier', [
            'visits' => Visit::query()
                ->with(['patient', 'service'])
                ->whereDate('opened_at', today())
                ->orderByDesc('id')
                ->get(),
            'todayPayments' => Payment::query()
                ->with('patient')
                ->where('type', Payment::TYPE_TICKET)
                ->whereDate('created_at', today())
                ->orderByDesc('id')
                ->limit(10)
                ->get(),
            'todayTotal' => (int) Payment::query()
                ->where('type', Payment::TYPE_TICKET)
                ->where('status', Payment::STATUS_PAID)
                ->whereDate('created_at', today())
                ->sum('amount'),
        ]);
    }
}
