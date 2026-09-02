<div class="pile">
    <x-page-header
        :fil="['Caisse', 'Encaissement']"
        titre="Encaissement"
        sous-titre="Les patients en attente de paiement, et le total encaisse aujourd'hui." />

    @livewire('caisse.caisse-queue', ['serviceId' => $caisseServiceId], key('caisse-'.$caisseServiceId))
</div>
