@props(['groups'])

{{-- Timeline médicale groupée par jour (§29). --}}
<ol class="space-y-6">
    @foreach ($groups as $day => $events)
        <li>
            <h3 class="mb-3 text-xs font-bold tracking-wider text-ink-500 uppercase">
                {{ \Illuminate\Support\Carbon::parse($day)->translatedFormat('d F Y') }}
            </h3>

            <ol class="relative space-y-3 border-l border-ink-200 pl-5">
                @foreach ($events as $event)
                    <li class="relative">
                        <span class="absolute top-1.5 -left-[27px] flex h-3 w-3 rounded-full border-2 border-white bg-clinic-500"></span>
                        <div class="k-card p-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-xs text-ink-500">{{ $event['date']->format('H:i') }}</span>
                                    <span class="k-badge-info">{{ $event['label'] }}</span>
                                </div>
                                @if ($event['status'])
                                    <x-dme::status-badge :status="\Illuminate\Support\Str::slug($event['status'])" :label="$event['status']"/>
                                @endif
                            </div>
                            <p class="mt-1.5 text-sm font-medium text-ink-900">
                                @if ($event['url'])
                                    <a href="{{ $event['url'] }}" class="hover:text-clinic-700 hover:underline">{{ $event['title'] }}</a>
                                @else
                                    {{ $event['title'] }}
                                @endif
                            </p>
                            @if ($event['detail'])
                                <p class="mt-0.5 text-xs text-ink-500">{{ $event['detail'] }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </li>
    @endforeach
</ol>
