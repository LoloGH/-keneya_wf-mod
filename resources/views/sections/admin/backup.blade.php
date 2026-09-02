<div class="pile">
    <x-page-header
        :fil="['Accueil', 'Systeme', 'Sauvegarde']"
        titre="Sauvegarde"
        sous-titre="Ce que contient l'installation, et ce qu'une sauvegarde doit emporter." />

    @livewire('admin.backup-status', [], key('admin-backup-status'))
</div>
