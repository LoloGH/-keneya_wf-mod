<div class="pile">
    <x-page-header
        :fil="['Service', 'File d\'attente']"
        titre="File d'attente"
        sous-titre="Les patients en attente dans votre service, dans l'ordre d'arrivee." />

    @livewire('service.service-queue', ['serviceId' => $serviceId], key('service-queue-'.$serviceId))
</div>
