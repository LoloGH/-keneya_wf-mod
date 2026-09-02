<div class="pile">
    <x-page-header
        :fil="['Accueil', 'Gestion', 'Pathologies']"
        titre="Pathologies"
        sous-titre="Le catalogue servant a qualifier une consultation, et a cibler un envoi groupe." />

    @livewire('admin.pathology-manager', [], key('admin-pathology-manager'))
</div>
