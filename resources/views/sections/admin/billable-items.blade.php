<div class="pile">
    <x-page-header
        :fil="['Accueil', 'Gestion', 'Tarifs']"
        titre="Tarifs"
        sous-titre="Le catalogue des actes encaissables, et celui qui vaut ticket de consultation." />

    @livewire('admin.billable-item-manager', [], key('admin-billable-item-manager'))
</div>
