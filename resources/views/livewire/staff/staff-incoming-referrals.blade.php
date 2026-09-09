<x-card title="Renvois recus ({{ $referrals->count() }})" icon="services" :poll="config('keneya.poll_interval')">

    @forelse ($referrals as $referral)
        <article class="referrals__item">
            <p class="referrals__meta">
                <strong>{{ $referral->patient->name }}</strong>
                <span class="mono">{{ $referral->patient->patient_code }}</span> :
                envoye par {{ $referral->prescriberName() }} ({{ $referral->fromService->name }})
            </p>
            <p class="referrals__result">{{ $referral->instructions }}</p>

            @include('partials.examination-brief', ['referral' => $referral])

            @if ($answeringReferralId === $referral->id)
                <form wire:submit="submitResult" class="form my-patients__form">
                    <div class="field">
                        <label for="staff-result-{{ $referral->id }}">Resultat</label>
                        <textarea id="staff-result-{{ $referral->id }}" rows="4" wire:model="resultText"></textarea>
                        @error('resultText') <p class="field__error">{{ $message }}</p> @enderror
                    </div>

                    {{-- Le compte rendu part au dossier medical du patient, ou
                         il restera, et non en piece jointe d'un renvoi, qui
                         n'est qu'un mouvement du parcours (v3.3.1). --}}
                    <div class="field">
                        <label for="staff-titre-{{ $referral->id }}">
                            Titre du compte rendu <span class="field__hint">(facultatif)</span>
                        </label>
                        <input type="text" id="staff-titre-{{ $referral->id }}" wire:model="documentTitle"
                               placeholder="Compte rendu d'echographie">
                        @error('documentTitle') <p class="field__error">{{ $message }}</p> @enderror
                    </div>

                    <div class="field">
                        <label for="staff-files-{{ $referral->id }}">
                            Comptes rendus et resultats
                            <span class="field__hint">Verses au dossier medical du patient. 5 fichiers au plus</span>
                        </label>
                        <input id="staff-files-{{ $referral->id }}" type="file" multiple
                               accept=".pdf,.jpg,.jpeg,.png" wire:model="files">
                        @error('files.*') <p class="field__error">{{ $message }}</p> @enderror
                        <p class="hint" wire:loading wire:target="files">Televersement en cours...</p>
                    </div>

                    <div class="btn-row">
                        <button type="submit" class="btn btn--primary">Enregistrer le resultat</button>
                        <button type="button" class="btn btn--ghost" wire:click="cancel">Annuler</button>
                    </div>
                </form>
            @else
                <button type="button" class="btn btn--secondary"
                        wire:click="startAnswer({{ $referral->id }})">Saisir le resultat</button>
            @endif
        </article>
    @empty
        <p class="empty">Aucun renvoi en attente.</p>
    @endforelse
</x-card>
