<div class="portal">
    <section class="card">
        <h1 class="card__title">Votre avis, {{ $visitor->name }}</h1>
        <p class="hint">
            Vous avez ete recu(e) au service {{ $visitor->service?->name ?? 'de l\'hopital' }}.
            Votre reponse est transmise a la direction ; elle ne figure dans
            aucun dossier medical.
        </p>

        @if ($submitted)
            <div class="alert alert--success" role="status">
                Merci, votre message a bien ete transmis a la direction de l'hopital.
            </div>
        @endif

        {{-- Le lien recu par SMS reste cliquable apres reponse : la page doit
             donc dire ce qui a deja ete fait, plutot que reproposer un
             formulaire qui serait refuse. La reclamation, elle, reste ouverte. --}}
        @if ($sondageDonne)
            <x-notice title="Votre avis sur cette venue est enregistre">
                Merci, il n'y a rien d'autre a noter pour ce passage. Un nouveau
                sondage vous sera propose lors de votre prochaine venue.
            </x-notice>
        @endif

        <div class="btn-row">
            @unless ($sondageDonne)
                <button type="button"
                        class="btn {{ $type === \App\Models\FeedbackEntry::TYPE_SURVEY ? 'btn--primary' : 'btn--ghost' }}"
                        wire:click="selectType('{{ \App\Models\FeedbackEntry::TYPE_SURVEY }}')">
                    Noter mon passage
                </button>
            @endunless
            <button type="button"
                    class="btn {{ $type === \App\Models\FeedbackEntry::TYPE_COMPLAINT ? 'btn--primary' : 'btn--ghost' }}"
                    wire:click="selectType('{{ \App\Models\FeedbackEntry::TYPE_COMPLAINT }}')">
                Deposer une reclamation
            </button>
        </div>

        <form wire:submit="submit" class="form">
            @if ($type === \App\Models\FeedbackEntry::TYPE_SURVEY)
                <div class="field">
                    <label for="visiteur-care">Votre accueil</label>
                    <select id="visiteur-care" wire:model="ratingCare">
                        <option value="">Choisir une note</option>
                        @foreach ([5 => 'Tres satisfait', 4 => 'Satisfait', 3 => 'Correct', 2 => 'Peu satisfait', 1 => 'Pas satisfait'] as $note => $libelle)
                            <option value="{{ $note }}">{{ $note }} - {{ $libelle }}</option>
                        @endforeach
                    </select>
                    @error('ratingCare') <p class="field__error">{{ $message }}</p> @enderror
                </div>

                <div class="field">
                    <label for="visiteur-staff">Le personnel rencontre</label>
                    <select id="visiteur-staff" wire:model="ratingStaff">
                        <option value="">Choisir une note</option>
                        @foreach ([5 => 'Tres satisfait', 4 => 'Satisfait', 3 => 'Correct', 2 => 'Peu satisfait', 1 => 'Pas satisfait'] as $note => $libelle)
                            <option value="{{ $note }}">{{ $note }} - {{ $libelle }}</option>
                        @endforeach
                    </select>
                    @error('ratingStaff') <p class="field__error">{{ $message }}</p> @enderror
                </div>
            @endif

            <div class="field">
                <label for="visiteur-content">
                    {{ $type === \App\Models\FeedbackEntry::TYPE_SURVEY ? 'Commentaire (facultatif)' : 'Votre reclamation' }}
                </label>
                <textarea id="visiteur-content" rows="4" wire:model="content"
                          placeholder="Dites-nous ce qui s'est bien passe, ou ce qui devrait etre ameliore."></textarea>
                @error('content') <p class="field__error">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="btn btn--primary" wire:loading.attr="disabled">Envoyer</button>
        </form>
    </section>
</div>
