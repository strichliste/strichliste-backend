import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'item', 'status', 'scanForm'];
    static values = { resultsLabel: String, noResultsLabel: String };

    connect() {
        // the search input ships hidden (useless without JS); the manual barcode
        // form is the no-JS scanner fallback, so hide it once JS is alive
        this.inputTarget.hidden = false;
        if (this.hasScanFormTarget) this.scanFormTarget.hidden = true;
    }

    filter() {
        const q = (this.inputTarget.value || '').trim().toLowerCase();
        let visible = 0;
        this.itemTargets.forEach((el) => {
            const match =
                q === '' || (el.dataset.articleName || '').includes(q);
            el.hidden = !match;
            if (match) visible += 1;
        });
        this.announce(q, visible);
    }

    announce(query, visible) {
        if (!this.hasStatusTarget) return;
        // stay silent until the operator types, so focusing the field
        // doesn't announce the full list
        if (query === '') {
            this.statusTarget.textContent = '';
            return;
        }
        this.statusTarget.textContent =
            visible === 0
                ? this.noResultsLabelValue
                : `${visible} ${this.resultsLabelValue}`;
    }
}
