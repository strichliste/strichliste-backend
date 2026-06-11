import { Controller } from '@hotwired/stimulus';

// Disables submit buttons on submit so a kiosk double-tap posts once.
// Turbo snapshots the page before navigating; without the before-cache
// reset a back/forward restore would bring the buttons back disabled.
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
