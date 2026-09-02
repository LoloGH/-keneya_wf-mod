{{-- Navigation verticale + panneau actif.
     Le tiroir mobile est pilote par Alpine : sur tablette la barre laterale ne
     doit pas manger une portion fixe de l'ecran. --}}
<div class="workspace" x-data="{ drawer: false }" @keydown.escape.window="drawer = false">

    <button type="button" class="workspace__toggle" @click="drawer = !drawer"
            :aria-expanded="drawer ? 'true' : 'false'" aria-controls="nav-sections">
        <x-icon name="file" size="22" />
        <span x-text="drawer ? 'Masquer les sections' : 'Sections'">Sections</span>
        <span class="workspace__toggle-current">{{ $this->activeLabel() }}</span>
    </button>

    <nav id="nav-sections" class="tabnav" :class="drawer && 'tabnav--open'"
         aria-label="Sections de cet espace">
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
