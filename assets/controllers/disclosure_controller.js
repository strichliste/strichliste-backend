import { Controller } from '@hotwired/stimulus';

// <summary> maps to a roleless "generic" for assistive tech, and Safari
// leaves it out of the tab order — so role=button, tabindex=0 and a synced
// aria-expanded. Native behavior is untouched without JS.
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
