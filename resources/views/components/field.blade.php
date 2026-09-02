{{-- Champ de formulaire.

     Le libelle, la mention « facultatif », l'aide et l'erreur suivaient trois
     ecritures differentes selon les pages : tantot `(facultatif)` dans le
     libelle, tantot rien, et l'erreur parfois oubliee. Le composant fixe les
     trois.

     `name` sert a la fois d'identifiant du champ et de cle d'erreur : le
     `for` du libelle et le `@error` designent alors forcement la meme chose,
     ce qui n'etait pas garanti quand les deux etaient ecrits a la main.

     Usage :
         <x-field name="hospital-name" label="Nom de l'etablissement" error="hospitalName">
             <input id="hospital-name" type="text" wire:model="hospitalName">
         </x-field>

         <x-field name="hospital-phone" label="Telephone" optionnel error="hospitalPhone"
                  hint="Indicatif compris">
             …
         </x-field> --}}
@props([
    'name',
    'label' => null,
    'hint' => null,
    'optionnel' => false,
    'error' => null,
])

<div {{ $attributes->merge(['class' => 'field']) }}>
    @if ($label)
        <label for="{{ $name }}">
            {{ $label }}
            {{-- « Facultatif » se lit a cote du libelle, jamais dans le
                 champ : un texte de substitution disparait des qu'on tape. --}}
            @if ($optionnel)
                <span class="field__hint">(facultatif)</span>
            @endif
            @if ($hint)
                <span class="field__hint">{{ $hint }}</span>
            @endif
        </label>
    @endif

    {{ $slot }}

    @if ($error)
        @error($error) <p class="field__error">{{ $message }}</p> @enderror
    @endif
</div>
