import { Controller } from '@hotwired/stimulus';

/*
 * Dynamic participant rows for the split-invoice form.
 *
 * Add    → clone <template>, append, renumber, and move focus into the new
 *          row's select.
 * Remove → drop the row (always keeping at least one) and move focus to a
 *          neighbouring control so keyboard / screen-reader users aren't
 *          dropped to <body>.
 *
 * After every mutation each participant select gets a uniquely-numbered
 * accessible name ("Participant 1", "Participant 2", …) so dynamically added
 * rows aren't all announced with the same generic name (WCAG 1.3.1 / 4.1.2).
 * The label strings come from the row-label / remove-label values rendered by
 * the template (already translated).
 */
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

    // Choose a focus target *before* detaching the row the button lives in.
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
        if (this.hasRemoveLabelValue) btn.setAttribute('aria-label', this.removeLabelValue);
      }
      const select = row.querySelector('.split-invoice-pick');
      if (select && this.hasRowLabelValue) {
        select.setAttribute('aria-label', `${this.rowLabelValue} ${i + 1}`);
      }
    });
  }
}
