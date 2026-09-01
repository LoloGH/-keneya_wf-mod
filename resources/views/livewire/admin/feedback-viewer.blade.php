<section class="card">
    <h2 class="card__title">Retours et incidents</h2>
    <p class="hint">
        Sondages de satisfaction, reclamations et constats du personnel. Chaque
        entree porte le contexte d'accueil de la personne concernee : elle se
        lit sans avoir a la croiser avec une autre page.
    </p>

    <div class="sms-tally {{ $openCount > 0 ? 'sms-tally--alerte' : 'sms-tally--calme' }}">
        <span class="sms-tally__nombre">{{ $openCount }}</span>
        <span>
            {{ $openCount === 0 ? 'aucun retour en attente de traitement.' : ($openCount > 1 ? 'retours en attente de traitement.' : 'retour en attente de traitement.') }}
        </span>
    </div>

    @if ($visitorsWithoutMobile > 0)
        {{-- Sans ce compteur, le cas resterait invisible : ces visiteurs sont
             marques comme traites, mais n'ont jamais rien recu. --}}
        <p class="hint hint--blocking">
            {{ $visitorsWithoutMobile }} visiteur(s) sans numero de telephone n'ont pas recu de lien de retour.
        </p>
    @endif

    <div class="form form--inline-wrap">
        <div class="field">
            <label for="retour-type">Type</label>
            <select id="retour-type" wire:model.live="type">
                <option value="">Tous</option>
                @foreach ($types as $valeur => $libelle)
                    <option value="{{ $valeur }}">{{ $libelle }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="retour-statut">Statut</label>
            <select id="retour-statut" wire:model.live="status">
                <option value="">Tous</option>
                @foreach ($statuses as $valeur => $libelle)
                    <option value="{{ $valeur }}">{{ $libelle }}</option>
                @endforeach
            </select>
        </div>

        <button type="button" class="btn btn--ghost" wire:click="resetFilters">Reinitialiser</button>
    </div>

    @forelse ($entries as $entry)
        <article class="retour">
            <div class="retour__head">
                <span class="badge badge--{{ $entry->status === 'resolved' ? 'delivered' : ($entry->status === 'reviewed' ? 'sent' : 'queued') }}">
                    {{ $entry->statusLabel() }}
                </span>
                <strong>{{ $entry->typeLabel() }}</strong>
                <time>{{ $entry->created_at->format('d/m/Y H:i') }}</time>
            </div>

            {{-- Le contexte d'accueil : qui, joignable ou, quel service, quand. --}}
            <p class="retour__contexte">
                <strong>{{ $entry->authorName() }}</strong>
                @if ($entry->patient)
                    <span class="mono">{{ $entry->patient->patient_code }}</span>
                    <span>{{ $entry->patient->mobile }}</span>
                @elseif ($entry->visitor)
                    <span class="mono">{{ $entry->visitor->visitor_code }}</span>
                    <span>{{ $entry->visitor->mobile ?: 'sans telephone' }}</span>
                    <span>recu le {{ $entry->visitor->created_at->format('d/m/Y') }}</span>
                @endif
                @if ($entry->service)
                    <span>service {{ $entry->service->name }}</span>
                @endif
                @if ($entry->submittedBy)
                    <span>constat de {{ $entry->submittedBy->name }}</span>
                @endif
                @if ($entry->handledBy)
                    <span>personnel concerne : {{ $entry->handledBy->name }}</span>
                @endif
            </p>

            @if ($entry->hasRatings())
                <p class="retour__notes">
                    Prise en charge : <strong>{{ $entry->rating_care ?? '—' }}/5</strong> ·
                    Personnel : <strong>{{ $entry->rating_staff ?? '—' }}/5</strong>
                </p>
            @endif

            {{-- Le detail par poste (v3.2.9, point 3) : c'est lui qui dit ou
                 agir, la ou une note globale ne disait que « ça va » ou
                 « ça ne va pas ». --}}
            @if ($entry->surveyRatings->isNotEmpty())
                <ul class="retour__etapes">
                    @foreach ($entry->surveyRatings as $note)
                        <li>
                            <strong>{{ $note->post_label }}</strong>
                            <span class="mono">{{ $note->rating }}/5</span>
                            @if ($note->comment) <span>{{ $note->comment }}</span> @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($entry->content)
                <p class="retour__contenu">{{ $entry->content }}</p>
            @endif

            @if ($entry->resolution_notes)
                <p class="retour__resolution">
                    <strong>Traitement</strong> — {{ $entry->resolution_notes }}
                    <span class="hint">
                        par {{ $entry->resolvedBy?->name ?? 'inconnu' }},
                        le {{ $entry->resolved_at?->format('d/m/Y H:i') }}
                    </span>
                </p>
            @endif

            @if ($resolvingId === $entry->id)
                <form wire:submit="resolve" class="form">
                    <div class="field">
                        <label for="resolution-{{ $entry->id }}">
                            Comment cette entree a-t-elle ete traitee ?
                        </label>
                        <textarea id="resolution-{{ $entry->id }}" rows="3" wire:model="resolutionNotes"
                                  placeholder="Ce qui a ete fait, par qui, et avec quelle suite."></textarea>
                        @error('resolutionNotes') <p class="field__error">{{ $message }}</p> @enderror
                    </div>

                    <div class="btn-row">
                        <button type="submit" class="btn btn--primary">
                            Passer a « {{ $statuses[$targetStatus] ?? $targetStatus }} »
                        </button>
                        <button type="button" class="btn btn--ghost" wire:click="cancelResolution">Annuler</button>
                    </div>
                </form>
            @elseif ($entry->status !== 'resolved')
                <div class="btn-row">
                    @if ($entry->status === 'new')
                        <button type="button" class="btn btn--ghost"
                                wire:click="startResolution({{ $entry->id }}, 'reviewed')">
                            Marquer examine
                        </button>
                    @endif
                    <button type="button" class="btn btn--secondary"
                            wire:click="startResolution({{ $entry->id }}, 'resolved')">
                        Marquer resolu
                    </button>
                </div>
            @endif
        </article>
    @empty
        <p class="empty">Aucun retour ne correspond a ces filtres.</p>
    @endforelse

    {{ $entries->links() }}

    @if ($recentVisitors->isNotEmpty())
        <h3 class="card__subtitle">Lancer un sondage visiteur</h3>
        <p class="hint">
            Sans attendre le delai automatique. Le sondage ne portera que sur
            ce que le visiteur a deja vecu.
        </p>
        <ul class="diffusion__resultats">
            @foreach ($recentVisitors as $visiteur)
                <li>
                    <span>
                        {{ $visiteur->name }}
                        <span class="mono">{{ $visiteur->visitor_code }}</span>
                        <span class="hint">recu le {{ $visiteur->created_at->format('d/m/Y') }}</span>
                    </span>
                    <button type="button" class="btn btn--ghost"
                            wire:click="launchVisitorSurvey({{ $visiteur->id }})">
                        Lancer le sondage
                    </button>
                </li>
            @endforeach
        </ul>
    @endif

    <h3 class="card__subtitle">Envoi differe aux visiteurs</h3>
    <p class="hint">
        Delai entre l'enregistrement d'un visiteur et l'envoi de son lien de
        retour. Le conteneur <code>scheduler</code> doit tourner pour que ces
        envois partent.
    </p>

    <form wire:submit="saveDelay" class="form form--inline-wrap">
        <div class="field">
            <label for="retour-delai">Delai <span class="field__hint">en heures</span></label>
            <input id="retour-delai" type="number" min="0" max="168" step="1" wire:model="delayHours">
            @error('delayHours') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="btn btn--secondary">Enregistrer le delai</button>
    </form>
</section>
