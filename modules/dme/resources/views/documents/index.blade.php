@extends('dme::layouts.app')

@section('title', 'Documents')

@section('content')
    <x-dme::page-header title="Documents médicaux" subtitle="{{ $documents->total() }} document(s)."/>

    <form method="GET" class="k-card mb-4 flex flex-wrap gap-3 p-4">
        <div class="min-w-56 flex-1">
            <label for="q" class="sr-only">Rechercher</label>
            <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="k-input"
                   placeholder="Titre, n° de document ou patient...">
        </div>
        <div>
            <label for="type" class="sr-only">Type</label>
            <select id="type" name="type" class="k-select">
                <option value="">Tous les types</option>
                @foreach (\Keneya\Dme\Models\MedicalDocument::TYPES as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="k-btn-primary">Filtrer</button>
    </form>

    <div class="k-card">
        @if ($documents->isEmpty())
            <x-dme::empty-state icon="document" title="Aucun document"
                           message="Les documents s'importent depuis le dossier d'un patient. Les PDF générés par l'application peuvent y être archivés."/>
        @else
            <div class="overflow-x-auto">
                <table class="k-table">
                    <caption class="sr-only">Documents médicaux</caption>
                    <thead>
                        <tr>
                            <th scope="col">Titre</th>
                            <th scope="col">N°</th>
                            <th scope="col">Type</th>
                            <th scope="col">Patient</th>
                            <th scope="col">Auteur</th>
                            <th scope="col">Date</th>
                            <th scope="col">Taille</th>
                            <th scope="col">Version</th>
                            <th scope="col">Statut</th>
                            <th scope="col"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($documents as $document)
                            <tr>
                                <td class="font-medium text-ink-900">
                                    <a href="{{ route('dme.documents.show', $document) }}" class="hover:text-clinic-700 hover:underline">
                                        {{ $document->title }}
                                    </a>
                                </td>
                                <td class="font-mono text-xs">{{ $document->document_number }}</td>
                                <td>{{ $document->typeLabel() }}</td>
                                <td>
                                    <a href="{{ route('dme.patients.show', $document->patient) }}"
                                       class="hover:text-clinic-700 hover:underline">
                                        {{ $document->patient->fullName() }}
                                    </a>
                                </td>
                                <td class="text-xs">
                                    {{ $document->uploader?->displayName() ?? 'Application' }}
                                </td>
                                <td class="whitespace-nowrap">{{ $document->created_at->translatedFormat('d M Y') }}</td>
                                <td class="tabular-nums">{{ $document->humanSize() }}</td>
                                <td class="tabular-nums">v{{ $document->version }}</td>
                                <td><x-dme::status-badge :status="$document->status" :label="ucfirst($document->status)"/></td>
                                <td class="text-right">
                                    @can('download', $document)
                                        <a href="{{ route('dme.documents.download', $document) }}" class="k-btn-ghost k-btn-sm">
                                            <x-dme::icon name="download" class="h-3.5 w-3.5"/>
                                            <span class="sr-only">Télécharger {{ $document->title }}</span>
                                        </a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4">{{ $documents->links() }}</div>
        @endif
    </div>
@endsection
