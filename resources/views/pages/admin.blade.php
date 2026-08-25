@php
    // Arborescence propre a l'espace administration. « Personnel » regroupe les
    // trois sections qui concernent les agents ; les autres restent a plat,
    // une hierarchie n'y apporterait rien.
    $sections = [
        ['key' => 'etablissement', 'label' => 'Etablissement', 'view' => 'sections.admin.establishment'],
        ['key' => 'services', 'label' => 'Services', 'view' => 'sections.admin.services'],
        ['key' => 'personnel', 'label' => 'Personnel', 'children' => [
            ['key' => 'medecins', 'label' => 'Medecins', 'view' => 'sections.admin.doctors'],
            ['key' => 'receptionnistes', 'label' => 'Receptionnistes', 'view' => 'sections.admin.receptionists'],
            ['key' => 'plannings', 'label' => 'Plannings', 'view' => 'sections.admin.schedules'],
        ]],
        ['key' => 'patients', 'label' => 'Patients', 'view' => 'sections.admin.patients'],
        ['key' => 'audit', 'label' => "Journal d'audit", 'view' => 'sections.admin.audit'],
        ['key' => 'suppression', 'label' => 'Supprimer un dossier', 'view' => 'sections.admin.deletion'],
    ];
@endphp

<x-layouts.app :title="'Administration — '.config('keneya.name')">
    @if (session('admin.status'))
        <div class="alert alert--success" role="status">{{ session('admin.status') }}</div>
    @endif
    @if (session('admin.error'))
        <div class="alert alert--error" role="alert">{{ session('admin.error') }}</div>
    @endif

    @livewire('shared.vertical-tab-nav', ['sections' => $sections], key('nav-admin'))
</x-layouts.app>
