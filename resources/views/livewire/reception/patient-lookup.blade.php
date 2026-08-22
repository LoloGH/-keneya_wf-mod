<section class="card card--accent">
    <h2 class="card__title">Le patient est-il deja venu ?</h2>
    <p class="hint">
        Cherchez avant d'enregistrer : un patient deja connu garde son numero de dossier a vie.
        On lui ouvre un nouvel episode, on ne lui cree pas un second dossier.
    </p>

    <div class="field">
        <label for="lookup-search">Numero de dossier, nom ou telephone</label>
        <input id="lookup-search" type="search" wire:model.live.debounce.400ms="search"
               placeholder="HFD-00001, Sekou Diarra, 76445566…">
    </div>

    @if (trim($search) !== '' && $matches->isEmpty())
        <p class="empty">
            Aucun dossier ne correspond. Utilisez le formulaire « Enregistrer un patient » ci-dessous
            pour creer un nouveau dossier.
        </p>
    @endif

    @if ($matches->isNotEmpty() && ! $selected)
        <ul class="lookup">
            @foreach ($matches as $match)
                <li class="lookup__item">
                    <span class="lookup__identity">
                        <strong>{{ $match->name }}</strong>
                        <span class="mono">{{ $match->patient_code }}</span>
                        <span>{{ $match->age }} ans — {{ $match->gender }} — {{ $match->mobile }}</span>
                    </span>
                    <button type="button" class="btn btn--secondary" wire:click="select({{ $match->id }})">
                        C'est ce patient
                    </button>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($selected)
        {{-- Confirmation visuelle obligatoire : deux homonymes ne doivent
             jamais etre confondus. --}}
        <div class="lookup__confirm">
            <h3 class="card__subtitle">Confirmer l'identite</h3>

            <dl class="record__identity">
                <div><dt>Nom</dt><dd>{{ $selected->name }}</dd></div>
                <div><dt>Dossier</dt><dd class="mono">{{ $selected->patient_code }}</dd></div>
                <div><dt>Age</dt><dd>{{ $selected->age }} ans</dd></div>
                <div><dt>Sexe</dt><dd>{{ $selected->gender }}</dd></div>
                <div><dt>Telephone</dt><dd>{{ $selected->mobile }}</dd></div>
                <div><dt>Passages</dt><dd>{{ $selected->visits->count() }}</dd></div>
            </dl>

            @php $openVisit = $selected->visits->whereIn('status', $openStatuses)->sortByDesc('opened_at')->first(); @endphp

            @if ($openVisit)
                <p class="alert alert--warning" role="status">
                    Ce patient a deja un episode en cours au service {{ $openVisit->service->name }}
                    (ticket n° {{ $openVisit->token }}). Ouvrir un nouvel episode le placera dans une seconde file.
                </p>
            @endif

            <form wire:submit="openEpisode" class="form">
                <div class="field">
                    <label for="lookup-service">Service pour ce nouveau passage</label>
                    <select id="lookup-service" wire:model="serviceId">
                        <option value="">— Choisir un service —</option>
                        @foreach ($services as $service)
                            <option value="{{ $service->id }}">{{ $service->name }}</option>
                        @endforeach
                    </select>
                    @error('serviceId') <p class="field__error">{{ $message }}</p> @enderror
                </div>

                <div class="field">
                    <label for="lookup-reason">Motif de ce passage <span class="field__hint">(facultatif)</span></label>
                    <input id="lookup-reason" type="text" wire:model="reason"
                           placeholder="ex. retour pour douleurs abdominales, sans lien avec l'episode precedent">
                    @error('reason') <p class="field__error">{{ $message }}</p> @enderror
                </div>

                <div class="btn-row">
                    <button type="submit" class="btn btn--primary">Nouvel episode</button>
                    <button type="button" class="btn btn--ghost" wire:click="cancel">Ce n'est pas ce patient</button>
                </div>
            </form>
        </div>
    @endif
</section>
