{{-- Parametres generaux. Ne rassemble pas tous les reglages du produit : un
     reglage se modifie la ou l'on voit ses effets. Ceux qui vivent ailleurs
     sont recenses en bas de page, pour qu'on sache ou aller les chercher. --}}
<div class="pile">

    <x-card title="Rappel des rendez-vous" icon="planning">
        <p class="hint">
            Combien de temps avant l'heure du rendez-vous le SMS de rappel part.
            Le planificateur repasse toutes les quinze minutes : un rappel peut
            donc partir jusqu'a un quart d'heure apres le delai exact.
        </p>

        <form wire:submit="save" class="form">
            <x-field name="reminder-minutes" label="Delai avant le rendez-vous"
                     hint="En minutes, de 5 a 1440" error="reminderMinutes">
                <input id="reminder-minutes" type="number" min="5" max="1440" step="5"
                       wire:model="reminderMinutes" class="input--court">
            </x-field>

            <div class="btn-row btn-row--fin">
                <button type="submit" class="btn btn--primary">
                    <x-icon name="enregistrer" size="18" />
                    Enregistrer les modifications
                </button>
            </div>
        </form>
    </x-card>

    <x-card title="Reglages geres ailleurs" icon="info">
        <x-notice title="Pourquoi ils ne sont pas ici">
            Un reglage se modifie a l'endroit ou l'on constate ses effets. Les
            rassembler dans une page unique les eloignerait de ce qu'ils
            gouvernent, et personne ne penserait a revenir ici apres avoir
            change un tarif.
        </x-notice>

        <dl class="renvois">
            <div class="renvois__ligne">
                <dt>Delai d'invitation des visiteurs a donner leur avis</dt>
                <dd>Section <strong>Retours et incidents</strong></dd>
            </div>
            <div class="renvois__ligne">
                <dt>Acte du catalogue qui vaut ticket de consultation</dt>
                <dd>Section <strong>Tarifs</strong></dd>
            </div>
            <div class="renvois__ligne">
                <dt>Nom, coordonnees et tampon de l'etablissement</dt>
                <dd>Section <strong>Etablissement</strong></dd>
            </div>
            <div class="renvois__ligne">
                <dt>Signature et tampon personnels d'un medecin</dt>
                <dd>Carte de profil du medecin concerne</dd>
            </div>
        </dl>
    </x-card>
</div>
