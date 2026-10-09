import { Controller } from '@hotwired/stimulus';

const textOn = (hex) => {
    const value = String(hex || '').replace('#', '');
    if (value.length !== 6) {
        return '#ffffff';
    }
    const channels = [0, 2, 4].map((i) => {
        const channel = parseInt(value.slice(i, i + 2), 16) / 255;
        return channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
    });
    const luminance = 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
    return luminance > 0.179 ? '#0f172a' : '#ffffff';
};

/*
 * Met à jour l'aperçu (couleurs, accent, thème sombre) pendant la saisie.
 * L'interface ne change qu'après enregistrement, pour la société courante.
 */
export default class extends Controller {
    static targets = ['primary', 'secondary', 'accent', 'background', 'navbar', 'card', 'logo', 'file', 'preview', 'logoImage', 'logoFallback'];
    static values = { uploadedLogo: String };

    connect() {
        this.update();
    }

    apply(event) {
        const { primary, secondary, accent, background, navbar, card } = event.params;
        this.primaryTarget.value = primary;
        this.secondaryTarget.value = secondary;
        this.accentTarget.value = accent;
        this.backgroundTarget.value = background;
        this.navbarTarget.value = navbar;
        this.cardTarget.value = card;
        this.update();
    }

    previewFile() {
        const file = this.fileTarget.files && this.fileTarget.files[0];
        if (!file) {
            return;
        }
        const reader = new FileReader();
        reader.onload = () => {
            this.chosenFile = reader.result;
            this.update();
        };
        reader.readAsDataURL(file);
    }

    update() {
        const primary = this.primaryTarget.value;
        const secondary = this.secondaryTarget.value;
        const accent = this.accentTarget.value;
        const background = this.backgroundTarget.value;
        const navbar = this.navbarTarget.value;
        const card = this.cardTarget.value;
        const style = this.previewTarget.style;

        style.setProperty('--ml2c-primary', primary);
        style.setProperty('--ml2c-secondary', secondary);
        style.setProperty('--ml2c-tertiary', accent);
        style.setProperty('--ml2c-background', background);
        style.setProperty('--ml2c-navbar', navbar);
        style.setProperty('--ml2c-card', card);
        style.setProperty('--ml2c-on-primary', textOn(primary));
        style.setProperty('--ml2c-on-secondary', textOn(secondary));
        style.setProperty('--ml2c-on-accent', textOn(accent));
        style.setProperty('--ml2c-on-navbar', textOn(navbar));
        style.setProperty('--ml2c-on-background', textOn(background));
        style.setProperty('--ml2c-on-card', textOn(card));
        style.setProperty('--ml2c-gradient-primary', `linear-gradient(135deg, ${primary}, ${secondary})`);
        style.setProperty('--ml2c-gradient-secondary', `linear-gradient(135deg, ${secondary}, ${primary})`);
        style.setProperty('--ml2c-gradient-warm', `linear-gradient(135deg, ${accent}, ${primary})`);

        const logo = this.chosenFile || this.uploadedLogoValue || this.logoTarget.value.trim();
        this.logoImageTarget.classList.toggle('d-none', !logo);
        this.logoFallbackTarget.classList.toggle('d-none', !!logo);
        if (logo) {
            this.logoImageTarget.src = logo;
        }
    }
}
