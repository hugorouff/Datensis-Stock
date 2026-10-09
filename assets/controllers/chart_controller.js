import { Controller } from '@hotwired/stimulus';

const euro = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' });

/*
 * Chart.js est chargé globalement depuis le CDN (voir base.html.twig).
 */
export default class extends Controller {
    static values = {
        type: { type: String, default: 'bar' },
        labels: Array,
        values: Array,
        colors: Array,
        label: String,
        currency: Boolean,
    };

    connect() {
        const isCircular = ['doughnut', 'pie'].includes(this.typeValue);
        const format = (value) => (this.currencyValue ? euro.format(value) : value);
        const accent = getComputedStyle(this.element).getPropertyValue('--ml2c-primary').trim();

        this.chart = new window.Chart(this.element, {
            type: this.typeValue,
            data: {
                labels: this.labelsValue,
                datasets: [{
                    label: this.labelValue,
                    data: this.valuesValue,
                    backgroundColor: this.colorsValue.length ? this.colorsValue : accent,
                    borderRadius: isCircular ? 0 : 6,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: isCircular, position: 'bottom' },
                    tooltip: { callbacks: { label: (ctx) => ` ${format(ctx.parsed.y ?? ctx.parsed)}` } },
                },
                scales: isCircular ? {} : { y: { beginAtZero: true, ticks: { callback: format, precision: 0 } } },
            },
        });
    }

    disconnect() {
        this.chart?.destroy();
    }
}
