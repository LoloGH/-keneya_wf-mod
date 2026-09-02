<div class="pile">
    <x-page-header
        :fil="['Accueil', 'Etablissement', 'Plannings']"
        titre="Plannings"
        sous-titre="Qui est de garde, et quand. Les creneaux decident aussi qui recoit les notifications." />

    @livewire('admin.bulk-schedule-form', [], key('admin-bulk-schedule'))
    @livewire('admin.schedule-manager', [], key('admin-schedule-manager'))
</div>
