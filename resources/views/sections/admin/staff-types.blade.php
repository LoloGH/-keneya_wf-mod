<div class="pile">
    <x-page-header
        :fil="['Accueil', 'Etablissement', 'Types de personnel']"
        titre="Types de personnel"
        sous-titre="Les modeles qui decident du role, du rattachement et des capacites d'un agent." />

    @livewire('admin.staff-type-manager', [], key('admin-staff-type-manager'))
</div>
