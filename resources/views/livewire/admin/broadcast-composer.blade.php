@php use App\Models\BroadcastMessage; @endphp

<x-card title="SMS groupes" icon="sms">
    <p class="hint">
        Le message part par la file d'attente, comme tous les SMS de
        l'application : l'ecran rend la main immediatement, meme pour plusieurs
        centaines de destinataires. Leur acheminement se suit dans la section
        « SMS ».
    </p>

    <form wire:submit="preview" class="form">
        <div class="field">
            <label for="diffusion-message">Message</label>
            <textarea id="diffusion-message" rows="4" wire:model.live="content"
                      maxlength="480"
                      placeholder="Ex. : La campagne de vaccination se tient du 12 au 16 mars, de 8h a 14h."></textarea>
            <p class="hint">{{ mb_strlen($content) }} / 480 caracteres</p>
            @error('content') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="diffusion-cible">Destinataires</label>
            <select id="diffusion-cible" wire:model.live="targetType">
                @foreach ($targets as $valeur => $libelle)
                    <option value="{{ $valeur }}">{{ $libelle }}</option>
                @endforeach
            </select>
            @error('targetType') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        {{-- Destinataire nommement designe : personnel ou patient. --}}
        @if (in_array($targetType, [BroadcastMessage::TARGET_STAFF, BroadcastMessage::TARGET_SINGLE_PATIENT], true))
            <div class="field">
                <label for="diffusion-recherche">
                    {{ $targetType === BroadcastMessage::TARGET_STAFF ? 'Rechercher un membre du personnel' : 'Rechercher un patient' }}
                </label>
                <input id="diffusion-recherche" type="search" autocomplete="off"
                       wire:model.live.debounce.400ms="search"
                       placeholder="{{ $targetType === BroadcastMessage::TARGET_STAFF ? 'Nom' : 'Nom ou code de dossier' }}">
            </div>

            @if ($staffResults->isNotEmpty() || $patientResults->isNotEmpty())
                <ul class="diffusion__resultats">
                    @foreach ($staffResults as $membre)
                        <li>
                            <span>{{ $membre->name }}
                                <span class="hint">{{ $membre->smsNumber() ?? 'sans telephone' }}</span>
                            </span>
                            <button type="button" class="btn btn--ghost" wire:click="selectStaff({{ $membre->id }})">Choisir</button>
                        </li>
                    @endforeach
                    @foreach ($patientResults as $patient)
                        <li>
                            <span>{{ $patient->name }}
                                <span class="mono">{{ $patient->patient_code }}</span>
                                <span class="hint">{{ $patient->mobile ?: 'sans telephone' }}</span>
                            </span>
                            <button type="button" class="btn btn--ghost" wire:click="selectPatient({{ $patient->id }})">Choisir</button>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($selectedStaff)
                <p class="acte-facture"><span>Destinataire</span> <strong>{{ $selectedStaff->name }}</strong>
                    <span class="acte-facture__tarif">{{ $selectedStaff->smsNumber() ?? 'sans telephone' }}</span></p>
            @endif
            @if ($selectedPatient)
                <p class="acte-facture"><span>Destinataire</span> <strong>{{ $selectedPatient->name }}</strong>
                    <span class="acte-facture__tarif">{{ $selectedPatient->mobile ?: 'sans telephone' }}</span></p>
            @endif

            @error('staffUserId') <p class="field__error">{{ $message }}</p> @enderror
            @error('patientId') <p class="field__error">{{ $message }}</p> @enderror
        @endif

        {{-- Groupe filtre par service, et par pathologie si elle est renseignee. --}}
        @if ($targetType === BroadcastMessage::TARGET_PATIENT_GROUP)
            <div class="field">
                <label for="diffusion-service">Service traverse</label>
                <select id="diffusion-service" wire:model.live="serviceId">
                    <option value="">— Choisir un service —</option>
                    @foreach ($services as $service)
                        <option value="{{ $service->id }}">{{ $service->name }}</option>
                    @endforeach
                </select>
                <p class="hint">
                    Tout patient passe par ce service, y compris lors d'un
                    passage deja clos — pas seulement ceux qui y attendent
                    aujourd'hui.
                </p>
                @error('serviceId') <p class="field__error">{{ $message }}</p> @enderror
            </div>
        @endif

        @if (in_array($targetType, [BroadcastMessage::TARGET_PATIENT_GROUP, BroadcastMessage::TARGET_ALL_PATIENTS], true))
            <div class="field">
                <label for="diffusion-pathologie">Pathologie <span class="field__hint">(facultatif)</span></label>
                <select id="diffusion-pathologie" wire:model.live="pathologyId">
                    <option value="">— Toutes —</option>
                    @foreach ($pathologies as $pathologie)
                        <option value="{{ $pathologie->id }}">{{ $pathologie->name }}</option>
                    @endforeach
                </select>
                @error('pathologyId') <p class="field__error">{{ $message }}</p> @enderror
            </div>
        @endif

        <button type="submit" class="btn btn--secondary" wire:loading.attr="disabled">
            Compter les destinataires
        </button>
    </form>

    {{-- L'apercu, et la confirmation. Rien ne part tant que ce bloc n'a pas
         ete affiche : on ne s'adresse pas a des centaines de personnes sans
         avoir vu combien elles sont. --}}
    @if ($previewCount !== null)
        <div class="sms-tally {{ $previewCount > 0 ? 'sms-tally--alerte' : 'sms-tally--calme' }}" role="status">
            <span class="sms-tally__nombre">{{ $previewCount }}</span>
            <span>
                @if ($previewCount === 0)
                    destinataire : personne ne correspond a cette selection.
                @else
                    {{ $previewCount > 1 ? 'destinataires recevront ce message.' : 'destinataire recevra ce message.' }}
                    Verifiez le texte et les filtres avant de confirmer.
                @endif
            </span>
        </div>

        @if ($previewCount > 0)
            <blockquote class="diffusion__apercu">{{ $content }}</blockquote>

            <button type="button" class="btn btn--primary" wire:click="send" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="send">Envoyer a {{ $previewCount }} destinataire(s)</span>
                <span wire:loading wire:target="send">Mise en file…</span>
            </button>
        @endif
    @endif

    @if ($recent->isNotEmpty())
        <h3 class="card__subtitle">Dernieres diffusions</h3>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Date</th><th>Par</th><th>Cible</th><th>Destinataires</th><th>Message</th></tr>
                </thead>
                <tbody>
                    @foreach ($recent as $diffusion)
                        <tr>
                            <td class="mono">{{ $diffusion->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $diffusion->sentBy?->name ?? '—' }}</td>
                            <td>{{ $diffusion->targetLabel() }}</td>
                            <td class="mono">{{ $diffusion->recipient_count }}</td>
                            <td class="sms-body">{{ \Illuminate\Support\Str::limit($diffusion->content, 80) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-card>
