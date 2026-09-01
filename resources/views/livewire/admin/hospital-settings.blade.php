<section class="card">
    <h2 class="card__title">Etablissement</h2>
    <p class="hint">
        Le nom apparait dans la barre de toutes les interfaces et sur les
        tickets imprimes. L'adresse, le telephone et le courriel forment
        l'en-tete des ordonnances : ils ne sont ecrits nulle part dans le code.
    </p>

    <form wire:submit="save" class="form">
        <div class="field">
            <label for="hospital-name">Nom de l'etablissement</label>
            <input id="hospital-name" type="text" wire:model="hospitalName">
            @error('hospitalName') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="hospital-address">Adresse <span class="field__hint">(facultatif)</span></label>
            <input id="hospital-address" type="text" wire:model="hospitalAddress"
                   placeholder="Quartier Legal Segou, Kayes">
            @error('hospitalAddress') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <div class="field-row">
            <div class="field">
                <label for="hospital-phone">Telephone <span class="field__hint">(facultatif)</span></label>
                <input id="hospital-phone" type="text" wire:model="hospitalPhone" placeholder="+223 21 52 00 00">
                @error('hospitalPhone') <p class="field__error">{{ $message }}</p> @enderror
            </div>

            <div class="field">
                <label for="hospital-email">Courriel <span class="field__hint">(facultatif)</span></label>
                <input id="hospital-email" type="email" wire:model="hospitalEmail" placeholder="contact@hopital.ml">
                @error('hospitalEmail') <p class="field__error">{{ $message }}</p> @enderror
            </div>
        </div>

        <button type="submit" class="btn btn--primary">Enregistrer</button>
    </form>

    <h3 class="card__subtitle">Tampon de l'etablissement</h3>
    <p class="hint">
        Appose au bas de chaque ordonnance, a cote de la signature du medecin
        prescripteur. Il se gere ici et nulle part ailleurs : c'est un element
        institutionnel, pas la propriete d'un praticien. Toute modification est
        inscrite au journal d'audit.
    </p>

    <form wire:submit="saveStamp" class="form form--inline-wrap">
        <div class="field">
            <label for="hospital-stamp">
                Image du tampon
                <span class="field__hint">PNG, JPEG ou WebP. 2 Mo maximum</span>
            </label>
            <input id="hospital-stamp" type="file" accept="image/png,image/jpeg,image/webp" wire:model="stampFile">
            @error('stampFile') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="btn btn--secondary" wire:loading.attr="disabled">
            {{ $stampPresent ? 'Remplacer le tampon' : 'Deposer le tampon' }}
        </button>
    </form>

    @if ($stampPresent)
        <p class="hint">Un tampon est enregistre : il figure sur les ordonnances generees.</p>
    @else
        <p class="hint hint--blocking">
            Aucun tampon enregistre : les ordonnances laissent l'emplacement vide.
        </p>
    @endif
</section>
