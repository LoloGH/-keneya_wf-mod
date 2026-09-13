{{-- Allergies (§17) --}}
<div class="grid gap-4 lg:grid-cols-3">
    <section class="k-card lg:col-span-2">
        <div class="k-card-header">
            <h2 class="k-card-title">Allergies documentées</h2>
            <span class="text-xs text-ink-500">{{ $tabData['allergies']->count() }} enregistrement(s)</span>
        </div>

        @if ($tabData['allergies']->isEmpty())
            <x-dme::empty-state icon="alert" title="Aucune allergie documentée"
                           message="Renseignez les allergies connues : une allergie sévère devient une alerte permanente du dossier et sera confrontée à chaque nouvelle ordonnance."/>
        @else
            <div class="overflow-x-auto">
                <table class="k-table">
                    <caption class="sr-only">Allergies du patient</caption>
                    <thead>
                        <tr>
                            <th scope="col">Allergène</th>
                            <th scope="col">Réaction</th>
                            <th scope="col">Gravité</th>
                            <th scope="col">Constatée le</th>
                            <th scope="col">Statut</th>
                            <th scope="col">Consignée par</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tabData['allergies'] as $allergy)
                            <tr class="{{ $allergy->isCritical() ? 'bg-red-50/60' : '' }}">
                                <td class="font-medium text-ink-900">{{ $allergy->allergen }}</td>
                                <td>{{ $allergy->reaction ?: '-' }}</td>
                                <td><x-dme::status-badge :status="$allergy->severity" :label="$allergy->severityLabel()"/></td>
                                <td>{{ $allergy->observed_on?->translatedFormat('d M Y') ?: '-' }}</td>
                                <td>
                                    <x-dme::status-badge :status="$allergy->status"
                                        :label="match ($allergy->status) {
                                            'active' => 'Active', 'resolved' => 'Résolue', default => 'Invalidée',
                                        }"/>
                                </td>
                                <td class="text-xs text-ink-500">{{ $allergy->recorder?->displayName() ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @can('update', $patient)
        <section class="k-card">
            <div class="k-card-header"><h2 class="k-card-title">Ajouter une allergie</h2></div>
            <form action="{{ route('dme.record.allergies.store', $patient) }}" method="POST" class="k-card-body space-y-3">
                @csrf
                <div>
                    <label for="allergen" class="k-label">Allergène <span class="text-red-600" aria-hidden="true">*</span></label>
                    <input id="allergen" name="allergen" type="text" required maxlength="150" class="k-input"
                           placeholder="Pénicilline, iode, arachide...">
                </div>
                <div>
                    <label for="allergen_type" class="k-label">Type</label>
                    <select id="allergen_type" name="allergen_type" class="k-select">
                        <option value="medication">Médicament</option>
                        <option value="food">Aliment</option>
                        <option value="environment">Environnement</option>
                        <option value="other">Autre</option>
                    </select>
                </div>
                <div>
                    <label for="reaction" class="k-label">Réaction observée</label>
                    <input id="reaction" name="reaction" type="text" maxlength="255" class="k-input"
                           placeholder="Urticaire, œdème de Quincke...">
                </div>
                <div>
                    <label for="severity" class="k-label">Gravité <span class="text-red-600" aria-hidden="true">*</span></label>
                    <select id="severity" name="severity" required class="k-select">
                        <option value="unknown">Inconnue</option>
                        <option value="mild">Faible</option>
                        <option value="moderate">Modérée</option>
                        <option value="severe">Sévère</option>
                    </select>
                    <p class="k-hint">Une allergie sévère apparaît en alerte permanente du dossier.</p>
                </div>
                <div>
                    <label for="observed_on" class="k-label">Constatée le</label>
                    <input id="observed_on" name="observed_on" type="date" max="{{ now()->toDateString() }}" class="k-input">
                </div>
                <div>
                    <label for="allergy_status" class="k-label">Statut</label>
                    <select id="allergy_status" name="status" required class="k-select">
                        <option value="active">Active</option>
                        <option value="resolved">Résolue</option>
                        <option value="refuted">Invalidée</option>
                    </select>
                </div>
                <button type="submit" class="k-btn-primary w-full">Enregistrer l'allergie</button>
            </form>
        </section>
    @endcan
</div>
