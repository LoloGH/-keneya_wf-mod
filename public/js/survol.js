/**
 * Ouverture au survol, pour les panneaux et les menus de l'application.
 *
 * Trois gestes existaient deja pour ouvrir une carte ou un menu : le clic, le
 * clavier, le glissement. Le survol s'y ajoute sans les remplacer — il ne
 * fait qu'epargner un clic a qui tient une souris.
 *
 * Ce fichier existe pour que ce comportement soit ecrit une fois. La cloche,
 * la carte de profil, la barre laterale et ses groupes en ont tous besoin, et
 * quatre copies auraient diverge des le premier reglage de delai.
 *
 * ---------------------------------------------------------------------------
 * Ce que le survol seul fait mal, et ce que ce code corrige
 * ---------------------------------------------------------------------------
 *
 * 1. Le passage. Un curseur qui traverse la barre pour atteindre autre chose
 *    ouvrirait tout sur son trajet. D'ou le delai d'entree : le panneau
 *    n'ouvre que si l'on s'arrete. Pour la cloche, ce delai a une consequence
 *    qui depasse l'affichage — ouvrir le panneau marque les notifications
 *    comme lues, et un effleurement ne doit pas vider le badge.
 *
 * 2. Le vide entre le bouton et son panneau. Une carte est posee un demi-pas
 *    sous son bouton ; le curseur qui descend de l'un vers l'autre quitte donc
 *    la zone, et une fermeture immediate refermerait la carte juste avant
 *    qu'on l'atteigne. D'ou le delai de sortie, qui pardonne la traversee.
 *
 * 3. Le doigt. Sur une tablette, un appui emet un survol synthetique : sans
 *    garde, le menu s'ouvrirait et se refermerait au meme geste. Seule une
 *    souris declenche donc quoi que ce soit ici — `pointerType` le dit, et le
 *    dit mieux qu'une requete de media, qui ne sait pas distinguer deux
 *    pointeurs sur la meme machine.
 *
 * 4. Ce qu'on est en train de faire. Une carte qui se referme alors qu'on
 *    saisit un mot de passe dedans perd la saisie. Tant que le clavier est
 *    dans le panneau, ou que l'appelant declare une raison de le retenir, la
 *    fermeture automatique n'a pas lieu. Le clic de fermeture, lui, marche
 *    toujours.
 *
 * ---------------------------------------------------------------------------
 * Usage
 * ---------------------------------------------------------------------------
 *
 *   x-data="{ ...survol(), ouvert: false,
 *             survolOuvre() { this.ouvert = true },
 *             survolFerme() { this.ouvert = false } }"
 *   @pointerenter="survolEntre($event)"
 *   @pointerleave="survolSort($event)"
 *
 * `survolRetenu()` est facultative : elle repond « ne ferme pas maintenant ».
 *
 * Le composant est expose en global plutot qu'enregistre par `Alpine.data` :
 * il se melange ainsi a un `x-data` existant par simple decomposition, sans
 * avoir a deplacer l'etat deja ecrit ailleurs, et sans dependre de l'instant
 * ou Alpine demarre.
 */
window.survol = function (reglages = {}) {
    return {
        /* Le panneau est-il ouvert du fait du survol ? Un panneau ouvert au
           clic n'appartient pas a ce code et ne doit pas se refermer parce que
           le curseur passe au large. */
        survolTenu: false,

        /* Un seul compte a rebours a la fois : entrer annule la sortie en
           cours, et inversement. Sans cela, un aller-retour rapide laisse deux
           minuteries en concurrence et le dernier arrive gagne, au hasard. */
        survolMinuteur: null,

        /* L'element reellement survole. `$root` designerait toute l'ile Alpine
           — pour la barre laterale, cela inclurait le panneau de contenu, et
           le clavier pose n'importe ou dans la page retiendrait la barre
           ouverte. */
        survolCible: null,

        /* Un delai d'entree nul ouvre sur-le-champ, sans passer par une
           minuterie : c'est le reglage des panneaux de la barre du haut, ou
           l'attente se remarquait plus que le passage ne genait.
           320 ms a la sortie, partout : de quoi franchir le vide sous un
           bouton sans laisser un panneau trainer derriere soi. */
        survolDelaiEntree: reglages.entree ?? 180,
        survolDelaiSortie: reglages.sortie ?? 320,

        survolSouris(e) {
            // `pointerType` est vide sur quelques navigateurs anciens : dans le
            // doute on accepte, le pire cas etant le comportement d'avant.
            return ! e || ! e.pointerType || e.pointerType === 'mouse';
        },

        survolEntre(e) {
            if (! this.survolSouris(e)) return;

            this.survolCible = e?.currentTarget ?? this.survolCible;
            clearTimeout(this.survolMinuteur);

            const ouvrir = () => {
                this.survolTenu = true;
                this.survolOuvre?.();
            };

            // `setTimeout(..., 0)` n'est pas « tout de suite » : il rend la
            // main au navigateur, et l'ouverture arrive au tour suivant. Sur un
            // panneau qui demande ensuite une reponse au serveur, ce tour perdu
            // s'ajoute a l'aller-retour et se voit.
            if (this.survolDelaiEntree <= 0) return ouvrir();

            this.survolMinuteur = setTimeout(ouvrir, this.survolDelaiEntree);
        },

        survolSort(e) {
            if (! this.survolSouris(e)) return;

            clearTimeout(this.survolMinuteur);

            this.survolMinuteur = setTimeout(() => {
                if (this.survolRetient()) return;

                this.survolTenu = false;
                this.survolFerme?.();
            }, this.survolDelaiSortie);
        },

        /** Raisons de ne pas refermer : le clavier est dedans, ou l'appelant s'y oppose. */
        survolRetient() {
            if (this.survolCible?.contains(document.activeElement)) return true;

            return this.survolRetenu?.() === true;
        },

        /* Une page quittee ne doit pas laisser une minuterie tirer sur un
           composant demonte : Livewire remplace des morceaux de DOM a chaque
           reponse, et l'erreur qui en resulterait n'aurait aucun rapport
           visible avec un survol. */
        destroy() {
            clearTimeout(this.survolMinuteur);
        },
    };
};

/* ---------------------------------------------------------------------------
 * Les replis des pages : <details>
 * ---------------------------------------------------------------------------
 *
 * Le langage de balisage a son propre element pour cela — « Prescrire un
 * soin », « Voir les valeurs sous forme de tableau », le choix de langue de
 * l'ecran de connexion. Ce sont des menus comme les autres du point de vue de
 * celui qui s'en sert, et ils doivent s'ouvrir de la meme facon.
 *
 * L'ecoute est posee une fois sur le document, et non sur chaque repli : un
 * repli rendu plus tard — par Livewire, par une page ajoutee l'an prochain —
 * est couvert sans que personne ait a y penser. C'est aussi ce qui evite
 * d'avoir a repeter deux attributs sur chaque `<details>` du depot.
 *
 * `pointerover` et `pointerout` plutot que `enter`/`leave` : eux remontent, et
 * une ecoute unique sur le document ne verrait jamais les autres.
 *
 * Trois reserves, et elles comptent :
 *
 *  - Un repli marque `data-survol="non"` est laisse tranquille. C'est la pour
 *    ce qui se replie par prudence et non par economie de place : la
 *    suppression definitive d'un dossier medical est pliee pour qu'on ait a la
 *    demander, et un curseur qui passe n'est pas une demande.
 *
 *  - Ce qu'on a ouvert d'un clic ne se referme pas au survol. Le clic est un
 *    choix, il n'appartient pas a ce code de le defaire.
 *
 *  - Un repli qui contient le clavier reste ouvert. Plusieurs abritent un
 *    formulaire, et se refermer sous une saisie en cours la perdrait.
 */
(function () {
    const ENTREE = 180;
    const SORTIE = 320;

    // La minuterie est rangee par element : deux replis voisins ne doivent pas
    // se partager un compte a rebours, sinon entrer dans le second annule la
    // fermeture du premier, qui reste ouvert derriere soi.
    const minuteries = new WeakMap();

    function programme(repli, delai, action) {
        clearTimeout(minuteries.get(repli));
        minuteries.set(repli, setTimeout(action, delai));
    }

    function concerne(e) {
        if (e.pointerType && e.pointerType !== 'mouse') return null;

        const repli = e.target instanceof Element ? e.target.closest('details') : null;

        return repli && repli.dataset.survol !== 'non' ? repli : null;
    }

    document.addEventListener('pointerover', function (e) {
        const repli = concerne(e);
        if (! repli || repli.open) return;

        programme(repli, ENTREE, () => {
            repli.open = true;
            // La marque dit « c'est le survol qui l'a ouvert », et elle seule
            // autorise la fermeture automatique plus tard.
            repli.dataset.survolOuvert = '1';
        });
    });

    document.addEventListener('pointerout', function (e) {
        const repli = concerne(e);
        if (! repli) return;

        // `pointerout` se declenche aussi en passant d'un enfant a un autre a
        // l'interieur du repli : on ne sort que si la destination lui est
        // etrangere.
        if (e.relatedTarget instanceof Node && repli.contains(e.relatedTarget)) return;

        clearTimeout(minuteries.get(repli));
        if (repli.dataset.survolOuvert !== '1') return;

        programme(repli, SORTIE, () => {
            if (repli.contains(document.activeElement)) return;

            repli.open = false;
            delete repli.dataset.survolOuvert;
        });
    });

    // Un clic sur le resume fait du repli une decision : il cesse de suivre le
    // curseur, dans un sens comme dans l'autre.
    document.addEventListener('click', function (e) {
        const resume = e.target instanceof Element ? e.target.closest('summary') : null;
        const repli = resume?.parentElement;

        if (! repli || repli.tagName !== 'DETAILS') return;

        clearTimeout(minuteries.get(repli));
        delete repli.dataset.survolOuvert;
    });
})();
