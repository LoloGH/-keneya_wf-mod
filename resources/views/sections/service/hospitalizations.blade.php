<div class="pile">
    <x-page-header
        :fil="['Service', 'Patients hospitalises']"
        titre="Patients hospitalises"
        sous-titre="Les patients admis dans votre service et leur planning de soins." />

    @livewire('service.hospitalizations', ['serviceId' => $serviceId], key('service-hospitalizations-'.$serviceId))
</div>
