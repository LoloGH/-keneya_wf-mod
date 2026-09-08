<div class="pile">
    <x-page-header
        :fil="['Service', 'Traitements et examens']"
        titre="Traitements, examens et documents"
        sous-titre="Completer le dossier medical du patient pendant qu'il est la." />

    @livewire('service.medical-orders', ['serviceId' => $serviceId], key('service-examens-'.$serviceId))
</div>
