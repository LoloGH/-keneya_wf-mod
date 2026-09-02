<div class="pile">
    <x-page-header
        :fil="['Poste', 'Signaler un constat']"
        titre="Signaler un constat"
        sous-titre="Ce qui n'a pas fonctionne, remonte a l'administration." />

    @livewire('shared.incident-report-form', ['flashKey' => 'staff.status'], key('constat-staff'))
</div>
