@extends('dme::layouts.app')

@section('title', 'Imagerie '.$order->order_number)

@section('content')
    <x-dme::page-header :title="$order->modalityLabel().($order->body_site ? ' - '.$order->body_site : '')"
                   :subtitle="$order->order_number.' · demandé le '.$order->requested_at->translatedFormat('d F Y')"
                   :breadcrumbs="[
                       'Imagerie' => route('dme.imaging.index'),
                       $order->patient->fullName() => route('dme.patients.show', $order->patient),
                       $order->order_number => null,
                   ]"/>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <section class="k-card">
                <div class="k-card-header">
                    <h2 class="k-card-title">Compte rendu</h2>
                    <x-dme::status-badge :status="$order->status" :label="$order->statusLabel()"/>
                </div>
                <div class="k-card-body">
                    @if ($report = $order->report)
                        @if ($report->is_abnormal)
                            <div class="k-alert-warning mb-3">
                                <x-dme::icon name="alert" class="mt-0.5 h-5 w-5 shrink-0 text-amber-600"/>
                                <p class="text-sm text-amber-800">Compte rendu signalant une anomalie.</p>
                            </div>
                        @endif
                        <div class="space-y-3">
                            @foreach ([
                                'Technique' => $report->technique,
                                'Résultats' => $report->findings,
                                'Conclusion' => $report->conclusion,
                            ] as $label => $value)
                                @if ($value)
                                    <div>
                                        <h3 class="text-xs font-semibold tracking-wide text-ink-500 uppercase">{{ $label }}</h3>
                                        <p class="mt-1 text-sm leading-relaxed whitespace-pre-line text-ink-800">{{ $value }}</p>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                        <p class="mt-3 border-t border-ink-100 pt-3 text-xs text-ink-500">
                            {{ $report->radiologist?->displayName() ?? 'Radiologue non renseigné' }}
                            @if ($report->reported_at)
                                · {{ $report->reported_at->translatedFormat('d M Y à H:i') }}
                            @endif
                            · <x-dme::status-badge :status="$report->status"
                                :label="match ($report->status) {
                                    'draft' => 'Brouillon', 'final' => 'Définitif', default => 'Rectifié',
                                }"/>
                        </p>
                    @else
                        <x-dme::empty-state icon="document" title="Compte rendu non rédigé"
                                       message="Le compte rendu sera saisi par le radiologue après réalisation de l'examen."/>
                    @endif
                </div>
            </section>

            {{-- Rédaction du compte rendu (§24) --}}
            @can('report', $order)
                <section class="k-card">
                    <div class="k-card-header">
                        <h2 class="k-card-title">
                            {{ $order->report ? 'Modifier le compte rendu' : 'Rédiger le compte rendu' }}
                        </h2>
                    </div>
                    <form action="{{ route('dme.imaging.report.store', $order) }}" method="POST" class="k-card-body space-y-3">
                        @csrf
                        <div>
                            <label for="technique" class="k-label">Technique</label>
                            <textarea id="technique" name="technique" rows="2" maxlength="5000"
                                      class="k-textarea">{{ old('technique', $order->report?->technique) }}</textarea>
                        </div>
                        <div>
                            <label for="findings" class="k-label">Résultats <span class="text-red-600" aria-hidden="true">*</span></label>
                            <textarea id="findings" name="findings" rows="6" required maxlength="20000"
                                      class="k-textarea">{{ old('findings', $order->report?->findings) }}</textarea>
                            <x-dme::field-error name="findings"/>
                        </div>
                        <div>
                            <label for="conclusion" class="k-label">Conclusion <span class="text-red-600" aria-hidden="true">*</span></label>
                            <textarea id="conclusion" name="conclusion" rows="3" required maxlength="5000"
                                      class="k-textarea">{{ old('conclusion', $order->report?->conclusion) }}</textarea>
                            <x-dme::field-error name="conclusion"/>
                        </div>
                        <div class="flex flex-wrap items-end gap-4">
                            <div>
                                <label for="report_status" class="k-label">Statut <span class="text-red-600" aria-hidden="true">*</span></label>
                                <select id="report_status" name="status" required class="k-select">
                                    <option value="draft">Brouillon</option>
                                    <option value="final" @selected($order->report?->status === 'final')>Définitif</option>
                                    <option value="amended" @selected($order->report?->status === 'amended')>Rectifié</option>
                                </select>
                            </div>
                            <label class="flex items-center gap-2 pb-2 text-sm text-ink-700">
                                <input type="checkbox" name="is_abnormal" value="1"
                                       @checked($order->report?->is_abnormal)
                                       class="h-4 w-4 rounded border-ink-300 text-clinic-600">
                                Signaler une anomalie
                            </label>
                        </div>
                        <button type="submit" class="k-btn-primary">Enregistrer le compte rendu</button>
                    </form>
                </section>
            @endcan

            @if ($documents->isNotEmpty())
                <section class="k-card">
                    <div class="k-card-header"><h2 class="k-card-title">Documents rattachés</h2></div>
                    <ul class="k-card-body space-y-2">
                        @foreach ($documents as $document)
                            <li class="flex items-center justify-between gap-2 text-sm">
                                <a href="{{ route('dme.documents.show', $document) }}" class="text-clinic-700 hover:underline">
                                    {{ $document->title }}
                                </a>
                                <span class="flex items-center gap-3">
                                    {{-- Ouvrir plutôt que télécharger : un compte rendu se
                                         regarde, et chaque téléchargement laisse une copie du
                                         dossier sur le poste qui l'a consulté. --}}
                                    @if ($document->isPreviewable())
                                        <a href="{{ route('dme.documents.preview', $document) }}"
                                           target="_blank" rel="noopener"
                                           class="text-xs text-clinic-700 hover:underline">Voir</a>
                                    @endif
                                    <span class="text-xs text-ink-500">{{ $document->humanSize() }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        <div class="space-y-4">
            <section class="k-card">
                <div class="k-card-header"><h2 class="k-card-title">Patient</h2></div>
                <div class="k-card-body"><x-dme::patient-header :patient="$order->patient" compact/></div>
            </section>

            <section class="k-card">
                <div class="k-card-header"><h2 class="k-card-title">Demande</h2></div>
                <dl class="k-card-body space-y-2.5 text-sm">
                    <div>
                        <dt class="text-xs text-ink-500">Prescripteur</dt>
                        <dd class="font-medium text-ink-900">{{ $order->doctor?->displayName() ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-ink-500">Indication</dt>
                        <dd class="text-ink-800">{{ $order->indication ?: 'Non précisée' }}</dd>
                    </div>
                    @if ($order->scheduled_for)
                        <div>
                            <dt class="text-xs text-ink-500">Programmé pour</dt>
                            <dd class="text-ink-800">{{ $order->scheduled_for->translatedFormat('d M Y à H:i') }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-xs text-ink-500">N° d'accession</dt>
                        <dd class="font-mono text-xs text-ink-700">{{ $order->accession_number ?: '-' }}</dd>
                        <dd class="mt-0.5 text-[11px] text-ink-400">
                            Réservé à une future intégration DICOM/PACS.
                        </dd>
                    </div>
                </dl>
            </section>
        </div>
    </div>
@endsection
