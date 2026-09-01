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

            {{-- Une note par poste rencontre (v3.2.9, point 3), et non plus
                 une note unique « le personnel » : elle melait dans un seul
                 chiffre l'agent d'accueil, le caissier et le medecin. Les
                 etapes proposees sont celles que le dossier porte a cet
                 instant — un sondage lance avant la cloture n'en montre donc
                 qu'une partie. --}}
            @if ($steps->isNotEmpty())
                <h3 class="card__subtitle">Les personnes rencontrees</h3>
                <p class="hint">Notez ce que vous souhaitez : aucune etape n'est obligatoire.</p>

                @foreach ($steps as $etape)
                    <div class="etape">
                        <div class="field">
                            <label for="etape-{{ $loop->index }}">{{ $etape['label'] }}</label>
                            <select id="etape-{{ $loop->index }}" wire:model="stepRatings.{{ $etape['key'] }}">
                                <option value="">— Sans avis —</option>
                                @foreach ([5 => 'Tres satisfait', 4 => 'Satisfait', 3 => 'Correct', 2 => 'Peu satisfait', 1 => 'Pas satisfait'] as $note => $libelle)
                                    <option value="{{ $note }}">{{ $note }} — {{ $libelle }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="field">
                            <label for="etape-com-{{ $loop->index }}">
                                Precision <span class="field__hint">(facultatif)</span>
                            </label>
                            <input id="etape-com-{{ $loop->index }}" type="text" maxlength="500"
                                   wire:model="stepComments.{{ $etape['key'] }}">
                        </div>
                    </div>
                @endforeach
            @endif
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
