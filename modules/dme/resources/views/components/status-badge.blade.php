@props(['status', 'label' => null])

@php
    /**
     * Badge d'état unifié (§49).
     *
     * La correspondance état -> couleur est centralisée ici afin qu'un
     * même statut soit toujours rendu de la même façon dans toute
     * l'application : « validé » ne peut pas être vert ici et gris ailleurs.
     */
    $tone = match ($status) {
        'completed', 'validated', 'confirmed', 'delivered', 'final', 'signed',
        'active', 'available', 'discharged', 'dispensed', 'resolved', 'normal' => 'success',

        'in_progress', 'scheduled', 'requested', 'queued', 'pending', 'accepted', 'sent',
        'draft', 'admitted', 'performed', 'reported', 'controlled' => 'info',

        'suspected', 'suspended', 'low', 'high', 'urgent', 'no_show', 'warning' => 'warning',

        'cancelled', 'failed', 'critical', 'severe', 'deceased', 'vital', 'denied' => 'danger',

        default => 'neutral',
    };
@endphp

<span {{ $attributes->merge(['class' => "k-badge-{$tone}"]) }}>
    @if ($tone === 'danger')
        <x-dme::icon name="alert" class="h-3 w-3"/>
    @endif
    {{ $label ?? $slot }}
</span>
