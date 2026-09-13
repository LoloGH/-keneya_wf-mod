@props(['patient'])

@php
    $allergies = $patient->criticalAllergies();
    $conditions = $patient->activeConditions();
@endphp

{{--
    Alertes cliniques permanentes (§13).

    Elles sont rendues avant tout contenu du dossier et restent affichées
    sur chaque onglet : une allergie sévère ne doit jamais dépendre d'un
    clic pour être vue.
--}}
@if ($allergies->isNotEmpty() || $conditions->isNotEmpty())
    <div class="grid gap-2 sm:grid-cols-2" role="region" aria-label="Alertes médicales">
        @foreach ($allergies as $allergy)
            <div class="{{ $allergy->severity === 'severe' ? 'k-alert-critical' : 'k-alert-warning' }}">
                <x-dme::icon name="alert" class="mt-0.5 h-5 w-5 shrink-0
                    {{ $allergy->severity === 'severe' ? 'text-red-600' : 'text-amber-600' }}"/>
                <div class="min-w-0">
                    <p class="text-xs font-bold tracking-wide uppercase
                        {{ $allergy->severity === 'severe' ? 'text-red-700' : 'text-amber-700' }}">
                        Allergie - {{ $allergy->severityLabel() }}
                    </p>
                    <p class="text-sm font-medium text-ink-900">{{ $allergy->allergen }}</p>
                    @if ($allergy->reaction)
                        <p class="text-xs text-ink-600">{{ $allergy->reaction }}</p>
                    @endif
                </div>
            </div>
        @endforeach

        @foreach ($conditions as $condition)
            <div class="k-alert-warning">
                <x-dme::icon name="heart" class="mt-0.5 h-5 w-5 shrink-0 text-amber-600"/>
                <div class="min-w-0">
                    <p class="text-xs font-bold tracking-wide text-amber-700 uppercase">Pathologie chronique</p>
                    <p class="text-sm font-medium text-ink-900">{{ $condition->label }}</p>
                    <p class="text-xs text-ink-600">{{ $condition->statusLabel() }}</p>
                </div>
            </div>
        @endforeach
    </div>
@endif
