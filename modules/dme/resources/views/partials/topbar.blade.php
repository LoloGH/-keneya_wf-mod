@php
    /**
     * Barre supérieure (§7) : recherche globale, notifications, profil.
     * Le compteur de notifications est calculé une fois par requête.
     */
    $unreadCount = \Illuminate\Support\Facades\DB::table('dme_notifications')
        ->where('notifiable_type', auth()->user()->getMorphClass())
        ->where('notifiable_id', auth()->id())
        ->whereNull('read_at')
        ->count();
@endphp

<header class="k-no-print sticky top-0 z-20 flex h-20 items-center gap-3 border-b border-ink-200 bg-surface px-4 sm:px-6 lg:px-8">
    <button type="button" class="rounded-lg p-2 text-ink-600 hover:bg-ink-100 lg:hidden"
            @click="sidebarOpen = true" aria-label="Ouvrir la navigation">
        <x-dme::icon name="menu"/>
    </button>

    {{-- Recherche globale (§34) --}}
    <form action="{{ route('dme.search') }}" method="GET" class="min-w-0 flex-1 max-w-xl" role="search"
          x-data="globalSearch">
        <label for="recherche-globale" class="sr-only">Recherche globale</label>
        <div class="relative">
            <x-dme::icon name="search" class="pointer-events-none absolute top-1/2 left-3 h-4.5 w-4.5 -translate-y-1/2 text-ink-400"/>
            <input id="recherche-globale" type="search" name="q" x-model="term"
                   value="{{ request('q') }}"
                   @input="submitDebounced($event)"
                   placeholder="Rechercher un patient, une ordonnance, un examen..."
                   class="k-input pl-10" autocomplete="off">
        </div>
    </form>

    <div class="ml-auto flex items-center gap-1.5">
        {{-- Bascule clair / sombre, contre la cloche : deux réglages du poste,
             côte à côte. Le choix vit dans le navigateur, sous la même clé que
             l'application hôte — on le règle une fois, il vaut des deux
             côtés. --}}
        <button type="button"
                class="rounded-lg p-2 text-ink-600 hover:bg-ink-100"
                x-data="{
                    sombre: document.documentElement.dataset.theme === 'sombre',
                    bascule() {
                        this.sombre = ! this.sombre;
                        document.documentElement.dataset.theme = this.sombre ? 'sombre' : 'clair';
                        try {
                            localStorage.setItem('keneya.theme', this.sombre ? 'sombre' : 'clair');
                        } catch (e) {
                            // Navigation privée : le thème tient pour cette page.
                        }
                    },
                }"
                @click="bascule()"
                :aria-pressed="sombre ? 'true' : 'false'"
                :title="sombre ? 'Passer en clair' : 'Passer en sombre'"
                :aria-label="sombre ? 'Passer en mode clair' : 'Passer en mode sombre'">
            <svg x-show="! sombre" class="h-5 w-5" aria-hidden="true" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="1.7"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>
            </svg>
            <svg x-show="sombre" x-cloak class="h-5 w-5" aria-hidden="true" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="1.7"
                 stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="4"/>
                <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>
            </svg>
        </button>

        <a href="{{ route('dme.notifications.index') }}"
           class="relative rounded-lg p-2 text-ink-600 hover:bg-ink-100"
           aria-label="Notifications{{ $unreadCount ? " ({$unreadCount} non lues)" : '' }}">
            <x-dme::icon name="bell"/>
            @if ($unreadCount)
                <span class="absolute top-1 right-1 flex h-4 min-w-4 items-center justify-center rounded-full
                             bg-red-600 px-1 text-[10px] font-semibold text-white">
                    {{ min($unreadCount, 99) }}
                </span>
            @endif
        </a>

        {{-- Menu profil --}}
        <div class="relative" x-data="{ open: false }" @keydown.escape="open = false">
            <button type="button" @click="open = !open"
                    class="flex items-center gap-2 rounded-lg p-1.5 hover:bg-ink-100"
                    :aria-expanded="open" aria-haspopup="true">
                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-clinic-600 text-sm font-semibold text-white">
                    {{ auth()->user()->initials() }}
                </span>
                <span class="hidden text-left sm:block">
                    <span class="block text-sm font-medium text-ink-900">{{ auth()->user()->displayName() }}</span>
                    <span class="block text-xs text-ink-500">
                        {{ \Keneya\Dme\Support\Rbac::allRoleLabels()[auth()->user()->getRoleNames()->first()] ?? 'Utilisateur' }}
                    </span>
                </span>
            </button>

            <div x-show="open" x-cloak @click.outside="open = false"
                 class="absolute right-0 z-30 mt-2 w-64 rounded-xl border border-ink-200 bg-surface p-2 shadow-lg">
                <div class="border-b border-ink-100 px-3 py-2">
                    <p class="text-sm font-medium text-ink-900">{{ auth()->user()->displayName() }}</p>
                    <p class="truncate text-xs text-ink-500">{{ auth()->user()->email }}</p>
                    @if (auth()->user()->service)
                        <p class="mt-1 text-xs text-ink-500">{{ auth()->user()->service->name }}</p>
                    @endif
                </div>
                <a href="{{ route('dme.settings.index') }}" class="k-nav-link mt-1">
                    <x-dme::icon name="cog" class="h-4.5 w-4.5"/> Paramètres
                </a>
                {{-- La déconnexion appartient à l'application hôte : le module ne
                     l'expose qu'en mode autonome de développement, où il porte
                     lui-même la session. --}}
                @if (Route::has('dme.logout'))
                    <form action="{{ route('dme.logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="k-nav-link w-full text-left text-red-600 hover:bg-red-50">
                            <x-dme::icon name="logout" class="h-4.5 w-4.5"/> Se déconnecter
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</header>
