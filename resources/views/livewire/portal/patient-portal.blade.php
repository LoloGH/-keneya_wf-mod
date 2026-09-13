<div class="portal">
    @if (! $unlocked)
        {{-- Rien du dossier n'est affiche avant validation du code : la page ne
             divulgue meme pas le nom du patient. --}}
        <section class="card portal__gate">
            <h1 class="card__title">Mes documents</h1>
            <p class="hint">
                Saisissez le code a quatre chiffres qui vous a ete remis a l'accueil.
            </p>

            <form wire:submit="unlock" class="form">
                <div class="field">
                    <label for="portal-code">Code d'acces</label>
                    <input id="portal-code" type="text" inputmode="numeric" autocomplete="off"
                           maxlength="4" wire:model="code" class="portal__code">
                    @error('code') <p class="field__error">{{ $message }}</p> @enderror
                </div>

                @if ($error)
                    <p class="alert alert--error" role="alert">{{ $error }}</p>
                @endif

                <button type="submit" class="btn btn--primary btn--block">Afficher mes documents</button>
            </form>
        </section>
    @else
        <section class="card">
            <div class="card__head">
                <h1 class="card__title">Documents de {{ $patient->name }}</h1>
                <button type="button" class="btn btn--ghost" wire:click="lock">Masquer</button>
            </div>
            <p class="hint mono">N&deg; patient {{ $patient->patient_code }}</p>
        </section>

        <section class="card">
            <h2 class="card__subtitle">Mes rendez-vous a venir ({{ $appointments->count() }})</h2>

            @if ($appointments->isEmpty())
                <p class="empty">Aucun rendez-vous prevu.</p>
            @else
                <ul class="payments">
                    @foreach ($appointments as $rdv)
                        <li>
                            <strong>{{ $rdv->scheduled_at->format('d/m/Y a H:i') }}</strong>
                            - {{ $rdv->service->name }}
                            <span>{{ $rdv->authorName() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="card">
            <h2 class="card__subtitle">Mes ordonnances ({{ $prescriptions->count() }})</h2>

            @if ($prescriptions->isEmpty())
                <p class="empty">Aucune ordonnance.</p>
            @else
                <ul class="referrals">
                    @foreach ($prescriptions as $ordonnance)
                        <li class="referrals__item">
                            <p class="referrals__meta">
                                {{ $ordonnance->issued_on?->format('d/m/Y') }}
                                - {{ $ordonnance->doctor?->displayName() }}
                            </p>
                            <ol class="ordo-lu">
                                @foreach ($ordonnance->items as $ligne)
                                    <li>{{ $ligne->medication_name }}@if ($ligne->posology()) - {{ $ligne->posology() }}@endif</li>
                                @endforeach
                            </ol>

                            <a href="{{ route('portal.prescription.pdf', [$patient->portal_token, $ordonnance]) }}"
                               class="attachments__link">
                                Telecharger l'ordonnance
                                <span class="attachments__size">PDF</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Resultats d'analyses (v3.4). Seules les demandes rendues figurent
             ici : une analyse encore au laboratoire n'a rien a dire au
             patient, et l'annoncer « en attente » ne ferait qu'inquieter. --}}
        <section class="card">
            <h2 class="card__subtitle">Mes resultats d'analyses ({{ $labOrders->count() }})</h2>

            @if ($labOrders->isEmpty())
                <p class="empty">Aucun resultat d'analyse.</p>
            @else
                <ul class="referrals">
                    @foreach ($labOrders as $analyse)
                        <li class="referrals__item">
                            <p class="referrals__meta">
                                {{ $analyse->completed_at?->format('d/m/Y') ?? $analyse->requested_at?->format('d/m/Y') }}
                                @if ($analyse->doctor) - demande par {{ $analyse->doctor->displayName() }} @endif
                            </p>

                            @if (filled($analyse->conclusion))
                                <p class="resultats__conclusion">{{ $analyse->conclusion }}</p>
                            @endif

                            @foreach ($analyse->items as $examen)
                                @php $mesures = $examen->results->whereNotNull('validated_at'); @endphp

                                <p class="resultats__examen">{{ $examen->exam_name }}</p>

                                @if ($mesures->isEmpty())
                                    <p class="hint">
                                        {{ $examen->results->isEmpty()
                                            ? 'Resultat non chiffre : voyez le compte rendu ci-dessus.'
                                            : 'Valeurs en cours de validation par le biologiste.' }}
                                    </p>
                                @else
                                    <ul class="resultats">
                                        @foreach ($mesures as $mesure)
                                            <li class="resultats__ligne">
                                                <span class="resultats__parametre">{{ $mesure->parameter }}</span>
                                                <span class="resultats__valeur">
                                                    {{ $mesure->value }}@if ($mesure->unit) {{ $mesure->unit }}@endif
                                                </span>
                                                @if ($mesure->reference_range)
                                                    <span class="resultats__reference">normale : {{ $mesure->reference_range }}</span>
                                                @endif
                                                @if ($mesure->isAbnormal())
                                                    <span class="resultats__ecart">{{ $mesure->flagLabel() }}</span>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            @endforeach

                            <a href="{{ route('portal.lab.pdf', [$patient->portal_token, $analyse]) }}"
                               class="attachments__link">
                                Telecharger le compte rendu
                                <span class="attachments__size">PDF</span>
                            </a>
                        </li>
                    @endforeach
                </ul>

                <p class="hint">
                    Ces resultats se lisent avec le medecin qui les a demandes :
                    une valeur hors normale n'est pas, a elle seule, un diagnostic.
                </p>
            @endif
        </section>

        {{-- Comptes rendus d'imagerie (v3.4). Un brouillon reste au dossier
             medical : le radiologue le reprend et le corrige, et ce n'est
             qu'une fois arrete qu'il devient la parole de l'etablissement. --}}
        <section class="card">
            <h2 class="card__subtitle">Mes comptes rendus d'examens ({{ $imagingReports->count() }})</h2>

            @if ($imagingReports->isEmpty())
                <p class="empty">Aucun compte rendu d'examen.</p>
            @else
                <ul class="referrals">
                    @foreach ($imagingReports as $compteRendu)
                        <li class="referrals__item">
                            <p class="referrals__meta">
                                {{ $compteRendu->reported_at?->format('d/m/Y') }}
                                @if ($compteRendu->order) - {{ $compteRendu->order->modalityLabel() }} @endif
                                @if ($compteRendu->order?->body_site) ({{ $compteRendu->order->body_site }}) @endif
                            </p>

                            @if (filled($compteRendu->technique))
                                <p class="resultats__examen">Technique</p>
                                <p class="resultats__conclusion">{{ $compteRendu->technique }}</p>
                            @endif

                            @if (filled($compteRendu->findings))
                                <p class="resultats__examen">Resultats</p>
                                <p class="resultats__conclusion">{{ $compteRendu->findings }}</p>
                            @endif

                            {{-- La conclusion n'est repetee que si elle differe des
                                 resultats : le technicien qui rend depuis WorkFlow
                                 saisit un texte unique, et l'afficher deux fois
                                 ferait croire a deux avis distincts. --}}
                            @if (filled($compteRendu->conclusion) && $compteRendu->conclusion !== $compteRendu->findings)
                                <p class="resultats__examen">Conclusion</p>
                                <p class="resultats__conclusion">{{ $compteRendu->conclusion }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Documents du dossier medical (v3.4), a distinguer des pieces
             jointes de WorkFlow affichees plus bas : celles-ci accompagnent un
             renvoi et circulent avec le patient, ceux-la restent au dossier. --}}
        <section class="card">
            <h2 class="card__subtitle">Les documents de mon dossier medical ({{ $documents->count() }})</h2>

            @if ($documents->isEmpty())
                <p class="empty">Aucun document au dossier medical.</p>
            @else
                <ul class="attachments">
                    @foreach ($documents as $document)
                        <li>
                            <a href="{{ route('portal.document', [$patient->portal_token, $document]) }}"
                               class="attachments__link">
                                {{ $document->title }}
                                <span class="attachments__size">
                                    {{ $document->typeLabel() }} - {{ $document->humanSize() }}
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="card">
            <h2 class="card__subtitle">Mes pieces jointes ({{ $attachments->count() }})</h2>

            @if ($attachments->isEmpty())
                <p class="empty">Aucune piece jointe.</p>
            @else
                <ul class="attachments">
                    @foreach ($attachments as $piece)
                        <li>
                            <a href="{{ route('portal.attachment', [$patient->portal_token, $piece]) }}"
                               class="attachments__link">
                                {{ $piece->original_name }}
                                <span class="attachments__size">{{ $piece->humanSize() }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- « Donner votre avis » (v3.2.8, point 4) : une section de ce portail
             plutot qu'un second systeme d'acces, le patient est deja
             identifie ici, par le lien et le code qu'il possede. --}}
        @livewire('portal.patient-feedback-form', ['patientId' => $patient->id], key('avis-'.$patient->id))
    @endif
</div>
