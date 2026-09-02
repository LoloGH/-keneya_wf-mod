<div class="pile">
    <x-page-header
        :fil="['Service', 'Mes rendez-vous']"
        titre="Mes rendez-vous"
        sous-titre="Vos rendez-vous programmes." />

    @livewire('service.my-appointments', [], key('service-my-appointments'))
</div>
