<div class="pile">
    <x-page-header
        :fil="['Poste', 'File d\'attente']"
        titre="File d'attente"
        sous-titre="Les patients en attente a votre poste." />

    @livewire('staff.staff-queue', [], key('staff-queue'))
</div>
