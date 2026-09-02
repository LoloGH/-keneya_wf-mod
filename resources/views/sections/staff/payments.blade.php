<div class="pile">
    <x-page-header
        :fil="['Poste', 'Encaissement']"
        titre="Encaissement"
        sous-titre="Les patients en attente de paiement a votre poste." />

    @livewire('staff.staff-payments', [], key('staff-payments'))
</div>
