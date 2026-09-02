<div class="pile">
    <x-page-header
        :fil="['Accueil', 'Systeme', 'Parametres']"
        titre="Parametres generaux"
        sous-titre="Les reglages de fonctionnement qui n'appartiennent a aucune section en particulier." />

    @livewire('admin.general-parameters', [], key('admin-general-parameters'))
</div>
