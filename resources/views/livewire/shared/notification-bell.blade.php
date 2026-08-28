{{-- Cloche de notification (v3.2.3, point 2).

     Le son est joue par le navigateur, sur evenement Livewire : le composant
     n'emet `notification-nouvelle` que lorsque le total de non-lues augmente,
     jamais a chaque sondage.

     Les navigateurs refusent de lire un son tant que la page n'a recu aucune
     interaction. Le personnel s'etant deja connecte, la condition est remplie
     en pratique ; `catch` couvre le cas contraire — une cloche muette vaut
     mieux qu'une erreur JavaScript dans la console d'un poste de soins. --}}
<div class="bell" wire:poll.{{ config('keneya.poll_interval') }}="refresh"
     x-data="{
        son: null,
        jouer() {
            const url = @js(notification_sound_url());
            if (! url) return;
            try {
                this.son ??= new Audio(url);
                this.son.currentTime = 0;
                // `play()` renvoie une promesse rejetee si le navigateur
                // bloque : on l'absorbe, sans rien afficher.
                this.son.play().catch(() => {});
            } catch (e) { /* pas de son, pas d'erreur */ }
        },
     }"
     @notification-nouvelle.window="jouer()"
     @keydown.escape.window="$wire.close()">

    <button type="button" class="icon-btn bell__button" wire:click="toggle"
            data-testid="notification-bell"
            aria-haspopup="true" aria-expanded="{{ $open ? 'true' : 'false' }}"
            aria-label="{{ $unread > 0 ? $unread.' notification(s) non lue(s)' : 'Notifications' }}"
            title="Notifications">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9" />
            <path d="M13.73 21a2 2 0 0 1-3.46 0" />
        </svg>

        @if ($unread > 0)
            <span class="bell__badge" aria-hidden="true">{{ $unread > 99 ? '99+' : $unread }}</span>
        @endif
    </button>

    @if ($open)
        <div class="bell__panel" role="dialog" aria-label="Notifications recentes">
            <div class="bell__head">
                <strong>Notifications</strong>
                <button type="button" class="alert__dismiss" wire:click="close"
                        aria-label="Fermer" title="Fermer">&times;</button>
            </div>

            @forelse ($notifications as $notification)
                <a class="bell__item @if ($notification->read_at === null) bell__item--unread @endif"
                   href="{{ $notification->link ?? '#' }}" wire:key="notif-{{ $notification->id }}">
                    <span class="bell__type">{{ $notification->typeLabel() }}</span>
                    <span class="bell__title">{{ $notification->title }}</span>
                    <time class="bell__time">{{ $notification->created_at->format('d/m H:i') }}</time>
                </a>
            @empty
                <p class="empty">Aucune notification.</p>
            @endforelse
        </div>
    @endif
</div>
