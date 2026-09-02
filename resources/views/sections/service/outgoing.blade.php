<div class="pile">
    <x-page-header
        :fil="['Service', 'Mes renvois']"
        titre="Mes renvois"
        sous-titre="Les patients que vous avez adresses ailleurs, et leur retour." />

    @livewire('service.outgoing-referrals', ['serviceId' => $serviceId], key('service-outgoing-'.$serviceId))
</div>
