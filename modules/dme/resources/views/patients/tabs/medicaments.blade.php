{{-- Traitements habituels (§18) --}}
<div class="grid gap-4 lg:grid-cols-3">
    <section class="k-card lg:col-span-2">
        <div class="k-card-header">
            <h2 class="k-card-title">Traitements habituels</h2>
            <span class="text-xs text-ink-500">{{ $tabData['medications']->count() }} ligne(s)</span>
        </div>

        @if ($tabData['medications']->isEmpty())
            <x-dme::empty-state icon="pill" title="Aucun traitement habituel"
                           message="Les traitements de fond du patient, distincts des ordonnances ponctuelles, se saisissent ici."/>
        @else
            <div class="overflow-x-auto">
                <table class="k-table">
                    <caption class="sr-only">Traitements habituels</caption>
                    <thead>
                        <tr>
                            <th scope="col">Médicament</th>
                            <th scope="col">Dosage</th>
                            <th scope="col">Fréquence</th>
                            <th scope="col">Voie</th>
                            <th scope="col">Depuis</th>
                            <th scope="col">Prescripteur</th>
                            <th scope="col">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tabData['medications'] as $medication)
                            <tr>
                                <td class="font-medium text-ink-900">{{ $medication->name }}</td>
                                <td>{{ $medication->dosage ?: '-' }}</td>
                                <td>{{ $medication->frequency ?: '-' }}</td>
                                <td>{{ $medication->route ?: '-' }}</td>
                                <td>{{ $medication->started_on?->translatedFormat('M Y') ?: '-' }}</td>
                                <td class="text-xs text-ink-500">{{ $medication->prescriber?->displayName() ?? '-' }}</td>
                                <td><x-dme::status-badge :status="$medication->status" :label="$medication->statusLabel()"/></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @can('update', $patient)
        <section class="k-card self-start">
            <div class="k-card-header"><h2 class="k-card-title">Ajouter un traitement</h2></div>
            <form action="{{ route('dme.record.medications.store', $patient) }}" method="POST" class="k-card-body space-y-3">
                @csrf
                <div>
                    <label for="med_name" class="k-label">Médicament <span class="text-red-600" aria-hidden="true">*</span></label>
                    <input id="med_name" name="name" type="text" required maxlength="200" class="k-input">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="med_dosage" class="k-label">Dosage</label>
                        <input id="med_dosage" name="dosage" type="text" maxlength="100" class="k-input" placeholder="500 mg">
                    </div>
                    <div>
                        <label for="med_route" class="k-label">Voie</label>
                        <input id="med_route" name="route" type="text" maxlength="50" class="k-input" placeholder="Orale">
                    </div>
                </div>
                <div>
                    <label for="med_frequency" class="k-label">Fréquence</label>
                    <input id="med_frequency" name="frequency" type="text" maxlength="100" class="k-input"
                           placeholder="2 fois par jour">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="started_on" class="k-label">Début</label>
                        <input id="started_on" name="started_on" type="date" class="k-input">
                    </div>
                    <div>
                        <label for="ended_on" class="k-label">Fin</label>
                        <input id="ended_on" name="ended_on" type="date" class="k-input">
                    </div>
                </div>
                <div>
                    <label for="med_status" class="k-label">Statut <span class="text-red-600" aria-hidden="true">*</span></label>
                    <select id="med_status" name="status" required class="k-select">
                        @foreach (\Keneya\Dme\Models\Medication::STATUSES as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="k-btn-primary w-full">Enregistrer</button>
            </form>
        </section>
    @endcan
</div>
