<section class="card">
    <h2 class="card__title">Relèves — {{ $service->name }}</h2>

    @forelse ($sejours as $sejour)
        <article class="episode" wire:key="handoff-sejour-{{ $sejour->id }}">
            <header class="episode__head">
                <span class="episode__service">{{ $sejour->patient->name }}</span>
                <span class="mono">{{ $sejour->patient->patient_code }}</span>
                <span class="badge badge--waiting">{{ $sejour->room?->name ?? 'Sans salle' }}</span>
                <time>Depuis le {{ $sejour->admitted_at->format('d/m/Y') }}</time>
            </header>

            @livewire('shared.handoff-notes', [
                'hospitalizationId' => $sejour->id,
                'flashKey' => 'staff',
            ], key('staff-handoff-'.$sejour->id))
        </article>
    @empty
        <p class="empty">Aucun patient hospitalise dans ce service.</p>
    @endforelse
</section>
