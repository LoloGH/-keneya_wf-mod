<div class="pile">
    <x-page-header
        :fil="['Accueil', 'Gestion', 'Envoi groupe']"
        titre="Envoi groupe"
        sous-titre="Un message vers un agent, un patient, un groupe de patients ou l'ensemble de l'etablissement." />

    @livewire('admin.broadcast-composer', [], key('admin-broadcast-composer'))
</div>
