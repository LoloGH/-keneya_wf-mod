<div class="pile">
    <x-page-header
        :fil="['Service', 'Fin de consultation']"
        titre="Fin de consultation"
        sous-titre="Conclure un passage : conclusion, ordonnance, renvoi ou cloture." />

    @livewire('service.consultation-actions', ['serviceId' => $serviceId], key('service-consultation-'.$serviceId))
</div>
