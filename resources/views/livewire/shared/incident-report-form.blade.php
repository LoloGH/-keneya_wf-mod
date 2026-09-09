<x-card title="Signaler un constat" icon="alerte">
    <p class="hint">
        Un incident, une observation, un dysfonctionnement. Transmis a la
        direction, qui devra dire comment il a ete traite. Le dossier concerne
        est facultatif : un constat peut porter sur une observation generale.
    </p>

    <form wire:submit="submit" class="form">
        <div class="field-row">
            <div class="field">
                <label for="constat-dossier">Patient concerne <span class="field__hint">(facultatif)</span></label>
                <input id="constat-dossier" type="text" wire:model="patientCode" placeholder="HFD-00042" autocomplete="off">
                @error('patientCode') <p class="field__error">{{ $message }}</p> @enderror
            </div>

            <div class="field">
                <label for="constat-service">Service <span class="field__hint">(facultatif)</span></label>
                <select id="constat-service" wire:model="serviceId">
                    <option value="">Aucun</option>
                    @foreach ($services as $service)
                        <option value="{{ $service->id }}">{{ $service->name }}</option>
                    @endforeach
                </select>
                @error('serviceId') <p class="field__error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="field">
            <label for="constat-contenu">Constat</label>
            <textarea id="constat-contenu" rows="4" wire:model="content"
                      placeholder="Ce qui a ete observe, quand, et ce qui devrait etre fait."></textarea>
            @error('content') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="btn btn--primary" wire:loading.attr="disabled">Transmettre</button>
    </form>

    @if ($recents->isNotEmpty())
        <h3 class="card__subtitle">Mes derniers constats</h3>
        <ul class="payments">
            @foreach ($recents as $constat)
                <li>
                    <span class="badge badge--{{ $constat->status === 'resolved' ? 'delivered' : ($constat->status === 'reviewed' ? 'sent' : 'queued') }}">
                        {{ $constat->statusLabel() }}
                    </span>
                    {{ \Illuminate\Support\Str::limit($constat->content, 80) }}
                    <time>{{ $constat->created_at->format('d/m/Y') }}</time>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
