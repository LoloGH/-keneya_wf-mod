{{--
    La demande d'examen qui accompagne un renvoi vers un plateau technique
    (v3.3.1).

    Elle n'apparait pas d'elle-meme : elle suit la destination choisie. Le
    medecin dit ou il envoie le patient, et l'application sait quoi lui
    demander — l'echographie appelle une demande d'imagerie, le laboratoire une
    demande d'analyses. C'est le service qui porte cette nature, declaree par
    l'administrateur dans « Services ».

    Le meme partiel sert la file du medecin et celle d'un poste dedie : deux
    formulaires differents feraient arriver deux demandes differentes chez le
    technicien pour le meme geste.

    Attend : $examKind, $suffixe (pour des identifiants de champ uniques)
--}}
@php
    $suffixe ??= 'renvoi';
@endphp

@if ($examKind === \App\Models\Service::EXAM_LABORATORY)
    <fieldset class="fieldset">
        <legend>Analyses demandees</legend>

        <p class="hint">
            Cette demande part avec le patient : le laboratoire la verra
            en le recevant, et versera son compte rendu au dossier.
        </p>

        @foreach ($examExams as $index => $ligne)
            <div class="grid grid--two">
                <div class="field">
                    <label for="analyse-{{ $suffixe }}-{{ $index }}">Analyse</label>
                    <input type="text" id="analyse-{{ $suffixe }}-{{ $index }}"
                           wire:model="examExams.{{ $index }}.name"
                           placeholder="Numeration formule sanguine">
                    @error("examExams.$index.name") <p class="field__error">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="categorie-{{ $suffixe }}-{{ $index }}">
                        Categorie <span class="field__hint">(facultatif)</span>
                    </label>
                    <input type="text" id="categorie-{{ $suffixe }}-{{ $index }}"
                           wire:model="examExams.{{ $index }}.category"
                           placeholder="Hematologie">
                </div>
            </div>

            @if (count($examExams) > 1)
                <button type="button" class="btn btn--ghost btn--small"
                        wire:click="removeExamLine({{ $index }})">Retirer</button>
            @endif
        @endforeach

        @error('examExams') <p class="field__error">{{ $message }}</p> @enderror

        @if (count($examExams) < \App\Actions\Dme\OrderLaboratory::MAX_ANALYSES)
            <button type="button" class="btn btn--ghost" wire:click="addExamLine">Ajouter une analyse</button>
        @endif
    </fieldset>
@elseif ($examKind === \App\Models\Service::EXAM_IMAGING)
    <fieldset class="fieldset">
        <legend>Examen d'imagerie demande</legend>

        <p class="hint">
            Cette demande part avec le patient : le plateau la verra en le
            recevant, et versera son compte rendu au dossier.
        </p>

        <div class="grid grid--two">
            <div class="field">
                <label for="modalite-{{ $suffixe }}">Modalite</label>
                <select id="modalite-{{ $suffixe }}" wire:model="examModality">
                    @foreach (\Keneya\Dme\Models\ImagingOrder::MODALITIES as $cle => $libelle)
                        <option value="{{ $cle }}">{{ $libelle }}</option>
                    @endforeach
                </select>
                @error('examModality') <p class="field__error">{{ $message }}</p> @enderror
            </div>
            <div class="field">
                <label for="region-{{ $suffixe }}">
                    Region examinee <span class="field__hint">(facultatif)</span>
                </label>
                <input type="text" id="region-{{ $suffixe }}" wire:model="examBodySite"
                       placeholder="Abdomen, obstetricale…">
                @error('examBodySite') <p class="field__error">{{ $message }}</p> @enderror
            </div>
        </div>
    </fieldset>
@endif

@if ($examKind)
    <div class="grid grid--two">
        <div class="field">
            <label for="priorite-{{ $suffixe }}">Priorite</label>
            <select id="priorite-{{ $suffixe }}" wire:model="examPriority">
                @foreach (\App\Actions\Dme\OrderLaboratory::PRIORITIES as $cle => $libelle)
                    <option value="{{ $cle }}">{{ $libelle }}</option>
                @endforeach
            </select>
            @error('examPriority') <p class="field__error">{{ $message }}</p> @enderror
        </div>
        <div class="field">
            <label for="indication-{{ $suffixe }}">
                Indication <span class="field__hint">(facultatif)</span>
            </label>
            <input type="text" id="indication-{{ $suffixe }}" wire:model="examIndication"
                   placeholder="Pourquoi cet examen.">
            @error('examIndication') <p class="field__error">{{ $message }}</p> @enderror
        </div>
    </div>
@endif
