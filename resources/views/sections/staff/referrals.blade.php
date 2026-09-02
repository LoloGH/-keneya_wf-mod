<div class="pile">
    <x-page-header
        :fil="['Poste', 'Renvois recus']"
        titre="Renvois recus"
        sous-titre="Les patients qu'un service vous adresse." />

    @livewire('staff.staff-incoming-referrals', [], key('staff-incoming-referrals'))
</div>
