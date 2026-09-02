<div class="pile">
    <x-page-header
        :fil="['Accueil', 'Gestion', 'Patients']"
        titre="Patients"
        sous-titre="Les dossiers patients de l'etablissement, tous services confondus." />

    @livewire('admin.patient-directory', [], key('admin-patient-directory'))
</div>
