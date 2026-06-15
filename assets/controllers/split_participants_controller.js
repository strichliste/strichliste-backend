import { Controller } from '@hotwired/stimulus';

// Dynamic participant rows for the split-invoice form. Focus follows each
// mutation (new row's select on add, a neighbour on remove) so keyboard users
// aren't dropped to <body>, and every select / remove button gets a numbered
// accessible name — identical names are indistinguishable in a SR rotor.
export default class extends Controller {
    static targets = ['list', 'template', 'add', 'preview'];
    static values = {
        rowLabel: String,
        removeLabel: String,
        previewLabel: String,
        currency: String,
    };

    connect() {
        this.refresh();
        this.onInput = () => this.updatePreview();
        this.element.addEventListener('input', this.onInput);
        this.element.addEventListener('change', this.onInput);
        this.updatePreview();
    }

    disconnect() {
        this.element.removeEventListener('input', this.onInput);
        this.element.removeEventListener('change', this.onInput);
    }

    add(event) {
        event.preventDefault();
        if (!this.hasTemplateTarget) return;
        const frag = this.templateTarget.content.cloneNode(true);
        this.listTarget.appendChild(frag);
        this.refresh();
        this.updatePreview();
        const rows = this.rows();
        const select = rows[rows.length - 1]?.querySelector(
            '.split-invoice-pick',
        );
        if (select) select.focus();
    }

    remove(event) {
        event.preventDefault();
        const row = event.currentTarget.closest('.participants__row');
        if (!row) return;
        if (this.rows().length <= 1) return; // always keep at least one row

        // pick the focus target before the row detaches
        const prev = row.previousElementSibling;
        const next = row.nextElementSibling;
        row.remove();
        this.refresh();
        this.updatePreview();

        const focusTarget =
            prev?.querySelector('.split-invoice-pick') ||
            next?.querySelector('.split-invoice-pick') ||
            (this.hasAddTarget ? this.addTarget : null);
        if (focusTarget) focusTarget.focus();
    }

    rows() {
        return Array.from(
            this.listTarget.querySelectorAll('.participants__row'),
        );
    }

    // advisory per-head figure; the server distributes the exact cents
    updatePreview() {
        if (!this.hasPreviewTarget) return;
        const raw = (this.element.querySelector('#amount')?.value || '')
            .trim()
            .replace(',', '.');
        const total = Number.parseFloat(raw);
        const people = this.rows().filter(
            (r) => r.querySelector('.split-invoice-pick')?.value,
        ).length;
        if (!Number.isFinite(total) || total <= 0 || people === 0) {
            this.previewTarget.hidden = true;
            this.previewTarget.textContent = '';
            return;
        }
        const share = `${this.currencyValue}${(total / people).toFixed(2)}`;
        this.previewTarget.textContent = this.previewLabelValue.replace(
            '%share%',
            share,
        );
        this.previewTarget.hidden = false;
    }

    refresh() {
        const rows = this.rows();
        const showRemove = rows.length > 1;
        rows.forEach((row, i) => {
            const btn = row.querySelector('.participants__remove');
            if (btn) {
                btn.hidden = !showRemove;
                if (this.hasRemoveLabelValue)
                    btn.setAttribute(
                        'aria-label',
                        `${this.removeLabelValue} ${i + 1}`,
                    );
            }
            const select = row.querySelector('.split-invoice-pick');
            if (select && this.hasRowLabelValue) {
                select.setAttribute(
                    'aria-label',
                    `${this.rowLabelValue} ${i + 1}`,
                );
            }
        });
    }
}
