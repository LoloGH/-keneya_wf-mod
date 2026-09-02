<div class="pile">
    <x-page-header
        :fil="['Accueil', 'Visiteur']"
        titre="Visiteur"
        sous-titre="Enregistrer un accompagnateur ou un visiteur rattache a un patient." />

    @livewire('reception.visitor-registration-form', [], key('reception-visitor-registration-form'))
</div>
