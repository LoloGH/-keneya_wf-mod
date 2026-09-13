@extends('dme::layouts.app')

@section('title', $document->title)

@section('content')
    <x-dme::page-header :title="$document->title"
                   :subtitle="$document->typeLabel().' · '.$document->document_number"
                   :breadcrumbs="[
                       'Documents' => route('dme.documents.index'),
                       $document->patient->fullName() => route('dme.patients.show', $document->patient),
                       $document->document_number => null,
                   ]">
        <x-slot:actions>
            @can('download', $document)
                <a href="{{ route('dme.documents.download', $document) }}" class="k-btn-primary">
                    <x-dme::icon name="download" class="h-4 w-4"/> Télécharger
                </a>
            @endcan
        </x-slot:actions>
    </x-dme::page-header>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2">
    {{-- Aperçu intégré (§28), servi par une route contrôlée.

         PDF **et images** : un compte rendu d'échographie arrive le plus
         souvent en photo ou en scan, et obliger le praticien à le télécharger
         pour le lire laisse une copie du dossier sur chaque poste qui l'a
         consulté. --}}
            @if ($document->isPreviewable())
                @can('download', $document)
                    <section class="k-card overflow-hidden">
                        <div class="k-card-header">
                            <h2 class="k-card-title">Aperçu</h2>
                            <a href="{{ route('dme.documents.preview', $document) }}"
                               target="_blank" rel="noopener" class="text-xs text-clinic-700 hover:underline">
                                Ouvrir en plein écran
                            </a>
                        </div>
                        @if ($document->isImage())
                            <div class="flex justify-center bg-ink-50 p-3">
                                <img src="{{ route('dme.documents.preview', $document) }}"
                                     alt="Aperçu de {{ $document->title }}"
                                     class="max-h-[70vh] w-auto max-w-full rounded">
                            </div>
                        @else
                            <iframe src="{{ route('dme.documents.preview', $document) }}"
                                    title="Aperçu de {{ $document->title }}"
                                    class="h-[70vh] w-full border-0"></iframe>
                        @endif
                    </section>
                @endcan
            @else
                <div class="k-card">
                    <x-dme::empty-state icon="document" title="Aperçu indisponible"
                                   message="Ce type de fichier ne se consulte pas dans le navigateur. Téléchargez-le pour l'ouvrir.">
                        <x-slot:action>
                            @can('download', $document)
                                <a href="{{ route('dme.documents.download', $document) }}" class="k-btn-primary">
                                    <x-dme::icon name="download" class="h-4 w-4"/> Télécharger
                                </a>
                            @endcan
                        </x-slot:action>
                    </x-dme::empty-state>
                </div>
            @endif
        </div>

        <div class="space-y-4">
            <section class="k-card">
                <div class="k-card-header"><h2 class="k-card-title">Informations</h2></div>
                <dl class="k-card-body space-y-2.5 text-sm">
                    @foreach ([
                        'Type' => $document->typeLabel(),
                        'Patient' => $document->patient->fullName(),
                        'Auteur' => $document->uploader?->displayName() ?? 'Généré par l\'application',
                        'Ajouté le' => $document->created_at->translatedFormat('d F Y à H:i'),
                        'Taille' => $document->humanSize(),
                        'Version' => 'v'.$document->version,
                        'Format' => $document->mime_type ?: 'Inconnu',
                    ] as $label => $value)
                        <div>
                            <dt class="text-xs text-ink-500">{{ $label }}</dt>
                            <dd class="font-medium text-ink-900">{{ $value }}</dd>
                        </div>
                    @endforeach
                    @if ($document->description)
                        <div>
                            <dt class="text-xs text-ink-500">Description</dt>
                            <dd class="text-ink-800">{{ $document->description }}</dd>
                        </div>
                    @endif
                    @if ($document->previousVersion)
                        <div>
                            <dt class="text-xs text-ink-500">Version précédente</dt>
                            <dd>
                                <a href="{{ route('dme.documents.show', $document->previousVersion) }}"
                                   class="font-mono text-xs text-clinic-700 hover:underline">
                                    {{ $document->previousVersion->document_number }}
                                </a>
                            </dd>
                        </div>
                    @endif
                </dl>
            </section>

            <section class="k-card">
                <div class="k-card-header"><h2 class="k-card-title">Confidentialité</h2></div>
                <div class="k-card-body text-sm text-ink-600">
                    <p>
                        Ce fichier est stocké sur un disque privé. Il n'est jamais accessible par une URL
                        directe : chaque consultation et chaque téléchargement passent par une vérification
                        de permission et sont inscrits au journal d'audit du dossier.
                    </p>
                </div>
            </section>
        </div>
    </div>
@endsection
