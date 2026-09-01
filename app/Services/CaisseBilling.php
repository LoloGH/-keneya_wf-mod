<?php

namespace App\Services;

use App\Models\BillableItem;
use App\Models\Referral;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Visit;

/**
 * Ce que le patient doit payer, et pourquoi (v3.2.8, point 3).
 *
 * Le caissier voyait un champ de montant vide et une destination : il devait
 * demander au patient — ou deviner — ce qui etait facture. L'acte choisi par le
 * medecin voyage desormais avec la visite, et c'est ici qu'on le retrouve, quel
 * que soit le chemin par lequel la visite est arrivee a la caisse.
 */
class CaisseBilling
{
    /**
     * L'acte attendu pour cette visite a la caisse ou elle se trouve.
     *
     * Deux cas, et deux seulement : la caisse ticket applique le ticket de
     * consultation de l'etablissement, les autres suivent l'acte porte par le
     * renvoi qui a conduit le patient jusqu'ici.
     */
    public function itemFor(Visit $visit): ?BillableItem
    {
        $caisse = $visit->service;

        if ($caisse?->name === Service::CAISSE_TICKET) {
            return $this->ticketItem();
        }

        return $this->referralItem($visit);
    }

    /**
     * Le ticket de consultation, tel que l'administration l'a designe.
     *
     * Nul tant qu'aucun tarif n'a ete choisi comme ticket : mieux vaut un
     * montant a saisir qu'un montant invente.
     */
    public function ticketItem(): ?BillableItem
    {
        $id = Setting::get(Setting::TICKET_BILLABLE_ITEM_ID);

        return $id ? BillableItem::find((int) $id) : null;
    }

    /**
     * L'acte du dernier renvoi encore en attente pour cette visite.
     *
     * Le renvoi porte la destination medicale reelle, jamais la caisse : c'est
     * bien lui qui sait ce qui a ete demande.
     */
    private function referralItem(Visit $visit): ?BillableItem
    {
        return Referral::query()
            ->with('billableItem')
            ->where('visit_id', $visit->getKey())
            ->whereNotNull('billable_item_id')
            ->where('status', Referral::STATUS_PENDING)
            ->orderByDesc('id')
            ->first()?->billableItem;
    }
}
