<section class="card" wire:poll.{{ config('keneya.poll_interval') }}>
    <h2 class="card__title">Mes renvois</h2>

    <h3 class="card__subtitle">En attente de resultat ({{ $pending->count() }})</h3>

    @if ($pending->isEmpty())
        <p class="empty">Aucun renvoi en attente.</p>
    @else
        <ul class="referrals">
            @foreach ($pending as $referral)
                <li class="referrals__item">
                    <button type="button" class="referrals__patient"
                            wire:click="showHistory({{ $referral->patient_id }})">
                        <strong>{{ $referral->patient->name }}</strong>
                        <span class="mono">{{ $referral->patient->patient_code }}</span>
                    </button>
                    <p class="referrals__meta">
                        Vers {{ $referral->toService->name }}
                        — {{ $referral->created_at->format('d/m/Y H:i') }}
                    </p>
                    <p class="referrals__instructions">{{ $referral->instructions }}</p>
                </li>
            @endforeach
        </ul>
    @endif

    <h3 class="card__subtitle">Resultats recus ({{ $results->count() }})</h3>

    @if ($results->isEmpty())
        <p class="empty">Aucun resultat recu.</p>
    @else
        <ul class="referrals">
            @foreach ($results as $referral)
                <li class="referrals__item referrals__item--done">
                    <button type="button" class="referrals__patient"
                            wire:click="showHistory({{ $referral->patient_id }})">
                        <strong>{{ $referral->patient->name }}</strong>
                        <span class="mono">{{ $referral->patient->patient_code }}</span>
                    </button>
                    <p class="referrals__meta">
                        {{ $referral->toService->name }}
                        @if ($referral->completedByDoctor)
                            — {{ $referral->completedByDoctor->name() }}
                        @endif
                        @if ($referral->completed_at)
                            — {{ $referral->completed_at->format('d/m/Y H:i') }}
                        @endif
                    </p>
                    <p class="referrals__result">{{ $referral->result_text }}</p>
                </li>
            @endforeach
        </ul>
    @endif
</section>
