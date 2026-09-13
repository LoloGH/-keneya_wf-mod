@extends('dme::layouts.app')

@section('title', 'Notifications')

@section('content')
    <x-dme::page-header title="Notifications" subtitle="Alertes et informations qui vous sont adressées.">
        <x-slot:actions>
            <form action="{{ route('dme.notifications.read-all') }}" method="POST">
                @csrf
                <button type="submit" class="k-btn-secondary">Tout marquer comme lu</button>
            </form>
        </x-slot:actions>
    </x-dme::page-header>

    <div class="mb-4 flex flex-wrap gap-2">
        <a href="{{ route('dme.notifications.index') }}"
           class="k-btn-secondary k-btn-sm {{ ! request('unread') && ! request('category') ? 'border-clinic-500 text-clinic-700' : '' }}">
            Toutes
        </a>
        <a href="{{ route('dme.notifications.index', ['unread' => 1]) }}"
           class="k-btn-secondary k-btn-sm {{ request('unread') ? 'border-clinic-500 text-clinic-700' : '' }}">
            Non lues
        </a>
        @foreach ([
            'appointment' => 'Rendez-vous', 'lab_result' => 'Résultats',
            'prescription' => 'Ordonnances', 'alert' => 'Alertes',
        ] as $value => $label)
            <a href="{{ route('dme.notifications.index', ['category' => $value]) }}"
               class="k-btn-secondary k-btn-sm {{ request('category') === $value ? 'border-clinic-500 text-clinic-700' : '' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="k-card">
        @if ($notifications->isEmpty())
            <x-dme::empty-state icon="bell" title="Aucune notification"
                           message="Les alertes de résultats, de rendez-vous et d'ordonnances vous seront adressées ici."/>
        @else
            <ul class="divide-y divide-ink-100">
                @foreach ($notifications as $notification)
                    @php $data = json_decode($notification->data, true) ?: []; @endphp
                    <li class="flex flex-wrap items-start gap-3 p-4 {{ $notification->read_at ? '' : 'bg-clinic-50/40' }}">
                        <span class="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full
                            @class([
                                'bg-red-100 text-red-600' => $notification->level === 'critical',
                                'bg-amber-100 text-amber-600' => $notification->level === 'warning',
                                'bg-clinic-100 text-clinic-600' => $notification->level === 'info',
                            ])">
                            <x-dme::icon :name="$notification->level === 'critical' ? 'alert' : 'bell'" class="h-4 w-4"/>
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-ink-900">{{ $data['title'] ?? 'Notification' }}</p>
                            <p class="text-sm text-ink-600">{{ $data['message'] ?? '' }}</p>
                            <p class="mt-1 text-xs text-ink-400">
                                {{ \Illuminate\Support\Carbon::parse($notification->created_at)->diffForHumans() }}
                                @unless ($notification->read_at) · <span class="font-medium text-clinic-700">Non lue</span> @endunless
                            </p>
                        </div>

                        <div class="flex shrink-0 gap-2">
                            @if ($notification->action_url)
                                <a href="{{ $notification->action_url }}" class="k-btn-secondary k-btn-sm">Ouvrir</a>
                            @endif
                            @unless ($notification->read_at)
                                <form action="{{ route('dme.notifications.read', $notification->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="k-btn-ghost k-btn-sm">Marquer comme lue</button>
                                </form>
                            @endunless
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="p-4">{{ $notifications->links() }}</div>
        @endif
    </div>
@endsection
