{{-- Analyse des retours.

     Pas de graphique, et c'est le bon choix plutot qu'une facilite : dix
     services et une poignee d'agents sont autant de classes qui portent chacune
     un sens. La forme juste est alors un tableau, avec une jauge par ligne pour
     comparer d'un coup d'oeil — un histogramme de dix barres nommees n'ajouterait
     que du decor.

     La jauge encode la note par sa LONGUEUR, jamais par sa couleur. Peindre en
     rouge l'agent le moins bien note serait un jugement rendu par une feuille de
     style, sur des effectifs souvent minuscules. Le chiffre et son effectif sont
     affiches ; c'est a l'humain de conclure. --}}
@php
    $jauge = fn (?float $note) => $note === null ? 0 : max(0, min(100, ($note / 5) * 100));
@endphp

<div class="pile">

    <x-card title="Periode observee" icon="planning">
        <div class="field-row">
            <x-field name="periode-analyse" label="Fenetre d'observation">
                <select id="periode-analyse" wire:model.live="periode">
                    @foreach ($periodes as $valeur => $libelle)
                        <option value="{{ $valeur }}">{{ $libelle }}</option>
                    @endforeach
                </select>
            </x-field>
        </div>
    </x-card>

    <x-card title="Ce que disent les retours" icon="pathologie">
        <div class="chiffres">
            <div class="chiffres__bloc">
                <span class="chiffres__valeur mono">
                    {{ $entete['note_globale'] === null ? '—' : number_format($entete['note_globale'], 2, ',', ' ') }}
                </span>
                <span class="chiffres__libelle">note moyenne du parcours, sur 5</span>
            </div>
            <div class="chiffres__bloc">
                <span class="chiffres__valeur mono">{{ number_format($entete['sondages'], 0, ',', ' ') }}</span>
                <span class="chiffres__libelle">sondages recus</span>
            </div>
            <div class="chiffres__bloc">
                <span class="chiffres__valeur mono">{{ number_format($entete['notes_par_poste'], 0, ',', ' ') }}</span>
                <span class="chiffres__libelle">notes par poste rencontre</span>
            </div>
            <div class="chiffres__bloc">
                <span class="chiffres__valeur mono">{{ number_format($entete['reclamations'], 0, ',', ' ') }}</span>
                <span class="chiffres__libelle">reclamations</span>
            </div>
            <div class="chiffres__bloc">
                <span class="chiffres__valeur mono">{{ number_format($entete['constats'], 0, ',', ' ') }}</span>
                <span class="chiffres__libelle">constats du personnel</span>
            </div>
        </div>

        <x-notice ton="alerte" title="Une moyenne n'est pas une evaluation">
            Sous {{ $effectifFiable }} avis, un seul patient pese plus d'un cinquieme du
            resultat. Les lignes concernees sont signalees « peu d'avis » : elles
            se lisent, elles ne se comparent pas. Un chiffre qui porte sur des
            personnes ne se manie pas comme un chiffre qui compte des tickets.
        </x-notice>
    </x-card>

    <x-card title="Par service" icon="services">
        <p class="hint">
            La note que les patients donnent a leur prise en charge, service par
            service. Les services dont personne n'a parle figurent a zero plutot
            que de disparaitre : les retirer donnerait un palmares ou ne
            resteraient que ceux qui ont fait parler d'eux.
        </p>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Service</th>
                        <th scope="col">Note moyenne</th>
                        <th scope="col">Sondages</th>
                        <th scope="col">Reclamations</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($services as $ligne)
                        <tr wire:key="svc-{{ $loop->index }}">
                            <th scope="row">{{ $ligne['nom'] }}</th>
                            <td>
                                @if ($ligne['moyenne'] === null)
                                    <span class="empty">Aucun avis</span>
                                @else
                                    <span class="jauge">
                                        <span class="jauge__piste" aria-hidden="true">
                                            <span class="jauge__valeur" style="width: {{ $jauge($ligne['moyenne']) }}%"></span>
                                        </span>
                                        <span class="jauge__note mono">{{ number_format($ligne['moyenne'], 2, ',', ' ') }}</span>
                                    </span>
                                @endif
                            </td>
                            <td class="mono">
                                {{ $ligne['sondages'] }}
                                @if ($ligne['sondages'] > 0 && ! $ligne['fiable'])
                                    <span class="badge badge--neutral">peu d'avis</span>
                                @endif
                            </td>
                            <td class="mono">{{ $ligne['reclamations'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>

    <x-card title="Par agent" icon="personnel">
        <p class="hint">
            Les notes que les patients ont donnees a chaque personne rencontree
            durant leur parcours. La source est la note par poste, et non la note
            globale du passage : celle-ci reprocherait a l'infirmier le temps
            d'attente a la caisse.
        </p>

        @if ($agents->isEmpty())
            <p class="empty">
                Aucune note nominative sur cette periode. Un sondage ne designe
                quelqu'un que si le dossier permet de l'identifier a l'etape notee.
            </p>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">Agent</th>
                            <th scope="col">Role</th>
                            <th scope="col">Note moyenne</th>
                            <th scope="col">Avis recus</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($agents as $ligne)
                            <tr wire:key="agt-{{ $loop->index }}">
                                <th scope="row">{{ $ligne['nom'] }}</th>
                                <td>{{ $ligne['role'] }}</td>
                                <td>
                                    <span class="jauge">
                                        <span class="jauge__piste" aria-hidden="true">
                                            <span class="jauge__valeur" style="width: {{ $jauge($ligne['moyenne']) }}%"></span>
                                        </span>
                                        <span class="jauge__note mono">{{ number_format($ligne['moyenne'], 2, ',', ' ') }}</span>
                                    </span>
                                </td>
                                <td class="mono">
                                    {{ $ligne['notes'] }}
                                    @unless ($ligne['fiable'])
                                        <span class="badge badge--neutral">peu d'avis</span>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <x-card title="Par etape du parcours" icon="file">
        <p class="hint">
            La meme matiere, lue autrement : non pas qui, mais quelle etape pese
            sur la satisfaction. Un accueil mal note partout ne designe personne
            en particulier — il designe l'accueil.
        </p>

        @if ($postes->isEmpty())
            <p class="empty">Aucune note par etape sur cette periode.</p>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">Etape</th>
                            <th scope="col">Note moyenne</th>
                            <th scope="col">Avis recus</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($postes as $ligne)
                            <tr wire:key="pst-{{ $loop->index }}">
                                <th scope="row">{{ $ligne['poste'] }}</th>
                                <td>
                                    <span class="jauge">
                                        <span class="jauge__piste" aria-hidden="true">
                                            <span class="jauge__valeur" style="width: {{ $jauge($ligne['moyenne']) }}%"></span>
                                        </span>
                                        <span class="jauge__note mono">{{ number_format($ligne['moyenne'], 2, ',', ' ') }}</span>
                                    </span>
                                </td>
                                <td class="mono">
                                    {{ $ligne['notes'] }}
                                    @unless ($ligne['fiable'])
                                        <span class="badge badge--neutral">peu d'avis</span>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</div>
