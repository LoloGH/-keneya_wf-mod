@php
    use App\Models\Service;
    use App\Models\ServiceKind;

    // Toutes les caisses declarees, pas seulement les deux caisses d'origine :
    // l'administrateur peut en creer une troisieme, elle doit alors etre tenue
    // depuis cette interface comme les autres. Une caisse par section, un seul
    // role les voit toutes.
    $caisses = Service::ofKindSlug(ServiceKind::SLUG_CAISSE)->orderBy('name')->get();

    $sections = $caisses
        ->map(fn (Service $caisse) => [
            'key' => 'caisse-'.$caisse->getKey(),
            'label' => $caisse->name,
            'view' => 'sections.caisse.queue',
            'context' => ['caisseServiceId' => $caisse->getKey()],
        ])
        ->push(['key' => 'planning', 'label' => 'Mon planning', 'view' => 'sections.caisse.schedule'])
        ->all();
@endphp

<x-layouts.app :title="'Caisse — '.config('keneya.name')">
    @if (session('caisse.status'))
        <div class="alert alert--success" role="status">{{ session('caisse.status') }}</div>
    @endif
    @if (session('caisse.error'))
        <div class="alert alert--error" role="alert">{{ session('caisse.error') }}</div>
    @endif

    @if ($caisses->isEmpty())
        <div class="alert alert--error" role="alert">
            Aucune caisse n'est configuree. Demandez a l'administrateur de creer
            les services « {{ Service::CAISSE_TICKET }} » et « {{ Service::CAISSE_SERVICES }} ».
        </div>
    @else
        @livewire('shared.vertical-tab-nav', ['sections' => $sections], key('nav-caisse'))
    @endif
</x-layouts.app>
