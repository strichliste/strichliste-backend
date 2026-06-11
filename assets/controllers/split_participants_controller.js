import { Controller } from '@hotwired/stimulus';

// Dynamic participant rows for the split-invoice form. Focus follows each
// mutation (new row's select on add, a neighbour on remove) so keyboard users
// aren't dropped to <body>, and every select / remove button gets a numbered
// accessible name — identical names are indistinguishable in a SR rotor.
export default class extends Controller {
  static targets = ['list', 'template', 'add'];
  static values = { rowLabel: String, removeLabel: String };

  connect() {
    this.refresh();
  }

  add(event) {
    event.preventDefault();
    if (!this.hasTemplateTarget) return;
    const frag = this.templateTarget.content.cloneNode(true);
    this.listTarget.appendChild(frag);
    this.refresh();
    const rows = this.rows();
    const select = rows[rows.length - 1]?.querySelector('.split-invoice-pick');
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

    const focusTarget =
      prev?.querySelector('.split-invoice-pick') ||
      next?.querySelector('.split-invoice-pick') ||
      (this.hasAddTarget ? this.addTarget : null);
    if (focusTarget) focusTarget.focus();
  }

  rows() {
    return Array.from(this.listTarget.querySelectorAll('.participants__row'));
  }

  refresh() {
    const rows = this.rows();
    const showRemove = rows.length > 1;
    rows.forEach((row, i) => {
      const btn = row.querySelector('.participants__remove');
      if (btn) {
        btn.hidden = !showRemove;
        if (this.hasRemoveLabelValue) btn.setAttribute('aria-label', `${this.removeLabelValue} ${i + 1}`);
      }
      const select = row.querySelector('.split-invoice-pick');
      if (select && this.hasRowLabelValue) {
        select.setAttribute('aria-label', `${this.rowLabelValue} ${i + 1}`);
      }
    });
  }
}
