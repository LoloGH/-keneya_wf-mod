@php
    // Arborescence propre a l'espace administration. « Personnel » regroupe les
    // sections qui concernent les agents ; les autres restent a plat, une
    // hierarchie n'y apporterait rien.
    //
    // « Personnels » remplace depuis la v3.2.3 les trois formulaires separes
    // Medecins / Receptionnistes / Interfaces dediees : un seul formulaire ou
    // le type de personnel choisi decide du role et du rattachement.
    $sections = [
        ['key' => 'etablissement', 'label' => 'Etablissement', 'view' => 'sections.admin.establishment'],
        ['key' => 'services', 'label' => 'Services', 'children' => [
            ['key' => 'liste-services', 'label' => 'Liste des services', 'view' => 'sections.admin.services'],
            ['key' => 'types-de-service', 'label' => 'Types de service', 'view' => 'sections.admin.service-kinds'],
        ]],
        ['key' => 'personnel', 'label' => 'Personnel', 'children' => [
            ['key' => 'types-de-personnel', 'label' => 'Types de personnel', 'view' => 'sections.admin.staff-types'],
            ['key' => 'personnels', 'label' => 'Personnels', 'view' => 'sections.admin.staff'],
            ['key' => 'plannings', 'label' => 'Plannings', 'view' => 'sections.admin.schedules'],
        ]],
        ['key' => 'hospitalisation', 'label' => 'Hospitalisation', 'children' => [
            ['key' => 'salles', 'label' => 'Salles', 'view' => 'sections.admin.rooms'],
            ['key' => 'types-de-soins', 'label' => 'Types de soins', 'view' => 'sections.admin.care-task-types'],
        ]],
        ['key' => 'tarifs', 'label' => 'Tarifs', 'view' => 'sections.admin.billable-items'],
        ['key' => 'patients', 'label' => 'Patients', 'view' => 'sections.admin.patients'],
        ['key' => 'sms', 'label' => 'SMS', 'view' => 'sections.admin.sms'],
        ['key' => 'audit', 'label' => "Journal d'audit", 'view' => 'sections.admin.audit'],
        ['key' => 'suppression', 'label' => 'Supprimer un dossier', 'view' => 'sections.admin.deletion'],
    ];
@endphp

<x-layouts.app :title="'Administration — '.config('keneya.name')">
    {{-- Bandeau pilote par Livewire : un message emis pendant une action
         s'affiche immediatement, sans attendre un rechargement complet. --}}
    @livewire('shared.flash-alert', [], key('admin-flash'))

    @livewire('shared.vertical-tab-nav', ['sections' => $sections], key('nav-admin'))
</x-layouts.app>
