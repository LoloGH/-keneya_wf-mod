<div class="service-selector">
    @if ($assignments->count() > 1)
        {{-- Un medecin rattache a plusieurs services choisit ici le sien.
             La liste ne contient que ses propres services. --}}
        <label for="service-selector">Service actif</label>
        <select id="service-selector" wire:model.live="serviceId">
            @foreach ($assignments as $assignment)
                <option value="{{ $assignment->service_id }}">{{ $assignment->service->name }}</option>
            @endforeach
        </select>
    @elseif ($assignments->count() === 1)
        <p class="service-selector__single">{{ $assignments->first()->service->name }}</p>
    @endif
</div>
