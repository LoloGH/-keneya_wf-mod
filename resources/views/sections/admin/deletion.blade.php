<div class="pile">
    <x-page-header
        :fil="['Accueil', 'Systeme', 'Supprimer un dossier']"
        titre="Supprimer un dossier"
        sous-titre="Suppression definitive d'un dossier patient et de tout ce qui s'y rattache." />

    @livewire('admin.patient-deletion', [], key('admin-patient-deletion'))
</div>
