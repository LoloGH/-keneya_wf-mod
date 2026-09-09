<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\NotifiesUser;
use App\Models\BillableItem;
use App\Models\Service;
use App\Models\Setting;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Catalogue de tarifs (v3.2.8, point 3).
 *
 * Meme forme que les autres catalogues administrables, types de service,
 * types de personnel, types de soins : la liste des actes d'un hopital
 * s'enrichit, elle n'a pas sa place figee dans le code.
 *
 * Le montant se saisissait librement a la caisse. Rien ne disait alors ce que
 * le patient payait, ni ne garantissait que deux caissiers demandent la meme
 * somme pour le meme acte.
 */
class BillableItemManager extends Component
{
    use NotifiesUser;

    public ?int $editingId = null;

    public string $name = '';

    public ?int $service_id = null;

    public ?int $price = null;

    /** L'acte qui vaut ticket de consultation, applique a l'enregistrement. */
    public ?int $ticketItemId = null;

    public function mount(): void
    {
        $ticket = Setting::get(Setting::TICKET_BILLABLE_ITEM_ID);

        $this->ticketItemId = $ticket ? (int) $ticket : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            // Entier : les montants sont en FCFA, sans decimales.
            'price' => ['required', 'integer', 'min:0', 'max:9999999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => "nom de l'acte",
            'service_id' => 'service',
            'price' => 'tarif',
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editingId) {
            $item = BillableItem::findOrFail($this->editingId);
            $ancien = $item->price;
            $item->update($data);

            Audit::log(
                Audit::EVENT_UPDATED,
                sprintf(
                    'Tarif « %s » modifie%s.',
                    $item->name,
                    $ancien !== $item->price
                        ? sprintf(' : %s FCFA au lieu de %s FCFA', number_format($item->price, 0, ',', ' '), number_format($ancien, 0, ',', ' '))
                        : '',
                ),
                $item,
            );

            $this->notifySuccess('Tarif mis a jour.');
        } else {
            $item = BillableItem::create($data);

            Audit::log(Audit::EVENT_CREATED, sprintf('Tarif « %s » cree a %s.', $item->name, $item->formattedPrice()), $item);

            $this->notifySuccess('Tarif cree.');
        }

        $this->cancel();
    }

    public function edit(int $itemId): void
    {
        $item = BillableItem::findOrFail($itemId);

        $this->editingId = $item->getKey();
        $this->name = $item->name;
        $this->service_id = $item->service_id;
        $this->price = $item->price;
        $this->resetValidation();
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'name', 'service_id', 'price']);
        $this->resetValidation();
    }

    public function delete(int $itemId): void
    {
        $item = BillableItem::withCount(['payments', 'referrals'])->findOrFail($itemId);

        // Un tarif deja facture ou deja demande ne se supprime pas : le recu
        // d'hier doit continuer a dire ce qu'il disait.
        if ($item->payments_count > 0 || $item->referrals_count > 0) {
            $this->notifyError(sprintf(
                'Le tarif « %s » ne peut pas etre supprime : %d encaissement(s) et %d renvoi(s) s\'y rattachent. Modifiez son prix plutot que de le retirer.',
                $item->name,
                $item->payments_count,
                $item->referrals_count,
            ));

            return;
        }

        if ($this->ticketItemId === $item->getKey()) {
            $this->notifyError('Ce tarif est celui du ticket de consultation : designez-en un autre avant de le supprimer.');

            return;
        }

        $nom = $item->name;
        $item->delete();

        Audit::log(Audit::EVENT_DELETED, sprintf('Tarif « %s » supprime.', $nom));

        $this->notifySuccess('Tarif supprime.');
    }

    /**
     * Designe l'acte applique automatiquement a l'enregistrement. Sans lui, la
     * caisse ticket retombe sur la saisie libre : mieux vaut un montant a
     * saisir qu'un montant invente.
     */
    public function saveTicketItem(): void
    {
        $this->validate(
            ['ticketItemId' => ['nullable', 'integer', 'exists:billable_items,id']],
            attributes: ['ticketItemId' => 'ticket de consultation'],
        );

        Setting::put(Setting::TICKET_BILLABLE_ITEM_ID, $this->ticketItemId ? (string) $this->ticketItemId : null);

        $item = $this->ticketItemId ? BillableItem::find($this->ticketItemId) : null;

        Audit::log(
            Audit::EVENT_UPDATED,
            $item
                ? sprintf('Ticket de consultation fixe a « %s » (%s).', $item->name, $item->formattedPrice())
                : 'Ticket de consultation : aucun tarif applique automatiquement.',
        );

        $this->notifySuccess('Ticket de consultation enregistre.');
    }

    public function render(): View
    {
        return view('livewire.admin.billable-item-manager', [
            'items' => BillableItem::with('service')
                ->withCount(['payments', 'referrals'])
                ->orderBy('service_id')
                ->orderBy('name')
                ->get(),
            'services' => Service::careServices()->orderBy('name')->get(),
            'ticketChoices' => BillableItem::orderBy('name')->get(),
        ]);
    }
}
