<div class="pile">
    <x-page-header
        :fil="['Accueil', 'Etablissement', 'Personnels']"
        titre="Personnels"
        sous-titre="Les agents de l'etablissement. Le type choisi decide du role et du rattachement." />

    @livewire('admin.staff-manager', [], key('admin-staff-manager'))
</div>
