<div class="pile">
    <x-page-header
        :fil="['Service', 'Consultation']"
        titre="Consultation"
        sous-titre="Rediger la consultation dans le dossier medical du patient." />

    @livewire('service.medical-consultation', ['serviceId' => $serviceId], key('service-consultation-medicale-'.$serviceId))
</div>
