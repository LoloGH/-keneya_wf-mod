@php
    // Service courant du medecin connecte : son premier rattachement par defaut.
    $assignment = auth()->user()->doctors()->with('service')->orderBy('id')->first();
    $serviceId = $assignment?->service_id;
@endphp

<x-layouts.app :title="'Service — '.config('keneya.name')">
    <x-slot:space>Espace service{{ $assignment ? ' — '.$assignment->service->name : '' }}</x-slot:space>

    @if (! $serviceId)
        <div class="alert alert--error" role="alert">
            Votre compte n'est rattache a aucun service. Contactez l'administrateur.
        </div>
    @else
        <div class="service-head">
            <h1 class="page-title">Mon service</h1>
            @livewire('service.service-selector', ['serviceId' => $serviceId])
        </div>

        @if (session('service.status'))
            <div class="alert alert--success" role="status">{{ session('service.status') }}</div>
        @endif
        @if (session('service.error'))
            <div class="alert alert--error" role="alert">{{ session('service.error') }}</div>
        @endif

        <div class="grid grid--main">
            <div class="stack">
                @livewire('service.service-queue', ['serviceId' => $serviceId])
                @livewire('service.incoming-referrals', ['serviceId' => $serviceId])
                @livewire('service.outgoing-referrals', ['serviceId' => $serviceId])
                @livewire('service.consultation-actions', ['serviceId' => $serviceId])
                @livewire('service.my-patients')
                @livewire('shared.my-schedule')
            </div>

            {{-- Le dossier patient s'ouvre ici meme, jamais sur une autre page. --}}
            <div class="record-column">
                @livewire('service.patient-record-panel')
            </div>
        </div>
    @endif
</x-layouts.app>
