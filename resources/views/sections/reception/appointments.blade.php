<div class="pile">
    <x-page-header
        :fil="['Accueil', 'Rendez-vous du jour']"
        titre="Rendez-vous du jour"
        sous-titre="Les rendez-vous programmes pour aujourd'hui." />

    @livewire('reception.today-appointments', [], key('reception-today-appointments'))
</div>
