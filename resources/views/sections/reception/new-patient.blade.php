<div class="pile">
    <x-page-header
        :fil="['Accueil', 'Nouveau patient']"
        titre="Nouveau patient"
        sous-titre="Enregistrer un patient qui n'a encore aucun dossier ici." />

    @livewire('reception.patient-registration-form', [], key('reception-patient-registration-form'))
</div>
