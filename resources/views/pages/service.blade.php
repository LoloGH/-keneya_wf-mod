@php
    // Service courant du medecin connecte : son premier rattachement par defaut.
    $assignments = auth()->user()->doctors()->with('service')->orderBy('id')->get();
    $assignment = $assignments->first();
    $serviceId = $assignment?->service_id;

    use App\Models\StaffType;

    // Arborescence propre a l'espace service. « Renvois » regroupe les deux
    // panneaux qui vont par paire — ce qu'on recoit et ce qu'on a envoye.
    //
    // Depuis le v3.2.2, les sections optionnelles dependent des capacites du
    // type de personnel du medecin connecte : meme mecanique que pour
    // /staff/{slug}, pas une seconde. Les sections sans capacite associee sont
    // le squelette du role et ne se retirent pas.
    $peut = fn (string $capacite) => auth()->user()->hasCapability($capacite);

    // Dossier medical (v3.3.1) : les ecrans qui ecrivent dans le DME sont
    // regroupes sous une seule entree. Chacun garde sa capacite — un type
    // de personnel peut n'en recevoir qu'une — et le groupe disparait si
    // le compte n'en porte aucune. Sans ce regroupement, la barre du
    // medecin passait a onze entrees de premier niveau.
    $sousDossierMedical = array_values(array_filter([
        $peut(StaffType::CAP_RECORD_CONSULTATION)
            ? ['key' => 'consultation-medicale', 'icon' => 'soins', 'label' => 'Consultation', 'view' => 'sections.service.medical-consultation']
            : null,
        // Antecedents et allergies partagent un ecran mais deux capacites :
        // l'entree apparait des que l'une des deux est cochee, et l'ecran
        // ne montre alors que la moitie qui revient au compte connecte.
        ($peut(StaffType::CAP_RECORD_HISTORY) || $peut(StaffType::CAP_RECORD_ALLERGIES))
            ? ['key' => 'antecedents', 'icon' => 'document', 'label' => 'Antecedents et allergies', 'view' => 'sections.service.medical-background']
            : null,
        // Traitements, laboratoire, imagerie et documents : quatre
        // capacites, un ecran, meme principe.
        ($peut(StaffType::CAP_RECORD_MEDICATIONS) || $peut(StaffType::CAP_ORDER_LABORATORY)
            || $peut(StaffType::CAP_ORDER_IMAGING) || $peut(StaffType::CAP_RECORD_DOCUMENTS))
            ? ['key' => 'examens', 'icon' => 'soins', 'label' => 'Traitements et examens', 'view' => 'sections.service.medical-orders']
            : null,
    ]));

    $sections = array_values(array_filter([
        ['key' => 'file', 'icon' => 'file', 'label' => "File d'attente", 'view' => 'sections.service.queue'],
        $sousDossierMedical !== []
            ? ['key' => 'dossier-medical', 'icon' => 'document', 'label' => 'Dossier medical', 'children' => $sousDossierMedical]
            : null,
        ['key' => 'renvois', 'icon' => 'services', 'label' => 'Renvois', 'children' => [
            ['key' => 'renvois-entrants', 'icon' => 'services', 'label' => 'Renvois en attente', 'view' => 'sections.service.incoming'],
            ['key' => 'renvois-sortants', 'icon' => 'services', 'label' => 'Mes renvois', 'view' => 'sections.service.outgoing'],
        ]],
        $peut(StaffType::CAP_PRESCRIBE)
            ? ['key' => 'consultation', 'icon' => 'soins', 'label' => 'Fin de consultation', 'view' => 'sections.service.consultation']
            : null,
        $peut(StaffType::CAP_ADMIT_HOSPITALIZATION)
            ? ['key' => 'hospitalisation', 'icon' => 'lit', 'label' => 'Patients hospitalises', 'view' => 'sections.service.hospitalizations']
            : null,
        ['key' => 'mes-patients', 'icon' => 'patient', 'label' => 'Mes patients', 'view' => 'sections.service.my-patients'],
        $peut(StaffType::CAP_SCHEDULE_APPOINTMENT)
            ? ['key' => 'mes-rendez-vous', 'icon' => 'planning', 'label' => 'Mes rendez-vous', 'view' => 'sections.service.my-appointments']
            : null,
        ['key' => 'constat', 'icon' => 'alerte', 'label' => 'Signaler un constat', 'view' => 'sections.service.incident'],
        ['key' => 'planning', 'icon' => 'planning', 'label' => 'Mon planning', 'view' => 'sections.service.schedule'],
    ]));
@endphp

<x-layouts.app :title="'Service — '.config('keneya.name')">
    {{-- A droite de la barre : le service du medecin plutot que son role. --}}
    <x-slot:context>{{ $assignment?->service?->name ?? \App\Support\Roles::label(auth()->user()->scopedRole()) }}</x-slot:context>

    @if (! $serviceId)
        <div class="alert alert--error" role="alert">
            Votre compte n'est rattache a aucun service. Contactez l'administrateur.
        </div>
    @else
        {{-- Bandeau pilote par Livewire : un refus emis pendant une action
             s'affiche immediatement, sans attendre un rechargement complet.
             Il lit aussi la session au montage, donc les composants qui
             continuent de n'ecrire qu'en session s'affichent comme avant. --}}
        @livewire('shared.flash-alert', [
            'successKey' => 'service.status',
            'errorKey' => 'service.error',
        ], key('service-flash'))

        {{-- Le selecteur reste hors des onglets : il change le contexte de
             toutes les sections a la fois. Il n'apparait que pour un medecin
             rattache a plusieurs services — pour les autres, la barre affiche
             deja le nom du service. --}}
        @if ($assignments->count() > 1)
            <div class="service-head">
                @livewire('service.service-selector', ['serviceId' => $serviceId], key('service-selector'))
            </div>
        @endif

        <div class="grid grid--main">
            @livewire('shared.vertical-tab-nav', [
                'sections' => $sections,
                'context' => ['serviceId' => $serviceId],
            ], key('nav-service-'.$serviceId))

            {{-- Le dossier patient reste visible quelle que soit la section :
                 c'est le panneau qu'on consulte pendant qu'on travaille. --}}
            <div class="record-column">
                @livewire('service.patient-record-panel', [], key('service-record-panel'))
            </div>
        </div>
    @endif
</x-layouts.app>
