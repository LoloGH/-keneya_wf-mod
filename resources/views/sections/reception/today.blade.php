<div class="pile">
    <x-page-header
        :fil="['Accueil', 'Passages du jour']"
        titre="Passages du jour"
        sous-titre="Tous les passages enregistres aujourd'hui, quel que soit le service." />

    @livewire('reception.today-visits', [], key('reception-today-visits'))
</div>
