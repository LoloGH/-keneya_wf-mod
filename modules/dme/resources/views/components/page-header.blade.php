@props(['title', 'subtitle' => null, 'breadcrumbs' => []])

<div class="mb-5">
    @if ($breadcrumbs)
        <nav class="mb-2 flex flex-wrap items-center gap-1 text-xs text-ink-500" aria-label="Fil d'Ariane">
            @foreach ($breadcrumbs as $label => $url)
                @if ($url)
                    <a href="{{ $url }}" class="hover:text-clinic-700 hover:underline">{{ $label }}</a>
                    <x-dme::icon name="chevron-right" class="h-3.5 w-3.5"/>
                @else
                    <span class="font-medium text-ink-700" aria-current="page">{{ $label }}</span>
                @endif
            @endforeach
        </nav>
    @endif

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <h1 class="text-xl font-semibold tracking-tight text-ink-900 sm:text-2xl">{{ $title }}</h1>
            @if ($subtitle)
                <p class="mt-1 text-sm text-ink-500">{{ $subtitle }}</p>
            @endif
        </div>
        @isset($actions)
            <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>
</div>
