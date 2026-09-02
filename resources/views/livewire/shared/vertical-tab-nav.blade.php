{{-- Navigation verticale + panneau actif.
     Le tiroir mobile est pilote par Alpine : sur tablette la barre laterale ne
     doit pas manger une portion fixe de l'ecran. --}}
{{-- `replie` est memorise dans le navigateur : une barre qu'on replie a chaque
     changement de section ne rend pas le service qu'on lui demande. Le
     stockage peut echouer (navigation privee, site data bloque) : on retombe
     alors simplement sur « depliee », sans rien casser.

     Le glissement horizontal ouvre et ferme : vers la droite on deplie, vers la
     gauche on replie. Le bouton fait la meme chose pour qui prefere cliquer, et
     reste le seul chemin accessible au clavier. --}}
<div class="workspace" :class="replie && 'workspace--replie'"
     x-data="{
        drawer: false,
        replie: false,
        depart: null,
        init() {
            try { this.replie = localStorage.getItem('keneya.nav.replie') === '1'; } catch (e) {}
        },
        basculer() {
            this.replie = ! this.replie;
            try { localStorage.setItem('keneya.nav.replie', this.replie ? '1' : '0'); } catch (e) {}
        },
        // Le relachement est ecoute sur la fenetre et non sur la barre : une
        // barre repliee ne fait que 66 px de large, et un geste vers la droite
        // se termine donc hors d'elle. Ecoute sur la barre seule, l'evenement
        // n'arrivait jamais et le glissement ne faisait rien.
        prise(e) { this.depart = e.clientX; },
        relache(e) {
            if (this.depart === null) return;
            const parcouru = e.clientX - this.depart;
            this.depart = null;
            // 40 px : au-dela d'un tremblement de main, en deca d'un geste ample.
            if (Math.abs(parcouru) < 40) return;
            const veutReplier = parcouru < 0;
            if (veutReplier !== this.replie) this.basculer();
        },
     }"
     @keydown.escape.window="drawer = false">

    <button type="button" class="workspace__toggle" @click="drawer = !drawer"
            :aria-expanded="drawer ? 'true' : 'false'" aria-controls="nav-sections">
        <x-icon name="file" size="22" />
        <span x-text="drawer ? 'Masquer les sections' : 'Sections'">Sections</span>
        <span class="workspace__toggle-current">{{ $this->activeLabel() }}</span>
    </button>

    <nav id="nav-sections" class="tabnav" :class="drawer && 'tabnav--open'"
         aria-label="Sections de cet espace"
         @pointerdown="prise($event)" @pointerup.window="relache($event)">

        {{-- Le bouton de repli n'apparait qu'a partir de la tablette en
             paysage : sur petit ecran la barre est deja un tiroir, la replier
             n'aurait aucun sens. --}}
        <button type="button" class="tabnav__replier" @click="basculer()"
                :aria-expanded="replie ? 'false' : 'true'" aria-controls="nav-sections"
                :aria-label="replie ? 'Deplier la navigation' : 'Replier la navigation'"
                :title="replie ? 'Deplier la navigation' : 'Replier la navigation'">
            <x-icon name="chevron" size="16" class="tabnav__replier-icone" />
        </button>

        <ul class="tabnav__list">
            @php $familleRendue = null; @endphp

            @foreach ($sections as $section)
                @php $isGroup = ! empty($section['children']); @endphp

                {{-- Une entree peut annoncer la famille qui commence avec elle
                     (« ETABLISSEMENT », « GESTION », « SYSTEME »). C'est une
                     simple cle facultative : l'arbre de sections garde
                     exactement la meme forme, et une interface qui n'en pose
                     aucune — /caisse et ses trois sections — n'affiche aucun
                     intitule plutot qu'un decoupage qui n'apprendrait rien. --}}
                @if (($section['famille'] ?? null) && $section['famille'] !== $familleRendue)
                    @php $familleRendue = $section['famille']; @endphp
                    <li class="tabnav__famille" aria-hidden="true">{{ $section['famille'] }}</li>
                @endif

                <li class="tabnav__item" wire:key="sec-{{ $section['key'] }}">
                    @if ($isGroup)
                        @php $open = in_array($section['key'], $expanded, true); @endphp

                        <button type="button" class="tabnav__group"
                                wire:click="toggleGroup('{{ $section['key'] }}')"
                                aria-expanded="{{ $open ? 'true' : 'false' }}"
                                aria-controls="grp-{{ $section['key'] }}">
                            <x-icon name="{{ $section['icon'] ?? 'chevron' }}" size="18" class="tabnav__icone" />
                            <span>{{ $section['label'] }}</span>
                            {{-- Liaison `:class` et non un `@if` dans l'attribut : les
                                 attributs d'un composant Blade sont analyses, une
                                 directive glissee dedans ne compile pas. --}}
                            <x-icon name="chevron" size="16"
                                    :class="$open ? 'tabnav__chevron tabnav__chevron--open' : 'tabnav__chevron'" />
                        </button>

                        @if ($open)
                            <ul class="tabnav__list tabnav__list--nested" id="grp-{{ $section['key'] }}">
                                @foreach ($section['children'] as $child)
                                    <li wire:key="sec-{{ $child['key'] }}">
                                        <button type="button"
                                                class="tabnav__link @if ($active === $child['key']) tabnav__link--active @endif"
                                                wire:click="select('{{ $child['key'] }}')"
                                                @click="drawer = false"
                                                @if ($active === $child['key']) aria-current="page" @endif>
                                            {{ $child['label'] }}
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    @else
                        <button type="button"
                                class="tabnav__link @if ($active === $section['key']) tabnav__link--active @endif"
                                wire:click="select('{{ $section['key'] }}')"
                                @click="drawer = false"
                                @if ($active === $section['key']) aria-current="page" @endif>
                            <x-icon name="{{ $section['icon'] ?? 'chevron' }}" size="18" class="tabnav__icone" />
                            <span>{{ $section['label'] }}</span>
                        </button>
                    @endif
                </li>
            @endforeach
        </ul>
    </nav>

    {{-- Un seul panneau rendu a la fois : les sections inactives ne sont pas
         seulement masquees, elles ne sont pas montees — pas de wire:poll qui
         continuerait a tourner dans le vide. --}}
    <section class="workspace__panel" aria-live="polite">
        {{-- Chaque section porte deja son titre de carte : ce titre-ci sert la
             structure du document et les lecteurs d'ecran, sans doubler le
             libelle a l'ecran. L'onglet actif indique visuellement ou l'on est. --}}
        <h1 class="page-title sr-only">{{ $this->activeLabel() }}</h1>

        @if ($view = $this->activeView())
            @include($view, $this->activeContext())
        @else
            <p class="empty">Aucune section a afficher.</p>
        @endif
    </section>
</div>
