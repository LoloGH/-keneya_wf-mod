<div class="pile">
    <x-page-header
        :fil="['Accueil', 'Rechercher un dossier']"
        titre="Rechercher un dossier"
        sous-titre="Retrouver un patient deja connu de l'etablissement avant d'en creer un nouveau." />

    @livewire('reception.patient-lookup', [], key('reception-patient-lookup'))
</div>
