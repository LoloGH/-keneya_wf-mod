<div class="pile">
    <x-page-header
        :fil="['Poste', 'Releves']"
        titre="Releves"
        sous-titre="Ce que l'equipe precedente a laisse a l'equipe suivante." />

    @livewire('staff.staff-handoffs', [], key('staff-handoffs'))
</div>
