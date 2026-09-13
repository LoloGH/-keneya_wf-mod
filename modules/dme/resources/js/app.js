/* La feuille de style du module est importée ici : la construction du
   package n'a qu'un point d'entrée, pour produire app.js et app.css sous
   des noms stables. */
import '../css/app.css';

import './bootstrap';

import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

/*
 * Keneya-DME : couche d'interactivité.
 *
 * L'application est rendue côté serveur : Alpine ne sert qu'aux
 * comportements d'interface (tiroirs, modales, listes dynamiques,
 * notifications). Aucune règle métier ni décision d'autorisation ne
 * dépend du JavaScript : le backend reste seul juge (§32).
 */

Alpine.plugin(collapse);

/**
 * Constructeur d'ordonnance (§22) : ajout et retrait de lignes de
 * prescription sans rechargement de page.
 */
Alpine.data('prescriptionBuilder', (initial = []) => ({
    items: initial.length ? initial : [{ medication_name: '', dosage: '', form: '', route: '', frequency: '', duration: '', quantity: '', instructions: '' }],

    add() {
        this.items.push({ medication_name: '', dosage: '', form: '', route: '', frequency: '', duration: '', quantity: '', instructions: '' });
    },

    remove(index) {
        // Une ordonnance conserve toujours au moins une ligne saisissable.
        if (this.items.length > 1) {
            this.items.splice(index, 1);
        }
    },

    duplicate(index) {
        this.items.splice(index + 1, 0, { ...this.items[index] });
    },
}));

/**
 * Bloc de diagnostics d'une consultation (§19).
 */
Alpine.data('diagnosisBuilder', (initial = []) => ({
    rows: initial.length ? initial : [{ label: '', code: '', type: 'primary', status: 'suspected', comment: '' }],

    add() {
        this.rows.push({ label: '', code: '', type: 'secondary', status: 'suspected', comment: '' });
    },

    remove(index) {
        if (this.rows.length > 1) {
            this.rows.splice(index, 1);
        }
    },
}));

/**
 * Gardes planifiées à l'avance pour un compte (§60) : ajout et retrait
 * de périodes sans rechargement de page.
 */
Alpine.data('dutyPeriodBuilder', (initial = []) => ({
    periods: initial,

    add() {
        this.periods.push({ starts_at: '', ends_at: '', notes: '' });
    },

    remove(index) {
        this.periods.splice(index, 1);
    },
}));

/**
 * Recherche globale avec anti-rebond (§58) : la frappe ne déclenche une
 * navigation qu'après une pause de saisie.
 */
Alpine.data('globalSearch', () => ({
    term: '',
    timer: null,

    submitDebounced(event) {
        clearTimeout(this.timer);

        this.timer = setTimeout(() => {
            if (this.term.trim().length >= 2) {
                event.target.form.submit();
            }
        }, 350);
    },
}));

/**
 * Notifications éphémères (§50).
 */
Alpine.data('toast', (autoDismiss = 6000) => ({
    visible: true,

    init() {
        if (autoDismiss > 0) {
            setTimeout(() => { this.visible = false; }, autoDismiss);
        }
    },
}));

window.Alpine = Alpine;
Alpine.start();
