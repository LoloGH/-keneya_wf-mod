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
            <p class="hint mono">Dossier {{ $patient->patient_code }}</p>
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
                            — {{ $rdv->service->name }}
                            <span>{{ $rdv->doctor?->name() }}</span>
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
                                {{ $ordonnance->created_at->format('d/m/Y') }}
                                — {{ $ordonnance->doctor?->name() }}
                            </p>
                            <ol class="ordo-lu">
                                @foreach ($ordonnance->lignes() as $ligne)
                                    <li>{{ \App\Models\Prescription::ligneEnTexte($ligne) }}</li>
                                @endforeach
                            </ol>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="card">
            <h2 class="card__subtitle">Mes documents ({{ $attachments->count() }})</h2>

            @if ($attachments->isEmpty())
                <p class="empty">Aucun document.</p>
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
    @endif
</div>
