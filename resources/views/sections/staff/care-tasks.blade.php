<div class="pile">
    <x-page-header
        :fil="['Poste', 'Soins programmes']"
        titre="Soins programmes"
        sous-titre="Les soins a realiser pour les patients hospitalises." />

    @livewire('staff.staff-care-tasks', [], key('staff-care-tasks'))
</div>
