<div class="pile">
    <x-page-header
        :fil="['Accueil', 'Etablissement', 'Types de soins']"
        titre="Types de soins"
        sous-titre="Les soins programmables dans un planning d'hospitalisation." />

    @livewire('admin.care-task-type-manager', [], key('admin-care-task-type-manager'))
</div>
