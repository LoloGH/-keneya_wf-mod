@props(['points' => [], 'label' => '', 'unit' => '', 'height' => 48])

@php
    /**
     * Courbe d'évolution d'une constante (§20), rendue en SVG pur : pas
     * de librairie de graphiques à charger, un rendu identique à
     * l'impression, et un contenu accessible via le résumé textuel.
     */
    $values = collect($points)->filter(fn ($p) => $p['value'] !== null)->values();
    $count = $values->count();
    $min = $count ? (float) $values->min('value') : 0;
    $max = $count ? (float) $values->max('value') : 0;
    $span = max($max - $min, 0.0001);
    $width = 100;

    $coords = $values->map(function ($point, $index) use ($count, $min, $span, $width) {
        $x = $count > 1 ? ($index / ($count - 1)) * $width : $width / 2;
        $y = 100 - ((((float) $point['value'] - $min) / $span) * 80 + 10);
        return round($x, 2).','.round($y, 2);
    })->implode(' ');
@endphp

<div>
    <div class="flex items-baseline justify-between">
        <p class="text-xs font-medium text-ink-600">{{ $label }}</p>
        @if ($count)
            <p class="text-xs text-ink-500">
                {{ $values->last()['value'] }}{{ $unit ? ' '.$unit : '' }}
            </p>
        @endif
    </div>

    @if ($count >= 2)
        <svg viewBox="0 0 {{ $width }} 100" preserveAspectRatio="none"
             class="mt-1 w-full" style="height: {{ $height }}px" role="img"
             aria-label="{{ $label }} : évolution de {{ $values->first()['value'] }} à {{ $values->last()['value'] }} {{ $unit }} sur {{ $count }} relevés">
            <polyline points="{{ $coords }}" fill="none" stroke="var(--color-clinic-600)"
                      stroke-width="2.5" vector-effect="non-scaling-stroke"
                      stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <p class="text-[11px] text-ink-400">{{ $count }} relevés · min {{ $min }} · max {{ $max }}</p>
    @else
        <p class="mt-1 text-xs text-ink-400">Pas assez de relevés pour tracer une évolution.</p>
    @endif
</div>
