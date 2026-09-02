<div class="pile">
    <x-page-header
        :fil="['Accueil', 'Gestion', 'Journal d\'audit']"
        titre="Journal d'audit"
        sous-titre="Qui a fait quoi, et quand. Le journal n'est jamais modifiable." />

    @livewire('admin.activity-log-viewer', [], key('admin-activity-log-viewer'))
</div>
