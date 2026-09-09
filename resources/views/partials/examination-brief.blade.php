{{--
    La demande d'examen telle que le technicien la recoit (v3.3.1).

    Elle voyage avec le patient : le plateau lit ce qu'on lui demande sans
    ouvrir un autre ecran, et sans avoir a le deviner des « instructions ».

    Un renvoi ordinaire n'en porte pas : le bloc disparait alors entierement,
    plutot que d'afficher un cadre vide.

    Attend : $referral
--}}
@php
    $demande = $referral->examinationOrder();
@endphp

@if ($demande)
    <div class="panel panel--request">
        <p class="panel__title">
            <span class="mono">{{ $demande->order_number }}</span>
            @if ($referral->dme_lab_order_id)
                - Analyses demandees
            @else
                - {{ \Keneya\Dme\Models\ImagingOrder::MODALITIES[$demande->modality] ?? $demande->modality }}
                @if ($demande->body_site) : {{ $demande->body_site }} @endif
            @endif
            <span class="badge">{{ \App\Actions\Dme\OrderLaboratory::PRIORITIES[$demande->priority] ?? $demande->priority }}</span>
        </p>

        @if ($referral->dme_lab_order_id)
            <ol class="ordo-lu">
                @foreach ($demande->items as $analyse)
                    <li>
                        {{ $analyse->exam_name }}
                        @if ($analyse->category)
                            <span class="muted">- {{ $analyse->category }}</span>
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif

        @if ($demande->indication)
            <p class="hint">Indication : {{ $demande->indication }}</p>
        @endif
    </div>
@endif
