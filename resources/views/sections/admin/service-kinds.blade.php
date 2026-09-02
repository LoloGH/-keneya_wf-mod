<div class="pile">
    <x-page-header
        :fil="['Accueil', 'Etablissement', 'Types de service']"
        titre="Types de service"
        sous-titre="Les modeles qui decident des sections offertes a une interface de service." />

    @livewire('admin.service-kind-manager', [], key('admin-service-kind-manager'))
</div>
