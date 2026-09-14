{{-- Cloche de notification (v3.2.3, point 2).

     Le son est joue par le navigateur, sur evenement Livewire : le composant
     n'emet `notification-nouvelle` que lorsque le total de non-lues augmente,
     jamais a chaque sondage.

     Les navigateurs refusent de lire un son tant que la page n'a recu aucune
     interaction. Le personnel s'etant deja connecte, la condition est remplie
     en pratique ; `catch` couvre le cas contraire : une cloche muette vaut
     mieux qu'une erreur JavaScript dans la console d'un poste de soins. --}}
<div class="bell" wire:poll.{{ config('keneya.poll_interval') }}="refresh"
     x-data="{
        ...survol({ entree: 0 }),

        // C'est le navigateur qui ouvre et ferme le panneau, pas le serveur.
        //
        // Retirer le delai d'attente ne suffisait pas a rendre l'ouverture
        // instantanee : le panneau n'existait pas dans la page tant que le
        // serveur ne l'avait pas renvoye, et cet aller-retour se voyait — sept
        // dixiemes de seconde sur un poste de developpement. Il est desormais
        // rendu d'avance et seulement masque, donc il parait sous le curseur.
        //
        // `$wire` suit derriere, pour ce qui appartient vraiment au serveur :
        // marquer les notifications comme lues. Les deux etats peuvent donc
        // differer un instant, sans consequence — l'affichage ne depend que
        // d'`ouvert`.
        ouvert: @js($open),

        // Ce qu'on accepte en echange de l'ouverture au survol : elle marque
        // les notifications comme lues, donc un curseur qui traverse la barre
        // pour atteindre l'avatar fait retomber le badge au passage. Les
        // notifications restent toutes lisibles dans le panneau — c'est le
        // point rouge qui s'en va, pas leur contenu.
        basculer() {
            this.ouvert = ! this.ouvert;
            this.ouvert ? this.$wire.toggle() : this.$wire.close();
        },
        survolOuvre() {
            this.ouvert = true;
            if (! this.$wire.open) this.$wire.toggle();
        },
        survolFerme() {
            this.ouvert = false;
            if (this.$wire.open) this.$wire.close();
        },

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
     @pointerenter="survolEntre($event)"
     @pointerleave="survolSort($event)"
     @keydown.escape.window="ouvert = false; $wire.close()">

    <button type="button" class="icon-btn bell__button" @click="basculer()"
            data-testid="notification-bell"
            aria-haspopup="true" :aria-expanded="ouvert ? 'true' : 'false'"
            aria-label="{{ $unread > 0 ? $unread.' notification(s) non lue(s)' : 'Notifications' }}"
            title="Notifications">
        <x-icon name="cloche" size="22" />

        @if ($unread > 0)
            <span class="bell__badge" aria-hidden="true">{{ $unread > 99 ? '99+' : $unread }}</span>
        @endif
    </button>

    {{-- Rendu d'avance, masque tant qu'on ne le demande pas. `x-cloak` couvre
         le temps qui separe l'affichage de la page du demarrage d'Alpine :
         sans lui, le panneau apparaitrait une fraction de seconde au
         chargement de chaque page. --}}
    <div class="bell__panel" role="dialog" aria-label="Notifications recentes"
         x-show="ouvert" x-cloak>
        <div class="bell__head">
            <strong>Notifications</strong>
            <button type="button" class="alert__dismiss"
                    @click="ouvert = false" wire:click="close"
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
</div>
