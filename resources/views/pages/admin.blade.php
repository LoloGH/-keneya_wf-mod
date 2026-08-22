<x-layouts.app :title="'Administration — '.config('keneya.name')">
    <x-slot:space>Espace administration</x-slot:space>

    <h1 class="page-title">Administration</h1>

    @if (session('admin.status'))
        <div class="alert alert--success" role="status">{{ session('admin.status') }}</div>
    @endif

    <div class="stack">
        @livewire('admin.service-manager')
        @livewire('admin.doctor-manager')
        @livewire('admin.receptionist-manager')
        @livewire('admin.patient-directory')
    </div>
</x-layouts.app>
