{{-- Soins infirmiers (§26).

     Deux registres, volontairement distincts :
       · les soins programmés, ce qui est demandé, par qui, pour qui ;
       · les transmissions, ce qui a réellement eu lieu.
     Confondre les deux ferait perdre la trace de l'écart entre la
     prescription et son exécution, qui est précisément ce qu'un dossier
     doit pouvoir montrer. --}}

@can('care_orders.view')
    @php
        $openOrders = $tabData['careOrders']->where('status', 'planned');
        $closedOrders = $tabData['careOrders']->where('status', '!=', 'planned');
    @endphp

    <section class="k-card mb-4">
        <div class="k-card-header">
            <h2 class="k-card-title">Soins programmés</h2>
            <span class="text-xs text-ink-500">
                {{ $openOrders->count() }} en cours · {{ $tabData['careOrders']->count() }} au total
            </span>
        </div>

        <div class="k-card-body">
            @if ($tabData['careOrders']->isEmpty())
                <x-dme::empty-state icon="calendar" title="Aucun soin programmé"
                               message="Un soin prescrit apparaît ici jusqu'à sa réalisation. Confié à un soignant, il ne concerne que lui ; laissé ouvert, il revient au personnel de garde du service prescripteur."/>
            @else
                <ul class="space-y-2.5">
                    @foreach ($openOrders->concat($closedOrders) as $order)
                        <li class="rounded-lg border p-3 @class([
                                'border-red-300 bg-red-50/40' => $order->isOverdue(),
                                'border-ink-200' => ! $order->isOverdue() && $order->isOpen(),
                                'border-ink-200 bg-ink-50/60' => ! $order->isOpen(),
                            ])">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-mono text-xs text-ink-500">{{ $order->reference }}</span>
                                        <x-dme::status-badge
                                            :status="match ($order->status) {
                                                'planned' => $order->isOverdue() ? 'cancelled' : 'active',
                                                'completed' => 'active',
                                                default => 'inactive',
                                            }"
                                            :label="$order->isOverdue() ? 'En retard' : $order->statusLabel()"/>
                                        @if ($order->priority === 'urgent')
                                            <span class="k-badge-danger">Urgent</span>
                                        @endif
                                    </div>
                                    <p class="mt-1 text-sm font-medium text-ink-900">{{ $order->title }}</p>
                                    @if ($order->instructions)
                                        <p class="mt-0.5 text-sm text-ink-600">{{ $order->instructions }}</p>
                                    @endif
                                    <p class="mt-1.5 text-xs text-ink-500">
                                        {{ $order->starts_at->translatedFormat('d M Y · H:i') }}
                                        @if ($order->ends_at) -> {{ $order->ends_at->translatedFormat('d M Y · H:i') }} @endif
                                        @if ($order->frequency) · {{ $order->frequency }} @endif
                                    </p>
                                    <p class="mt-0.5 text-xs text-ink-500">
                                        Prescrit par {{ $order->prescriber?->displayName() ?? '-' }}
                                        @if ($order->service) · {{ $order->service->name }} @endif
                                        ·
                                        @if ($order->assignedNurse)
                                            confié à <strong>{{ $order->assignedNurse->displayName() }}</strong>
                                        @else
                                            <strong>ouvert à la garde du service</strong>
                                        @endif
                                    </p>
                                    @if (! $order->isOpen())
                                        <p class="mt-1 text-xs text-ink-500">
                                            {{ $order->statusLabel() }}
                                            @if ($order->completed_at) le {{ $order->completed_at->translatedFormat('d M Y · H:i') }} @endif
                                            @if ($order->completedBy) par {{ $order->completedBy->displayName() }} @endif
                                        </p>
                                        @if ($order->outcome)
                                            <p class="mt-0.5 text-xs text-ink-600">{{ $order->outcome }}</p>
                                        @endif
                                    @endif
                                </div>

                                @if ($order->isOpen())
                                    <div class="flex shrink-0 flex-wrap items-center gap-1.5">
                                        @can('execute', $order)
                                            <form action="{{ route('dme.care-orders.execute', $order) }}" method="POST">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="status" value="completed">
                                                <button type="submit" class="k-btn-primary k-btn-sm">
                                                    <x-dme::icon name="check" class="h-3.5 w-3.5"/> Réalisé
                                                </button>
                                            </form>
                                        @endcan
                                        @can('assign', $order)
                                            <form action="{{ route('dme.care-orders.assign', $order) }}" method="POST"
                                                  class="flex items-center gap-1.5">
                                                @csrf @method('PATCH')
                                                <label class="sr-only" for="assign-{{ $order->id }}">Confier ce soin</label>
                                                <select id="assign-{{ $order->id }}" name="assigned_nurse_id"
                                                        class="k-select k-btn-sm w-40 text-xs">
                                                    <option value="">Ouvert à la garde</option>
                                                    @foreach ($tabData['assignableNurses'] as $nurse)
                                                        <option value="{{ $nurse->id }}"
                                                            @selected($order->assigned_nurse_id === $nurse->id)>
                                                            {{ $nurse->displayName() }}{{ $nurse->is_on_duty ? ' · de garde' : ($nurse->isScheduledNow() ? ' · horaire habituel' : '') }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="k-btn-secondary k-btn-sm">Confier</button>
                                            </form>
                                        @endcan
                                        @can('cancel', $order)
                                            <form action="{{ route('dme.care-orders.cancel', $order) }}" method="POST"
                                                  onsubmit="return (this.outcome.value = prompt('Motif d'annulation ?') || '') !== '';">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="outcome" value="">
                                                <button type="submit" class="k-btn-ghost k-btn-sm text-red-600">
                                                    Annuler
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

            @can('create', App\Models\CareOrder::class)
                <details class="mt-4 rounded-lg border border-ink-200">
                    <summary class="cursor-pointer px-3 py-2 text-sm font-medium text-clinic-700">
                        Prescrire un soin
                    </summary>
                    <form action="{{ route('dme.care-orders.store', $patient) }}" method="POST"
                          class="space-y-3 border-t border-ink-100 p-3">
                        @csrf

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label for="care-title" class="k-label">Intitulé du soin</label>
                                <input id="care-title" name="title" required maxlength="200"
                                       value="{{ old('title') }}"
                                       class="k-input @error('title') border-red-500 @enderror"
                                       placeholder="Pansement, surveillance, injection...">
                                <x-dme::field-error name="title"/>
                            </div>

                            <div class="sm:col-span-2">
                                <label for="care-instructions" class="k-label">Consignes</label>
                                <textarea id="care-instructions" name="instructions" rows="2" maxlength="5000"
                                          class="k-textarea">{{ old('instructions') }}</textarea>
                                <x-dme::field-error name="instructions"/>
                            </div>

                            <div>
                                <label for="care-starts" class="k-label">Début</label>
                                <input id="care-starts" name="starts_at" type="datetime-local" required
                                       value="{{ old('starts_at', now()->format('Y-m-d\TH:i')) }}"
                                       class="k-input @error('starts_at') border-red-500 @enderror">
                                <x-dme::field-error name="starts_at"/>
                            </div>

                            <div>
                                <label for="care-ends" class="k-label">Fin <span class="text-ink-400">(facultatif)</span></label>
                                <input id="care-ends" name="ends_at" type="datetime-local"
                                       value="{{ old('ends_at') }}"
                                       class="k-input @error('ends_at') border-red-500 @enderror">
                                <x-dme::field-error name="ends_at"/>
                            </div>

                            <div>
                                <label for="care-frequency" class="k-label">Fréquence</label>
                                <input id="care-frequency" name="frequency" maxlength="120"
                                       value="{{ old('frequency') }}" class="k-input"
                                       placeholder="Toutes les 8 h, 2 fois par jour...">
                            </div>

                            <div>
                                <label for="care-priority" class="k-label">Priorité</label>
                                <select id="care-priority" name="priority" class="k-select">
                                    @foreach (App\Models\CareOrder::PRIORITIES as $value => $label)
                                        <option value="{{ $value }}" @selected(old('priority', 'routine') === $value)>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            @if ($tabData['openHospitalizations']->isNotEmpty())
                                <div>
                                    <label for="care-hosp" class="k-label">Rattacher au séjour</label>
                                    <select id="care-hosp" name="hospitalization_id" class="k-select">
                                        <option value="">Aucun</option>
                                        @foreach ($tabData['openHospitalizations'] as $hosp)
                                            <option value="{{ $hosp->id }}"
                                                @selected(old('hospitalization_id') == $hosp->id)>
                                                {{ $hosp->hospitalization_number }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            @can('care_orders.assign')
                                <div>
                                    <label for="care-nurse" class="k-label">Confier à</label>
                                    <select id="care-nurse" name="assigned_nurse_id"
                                            class="k-select @error('assigned_nurse_id') border-red-500 @enderror">
                                        <option value="">Personnel de garde du service</option>
                                        @foreach ($tabData['assignableNurses'] as $nurse)
                                            <option value="{{ $nurse->id }}"
                                                @selected(old('assigned_nurse_id') == $nurse->id)>
                                                {{ $nurse->displayName() }}{{ $nurse->is_on_duty ? ' · de garde' : ($nurse->isScheduledNow() ? ' · horaire habituel' : '') }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-dme::field-error name="assigned_nurse_id"/>
                                    @if ($tabData['assignableNurses']->isEmpty())
                                        <p class="k-hint mt-1">
                                            Aucun infirmier n'est rattaché à votre service : le soin restera
                                            ouvert à la garde.
                                        </p>
                                    @endif
                                </div>
                            @endcan
                        </div>

                        <p class="k-hint">
                            Sans soignant nommé, le soin est visible par le personnel de garde du service
                            prescripteur : pas au-delà. Le prescripteur et l'administration y ont toujours accès.
                        </p>

                        <button type="submit" class="k-btn-primary w-full sm:w-auto">
                            <x-dme::icon name="plus" class="h-4 w-4"/> Programmer le soin
                        </button>
                    </form>
                </details>
            @endcan
        </div>
    </section>
@endcan

<div class="grid gap-4 lg:grid-cols-3">
    <section class="k-card lg:col-span-2">
        <div class="k-card-header">
            <h2 class="k-card-title">Soins et transmissions</h2>
            <span class="text-xs text-ink-500">{{ $tabData['nursingNotes']->total() }} entrée(s)</span>
        </div>

        @if ($tabData['nursingNotes']->isEmpty())
            <x-dme::empty-state icon="heart" title="Aucun soin enregistré"
                           message="Constantes, soins, administrations et transmissions se consignent ici, horodatés et signés."/>
        @else
            <div class="k-card-body">
                <ol class="relative space-y-3 border-l border-ink-200 pl-5">
                    @foreach ($tabData['nursingNotes'] as $note)
                        <li class="relative">
                            <span class="absolute top-2 -left-[27px] flex h-3 w-3 rounded-full border-2 border-white
                                @class([
                                    'bg-red-500' => $note->severity === 'critical',
                                    'bg-amber-500' => $note->severity === 'warning',
                                    'bg-clinic-500' => $note->severity === 'info',
                                ])"></span>
                            <div class="rounded-lg border border-ink-200 p-3">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-xs text-ink-500">
                                            {{ $note->occurred_at->translatedFormat('d M Y · H:i') }}
                                        </span>
                                        <span class="k-badge-info">{{ $note->typeLabel() }}</span>
                                    </div>
                                    @if ($note->severity !== 'info')
                                        <x-dme::status-badge :status="$note->severity"
                                            :label="$note->severity === 'critical' ? 'Critique' : 'Vigilance'"/>
                                    @endif
                                </div>
                                <p class="mt-1.5 text-sm font-medium text-ink-900">{{ $note->title }}</p>
                                @if ($note->content)
                                    <p class="mt-0.5 text-sm text-ink-600">{{ $note->content }}</p>
                                @endif
                                @if ($note->medication_name)
                                    <p class="mt-1 text-xs text-ink-500">
                                        {{ $note->medication_name }}
                                        @if ($note->medication_dose) · {{ $note->medication_dose }} @endif
                                        @if ($note->medication_route) · {{ $note->medication_route }} @endif
                                    </p>
                                @endif
                                <p class="mt-1.5 text-xs text-ink-400">{{ $note->nurse?->displayName() ?? 'Auteur non renseigné' }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
                <div class="mt-4">{{ $tabData['nursingNotes']->links() }}</div>
            </div>
        @endif
    </section>

    @can('nursing.create')
        <section class="k-card self-start">
            <div class="k-card-header"><h2 class="k-card-title">Enregistrer un soin</h2></div>
            <form action="{{ route('dme.nursing.store', $patient) }}" method="POST" class="k-card-body space-y-3"
                  x-data="{ type: 'care' }">
                @csrf
                <div>
                    <label for="nursing_type" class="k-label">Type <span class="text-red-600" aria-hidden="true">*</span></label>
                    <select id="nursing_type" name="type" x-model="type" required class="k-select">
                        @foreach (\Keneya\Dme\Models\NursingNote::TYPES as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="occurred_at" class="k-label">Date et heure <span class="text-red-600" aria-hidden="true">*</span></label>
                    <input id="occurred_at" name="occurred_at" type="datetime-local" required
                           value="{{ now()->format('Y-m-d\TH:i') }}" max="{{ now()->format('Y-m-d\TH:i') }}" class="k-input">
                </div>
                <div>
                    <label for="nursing_title" class="k-label">Intitulé <span class="text-red-600" aria-hidden="true">*</span></label>
                    <input id="nursing_title" name="title" type="text" required maxlength="200" class="k-input">
                </div>

                <div x-show="type === 'medication_administration'" x-cloak class="space-y-3">
                    <div>
                        <label for="medication_name" class="k-label">Médicament</label>
                        <input id="medication_name" name="medication_name" type="text" maxlength="200" class="k-input">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="medication_dose" class="k-label">Dose</label>
                            <input id="medication_dose" name="medication_dose" type="text" maxlength="100" class="k-input">
                        </div>
                        <div>
                            <label for="medication_route" class="k-label">Voie</label>
                            <input id="medication_route" name="medication_route" type="text" maxlength="50" class="k-input">
                        </div>
                    </div>
                </div>

                <div>
                    <label for="nursing_content" class="k-label">Observation</label>
                    <textarea id="nursing_content" name="content" rows="3" maxlength="5000" class="k-textarea"></textarea>
                </div>
                <div>
                    <label for="severity" class="k-label">Criticité <span class="text-red-600" aria-hidden="true">*</span></label>
                    <select id="severity" name="severity" required class="k-select">
                        <option value="info">Information</option>
                        <option value="warning">Vigilance</option>
                        <option value="critical">Critique</option>
                    </select>
                </div>
                <button type="submit" class="k-btn-primary w-full">Enregistrer le soin</button>
            </form>
        </section>
    @endcan
</div>
