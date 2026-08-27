{{-- Notes de releve d'un sejour (v3.2.3, point 4).

     Les plus recentes en haut : ce qui vient d'etre ecrit est ce que l'equipe
     qui arrive doit lire en premier. --}}
<div class="handoff">
    @if ($onDuty)
        <form wire:submit="save" class="form">
            <div class="field">
                <label for="handoff-{{ $hospitalizationId }}">
                    Ajouter une note de releve
                    <span class="field__hint">visible par tout le personnel de garde sur ce service</span>
                </label>
                <textarea id="handoff-{{ $hospitalizationId }}" rows="2" wire:model="content"
                          placeholder="A mal dormi, la famille passe ce matin…"></textarea>
                @error('content') <p class="field__error">{{ $message }}</p> @enderror
            </div>

            <div class="btn-row">
                <button type="submit" class="btn btn--secondary">Enregistrer la note</button>
            </div>
        </form>
    @else
        {{-- Hors garde, la note se lit mais ne s'ecrit pas : meme regle que
             pour les soins, et dite plutot que subie. --}}
        <p class="empty">
            Vous n'etes pas de garde sur ce service : les notes se lisent, elles
            ne s'ecrivent pas.
        </p>
    @endif

    @forelse ($notes as $note)
        <article class="handoff__note" wire:key="handoff-note-{{ $note->id }}">
            <header class="handoff__meta">
                <strong>{{ $note->writtenBy?->name ?? 'Compte supprime' }}</strong>
                <time>{{ $note->created_at->format('d/m/Y H:i') }}</time>
            </header>
            <p>{{ $note->content }}</p>
        </article>
    @empty
        <p class="empty">Aucune note de releve pour ce sejour.</p>
    @endforelse
</div>
