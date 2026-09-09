<x-card title="Renvois en attente ({{ $pending->count() }})" icon="services" :poll="config('keneya.poll_interval')">

    @if ($pending->isEmpty())
        <p class="empty">Aucun renvoi a traiter.</p>
    @else
        <ul class="referrals">
            @foreach ($pending as $referral)
                <li class="referrals__item">
                    <button type="button" class="referrals__patient"
                            wire:click="showRecord({{ $referral->patient_id }})">
                        <strong>{{ $referral->patient->name }}</strong>
                        <span class="mono">{{ $referral->patient->patient_code }}</span>
                    </button>

                    <p class="referrals__meta">
                        Envoye par {{ $referral->prescriberName() }}
                        ({{ $referral->fromService->name }})
                        — {{ $referral->created_at->format('d/m/Y H:i') }}
                    </p>

                    <p class="referrals__instructions">{{ $referral->instructions }}</p>

                    @include('partials.examination-brief', ['referral' => $referral])

                    @if ($answeringReferralId === $referral->id)
                        <form wire:submit="submitResult" class="form">
                            <div class="field">
                                <label for="result-{{ $referral->id }}">Resultat</label>
                                <textarea id="result-{{ $referral->id }}" rows="4" wire:model="resultText"
                                          placeholder="Conclusion de l'examen…"></textarea>
                                @error('resultText') <p class="field__error">{{ $message }}</p> @enderror
                            </div>

                            {{-- Le compte rendu part au dossier medical du
                                 patient, ou il restera — et non en piece
                                 jointe d'un renvoi, qui n'est qu'un mouvement
                                 du parcours (v3.3.1). --}}
                            <div class="field">
                                <label for="titre-{{ $referral->id }}">
                                    Titre du compte rendu
                                    <span class="field__hint">(facultatif)</span>
                                </label>
                                <input type="text" id="titre-{{ $referral->id }}" wire:model="documentTitle"
                                       placeholder="Compte rendu d'echographie">
                                @error('documentTitle') <p class="field__error">{{ $message }}</p> @enderror
                            </div>

                            <div class="field">
                                <label for="files-{{ $referral->id }}">
                                    Comptes rendus et resultats
                                    <span class="field__hint">Verses au dossier medical du patient. 5 fichiers au plus</span>
                                </label>
                                <input id="files-{{ $referral->id }}" type="file" multiple
                                       accept=".pdf,.jpg,.jpeg,.png" wire:model="files">
                                @error('files.*') <p class="field__error">{{ $message }}</p> @enderror
                                <p class="hint" wire:loading wire:target="files">Televersement en cours…</p>
                            </div>

                            <div class="btn-row">
                                <button type="submit" class="btn btn--primary" wire:loading.attr="disabled" wire:target="submitResult">
                                    Renvoyer le resultat
                                </button>
                                <button type="button" class="btn btn--ghost" wire:click="cancel">Annuler</button>
                            </div>
                        </form>
                    @else
                        <button type="button" class="btn btn--secondary"
                                wire:click="startAnswer({{ $referral->id }})">
                            Saisir le resultat
                        </button>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
