import { Controller } from '@hotwired/stimulus';

/*
 * Island Stimulus (AD-8) : enrichit le formulaire de recherche côté client.
 * - révèle un bouton « Effacer » quand le champ n'est pas vide ;
 * - vide le champ et lui redonne le focus au clic.
 * data-hydrated sert de preuve déterministe d'hydratation (voir le test Panther).
 */
export default class extends Controller {
    static targets = ['input', 'clear'];

    connect() {
        this.element.dataset.hydrated = 'true';
    }

    // Fixe l'état initial du bouton dès que le champ est disponible.
    inputTargetConnected() {
        this.toggleClear();
    }

    // Recalcule la visibilité du bouton à chaque frappe (data-action facultatif).
    toggleClear() {
        if (!this.hasClearTarget || !this.hasInputTarget) {
            return;
        }
        this.clearTarget.hidden = this.inputTarget.value.trim() === '';
    }

    clear() {
        this.inputTarget.value = '';
        this.toggleClear();
        this.inputTarget.focus();
    }
}
