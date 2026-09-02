<div class="pile">
    <x-page-header
        :fil="['Accueil', 'Gestion', 'Retours et incidents']"
        titre="Retours et incidents"
        sous-titre="Ce que patients et visiteurs disent de leur passage, et les incidents signales par le personnel." />

    @livewire('admin.feedback-viewer', [], key('admin-feedback-viewer'))
</div>
