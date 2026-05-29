import { Controller } from '@hotwired/stimulus';

/*
 * Disables the form's submit button(s) immediately on submit so a rapid
 * double-tap on a kiosk step button posts exactly once.
 * Works under Turbo Drive (which still emits a `submit` event) and as a
 * full-page submit. No-JS path is unaffected; the browser still posts twice
 * on a rapid double-tap there, but the boundary check on the server keeps
 * money safe.
 *
 * Turbo caches the current page snapshot before navigating away and restores
 * it on back/forward. Without the before-cache reset below, the snapshot would
 * be captured with the buttons still disabled, leaving them permanently dead
 * on a restored page. We re-enable on turbo:before-cache to avoid that.
 */
export default class extends Controller {
  connect() {
    this.element.addEventListener('submit', this.handleSubmit);
    document.addEventListener('turbo:before-cache', this.reset);
  }

  disconnect() {
    this.element.removeEventListener('submit', this.handleSubmit);
    document.removeEventListener('turbo:before-cache', this.reset);
  }

  submitButtons() {
    return this.element.querySelectorAll('button[type="submit"], input[type="submit"]');
  }

  handleSubmit = () => {
    this.submitButtons().forEach((b) => {
      b.disabled = true;
      b.setAttribute('aria-busy', 'true');
    });
  };

  reset = () => {
    this.submitButtons().forEach((b) => {
      b.disabled = false;
      b.removeAttribute('aria-busy');
    });
  };
}
