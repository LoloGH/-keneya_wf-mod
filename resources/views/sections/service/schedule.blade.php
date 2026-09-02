<div class="pile">
    <x-page-header
        :fil="['Service', 'Mon planning']"
        titre="Mon planning"
        sous-titre="Vos creneaux de garde a venir." />

    @livewire('shared.my-schedule', [], key('service-my-schedule'))
</div>
