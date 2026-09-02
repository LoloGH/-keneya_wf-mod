<div class="pile">
    <x-page-header
        :fil="['Accueil', 'Etablissement']"
        titre="Informations de l'etablissement"
        sous-titre="Ces informations apparaitront sur toutes les ordonnances et tickets imprimes." />

    @livewire('admin.hospital-settings', [], key('admin-hospital-settings'))
</div>
