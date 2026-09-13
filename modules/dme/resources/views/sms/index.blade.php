@extends('dme::layouts.app')

@section('title', 'Service SMS')

@section('content')
    <x-dme::page-header title="Service SMS"
                   subtitle="Service transversal : il ne dépend d'aucun module médical et pourra être extrait tel quel en phase 2."/>

    {{-- Avertissement explicite lorsque rien n'est réellement émis --}}
    @if ($simulated)
        <div class="k-alert-warning mb-4" role="status">
            <x-dme::icon name="alert" class="mt-0.5 h-5 w-5 shrink-0 text-amber-600"/>
            <div>
                <p class="text-sm font-semibold text-amber-800">
                    Passerelle de simulation : aucun SMS n'est réellement envoyé
                </p>
                <p class="text-sm text-amber-700">
                    Les messages sont journalisés localement. Pour un envoi réel, définissez
                    <span class="font-mono">SMS_GATEWAY=smsgate</span> et les identifiants SMSGate
                    dans le fichier <span class="font-mono">.env</span>.
                </p>
            </div>
        </div>
    @endif

    {{-- État du service --}}
    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <div class="k-card p-4">
            <p class="text-xs font-medium text-ink-500">Passerelle active</p>
            <p class="mt-1 text-lg font-semibold text-ink-900">{{ $gateway }}</p>
            <p class="mt-0.5 text-[11px] text-ink-400">
                {{ $simulated ? 'Simulation locale' : 'Envoi réel vers l\'opérateur' }}
            </p>
        </div>
        <div class="k-card p-4">
            <p class="text-xs font-medium text-ink-500">Remis</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums text-keneya-600">{{ $stats['delivered'] }}</p>
            <p class="mt-0.5 text-[11px] text-ink-400">Confirmé par l'opérateur</p>
        </div>
        <div class="k-card p-4">
            <p class="text-xs font-medium text-ink-500">Envoyés</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums text-clinic-600">{{ $stats['sent'] }}</p>
            <p class="mt-0.5 text-[11px] text-ink-400">Sans accusé de remise</p>
        </div>
        <div class="k-card p-4">
            <p class="text-xs font-medium text-ink-500">En transit</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums text-ink-700">{{ $stats['in_transit'] }}</p>
            <p class="mt-0.5 text-[11px] text-ink-400">File d'attente et passerelle</p>
        </div>
        <div class="k-card p-4">
            <p class="text-xs font-medium text-ink-500">En échec</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums text-red-600">{{ $stats['failed'] }}</p>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <section class="k-card">
                <div class="k-card-header">
                    <h2 class="k-card-title">Historique des envois</h2>
                    <form method="GET" class="flex flex-wrap gap-2">
                        <label for="q" class="sr-only">Rechercher</label>
                        <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="k-input"
                               placeholder="Numéro ou référence...">
                        <label for="status" class="sr-only">Statut</label>
                        <select id="status" name="status" class="k-select">
                            <option value="">Tous</option>
                            @foreach (\Keneya\Dme\Models\SmsMessage::STATUSES as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="k-btn-secondary k-btn-sm">Filtrer</button>
                    </form>
                </div>

                @if ($messages->isEmpty())
                    <x-dme::empty-state icon="chat" title="Aucun message"
                                   message="Les SMS déclenchés par les rendez-vous, résultats et ordonnances apparaîtront ici."/>
                @else
                    <div class="overflow-x-auto">
                        <table class="k-table">
                            <caption class="sr-only">Historique des SMS</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Date</th>
                                    <th scope="col">Destinataire</th>
                                    <th scope="col">Message</th>
                                    <th scope="col">Patient</th>
                                    <th scope="col">Essais</th>
                                    <th scope="col">Statut</th>
                                    <th scope="col"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($messages as $message)
                                    <tr>
                                        <td class="whitespace-nowrap text-xs">
                                            {{ $message->created_at->translatedFormat('d M Y H:i') }}
                                        </td>
                                        <td class="font-mono text-xs">{{ $message->recipient }}</td>
                                        <td class="max-w-sm">
                                            <span class="block truncate">{{ $message->body }}</span>
                                            @if ($message->error_message)
                                                <span class="block text-xs text-red-600">{{ $message->error_message }}</span>
                                            @endif
                                        </td>
                                        <td class="text-xs">
                                            @if ($message->patient)
                                                <a href="{{ route('dme.patients.show', $message->patient) }}"
                                                   class="text-clinic-700 hover:underline">
                                                    {{ $message->patient->patient_number }}
                                                </a>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="tabular-nums">{{ $message->attempts }}</td>
                                        <td><x-dme::status-badge :status="$message->status" :label="$message->statusLabel()"/></td>
                                        <td class="text-right">
                                            @can('retry', $message)
                                                <form action="{{ route('dme.sms.retry', $message) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="k-btn-ghost k-btn-sm">Rejouer</button>
                                                </form>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4">{{ $messages->links() }}</div>
                @endif
            </section>
        </div>

        <div class="space-y-4">
            @can('sms.send')
                <section class="k-card">
                    <div class="k-card-header"><h2 class="k-card-title">Envoi manuel</h2></div>
                    <form action="{{ route('dme.sms.store') }}" method="POST" class="k-card-body space-y-3">
                        @csrf
                        <div>
                            <label for="recipient" class="k-label">Destinataire <span class="text-red-600" aria-hidden="true">*</span></label>
                            <input id="recipient" name="recipient" type="tel" required maxlength="30" class="k-input"
                                   value="{{ old('recipient') }}" placeholder="+223 70 00 10 01">
                            <x-dme::field-error name="recipient"/>
                        </div>
                        <div>
                            <label for="body" class="k-label">Message <span class="text-red-600" aria-hidden="true">*</span></label>
                            <textarea id="body" name="body" rows="4" required maxlength="480"
                                      class="k-textarea">{{ old('body') }}</textarea>
                            <p class="k-hint">
                                N'inscrivez jamais de résultat clinique dans un SMS : le réseau n'est pas maîtrisé.
                            </p>
                            <x-dme::field-error name="body"/>
                        </div>
                        <button type="submit" class="k-btn-primary w-full">Placer dans la file d'envoi</button>
                    </form>
                </section>
            @endcan

            <section class="k-card">
                <div class="k-card-header"><h2 class="k-card-title">Modèles de message</h2></div>
                <ul class="k-card-body space-y-3">
                    @foreach ($templates as $template)
                        <li>
                            <p class="text-sm font-medium text-ink-900">
                                {{ $template->name }}
                                @unless ($template->is_active)
                                    <span class="k-badge-neutral ml-1">Désactivé</span>
                                @endunless
                            </p>
                            <p class="mt-0.5 rounded bg-ink-50 px-2 py-1.5 text-xs text-ink-600">{{ $template->body }}</p>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>
    </div>
@endsection
