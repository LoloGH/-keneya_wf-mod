<div class="pile">
    <x-page-header
        :fil="['Accueil', 'Mon planning']"
        titre="Mon planning"
        sous-titre="Vos creneaux de garde a venir." />

    @livewire('shared.my-schedule', [], key('reception-my-schedule'))
</div>
