@php
    // Arborescence propre a l'espace administration. « Personnel » regroupe les
    // sections qui concernent les agents ; les autres restent a plat, une
    // hierarchie n'y apporterait rien.
    //
    // « Personnels » remplace depuis la v3.2.3 les trois formulaires separes
    // Medecins / Receptionnistes / Interfaces dediees : un seul formulaire ou
    // le type de personnel choisi decide du role et du rattachement.
    // Trois familles, comme la maquette de reference : ce que l'etablissement
    // EST, ce qu'on y GERE au quotidien, et ce qui releve du SYSTEME. La cle
    // `famille` n'est qu'un intitule de reperage : l'arbre garde exactement la
    // meme forme, et les interfaces qui n'en posent aucune n'affichent aucun
    // decoupage.
    $sections = [
        ['key' => 'etablissement', 'label' => 'Etablissement', 'icon' => 'batiment',
         'famille' => 'Etablissement', 'view' => 'sections.admin.establishment'],
        ['key' => 'services', 'label' => 'Services', 'icon' => 'services', 'children' => [
            ['key' => 'liste-services', 'label' => 'Liste des services', 'view' => 'sections.admin.services'],
            ['key' => 'types-de-service', 'label' => 'Types de service', 'view' => 'sections.admin.service-kinds'],
        ]],
        ['key' => 'personnel', 'label' => 'Personnel', 'icon' => 'personnel', 'children' => [
            ['key' => 'types-de-personnel', 'label' => 'Types de personnel', 'view' => 'sections.admin.staff-types'],
            ['key' => 'personnels', 'label' => 'Personnels', 'view' => 'sections.admin.staff'],
            ['key' => 'plannings', 'label' => 'Plannings', 'view' => 'sections.admin.schedules'],
        ]],
        ['key' => 'hospitalisation', 'label' => 'Hospitalisation', 'icon' => 'lit', 'children' => [
            ['key' => 'salles', 'label' => 'Salles', 'view' => 'sections.admin.rooms'],
            ['key' => 'types-de-soins', 'label' => 'Types de soins', 'view' => 'sections.admin.care-task-types'],
        ]],

        ['key' => 'tarifs', 'label' => 'Tarifs', 'icon' => 'tarif',
         'famille' => 'Gestion', 'view' => 'sections.admin.billable-items'],
        ['key' => 'patients', 'label' => 'Patients', 'icon' => 'patient', 'view' => 'sections.admin.patients'],
        ['key' => 'retours', 'label' => 'Retours et incidents', 'icon' => 'retour', 'view' => 'sections.admin.feedback'],
        ['key' => 'sms', 'label' => 'SMS', 'icon' => 'sms', 'children' => [
            ['key' => 'sms-journal', 'label' => 'Journal des envois', 'view' => 'sections.admin.sms'],
            ['key' => 'sms-groupes', 'label' => 'Envoi groupe', 'view' => 'sections.admin.broadcast'],
        ]],
        ['key' => 'pathologies', 'label' => 'Pathologies', 'icon' => 'pathologie', 'view' => 'sections.admin.pathologies'],
        ['key' => 'analyse', 'label' => 'Analyse', 'icon' => 'pathologie', 'view' => 'sections.admin.performance'],
        ['key' => 'audit', 'label' => "Journal d'audit", 'icon' => 'audit', 'view' => 'sections.admin.audit'],

        ['key' => 'utilisateurs', 'label' => 'Utilisateurs', 'icon' => 'utilisateurs',
         'famille' => 'Systeme', 'view' => 'sections.admin.users'],
        ['key' => 'parametres', 'label' => 'Parametres', 'icon' => 'reglages', 'view' => 'sections.admin.parameters'],
        ['key' => 'sauvegarde', 'label' => 'Sauvegarde', 'icon' => 'sauvegarde', 'view' => 'sections.admin.backup'],
        ['key' => 'suppression', 'label' => 'Supprimer un dossier', 'icon' => 'suppression', 'view' => 'sections.admin.deletion'],
    ];
@endphp

<x-layouts.app :title="'Administration - '.config('keneya.name')">
    {{-- Bandeau pilote par Livewire : un message emis pendant une action
         s'affiche immediatement, sans attendre un rechargement complet. --}}
    @livewire('shared.flash-alert', [], key('admin-flash'))

    @livewire('shared.vertical-tab-nav', ['sections' => $sections], key('nav-admin'))
</x-layouts.app>
