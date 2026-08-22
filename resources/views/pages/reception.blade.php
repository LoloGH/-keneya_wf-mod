<x-layouts.app :title="'Accueil — '.config('keneya.name')">
    <x-slot:space>Espace accueil</x-slot:space>

    <h1 class="page-title">Accueil</h1>

    @if (session('reception.success'))
        <div class="alert alert--success" role="status">{{ session('reception.success') }}</div>
    @endif

    <div class="grid grid--two">
        @livewire('reception.patient-registration-form')
        @livewire('reception.visitor-registration-form')
    </div>

    <div class="stack">
        {{-- La receptionniste surveille la salle d'attente depuis son propre poste. --}}
        @livewire('board.waiting-board')
        @livewire('reception.today-visits')
    </div>
</x-layouts.app>
