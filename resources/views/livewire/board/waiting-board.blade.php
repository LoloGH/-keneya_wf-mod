<section class="{{ $fullscreen ? 'board board--fullscreen' : 'card board' }}"
         wire:poll.{{ config('keneya.board_poll_interval') }}>

    @if ($fullscreen)
        <header class="board__header">
            {{-- Fond sombre : variante claire. Le logo remplace le titre. --}}
            <x-brand-logo lockup variant="light" class="board__logo" />
            <p>{{ hospital_name() }}</p>
        </header>
    @else
        <h2 class="card__title">Salle d'attente</h2>
    @endif

    <div class="board__grid">
        @foreach ($rows as $row)
            <article class="board__service">
                <h3 class="board__service-name">{{ $row['service']->name }}</h3>

                <p class="board__current-label">En cours</p>
                <p class="board__current">{{ $row['current'] ?? '—' }}</p>

                <p class="board__next-label">Suivants ({{ $row['waiting_count'] }} en attente)</p>
                <p class="board__next">
                    @forelse ($row['next'] as $token)
                        <span class="board__next-token">{{ $token }}</span>
                    @empty
                        <span class="board__next-token board__next-token--empty">—</span>
                    @endforelse
                </p>
            </article>
        @endforeach
    </div>
</section>
