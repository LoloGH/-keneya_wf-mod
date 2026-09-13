@props(['label', 'value', 'unit' => null, 'abnormal' => false, 'hint' => null])

{{-- Carte de constante vitale (§20). Une valeur hors norme est signalée
     visuellement ET textuellement, jamais par la couleur seule (§48). --}}
<div class="rounded-lg border px-3 py-2.5 {{ $abnormal ? 'border-amber-300 bg-amber-50' : 'border-ink-200 bg-surface' }}">
    <p class="text-xs font-medium text-ink-500">{{ $label }}</p>
    <p class="mt-0.5 flex items-baseline gap-1">
        <span class="text-lg font-semibold {{ $abnormal ? 'text-amber-800' : 'text-ink-900' }}">
            {{ $value ?? '-' }}
        </span>
        @if ($unit && $value !== null)
            <span class="text-xs text-ink-500">{{ $unit }}</span>
        @endif
    </p>
    @if ($abnormal)
        <p class="mt-0.5 text-[11px] font-medium text-amber-700">Hors intervalle de référence</p>
    @elseif ($hint)
        <p class="mt-0.5 text-[11px] text-ink-400">{{ $hint }}</p>
    @endif
</div>
