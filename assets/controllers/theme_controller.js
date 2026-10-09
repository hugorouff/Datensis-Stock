import { Controller } from '@hotwired/stimulus';

const STORAGE_KEY = 'ml2c-theme';

/*
 * Préférence d'affichage de l'utilisateur (clair, sombre, automatique), comme theme_ml2c.js dans Datensis.
 * Elle ne modifie pas la charte enregistrée pour la société.
 */
export default class extends Controller {
    static targets = ['choice'];

    connect() {
        this.media = window.matchMedia('(prefers-color-scheme: dark)');
        this.onSystemChange = () => {
            if (this.saved() === 'auto') {
                this.apply('auto');
            }
        };
        this.media.addEventListener('change', this.onSystemChange);
        this.apply(this.saved() || 'light');
    }

    disconnect() {
        this.media.removeEventListener('change', this.onSystemChange);
    }

    choose(event) {
        const theme = event.currentTarget.dataset.themeMode;
        try {
            localStorage.setItem(STORAGE_KEY, theme);
        } catch (error) {
            /* navigation privée : le choix reste valable pour cette page */
        }
        this.apply(theme);
    }

    saved() {
        try {
            return localStorage.getItem(STORAGE_KEY);
        } catch (error) {
            return null;
        }
    }

    apply(theme) {
        const dark = theme === 'dark' || (theme === 'auto' && this.media.matches);
        const root = document.documentElement;
        root.classList.toggle('theme-dark', dark);
        root.classList.toggle('theme-original', theme === 'original');
        root.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
        const selected = this.saved() || theme;
        this.choiceTargets.forEach((button) => {
            button.classList.toggle('active', button.dataset.themeMode === selected);
        });
    }
}
