<x-layouts.portal :title="'Mes documents - '.config('keneya.name')">
    @livewire('portal.patient-portal', ['token' => $token], key('portal-'.$token))
</x-layouts.portal>
