<div class="pile">
    <x-page-header
        :fil="['Service', 'Mes patients']"
        titre="Mes patients"
        sous-titre="Les dossiers que vous avez suivis." />

    @livewire('service.my-patients', [], key('service-my-patients'))
</div>
