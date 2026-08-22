<x-layouts.app :title="'Administration — '.config('keneya.name')">
    <x-slot:space>Espace administration</x-slot:space>

    <h1 class="page-title">Administration</h1>

    @if (session('admin.status'))
        <div class="alert alert--success" role="status">{{ session('admin.status') }}</div>
    @endif
    @if (session('admin.error'))
        <div class="alert alert--error" role="alert">{{ session('admin.error') }}</div>
    @endif

    <div class="stack">
        @livewire('admin.hospital-settings')
        @livewire('admin.service-manager')
        @livewire('admin.doctor-manager')
        @livewire('admin.receptionist-manager')
        @livewire('admin.schedule-manager')
        @livewire('admin.patient-directory')
        @livewire('admin.activity-log-viewer')
    </div>
</x-layouts.app>
