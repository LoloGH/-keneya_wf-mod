<?php

namespace App\Livewire\Caisse;

use App\Actions\CallNextPatient;
use App\Actions\ConfirmCaissePayment;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Visit;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Une file de caisse (v3.2, point 6).
 *
 * Le composant sert les deux caisses — « Caisse Ticket » et « Caisse
 * Services » — comme deux sections de la meme interface : un seul role les voit
 * toutes les deux, ce n'est plus reparti entre accueil et service.
 *
 * Meme mecanique de file que partout ailleurs : appeler le suivant, puis
 * encaisser et orienter.
 */
class CaisseQueue extends Component
{
    public int $serviceId;

    /** Visite en cours d'encaissement. */
    public ?int $payingVisitId = null;

    public ?int $amount = null;

    public function mount(int $serviceId): void
    {
        $this->serviceId = $this->assertCaisse($serviceId)->getKey();
    }

    #[On('caisse-mise-a-jour')]
    public function refreshQueue(): void
    {
        // Un nouveau rendu suffit.
    }

    public function callNext(CallNextPatient $action): void
    {
        $service = $this->assertCaisse($this->serviceId);

        // Meme action que partout ailleurs : le caissier appelle en son nom
        // propre, sans etre rattache a un service comme un medecin.
        $visit = $action->execute($service, Auth::user());

        session()->flash(
            'caisse.status',
            $visit
                ? sprintf('Ticket n° %d appele : %s.', $visit->token, $visit->patient->label())
                : 'Aucun patient en attente a cette caisse.'
        );

        $this->dispatch('caisse-mise-a-jour');
    }

    public function startPayment(int $visitId): void
    {
        $this->payingVisitId = $visitId;
        $this->amount = null;
        $this->resetValidation();
    }

    public function cancel(): void
    {
        $this->reset(['payingVisitId', 'amount']);
        $this->resetValidation();
    }

    /**
     * Encaisse puis oriente : la visite quitte la caisse pour le service qui
     * l'attend.
     */
    public function confirmAndRoute(ConfirmCaissePayment $action): void
    {
        $this->validate([
            'payingVisitId' => ['required', 'integer', 'exists:visits,id'],
            'amount' => ['required', 'integer', 'min:1', 'max:9999999999'],
        ], attributes: ['payingVisitId' => 'patient', 'amount' => 'montant']);

        $visit = Visit::with(['patient', 'pendingNextService'])
            ->where('service_id', $this->serviceId)
            ->findOrFail($this->payingVisitId);

        try {
            $visit = $action->execute($visit, Auth::user(), (int) $this->amount);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        session()->flash('caisse.status', sprintf(
            '%s encaisse. %s oriente vers %s, ticket n° %d.',
            number_format((int) $this->amount, 0, ',', ' ').' FCFA',
            $visit->patient->name,
            $visit->service->name,
            $visit->token,
        ));

        $this->cancel();
        $this->dispatch('caisse-mise-a-jour');
    }

    private function assertCaisse(int $serviceId): Service
    {
        $service = Service::findOrFail($serviceId);

        abort_unless($service->isCaisse(), 403, "Ce service n'est pas une caisse.");

        return $service;
    }

    public function render(): View
    {
        $service = $this->assertCaisse($this->serviceId);

        return view('livewire.caisse.caisse-queue', [
            'service' => $service,
            'queue' => Visit::query()
                ->with(['patient', 'pendingNextService'])
                ->inTodaysQueue($this->serviceId)
                ->orderByRaw("CASE status WHEN 'called' THEN 0 WHEN 'waiting' THEN 1 ELSE 2 END")
                ->orderBy('token')
                ->get(),
            'todayPayments' => Payment::query()
                ->with(['patient', 'service'])
                ->whereDate('created_at', today())
                ->where('recorded_by_user_id', Auth::id())
                ->orderByDesc('id')
                ->limit(10)
                ->get(),
            'todayTotal' => (int) Payment::query()
                ->where('status', Payment::STATUS_PAID)
                ->whereDate('created_at', today())
                ->where('recorded_by_user_id', Auth::id())
                ->sum('amount'),
        ]);
    }
}
