{{--
    Bascule clair / sombre, posee dans la barre a cote de la cloche.

    Entierement dans le navigateur : le theme n'est pas une donnee metier, il
    ne regarde pas le serveur. Le choix vit dans `localStorage`, sous une cle
    propre au poste, et s'applique a `<html>` par `data-theme`. Un agent qui
    travaille de nuit sur un poste de garde le regle une fois.

    Sans choix enregistre, l'application suit le systeme : un poste configure
    en sombre s'ouvre en sombre, sans que personne ait rien a faire.

    Le script qui relit ce choix est dans l'entete de la page, avant le premier
    rendu : ici, il ne ferait que basculer une page deja peinte en clair.
--}}
<button type="button"
        class="icon-btn theme-toggle"
        data-testid="theme-toggle"
        x-data="{
            sombre: document.documentElement.dataset.theme === 'sombre',
            bascule() {
                this.sombre = ! this.sombre;
                document.documentElement.dataset.theme = this.sombre ? 'sombre' : 'clair';
                try {
                    localStorage.setItem('keneya.theme', this.sombre ? 'sombre' : 'clair');
                } catch (e) {
                    // Navigation privee, stockage refuse : le theme tient pour
                    // cette page, et c'est tout ce qu'on peut promettre.
                }
            },
        }"
        @click="bascule()"
        :aria-pressed="sombre ? 'true' : 'false'"
        :title="sombre ? 'Passer en clair' : 'Passer en sombre'"
        :aria-label="sombre ? 'Passer en mode clair' : 'Passer en mode sombre'">

    {{-- Un seul bouton, deux icones : celle qui s'affiche annonce ce vers quoi
         l'on va, pas ou l'on est. --}}
    <svg class="theme-toggle__icone" x-show="! sombre" aria-hidden="true"
         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
         stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>
    </svg>

    <svg class="theme-toggle__icone" x-show="sombre" x-cloak aria-hidden="true"
         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
         stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="4"/>
        <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>
    </svg>
</button>
