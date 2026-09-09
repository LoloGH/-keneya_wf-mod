{{-- Soins programmes d'un sejour, corrigeables et annulables (v3.2.3, point 4).

     Rien ne s'efface : un soin annule reste affiche, barre, avec son motif et
     son auteur. C'est la seule facon de corriger un compte sans reecrire le
     dossier du patient. --}}
<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>Heure</th>
                <th>Soin</th>
                <th>Instructions</th>
                <th>Etat</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($careTasks as $task)
                <tr wire:key="care-task-{{ $task->id }}"
                    @class(['is-cancelled' => $task->isCancelled()])>
                    <td class="mono">{{ $task->scheduled_at->format('d/m H:i') }}</td>
                    <td>{{ $task->type->name }}</td>
                    <td>{{ $task->instructions ?: '-' }}</td>
                    <td>
                        {{ $task->statusLabel() }}
                        @if ($task->isCancelled())
                            <span class="field__hint">
                                - {{ $task->cancellation_reason }}
                                ({{ $task->cancelledBy?->name }})
                            </span>
                        @elseif ($task->isDone())
                            <span class="field__hint">- {{ $task->completedBy?->name }}</span>
                        @elseif ($task->assignedTo)
                            <span class="field__hint">, confie a {{ $task->assignedTo->name }}</span>
                        @endif
                    </td>
                    <td>
                        @unless ($task->isCancelled())
                            <div class="btn-row">
                                <button type="button" class="btn btn--ghost"
                                        wire:click="startRevision({{ $task->id }})">Corriger</button>
                                <button type="button" class="btn btn--ghost"
                                        wire:click="startCancellation({{ $task->id }})">Annuler</button>
                            </div>
                        @endunless
                    </td>
                </tr>

                @if ($revisingTaskId === $task->id)
                    <tr wire:key="care-task-revise-{{ $task->id }}">
                        <td colspan="5">
                            <form wire:submit="saveRevision" class="form my-patients__form">
                                <div class="form form--inline-wrap">
                                    <div class="field">
                                        <label for="revise-type-{{ $task->id }}">Type de soin</label>
                                        <select id="revise-type-{{ $task->id }}" wire:model="reviseTypeId">
                                            @foreach ($careTaskTypes as $type)
                                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('reviseTypeId') <p class="field__error">{{ $message }}</p> @enderror
                                    </div>

                                    <div class="field">
                                        <label for="revise-at-{{ $task->id }}">Heure</label>
                                        <input id="revise-at-{{ $task->id }}" type="datetime-local"
                                               wire:model="reviseScheduledAt">
                                        @error('reviseScheduledAt') <p class="field__error">{{ $message }}</p> @enderror
                                    </div>

                                    <div class="field">
                                        <label for="revise-assign-{{ $task->id }}">Confier a</label>
                                        <select id="revise-assign-{{ $task->id }}" wire:model="reviseAssignedToUserId">
                                            <option value="">Personnel de garde</option>
                                            @foreach ($carers as $carer)
                                                <option value="{{ $carer->id }}">{{ $carer->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('reviseAssignedToUserId') <p class="field__error">{{ $message }}</p> @enderror
                                    </div>
                                </div>

                                <div class="field">
                                    <label for="revise-instructions-{{ $task->id }}">Instructions</label>
                                    <textarea id="revise-instructions-{{ $task->id }}" rows="2"
                                              wire:model="reviseInstructions"></textarea>
                                    @error('reviseInstructions') <p class="field__error">{{ $message }}</p> @enderror
                                </div>

                                <div class="btn-row">
                                    <button type="submit" class="btn btn--primary">Enregistrer la correction</button>
                                    <button type="button" class="btn btn--ghost"
                                            wire:click="closeCareTaskForms">Annuler</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                @endif

                @if ($cancellingTaskId === $task->id)
                    <tr wire:key="care-task-cancel-{{ $task->id }}">
                        <td colspan="5">
                            <form wire:submit="confirmCancellation" class="form my-patients__form">
                                <div class="field">
                                    <label for="cancel-reason-{{ $task->id }}">
                                        Motif de l'annulation
                                        <span class="field__hint">il reste au dossier</span>
                                    </label>
                                    <input id="cancel-reason-{{ $task->id }}" type="text"
                                           wire:model="cancellationReason"
                                           placeholder="Erreur de saisie, prescription arretee...">
                                    @error('cancellationReason') <p class="field__error">{{ $message }}</p> @enderror
                                </div>

                                <div class="btn-row">
                                    <button type="submit" class="btn btn--primary">Confirmer l'annulation</button>
                                    <button type="button" class="btn btn--ghost"
                                            wire:click="closeCareTaskForms">Revenir</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                @endif
            @empty
                <tr><td colspan="5" class="empty">Aucun soin programme pour ce sejour.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
