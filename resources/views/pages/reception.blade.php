<x-layouts.app :title="'Accueil — '.config('keneya.name')">
    <x-slot:space>Espace accueil</x-slot:space>

    <h1 class="page-title">Accueil</h1>

    @if (session('reception.success'))
        <div class="alert alert--success" role="status">{{ session('reception.success') }}</div>
    @endif
    @if (session('reception.error'))
        <div class="alert alert--error" role="alert">{{ session('reception.error') }}</div>
    @endif

    {{-- Toujours chercher un dossier existant avant d'en creer un nouveau :
         un patient deja connu ne doit jamais recevoir un second code. --}}
    <div class="stack">
        @livewire('reception.patient-lookup')
    </div>

    <div class="grid grid--two">
        @livewire('reception.patient-registration-form')
        @livewire('reception.visitor-registration-form')
    </div>

    <div class="stack">
        @livewire('reception.today-appointments')
        @livewire('reception.ticket-cashier')
        {{-- La receptionniste surveille la salle d'attente depuis son poste. --}}
        @livewire('board.waiting-board')
        @livewire('reception.today-visits')
        @livewire('shared.my-schedule')
    </div>
</x-layouts.app>
