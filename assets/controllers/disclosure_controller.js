import { Controller } from '@hotwired/stimulus';

/*
 * Exposes a <details>/<summary> disclosure properly to assistive tech.
 * Engines map <summary> to a bare "generic" with no role and no
 * expanded/collapsed state, and Safari doesn't put it in the tab order at
 * all. Attached to the <details>: the summary gets role=button, a tabindex
 * for Safari, and an aria-expanded kept in sync with the native toggle.
 * Without JS the native (visual) behavior is unchanged.
 */
export default class extends Controller {
  connect() {
    this.summary = this.element.querySelector('summary');
    if (!this.summary) return;
    this.summary.setAttribute('role', 'button');
    this.summary.setAttribute('tabindex', '0');
    this.sync = () =>
      this.summary.setAttribute('aria-expanded', this.element.open ? 'true' : 'false');
    this.sync();
    this.element.addEventListener('toggle', this.sync);
  }

  disconnect() {
    if (this.sync) this.element.removeEventListener('toggle', this.sync);
  }
}
