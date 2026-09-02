@php
    use App\Models\Service;
    use App\Models\ServiceKind;

    // Toutes les caisses declarees, pas seulement les deux caisses d'origine :
    // l'administrateur peut en creer une troisieme, elle doit alors etre tenue
    // depuis cette interface comme les autres. Une caisse par section, un seul
    // role les voit toutes.
    // « Caisse Ticket » avant « Caisse Services » (v3.2.8, point 3) : c'est
    // l'ordre du parcours du patient, qui prend son ticket avant d'etre oriente
    // vers un acte. L'ordre alphabetique les presentait a l'envers. Les caisses
    // ajoutees ensuite par l'administrateur suivent, par nom.
    $ordre = [Service::CAISSE_TICKET => 0, Service::CAISSE_SERVICES => 1];

    $caisses = Service::ofKindSlug(ServiceKind::SLUG_CAISSE)
        ->orderBy('name')
        ->get()
        ->sortBy(fn (Service $caisse) => [$ordre[$caisse->name] ?? 2, $caisse->name])
        ->values();

    $sections = $caisses
        ->map(fn (Service $caisse) => [
            'key' => 'caisse-'.$caisse->getKey(),
            'label' => $caisse->name,
            'view' => 'sections.caisse.queue',
            'context' => ['caisseServiceId' => $caisse->getKey()],
        ])
        ->push(['key' => 'constat', 'icon' => 'alerte', 'label' => 'Signaler un constat', 'view' => 'sections.caisse.incident'])
        ->push(['key' => 'planning', 'icon' => 'planning', 'label' => 'Mon planning', 'view' => 'sections.caisse.schedule'])
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
