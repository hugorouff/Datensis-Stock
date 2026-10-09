import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['panel', 'backdrop'];

    toggle() {
        this.panelTarget.classList.toggle('show');
        this.backdropTarget.classList.toggle('show');
    }

    close() {
        this.panelTarget.classList.remove('show');
        this.backdropTarget.classList.remove('show');
    }
}
