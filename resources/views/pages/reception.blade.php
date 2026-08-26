@php
    // Arborescence propre a l'espace accueil. « Enregistrement » regroupe les
    // trois portes d'entree d'un patient ou d'un visiteur, dans l'ordre ou la
    // receptionniste les utilise : chercher d'abord, creer ensuite.
    use App\Models\StaffType;

    // Sections optionnelles pilotees par les capacites du type (v3.2.2).
    $peut = fn (string $capacite) => auth()->user()->hasCapability($capacite);

    $sections = array_values(array_filter([
        ['key' => 'enregistrement', 'label' => 'Enregistrement', 'children' => array_values(array_filter([
            ['key' => 'recherche', 'label' => 'Rechercher un dossier', 'view' => 'sections.reception.lookup'],
            ['key' => 'nouveau-patient', 'label' => 'Nouveau patient', 'view' => 'sections.reception.new-patient'],
            $peut(StaffType::CAP_REGISTER_VISITOR)
                ? ['key' => 'visiteur', 'label' => 'Visiteur', 'view' => 'sections.reception.visitor']
                : null,
        ]))],
        $peut(StaffType::CAP_SCHEDULE_APPOINTMENT)
            ? ['key' => 'rendez-vous', 'label' => 'Rendez-vous du jour', 'view' => 'sections.reception.appointments']
            : null,
        ['key' => 'salle-attente', 'label' => "Salle d'attente", 'view' => 'sections.reception.board'],
        ['key' => 'passages', 'label' => 'Passages du jour', 'view' => 'sections.reception.today'],
        ['key' => 'planning', 'label' => 'Mon planning', 'view' => 'sections.reception.schedule'],
    ]));
@endphp

<x-layouts.app :title="'Accueil — '.config('keneya.name')">
    @if (session('reception.success'))
        <div class="alert alert--success" role="status">{{ session('reception.success') }}</div>
    @endif
    @if (session('reception.error'))
        <div class="alert alert--error" role="alert">{{ session('reception.error') }}</div>
    @endif

    @livewire('shared.vertical-tab-nav', ['sections' => $sections], key('nav-reception'))
</x-layouts.app>
