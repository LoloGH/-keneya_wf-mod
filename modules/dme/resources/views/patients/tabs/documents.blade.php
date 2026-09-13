{{-- Documents médicaux (§28) --}}
<div class="grid gap-4 lg:grid-cols-3">
    <section class="lg:col-span-2">
        <div class="k-card">
            <div class="k-card-header">
                <h2 class="k-card-title">Documents du dossier</h2>
                <span class="text-xs text-ink-500">{{ $tabData['documents']->total() }} document(s)</span>
            </div>

            @if ($tabData['documents']->isEmpty())
                <x-dme::empty-state icon="document" title="Aucun document"
                               message="Importez un document ou générez une ordonnance en PDF pour l'archiver au dossier."/>
            @else
                <div class="grid gap-3 p-4 sm:grid-cols-2">
                    @foreach ($tabData['documents'] as $document)
                        <article class="rounded-lg border border-ink-200 p-3">
                            <div class="flex items-start justify-between gap-2">
                                <span class="k-badge-info">{{ $document->typeLabel() }}</span>
                                <span class="font-mono text-[11px] text-ink-400">{{ $document->document_number }}</span>
                            </div>
                            <h3 class="mt-1.5 text-sm font-medium text-ink-900">
                                <a href="{{ route('dme.documents.show', $document) }}" class="hover:text-clinic-700 hover:underline">
                                    {{ $document->title }}
                                </a>
                            </h3>
                            <p class="mt-0.5 text-xs text-ink-500">
                                {{ $document->created_at->translatedFormat('d M Y') }}
                                · {{ $document->humanSize() }}
                                · v{{ $document->version }}
                            </p>
                            <p class="text-xs text-ink-400">
                                {{ $document->uploader?->displayName() ?? 'Généré par l\'application' }}
                            </p>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @can('download', $document)
                                    @if ($document->isPdf())
                                        <a href="{{ route('dme.documents.preview', $document) }}" target="_blank" rel="noopener"
                                           class="k-btn-secondary k-btn-sm">Aperçu</a>
                                    @endif
                                    <a href="{{ route('dme.documents.download', $document) }}" class="k-btn-secondary k-btn-sm">
                                        <x-dme::icon name="download" class="h-3.5 w-3.5"/> Télécharger
                                    </a>
                                @endcan
                            </div>
                        </article>
                    @endforeach
                </div>
                <div class="p-4 pt-0">{{ $tabData['documents']->links() }}</div>
            @endif
        </div>
    </section>

    @can('documents.upload')
        <section class="k-card self-start">
            <div class="k-card-header"><h2 class="k-card-title">Importer un document</h2></div>
            <form action="{{ route('dme.documents.store', $patient) }}" method="POST" enctype="multipart/form-data"
                  class="k-card-body space-y-3">
                @csrf
                <div>
                    <label for="doc_title" class="k-label">Titre <span class="text-red-600" aria-hidden="true">*</span></label>
                    <input id="doc_title" name="title" type="text" required maxlength="200" class="k-input">
                    <x-dme::field-error name="title"/>
                </div>
                <div>
                    <label for="doc_type" class="k-label">Type <span class="text-red-600" aria-hidden="true">*</span></label>
                    <select id="doc_type" name="type" required class="k-select">
                        @foreach (\Keneya\Dme\Models\MedicalDocument::TYPES as $value => $label)
                            <option value="{{ $value }}" @selected($value === 'imported')>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="doc_description" class="k-label">Description</label>
                    <textarea id="doc_description" name="description" rows="2" maxlength="1000" class="k-textarea"></textarea>
                </div>
                <div>
                    <label for="doc_file" class="k-label">Fichier <span class="text-red-600" aria-hidden="true">*</span></label>
                    <input id="doc_file" name="file" type="file" required class="k-input py-1.5"
                           accept=".{{ implode(',.', config('dme.documents.allowed_mimes')) }}">
                    <p class="k-hint">
                        Formats acceptés : {{ implode(', ', config('dme.documents.allowed_mimes')) }}.
                        Taille maximale {{ round(config('dme.documents.max_size_kb') / 1024) }} Mo.
                    </p>
                    <x-dme::field-error name="file"/>
                </div>
                <button type="submit" class="k-btn-primary w-full">Importer</button>
                <p class="k-hint">
                    Les fichiers sont stockés sur un disque privé. Ils ne sont accessibles qu'aux
                    utilisateurs autorisés, et chaque téléchargement est journalisé.
                </p>
            </form>
        </section>
    @endcan
</div>
