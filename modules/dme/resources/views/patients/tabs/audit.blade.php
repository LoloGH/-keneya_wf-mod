{{-- Audit du dossier (§30) --}}
<section class="k-card">
    <div class="k-card-header">
        <div>
            <h2 class="k-card-title">Journal d'accès au dossier</h2>
            <p class="text-xs text-ink-500">
                Registre en écriture seule : aucune entrée ne peut être modifiée ni supprimée.
            </p>
        </div>
        @can('audit.view')
            <a href="{{ route('dme.audit.index') }}" class="text-xs font-medium text-clinic-700 hover:underline">
                Journal général
            </a>
        @endcan
    </div>

    @if ($tabData['auditLogs']->isEmpty())
        <x-dme::empty-state icon="clipboard" title="Aucune trace d'accès"
                       message="Les consultations et modifications de ce dossier seront journalisées ici."/>
    @else
        <div class="overflow-x-auto">
            <table class="k-table">
                <caption class="sr-only">Journal d'accès au dossier patient</caption>
                <thead>
                    <tr>
                        <th scope="col">Date et heure</th>
                        <th scope="col">Utilisateur</th>
                        <th scope="col">Rôle</th>
                        <th scope="col">Action</th>
                        <th scope="col">Détail</th>
                        <th scope="col">Adresse IP</th>
                        <th scope="col">Résultat</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tabData['auditLogs'] as $log)
                        <tr>
                            <td class="whitespace-nowrap font-mono text-xs">
                                {{ $log->created_at->translatedFormat('d/m/Y H:i:s') }}
                            </td>
                            <td class="font-medium text-ink-900">{{ $log->causer?->displayName() ?? 'Système' }}</td>
                            <td class="text-xs">
                                {{ \Keneya\Dme\Support\Rbac::allRoleLabels()[$log->causer_role] ?? ($log->causer_role ?: '-') }}
                            </td>
                            <td>{{ $log->actionLabel() }}</td>
                            <td class="max-w-xs truncate text-xs text-ink-500">{{ $log->description }}</td>
                            <td class="font-mono text-xs">{{ $log->ip_address ?: '-' }}</td>
                            <td>
                                <x-dme::status-badge :status="$log->outcome"
                                    :label="$log->outcome === 'allowed' ? 'Autorisé' : ($log->outcome === 'denied' ? 'Refusé' : 'Échec')"/>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $tabData['auditLogs']->links() }}</div>
    @endif
</section>
