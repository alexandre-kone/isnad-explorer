import { Controller } from '@hotwired/stimulus';

/*
 * Island Stimulus (AD-8) : hydraté côté client sur tout élément
 * portant data-controller="hello". Le marqueur data-hydrated sert de
 * preuve déterministe d'hydratation (voir HomeIslandHydrationTest).
 */
export default class extends Controller {
    connect() {
        this.element.textContent = 'JS hydraté ✓';
        this.element.dataset.hydrated = 'true';
    }
}
