@php
    use App\Models\StaffType;

    // Les sections affichees sont exactement celles que les capacites cochees
    // autorisent : aucune section vide pour une capacite desactivee.
    $sections = [];

    if ($type->can(StaffType::CAP_QUEUE)) {
        $sections[] = ['key' => 'file', 'label' => "File d'attente", 'view' => 'sections.staff.queue'];
    }

    if ($type->can(StaffType::CAP_RECEIVE_REFERRAL)) {
        $sections[] = ['key' => 'renvois', 'label' => 'Renvois recus', 'view' => 'sections.staff.referrals'];
    }

    if ($type->can(StaffType::CAP_CARE_TASKS)) {
        $sections[] = ['key' => 'soins', 'label' => 'Soins programmes', 'view' => 'sections.staff.care-tasks'];
        // Les releves accompagnent les soins : qui administre a besoin de
        // savoir ce que l'equipe precedente a laisse (v3.2.3, point 4).
        $sections[] = ['key' => 'releves', 'label' => 'Releves', 'view' => 'sections.staff.handoffs'];
    }

    if ($type->can(StaffType::CAP_ACCEPT_PAYMENT)) {
        $sections[] = ['key' => 'encaissement', 'label' => 'Encaissement', 'view' => 'sections.staff.payments'];
    }

    // Le planning personnel est offert a tout le monde, comme dans les quatre
    // autres interfaces : il ne depend d'aucune capacite.
    $sections[] = ['key' => 'planning', 'label' => 'Mon planning', 'view' => 'sections.staff.schedule'];
@endphp

<x-layouts.app :title="$type->name.' — '.config('keneya.name')">
    {{-- A droite de la barre : le service de rattachement plutot que le seul
         nom du type, comme dans /service. --}}
    <x-slot:context>
        {{ auth()->user()->staffMember?->service?->name ?? $type->name }}
    </x-slot:context>

    {{-- Bandeau pilote par Livewire : un refus emis pendant une action
         s'affiche immediatement, sans attendre un rechargement complet. Il lit
         aussi la session au montage, donc les composants qui n'ecrivent qu'en
         session s'affichent comme avant. --}}
    @livewire('shared.flash-alert', [
        'successKey' => 'staff.status',
        'errorKey' => 'staff.error',
    ], key('staff-flash'))

    @if ($sections === [])
        <p class="empty">Aucune fonction n'est activee pour ce type de personnel.</p>
    @endif

    <div class="grid--main">
        @livewire('shared.vertical-tab-nav', ['sections' => $sections], key('nav-staff'))

        @if ($type->can(StaffType::CAP_VIEW_DOSSIER))
            @livewire('staff.staff-record-panel', [], key('staff-record-panel'))
        @endif
    </div>
</x-layouts.app>
