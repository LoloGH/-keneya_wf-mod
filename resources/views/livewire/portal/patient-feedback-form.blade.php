<section class="card">
    <h2 class="card__subtitle">Donner votre avis</h2>

    @if ($submitted)
        <div class="alert alert--success" role="status">
            Merci, votre message a bien ete transmis a la direction de l'hopital.
        </div>
    @endif

    <div class="btn-row">
        <button type="button"
                class="btn {{ $type === \App\Models\FeedbackEntry::TYPE_SURVEY ? 'btn--primary' : 'btn--ghost' }}"
                wire:click="selectType('{{ \App\Models\FeedbackEntry::TYPE_SURVEY }}')">
            Noter mon passage
        </button>
        <button type="button"
                class="btn {{ $type === \App\Models\FeedbackEntry::TYPE_COMPLAINT ? 'btn--primary' : 'btn--ghost' }}"
                wire:click="selectType('{{ \App\Models\FeedbackEntry::TYPE_COMPLAINT }}')">
            Deposer une reclamation
        </button>
    </div>

    <form wire:submit="submit" class="form">
        @if ($type === \App\Models\FeedbackEntry::TYPE_SURVEY)
            <div class="field">
                <label for="avis-care">Votre prise en charge</label>
                <select id="avis-care" wire:model="ratingCare">
                    <option value="">— Choisir une note —</option>
                    @foreach ([5 => 'Tres satisfait', 4 => 'Satisfait', 3 => 'Correct', 2 => 'Peu satisfait', 1 => 'Pas satisfait'] as $note => $libelle)
                        <option value="{{ $note }}">{{ $note }} — {{ $libelle }}</option>
                    @endforeach
                </select>
                @error('ratingCare') <p class="field__error">{{ $message }}</p> @enderror
            </div>

            <div class="field">
                <label for="avis-staff">Le personnel rencontre</label>
                <select id="avis-staff" wire:model="ratingStaff">
                    <option value="">— Choisir une note —</option>
                    @foreach ([5 => 'Tres satisfait', 4 => 'Satisfait', 3 => 'Correct', 2 => 'Peu satisfait', 1 => 'Pas satisfait'] as $note => $libelle)
                        <option value="{{ $note }}">{{ $note }} — {{ $libelle }}</option>
                    @endforeach
                </select>
                @error('ratingStaff') <p class="field__error">{{ $message }}</p> @enderror
            </div>
        @endif

        <div class="field">
            <label for="avis-content">
                {{ $type === \App\Models\FeedbackEntry::TYPE_SURVEY ? 'Commentaire (facultatif)' : 'Votre reclamation' }}
            </label>
            <textarea id="avis-content" rows="4" wire:model="content"
                      placeholder="Dites-nous ce qui s'est bien passe, ou ce qui devrait etre ameliore."></textarea>
            @error('content') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="btn btn--primary" wire:loading.attr="disabled">Envoyer</button>
    </form>
</section>
