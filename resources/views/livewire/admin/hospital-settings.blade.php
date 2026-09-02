{{-- Etablissement — la page de reference du systeme visuel.

     C'est ici que les composants partages sont mis a l'epreuve les premiers :
     en-tete de page avec fil d'ariane, cartes a badge d'icone, champs
     normalises, bandeau d'information. Toute autre section de l'application
     reprend ces memes composants, jamais une copie de ce balisage. --}}
<div class="pile">

    <x-page-header
        :fil="['Accueil', 'Etablissement']"
        titre="Informations de l'etablissement"
        sous-titre="Ces informations apparaitront sur toutes les ordonnances et tickets imprimes." />

    <x-card title="Informations generales" icon="batiment">
        <form wire:submit="save" class="form">
            <x-field name="hospital-name" label="Nom de l'etablissement" error="hospitalName">
                <input id="hospital-name" type="text" wire:model="hospitalName">
            </x-field>

            <x-field name="hospital-address" label="Adresse" optionnel error="hospitalAddress">
                <input id="hospital-address" type="text" wire:model="hospitalAddress"
                       placeholder="BP 98 - Kayes Plateaux">
            </x-field>

            <div class="field-row">
                <x-field name="hospital-phone" label="Telephone" optionnel error="hospitalPhone">
                    <input id="hospital-phone" type="text" wire:model="hospitalPhone"
                           placeholder="+223 21 52 12 32">
                </x-field>

                <x-field name="hospital-email" label="Courriel" optionnel error="hospitalEmail">
                    <input id="hospital-email" type="email" wire:model="hospitalEmail"
                           placeholder="contact@hopital.ml">
                </x-field>
            </div>

            <h3 class="card__subtitle">Informations complementaires</h3>

            <div class="field-row">
                <x-field name="hospital-website" label="Site web" optionnel error="hospitalWebsite">
                    <input id="hospital-website" type="text" wire:model="hospitalWebsite"
                           placeholder="www.hopital-fousseyni-daou.ml">
                </x-field>

                <x-field name="hospital-hours" label="Horaires d'ouverture" optionnel error="hospitalHours">
                    <input id="hospital-hours" type="text" wire:model="hospitalHours"
                           placeholder="Lun - Ven : 07h30 - 17h00 | Sam : 07h30 - 13h00">
                </x-field>
            </div>

            <x-field name="hospital-motto" label="Devise" optionnel error="hospitalMotto">
                <input id="hospital-motto" type="text" wire:model="hospitalMotto"
                       placeholder="Notre mission, votre sante.">
            </x-field>

            <div class="btn-row btn-row--fin">
                <button type="submit" class="btn btn--primary">
                    <x-icon name="enregistrer" size="18" />
                    Enregistrer les modifications
                </button>
            </div>
        </form>
    </x-card>

    <x-card title="Tampon de l'etablissement" icon="image">
        {{-- Le tampon institutionnel se gere ici et nulle part ailleurs. La
             signature du medecin, elle, reste dans la carte de profil de
             chaque praticien : elle l'engage personnellement, un administrateur
             ne peut pas en deposer une pour lui. --}}
        <div class="tampon">
            <div class="tampon__saisie">
                <form wire:submit="saveStamp" class="form">
                    <x-field name="hospital-stamp" label="Image du tampon"
                             hint="PNG, JPEG ou WebP. 2 Mo maximum" error="stampFile">
                        <input id="hospital-stamp" type="file"
                               accept="image/png,image/jpeg,image/webp" wire:model="stampFile">
                    </x-field>

                    <div class="btn-row">
                        <button type="submit" class="btn btn--secondary" wire:loading.attr="disabled">
                            <x-icon name="televerser" size="18" />
                            {{ $stampPresent ? 'Remplacer le tampon' : 'Deposer le tampon' }}
                        </button>
                    </div>
                </form>
            </div>

            <div class="tampon__aide">
                @if ($stampPresent)
                    <x-notice title="Bon a savoir">
                        Le tampon de l'etablissement est enregistre : il figure au bas de
                        chaque ordonnance, a cote de la signature du medecin prescripteur.
                    </x-notice>
                @else
                    <x-notice ton="alerte" title="Aucun tampon enregistre">
                        Les ordonnances s'impriment normalement, mais l'emplacement du
                        cachet reste vide.
                    </x-notice>
                @endif

                <p class="hint">
                    Toute modification du tampon est inscrite au journal d'audit :
                    c'est une piece a valeur legale.
                </p>
            </div>
        </div>
    </x-card>
</div>
