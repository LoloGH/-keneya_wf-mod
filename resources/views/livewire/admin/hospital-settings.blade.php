<section class="card">
    <h2 class="card__title">Etablissement</h2>
    <p class="hint">Ce nom apparait dans la barre de toutes les interfaces et sur les tickets imprimes.</p>

    <form wire:submit="save" class="form form--inline-wrap">
        <div class="field">
            <label for="hospital-name">Nom de l'etablissement</label>
            <input id="hospital-name" type="text" wire:model="hospitalName">
            @error('hospitalName') <p class="field__error">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="btn btn--primary">Enregistrer</button>
    </form>
</section>
