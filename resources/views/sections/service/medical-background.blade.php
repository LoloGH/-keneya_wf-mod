<div class="pile">
    <x-page-header
        :fil="['Service', 'Antecedents et allergies']"
        titre="Antecedents et allergies"
        sous-titre="Ce que le dossier medical retient du passe du patient." />

    @livewire('service.medical-background', ['serviceId' => $serviceId], key('service-antecedents-'.$serviceId))
</div>
