<section class="card">
    <h2 class="card__title">SMS</h2>
    <p class="hint">
        Chaque SMS est mis en file puis envoye en arriere-plan : l'enregistrement
        d'un patient n'attend plus la passerelle. Cette liste remplace le journal
        texte ou les echecs passaient inapercus.
    </p>

    {{-- L'indicateur qui manquait : un echec ne peut plus etre silencieux. --}}
    <div class="sms-tally {{ $failureCount > 0 ? 'sms-tally--alerte' : 'sms-tally--calme' }}">
        <span class="sms-tally__nombre">{{ $failureCount }}</span>
        <span>
            @if ($failureCount === 0)
                aucun echec d'envoi dans les dernieres 24 h.
            @else
                {{ $failureCount > 1 ? 'echecs d\'envoi' : 'echec d\'envoi' }} dans les dernieres 24 h.
                Verifiez que le telephone qui heberge SMSGate est allume, branche et joignable.
            @endif
        </span>

        @if ($failureCount > 0)
            <button type="button" class="btn btn--ghost" wire:click="showFailures">Voir les echecs</button>
        @endif
    </div>

    <div class="form form--inline-wrap">
        <div class="field">
            <label for="sms-status">Statut</label>
            <select id="sms-status" wire:model.live="status">
                <option value="">Tous</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="sms-search">Numero ou texte</label>
            <input id="sms-search" type="search" wire:model.live.debounce.400ms="search" placeholder="70 11 22 33">
        </div>

        <button type="button" class="btn btn--ghost" wire:click="resetFilters">Reinitialiser</button>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Mise en file</th>
                    <th>Destinataire</th>
                    <th>Message</th>
                    <th>Statut</th>
                    <th>Tentatives</th>
                    <th>Detail</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($messages as $message)
                    <tr>
                        <td class="mono">{{ $message->created_at->format('d/m/Y H:i') }}</td>
                        <td class="mono">{{ $message->to }}</td>
                        <td class="sms-body">{{ \Illuminate\Support\Str::limit($message->body, 90) }}</td>
                        <td>
                            <span class="badge badge--{{ $message->status }}">{{ $message->statusLabel() }}</span>
                        </td>
                        <td class="mono">{{ $message->attempts }}</td>
                        <td>
                            @if ($message->failure_reason)
                                <span class="sms-reason">{{ $message->failure_reason }}</span>
                            @elseif ($message->sent_at)
                                Accepte le {{ $message->sent_at->format('d/m/Y H:i') }}
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">Aucun SMS ne correspond a ces filtres.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $messages->links() }}

    <p class="hint">
        « Envoye » signifie accepte par la passerelle, pas recu par le patient :
        la configuration actuelle de SMSGate ne renvoie aucun accuse de remise
        (voir docs/exploitation-demo.md). Le statut « Remis » reste donc inutilise
        tant que la passerelle n'expose pas cette information.
    </p>
</section>
