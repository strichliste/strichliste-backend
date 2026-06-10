import { Controller } from '@hotwired/stimulus';

/*
 * Filters the BUY ARTICLE pill list client-side as the operator types in the
 * search input. Each pill carries `data-article-name` (lowercased) so we match
 * against that. Without JS, the (useless) search input stays hidden and all
 * pills stay visible — the server-side barcode submit path still works.
 *
 * The visible count is mirrored into a polite live region so screen-reader and
 * keyboard users get feedback as they type — including the "no matches" case,
 * which would otherwise be a silently blank area.
 */
export default class extends Controller {
  static targets = ['input', 'item', 'status'];
  static values = { resultsLabel: String, noResultsLabel: String };

  connect() {
    // The search input is server-rendered `hidden` (it does nothing without
    // JS); reveal it now that filtering works.
    this.inputTarget.hidden = false;
  }

  filter() {
    const q = (this.inputTarget.value || '').trim().toLowerCase();
    let visible = 0;
    this.itemTargets.forEach((el) => {
      const match = q === '' || (el.dataset.articleName || '').includes(q);
      el.hidden = !match;
      if (match) visible += 1;
    });
    this.announce(q, visible);
  }

  announce(query, visible) {
    if (!this.hasStatusTarget) return;
    // Stay silent until the operator types, so focusing the field doesn't
    // announce the full list.
    if (query === '') {
      this.statusTarget.textContent = '';
      return;
    }
    this.statusTarget.textContent =
      visible === 0 ? this.noResultsLabelValue : `${visible} ${this.resultsLabelValue}`;
  }
}
