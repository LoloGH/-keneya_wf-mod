@props(['title' => 'Aucune donnée', 'message' => null, 'icon' => 'document'])

{{-- État vide (§50) : toujours accompagné de l'action qui le résout. --}}
<div class="flex flex-col items-center justify-center px-6 py-12 text-center">
    <span class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-ink-100 text-ink-400">
        <x-dme::icon :name="$icon" class="h-6 w-6"/>
    </span>
    <p class="text-sm font-medium text-ink-800">{{ $title }}</p>
    @if ($message)
        <p class="mt-1 max-w-sm text-sm text-ink-500">{{ $message }}</p>
    @endif
    @isset($action)
        <div class="mt-4">{{ $action }}</div>
    @endisset
</div>
