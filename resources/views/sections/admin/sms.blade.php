<div class="pile">
    <x-page-header
        :fil="['Accueil', 'Gestion', 'Journal des envois']"
        titre="Journal des SMS"
        sous-titre="Chaque message mis en file, son statut et, en cas d'echec, sa raison." />

    @livewire('admin.sms-message-viewer', [], key('admin-sms-message-viewer'))
</div>
