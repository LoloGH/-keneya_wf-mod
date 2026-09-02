<div class="pile">
    <x-page-header
        :fil="['Accueil', 'Etablissement', 'Liste des services']"
        titre="Services"
        sous-titre="Les services de l'etablissement, et le type d'interface dont chacun herite." />

    @livewire('admin.service-manager', [], key('admin-service-manager'))
</div>
