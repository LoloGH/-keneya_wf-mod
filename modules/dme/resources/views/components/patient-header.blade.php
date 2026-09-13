@props(['patient', 'compact' => false])

{{--
    Bandeau d'identité patient (§13).

    Il porte l'identitovigilance : nom, numéro de dossier, âge, sexe,
    groupe sanguin et médecin traitant sont visibles immédiatement, sans
    interaction, sur toutes les tailles d'écran.
--}}
<div class="flex flex-wrap items-start gap-4">
    <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-clinic-100
                 text-lg font-semibold text-clinic-700">
        {{ $patient->initials() }}
    </span>

    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
            <h2 class="text-lg font-semibold text-ink-900">{{ $patient->fullName() }}</h2>
            <span class="k-badge-neutral font-mono">{{ $patient->patient_number }}</span>
            @if ($patient->status !== 'active')
                <x-dme::status-badge :status="$patient->status" :label="ucfirst($patient->status)"/>
            @endif
        </div>

        <dl class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-sm text-ink-600">
            <div class="flex gap-1">
                <dt class="sr-only">Âge</dt>
                <dd>{{ $patient->ageLabel() }}</dd>
            </div>
            <div class="flex gap-1">
                <dt class="sr-only">Sexe</dt>
                <dd>{{ $patient->sexLabel() }}</dd>
            </div>
            @if ($patient->blood_group)
                <div class="flex gap-1">
                    <dt class="text-ink-400">Groupe</dt>
                    <dd class="font-medium text-ink-800">{{ $patient->blood_group }}</dd>
                </div>
            @endif
            @if ($patient->phone)
                <div class="flex gap-1">
                    <dt class="text-ink-400">Tél.</dt>
                    <dd>{{ $patient->phone }}</dd>
                </div>
            @endif
            @if (! $compact && $patient->id_card_number)
                <div class="flex gap-1">
                    <dt class="text-ink-400">Carte d'identité</dt>
                    <dd class="font-mono">{{ $patient->id_card_number }}</dd>
                </div>
            @endif
            @if (! $compact && $patient->attendingDoctor)
                <div class="flex gap-1">
                    <dt class="text-ink-400">Médecin traitant</dt>
                    <dd>{{ $patient->attendingDoctor->displayName() }}</dd>
                </div>
            @endif
        </dl>
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
