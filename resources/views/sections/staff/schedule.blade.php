<div class="pile">
    <x-page-header
        :fil="['Poste', 'Mon planning']"
        titre="Mon planning"
        sous-titre="Vos creneaux de garde a venir." />

    @livewire('shared.my-schedule', [], key('staff-schedule'))
</div>
