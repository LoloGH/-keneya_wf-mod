@php
    use App\Models\StaffType;

    // Le service de rattachement : tout ce que cette interface montre s'y
    // rapporte, comme /service se rapporte au service du medecin.
    $member = auth()->user()->staffMember;
    $serviceId = $member?->service_id;

    $peut = fn (string $capacite) => $type->can($capacite);

    // Les sections affichees sont exactement celles que les capacites cochees
    // autorisent : aucune section vide pour une capacite desactivee, et aucune
    // capacite cochee sans section, ce qui etait le defaut jusqu'a la v3.3.1,
    // ou quatorze des vingt et une capacites ne produisaient rien ici.
    $sections = [];

    if ($peut(StaffType::CAP_QUEUE)) {
        $sections[] = ['key' => 'file', 'icon' => 'file', 'label' => "File d'attente", 'view' => 'sections.staff.queue'];
    }

    // Dossier medical (v3.3.1). Les memes ecrans que /service, et le meme
    // regroupement : chacun garde sa capacite, le groupe disparait si le type
    // n'en porte aucune. Ce qui s'y saisit atterrit dans le DME, que le compte
    // ait ou non le droit d'ouvrir le dossier complet : consigner un acte et
    // lire un dossier ne sont pas le meme droit.
    $sousDossierMedical = $serviceId ? array_values(array_filter([
        $peut(StaffType::CAP_RECORD_CONSULTATION)
            ? ['key' => 'consultation-medicale', 'icon' => 'soins', 'label' => 'Consultation', 'view' => 'sections.staff.medical-consultation']
            : null,
        ($peut(StaffType::CAP_RECORD_HISTORY) || $peut(StaffType::CAP_RECORD_ALLERGIES))
            ? ['key' => 'antecedents', 'icon' => 'document', 'label' => 'Antecedents et allergies', 'view' => 'sections.staff.medical-background']
            : null,
        ($peut(StaffType::CAP_RECORD_MEDICATIONS) || $peut(StaffType::CAP_ORDER_LABORATORY)
            || $peut(StaffType::CAP_ORDER_IMAGING) || $peut(StaffType::CAP_RECORD_DOCUMENTS))
            ? ['key' => 'examens', 'icon' => 'soins', 'label' => 'Traitements et examens', 'view' => 'sections.staff.medical-orders']
            : null,
    ])) : [];

    if ($sousDossierMedical !== []) {
        $sections[] = ['key' => 'dossier-medical', 'icon' => 'document', 'label' => 'Dossier medical', 'children' => $sousDossierMedical];
    }

    if ($peut(StaffType::CAP_RECEIVE_REFERRAL)) {
        $sections[] = ['key' => 'renvois', 'icon' => 'services', 'label' => 'Renvois recus', 'view' => 'sections.staff.referrals'];
    }

    if ($serviceId && $peut(StaffType::CAP_PRESCRIBE)) {
        $sections[] = ['key' => 'consultation', 'icon' => 'soins', 'label' => 'Fin de consultation', 'view' => 'sections.staff.consultation'];
    }

    // « Patients hospitalises » sert deux capacites : admettre, et prescrire
    // des soins. Elles se cochent separement, admettre un patient et lui
    // prescrire un traitement ne sont pas la meme decision, mais elles
    // s'exercent sur le meme ecran.
    if ($serviceId && ($peut(StaffType::CAP_ADMIT_HOSPITALIZATION) || $peut(StaffType::CAP_PRESCRIBE_CARE))) {
        $sections[] = ['key' => 'hospitalisation', 'icon' => 'lit', 'label' => 'Patients hospitalises', 'view' => 'sections.staff.hospitalizations'];
    }

    if ($peut(StaffType::CAP_CARE_TASKS)) {
        $sections[] = ['key' => 'soins', 'icon' => 'soins', 'label' => 'Soins programmes', 'view' => 'sections.staff.care-tasks'];
        // Les releves accompagnent les soins : qui administre a besoin de
        // savoir ce que l'equipe precedente a laisse (v3.2.3, point 4).
        $sections[] = ['key' => 'releves', 'icon' => 'document', 'label' => 'Releves', 'view' => 'sections.staff.handoffs'];
    }

    // « Mes patients » porte le point d'entree du dossier medical complet :
    // sans cette section, la capacite CAP_ACCESS_DME n'aurait ici aucun
    // endroit ou s'exercer. Elle sert aussi a qui consulte simplement les
    // dossiers qu'il a suivis.
    if ($peut(StaffType::CAP_VIEW_DOSSIER) || $peut(StaffType::CAP_ACCESS_DME)) {
        $sections[] = ['key' => 'mes-patients', 'icon' => 'patient', 'label' => 'Mes patients', 'view' => 'sections.staff.my-patients'];
    }

    if ($peut(StaffType::CAP_SCHEDULE_APPOINTMENT)) {
        $sections[] = ['key' => 'mes-rendez-vous', 'icon' => 'planning', 'label' => 'Mes rendez-vous', 'view' => 'sections.staff.my-appointments'];
    }

    if ($peut(StaffType::CAP_REGISTER_PATIENT)) {
        $sections[] = ['key' => 'nouveau-patient', 'icon' => 'patient', 'label' => 'Nouveau patient', 'view' => 'sections.staff.new-patient'];
    }

    if ($peut(StaffType::CAP_REGISTER_VISITOR)) {
        $sections[] = ['key' => 'visiteur', 'icon' => 'patient', 'label' => 'Visiteur', 'view' => 'sections.staff.visitor'];
    }

    if ($peut(StaffType::CAP_ACCEPT_PAYMENT)) {
        $sections[] = ['key' => 'encaissement', 'icon' => 'tarif', 'label' => 'Encaissement', 'view' => 'sections.staff.payments'];
    }

    // Le planning personnel est offert a tout le monde, comme dans les quatre
    // autres interfaces : il ne depend d'aucune capacite.
    // Signaler un constat est offert a tout le monde, comme le planning :
    // un incident ne depend d'aucune capacite (v3.2.8, point 4).
    $sections[] = ['key' => 'constat', 'icon' => 'alerte', 'label' => 'Signaler un constat', 'view' => 'sections.staff.incident'];
    $sections[] = ['key' => 'planning', 'icon' => 'planning', 'label' => 'Mon planning', 'view' => 'sections.staff.schedule'];
@endphp

<x-layouts.app :title="$type->name.' - '.config('keneya.name')">
    {{-- A droite de la barre : le service de rattachement plutot que le seul
         nom du type, comme dans /service. --}}
    <x-slot:context>
        {{ $member?->service?->name ?? $type->name }}
    </x-slot:context>

    {{-- Bandeau pilote par Livewire : un refus emis pendant une action
         s'affiche immediatement, sans attendre un rechargement complet.

         Deux familles de cles de session : les ecrans propres au poste
         ecrivent sous `staff.*`, ceux partages avec l'interface medecin sous
         `service.*`. Le bandeau lit les deux, sans quoi la moitie des
         messages resterait invisible ici. --}}
    @livewire('shared.flash-alert', [
        'successKey' => ['staff.status', 'service.status'],
        'errorKey' => ['staff.error', 'service.error'],
    ], key('staff-flash'))

    {{-- Les ecrans du dossier medical, la fin de consultation et
         l'hospitalisation se rapportent tous a un service. Sans rattachement,
         ils ne sont pas affiches : le dire ici vaut mieux qu'une barre
         mysterieusement amputee. --}}
    @if (! $serviceId)
        <div class="alert alert--error" role="alert">
            Votre compte n'est rattache a aucun service. Contactez l'administrateur.
        </div>
    @endif

    @if ($sections === [])
        <p class="empty">Aucune fonction n'est activee pour ce type de personnel.</p>
    @endif

    {{-- `grid` manquait : la classe de variante seule ne pose pas la grille,
         et la colonne du dossier ne s'est jamais placee a droite ici. --}}
    <div class="grid grid--main">
        @livewire('shared.vertical-tab-nav', [
            'sections' => $sections,
            'context' => ['serviceId' => $serviceId],
        ], key('nav-staff'))

        {{-- Le dossier ne prend sa colonne que lorsqu'il est ouvert : ferme,
             il ne rend rien et l'espace de travail occupe toute la largeur. --}}
        @if ($type->can(StaffType::CAP_VIEW_DOSSIER))
            <div class="record-column">
                @livewire('staff.staff-record-panel', [], key('staff-record-panel'))
            </div>
        @endif
    </div>
</x-layouts.app>
