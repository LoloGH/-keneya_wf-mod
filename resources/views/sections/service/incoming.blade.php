<div class="pile">
    <x-page-header
        :fil="['Service', 'Renvois en attente']"
        titre="Renvois en attente"
        sous-titre="Les patients qu'un autre service vous adresse." />

    @livewire('service.incoming-referrals', ['serviceId' => $serviceId], key('service-incoming-'.$serviceId))
</div>
