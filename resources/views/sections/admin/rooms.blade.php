<div class="pile">
    <x-page-header
        :fil="['Accueil', 'Etablissement', 'Salles']"
        titre="Salles"
        sous-titre="Les salles d'hospitalisation et leur capacite en lits." />

    @livewire('admin.room-manager', [], key('admin-room-manager'))
</div>
