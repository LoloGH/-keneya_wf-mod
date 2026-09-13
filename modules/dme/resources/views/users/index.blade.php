@extends('dme::layouts.app')

@section('title', 'Utilisateurs')

@section('content')
    <x-dme::page-header title="Utilisateurs" subtitle="{{ $users->total() }} compte(s) professionnel(s).">
        <x-slot:actions>
            @can('users.manage')
                <a href="{{ route('dme.users.create') }}" class="k-btn-primary">
                    <x-dme::icon name="plus" class="h-4 w-4"/> Nouveau compte
                </a>
            @endcan
        </x-slot:actions>
    </x-dme::page-header>

    <form method="GET" class="k-card mb-4 flex flex-wrap gap-3 p-4">
        <div class="min-w-56 flex-1">
            <label for="q" class="sr-only">Rechercher</label>
            <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="k-input"
                   placeholder="Nom, e-mail ou matricule...">
        </div>
        <div>
            <label for="role" class="sr-only">Rôle</label>
            <select id="role" name="role" class="k-select">
                <option value="">Tous les rôles</option>
                @foreach ($roleLabels as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['role'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="k-btn-primary">Filtrer</button>
    </form>

    <div class="k-card">
        @if ($users->isEmpty())
            <x-dme::empty-state icon="shield" title="Aucun utilisateur" message="Créez un premier compte professionnel."/>
        @else
            <div class="overflow-x-auto">
                <table class="k-table">
                    <caption class="sr-only">Comptes utilisateurs</caption>
                    <thead>
                        <tr>
                            <th scope="col">Utilisateur</th>
                            <th scope="col">Matricule</th>
                            <th scope="col">Rôle</th>
                            <th scope="col">Service</th>
                            <th scope="col">Spécialité</th>
                            <th scope="col">Dernière connexion</th>
                            <th scope="col">Statut</th>
                            <th scope="col"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td>
                                    <span class="block font-medium text-ink-900">{{ $user->displayName() }}</span>
                                    <span class="block text-xs text-ink-500">{{ $user->email }}</span>
                                </td>
                                <td class="font-mono text-xs">{{ $user->matricule ?: '-' }}</td>
                                <td>
                                    @foreach ($user->roles as $role)
                                        <span class="k-badge-info">{{ $roleLabels[$role->name] ?? $role->name }}</span>
                                    @endforeach
                                </td>
                                <td>{{ $user->service?->name ?? '-' }}</td>
                                <td class="text-xs">{{ $user->speciality ?: '-' }}</td>
                                <td class="whitespace-nowrap text-xs">
                                    {{ $user->last_login_at?->translatedFormat('d M Y H:i') ?? 'Jamais' }}
                                </td>
                                <td>
                                    <x-dme::status-badge :status="$user->is_active ? 'active' : 'cancelled'"
                                                    :label="$user->is_active ? 'Actif' : 'Désactivé'"/>
                                </td>
                                <td class="text-right">
                                    @can('update', $user)
                                        <a href="{{ route('dme.users.edit', $user) }}" class="k-btn-ghost k-btn-sm">Modifier</a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4">{{ $users->links() }}</div>
        @endif
    </div>
@endsection
