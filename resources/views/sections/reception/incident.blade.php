<div class="pile">
    <x-page-header
        :fil="['Accueil', 'Signaler un constat']"
        titre="Signaler un constat"
        sous-titre="Ce qui n'a pas fonctionne, remonte a l'administration." />

    @livewire('shared.incident-report-form', ['flashKey' => 'reception.success'], key('constat-reception'))
</div>
