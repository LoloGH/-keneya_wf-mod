<x-layouts.portal :title="'Votre avis - '.config('keneya.name')">
    @livewire('portal.visitor-feedback-form', ['token' => $token], key('avis-'.$token))
</x-layouts.portal>
