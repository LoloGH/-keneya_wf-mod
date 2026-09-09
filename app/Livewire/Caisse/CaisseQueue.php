<?php

namespace App\Livewire\Caisse;

use App\Actions\CallNextPatient;
use App\Actions\ConfirmCaissePayment;
use App\Models\BillableItem;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Visit;
use App\Services\CaisseBilling;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Une file de caisse (v3.2, point 6).
 *
 * Le composant sert n'importe quel service de type caisse : « Caisse Ticket »
 * et « Caisse Services » a l'installation, plus toute caisse ajoutee ensuite
 * par l'administrateur. Chacune est une section de la meme interface : un seul
 * role les voit toutes, ce n'est plus reparti entre accueil et service.
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

    /**
     * L'acte facture, resolu depuis le renvoi ou le ticket (v3.2.8, point 3).
     * Le caissier ne le choisit pas : il lui est presente.
     */
    public ?int $billableItemId = null;

    /** Tarif du catalogue, affiche a cote du montant saisi. */
    public ?int $catalogPrice = null;

    /**
     * Derogation au tarif. Volontairement fermee par defaut : corriger un
     * montant doit etre un geste delibere, pas le comportement ordinaire.
     */
    public bool $overridePrice = false;

    public string $overrideReason = '';

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

    public function startPayment(int $visitId, CaisseBilling $billing): void
    {
        $this->payingVisitId = $visitId;
        $this->overridePrice = false;
        $this->overrideReason = '';
        $this->resetValidation();

        $visit = Visit::with('service')->find($visitId);
        $acte = $visit ? $billing->itemFor($visit) : null;

        $this->billableItemId = $acte?->getKey();
        $this->catalogPrice = $acte?->price;

        // Le montant se pre-remplit depuis le tarif : le caissier confirme au
        // lieu de saisir. Sans acte au catalogue, on retombe sur la saisie
        // libre plutot que de proposer un chiffre invente.
        $this->amount = $acte?->price;
    }

    public function cancel(): void
    {
        $this->reset(['payingVisitId', 'amount', 'billableItemId', 'catalogPrice', 'overridePrice', 'overrideReason']);
        $this->resetValidation();
    }

    /** Revenir au tarif du catalogue en un geste. */
    public function restoreCatalogPrice(): void
    {
        $this->overridePrice = false;
        $this->overrideReason = '';
        $this->amount = $this->catalogPrice;
        $this->resetValidation();
    }

    /**
     * Encaisse puis oriente : la visite quitte la caisse pour le service qui
     * l'attend.
     */
    public function confirmAndRoute(ConfirmCaissePayment $action): void
    {
        $deroge = $this->catalogPrice !== null && (int) $this->amount !== $this->catalogPrice;

        $this->validate([
            'payingVisitId' => ['required', 'integer', 'exists:visits,id'],
            'amount' => ['required', 'integer', 'min:1', 'max:9999999999'],
            // Le motif n'est exige que lorsqu'il y a effectivement derogation :
            // encaisser au tarif ne doit rien demander de plus qu'avant.
            'overrideReason' => $deroge ? ['required', 'string', 'min:3', 'max:255'] : ['nullable'],
        ], attributes: [
            'payingVisitId' => 'patient',
            'amount' => 'montant',
            'overrideReason' => 'motif de la derogation',
        ]);

        $visit = Visit::with(['patient', 'pendingNextService'])
            ->where('service_id', $this->serviceId)
            ->findOrFail($this->payingVisitId);

        $acte = $this->billableItemId ? BillableItem::find($this->billableItemId) : null;

        try {
            $visit = $action->execute(
                $visit,
                Auth::user(),
                (int) $this->amount,
                $acte,
                $deroge ? $this->overrideReason : null,
            );
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        session()->flash('caisse.status', sprintf(
            '%s encaisse%s. %s oriente vers %s, ticket n° %d.',
            number_format((int) $this->amount, 0, ',', ' ').' FCFA',
            $acte ? ' pour « '.$acte->name.' »' : '',
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

        $billing = app(CaisseBilling::class);

        $queue = Visit::query()
            ->with(['patient', 'pendingNextService', 'service'])
            ->inTodaysQueue($this->serviceId)
            ->orderByRaw("CASE status WHEN 'called' THEN 0 WHEN 'waiting' THEN 1 ELSE 2 END")
            ->orderBy('token')
            ->get();

        // L'acte attendu est resolu pour toute la file : le caissier voit ce
        // qu'il aura a encaisser avant meme d'appeler le patient.
        $actes = $queue->mapWithKeys(fn (Visit $visit) => [
            $visit->getKey() => $billing->itemFor($visit),
        ]);

        return view('livewire.caisse.caisse-queue', [
            'service' => $service,
            'actes' => $actes,
            'queue' => $queue,
            'todayPayments' => Payment::query()
                ->with(['patient', 'service', 'billableItem'])
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
