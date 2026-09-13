{{-- Antécédents (§16) --}}
<div class="grid gap-4 lg:grid-cols-3">
    <div class="space-y-4 lg:col-span-2">
        @foreach (\Keneya\Dme\Models\MedicalHistory::CATEGORIES as $key => $label)
            @php $items = $tabData['histories'][$key] ?? collect(); @endphp
            <section class="k-card">
                <div class="k-card-header">
                    <h2 class="k-card-title">{{ $label }}</h2>
                    <span class="text-xs text-ink-500">{{ $items->count() }}</span>
                </div>
                <div class="k-card-body">
                    @forelse ($items as $history)
                        <div class="border-b border-ink-100 py-2.5 first:pt-0 last:border-0 last:pb-0">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <p class="text-sm font-medium text-ink-900">
                                    {{ $history->label }}
                                    @if ($history->code)
                                        <span class="ml-1 font-mono text-xs text-ink-400">{{ $history->code }}</span>
                                    @endif
                                </p>
                                <span class="text-xs text-ink-500">
                                    {{ $history->year ?: $history->occurred_on?->format('Y') ?: '-' }}
                                </span>
                            </div>
                            <p class="mt-0.5 text-xs text-ink-600">
                                @if ($history->relative) <span class="font-medium">{{ $history->relative }}</span> - @endif
                                @if ($history->facility) {{ $history->facility }} - @endif
                                {{ $history->comment ?: $history->complications ?: '' }}
                            </p>
                        </div>
                    @empty
                        <p class="py-1 text-sm text-ink-500">Aucun antécédent {{ mb_strtolower($label) }} renseigné.</p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>

    @can('update', $patient)
        <section class="k-card self-start">
            <div class="k-card-header"><h2 class="k-card-title">Ajouter un antécédent</h2></div>
            <form action="{{ route('dme.record.histories.store', $patient) }}" method="POST" class="k-card-body space-y-3"
                  x-data="{ category: 'personal' }">
                @csrf
                <div>
                    <label for="category" class="k-label">Catégorie <span class="text-red-600" aria-hidden="true">*</span></label>
                    <select id="category" name="category" x-model="category" required class="k-select">
                        @foreach (\Keneya\Dme\Models\MedicalHistory::CATEGORIES as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="label" class="k-label">Intitulé <span class="text-red-600" aria-hidden="true">*</span></label>
                    <input id="label" name="label" type="text" required maxlength="200" class="k-input"
                           placeholder="Pathologie, intervention, facteur...">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="year" class="k-label">Année</label>
                        <input id="year" name="year" type="text" maxlength="9" class="k-input" placeholder="2016">
                    </div>
                    <div>
                        <label for="code" class="k-label">Code CIM-10</label>
                        <input id="code" name="code" type="text" maxlength="20" class="k-input" placeholder="I10">
                    </div>
                </div>

                <div x-show="category === 'surgical'" x-cloak class="space-y-3">
                    <div>
                        <label for="facility" class="k-label">Établissement</label>
                        <input id="facility" name="facility" type="text" maxlength="150" class="k-input">
                    </div>
                    <div>
                        <label for="complications" class="k-label">Complications</label>
                        <input id="complications" name="complications" type="text" maxlength="1000" class="k-input">
                    </div>
                </div>

                <div x-show="category === 'family'" x-cloak>
                    <label for="relative" class="k-label">Parent concerné</label>
                    <input id="relative" name="relative" type="text" maxlength="100" class="k-input"
                           placeholder="Père, mère, fratrie...">
                </div>

                <div>
                    <label for="comment" class="k-label">Commentaire</label>
                    <textarea id="comment" name="comment" rows="2" maxlength="1000" class="k-textarea"></textarea>
                </div>

                <button type="submit" class="k-btn-primary w-full">
                    <x-dme::icon name="plus" class="h-4 w-4"/> Ajouter un antécédent
                </button>
            </form>
        </section>
    @endcan
</div>
