<div class="pile">
    <x-page-header
        :fil="['Accueil', 'Gestion', 'Analyse']"
        titre="Analyse des retours"
        sous-titre="Ce que les sondages des patients et des visiteurs disent des services et des agents." />

    @livewire('admin.performance-dashboard', [], key('admin-performance-dashboard'))
</div>
