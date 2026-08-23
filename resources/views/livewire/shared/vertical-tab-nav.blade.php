{{-- Navigation verticale + panneau actif.
     Le tiroir mobile est pilote par Alpine : sur tablette la barre laterale ne
     doit pas manger une portion fixe de l'ecran. --}}
<div class="workspace" x-data="{ drawer: false }" @keydown.escape.window="drawer = false">

    <button type="button" class="workspace__toggle" @click="drawer = !drawer"
            :aria-expanded="drawer ? 'true' : 'false'" aria-controls="nav-sections">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" aria-hidden="true">
            <path d="M4 7h16M4 12h16M4 17h16" />
        </svg>
        <span x-text="drawer ? 'Masquer les sections' : 'Sections'">Sections</span>
        <span class="workspace__toggle-current">{{ $this->activeLabel() }}</span>
    </button>

    <nav id="nav-sections" class="tabnav" :class="drawer && 'tabnav--open'"
         aria-label="Sections de cet espace">
        <ul class="tabnav__list">
            @foreach ($sections as $section)
                @php $isGroup = ! empty($section['children']); @endphp

                <li class="tabnav__item" wire:key="sec-{{ $section['key'] }}">
                    @if ($isGroup)
                        @php $open = in_array($section['key'], $expanded, true); @endphp

                        <button type="button" class="tabnav__group"
                                wire:click="toggleGroup('{{ $section['key'] }}')"
                                aria-expanded="{{ $open ? 'true' : 'false' }}"
                                aria-controls="grp-{{ $section['key'] }}">
                            <svg class="tabnav__chevron @if ($open) tabnav__chevron--open @endif"
                                 viewBox="0 0 24 24" width="16" height="16" fill="none"
                                 stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                                 stroke-linejoin="round" aria-hidden="true">
                                <path d="M9 6l6 6-6 6" />
                            </svg>
                            <span>{{ $section['label'] }}</span>
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
                            {{ $section['label'] }}
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
            @include($view, $context)
        @else
            <p class="empty">Aucune section a afficher.</p>
        @endif
    </section>
</div>
