<x-card class="card--danger" icon="suppression" title="Supprimer un dossier patient">
    {{-- Un bandeau et non un paragraphe : c'est l'ecran le plus destructeur du
         produit, l'avertissement doit se distinguer du texte d'aide ordinaire
         que l'oeil apprend a sauter. --}}
    <x-notice ton="blocage" title="Suppression definitive et en cascade">
        Passages, renvois, historique, pieces jointes, encaissements,
        ordonnances, rendez-vous et accompagnateurs. Rien n'est recuperable.
        Seul le journal d'audit conserve la trace de l'operation.
    </x-notice>

    <div class="field">
        <label for="deletion-search">Rechercher le dossier</label>
        <input id="deletion-search" type="search" wire:model.live.debounce.400ms="search"
               placeholder="N&deg; patient, nom ou telephone...">
    </div>

    @if ($matches->isNotEmpty() && ! $selected)
        <ul class="lookup">
            @foreach ($matches as $match)
                <li class="lookup__item">
                    <span class="lookup__identity">
                        <strong>{{ $match->name }}</strong>
                        <span class="mono">{{ $match->patient_code }}</span>
                        <span>{{ $match->age }} ans - {{ $match->mobile }}</span>
                    </span>
                    <button type="button" class="btn btn--ghost" wire:click="select({{ $match->id }})">
                        Selectionner
                    </button>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($selected)
        <div class="deletion__confirm">
            <h3 class="card__subtitle">Confirmer la suppression de {{ $selected->name }}</h3>

            <dl class="record__identity">
                <div><dt>N&deg; patient</dt><dd class="mono">{{ $selected->patient_code }}</dd></div>
                <div><dt>Passages</dt><dd>{{ $selected->visits_count }}</dd></div>
                <div><dt>Pieces jointes</dt><dd>{{ $selected->attachments_count }}</dd></div>
                <div><dt>Ordonnances</dt><dd>{{ $selected->prescriptions_count }}</dd></div>
                <div><dt>Rendez-vous</dt><dd>{{ $selected->appointments_count }}</dd></div>
            </dl>

            <form wire:submit="delete" class="form">
                <div class="field">
                    <label for="deletion-confirm">
                        Retapez l&rsquo;identifiant patient <strong class="mono">{{ $selected->patient_code }}</strong>
                    </label>
                    <input id="deletion-confirm" type="text" wire:model="confirmation" autocomplete="off">
                    @error('confirmation') <p class="field__error">{{ $message }}</p> @enderror
                </div>

                <div class="field">
                    <label for="deletion-reason">Motif de la suppression</label>
                    <textarea id="deletion-reason" rows="3" wire:model="reason"
                              placeholder="Doublon avec un autre dossier, demande du patient..."></textarea>
                    @error('reason') <p class="field__error">{{ $message }}</p> @enderror
                </div>

                <div class="btn-row">
                    <button type="submit" class="btn btn--danger"
                            wire:confirm="Cette suppression est definitive. Confirmer ?">
                        Supprimer definitivement
                    </button>
                    <button type="button" class="btn btn--ghost" wire:click="cancel">Annuler</button>
                </div>
            </form>
        </div>
    @endif
</x-card>
