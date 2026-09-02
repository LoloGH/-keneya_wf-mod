<div class="pile">
    <x-page-header
        :fil="['Accueil', 'Salle d\'attente']"
        titre="Salle d'attente"
        sous-titre="Ce que le moniteur de la salle affiche en ce moment." />

    @livewire('board.waiting-board', [], key('reception-waiting-board'))
</div>
